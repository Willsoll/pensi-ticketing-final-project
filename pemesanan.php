<?php
/**
 * pemesanan.php - Identity Binding (Nama + NIS/NIK per tiket) & Kode Promo
 * Hanya role pembeli, butuh session draft dari detail_event.php
 *
 * Hardening:
 * - Segmentasi Internal/Eksternal divalidasi ulang di server & di dalam transaksi.
 * - Maksimal 5 tiket per akun (Lunas + Menunggu Bayar + Menunggu Verifikasi).
 * - Maksimal 1 reservasi aktif per akun.
 * - Identitas (NIS/NIK) unik secara global untuk tiket aktif (anti-calo).
 * - Harga, stok, kursi, dan promo divalidasi ulang dengan row lock (anti race condition).
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/middleware.php';

require_role(['pembeli']);
release_expired_transactions($pdo);

$user  = current_user();
$draft = $_SESSION['pensi_booking_draft'] ?? null;

if (!is_array($draft) || empty($draft['id_jenis']) || empty($draft['qty'])) {
    set_flash('ERROR', 'Sesi pemilihan tiket tidak ditemukan. Silakan pilih jenis tiket terlebih dahulu.');
    header('Location: index.php');
    exit;
}

$id_jenis = (int)$draft['id_jenis'];
$qty      = (int)$draft['qty'];
$seats    = is_array($draft['seats'] ?? null) ? $draft['seats'] : [];

if ($id_jenis <= 0 || $qty < 1 || $qty > 5 || !is_array($seats)) {
    unset($_SESSION['pensi_booking_draft']);
    set_flash('ERROR', 'Draft pemesanan tidak valid. Silakan pilih ulang tiket.');
    header('Location: index.php');
    exit;
}

// Ambil info jenis tiket
$stmtJenis = $pdo->prepare("SELECT * FROM jenis_tiket WHERE id_jenis = ? LIMIT 1");
$stmtJenis->execute([$id_jenis]);
$jenis = $stmtJenis->fetch();

if (!$jenis) {
    unset($_SESSION['pensi_booking_draft']);
    set_flash('ERROR', 'Jenis tiket tidak valid.');
    header('Location: index.php');
    exit;
}

// Segmentasi di awal (pesan ramah sebelum form).
$target_pembeli = (string)$jenis['target_pembeli'];
$user_tipe      = (string)$user['tipe_pengguna'];
if (($target_pembeli === 'Internal' && $user_tipe !== 'Internal')
    || ($target_pembeli === 'Eksternal' && $user_tipe !== 'Eksternal')) {
    unset($_SESSION['pensi_booking_draft']);
    set_flash('ERROR', 'Jenis tiket ini tidak sesuai dengan segmentasi akun Anda (Internal/Eksternal).');
    header('Location: index.php');
    exit;
}

// Seated: jumlah kursi pada draft harus sama dengan qty.
if ($jenis['tipe_tiket'] === 'Seated' && count($seats) !== $qty) {
    unset($_SESSION['pensi_booking_draft']);
    set_flash('ERROR', 'Jumlah kursi yang dipilih tidak sesuai. Silakan pilih ulang kursi.');
    header('Location: detail_event.php?id_jenis=' . $id_jenis);
    exit;
}

$subtotal = (int)$jenis['harga'] * $qty;

// Voucher (preview; validasi final dilakukan di dalam transaksi)
$kode_voucher_input = strtoupper(trim((string)($_POST['kode_voucher'] ?? '')));
$voucher_valid = null;
$diskon = 0;
$voucher_msg = '';

if ($kode_voucher_input !== '') {
    $stmtV = $pdo->prepare("
        SELECT * FROM kode_promo
        WHERE kode_voucher = ? AND kuota_voucher > 0 AND berlaku_sampai >= CURDATE()
        LIMIT 1
    ");
    $stmtV->execute([$kode_voucher_input]);
    $v_row = $stmtV->fetch();

    if (!$v_row) {
        $voucher_msg = "Kode voucher '{$kode_voucher_input}' tidak ditemukan, kuota habis, atau sudah kadaluarsa.";
    } elseif (check_promo_used_by_user($pdo, (int)$user['id_user'], (int)$v_row['id_promo'])) {
        $voucher_msg = "Kode voucher '{$kode_voucher_input}' sudah pernah Anda gunakan pada pesanan aktif.";
    } else {
        $voucher_valid = $v_row;
        $diskon = min($subtotal, (int)$v_row['potongan_nominal']);
        $voucher_msg = "Voucher '{$kode_voucher_input}' berhasil diterapkan! Potongan: " . format_rupiah($diskon) . ".";
    }
}

$total_bayar = max(0, $subtotal - $diskon);

// Data lama untuk repopulate form
$holders_old = [];
for ($i = 0; $i < $qty; $i++) {
    $nama_raw = $_POST['nama_peserta'][$i] ?? ($i === 0 ? $user['nama'] : '');
    $id_raw   = $_POST['id_peserta'][$i] ?? ($i === 0 ? $user['nis_atau_nik'] : '');
    $holders_old[$i] = [
        'nama'          => is_string($nama_raw) ? trim($nama_raw) : '',
        'id_identifier' => is_string($id_raw) ? trim($id_raw) : '',
    ];
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'buat_pemesanan') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan (CSRF) tidak valid.';
    }

    $id_seen = [];
    for ($i = 0; $i < $qty; $i++) {
        $nama_p = $holders_old[$i]['nama'];
        $id_p   = $holders_old[$i]['id_identifier'];
        $no_tkt = $i + 1;

        if (mb_strlen($nama_p) < 3 || mb_strlen($nama_p) > 100) {
            $errors[] = "Tiket #{$no_tkt}: Nama lengkap peserta wajib diisi 3 - 100 karakter.";
        }

        // Validasi NIS/NIK sesuai tipe tiket target
        if ($target_pembeli === 'Semua') {
            if (!preg_match('/^[0-9]{5,20}$/', $id_p) && !preg_match('/^[0-9]{16}$/', $id_p)) {
                $errors[] = "Tiket #{$no_tkt}: NIS (5-20 digit) atau NIK (16 digit) wajib diisi.";
            }
        } elseif ($target_pembeli === 'Internal') {
            if (!preg_match('/^[0-9]{5,20}$/', $id_p)) {
                $errors[] = "Tiket #{$no_tkt}: NIS wajib 5-20 digit angka.";
            }
        } else {
            if (!preg_match('/^[0-9]{16}$/', $id_p)) {
                $errors[] = "Tiket #{$no_tkt}: NIK KTP wajib tepat 16 digit angka.";
            }
        }

        if (isset($id_seen[$id_p])) {
            $errors[] = "Tiket #{$no_tkt}: Identifier {$id_p} tidak boleh ganda dalam satu transaksi (1 identitas = 1 tiket).";
        }
        $id_seen[$id_p] = true;
    }

    if (empty($errors)) {
        $nik_locks = [];
        try {
            // Kunci per-NIK (named lock) agar dua akun tidak bisa memakai NIK yang sama secara bersamaan.
            $unique_niks = array_values(array_unique(array_map(
                static fn(array $h): string => (string)$h['id_identifier'],
                $holders_old
            )));
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

            // Serialisasi seluruh pesanan milik user yang sama.
            $stmtLockUser = $pdo->prepare("SELECT id_user FROM users WHERE id_user = ? FOR UPDATE");
            $stmtLockUser->execute([(int)$user['id_user']]);
            if (!$stmtLockUser->fetchColumn()) {
                throw new DomainException('Akun Anda tidak ditemukan.');
            }

            // Lock row jenis_tiket untuk race condition & validasi ulang data master.
            $stmtLockJenis = $pdo->prepare("SELECT * FROM jenis_tiket WHERE id_jenis = ? FOR UPDATE");
            $stmtLockJenis->execute([$id_jenis]);
            $jenis_locked = $stmtLockJenis->fetch();

            if (!$jenis_locked) {
                throw new DomainException('Jenis tiket tidak ditemukan.');
            }
            if ((int)$jenis_locked['stok_tersedia'] < $qty) {
                throw new DomainException('Stok tiket pada jenis ini sudah tidak mencukupi.');
            }

            // Validasi ulang segmentasi memakai data master terbaru.
            $target_locked = (string)$jenis_locked['target_pembeli'];
            if (($target_locked === 'Internal' && $user_tipe !== 'Internal')
                || ($target_locked === 'Eksternal' && $user_tipe !== 'Eksternal')) {
                throw new DomainException('Segmentasi jenis tiket berubah dan tidak sesuai dengan akun Anda.');
            }

            // Batas 5 tiket & 1 reservasi aktif.
            if (!check_user_ticket_limit($pdo, (int)$user['id_user'], $qty)) {
                throw new DomainException('Batas maksimal 5 tiket per akun telah tercapai (termasuk tiket Lunas/Menunggu Verifikasi).');
            }
            if (!check_user_active_reservation($pdo, (int)$user['id_user'])) {
                throw new DomainException('Anda masih memiliki reservasi aktif. Selesaikan pembayaran/verifikasi terlebih dahulu sebelum memesan lagi.');
            }

            // Harga dihitung ulang dari data master yang terkunci (anti TOCTOU).
            $subtotal_locked = (int)$jenis_locked['harga'] * $qty;

            // Promo: lock, validasi kuota/tanggal, cegah reuse.
            $id_promo_final = null;
            $diskon_locked  = 0;
            if ($voucher_valid) {
                $stmtLockV = $pdo->prepare("
                    SELECT id_promo, kode_voucher, potongan_nominal, kuota_voucher, berlaku_sampai
                    FROM kode_promo WHERE id_promo = ? FOR UPDATE
                ");
                $stmtLockV->execute([(int)$voucher_valid['id_promo']]);
                $vr = $stmtLockV->fetch();

                if (!$vr || (int)$vr['kuota_voucher'] <= 0 || (string)$vr['berlaku_sampai'] < date('Y-m-d')) {
                    throw new DomainException('Kode voucher sudah tidak valid (kuota habis/kadaluarsa). Silakan hapus voucher dan coba lagi.');
                }
                if (check_promo_used_by_user($pdo, (int)$user['id_user'], (int)$vr['id_promo'])) {
                    throw new DomainException('Kode voucher ini sudah pernah Anda gunakan pada pesanan aktif.');
                }

                $id_promo_final = (int)$vr['id_promo'];
                $diskon_locked  = min($subtotal_locked, (int)$vr['potongan_nominal']);
                $updV = $pdo->prepare("UPDATE kode_promo SET kuota_voucher = kuota_voucher - 1 WHERE id_promo = ? AND kuota_voucher > 0");
                $updV->execute([$id_promo_final]);
                if ($updV->rowCount() !== 1) {
                    throw new DomainException('Kuota voucher baru saja habis. Silakan coba tanpa voucher.');
                }
            }
            $total_locked = max(0, $subtotal_locked - $diskon_locked);

            // Lock & validasi kursi. Kunci selalu diurutkan menaik berdasarkan
            // id_kursi agar dua transaksi tidak saling menunggu (deadlock).
            $seat_rows  = []; // id_kursi => row
            $seat_order = []; // urutan kursi sesuai pilihan tiket
            if ((string)$jenis_locked['tipe_tiket'] === 'Seated') {
                if (count($seats) !== $qty) {
                    throw new DomainException('Jumlah kursi tidak sesuai dengan jumlah tiket.');
                }
                $seen_seats = [];
                foreach ($seats as $st) {
                    $sid = (int)($st['id_kursi'] ?? 0);
                    if ($sid <= 0 || isset($seen_seats[$sid])) {
                        throw new DomainException('Pilihan kursi tidak valid (duplikat).');
                    }
                    $seen_seats[$sid] = true;
                    $seat_order[] = $sid;
                }

                $seat_ids_sorted = $seat_order;
                sort($seat_ids_sorted, SORT_NUMERIC);

                foreach ($seat_ids_sorted as $sid) {
                    $stmtLockSeat = $pdo->prepare("
                        SELECT id_kursi, nomor_kursi, status_kursi
                        FROM kursi_pensi
                        WHERE id_kursi = ? AND id_jenis = ?
                        FOR UPDATE
                    ");
                    $stmtLockSeat->execute([$sid, $id_jenis]);
                    $seat_row = $stmtLockSeat->fetch();
                    if (!$seat_row || $seat_row['status_kursi'] !== 'Available') {
                        $nomor = $seat_row['nomor_kursi'] ?? ('#' . $sid);
                        throw new DomainException("Kursi {$nomor} baru saja dikunci/dipesan peserta lain.");
                    }
                    $seat_rows[$sid] = $seat_row;
                }
            }

            // Identitas (NIS/NIK) unik untuk seluruh tiket aktif (anti-calo lintas akun).
            foreach ($holders_old as $h) {
                $stmtDup = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM detail_tiket_satuan dts
                    JOIN pemesanan p ON dts.id_pesan = p.id_pesan
                    WHERE dts.nik_pemegang_tiket = ?
                      AND p.status_bayar IN ('Menunggu Bayar', 'Menunggu Verifikasi', 'Lunas')
                ");
                $stmtDup->execute([$h['id_identifier']]);
                if ((int)$stmtDup->fetchColumn() > 0) {
                    throw new DomainException("NIS/NIK {$h['id_identifier']} sudah terdaftar pada tiket aktif lain. 1 identitas hanya boleh memiliki 1 tiket aktif.");
                }
            }

            $kode_transaksi = generate_kode_transaksi();
            $batas_bayar    = date('Y-m-d H:i:s', strtotime('+' . BATAS_WAKTU_BAYAR_MENIT . ' minutes'));

            // Insert pemesanan
            $stmtInsPesan = $pdo->prepare("
                INSERT INTO pemesanan (
                    kode_transaksi, id_user, id_jenis, id_promo,
                    jumlah, diskon, total_harga,
                    bukti_bayar, catatan_penolakan, batas_waktu_bayar, status_bayar, tgl_pesan
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?, 'Menunggu Bayar', NOW())
            ");
            $stmtInsPesan->execute([
                $kode_transaksi,
                (int)$user['id_user'],
                $id_jenis,
                $id_promo_final,
                $qty,
                $diskon_locked,
                $total_locked,
                $batas_bayar,
            ]);
            $id_pesan = (int)$pdo->lastInsertId();

            // Update stok: stok_tersedia -qty, stok_tertahan +qty (dengan guard).
            $updJenis = $pdo->prepare("
                UPDATE jenis_tiket
                SET stok_tersedia = stok_tersedia - ?,
                    stok_tertahan = stok_tertahan + ?
                WHERE id_jenis = ? AND stok_tersedia >= ?
            ");
            $updJenis->execute([$qty, $qty, $id_jenis, $qty]);
            if ($updJenis->rowCount() !== 1) {
                throw new DomainException('Stok tiket berubah saat transaksi diproses. Silakan coba lagi.');
            }

            // Insert detail_tiket_satuan & update kursi
            $stmtInsDetail = $pdo->prepare("
                INSERT INTO detail_tiket_satuan (
                    id_pesan, id_kursi, kode_tiket_unik,
                    nama_pemegang_tiket, nik_pemegang_tiket,
                    status_checkin, waktu_checkin, id_petugas_gate
                ) VALUES (?, ?, ?, ?, ?, 'Belum Masuk', NULL, NULL)
            ");

            for ($i = 0; $i < $qty; $i++) {
                $id_kursi_item = null;
                if ((string)$jenis_locked['tipe_tiket'] === 'Seated') {
                    $id_kursi_item = (int)$seat_order[$i];
                    $updKursi = $pdo->prepare("UPDATE kursi_pensi SET status_kursi = 'Locked' WHERE id_kursi = ? AND status_kursi = 'Available'");
                    $updKursi->execute([$id_kursi_item]);
                    if ($updKursi->rowCount() !== 1) {
                        throw new DomainException('Kursi baru saja diambil peserta lain. Silakan pilih ulang.');
                    }
                }

                $kode_tiket = generate_kode_tiket($i + 1);
                $stmtInsDetail->execute([
                    $id_pesan,
                    $id_kursi_item,
                    $kode_tiket,
                    $holders_old[$i]['nama'],
                    $holders_old[$i]['id_identifier'],
                ]);
            }

            catat_audit_log(
                $pdo,
                (int)$user['id_user'],
                'CREATE_PEMESANAN',
                'pemesanan',
                "Pembeli membuat pesanan {$kode_transaksi} untuk {$qty} tiket {$jenis_locked['nama_jenis']}. Total: " . format_rupiah($total_locked) . '.'
            );

            $pdo->commit();
            unset($_SESSION['pensi_booking_draft']);

            set_flash('SUKSES', "Pesanan {$kode_transaksi} berhasil dibuat! Selesaikan pembayaran sebelum batas waktu 15 menit habis.");
            header('Location: checkout.php?kode=' . urlencode($kode_transaksi));
            exit;

        } catch (DomainException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $e->getMessage();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[CREATE_ORDER] ' . $e->getMessage());
            $errors[] = 'Terjadi kesalahan sistem saat membuat pesanan. Silakan coba lagi.';
        } finally {
            foreach ($nik_locks as $lock_name) {
                try {
                    $stmtRelease = $pdo->prepare("SELECT RELEASE_LOCK(?)");
                    $stmtRelease->execute([$lock_name]);
                } catch (Throwable $ignored) {
                    // abaikan kegagalan pelepasan lock; koneksi akan menutup otomatis
                }
            }
        }
    }
}

$page_title = 'Identity Binding & Kode Promo - ' . $jenis['nama_jenis'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb">
    <a href="index.php">Katalog</a> > 
    <a href="detail_event.php?id_jenis=<?= $id_jenis ?>"><?= e($jenis['nama_jenis']) ?></a> > 
    Identity Binding & Promo
</div>

<h2 style="color: #4F46E5; margin-bottom: 8px;">Data Pemegang Tiket (Identity Binding) & Kode Promo</h2>
<p style="margin-bottom: 16px; color: #6B7280;">
    Setiap lembar tiket wajib didaftarkan atas nama <strong>Nama Lengkap</strong> dan 
    <strong><?= $jenis['target_pembeli'] === 'Internal' ? 'NIS (5-20 digit)' : ($jenis['target_pembeli'] === 'Eksternal' ? 'NIK KTP (16 digit)' : 'NIS/NIK') ?></strong> 
    peserta yang akan hadir. <?= $qty > 1 ? 'Setiap tiket harus identitas yang berbeda.' : '' ?>
</p>

<?php if (!empty($errors)): ?>
    <div class="flash ERROR">
        <strong>Periksa Data Anda:</strong>
        <ul style="margin: 8px 0 0 20px;">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" action="pemesanan.php" onsubmit="return validasiIdentityBinding();" novalidate>
    <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">

    <div class="card">
        <div class="card-header">
            <h3 style="margin: 0;">1. Form Identitas Peserta Per Lembar Tiket (<?= $qty ?> TIKET)</h3>
        </div>

        <?php for ($i = 0; $i < $qty; $i++): 
            $kursi_label = ($jenis['tipe_tiket'] === 'Seated' && isset($seats[$i]))
                ? 'Kursi: ' . ($seats[$i]['nomor_kursi'] ?? '-')
                : 'Free Standing';
            $id_label = $jenis['target_pembeli'] === 'Internal' ? 'NIS (5-20 Digit)' : ($jenis['target_pembeli'] === 'Eksternal' ? 'NIK KTP (16 Digit)' : 'NIS (5-20 Digit) / NIK (16 Digit)');
            $id_pattern = $jenis['target_pembeli'] === 'Internal' ? '[0-9]{5,20}' : ($jenis['target_pembeli'] === 'Eksternal' ? '[0-9]{16}' : '[0-9]{5,20}');
            $id_maxlen = $jenis['target_pembeli'] === 'Internal' ? 20 : ($jenis['target_pembeli'] === 'Eksternal' ? 16 : 20);
        ?>
            <div style="border: 1px solid #E5E7EB; border-radius: 8px; padding: 16px; margin-bottom: 16px; background: #FAFAFA;">
                <div style="font-weight: 700; color: #4F46E5; margin-bottom: 12px;">
                    TIKET #<?= $i + 1 ?> &mdash; <?= e($jenis['nama_jenis']) ?> | <?= e($kursi_label) ?>
                </div>
                <table style="border: none;">
                    <tr>
                        <td style="width: 200px; border: none;">
                            <label for="nama_<?= $i ?>"><strong>Nama Lengkap Peserta *</strong></label>
                        </td>
                        <td style="border: none;">
                            <input type="text" id="nama_<?= $i ?>" name="nama_peserta[<?= $i ?>]"
                                   value="<?= e($holders_old[$i]['nama']) ?>" required minlength="3" maxlength="100" size="45">
                        </td>
                    </tr>
                    <tr>
                        <td style="border: none;">
                            <label for="id_<?= $i ?>"><strong><?= e($id_label) ?> *</strong></label>
                        </td>
                        <td style="border: none;">
                            <input type="text" id="id_<?= $i ?>" name="id_peserta[<?= $i ?>]"
                                   value="<?= e($holders_old[$i]['id_identifier']) ?>" required pattern="<?= $id_pattern ?>" maxlength="<?= $id_maxlen ?>" size="25" class="id-field">
                            <br><small>Tiket ini target: <strong><?= e($jenis['target_pembeli']) ?></strong>. 
                            <?= $jenis['target_pembeli'] === 'Semua' ? 'Bisa NIS (5-20 digit) atau NIK (16 digit).' : '' ?></small>
                        </td>
                    </tr>
                </table>
            </div>
        <?php endfor; ?>
    </div>

    <br>

    <div class="card">
        <div class="card-header">
            <h3 style="margin: 0;">2. Voucher Promo & Kalkulasi Rincian Biaya</h3>
        </div>

        <table style="border: none; margin-bottom: 16px;">
            <tr>
                <td style="width: 200px; border: none;">
                    <label for="kode_voucher"><strong>Kode Voucher Promo (Opsional):</strong></label>
                </td>
                <td style="border: none;">
                    <input type="text" id="kode_voucher" name="kode_voucher" value="<?= e($kode_voucher_input) ?>" size="22" placeholder="Contoh: PENSI10K">
                    <button type="submit" name="aksi" value="cek_voucher" formnovalidate class="secondary" style="margin-left: 8px;">Terapkan</button>
                </td>
            </tr>
            <?php if ($voucher_msg !== ''): ?>
                <tr>
                    <td style="border: none;"></td>
                    <td style="border: none;"><strong style="color: <?= strpos($voucher_msg, 'berhasil') !== false ? '#166534' : '#991B1B' ?>;"><?= e($voucher_msg) ?></strong></td>
                </tr>
            <?php endif; ?>
        </table>

        <hr>

        <table style="border: none; width: 100%; max-width: 500px;">
            <tr style="border: none;">
                <td style="border: none;">Subtotal (<?= $qty ?> x <?= format_rupiah((int)$jenis['harga']) ?>)</td>
                <td style="text-align: right; border: none;"><strong><?= format_rupiah($subtotal) ?></strong></td>
            </tr>
            <tr style="border: none;">
                <td style="border: none;">Potongan Voucher <?= $voucher_valid ? '(' . e($voucher_valid['kode_voucher']) . ')' : '' ?></td>
                <td style="text-align: right; border: none; color: #E11D48;">- <?= format_rupiah($diskon) ?></td>
            </tr>
            <tr style="border: none;">
                <td style="border: none;"><strong>TOTAL PEMBAYARAN</strong></td>
                <td style="text-align: right; border: none;"><strong style="font-size: 1.25rem; color: #4F46E5;"><?= format_rupiah($total_bayar) ?></strong></td>
            </tr>
        </table>

        <br>

        <button type="submit" name="aksi" value="buat_pemesanan" class="success" style="font-size: 1rem; padding: 14px 28px; width: 100%;">
            <strong>KUNCI TIKET & LANJUT KE CHECKOUT (15 MENIT) &rarr;</strong>
        </button>
    </div>
</form>

<script>
function validasiIdentityBinding() {
    var idFields = document.querySelectorAll('input.id-field');
    var used = {};
    for (var i = 0; i < idFields.length; i++) {
        var namaEl = document.getElementById('nama_' + i);
        if (!namaEl || namaEl.value.trim().length < 3) {
            alert('Nama peserta tiket #' + (i + 1) + ' minimal 3 karakter.');
            if (namaEl) namaEl.focus();
            return false;
        }
        var idVal = idFields[i].value.trim();
        if (idVal === '') {
            alert('Identifier peserta tiket #' + (i + 1) + ' wajib diisi.');
            idFields[i].focus();
            return false;
        }
        if (used[idVal]) {
            alert('Identifier ' + idVal + ' tidak boleh duplikat dalam satu transaksi!');
            idFields[i].focus();
            return false;
        }
        used[idVal] = true;
    }
    return true;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
