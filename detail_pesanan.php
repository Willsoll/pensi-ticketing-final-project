<?php
/**
 * detail_pesanan.php - Detail Pesanan (View Only)
 * Bisa diakses pembeli (milik sendiri) & panitia (semua)
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/middleware.php';

require_login();
release_expired_transactions($pdo);

$user = current_user();
$kode_transaksi = trim($_GET['kode'] ?? '');

if ($kode_transaksi === '') {
    set_flash('ERROR', 'Kode transaksi tidak ditemukan.');
    if ($user['role'] === 'pembeli') header('Location: dashboard_peserta.php');
    else header('Location: dashboard_panitia.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT p.*, u.nama as nama_pembeli, u.tipe_pengguna, u.nis_atau_nik, u.email, u.no_hp, j.nama_jenis, j.tipe_tiket
    FROM pemesanan p
    JOIN users u ON p.id_user = u.id_user
    JOIN jenis_tiket j ON p.id_jenis = j.id_jenis
    WHERE p.kode_transaksi = ?
    LIMIT 1
");
$stmt->execute([$kode_transaksi]);
$pesanan = $stmt->fetch();

if (!$pesanan) {
    set_flash('ERROR', 'Data pesanan tidak ditemukan.');
    if ($user['role'] === 'pembeli') header('Location: dashboard_peserta.php');
    else header('Location: dashboard_panitia.php');
    exit;
}

// Cek akses: pembeli hanya milik sendiri
if ($user['role'] === 'pembeli' && (int)$pesanan['id_user'] !== (int)$user['id_user']) {
    http_response_code(403);
    set_flash('ERROR', 'Akses ditolak! Pesanan ini bukan milik akun Anda.');
    header('Location: dashboard_peserta.php');
    exit;
}

// Detail tiket
$stmtDet = $pdo->prepare("
    SELECT dts.*, kp.nomor_kursi
    FROM detail_tiket_satuan dts
    LEFT JOIN kursi_pensi kp ON dts.id_kursi = kp.id_kursi
    WHERE dts.id_pesan = ?
    ORDER BY dts.id_detail_tiket ASC
");
$stmtDet->execute([(int)$pesanan['id_pesan']]);
$detail_tiket = $stmtDet->fetchAll();

$page_title = 'Detail Pesanan - ' . $kode_transaksi;
require_once __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb">
    <a href="index.php">Katalog</a> > 
    <?php if ($user['role'] === 'pembeli'): ?>
        <a href="dashboard_peserta.php">Dashboard Peserta</a> > 
    <?php else: ?>
        <a href="dashboard_panitia.php">Dashboard Panitia</a> > 
    <?php endif; ?>
    Detail Pesanan
</div>

<h2 style="color: #4F46E5; margin-bottom: 8px;">Detail Pesanan</h2>

<div class="card">
    <div class="card-header">
        <h3 style="margin: 0;">Informasi Pesanan</h3>
        <span class="badge badge-<?= strtolower(str_replace(' ', '-', $pesanan['status_bayar'])) ?>">
            <?= e(strtoupper($pesanan['status_bayar'])) ?>
        </span>
    </div>
    <table style="border: none; width: 100%;">
        <tr style="border: none;">
            <td style="width: 200px; border: none;"><strong>Kode Transaksi</strong></td>
            <td style="border: none;"><code><?= e($pesanan['kode_transaksi']) ?></code></td>
        </tr>
        <tr style="border: none;">
            <td style="border: none;"><strong>Tanggal Pesan</strong></td>
            <td style="border: none;"><?= e(date('d-m-Y H:i:s', strtotime($pesanan['tgl_pesan']))) ?> WIB</td>
        </tr>
        <tr style="border: none;">
            <td style="border: none;"><strong>Pembeli</strong></td>
            <td style="border: none;">
                <strong><?= e($pesanan['nama_pembeli']) ?></strong> 
                (<?= e(label_tipe_pengguna($pesanan['tipe_pengguna'])) ?>: <?= e($pesanan['nis_atau_nik']) ?>)
            </td>
        </tr>
        <tr style="border: none;">
            <td style="border: none;"><strong>Email / HP</strong></td>
            <td style="border: none;"><?= e($pesanan['email']) ?> / <?= e($pesanan['no_hp']) ?></td>
        </tr>
        <tr style="border: none;">
            <td style="border: none;"><strong>Jenis Tiket</strong></td>
            <td style="border: none;"><?= e($pesanan['nama_jenis']) ?> (<?= e($pesanan['tipe_tiket']) ?>)</td>
        </tr>
        <tr style="border: none;">
            <td style="border: none;"><strong>Jumlah Tiket</strong></td>
            <td style="border: none;"><?= (int)$pesanan['jumlah'] ?></td>
        </tr>
        <?php if (!empty($pesanan['id_promo'])): ?>
        <tr style="border: none;">
            <td style="border: none;"><strong>Kode Promo</strong></td>
            <td style="border: none;">(terpakai: potongan <?= format_rupiah((int)$pesanan['diskon']) ?>)</td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($pesanan['catatan_penolakan'])): ?>
        <tr style="border: none;">
            <td style="border: none;"><strong>Catatan Penolakan</strong></td>
            <td style="border: none; color: #E11D48;"><strong><?= e($pesanan['catatan_penolakan']) ?></strong></td>
        </tr>
        <?php endif; ?>
        <?php if ($pesanan['status_bayar'] === 'Menunggu Bayar'): ?>
        <tr style="border: none;">
            <td style="border: none;"><strong>Batas Waktu Bayar</strong></td>
            <td style="border: none;"><?= e(date('d-m-Y H:i:s', strtotime($pesanan['batas_waktu_bayar']))) ?> WIB</td>
        </tr>
        <?php endif; ?>
    </table>
</div>

<br>

<div class="card">
    <div class="card-header">
        <h3 style="margin: 0;">Rincian Kalkulasi Biaya</h3>
    </div>
    <table style="border: none; width: 100%; max-width: 500px;">
        <tr style="border: none;">
            <td style="border: none;">Subtotal (<?= (int)$pesanan['jumlah'] ?> x <?= format_rupiah((int)$pesanan['harga']) ?>)</td>
            <td style="text-align: right; border: none;"><strong><?= format_rupiah((int)$pesanan['harga'] * (int)$pesanan['jumlah']) ?></strong></td>
        </tr>
        <tr style="border: none;">
            <td style="border: none;">Potongan Voucher</td>
            <td style="text-align: right; border: none; color: #E11D48;">- <?= format_rupiah((int)$pesanan['diskon']) ?></td>
        </tr>
        <tr style="border: none;">
            <td style="border: none;"><strong>TOTAL BAYAR</strong></td>
            <td style="text-align: right; border: none;"><strong style="font-size: 1.25rem; color: #4F46E5;"><?= format_rupiah((int)$pesanan['total_harga']) ?></strong></td>
        </tr>
    </table>
</div>

<br>

<div class="card">
    <div class="card-header">
        <h3 style="margin: 0;">Detail Peserta & E-Ticket (<?= count($detail_tiket) ?> tiket)</h3>
    </div>
    <table>
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th>Kode E-Ticket Unik</th>
                <th>Nama Peserta</th>
                <th>NIS / NIK</th>
                <th style="width: 15%;">Nomor Kursi</th>
                <th style="width: 18%;">Status Check-In</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; foreach ($detail_tiket as $dt): ?>
                <tr>
                    <td align="center"><?= $no++ ?></td>
                    <td><code><?= e($dt['kode_tiket_unik']) ?></code></td>
                    <td><strong><?= e($dt['nama_pemegang_tiket']) ?></strong></td>
                    <td><code><?= e($dt['nik_pemegang_tiket']) ?></code></td>
                    <td align="center"><strong><?= $dt['nomor_kursi'] ? e($dt['nomor_kursi']) : 'Free Standing' ?></strong></td>
                    <td align="center">
                        <span class="badge badge-<?= $dt['status_checkin'] === 'Sudah Masuk' ? 'sudah-masuk' : 'belum-masuk' ?>">
                            <?= e($dt['status_checkin']) ?>
                        </span>
                        <?php if ($dt['status_checkin'] === 'Sudah Masuk'): ?>
                            <br><small><?= e(date('d-m-Y H:i', strtotime($dt['waktu_checkin']))) ?></small>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<br>

<div style="text-align: center;">
    <?php if ($pesanan['status_bayar'] === 'Lunas' && $user['role'] === 'pembeli'): ?>
        <a href="etiket.php?kode_transaksi=<?= urlencode($pesanan['kode_transaksi']) ?>" class="button success" style="font-size: 1rem; padding: 12px 24px; text-decoration: none;">
            <strong>🖨️ CETAK E-TICKET SATUAN</strong>
        </a>
    <?php elseif ($pesanan['status_bayar'] === 'Menunggu Bayar' || $pesanan['status_bayar'] === 'Ditolak'): ?>
        <a href="checkout.php?kode=<?= urlencode($pesanan['kode_transaksi']) ?>" class="button" style="font-size: 1rem; padding: 12px 24px; text-decoration: none;">
            <strong><?= $pesanan['status_bayar'] === 'Menunggu Bayar' ? '💳 LANJUT BAYAR' : '🔄 UPLOAD ULANG BUKTI' ?></strong>
        </a>
    <?php endif; ?>
    <a href="<?= $user['role'] === 'pembeli' ? 'dashboard_peserta.php' : 'dashboard_panitia.php' ?>" class="button secondary" style="margin-left: 12px; font-size: 1rem; padding: 12px 24px; text-decoration: none;">
        [ Kembali ke Dashboard ]
    </a>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>