<?php
/**
 * dashboard_peserta.php - Dashboard Peserta: Daftar Pesanan & Cetak E-Ticket
 * Hanya role pembeli, milik user sendiri
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/middleware.php';

require_role(['pembeli']);
release_expired_transactions($pdo);

$user = current_user();

// Ambil pesanan user
$stmt = $pdo->prepare("
    SELECT p.*, j.nama_jenis, j.tipe_tiket,
           (SELECT COUNT(*) FROM detail_tiket_satuan WHERE id_pesan = p.id_pesan) as total_tiket,
           (SELECT COUNT(*) FROM detail_tiket_satuan WHERE id_pesan = p.id_pesan AND status_checkin = 'Sudah Masuk') as sudah_masuk
    FROM pemesanan p
    JOIN jenis_tiket j ON p.id_jenis = j.id_jenis
    WHERE p.id_user = ?
    ORDER BY p.tgl_pesan DESC
");
$stmt->execute([(int)$user['id_user']]);
$pesanan_list = $stmt->fetchAll();

$page_title = 'Dashboard Peserta & E-Ticket - Pensi School';
require_once __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb">
    <a href="index.php">Katalog</a> > Dashboard Peserta
</div>

<h2 style="color: #4F46E5; margin-bottom: 8px;">Dashboard Peserta</h2>
<p style="margin-bottom: 16px; color: #6B7280;">
    Halo, <strong><?= e($user['nama']) ?></strong> (<?= e(label_tipe_pengguna($user['tipe_pengguna'])) ?>). 
    Berikut riwayat pesanan tiket Anda.
</p>

<?php if (empty($pesanan_list)): ?>
    <div class="card" style="text-align: center; padding: 40px;">
        <div style="font-size: 3rem; margin-bottom: 16px;">🎫</div>
        <h3 style="color: #6B7280; margin-bottom: 8px;">Belum Ada Pesanan</h3>
        <p style="color: #9CA3AF; margin-bottom: 20px;">Anda belum memesan tiket apapun.</p>
        <a href="index.php" class="success" style="display: inline-block;">[ Pesan Tiket Sekarang ]</a>
    </div>
<?php else: ?>
    <div class="card">
        <table>
            <thead>
                <tr>
                    <th style="width: 15%;">Kode Transaksi</th>
                    <th style="width: 20%;">Tanggal Pesan</th>
                    <th style="width: 18%;">Jenis Tiket</th>
                    <th style="width: 10%;">Jumlah</th>
                    <th style="width: 12%; text-align: right;">Total Bayar</th>
                    <th style="width: 12%;">Status</th>
                    <th style="width: 13%;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pesanan_list as $ps): 
                    $status_class = strtolower(str_replace(' ', '-', $ps['status_bayar']));
                    $can_print = $ps['status_bayar'] === 'Lunas';
                    $can_pay = in_array($ps['status_bayar'], ['Menunggu Bayar', 'Ditolak']);
                ?>
                    <tr>
                        <td><code><strong><?= e($ps['kode_transaksi']) ?></strong></code></td>
                        <td><?= e(date('d-m-Y H:i', strtotime($ps['tgl_pesan']))) ?></td>
                        <td><?= e($ps['nama_jenis']) ?> <span class="badge badge-<?= $ps['tipe_tiket'] === 'Seated' ? 'available' : 'belum-masuk' ?>"><?= e($ps['tipe_tiket']) ?></span></td>
                        <td align="center"><?= (int)$ps['jumlah'] ?></td>
                        <td align="right"><strong><?= format_rupiah((int)$ps['total_harga']) ?></strong></td>
                        <td align="center">
                            <span class="badge badge-<?= $status_class ?>">
                                <?= e($ps['status_bayar']) ?>
                            </span>
                            <?php if (!empty($ps['catatan_penolakan'])): ?>
                                <br><small style="color: #E11D48;"><?= e($ps['catatan_penolakan']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td align="center" style="white-space: nowrap;">
                            <?php if ($can_pay): ?>
                                <a href="checkout.php?kode=<?= urlencode($ps['kode_transaksi']) ?>" class="button" style="font-size: 0.75rem; padding: 6px 10px;">
                                    <?= $ps['status_bayar'] === 'Menunggu Bayar' ? 'Bayar Sekarang' : 'Upload Ulang' ?>
                                </a>
                            <?php endif; ?>
                            <?php if ($can_print): ?>
                                <a href="etiket.php?kode_transaksi=<?= urlencode($ps['kode_transaksi']) ?>" class="button success" style="font-size: 0.75rem; padding: 6px 10px; margin-left: 4px;">
                                    Cetak E-Ticket
                                </a>
                            <?php endif; ?>
                            <a href="detail_pesanan.php?kode=<?= urlencode($ps['kode_transaksi']) ?>" class="button secondary" style="font-size: 0.75rem; padding: 6px 10px; margin-left: 4px;">
                                Detail
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<br>

<div class="card" style="background: #F0FDF4; border-color: #BBF7D0;">
    <h4 style="color: #166534; margin-bottom: 8px;">📋 Panduan Cetak E-Ticket</h4>
    <ul style="color: #166534; margin-left: 20px; line-height: 1.8;">
        <li>E-Ticket hanya bisa dicetak setelah status pembayaran <strong>Lunas</strong> (diverifikasi Panitia Keuangan).</li>
        <li>Setiap tiket memiliki <strong>Kode E-Ticket Unik</strong> dan <strong>Barcode/QR Code</strong>.</li>
        <li>Wajib membawa <strong>KTP Asli / Kartu Pelajar</strong> sesuai NIS/NIK tertera saat pemindaian di Gate.</li>
        <li>1 Kode E-Ticket = 1 kali masuk. Scan kedua akan menampilkan peringatan <strong style="color: #E11D48;">"TIKET SUDAH DIGUNAKAN"</strong>.</li>
    </ul>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>