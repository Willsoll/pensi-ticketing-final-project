<?php
// views/admin/dashboard.php
$e = $e ?? function($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); };
$attr = $attr ?? function($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); };
$rupiah = $rupiah ?? function($v) { return 'Rp ' . number_format($v, 0, ',', '.'); };
$date = $date ?? function($v) { return date('d-m-Y', strtotime($v)); };
$datetime = $datetime ?? function($v) { return date('d-m-Y H:i', strtotime($v)); };
?>
<div class="breadcrumb">
    <a href="/">Beranda</a>
    <span class="breadcrumb-separator">/</span>
    <span class="breadcrumb-current">Dashboard Panitia OSIS & Keuangan</span>
</div>

<div class="page-header">
    <h1 class="page-title">Dashboard Panitia OSIS & Keuangan</h1>
    <p class="page-subtitle">Selamat datang, <?= $e($current_user['nama']) ?> (<?= $e(ucfirst($current_user['role'])) ?>)</p>
</div>

<!-- Stat Cards -->
<div class="grid grid-4" style="margin-bottom: 2rem;">
    <div class="card stat-card">
        <div class="stat-label">Total Pesanan</div>
        <div class="stat-value"><?= (int)$stats['total_transaksi'] ?></div>
    </div>
    <div class="card stat-card emerald">
        <div class="stat-label">Tiket Lunas (Terjual)</div>
        <div class="stat-value"><?= (int)$stats['tiket_lunas'] ?></div>
    </div>
    <div class="card stat-card orange">
        <div class="stat-label">Total Pemasukan</div>
        <div class="stat-value"><?= $rupiah((int)$stats['total_pemasukan']) ?></div>
    </div>
    <div class="card stat-card purple">
        <div class="stat-label">Menunggu Verifikasi</div>
        <div class="stat-value"><?= (int)$stats['menunggu_verif'] ?></div>
    </div>
    <div class="card stat-card orange">
        <div class="stat-label">Menunggu Bayar</div>
        <div class="stat-value"><?= (int)$stats['menunggu_bayar'] ?></div>
    </div>
    <div class="card stat-card slate">
        <div class="stat-label">Kadaluarsa / Ditolak</div>
        <div class="stat-value"><?= (int)($stats['kadaluarsa'] + $stats['ditolak']) ?></div>
    </div>
</div>

