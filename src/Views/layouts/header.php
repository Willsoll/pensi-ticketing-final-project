<?php
/**
 * Header layout v4 — sidebar indigo untuk pengguna login, header publik untuk tamu.
 * Variabel opsional: $pageTitle, $pageSubtitle, $activeNav
 */
if (!function_exists('current_user')) {
    require_once __DIR__ . '/../../Middleware/middleware.php';
}

$user = current_user();
$pageTitle = $pageTitle ?? 'PENSI EVENTPRO';
$pageSubtitle = $pageSubtitle ?? 'Multi-Event & Seat/Quota Ticketing Platform';
$activeNav = $activeNav ?? '';

$themeParam = $_GET['theme'] ?? null;
if (is_string($themeParam) && preg_match('/^(0[1-9]|10)$/', $themeParam)) {
    $pensi_theme = $themeParam;
    if (!headers_sent()) {
        $cookieSecure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie('pensi_theme', $pensi_theme, [
            'expires' => time() + 31536000,
            'path' => base_path() ?: '/',
            'secure' => $cookieSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
} else {
    $pensi_theme = $_COOKIE['pensi_theme'] ?? '07';
    if (!preg_match('/^(0[1-9]|10)$/', (string)$pensi_theme)) $pensi_theme = '07';
}
$themeFiles = [
    '01' => 'midnight-aurora', '02' => 'obsidian-split', '03' => 'cyber-grid',
    '04' => 'deep-space', '05' => 'carbon-neon', '06' => 'paper-minimal',
    '07' => 'aurora-glass', '08' => 'neo-brutal', '09' => 'soft-pastel', '10' => 'editorial',
];
$pensi_theme_file = $themeFiles[$pensi_theme];

function nav_icon(string $name): string
{
    $paths = [
        'grid'     => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/>',
        'ticket'   => '<path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4z"/><path d="M12 6v12" stroke-dasharray="2 3"/>',
        'chart'    => '<path d="M4 20V6"/><path d="M4 20h16"/><rect x="7" y="12" width="3" height="5"/><rect x="12" y="9" width="3" height="8"/><rect x="17" y="5" width="3" height="12"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'tag'      => '<path d="M20.6 13.4 12 22l-9-9V4h9z"/><circle cx="7.5" cy="8.5" r="1.5"/>',
        'shield'   => '<path d="M12 3 4 6v6c0 5 3.4 8.4 8 9 4.6-.6 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'check'    => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>',
        'scan'     => '<path d="M4 8V5a1 1 0 0 1 1-1h3M20 8V5a1 1 0 0 0-1-1h-3M4 16v3a1 1 0 0 0 1 1h3M20 16v3a1 1 0 0 1-1 1h-3"/><path d="M4 12h16"/>',
    ];
    $inner = $paths[$name] ?? $paths['grid'];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="<?= e($pensi_theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — PENSI EVENTPRO</title>
    <meta name="description" content="<?= e($pageSubtitle) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/design-system.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/themes/theme-' . $pensi_theme . '-' . $pensi_theme_file . '.css')) ?>">
    <?php if ((int)$pensi_theme <= 5): ?>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/themes/_dark-fixes.css')) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/ui-fixes.css?v=6.2')) ?>">
    <style>
        .skip-link { position:absolute;left:-9999px;top:0;background:#F97316;color:#111827;padding:8px 14px;z-index:9999;border-radius:0 0 8px 0; }
        .skip-link:focus { left:0; }
        @media print { .sidebar,.topbar,.public-header,.no-print,.theme-toggle{display:none !important;} .app-main{margin:0;} .page-wrap{padding:0;} }
    </style>
</head>
<body>
<a class="skip-link" href="#main">Lewati ke konten</a>

<?php if ($user): ?>
<div class="app-shell">
    <aside class="sidebar" id="sidebar" aria-label="Navigasi utama">
        <a href="<?= e(base_url('index.php')) ?>" class="sidebar-brand">
            <span class="brand-mark">P</span>
            <span><strong>PENSI EVENTPRO</strong><small>Ticket Platform</small></span>
        </a>
        <nav class="sidebar-nav">
            <?php foreach (nav_items($user) as $item): ?>
            <a href="<?= e($item['href']) ?>" class="sidebar-link <?= $activeNav === $item['href'] ? 'active' : '' ?>">
                <?= nav_icon($item['icon']) ?><span><?= e($item['label']) ?></span>
            </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-foot">
            <div class="sidebar-user">
                <span class="sidebar-avatar"><?= e(mb_strtoupper(mb_substr($user['nama_lengkap'], 0, 1))) ?></span>
                <span>
                    <span><?= e($user['nama_lengkap']) ?></span>
                    <small><?= e(label_role($user['role'])) ?></small>
                </span>
            </div>
            <form method="post" action="<?= e(base_url('logout.php')) ?>" class="sidebar-logout-form" style="margin:0;">
                <?= csrf_field() ?>
                <button type="submit" class="sidebar-logout" style="background:none;border:0;width:100%;text-align:left;cursor:pointer;font:inherit;">Keluar</button>
            </form>
        </div>
    </aside>

    <div class="app-main">
        <div class="topbar">
            <div style="display:flex;align-items:center;gap:10px;min-width:0;">
                <button class="sidebar-toggle" type="button" aria-label="Buka menu" onclick="document.getElementById('sidebar').classList.toggle('open')">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
                <div style="min-width:0;">
                    <div style="font-weight:650;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($pageTitle) ?></div>
                    <div class="topbar-title"><?= e($pageSubtitle) ?></div>
                </div>
            </div>
            <a href="<?= e(base_url('index.php')) ?>" class="btn btn-ghost btn-sm">Katalog</a>
        </div>
        <main class="page-wrap" id="main">
<?php else: ?>
<header class="public-header">
    <div class="public-header-inner">
        <a href="<?= e(base_url('index.php')) ?>" class="public-brand">
            <span class="brand-mark">P</span>
            <span>PENSI EVENTPRO</span>
        </a>
        <nav class="public-nav" aria-label="Navigasi publik">
            <a href="<?= e(base_url('index.php')) ?>" class="<?= $activeNav === 'index.php' ? 'active' : '' ?>">Katalog Event</a>
            <a href="<?= e(base_url('login.php')) ?>" class="<?= $activeNav === 'login.php' ? 'active' : '' ?>">Login &amp; Registrasi</a>
        </nav>
    </div>
</header>
<main class="page-wrap" id="main">
<?php endif; ?>

<?php if ($flash = get_flash()): ?>
<?php
$flashClass = ['SUCCESS' => 'alert-success', 'ERROR' => 'alert-danger', 'WARNING' => 'alert-warning', 'INFO' => 'alert-info'][$flash['type']] ?? 'alert-info';
?>
<div class="alert <?= e($flashClass) ?>" role="status" style="margin-bottom:18px;">
    <?= e($flash['message']) ?>
</div>
<?php endif; ?>
