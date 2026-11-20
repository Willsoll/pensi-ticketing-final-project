<?php
/**
 * dashboard_panitia.php - Dashboard Panitia OSIS & Keuangan
 * Role: admin_osis (full), panitia_keuangan (view only)
 * Statistik: tiket terjual per segmentasi, total pemasukan, kuota tersisa, check-in stats
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/middleware.php';

require_role(['admin_osis', 'panitia_keuangan']);
release_expired_transactions($pdo);

$user = current_user();
$is_admin = $user['role'] === 'admin_osis';

// Statistik Umum
$stat = [];
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_pesanan,
        SUM(CASE WHEN status_bayar = 'Lunas' THEN jumlah ELSE 0 END) as tiket_lunas,
        SUM(CASE WHEN status_bayar = 'Lunas' THEN total_harga ELSE 0 END) as total_pemasukan,
        SUM(CASE WHEN status_bayar = 'Menunggu Verifikasi' THEN 1 ELSE 0 END) as menunggu_verif,
        SUM(CASE WHEN status_bayar = 'Menunggu Bayar' THEN 1 ELSE 0 END) as menunggu_bayar,
        SUM(CASE WHEN status_bayar = 'Kadaluarsa' THEN 1 ELSE 0 END) as kadaluarsa,
        SUM(CASE WHEN status_bayar = 'Ditolak' THEN 1 ELSE 0 END) as ditolak
    FROM pemesanan
");
$stat = $stmt->fetch();

// Stok per jenis tiket
$stmtJenis = $pdo->query("SELECT * FROM jenis_tiket ORDER BY id_jenis");
$jenis_stats = $stmtJenis->fetchAll();

// Tiket terjual per segmentasi (Internal/Eksternal)
$stmtSeg = $pdo->query("
    SELECT 
        j.target_pembeli,
        j.nama_jenis,
        SUM(CASE WHEN p.status_bayar = 'Lunas' THEN p.jumlah ELSE 0 END) as terjual,
        SUM(CASE WHEN p.status_bayar = 'Menunggu Verifikasi' THEN p.jumlah ELSE 0 END) as pending_verif,
        SUM(CASE WHEN p.status_bayar = 'Menunggu Bayar' THEN p.jumlah ELSE 0 END) as pending_bayar
    FROM pemesanan p
    JOIN jenis_tiket j ON p.id_jenis = j.id_jenis
    GROUP BY j.id_jenis
    ORDER BY j.id_jenis
");
$segment_stats = $stmtSeg->fetchAll();

// Check-in stats
$stmtCheckin = $pdo->query("
    SELECT 
        j.nama_jenis,
        COUNT(dts.id_detail_tiket) as total_tiket,
        SUM(CASE WHEN dts.status_checkin = 'Sudah Masuk' THEN 1 ELSE 0 END) as sudah_masuk,
        SUM(CASE WHEN dts.status_checkin = 'Belum Masuk' THEN 1 ELSE 0 END) as belum_masuk
    FROM detail_tiket_satuan dts
    JOIN pemesanan p ON dts.id_pesan = p.id_pesan
    JOIN jenis_tiket j ON p.id_jenis = j.id_jenis
    WHERE p.status_bayar = 'Lunas'
    GROUP BY j.id_jenis
");
$checkin_stats = $stmtCheckin->fetchAll();

// Pesanan terbaru (10)
$stmtRecent = $pdo->query("
    SELECT p.*, u.nama as nama_pembeli, u.tipe_pengguna, j.nama_jenis
    FROM pemesanan p
    JOIN users u ON p.id_user = u.id_user
    JOIN jenis_tiket j ON p.id_jenis = j.id_jenis
    ORDER BY p.tgl_pesan DESC
    LIMIT 10
");
$recent_orders = $stmtRecent->fetchAll();

$page_title = 'Dashboard Panitia OSIS & Keuangan - Pensi School';
require_once __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb">
    <a href="index.php">Katalog</a> > Dashboard Panitia
</div>

<h2 style="color: #4F46E5; margin-bottom: 8px;">Dashboard Panitia OSIS & Keuangan</h2>
<p style="margin-bottom: 20px; color: #6B7280;">
    Selamat datang, <strong><?= e($user['nama']) ?></strong> (<span class="badge badge-<?= $is_admin ? 'available' : 'belum-masuk' ?>"><?= e(label_role($user['role'])) ?></span>).
</p>

<!-- STAT CARDS -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <div class="card" style="border-left: 4px solid #4F46E5;">
        <div style="color: #6B7280; font-size: 0.875rem;">Total Pesanan</div>
        <div style="font-size: 2rem; font-weight: 700; color: #4F46E5;"><?= (int)$stat['total_pesanan'] ?></div>
    </div>
    <div class="card" style="border-left: 4px solid #16A34A;">
        <div style="color: #6B7280; font-size: 0.875rem;">Tiket Lunas (Terjual)</div>
        <div style="font-size: 2rem; font-weight: 700; color: #16A34A;"><?= (int)$stat['tiket_lunas'] ?></div>
    </div>
    <div class="card" style="border-left: 4px solid #EAB308;">
        <div style="color: #6B7280; font-size: 0.875rem;">Total Pemasukan</div>
        <div style="font-size: 2rem; font-weight: 700; color: #92400E;"><?= format_rupiah((int)$stat['total_pemasukan']) ?></div>
    </div>
    <div class="card" style="border-left: 4px solid #3B82F6;">
        <div style="color: #6B7280; font-size: 0.875rem;">Menunggu Verifikasi</div>
        <div style="font-size: 2rem; font-weight: 700; color: #3B82F6;"><?= (int)$stat['menunggu_verif'] ?></div>
    </div>
    <div class="card" style="border-left: 4px solid #F59E0B;">
        <div style="color: #6B7280; font-size: 0.875rem;">Menunggu Bayar</div>
        <div style="font-size: 2rem; font-weight: 700; color: #F59E0B;"><?= (int)$stat['menunggu_bayar'] ?></div>
    </div>
    <div class="card" style="border-left: 4px solid #9CA3AF;">
        <div style="color: #6B7280; font-size: 0.875rem;">Kadaluarsa / Ditolak</div>
        <div style="font-size: 2rem; font-weight: 700; color: #9CA3AF;"><?= (int)$stat['kadaluarsa'] + (int)$stat['ditolak'] ?></div>
    </div>
</div>

<!-- STOK PER JENIS TIKET -->
<div class="card">
    <div class="card-header">
        <h3 style="margin: 0;">📦 Stok & Kuota Per Jenis Tiket</h3>
    </div>
    <table>
        <thead>
            <tr>
                <th>Jenis Tiket</th>
                <th>Target</th>
                <th>Tipe</th>
                <th style="text-align: right;">Harga</th>
                <th style="text-align: center;">Total Stok</th>
                <th style="text-align: center;">Tersedia</th>
                <th style="text-align: center;">Tertahan</th>
                <th style="text-align: center;">Terjual (Lunas)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($jenis_stats as $js):
                $terjual = 0;
                foreach ($segment_stats as $ss) {
                    if ($ss['nama_jenis'] === $js['nama_jenis']) {
                        $terjual = (int)$ss['terjual'];
                        break;
                    }
                }
                $persen = $js['stok_tiket'] > 0 ? round(($terjual / $js['stok_tiket']) * 100) : 0;
            ?>
                <tr>
                    <td><strong><?= e($js['nama_jenis']) ?></strong></td>
                    <td><span class="badge badge-<?= $js['target_pembeli'] === 'Internal' ? 'internal' : ($js['target_pembeli'] === 'Eksternal' ? 'eksternal' : 'available') ?>"><?= e($js['target_pembeli']) ?></span></td>
                    <td><?= e($js['tipe_tiket']) ?></td>
                    <td style="text-align: right;"><strong><?= format_rupiah((int)$js['harga']) ?></strong></td>
                    <td style="text-align: center;"><?= (int)$js['stok_tiket'] ?></td>
                    <td style="text-align: center;"><strong><?= (int)$js['stok_tersedia'] ?></strong></td>
                    <td style="text-align: center;"><?= (int)$js['stok_tertahan'] ?></td>
                    <td style="text-align: center;">
                        <strong><?= $terjual ?></strong> 
                        <small style="color: #6B7280;">(<?= $persen ?>%)</small>
                        <div style="height: 6px; background: #E5E7EB; border-radius: 3px; margin-top: 4px;">
                            <div style="height: 100%; width: <?= $persen ?>%; background: #4F46E5; border-radius: 3px; transition: width 0.3s;"></div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<br>

<!-- TIKET TERJUAL PER SEGMENTASI -->
<div class="card">
    <div class="card-header">
        <h3 style="margin: 0;">📊 Tiket Terjual Per Segmentasi (Mandat CEO: Anti-Calo)</h3>
    </div>
    <table>
        <thead>
            <tr>
                <th>Jenis Tiket</th>
                <th>Target Pembeli</th>
                <th style="text-align: center;">Lunas</th>
                <th style="text-align: center;">Menunggu Verifikasi</th>
                <th style="text-align: center;">Menunggu Bayar</th>
                <th style="text-align: center;">Total Aktif</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($segment_stats as $ss): 
                $total_aktif = (int)$ss['terjual'] + (int)$ss['pending_verif'] + (int)$ss['pending_bayar'];
            ?>
                <tr>
                    <td><strong><?= e($ss['nama_jenis']) ?></strong></td>
                    <td><span class="badge badge-<?= $ss['target_pembeli'] === 'Internal' ? 'internal' : ($ss['target_pembeli'] === 'Eksternal' ? 'eksternal' : 'available') ?>"><?= e($ss['target_pembeli']) ?></span></td>
                    <td style="text-align: center;"><span class="badge badge-paid"><?= (int)$ss['terjual'] ?></span></td>
                    <td style="text-align: center;"><span class="badge badge-waiting"><?= (int)$ss['pending_verif'] ?></span></td>
                    <td style="text-align: center;"><span class="badge badge-pending"><?= (int)$ss['pending_bayar'] ?></span></td>
                    <td style="text-align: center;"><strong><?= $total_aktif ?></strong></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<br>

<!-- CHECK-IN STATISTICS -->
<div class="card">
    <div class="card-header">
        <h3 style="margin: 0;">🎟️ Statistik Check-In Gate (Hari Acara)</h3>
    </div>
    <table>
        <thead>
            <tr>
                <th>Jenis Tiket</th>
                <th style="text-align: center;">Total Tiket Lunas</th>
                <th style="text-align: center;">Sudah Masuk</th>
                <th style="text-align: center;">Belum Masuk</th>
                <th style="text-align: center;">Persentase</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($checkin_stats as $cs):
                $total = (int)$cs['total_tiket'];
                $masuk = (int)$cs['sudah_masuk'];
                $persen = $total > 0 ? round(($masuk / $total) * 100) : 0;
            ?>
                <tr>
                    <td><strong><?= e($cs['nama_jenis']) ?></strong></td>
                    <td style="text-align: center;"><?= $total ?></td>
                    <td style="text-align: center;"><span class="badge badge-sudah-masuk"><?= $masuk ?></span></td>
                    <td style="text-align: center;"><span class="badge badge-belum-masuk"><?= (int)$cs['belum_masuk'] ?></span></td>
                    <td style="text-align: center;">
                        <strong><?= $persen ?>%</strong>
                        <div style="height: 6px; background: #E5E7EB; border-radius: 3px; margin-top: 4px;">
                            <div style="height: 100%; width: <?= $persen ?>%; background: #16A34A; border-radius: 3px;"></div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($checkin_stats)): ?>
                <tr><td colspan="5" align="center" style="color: #6B7280;">Belum ada tiket berstatus Lunas untuk check-in.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<br>

<!-- PESANAN TERBARU -->
<div class="card">
    <div class="card-header">
        <h3 style="margin: 0;">📋 10 Pesanan Terbaru</h3>
        <?php if ($is_admin): ?>
            <a href="verifikasi_pembayaran.php" class="button secondary" style="font-size: 0.75rem;">[ Ke Verifikasi Pembayaran ]</a>
        <?php endif; ?>
    </div>
    <table>
        <thead>
            <tr>
                <th>Kode Transaksi</th>
                <th>Pembeli</th>
                <th>Tipe</th>
                <th>Jenis Tiket</th>
                <th>Jumlah</th>
                <th style="text-align: right;">Total</th>
                <th>Status</th>
                <th>Tanggal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recent_orders as $ro): 
                $status_class = strtolower(str_replace(' ', '-', $ro['status_bayar']));
            ?>
                <tr>
                    <td><code><?= e($ro['kode_transaksi']) ?></code></td>
                    <td>
                        <strong><?= e($ro['nama_pembeli']) ?></strong><br>
                        <small><?= e(label_tipe_pengguna($ro['tipe_pengguna'])) ?></small>
                    </td>
                    <td><span class="badge badge-<?= $ro['tipe_pengguna'] === 'Internal' ? 'internal' : 'eksternal' ?>"><?= e($ro['tipe_pengguna']) ?></span></td>
                    <td><?= e($ro['nama_jenis']) ?></td>
                    <td align="center"><?= (int)$ro['jumlah'] ?></td>
                    <td style="text-align: right;"><strong><?= format_rupiah((int)$ro['total_harga']) ?></strong></td>
                    <td align="center"><span class="badge badge-<?= $status_class ?>"><?= e($ro['status_bayar']) ?></span></td>
                    <td><small><?= e(date('d-m-Y H:i', strtotime($ro['tgl_pesan']))) ?></small></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>