<?php
declare(strict_types=1);

/**
 * dashboard_eo.php — Executive analytics untuk admin_eo.
 * Agregat transaksi status Paid: omzet kotor, potongan promo, pendapatan bersih,
 * biaya layanan, total transaksi, tiket terjual. Filter opsional ?id_event=.
 */
require_once __DIR__ . '/src/Config/database.php';
require_once __DIR__ . '/src/Middleware/middleware.php';
release_expired_transactions($pdo);

require_role(['admin_eo']);

$user = current_user();

$idEventFilter = filter_var($_GET['id_event'] ?? null, FILTER_VALIDATE_INT);
$where  = 'WHERE t.status_bayar = ?';
$params = ['Paid'];
if ($idEventFilter) {
    $where .= ' AND t.id_event = ?';
    $params[] = (int)$idEventFilter;
}

$exec = function (string $sql, array $p) use ($pdo) {
    $s = $pdo->prepare($sql);
    $s->execute($p);
    return $s;
};

// ----- Statistik utama (5 angka) -----
$stat = $exec("
    SELECT COUNT(*) AS total_transaksi,
           COALESCE(SUM(t.subtotal),0) AS omzet_kotor,
           COALESCE(SUM(t.diskon),0) AS total_diskon,
           COALESCE(SUM(t.subtotal - t.diskon),0) AS pendapatan_bersih,
           COALESCE(SUM(t.biaya_layanan),0) AS biaya_layanan
    FROM transaksi t $where
", $params)->fetch();

$tiketTerjual = (int)$exec("
    SELECT COALESCE(COUNT(*),0)
    FROM e_tickets et
    JOIN transaksi t ON t.id_transaksi = et.id_transaksi
    $where
", $params)->fetchColumn();

// ----- Per-event table -----
$sqlEvent = "
    SELECT e.id_event, e.judul_event,
           COALESCE(tier.kuota_total,0) AS kuota_total,
           COALESCE(tk.tiket_terjual,0) AS tiket_terjual,
           COALESCE(trx.omzet_kotor,0) AS omzet_kotor,
           COALESCE(trx.diskon,0) AS diskon,
           COALESCE(trx.bersih,0) AS bersih
    FROM events e
    LEFT JOIN (
        SELECT id_event, SUM(kuota_total) AS kuota_total
        FROM tiket_tier GROUP BY id_event
    ) tier ON tier.id_event = e.id_event
    LEFT JOIN (
        SELECT t.id_event, COUNT(*) AS tiket_terjual
        FROM e_tickets et
        JOIN transaksi t ON t.id_transaksi = et.id_transaksi
        WHERE t.status_bayar = ? GROUP BY t.id_event
    ) tk ON tk.id_event = e.id_event
    LEFT JOIN (
        SELECT id_event, SUM(subtotal) AS omzet_kotor, SUM(diskon) AS diskon,
               SUM(subtotal - diskon) AS bersih
        FROM transaksi WHERE status_bayar = ? GROUP BY id_event
    ) trx ON trx.id_event = e.id_event
";
$eventParams = ['Paid', 'Paid'];
if ($idEventFilter) {
    $sqlEvent .= ' WHERE e.id_event = ?';
    $eventParams[] = (int)$idEventFilter;
}
$sqlEvent .= ' ORDER BY omzet_kotor DESC, judul_event';
$rowsEvent = $exec($sqlEvent, $eventParams)->fetchAll();

// ----- Occupancy per tier -----
$sqlTier = "
    SELECT tier.id_tier, tier.nama_tier, tier.kuota_total, e.judul_event,
           COALESCE((
               SELECT COUNT(*) FROM e_tickets et
               JOIN transaksi t ON t.id_transaksi = et.id_transaksi
               WHERE t.status_bayar = ? AND et.id_tier = tier.id_tier
           ),0) AS sold
    FROM tiket_tier tier
    JOIN events e ON e.id_event = tier.id_event
";
$tierParams = ['Paid'];
if ($idEventFilter) {
    $sqlTier .= ' WHERE e.id_event = ?';
    $tierParams[] = (int)$idEventFilter;
}
$sqlTier .= ' ORDER BY e.judul_event, tier.nama_tier';
$rowsTier = $exec($sqlTier, $tierParams)->fetchAll();

// ----- Chart penjualan harian 14 hari -----
$labels = [];
$values = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $labels[] = $d;
    $values[$d] = 0;
}
$sqlChart = "
    SELECT DATE(created_at) AS tgl, SUM(total_bayar) AS total
    FROM transaksi
    WHERE status_bayar = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
";
$chartParams = ['Paid'];
if ($idEventFilter) {
    $sqlChart .= ' AND id_event = ?';
    $chartParams[] = (int)$idEventFilter;
}
$sqlChart .= ' GROUP BY DATE(created_at)';
foreach ($exec($sqlChart, $chartParams)->fetchAll() as $r) {
    $values[$r['tgl']] = (int)$r['total'];
}
$chartMax = max($values);

// ----- Transaksi Lunas terbaru -----
$sqlTrx = "
    SELECT t.kode_booking, u.nama_lengkap, e.judul_event, t.total_bayar, t.created_at
    FROM transaksi t
    JOIN users u ON u.id_user = t.id_user
    JOIN events e ON e.id_event = t.id_event
    WHERE t.status_bayar = ?
";
$trxParams = ['Paid'];
if ($idEventFilter) {
    $sqlTrx .= ' AND t.id_event = ?';
    $trxParams[] = (int)$idEventFilter;
}
$sqlTrx .= ' ORDER BY t.created_at DESC LIMIT 10';
$rowsTrx = $exec($sqlTrx, $trxParams)->fetchAll();

// Opsi filter event
$eventOptions = $pdo->query('SELECT id_event, judul_event FROM events ORDER BY judul_event')->fetchAll();

$pageTitle = 'Dashboard EO';
$pageSubtitle = 'Analisis keuangan transaksi Lunas';
$activeNav = 'dashboard_eo.php';

require __DIR__ . '/src/Views/layouts/header.php';
?>

<section style="margin-bottom:18px;">
    <form method="get" class="filter-bar">
        <div style="font-size:12.5px;line-height:38px;color:var(--color-text-muted);">Filter event:</div>
        <select name="id_event" class="form-select">
            <option value="">Semua Event</option>
            <?php foreach ($eventOptions as $o): ?>
                <option value="<?= (int)$o['id_event'] ?>" <?= ($idEventFilter === (int)$o['id_event']) ? 'selected' : '' ?>><?= e($o['judul_event']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Terapkan</button>
        <?php if ($idEventFilter): ?>
            <a class="btn btn-outline" href="dashboard_eo.php">Reset Filter</a>
        <?php endif; ?>
    </form>
</section>

<div class="card card-elevated">
    <div class="card-body">
        <h2 style="margin:0 0 14px 0;font-size:16px;color:var(--color-text-primary);">Ringkasan Keuangan (Lunas)</h2>
        <div class="stat-grid">
            <div class="stat-box">
                <small>Omzet Kotor</small>
                <strong><?= format_rupiah((int)$stat['omzet_kotor']) ?></strong>
            </div>
            <div class="stat-box">
                <small>Total Potongan Promo</small>
                <strong><?= format_rupiah((int)$stat['total_diskon']) ?></strong>
            </div>
            <div class="stat-box">
                <small>Pendapatan Bersih</small>
                <strong><?= format_rupiah((int)$stat['pendapatan_bersih']) ?></strong>
            </div>
            <div class="stat-box">
                <small>Biaya Layanan</small>
                <strong><?= format_rupiah((int)$stat['biaya_layanan']) ?></strong>
            </div>
            <div class="stat-box">
                <small>Total Transaksi Lunas</small>
                <strong><?= (int)$stat['total_transaksi'] ?></strong>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top:14px;">
    <div class="card-header">
        <span class="card-title">Rekapitulasi per Event</span>
    </div>
    <div class="card-body">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th style="text-align:right;">Tiket Terjual</th>
                        <th style="text-align:right;">Kuota Total</th>
                        <th style="text-align:right;">Occupancy</th>
                        <th style="text-align:right;">Omzet Kotor</th>
                        <th style="text-align:right;">Diskon</th>
                        <th style="text-align:right;">Bersih</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rowsEvent as $r): ?>
                        <?php
                        $pct = $r['kuota_total'] > 0 ? (int)round($r['tiket_terjual'] / $r['kuota_total'] * 100) : 0;
                        ?>
                        <tr>
                            <td><?= e($r['judul_event']) ?></td>
                            <td style="text-align:right;"><?= (int)$r['tiket_terjual'] ?></td>
                            <td style="text-align:right;"><?= (int)$r['kuota_total'] ?></td>
                            <td style="text-align:right;"><?= $pct ?>%</td>
                            <td style="text-align:right;"><?= format_rupiah((int)$r['omzet_kotor']) ?></td>
                            <td style="text-align:right;"><?= format_rupiah((int)$r['diskon']) ?></td>
                            <td style="text-align:right;"><?= format_rupiah((int)$r['bersih']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$rowsEvent): ?>
                        <tr><td colspan="7" style="color:var(--color-text-muted);text-align:center;">Tidak ada data event.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card" style="margin-top:14px;">
    <div class="card-header">
        <span class="card-title">Occupancy per Tiket Tier</span>
    </div>
    <div class="card-body">
        <?php foreach ($rowsTier as $r): ?>
            <?php
            $pct = $r['kuota_total'] > 0 ? (int)round($r['sold'] / $r['kuota_total'] * 100) : 0;
            ?>
            <div class="occupancy-row">
                <span class="occ-name"><?= e($r['judul_event']) ?> &middot; <?= e($r['nama_tier']) ?></span>
                <span class="occ-count"><?= (int)$r['sold'] ?> / <?= (int)$r['kuota_total'] ?></span>
                <div class="bar"><i style="width: <?= $pct ?>%"></i></div>
                <span class="occ-pct"><?= $pct ?>%</span>
            </div>
        <?php endforeach; ?>
        <?php if (!$rowsTier): ?>
            <p style="color:var(--color-text-muted);font-size:12.5px;">Tidak ada data tier.</p>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-top:14px;">
    <div class="card-header">
        <span class="card-title">Penjualan Harian &mdash; 14 Hari Terakhir</span>
    </div>
    <div class="card-body">
        <?php if ($chartMax > 0): ?>
            <div class="chart-bars">
                <?php foreach ($labels as $d): ?>
                    <?php $h = $chartMax > 0 ? (int)round($values[$d] / $chartMax * 100) : 0; ?>
                    <div class="chart-col">
                        <div class="chart-bar" style="height: <?= $h ?>%;" title="<?= format_rupiah($values[$d]) ?>"></div>
                        <span class="chart-label"><?= date('d/m', strtotime($d)) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <p style="font-size:12.5px;color:var(--color-text-muted);margin-top:8px;">Nilai tertinggi: <?= format_rupiah($chartMax) ?></p>
        <?php else: ?>
            <p style="color:var(--color-text-muted);font-size:12.5px;">Belum ada data penjualan Lunas dalam 14 hari terakhir.</p>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-top:14px;">
    <div class="card-header">
        <span class="card-title">Transaksi Lunas Terbaru</span>
    </div>
    <div class="card-body">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kode Booking</th>
                        <th>Pembeli</th>
                        <th>Event</th>
                        <th style="text-align:right;">Total Bayar</th>
                        <th>Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rowsTrx as $r): ?>
                        <tr>
                            <td><?= e($r['kode_booking']) ?></td>
                            <td><?= e($r['nama_lengkap']) ?></td>
                            <td><?= e($r['judul_event']) ?></td>
                            <td style="text-align:right;"><?= format_rupiah((int)$r['total_bayar']) ?></td>
                            <td><?= format_tanggal($r['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$rowsTrx): ?>
                        <tr><td colspan="5" style="color:var(--color-text-muted);text-align:center;">Belum ada transaksi Lunas.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/src/Views/layouts/footer.php'; ?>
