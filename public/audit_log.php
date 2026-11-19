<?php
declare(strict_types=1);

/**
 * audit_log.php — Daftar jejak audit (admin_eo only).
 * Paginasi 50/halaman, filter aksi/entitas/tanggal/q, read-only.
 */
require_once __DIR__ . '/../src/Config/database.php';
require_once __DIR__ . '/../src/Middleware/middleware.php';
release_expired_transactions($pdo);

require_role(['admin_eo']);

$user = current_user();

$aksiFilter     = $_GET['aksi']     ?? '';
$entitasFilter  = $_GET['entitas']  ?? '';
$dateFrom       = $_GET['date_from'] ?? '';
$dateTo         = $_GET['date_to'] ?? '';
$queryFilter    = $_GET['q']        ?? '';
$page           = max(1, (int)($_GET['page'] ?? 1));
$perPage        = 50;
$offset         = ($page - 1) * $perPage;

$whereParts = [];
$params     = [];
if ($aksiFilter !== '')     { $whereParts[] = 'al.aksi = ?'; $params[] = $aksiFilter; }
if ($entitasFilter !== '')  { $whereParts[] = 'al.entitas = ?'; $params[] = $entitasFilter; }
if ($dateFrom !== '')       { $whereParts[] = 'DATE(al.waktu) >= ?'; $params[] = $dateFrom; }
if ($dateTo !== '')         { $whereParts[] = 'DATE(al.waktu) <= ?'; $params[] = $dateTo; }
if ($queryFilter !== '')    { $whereParts[] = 'al.keterangan LIKE ?'; $params[] = '%' . $queryFilter . '%'; }
$where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

$count = $pdo->prepare("SELECT COUNT(*) FROM audit_logs al LEFT JOIN users u ON u.id_user = al.id_user $where");
$count->execute($params);
$totalRows = (int)$count->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$rows = $pdo->prepare("
    SELECT al.id_log, al.waktu, al.aksi, al.entitas, al.keterangan,
           u.nama_lengkap, u.role
    FROM audit_logs al
    LEFT JOIN users u ON u.id_user = al.id_user
    $where
    ORDER BY al.waktu DESC, al.id_log DESC
    LIMIT ? OFFSET ?
");
$rows->execute(array_merge($params, [$perPage, $offset]));
$logs = $rows->fetchAll();

$aksiOptions   = $pdo->query('SELECT DISTINCT aksi FROM audit_logs ORDER BY aksi')->fetchAll(PDO::FETCH_COLUMN);
$entitasOptions = $pdo->query('SELECT DISTINCT entitas FROM audit_logs ORDER BY entitas')->fetchAll(PDO::FETCH_COLUMN);

$aksiBadge = [
    'CREATE' => 'badge-success', 'UPDATE' => 'badge-info', 'DELETE' => 'badge-danger',
    'EXPIRE' => 'badge-warning', 'VERIFY' => 'badge-success', 'CHECKIN' => 'badge-info',
];

$filterQuery = array_filter([
    'aksi' => $aksiFilter, 'entitas' => $entitasFilter,
    'date_from' => $dateFrom, 'date_to' => $dateTo, 'q' => $queryFilter,
], fn($v) => $v !== '');
$pageUrl = function (int $p) use ($filterQuery) {
    return 'audit_log.php?' . http_build_query(array_merge($filterQuery, ['page' => $p]));
};

$pageTitle = 'Audit Log';
$pageSubtitle = 'Jejak aktivitas data sensitif';
$activeNav = 'audit_log.php';

require __DIR__ . '/../src/Views/layouts/header.php';
?>

<div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:14px;">
    <form method="get" class="filter-bar" style="flex:1;min-width:260px;">
        <select name="aksi" class="form-select" style="min-width:150px;">
            <option value="">Semua Aksi</option>
            <?php foreach ($aksiOptions as $a): ?>
                <option value="<?= e($a) ?>" <?= ($aksiFilter === $a) ? 'selected' : '' ?>><?= e($a) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="entitas" class="form-select" style="min-width:160px;">
            <option value="">Semua Entitas</option>
            <?php foreach ($entitasOptions as $e2): ?>
                <option value="<?= e($e2) ?>" <?= ($entitasFilter === $e2) ? 'selected' : '' ?>><?= e($e2) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <form method="get" class="filter-bar" style="flex:1;min-width:260px;">
        <input type="date" name="date_from" class="form-input" value="<?= e($dateFrom) ?>">
        <input type="date" name="date_to" class="form-input" value="<?= e($dateTo) ?>">
    </form>
    <form method="get" class="filter-bar" style="flex:1;min-width:260px;">
        <input type="text" name="q" class="form-input" placeholder="Cari keterangan..." value="<?= e($queryFilter) ?>">
        <button type="submit" class="btn btn-primary">Cari</button>
        <?php if ($filterQuery): ?>
            <a class="btn btn-outline" href="audit_log.php">Reset</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <div class="card-header"><span class="card-title">Audit Log (<?= $totalRows ?> entri)</span></div>
    <div class="card-body">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Aksi</th>
                        <th>Entitas</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td><?= format_tanggal($l['waktu']) ?></td>
                            <td><?= $l['nama_lengkap'] !== null ? e($l['nama_lengkap']) : '<span style="color:var(--color-text-muted);">-</span>' ?></td>
                            <td><?= $l['role'] !== null ? e(label_role($l['role'])) : '<span style="color:var(--color-text-muted);">-</span>' ?></td>
                            <td><span class="badge <?= $aksiBadge[$l['aksi']] ?? 'badge-muted' ?>"><?= e($l['aksi']) ?></span></td>
                            <td><?= e($l['entitas']) ?></td>
                            <td><?= e($l['keterangan']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$logs): ?>
                        <tr><td colspan="6" style="color:var(--color-text-muted);text-align:center;">Tidak ada entri audit log.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($totalPages > 1): ?>
    <nav style="margin-top:14px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
        <?php if ($page > 1): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e($pageUrl($page - 1)) ?>">&larr; Sebelumnya</a>
        <?php else: ?>
            <span class="btn btn-ghost btn-sm" style="opacity:0.5;">&larr; Sebelumnya</span>
        <?php endif; ?>
        <span style="font-size:12.5px;color:var(--color-text-muted);">Halaman <?= $page ?> dari <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e($pageUrl($page + 1)) ?>">Selanjutnya &rarr;</a>
        <?php else: ?>
            <span class="btn btn-ghost btn-sm" style="opacity:0.5;">Selanjutnya &rarr;</span>
        <?php endif; ?>
    </nav>
<?php endif; ?>

<?php require __DIR__ . '/../src/Views/layouts/footer.php'; ?>