<!-- Stok Per Jenis Tiket -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
        <h3 style="font-size: 1.125rem; font-weight: 700;">📦 Stok & Kuota Per Jenis Tiket</h3>
    </div>
    <div class="card-body" style="padding: 0;">
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
                <?php foreach ($segmentStats as $seg): 
                    $persen = $seg['kuota_total'] > 0 ? round(($seg['terjual'] / $seg['kuota_total']) * 100) : 0;
                ?>
                <tr>
                    <td><strong><?= $e($seg['nama_jenis']) ?></strong></td>
                    <td><span class="badge badge-<?= $seg['target_pembeli'] === 'Internal' ? 'primary' : ($seg['target_pembeli'] === 'Eksternal' ? 'orange' : 'emerald') ?>"><?= $e($seg['target_pembeli']) ?></span></td>
                    <td><?= $e($seg['tipe_venue'] ?? '') ?></td>
                    <td style="text-align: right;"><strong><?= $rupiah($seg['harga']) ?></strong></td>
                    <td style="text-align: center;"><?= (int)$seg['kuota_total'] ?></td>
                    <td style="text-align: center;"><strong><?= (int)$seg['kuota_tersedia'] ?></strong></td>
                    <td style="text-align: center;"><?= (int)$seg['kuota_locked'] ?></td>
                    <td style="text-align: center;">
                        <strong><?= (int)$seg['terjual'] ?></strong>
                        <small style="color: var(--color-slate-500);">(<?= $persen ?>%)</small>
                        <div style="height: 6px; background: var(--color-slate-200); border-radius: 3px; margin-top: 4px;">
                            <div style="height: 100%; width: <?= $persen ?>%; background: var(--color-purple); border-radius: 3px;"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Tiket Terjual Per Segmentasi -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
        <h3 style="margin: 0;">📊 Tiket Terjual Per Segmentasi (Mandat CEO: Anti-Calo)</h3>
    </div>
    <div class="card-body" style="padding: 0;">
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
                <?php foreach ($segmentStats as $seg): 
                    $totalAktif = $seg['terjual'] + $seg['pending_verif'] + $seg['pending_bayar'];
                ?>
                <tr>
                    <td><strong><?= $e($seg['nama_jenis']) ?></strong></td>
                    <td><span class="badge badge-<?= $seg['target_pembeli'] === 'Internal' ? 'primary' : ($seg['target_pembeli'] === 'Eksternal' ? 'orange' : 'emerald') ?>"><?= $e($seg['target_pembeli']) ?></span></td>
                    <td style="text-align: center;"><span class="badge badge-emerald"><?= (int)$seg['terjual'] ?></span></td>
                    <td style="text-align: center;"><span class="badge badge-purple"><?= (int)$seg['pending_verif'] ?></span></td>
                    <td style="text-align: center;"><span class="badge badge-yellow"><?= (int)$seg['pending_bayar'] ?></span></td>
                    <td style="text-align: center;"><strong><?= $totalAktif ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Check-in Statistics -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
        <h3 style="margin: 0;">🎟️ Statistik Check-In Gate (Hari Acara)</h3>
    </div>
    <div class="card-body" style="padding: 0;">
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
                <?php foreach ($checkinStats as $cs): 
                    $total = (int)$cs['total_tiket'];
                    $masuk = (int)$cs['sudah_masuk'];
                    $persen = $total > 0 ? round(($masuk / $total) * 100) : 0;
                ?>
                <tr>
                    <td><strong><?= $e($cs['nama_jenis']) ?></strong></td>
                    <td style="text-align: center;"><?= $total ?></td>
                    <td style="text-align: center;"><span class="badge badge-emerald"><?= $masuk ?></span></td>
                    <td style="text-align: center;"><span class="badge badge-purple"><?= (int)$cs['belum_masuk'] ?></span></td>
                    <td style="text-align: center;">
                        <strong><?= $persen ?>%</strong>
                        <div style="height: 6px; background: var(--color-slate-200); border-radius: 3px; margin-top: 4px;">
                            <div style="height: 100%; width: <?= $persen ?>%; background: var(--color-emerald); border-radius: 3px;"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($checkinStats)): ?>
                <tr><td colspan="5" align="center" style="color: var(--color-slate-500); padding: 2rem;">Belum ada tiket berstatus Lunas untuk check-in.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pesanan Terbaru -->
<div class="card">
    <div class="card-header">
        <h3 style="margin: 0;">📋 10 Pesanan Terbaru</h3>
        <a href="/finance" class="btn btn-secondary" style="font-size: 0.75rem;">[ Ke Verifikasi Pembayaran ]</a>
    </div>
    <div class="card-body" style="padding: 0;">
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
                <?php foreach ($recentOrders as $ro): 
                    $statusClass = match($ro['status_bayar']) {
                        'Paid' => 'badge-emerald',
                        'Waiting Verification' => 'badge-purple',
                        'Pending' => 'badge-yellow',
                        'Expired' => 'badge-red',
                        'Rejected' => 'badge-red',
                        default => 'badge-slate',
                    };
                ?>
                <tr>
                    <td><code><?= $e($ro['kode_booking']) ?></code></td>
                    <td>
                        <strong><?= $e($ro['nama_pembeli']) ?></strong>
                        <br><small><?= $e($ro['tipe_pengguna']) ?></small>
                    </td>
                    <td><span class="badge badge-<?= $ro['tipe_pengguna'] === 'Internal' ? 'primary' : 'orange' ?>"><?= $e($ro['tipe_pengguna']) ?></span></td>
                    <td><?= $e($ro['nama_jenis']) ?></td>
                    <td style="text-align: center;"><?= (int)$ro['jumlah'] ?></td>
                    <td style="text-align: right;"><strong><?= $rupiah((int)$ro['total_harga']) ?></strong></td>
                    <td style="text-align: center;"><span class="badge <?= $statusClass ?>"><?= $e($ro['status_bayar']) ?></span></td>
                    <td><small><?= $datetime($ro['tgl_pesan']) ?></small></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>