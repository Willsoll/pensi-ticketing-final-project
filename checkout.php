<?php
/**
 * checkout.php - Checkout dengan Live Countdown Timer 2 Jam & Upload Bukti Bayar
 * Hanya role pembeli, milik user sendiri
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/middleware.php';

require_role(['pembeli']);
release_expired_transactions($pdo);

$user = current_user();
$kode_transaksi = trim($_GET['kode'] ?? $_POST['kode_transaksi'] ?? '');

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'upload_bukti') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $upload_error = 'Token keamanan (CSRF) tidak valid.';
    } elseif (!in_array($pesanan['status_bayar'], ['Menunggu Bayar', 'Ditolak'], true)) {
        $upload_error = 'Status pesanan saat ini (' . $pesanan['status_bayar'] . ') tidak dapat mengunggah ulang bukti bayar.';
    } elseif ($pesanan['status_bayar'] === 'Menunggu Bayar' && strtotime($pesanan['batas_waktu_bayar']) < time()) {
        release_expired_transactions($pdo);
        set_flash('ERROR', 'Batas waktu pembayaran 2 jam telah berakhir. Pesanan otomatis menjadi Kadaluarsa.');
        header('Location: dashboard_peserta.php');
        exit;
    } elseif (!isset($_FILES['bukti_bayar']) || $_FILES['bukti_bayar']['error'] !== UPLOAD_ERR_OK) {
        $upload_error = 'Silakan pilih file bukti transfer terlebih dahulu.';
    } else {
        $file = $_FILES['bukti_bayar'];
        if ($file['size'] > UPLOAD_MAX_SIZE) {
            $upload_error = 'Ukuran file bukti bayar maksimal 2 MB.';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            if (!isset(UPLOAD_ALLOWED_MIME[$mime])) {
                $upload_error = 'Format file wajib JPG, PNG, atau WEBP.';
            } else {
                $ext = UPLOAD_ALLOWED_MIME[$mime];
                $fname = 'bukti_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $kode_transaksi)) . '_' . time() . '.' . $ext;
                $rel_path = 'uploads/bukti/' . $fname;
                $abs_path = __DIR__ . '/' . $rel_path;
                
                if (!move_uploaded_file($file['tmp_name'], $abs_path)) {
                    $upload_error = 'Gagal menyimpan file bukti bayar ke direktori server.';
                } else {
                    $upd = $pdo->prepare("
                        UPDATE pemesanan
                        SET bukti_bayar = ?, status_bayar = 'Menunggu Verifikasi'
                        WHERE id_pesan = ?
                    ");
                    $upd->execute([$rel_path, (int)$pesanan['id_pesan']]);

                    catat_audit_log(
                        $pdo,
                        (int)$user['id_user'],
                        'UPLOAD_BUKTI_BAYAR',
                        'pemesanan',
                        "Pembeli mengunggah bukti transfer ({$fname}) untuk pesanan {$kode_transaksi}. Status menjadi Menunggu Verifikasi."
                    );

                    set_flash('SUKSES', 'Bukti transfer berhasil diunggah! Menunggu verifikasi Panitia Keuangan.');
                    header('Location: checkout.php?kode=' . urlencode($kode_transaksi));
                    exit;
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
        <h3 style="margin: 0;">Status Pesanan & Live Countdown Timer (2 Jam)</h3>
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
                    <a href="<?= e($pesanan['bukti_bayar']) ?>" target="_blank">
                        <img src="<?= e($pesanan['bukti_bayar']) ?>" alt="Bukti Transfer" style="max-width: 100%; border: 1px solid #E5E7EB; border-radius: 6px;">
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