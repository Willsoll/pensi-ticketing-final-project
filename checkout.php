<?php
/**
 * checkout.php - Checkout dengan Live Countdown Timer 15 Menit & Upload Bukti Bayar
 * Hanya role pembeli, milik user sendiri
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/middleware.php';

require_role(['pembeli']);
release_expired_transactions($pdo);

$user = current_user();
$kode_raw       = $_GET['kode'] ?? $_POST['kode_transaksi'] ?? '';
$kode_transaksi = is_string($kode_raw) ? mb_substr(trim($kode_raw), 0, 64) : '';

if ($kode_transaksi === '') {
    set_flash('ERROR', 'Kode transaksi tidak ditemukan.');
    header('Location: dashboard_peserta.php');
    exit;
}

// Ambil data pemesanan + detail tiket
$stmt = $pdo->prepare("
    SELECT p.*, j.nama_jenis, j.harga, j.tipe_tiket
    FROM pemesanan p
    JOIN jenis_tiket j ON p.id_jenis = j.id_jenis
    WHERE p.kode_transaksi = ? AND p.id_user = ?
    LIMIT 1
");
$stmt->execute([$kode_transaksi, (int)$user['id_user']]);
$pesanan = $stmt->fetch();

if (!$pesanan) {
    set_flash('ERROR', 'Pesanan tidak ditemukan atau bukan milik akun Anda.');
    header('Location: dashboard_peserta.php');
    exit;
}

// Ambil detail tiket
$stmtDet = $pdo->prepare("
    SELECT dts.*, kp.nomor_kursi
    FROM detail_tiket_satuan dts
    LEFT JOIN kursi_pensi kp ON dts.id_kursi = kp.id_kursi
    WHERE dts.id_pesan = ?
    ORDER BY dts.id_detail_tiket ASC
");
$stmtDet->execute([(int)$pesanan['id_pesan']]);
$detail_tiket = $stmtDet->fetchAll();

$upload_error = '';

// Penanda khusus: deadline 15 menit habis saat upload sedang diproses.
class CheckoutExpiredException extends RuntimeException
{
}

/**
 * Hapus bukti lama hanya jika path berada di uploads/bukti dan namanya aman.
 * Mencegah path traversal / penghapusan berkas lain lewat data DB.
 */
