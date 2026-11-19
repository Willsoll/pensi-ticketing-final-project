<?php
/**
 * includes/header.php
 * Header shared: branding, user info, navigasi role-based
 * Dipanggil di setiap halaman setelah require middleware
 * Uses new Design System from assets/design-system.css
 */
require_once __DIR__ . '/../../Middleware/middleware.php';

$page_title  = $page_title ?? 'Pensi School - Event Ticketing';
$logged_user = current_user();
$flash       = get_flash();

/* ===== Theme engine: 10 design iterations (5 dark, 5 light) =====
   Selectable via ?theme=NN or cookie pensi_theme. Default 07 Aurora Glass. */
$pensi_themes = [
    '01' => 'midnight-aurora',
    '02' => 'obsidian-split',
    '03' => 'cyber-grid',
    '04' => 'deep-space',
    '05' => 'carbon-neon',
    '06' => 'paper-minimal',
    '07' => 'aurora-glass',
    '08' => 'neo-brutal',
    '09' => 'soft-pastel',
    '10' => 'editorial',
];
$pensi_theme = '07';
if (isset($_GET['theme']) && is_string($_GET['theme']) && isset($pensi_themes[$_GET['theme']])) {
    $pensi_theme = $_GET['theme'];
    setcookie('pensi_theme', $pensi_theme, [
        'expires'  => time() + 31536000,
        'path'     => '/',
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
} elseif (isset($_COOKIE['pensi_theme']) && is_string($_COOKIE['pensi_theme']) && isset($pensi_themes[$_COOKIE['pensi_theme']])) {
    $pensi_theme = $_COOKIE['pensi_theme'];
}
$pensi_theme_file = $pensi_themes[$pensi_theme];
?>
<!DOCTYPE html>
<html lang="id" data-theme="<?= e($pensi_theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/design-system.css">
    <link rel="stylesheet" href="/assets/css/themes/theme-<?= e($pensi_theme) ?>-<?= e($pensi_theme_file) ?>.css">
    <?php if ((int)$pensi_theme <= 5): ?>
    <link rel="stylesheet" href="/assets/css/themes/_dark-fixes.css">
    <?php endif; ?>
    <script src="/assets/js/ui.js" defer></script>
    <style>
        .seat-map { display: grid; grid-template-columns: repeat(auto-fill, minmax(64px, 1fr)); gap: 8px; margin: 16px 0; }
        .seat { aspect-ratio: 1; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; border-radius: 8px; cursor: pointer; transition: all 120ms ease; border: 2px solid transparent; position: relative; }
        .seat:focus-visible { outline: 2px solid var(--color-border-focus); outline-offset: 2px; }
        .seat-available { background: var(--color-success-bg); color: var(--color-success); border-color: var(--color-success-border); }
        .seat-available:hover { background: var(--color-success-border); color: var(--color-success); transform: scale(1.05); }
        .seat-locked { background: var(--color-warning-bg); color: var(--color-warning); border-color: var(--color-warning-border); text-decoration: line-through; cursor: not-allowed; }
        .seat-sold { background: var(--color-error-bg); color: var(--color-error); border-color: var(--color-error-border); text-decoration: line-through; cursor: not-allowed; }
        .seat input { position: absolute; opacity: 0; pointer-events: none; }
        .stage-indicator { grid-column: 1 / -1; text-align: center; padding: 12px; background: var(--color-bg-muted); border: 2px dashed var(--color-border-medium); border-radius: 8px; font-weight: 600; color: var(--color-primary); margin: 12px 0; }
        .etiket-print { max-width: 700px; margin: 0 auto; background: var(--color-bg-card); border: 3px solid var(--color-header); border-radius: var(--radius-xl); padding: 32px; box-shadow: var(--shadow-xl); }
        .modal-overlay { position: fixed; inset: 0; background: rgba(17, 24, 39, 0.5); backdrop-filter: blur(4px); z-index: var(--z-modal-backdrop); display: flex; align-items: center; justify-content: center; padding: 16px; animation: fadeIn 200ms ease; }
        .modal { background: var(--color-bg-card); border-radius: var(--radius-xl); box-shadow: var(--shadow-2xl); max-width: 560px; width: 100%; max-height: 90vh; overflow-y: auto; animation: slideUp 200ms ease; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @media print {
            .header, .footer, .breadcrumb, .btn, .no-print, .header-nav, .header-user, .header-nav-link, .modal-overlay { display: none !important; }
            .main { padding: 0; max-width: none; }
            .etiket-print { border: none; padding: 0; box-shadow: none; page-break-after: always; }
            body { background: white; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .card { box-shadow: none; border: 1px solid var(--color-border-light); break-inside: avoid; }
        }
    </style>
</head>
<body>

<header class="header">
    <div class="header-inner">
        <div class="header-brand">
            <svg class="header-logo" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <rect width="40" height="40" rx="8" fill="var(--color-primary)"/>
                <path d="M12 20L18 26L28 14" stroke="var(--color-cta)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="20" cy="20" r="10" stroke="var(--color-cta)" stroke-width="1.5" fill="none" opacity="0.3"/>
            </svg>
            <div>
                <h1 class="header-title">PENSI SCHOOL</h1>
                <p class="header-subtitle">Pentas Seni Sekolah &bull; Event Ticketing Platform</p>
            </div>
        </div>
        
        <div class="header-user">
            <?php if ($logged_user): ?>
                <div>
                    <div class="header-user-name"><?= e($logged_user['nama']) ?></div>
                    <span class="header-user-role">[<?= e(label_role($logged_user['role'])) ?>] &bull; <?= e(label_tipe_pengguna($logged_user['tipe_pengguna'])) ?></span>
                </div>
            <?php else: ?>
                <span class="header-user-role" style="background: rgba(234,179,8,0.15); color: var(--color-cta);">PENGUNJUNG PUBLIK (GUEST)</span>
            <?php endif; ?>
        </div>
        
        <nav class="header-nav" aria-label="Navigasi utama">
            <a href="index.php" class="header-nav-link">Katalog Acara</a>
            <?php if (!$logged_user): ?>
                <a href="login.php" class="header-nav-link">Login & Registrasi</a>
            <?php else: ?>
                <?php if ($logged_user['role'] === 'pembeli'): ?>
                    <a href="dashboard_peserta.php" class="header-nav-link">Dashboard Peserta & E-Ticket</a>
                <?php elseif ($logged_user['role'] === 'admin_osis'): ?>
                    <a href="dashboard_panitia.php" class="header-nav-link">Dashboard Panitia OSIS & Keuangan</a>
                    <a href="crud_tiket.php" class="header-nav-link">CRUD Master Data</a>
                    <a href="gate_checkin.php" class="header-nav-link">Gate Check-in & Audit Log</a>
                <?php elseif ($logged_user['role'] === 'panitia_keuangan'): ?>
                    <a href="verifikasi_pembayaran.php" class="header-nav-link">Verifikasi Pembayaran</a>
                    <a href="gate_checkin.php" class="header-nav-link">Audit Log Sistem</a>
                <?php elseif ($logged_user['role'] === 'petugas_gate'): ?>
                    <a href="gate_checkin.php" class="header-nav-link">Gate Check-in & Audit Log</a>
                <?php endif; ?>
                <a href="logout.php?csrf=<?= e(generate_csrf_token()) ?>" class="header-nav-link btn-ghost" onclick="return confirm('Keluar dari akun Anda?');">Logout</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="main">
    <?php if ($flash): ?>
        <?php $flash_type = in_array(($flash['type'] ?? ''), ['SUKSES', 'ERROR', 'INFO'], true) ? $flash['type'] : 'INFO'; ?>
        <div class="alert alert-<?= strtolower($flash_type) ?>" role="alert">
            <div class="alert-icon">
                <?php if ($flash_type === 'SUKSES'): ?>
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                <?php elseif ($flash_type === 'ERROR'): ?>
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                <?php else: ?>
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                <?php endif; ?>
            </div>
            <div class="alert-content">
                <div class="alert-title"><?= e($flash_type) ?>:</div>
                <div class="alert-message"><?= e($flash['message'] ?? '') ?></div>
            </div>
            <button class="alert-dismiss" onclick="this.parentElement.remove()" aria-label="Tutup notifikasi">
                <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"/></svg>
            </button>
        </div>
    <?php endif; ?>
