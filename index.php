<?php
/**
 * index.php - Multi-event catalog (public)
 * Daftar event yang telah dipublikasikan dengan filter dan pencarian real-time.
 */
require_once __DIR__ . '/src/Config/database.php';
require_once __DIR__ . '/src/Middleware/middleware.php';

maybe_release_expired_transactions($pdo);

/* ---------------------------------------------------------------------------
 * Statistik publik
 * ------------------------------------------------------------------------- */
$stmtStats = $pdo->query("SELECT COUNT(*) AS total_event FROM events WHERE status_event='Published'");
$total_event = (int)$stmtStats->fetchColumn();

$stmtTiket = $pdo->query("SELECT COALESCE(SUM(t.kuota_tersedia),0) AS total_tiket
                          FROM tiket_tier t
                          JOIN events e ON e.id_event = t.id_event
                          WHERE e.status_event = 'Published'");
$total_tiket = (int)$stmtTiket->fetchColumn();

$stmtKat = $pdo->query("SELECT COUNT(*) AS total_kat FROM kategori_event");
$total_kat = (int)$stmtKat->fetchColumn();

/* ---------------------------------------------------------------------------
 * Filter dari GET
 * ------------------------------------------------------------------------- */
$q      = trim($_GET['q'] ?? '');
$kat    = trim($_GET['kategori'] ?? '');
$kota   = trim($_GET['kota'] ?? '');
$dari   = trim($_GET['dari'] ?? '');
$sampai = trim($_GET['sampai'] ?? '');
$status = trim($_GET['status'] ?? '');

$sql = "SELECT e.id_event, e.id_kat_event, e.judul_event, e.penyelenggara, e.lokasi,
               e.tipe_venue, e.tgl_mulai, e.status_event, k.nama_kategori,
               MIN(t.harga) AS harga_mulai,
               SUM(t.kuota_tersedia) AS sisa,
               COUNT(t.id_tier) AS tier_count,
               MIN(t.batas_jual) AS batas_terdekat
        FROM events e
        JOIN kategori_event k ON k.id_kat_event = e.id_kat_event
        LEFT JOIN tiket_tier t ON t.id_event = e.id_event
        WHERE e.status_event = 'Published'";
$params = [];

if ($q !== '') {
    $sql .= " AND (e.judul_event LIKE ? OR e.penyelenggara LIKE ? OR e.lokasi LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($kat !== '') {
    $sql .= " AND e.id_kat_event = ?";
    $params[] = $kat;
}
if ($kota !== '') {
    $sql .= " AND e.lokasi LIKE ?";
    $params[] = '%' . $kota . '%';
}
if ($dari !== '') {
    $sql .= " AND DATE(e.tgl_mulai) >= ?";
    $params[] = $dari;
}
if ($sampai !== '') {
    $sql .= " AND DATE(e.tgl_mulai) <= ?";
    $params[] = $sampai;
}
if ($status === 'available') {
    $sql .= " HAVING sisa > 0";
} elseif ($status === 'soldout') {
    $sql .= " HAVING sisa = 0 OR sisa IS NULL";
}
$sql .= " GROUP BY e.id_event ORDER BY e.tgl_mulai ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

$kats    = $pdo->query("SELECT id_kat_event, nama_kategori FROM kategori_event ORDER BY nama_kategori")->fetchAll();
$kotaList = $pdo->query("SELECT DISTINCT lokasi FROM events WHERE status_event='Published' ORDER BY lokasi")->fetchAll();

$pageTitle   = 'Katalog Event';
$pageSubtitle = 'Pilih acara yang ingin Anda ikuti';
$activeNav   = 'index.php';
require __DIR__ . '/src/Views/layouts/header.php';
?>

<div class="stat-grid" style="margin-bottom:var(--space-6);">
    <div class="stat-box">
        <small>Total Event Dipublikasikan</small>
        <strong><?= (int)$total_event ?></strong>
    </div>
    <div class="stat-box">
        <small>Total Tiket Tersedia</small>
        <strong><?= (int)$total_tiket ?></strong>
    </div>
    <div class="stat-box">
        <small>Total Kategori</small>
        <strong><?= (int)$total_kat ?></strong>
    </div>
</div>

<form class="filter-bar" method="GET" action="index.php" id="filterForm">
    <div class="form-group" style="margin-bottom:0;grid-column:1 / -1;">
        <label class="form-label" style="margin-bottom:var(--space-1);">Cari judul, penyelenggara, atau lokasi</label>
        <input type="search" name="q" class="form-control" value="<?= e($q) ?>"
               placeholder="Ketik untuk mencari..." aria-label="Cari event"
               oninput="filterSearch(this.value)">
    </div>
    <div class="form-group" style="margin-bottom:0;">
        <label class="form-label" style="margin-bottom:var(--space-1);">Kategori</label>
        <select name="kategori" class="form-select" aria-label="Kategori">
            <option value="">Semua Kategori</option>
            <?php foreach ($kats as $katRow): ?>
                <option value="<?= (int)$katRow['id_kat_event'] ?>" <?= $kat == $katRow['id_kat_event'] ? 'selected' : '' ?>>
                    <?= e($katRow['nama_kategori']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group" style="margin-bottom:0;">
        <label class="form-label" style="margin-bottom:var(--space-1);">Kota / Lokasi</label>
        <select name="kota" class="form-select" aria-label="Lokasi">
            <option value="">Semua Lokasi</option>
            <?php foreach ($kotaList as $kRow): ?>
                <option value="<?= e($kRow['lokasi']) ?>" <?= $kota === $kRow['lokasi'] ? 'selected' : '' ?>>
                    <?= e($kRow['lokasi']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group" style="margin-bottom:0;">
        <label class="form-label" style="margin-bottom:var(--space-1);">Tanggal Mulai Dari</label>
        <input type="date" name="dari" class="form-control" value="<?= e($dari) ?>" aria-label="Tanggal dari">
    </div>
    <div class="form-group" style="margin-bottom:0;">
        <label class="form-label" style="margin-bottom:var(--space-1);">Sampai</label>
        <input type="date" name="sampai" class="form-control" value="<?= e($sampai) ?>" aria-label="Tanggal sampai">
    </div>
    <div class="form-group" style="margin-bottom:0;display:flex;align-items:end;">
        <label class="form-label" style="margin-bottom:var(--space-1);">Status Tersedia</label>
        <select name="status" class="form-select" aria-label="Status ketersediaan">
            <option value="" <?= $status === '' ? 'selected' : '' ?>>Semua</option>
            <option value="available" <?= $status === 'available' ? 'selected' : '' ?>>Tersedia</option>
            <option value="soldout" <?= $status === 'soldout' ? 'selected' : '' ?>>Habis</option>
        </select>
    </div>
    <div class="form-group" style="margin-bottom:0;grid-column:1 / -1;display:flex;gap:8px;">
        <button type="submit" class="btn btn-primary">Terapkan Filter</button>
        <button type="reset" class="btn btn-ghost" onclick="resetSearch()">Reset</button>
    </div>
</form>

<p style="margin:0 0 var(--space-4) 0;color:var(--color-text-muted);font-size:var(--text-sm);">
    Ditemukan <strong><?= count($events) ?></strong> event<?= count($events) !== 1 ? ':' : ':' ?>
</p>

<?php if (empty($events)): ?>
    <div class="empty-state" style="text-align:center;padding:var(--space-12) var(--space-4);">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-muted)" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="margin:0 auto var(--space-4);display:block;">
            <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17v4M12 7v4"/>
        </svg>
        <h3 style="color:var(--color-text-primary);margin:0 0 var(--space-2) 0;">Tidak ada event yang cocok</h3>
        <p style="color:var(--color-text-muted);margin:0;">Coba ubah kata kunci atau filter yang Anda pilih.</p>
    </div>
<?php else: ?>
    <div class="event-grid" id="eventGrid">
        <?php foreach ($events as $ev): ?>
            <?php
            $sisa    = (int)($ev['sisa'] ?? 0);
            $harga   = (int)($ev['harga_mulai'] ?? 0);
            $isSold  = $sisa <= 0;
            $banner  = safe_banner_url($ev['banner'] ?? '');
            ?>
            <a href="detail_event.php?id_event=<?= (int)$ev['id_event'] ?>" class="event-card" style="text-decoration:none;color:inherit;">
                <div class="event-banner" data-cat="<?= e($ev['nama_kategori']) ?>" style="<?= $banner !== '' ? "background-image:url('" . e($banner) . "');background-size:cover;background-position:center;" : '' ?>">
                    <span class="cat-chip"><?= e($ev['nama_kategori']) ?></span>
                </div>
                <div class="event-body">
                    <h3 class="event-title"><?= e($ev['judul_event']) ?></h3>
                    <div class="event-meta">
                        <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> <?= e(format_tanggal($ev['tgl_mulai'], false)) ?></span>
                        <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg> <?= e($ev['lokasi']) ?></span>
                        <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2H4.18a2 2 0 0 1-2-2v-3"/><path d="M18 14H4v3h14z"/><path d="M6 6h.01M16 6h.01"/></svg> <?= e($ev['tipe_venue']) ?></span>
                    </div>
                    <div class="event-price">
                        <strong><?= $isSold ? 'Habis' : 'Mulai ' . format_rupiah($harga) ?></strong>
                        <span class="badge <?= $isSold ? 'badge-error' : 'badge-success' ?>">
                            <?= $isSold ? 'Sold Out' : 'Tersedia (' . $sisa . ')' ?>
                        </span>
                    </div>
                    <span class="btn btn-primary btn-sm" style="width:100%;margin-top:auto;">Lihat Detail</span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
function filterSearch(term) {
    term = term.toLowerCase().trim();
    document.querySelectorAll('#eventGrid .event-card').forEach(function(card){
        var text = card.textContent || card.innerText || '';
        if (term === '' || text.toLowerCase().indexOf(term) !== -1) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}
function resetSearch() {
    var qInput = document.querySelector('input[name="q"]');
    if (qInput) qInput.value = '';
    filterSearch('');
}
</script>

<?php require __DIR__ . '/src/Views/layouts/footer.php'; ?>