function checkout_remove_old_proof(?string $rel_path): void
{
    if (!is_string($rel_path) || $rel_path === '') {
        return;
    }
    if (!preg_match('#^uploads/bukti/[A-Za-z0-9._-]+$#', $rel_path)) {
        return;
    }
    $target = __DIR__ . '/' . $rel_path;
    if (is_file($target)) {
        @unlink($target);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'upload_bukti') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $upload_error = 'Token keamanan (CSRF) tidak valid.';
    } elseif (!in_array($pesanan['status_bayar'], ['Menunggu Bayar', 'Ditolak'], true)) {
        $upload_error = 'Status pesanan saat ini (' . $pesanan['status_bayar'] . ') tidak dapat mengunggah ulang bukti bayar.';
    } elseif (!isset($_FILES['bukti_bayar']) || !is_array($_FILES['bukti_bayar']) || $_FILES['bukti_bayar']['error'] !== UPLOAD_ERR_OK) {
        $upload_error = 'Silakan pilih file bukti transfer terlebih dahulu.';
    } else {
        $file = $_FILES['bukti_bayar'];

        if ((int)$file['size'] <= 0 || (int)$file['size'] > UPLOAD_MAX_SIZE) {
            $upload_error = 'Ukuran file bukti bayar maksimal 2 MB.';
        } else {
            // Security: reject double extensions (e.g., shell.php.jpg)
            $original_name = (string)($file['name'] ?? '');
            if ($original_name === '' || substr_count($original_name, '.') > 1) {
                $upload_error = 'Nama file tidak boleh mengandung lebih dari satu titik (double extension tidak diperbolehkan).';
            } elseif (!is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
                // Pastikan file benar-benar berasal dari upload HTTP, bukan path lokal.
                error_log('[CHECKOUT_UPLOAD] tmp_name bukan file upload yang sah.');
                $upload_error = 'File bukti bayar tidak valid.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->file($file['tmp_name']);

                if (!is_string($mime) || !isset(UPLOAD_ALLOWED_MIME[$mime])) {
                    $upload_error = 'Format file wajib JPG, PNG, atau WEBP.';
                } else {
                    $ext = UPLOAD_ALLOWED_MIME[$mime];

                    // Pastikan isi file benar-benar gambar (bukan polyglot/script).
                    $img_info = @getimagesize($file['tmp_name']);
                    $allowed_types = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
                    if ($img_info === false || !in_array($img_info[2] ?? null, $allowed_types, true)) {
                        $upload_error = 'File yang diunggah bukan gambar JPG/PNG/WEBP yang valid.';
                    } else {
                        // Tolak image-polyglot yang menyisipkan tag PHP/script di metadata.
                        $raw_probe = @file_get_contents($file['tmp_name']);
                        if (!is_string($raw_probe)
                            || stripos($raw_probe, '<?php') !== false
                            || strpos($raw_probe, '<?=') !== false) {
                            $upload_error = 'File gambar mengandung kode yang tidak diizinkan.';
                        } else {
                        // Nama acak 128-bit agar tidak dapat ditebak/di-collision.
                        $fname    = 'bukti_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $kode_transaksi)) . '_' . bin2hex(random_bytes(16)) . '.' . $ext;
                        $rel_path = 'uploads/bukti/' . $fname;
                        $abs_path = __DIR__ . '/' . $rel_path;
                        $old_proof = (string)($pesanan['bukti_bayar'] ?? '');

                        if (!move_uploaded_file($file['tmp_name'], $abs_path)) {
                            $upload_error = 'Gagal menyimpan file bukti bayar ke direktori server.';
                        } else {
                            // Named lock per NIK (sama seperti pemesanan.php) agar
                            // re-aktivasi pesanan Ditolak tidak balapan dengan order baru.
                            $nik_locks = [];
                            try {
                                $unique_niks = [];
                                foreach ($detail_tiket as $dtRow) {
                                    $nik = (string)($dtRow['nik_pemegang_tiket'] ?? '');
                                    if ($nik !== '') {
                                        $unique_niks[$nik] = true;
                                    }
                                }
                                $unique_niks = array_keys($unique_niks);
                                sort($unique_niks, SORT_STRING);
                                foreach ($unique_niks as $nik) {
                                    $lock_name = 'pensi_nik_' . hash('sha256', $nik);
                                    $stmtLockNik = $pdo->prepare("SELECT GET_LOCK(?, 5)");
                                    $stmtLockNik->execute([$lock_name]);
                                    if ((int)$stmtLockNik->fetchColumn() !== 1) {
                                        throw new DomainException('Sistem sedang memproses data identitas lain. Silakan coba lagi.');
                                    }
                                    $nik_locks[] = $lock_name;
                                }

                                $pdo->beginTransaction();

                                // Kunci pesanan; status dapat berubah sejak halaman dimuat.
                                $stmtLock = $pdo->prepare("SELECT * FROM pemesanan WHERE id_pesan = ? AND id_user = ? FOR UPDATE");
                                $stmtLock->execute([(int)$pesanan['id_pesan'], (int)$user['id_user']]);
                                $locked_order = $stmtLock->fetch();

                                if (!$locked_order) {
                                    throw new DomainException('Pesanan tidak ditemukan atau bukan milik akun Anda.');
                                }
                                if (!in_array($locked_order['status_bayar'], ['Menunggu Bayar', 'Ditolak'], true)) {
                                    throw new DomainException('Status pesanan telah berubah (' . $locked_order['status_bayar'] . '). Silakan muat ulang halaman.');
                                }
                                if ($locked_order['status_bayar'] === 'Menunggu Bayar'
                                    && strtotime((string)$locked_order['batas_waktu_bayar']) < time()) {
                                    throw new CheckoutExpiredException('Batas waktu pembayaran 15 menit telah berakhir.');
                                }

                                $is_reactivation = ($locked_order['status_bayar'] === 'Ditolak');
                                $qty_order = (int)$locked_order['jumlah'];

                                if ($is_reactivation) {
                                    // Serialisasi dengan pembuatan pesanan baru oleh user yang sama.
                                    $stmtLockUser = $pdo->prepare("SELECT id_user FROM users WHERE id_user = ? FOR UPDATE");
                                    $stmtLockUser->execute([(int)$user['id_user']]);
                                    if (!$stmtLockUser->fetchColumn()) {
                                        throw new DomainException('Akun Anda tidak ditemukan.');
                                    }

                                    // Batas 5 tiket, tidak menghitung pesanan ini (sedang Ditolak).
                                    $stmtLimit = $pdo->prepare("
                                        SELECT COALESCE(SUM(jumlah), 0)
                                        FROM pemesanan
                                        WHERE id_user = ? AND id_pesan <> ?
                                          AND status_bayar IN ('Lunas', 'Menunggu Bayar', 'Menunggu Verifikasi')
                                    ");
                                    $stmtLimit->execute([(int)$user['id_user'], (int)$locked_order['id_pesan']]);
                                    if ((int)$stmtLimit->fetchColumn() + $qty_order > 5) {
                                        throw new DomainException('Batas maksimal 5 tiket per akun akan terlampaui. Selesaikan/lepas tiket lain terlebih dahulu.');
                                    }

                                    // Maksimal 1 reservasi aktif, tidak menghitung pesanan ini.
                                    $stmtActive = $pdo->prepare("
                                        SELECT COUNT(*)
                                        FROM pemesanan
                                        WHERE id_user = ? AND id_pesan <> ?
                                          AND status_bayar IN ('Menunggu Bayar', 'Menunggu Verifikasi')
                                    ");
                                    $stmtActive->execute([(int)$user['id_user'], (int)$locked_order['id_pesan']]);
                                    if ((int)$stmtActive->fetchColumn() > 0) {
                                        throw new DomainException('Anda masih memiliki reservasi aktif lain. Selesaikan pembayaran/verifikasi terlebih dahulu sebelum mengunggah ulang.');
                                    }

                                    // Kunci data master & validasi stok terbaru.
                                    $stmtJ = $pdo->prepare("SELECT * FROM jenis_tiket WHERE id_jenis = ? FOR UPDATE");
                                    $stmtJ->execute([(int)$locked_order['id_jenis']]);
                                    $jenis_locked = $stmtJ->fetch();
                                    if (!$jenis_locked) {
                                        throw new DomainException('Jenis tiket tidak ditemukan.');
                                    }
                                    if ((int)$jenis_locked['stok_tersedia'] < $qty_order) {
                                        throw new DomainException('Stok tiket sudah tidak mencukupi untuk mengaktifkan kembali pesanan ini.');
                                    }

                                    // Detail tiket (kursi + NIK), kursi diurutkan menaik (anti-deadlock).
                                    $stmtDet = $pdo->prepare("
                                        SELECT id_detail_tiket, id_kursi, nik_pemegang_tiket
                                        FROM detail_tiket_satuan
                                        WHERE id_pesan = ?
                                        ORDER BY (id_kursi IS NULL), id_kursi ASC, id_detail_tiket ASC
                                    ");
                                    $stmtDet->execute([(int)$locked_order['id_pesan']]);
                                    $details_locked = $stmtDet->fetchAll();

                                    if (count($details_locked) !== $qty_order) {
                                        throw new DomainException('Data tiket pesanan tidak konsisten. Hubungi panitia.');
                                    }

                                    if ((string)$jenis_locked['tipe_tiket'] === 'Seated') {
                                        foreach ($details_locked as $dRow) {
                                            if (empty($dRow['id_kursi'])) {
                                                throw new DomainException('Data kursi pesanan tidak lengkap. Hubungi panitia.');
                                            }
                                            $seat_id = (int)$dRow['id_kursi'];
                                            $stmtSeat = $pdo->prepare("
                                                SELECT id_kursi, nomor_kursi, status_kursi
                                                FROM kursi_pensi
                                                WHERE id_kursi = ? AND id_jenis = ?
                                                FOR UPDATE
                                            ");
                                            $stmtSeat->execute([$seat_id, (int)$locked_order['id_jenis']]);
                                            $seat_row = $stmtSeat->fetch();
                                            if (!$seat_row || $seat_row['status_kursi'] !== 'Available') {
                                                $nomor = $seat_row['nomor_kursi'] ?? ('#' . $seat_id);
                                                throw new DomainException("Kursi {$nomor} sudah dipesan peserta lain. Silakan buat pesanan baru.");
                                            }
                                        }
                                    }

                                    // NIK unik terhadap tiket aktif lain (selain pesanan ini).
                                    foreach ($details_locked as $dRow) {
                                        $stmtDup = $pdo->prepare("
                                            SELECT COUNT(*)
                                            FROM detail_tiket_satuan dts
                                            JOIN pemesanan p ON dts.id_pesan = p.id_pesan
                                            WHERE dts.nik_pemegang_tiket = ?
                                              AND dts.id_pesan <> ?
                                              AND p.status_bayar IN ('Menunggu Bayar', 'Menunggu Verifikasi', 'Lunas')
                                        ");
                                        $stmtDup->execute([(string)$dRow['nik_pemegang_tiket'], (int)$locked_order['id_pesan']]);
                                        if ((int)$stmtDup->fetchColumn() > 0) {
                                            throw new DomainException('NIS/NIK pada pesanan ini sudah dipakai tiket aktif lain. Hubungi panitia.');
                                        }
                                    }

                                    // Kuota promo: masih tersedia, belum kadaluarsa, tidak dipakai order aktif lain.
                                    if (!empty($locked_order['id_promo'])) {
                                        $stmtPromo = $pdo->prepare("
                                            SELECT id_promo, kuota_voucher, berlaku_sampai
                                            FROM kode_promo
                                            WHERE id_promo = ?
                                            FOR UPDATE
                                        ");
                                        $stmtPromo->execute([(int)$locked_order['id_promo']]);
                                        $promo_row = $stmtPromo->fetch();
                                        if (!$promo_row
                                            || (int)$promo_row['kuota_voucher'] <= 0
                                            || (string)$promo_row['berlaku_sampai'] < date('Y-m-d')) {
                                            throw new DomainException('Kuota/kode promo pada pesanan ini sudah tidak tersedia. Hubungi panitia.');
                                        }
                                        $stmtPromoUse = $pdo->prepare("
                                            SELECT COUNT(*)
                                            FROM pemesanan
                                            WHERE id_user = ? AND id_promo = ? AND id_pesan <> ?
                                              AND status_bayar IN ('Menunggu Bayar', 'Menunggu Verifikasi', 'Lunas')
                                        ");
                                        $stmtPromoUse->execute([(int)$user['id_user'], (int)$locked_order['id_promo'], (int)$locked_order['id_pesan']]);
                                        if ((int)$stmtPromoUse->fetchColumn() > 0) {
                                            throw new DomainException('Kode promo ini sudah dipakai pada pesanan aktif lain. Hubungi panitia.');
                                        }
                                        $updPromo = $pdo->prepare("UPDATE kode_promo SET kuota_voucher = kuota_voucher - 1 WHERE id_promo = ? AND kuota_voucher > 0");
                                        $updPromo->execute([(int)$locked_order['id_promo']]);
                                        if ($updPromo->rowCount() !== 1) {
                                            throw new DomainException('Kuota promo baru saja habis. Hubungi panitia.');
                                        }
                                    }

                                    // Reservasi ulang stok.
                                    $updJenis = $pdo->prepare("
                                        UPDATE jenis_tiket
                                        SET stok_tersedia = stok_tersedia - ?,
                                            stok_tertahan = stok_tertahan + ?
                                        WHERE id_jenis = ? AND stok_tersedia >= ?
                                    ");
                                    $updJenis->execute([$qty_order, $qty_order, (int)$locked_order['id_jenis'], $qty_order]);
                                    if ($updJenis->rowCount() !== 1) {
                                        throw new DomainException('Stok tiket berubah saat diproses. Silakan coba lagi.');
                                    }

                                    // Reservasi ulang kursi (Available -> Locked).
                                    if ((string)$jenis_locked['tipe_tiket'] === 'Seated') {
                                        foreach ($details_locked as $dRow) {
                                            $updKursi = $pdo->prepare("UPDATE kursi_pensi SET status_kursi = 'Locked' WHERE id_kursi = ? AND status_kursi = 'Available'");
                                            $updKursi->execute([(int)$dRow['id_kursi']]);
                                            if ($updKursi->rowCount() !== 1) {
                                                throw new DomainException('Kursi baru saja diambil peserta lain. Silakan buat pesanan baru.');
                                            }
                                        }
                                    }
                                }

                                $updPesan = $pdo->prepare("
                                    UPDATE pemesanan
                                    SET bukti_bayar = ?, status_bayar = 'Menunggu Verifikasi', catatan_penolakan = NULL
                                    WHERE id_pesan = ? AND status_bayar IN ('Menunggu Bayar', 'Ditolak')
                                ");
                                $updPesan->execute([$rel_path, (int)$locked_order['id_pesan']]);
                                if ($updPesan->rowCount() !== 1) {
                                    throw new DomainException('Status pesanan berubah saat upload. Silakan muat ulang halaman.');
                                }

                                if ($is_reactivation) {
                                    catat_audit_log(
                                        $pdo,
                                        (int)$user['id_user'],
                                        'REAKTIVASI_UPLOAD_BUKTI',
                                        'pemesanan',
                                        "Pembeli mengunggah ulang bukti transfer ({$fname}) untuk pesanan Ditolak {$locked_order['kode_transaksi']}. Stok, kursi, dan kuota promo direservasi ulang; status menjadi Menunggu Verifikasi."
                                    );
                                } else {
                                    catat_audit_log(
                                        $pdo,
                                        (int)$user['id_user'],
                                        'UPLOAD_BUKTI_BAYAR',
                                        'pemesanan',
                                        "Pembeli mengunggah bukti transfer ({$fname}) untuk pesanan {$locked_order['kode_transaksi']}. Status menjadi Menunggu Verifikasi."
                                    );
                                }

                                $pdo->commit();

                                // Hapus bukti lama setelah commit (jika ada & berbeda).
                                if ($old_proof !== '' && $old_proof !== $rel_path) {
                                    checkout_remove_old_proof($old_proof);
                                }

                                set_flash('SUKSES', $is_reactivation
                                    ? 'Bukti transfer berhasil diunggah ulang! Stok, kursi, dan promo telah direservasi kembali; menunggu verifikasi Panitia Keuangan.'
                                    : 'Bukti transfer berhasil diunggah! Menunggu verifikasi Panitia Keuangan.');
                                header('Location: checkout.php?kode=' . urlencode($locked_order['kode_transaksi']));
                                exit;

                            } catch (CheckoutExpiredException $e) {
                                if ($pdo->inTransaction()) {
                                    $pdo->rollBack();
                                }
                                @unlink($abs_path);
                                release_expired_transactions($pdo);
                                set_flash('ERROR', 'Batas waktu pembayaran 15 menit telah berakhir. Pesanan otomatis menjadi Kadaluarsa.');
                                header('Location: dashboard_peserta.php');
                                exit;
                            } catch (DomainException $e) {
                                if ($pdo->inTransaction()) {
                                    $pdo->rollBack();
                                }
                                @unlink($abs_path);
                                $upload_error = $e->getMessage();
                            } catch (Throwable $e) {
                                if ($pdo->inTransaction()) {
                                    $pdo->rollBack();
                                }
                                @unlink($abs_path);
                                error_log('[CHECKOUT_UPLOAD] ' . $e->getMessage());
                                $upload_error = 'Terjadi kesalahan sistem saat menyimpan bukti bayar. Silakan coba lagi.';
                            } finally {
                                foreach ($nik_locks as $lock_name) {
                                    try {
                                        $stmtRelease = $pdo->prepare("SELECT RELEASE_LOCK(?)");
                                        $stmtRelease->execute([$lock_name]);
                                    } catch (Throwable $ignored) {
                                        // abaikan kegagalan pelepasan lock; koneksi menutup otomatis
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
}

// Countdown deadline (milliseconds untuk JS)
$batas_ms = strtotime($pesanan['batas_waktu_bayar']) * 1000;

$page_title = 'Checkout & Pembayaran - ' . $kode_transaksi;
require_once __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb">
    <a href="index.php">Katalog</a> > 
    <a href="dashboard_peserta.php">Dashboard Peserta</a> > 
    Checkout: <?= e($kode_transaksi) ?>
</div>

<h2 style="color: #4F46E5; margin-bottom: 8px;">Checkout & Pembayaran Tiket</h2>

<?php if ($upload_error !== ''): ?>
    <div class="flash ERROR"><?= e($upload_error) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3 style="margin: 0;">Status Pesanan & Live Countdown Timer (15 Menit)</h3>
    </div>
    
    <table style="border: none; width: 100%;">
        <tr style="border: none;">
            <td style="width: 200px; border: none;"><strong>Kode Transaksi</strong></td>
            <td style="border: none;"><code><?= e($pesanan['kode_transaksi']) ?></code></td>
        </tr>
        <tr style="border: none;">
            <td style="border: none;"><strong>Jenis Tiket</strong></td>
            <td style="border: none;"><?= e($pesanan['nama_jenis']) ?> (<?= e($pesanan['tipe_tiket']) ?>)</td>
        </tr>
        <tr style="border: none;">
            <td style="border: none;"><strong>Status Pembayaran</strong></td>
            <td style="border: none;">
                <span class="badge badge-<?= strtolower(str_replace(' ', '-', $pesanan['status_bayar'])) ?>">
                    <?= e(strtoupper($pesanan['status_bayar'])) ?>
                </span>
            </td>
        </tr>
        <?php if (!empty($pesanan['catatan_penolakan'])): ?>
        <tr style="border: none;">
            <td style="border: none;"><strong>Catatan Penolakan</strong></td>
            <td style="border: none; color: #E11D48;"><strong><?= e($pesanan['catatan_penolakan']) ?></strong></td>
        </tr>
        <?php endif; ?>
        <?php if ($pesanan['status_bayar'] === 'Menunggu Bayar'): ?>
        <tr style="border: none;">
            <td style="border: none;"><strong>Batas Akhir Pembayaran</strong></td>
            <td style="border: none;"><?= e(date('d-m-Y H:i:s', strtotime($pesanan['batas_waktu_bayar']))) ?> WIB</td>
        </tr>
        <tr style="border: none;">
            <td style="border: none;"><strong>Sisa Waktu</strong></td>
            <td style="border: none;">
                <div class="countdown" id="live_countdown">Menghitung...</div>
                <small style="color: #6B7280;">(Pesanan & kursi otomatis dilepas jika waktu habis)</small>
            </td>
        </tr>
        <?php endif; ?>
    </table>
</div>

<br>

<table style="border: none; width: 100%;">
    <tr valign="top" style="border: none;">
        <!-- RINCIAN BIAYA -->
        <td style="width: 55%; border: none; padding-right: 16px;">
            <div class="card">
                <div class="card-header">
                    <h3 style="margin: 0;">Rincian Kalkulasi Biaya</h3>
                </div>
                <table style="border: none; width: 100%;">
                    <tr style="border: none;">
                        <td style="border: none;">Event</td>
                        <td style="text-align: right; border: none;"><strong>Pensi School 2026</strong></td>
                    </tr>
                    <tr style="border: none;">
                        <td style="border: none;">Jenis Tiket</td>
                        <td style="text-align: right; border: none;"><strong><?= e($pesanan['nama_jenis']) ?></strong></td>
                    </tr>
                    <tr style="border: none;">
                        <td style="border: none;">Jumlah Tiket</td>
                        <td style="text-align: right; border: none;"><?= (int)$pesanan['jumlah'] ?> x <?= format_rupiah((int)$pesanan['harga']) ?></td>
                    </tr>
                    <tr style="border: none;">
                        <td style="border: none;">Subtotal</td>
                        <td style="text-align: right; border: none;"><strong><?= format_rupiah((int)$pesanan['jumlah'] * (int)$pesanan['harga']) ?></strong></td>
                    </tr>
                    <tr style="border: none;">
                        <td style="border: none;">Potongan Voucher <?= !empty($pesanan['id_promo']) ? '(kode promo)' : '' ?></td>
                        <td style="text-align: right; border: none; color: #E11D48;">- <?= format_rupiah((int)$pesanan['diskon']) ?></td>
                    </tr>
                    <tr style="border: none;">
                        <td style="border: none;"><strong>TOTAL BAYAR</strong></td>
                        <td style="text-align: right; border: none;"><strong style="font-size: 1.25rem; color: #4F46E5;"><?= format_rupiah((int)$pesanan['total_harga']) ?></strong></td>
                    </tr>
                </table>
            </div>
        </td>

        <!-- UPLOAD BUKTI BAYAR -->
        <td style="width: 45%; border: none; padding-left: 16px;">
            <div class="card">
                <div class="card-header">
                    <h3 style="margin: 0;">Upload Bukti Transfer</h3>
                </div>

                <p style="margin-bottom: 16px;">
                    Transfer tepat sesuai <strong>Total Bayar</strong> ke:<br>
                    <strong>Bank BCA: 880-123-4567</strong><br>
                    a.n. <strong>Pensi School OSIS</strong><br>
                    <small style="color: #6B7280;">Berikan keterangan: <code><?= e($pesanan['kode_transaksi']) ?></code></small>
                </p>

                <?php if (in_array($pesanan['status_bayar'], ['Menunggu Bayar', 'Ditolak'], true)): ?>
                    <hr>
                    <form method="POST" action="checkout.php" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                        <input type="hidden" name="aksi" value="upload_bukti">
                        <input type="hidden" name="kode_transaksi" value="<?= e($pesanan['kode_transaksi']) ?>">

                        <label for="bukti_bayar"><strong>Pilih Foto Bukti Transfer (JPG/PNG/WEBP, maks 2MB):</strong></label><br><br>
                        <input type="file" id="bukti_bayar" name="bukti_bayar" accept="image/jpeg,image/png,image/webp" required><br><br>
                        <button type="submit" class="success" style="width: 100%;"><strong>UNGGAH BUKTI BAYAR</strong></button>
                    </form>
                <?php elseif (!empty($pesanan['bukti_bayar'])): ?>
                    <hr>
                    <p><strong>Bukti Transfer Terunggah:</strong></p>
                    <a href="bukti.php?id_pesan=<?= (int)$pesanan['id_pesan'] ?>" target="_blank" rel="noopener">
                        <img src="bukti.php?id_pesan=<?= (int)$pesanan['id_pesan'] ?>" alt="Bukti Transfer" style="max-width: 100%; border: 1px solid #E5E7EB; border-radius: 6px;">
                    </a>
                    <p style="margin-top: 8px; font-size: 0.875rem; color: #6B7280;">
                        Status: 
                        <?php if ($pesanan['status_bayar'] === 'Menunggu Verifikasi'): ?>
                            <span class="badge badge-waiting">Menunggu Verifikasi</span>
                        <?php elseif ($pesanan['status_bayar'] === 'Lunas'): ?>
                            <span class="badge badge-paid">Lunas</span>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>

                <?php if ($pesanan['status_bayar'] === 'Lunas'): ?>
                    <hr>
                    <p style="text-align: center;">
                        <a href="dashboard_peserta.php" class="success" style="display: inline-block; width: auto; text-decoration: none;">
                            <strong>[ CETAK E-TICKET SATUAN ANDA &rarr; ]</strong>
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        </td>
    </tr>
</table>

<br>

<!-- DETAIL TIKET -->
<h3 style="margin-bottom: 12px;">Rincian Peserta & Nomor Kursi</h3>
<div class="card">
    <table>
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th>Kode E-Ticket</th>
                <th>Nama Pemegang</th>
                <th>NIS / NIK</th>
                <th style="width: 15%;">Nomor Kursi</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; foreach ($detail_tiket as $dt): ?>
                <tr>
                    <td align="center"><?= $no++ ?></td>
                    <td><code><?= e($dt['kode_tiket_unik']) ?></code></td>
                    <td><strong><?= e($dt['nama_pemegang_tiket']) ?></strong></td>
                    <td><code><?= e($dt['nik_pemegang_tiket']) ?></code></td>
                    <td align="center">
                        <strong><?= $dt['nomor_kursi'] ? e($dt['nomor_kursi']) : 'Free Standing' ?></strong>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
(function() {
    var el = document.getElementById('live_countdown');
    if (!el) return;

    var deadline = <?= (int)$batas_ms ?>;
    function tick() {
        var now = new Date().getTime();
        var diff = deadline - now;
        if (diff <= 0) {
            el.textContent = '00:00:00 (KADALUARSA)';
            el.style.color = '#E11D48';
            setTimeout(function() { window.location.reload(); }, 1500);
            return;
        }
        var hours = Math.floor(diff / (1000 * 60 * 60));
        var mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        var secs = Math.floor((diff % (1000 * 60)) / 1000);
        el.textContent = (hours < 10 ? '0' + hours : hours) + ':' + (mins < 10 ? '0' + mins : mins) + ':' + (secs < 10 ? '0' + secs : secs);
    }
    tick();
    setInterval(tick, 1000);
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
