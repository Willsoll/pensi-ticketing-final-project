<?php
/**
 * detail_event.php - Detail event, rundown, dan pemilihan tiket (public)
 * Memerlukan id_event via GET. Hanya event dengan status_event='Published' yang ditampilkan.
 */
require_once __DIR__ . '/src/Config/database.php';
require_once __DIR__ . '/src/Middleware/middleware.php';

release_expired_transactions($pdo);

$idEventRaw = $_GET['id_event'] ?? '';
$idEvent    = is_numeric($idEventRaw) ? (int)$idEventRaw : 0;

if ($idEvent <= 0) {
    header('Location: error.php?msg=' . urlencode('ID event tidak valid.'));
    exit;
}

$stmtEvent = $pdo->prepare("
    SELECT e.id_event, e.id_kat_event, e.judul_event, e.penyelenggara, e.lokasi,
           e.tipe_venue, e.tgl_mulai, e.tgl_selesai, e.banner, e.deskripsi, e.status_event,
           k.nama_kategori
    FROM events e
    JOIN kategori_event k ON k.id_kat_event = e.id_kat_event
    WHERE e.id_event = ? AND e.status_event = 'Published'
    LIMIT 1
");
$stmtEvent->execute([$idEvent]);
$event = $stmtEvent->fetch();

if (!$event) {
    header('Location: error.php?msg=' . urlencode('Event tidak ditemukan atau belum dipublikasikan.'));
    exit;
}

$isLoggedIn = is_logged_in();
$now = date('Y-m-d H:i:s');

/* Rundown */
$stmtRundown = $pdo->prepare("
    SELECT judul_sesi, pembicara, jam_mulai, jam_selesai, lokasi_panggung
    FROM event_rundown
    WHERE id_event = ?
    ORDER BY jam_mulai ASC
");
$stmtRundown->execute([$idEvent]);
$rundown = $stmtRundown->fetchAll();

/* Tier dengan kursi (untuk event Seated) diambil sekaligus dan dikelompokkan per tier */
$stmtTiers = $pdo->prepare("
    SELECT id_tier, nama_tier, harga, kuota_total, kuota_tersedia, kuota_locked, batas_jual
    FROM tiket_tier
    WHERE id_event = ?
    ORDER BY harga ASC
");
$stmtTiers->execute([$idEvent]);
$tiers = $stmtTiers->fetchAll();

$seatsByTier = [];
foreach ($tiers as $t) {
    $seatsByTier[(int)$t['id_tier']] = [];
}
if (!empty($tiers)) {
    $tierIds = array_map(fn($t) => (int)$t['id_tier'], $tiers);
    $ph = implode(',', array_fill(0, count($tierIds), '?'));
    $stmtSeats = $pdo->prepare("
        SELECT id_kursi, id_tier, nomor_kursi, status_kursi
        FROM kursi_event
        WHERE id_tier IN ({$ph})
        ORDER BY id_tier ASC, nomor_kursi ASC
    ");
    $stmtSeats->execute($tierIds);
    foreach ($stmtSeats->fetchAll() as $s) {
        $seatsByTier[(int)$s['id_tier']][] = $s;
    }
}

$pageTitle   = 'Detail - ' . $event['judul_event'];
$pageSubtitle = 'Pilih tiket dan atur pesanan Anda';
$activeNav   = 'index.php';
require __DIR__ . '/src/Views/layouts/header.php';
?>

<div style="margin-bottom:var(--space-6);">
    <a href="index.php" class="btn btn-ghost btn-sm">&larr; Kembali ke Katalog</a>
</div>

<div class="card card-elevated" style="margin-bottom:var(--space-6);">
    <div class="event-banner" style="<?= !empty($event['banner']) ? 'background-image:url(' . e($event['banner']) . ');background-size:cover;background-position:center;' : '' ?>"></div>
    <div class="card-body" style="padding:var(--space-5);">
        <div style="display:flex;align-items:center;gap:var(--space-3);margin-bottom:var(--space-3);flex-wrap:wrap;">
            <span class="badge badge-primary"><?= e($event['nama_kategori']) ?></span>
            <span class="badge badge-info"><?= e($event['tipe_venue']) ?></span>
        </div>
        <h1 style="font-size:var(--text-2xl);font-weight:700;margin:0 0 var(--space-3) 0;color:var(--color-text-primary);"><?= e($event['judul_event']) ?></h1>
        <div class="event-meta" style="font-size:var(--text-sm);color:var(--color-text-muted);gap:var(--space-3);flex-wrap:wrap;margin-bottom:var(--space-4);">
            <span><strong>Penyelenggara:</strong> <?= e($event['penyelenggara']) ?></span>
            <span><strong>Lokasi:</strong> <?= e($event['lokasi']) ?></span>
            <span><strong>Tanggal:</strong> <?= e(format_tanggal($event['tgl_mulai'], false)) ?> &ndash; <?= e(format_tanggal($event['tgl_selesai'], false)) ?></span>
        </div>

        <h3 style="font-size:var(--text-lg);font-weight:600;margin:0 0 var(--space-3) 0;color:var(--color-text-primary);">Deskripsi</h3>
        <p style="color:var(--color-text-secondary);margin:0;"><?= nl2br(e($event['deskripsi'] ?? 'Deskripsi event belum tersedia.')) ?></p>

        <?php if (!empty($rundown)): ?>
            <h3 style="font-size:var(--text-lg);font-weight:600;margin:var(--space-6) 0 var(--space-3) 0;color:var(--color-text-primary);">Rundown Acara</h3>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:16%;">Jam</th>
                            <th style="width:34%;">Sesi</th>
                            <th style="width:30%;">Pembicara</th>
                            <th>Panggung</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rundown as $r): ?>
                            <tr>
                                <td style="font-variant-numeric:tabular-nums;"><?= e(date('H:i', strtotime($r['jam_mulai']))) ?> &ndash; <?= e(date('H:i', strtotime($r['jam_selesai']))) ?></td>
                                <td><?= e($r['judul_sesi']) ?></td>
                                <td><?= e($r['pembicara']) ?></td>
                                <td><?= e($r['lokasi_panggung']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <h3 style="font-size:var(--text-lg);font-weight:600;margin:var(--space-8) 0 var(--space-4) 0;color:var(--color-text-primary);">Pilih Tiket</h3>

        <?php if (empty($tiers)): ?>
            <div class="alert alert-info" role="alert">
                <div class="alert-content">
                    <div class="alert-message">Belum ada tier tiket yang tersedia untuk event ini.</div>
                </div>
            </div>
        <?php endif; ?>

        <?php foreach ($tiers as $tier): ?>
            <?php
            $idTier   = (int)$tier['id_tier'];
            $harga    = (int)$tier['harga'];
            $total    = (int)$tier['kuota_total'];
            $tersedia = (int)$tier['kuota_tersedia'];
            $locked   = (int)$tier['kuota_locked'];
            $batasJual= $tier['batas_jual'];
            $isPast   = $batasJual !== '' && $now > $batasJual;
            $isSold   = $tersedia <= 0 || $isPast;
            $occ      = $total > 0 ? round((($total - $tersedia) / $total) * 100) : 0;
            $seats    = $seatsByTier[$idTier] ?? [];
            ?>
            <div class="card card-elevated" style="margin-bottom:var(--space-5);overflow:hidden;">
                <div class="card-body" style="padding:var(--space-5);">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:var(--space-4);flex-wrap:wrap;margin-bottom:var(--space-4);">
                        <div>
                            <h3 style="font-size:var(--text-lg);font-weight:700;margin:0 0 var(--space-1) 0;color:var(--color-text-primary);"><?= e($tier['nama_tier']) ?></h3>
                            <div style="font-size:var(--text-base);font-weight:700;color:var(--color-primary);"><?= format_rupiah($harga) ?></div>
                        </div>
                        <span class="badge <?= $isSold ? 'badge-error' : 'badge-success' ?>">
                            <?= $isSold ? 'Tidak Tersedia' : 'Tersedia (' . $tersedia . ')' ?>
                        </span>
                    </div>

                    <div class="occupancy-row">
                        <span style="font-size:12px;color:var(--color-text-muted);width:90px;">Sisa kuota</span>
                        <div class="bar" style="<?= $isSold ? 'background:var(--color-border-muted);' : '' ?>">
                            <i style="width:<?= $occ ?>%;background:<?= $isSold ? 'var(--color-text-muted)' : 'var(--color-success,#059669)' ?>;"></i>
                        </div>
                        <span style="font-size:12px;font-variant-numeric:tabular-nums;"><?= $tersedia ?> / <?= $total ?> tersedia</span>
                    </div>

                    <div style="font-size:var(--text-xs);color:var(--color-text-muted);margin-top:var(--space-2);">
                        Batas jual: <?= $batasJual !== '' ? e(format_tanggal($batasJual)) : '-' ?>
                        <?= $isPast ? ' <span style="color:var(--color-error);"><strong>(lewat waktu)</strong></span>' : '' ?>
                    </div>

                    <?php if (!$isSold): ?>
                        <div style="margin-top:var(--space-5);">
                            <?php if ($event['tipe_venue'] === 'Seated'): ?>
                                <p style="font-size:var(--text-xs);color:var(--color-text-secondary);margin:0 0 var(--space-3) 0;">
                                    <?= $isLoggedIn ? '' : 'Pratinjau kursi — login diperlukan untuk melanjutkan.' ?><br>Pilih kursi (maksimal 5): <span id="seatCount-<?= $idTier ?>" style="font-weight:700;color:var(--color-primary);">0</span> / 5
                                </p>
                                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(52px,1fr));gap:8px;max-height:320px;overflow-y:auto;border:1px solid var(--color-border);border-radius:var(--radius-md);padding:var(--space-3);background:var(--color-bg-muted);margin-bottom:var(--space-4);">
<div class="seat-legend"><span><i style="background:#86EFAC"></i>Available</span><span><i style="background:#FCD34D"></i>Locked</span><span><i style="background:#D1D5DB"></i>Sold</span><span><i style="background:#F97316"></i>Dipilih</span></div>
<div class="seat-stage">PANGGUNG / STAGE</div>
                                    <?php if (empty($seats)): ?>
                                        <div style="grid-column:1/-1;text-align:center;color:var(--color-text-muted);font-size:var(--text-xs);">Belum ada kursi tersedia untuk tier ini.</div>
                                    <?php else: ?>
                                        <form method="GET" action="pemesanan.php" onsubmit="return validateSeatSelection(<?= $idTier ?>, 5);" style="display:contents;">
                                            <input type="hidden" name="id_event" value="<?= $idEvent ?>">
                                            <input type="hidden" name="id_tier" value="<?= $idTier ?>">
                                            <?php foreach ($seats as $s): ?>
                                                <?php
                                                $statusKursi = $s['status_kursi'];
                                                $isAvailable = $statusKursi === 'Available';
                                                $disabled    = !$isAvailable ? 'disabled' : '';
                                                $muted       = !$isAvailable ? 'opacity:0.55;filter:grayscale(1);' : '';
                                                ?>
                                                <label class="seat-chip <?= $isAvailable ? 'seat-avail' : ($statusKursi === 'Locked' ? 'seat-locked' : 'seat-sold') ?>" title="<?= e($s['nomor_kursi']) ?> - <?= e($statusKursi) ?>">
                                                    <input type="checkbox" name="kursi[]" value="<?= (int)$s['id_kursi'] ?>" class="seat-cb-<?= $idTier ?>" data-tier="<?= $idTier ?>" <?= $disabled ?> style="width:18px;height:18px;margin-bottom:4px;accent-color:var(--color-success);">
                                                    <strong style="font-size:11px;color:var(--color-text-primary);"><?= e($s['nomor_kursi']) ?></strong>
                                                    <span style="font-size:8px;color:var(--color-text-muted);"><?= $isAvailable ? '' : e(strtolower($statusKursi)) ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                            <button type="submit" class="btn btn-primary btn-sm" style="width:100%;margin-top:var(--space-3);grid-column:1/-1;"><?= $isLoggedIn ? 'Lanjut Pemesanan' : 'Login untuk Memesan' ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <form method="GET" action="pemesanan.php">
                                    <input type="hidden" name="id_event" value="<?= $idEvent ?>">
                                    <input type="hidden" name="id_tier" value="<?= $idTier ?>">
                                    <div class="form-group" style="margin-bottom:var(--space-4);">
                                        <label class="form-label" style="font-size:var(--text-xs);">Jumlah tiket</label>
                                        <input type="number" name="qty" class="form-control" min="1" max="<?= max(1, min(5, $tersedia)) ?>" value="1" style="max-width:140px;">
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm" style="width:100%;"><?= $isLoggedIn ? 'Lanjut Pemesanan' : 'Login untuk Memesan' ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div style="margin-top:var(--space-5);">
                            <?php if (!$isLoggedIn): ?>
                                <a href="login.php" class="btn btn-primary btn-sm" style="width:100%;">Login untuk Pemesanan</a>
                            <?php else: ?>
                                <span class="btn btn-primary btn-sm" style="width:100%;opacity:0.6;cursor:not-allowed;">Tidak Tersedia</span>
                                <p style="font-size:var(--text-xs);color:var(--color-text-muted);margin-top:var(--space-2);">Pembelian tidak tersedia (kuota habis atau lewat batas jual).</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function validateSeatSelection(tierId, maxSelect) {
    var cbs = document.querySelectorAll('.seat-cb-' + tierId + ':checked');
    if (cbs.length === 0) {
        alert('Pilih minimal 1 kursi.');
        return false;
    }
    if (cbs.length > maxSelect) {
        alert('Maksimal memilih ' + maxSelect + ' kursi.');
        return false;
    }
    return true;
}

document.querySelectorAll('.seat-cb-<?php echo $tiers[0]['id_tier'] ?? 0; ?>').forEach(function(cb){
    cb.addEventListener('change', function(){
        var tier = this.getAttribute('data-tier');
        var checked = document.querySelectorAll('.seat-cb-' + tier + ':checked').length;
        var el = document.getElementById('seatCount-' + tier);
        if (el) el.textContent = checked;
        if (checked >= 5) {
            document.querySelectorAll('.seat-cb-' + tier + ':not(:checked)').forEach(function(dis){
                dis.disabled = true;
            });
        } else {
            document.querySelectorAll('.seat-cb-' + tier).forEach(function(dis){
                dis.disabled = false;
            });
        }
    });
});
</script>

<?php require __DIR__ . '/src/Views/layouts/footer.php'; ?>