<?php
/**
 * pemesanan.php - Identity Binding (Nama + NIS/NIK per tiket) & Kode Promo
 * Hanya role pembeli, butuh session draft dari detail_event.php
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/middleware.php';

require_role(['pembeli']);
release_expired_transactions($pdo);

$user  = current_user();
$draft = $_SESSION['pensi_booking_draft'] ?? null;

if (!$draft || empty($draft['id_jenis']) || empty($draft['qty'])) {
    set_flash('ERROR', 'Sesi pemilihan tiket tidak ditemukan. Silakan pilih jenis tiket terlebih dahulu.');
    header('Location: index.php');
    exit;
}

$id_jenis = (int)$draft['id_jenis'];
$qty      = (int)$draft['qty'];
$seats    = $draft['seats'] ?? [];

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

$subtotal = (int)$jenis['harga'] * $qty;

// Voucher
$kode_voucher_input = strtoupper(trim($_POST['kode_voucher'] ?? ''));
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
    $holders_old[$i] = [
        'nama' => trim($_POST['nama_peserta'][$i] ?? ($i === 0 ? $user['nama'] : '')),
        'id_identifier' => trim($_POST['id_peserta'][$i] ?? ($i === 0 ? $user['nis_atau_nik'] : '')),
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
            $errors[] = "Tiket #{$no_tkt}: Nama lengkap peserta wajib diisi minimal 3 karakter.";
        }
        
        // Validasi NIS/NIK sesuai tipe tiket target
        if ($jenis['target_pembeli'] === 'Internal' || $jenis['target_pembeli'] === 'Semua') {
            // Bisa Internal atau Semua - validasi fleksibel
            if (!preg_match('/^[0-9]{5,20}$/', $id_p) && !preg_match('/^[0-9]{16}$/', $id_p)) {
                $errors[] = "Tiket #{$no_tkt}: NIS (5-20 digit) atau NIK (16 digit) wajib diisi.";
            }
        } else {
            // Hanya Eksternal - wajib NIK 16 digit
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
        try {
            $pdo->beginTransaction();

            // Lock row jenis_tiket untuk race condition
            $stmtLockJenis = $pdo->prepare("SELECT * FROM jenis_tiket WHERE id_jenis = ? FOR UPDATE");
            $stmtLockJenis->execute([$id_jenis]);
            $jenis_locked = $stmtLockJenis->fetch();

            if (!$jenis_locked || (int)$jenis_locked['stok_tersedia'] < $qty) {
                throw new RuntimeException('Stok tiket pada jenis ini sudah tidak mencukupi.');
            }

            // Jika Seated, lock kursi
            if ($jenis['tipe_tiket'] === 'Seated') {
                foreach ($seats as $st) {
                    $stmtLockSeat = $pdo->prepare("SELECT status_kursi FROM kursi_pensi WHERE id_kursi = ? FOR UPDATE");
                    $stmtLockSeat->execute([(int)$st['id_kursi']]);
                    if ($stmtLockSeat->fetchColumn() !== 'Available') {
                        throw new RuntimeException("Kursi {$st['nomor_kursi']} baru saja dikunci/dipesan peserta lain.");
                    }
                }
            }

            // Voucher
            $id_promo_final = null;
            if ($voucher_valid) {
                $stmtLockV = $pdo->prepare("SELECT id_promo, kuota_voucher FROM kode_promo WHERE id_promo = ? FOR UPDATE");
                $stmtLockV->execute([(int)$voucher_valid['id_promo']]);
                $vr = $stmtLockV->fetch();
                if ($vr && (int)$vr['kuota_voucher'] > 0) {
                    $id_promo_final = (int)$vr['id_promo'];
                    $updV = $pdo->prepare("UPDATE kode_promo SET kuota_voucher = kuota_voucher - 1 WHERE id_promo = ?");
                    $updV->execute([$id_promo_final]);
                } else {
                    $diskon = 0;
                    $total_bayar = $subtotal;
                }
            }

            $kode_transaksi = generate_kode_transaksi();
            $batas_bayar = date('Y-m-d H:i:s', strtotime('+' . BATAS_WAKTU_BAYAR_MENIT . ' minutes'));

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
                $diskon,
                $total_bayar,
                $batas_bayar,
            ]);
            $id_pesan = (int)$pdo->lastInsertId();

            // Update stok: stok_tersedia -qty, stok_tertahan +qty
            $updJenis = $pdo->prepare("
                UPDATE jenis_tiket
                SET stok_tersedia = stok_tersedia - ?,
                    stok_tertahan = stok_tertahan + ?
                WHERE id_jenis = ?
            ");
            $updJenis->execute([$qty, $qty, $id_jenis]);

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
                if ($jenis['tipe_tiket'] === 'Seated' && isset($seats[$i])) {
                    $id_kursi_item = (int)$seats[$i]['id_kursi'];
                    $updKursi = $pdo->prepare("UPDATE kursi_pensi SET status_kursi = 'Locked' WHERE id_kursi = ?");
                    $updKursi->execute([$id_kursi_item]);
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
                "Pembeli membuat pesanan {$kode_transaksi} untuk {$qty} tiket {$jenis['nama_jenis']}. Total: " . format_rupiah($total_bayar) . "."
            );

            $pdo->commit();
            unset($_SESSION['pensi_booking_draft']);

            set_flash('SUKSES', "Pesanan {$kode_transaksi} berhasil dibuat! Selesaikan pembayaran sebelum batas waktu 2 jam habis.");
            header('Location: checkout.php?kode=' . urlencode($kode_transaksi));
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Gagal membuat pesanan: ' . $e->getMessage();
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
    <strong><?= $jenis['target_pembeli'] === 'Internal' ? 'NIS (5-20 digit)' : 'NIK KTP (16 digit)' ?></strong> 
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
                ? 'Kursi: ' . $seats[$i]['nomor_kursi']
                : 'Free Standing';
            $id_label = $jenis['target_pembeli'] === 'Internal' ? 'NIS (5-20 Digit)' : 'NIK KTP (16 Digit)';
            $id_pattern = $jenis['target_pembeli'] === 'Internal' ? '[0-9]{5,20}' : '[0-9]{16}';
            $id_maxlen = $jenis['target_pembeli'] === 'Internal' ? 20 : 16;
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
            <strong>KUNCI TIKET & LANJUT KE CHECKOUT (2 JAM) &rarr;</strong>
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
        // Validasi format di server-side, client-side basic check
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