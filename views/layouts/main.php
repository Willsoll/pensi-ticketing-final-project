<?php
// views/layouts/main.php
// Main layout template - PAKET 7 Exact Color Palette + Stripe-inspired Design System

/**
 * @var array $app
 * @var array $pageTitle
 * @var array|null $current_user
 * @var array $flash
 * @var string $csrf_token
 * @var string $csrf_field
 * @var string $csrf_meta
 * @var callable $e
 * @var callable $attr
 * @var callable $rupiah
 * @var callable $date
 * @var callable $datetime
 * @var callable $mask
 * @var string|null $csp_nonce
 */
$csp_nonce = $csp_nonce ?? ($_SERVER['CSP_NONCE'] ?? null);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $e($pageTitle ?? $app['name']) ?></title>
    <?= $csrf_meta ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            /* ===== PAKET 7 EXACT COLOR PALETTE (from ROMBEL 3.md) ===== */
            --color-indigo-dark: #1E1B4B;       /* Header/Sidebar - Biru Indigo Gelap */
            --color-purple-electric: #6D28D9;   /* Primary Accent - Ungu Elektrik */
            --color-orange-bright: #F97316;     /* CTA Buttons - Oranye Terang */
            --color-slate-light: #F8FAFC;       /* Main Background - Abu-abu Slate */
            --color-emerald: #059669;           /* Status Lunas/Valid - Hijau Zamrud */

            /* ===== EXTENDED SEMANTIC COLORS ===== */
            --color-white: #FFFFFF;
            --color-indigo-darker: #0F142A;     /* Deep navy for headings (Stripe-style) */
            --color-indigo-light: #312E81;      /* Lighter indigo for hover states */
            --color-purple-light: #8B5CF6;      /* Lighter purple for hover */
            --color-purple-lighter: #A5B4FC;    /* Focus ring / subtle backgrounds */
            --color-orange-light: #FB923C;      /* Orange hover */
            --color-orange-lighter: #FED7AA;    /* Orange subtle backgrounds */
            --color-emerald-light: #10B981;     /* Emerald hover */
            --color-emerald-lighter: #D1FAE5;   /* Emerald subtle backgrounds */
            --color-red: #DC2626;               /* Error/Danger */
            --color-red-light: #EF4444;         /* Red hover */
            --color-red-lighter: #FEE2E2;       /* Red subtle backgrounds */
            --color-amber: #D97706;             /* Warning */
            --color-amber-light: #FBBF24;       /* Amber hover */
            --color-amber-lighter: #FEF3C7;     /* Amber subtle backgrounds */

            /* ===== NEUTRAL SCALE (Slate) ===== */
            --color-slate-50: #F8FAFC;
            --color-slate-100: #F1F5F9;
            --color-slate-200: #E2E8F0;
            --color-slate-300: #CBD5E1;
            --color-slate-400: #94A3B8;
            --color-slate-500: #64748B;
            --color-slate-600: #475569;
            --color-slate-700: #334155;
            --color-slate-800: #1E293B;
            --color-slate-900: #0F172A;
            --color-slate-950: #020617;

            /* ===== SHADOW SYSTEM (Stripe-inspired blue-tinted) ===== */
            --shadow-ambient: rgba(30, 27, 75, 0.06) 0px 2px 4px;
            --shadow-sm: rgba(30, 27, 75, 0.08) 0px 4px 6px -2px;
            --shadow-md: rgba(30, 27, 75, 0.10) 0px 10px 15px -3px;
            --shadow-lg: rgba(30, 27, 75, 0.12) 0px 20px 25px -5px;
            --shadow-xl: rgba(30, 27, 75, 0.25) 0px 25px 50px -12px, rgba(0, 0, 0, 0.1) 0px 12px 24px -12px;
            --shadow-2xl: rgba(15, 20, 42, 0.25) 0px 30px 60px -12px, rgba(0, 0, 0, 0.1) 0px 18px 36px -18px;
            --shadow-focus: 0 0 0 3px var(--color-purple-lighter);
            --shadow-inset: inset 0 2px 4px 0 rgba(30, 27, 75, 0.06);

            /* ===== BORDER RADIUS SCALE ===== */
            --radius-none: 0;
            --radius-xs: 2px;
            --radius-sm: 4px;
            --radius-md: 6px;
            --radius-lg: 8px;
            --radius-xl: 12px;
            --radius-2xl: 16px;
            --radius-full: 9999px;

            /* ===== SPACING SYSTEM (8px base) ===== */
            --space-0: 0;
            --space-1: 4px;
            --space-2: 8px;
            --space-3: 12px;
            --space-4: 16px;
            --space-5: 20px;
            --space-6: 24px;
            --space-8: 32px;
            --space-10: 40px;
            --space-12: 48px;
            --space-16: 64px;
            --space-20: 80px;

            /* ===== TRANSITIONS ===== */
            --transition-fast: 120ms ease-out;
            --transition-base: 200ms ease-out;
            --transition-slow: 300ms ease-out;

            /* ===== Z-INDEX ===== */
            --z-dropdown: 100;
            --z-sticky: 200;
            --z-fixed: 300;
            --z-modal-backdrop: 400;
            --z-modal: 500;
            --z-popover: 600;
            --z-tooltip: 700;
            --z-toast: 800;
        }

        /* ===== RESET & BASE ===== */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            font-size: 16px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--color-slate-light);
            color: var(--color-slate-700);
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ===== TYPOGRAPHY (Stripe-inspired: weight 300 for headings) ===== */
        h1, h2, h3, h4, h5, h6 {
            font-weight: 300;
            color: var(--color-indigo-darker);
            line-height: 1.15;
            letter-spacing: -0.02em;
        }

        h1 { font-size: clamp(1.75rem, 4vw, 2.5rem); }
        h2 { font-size: clamp(1.5rem, 3vw, 2rem); }
        h3 { font-size: clamp(1.25rem, 2.5vw, 1.5rem); font-weight: 400; }
        h4 { font-size: clamp(1.125rem, 2vw, 1.25rem); font-weight: 500; }

        p { line-height: 1.6; color: var(--color-slate-600); }

        a {
            color: var(--color-purple-electric);
            text-decoration: none;
            transition: color var(--transition-fast);
        }

        a:hover { color: var(--color-purple-light); }

        /* Focus visible for accessibility */
        *:focus-visible {
            outline: none;
            box-shadow: var(--shadow-focus);
        }

        /* ===== LAYOUT ===== */
        .main {
            flex: 1;
            padding: var(--space-6) var(--space-4);
            width: 100%;
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            width: 100%;
        }

        /* ===== HEADER ===== */
        .header {
            background-color: var(--color-indigo-dark);
            color: var(--color-white);
            padding: var(--space-4) var(--space-4);
            position: sticky;
            top: 0;
            z-index: var(--z-sticky);
            box-shadow: var(--shadow-md);
        }

        .header-inner {
            max-width: 1280px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: var(--space-4);
            flex-wrap: wrap;
        }

        .logo {
            font-size: 1.375rem;
            font-weight: 700;
            color: var(--color-white);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: var(--space-2);
            letter-spacing: -0.02em;
        }

        .logo:hover { color: var(--color-purple-lighter); }

        .nav {
            display: flex;
            align-items: center;
            gap: var(--space-1);
            flex-wrap: wrap;
        }

        .nav-link {
            color: var(--color-slate-300);
            padding: var(--space-2) var(--space-3);
            border-radius: var(--radius-md);
            font-weight: 500;
            font-size: 0.875rem;
            transition: all var(--transition-fast);
            white-space: nowrap;
        }

        .nav-link:hover {
            color: var(--color-white);
            background-color: rgba(255, 255, 255, 0.1);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-2);
            padding: var(--space-2) var(--space-4);
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: var(--radius-md);
            border: none;
            cursor: pointer;
            transition: all var(--transition-fast);
            text-decoration: none;
            white-space: nowrap;
        }

        .btn:disabled { opacity: 0.5; cursor: not-allowed; }

        .btn-primary {
            background: var(--color-orange-bright);
            color: var(--color-white);
        }
        .btn-primary:hover:not(:disabled) { background: var(--color-orange-light); }
        .btn-primary:active { background: #E85D0A; }

        .btn-secondary {
            background: var(--color-slate-200);
            color: var(--color-slate-700);
        }
        .btn-secondary:hover:not(:disabled) { background: var(--color-slate-300); }

        .btn-ghost {
            background: transparent;
            color: var(--color-purple-electric);
            border: 1px solid var(--color-purple-lighter);
        }
        .btn-ghost:hover:not(:disabled) { background: rgba(109, 40, 217, 0.08); }

        .btn-danger {
            background: var(--color-red);
            color: var(--color-white);
        }
        .btn-danger:hover:not(:disabled) { background: var(--color-red-light); }

        .btn-success {
            background: var(--color-emerald);
            color: var(--color-white);
        }
        .btn-success:hover:not(:disabled) { background: var(--color-emerald-light); }

        .btn-sm { padding: var(--space-1) var(--space-3); font-size: 0.8125rem; }

        /* Input styles */
        .input {
            width: 100%;
            padding: var(--space-2) var(--space-3);
            font-size: 0.875rem;
            border: 1px solid var(--color-slate-300);
            border-radius: var(--radius-md);
            background: var(--color-white);
            color: var(--color-slate-900);
            transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
        }

        .input:focus {
            outline: none;
            border-color: var(--color-purple-electric);
            box-shadow: var(--shadow-focus);
        }

        .input-error { border-color: var(--color-red); }
        .input-error:focus { box-shadow: 0 0 0 3px var(--color-red-lighter); }

        label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--color-slate-700);
            margin-bottom: var(--space-1);
        }

        .form-group { margin-bottom: var(--space-4); }

        .error-text {
            color: var(--color-red);
            font-size: 0.8125rem;
            margin-top: var(--space-1);
        }

        .card {
            background: var(--color-white);
            border-radius: var(--radius-lg);
            border: 1px solid var(--color-slate-200);
            box-shadow: var(--shadow-sm);
        }

        .card-header {
            padding: var(--space-4) var(--space-6);
            border-bottom: 1px solid var(--color-slate-200);
        }

        .card-body { padding: var(--space-6); }

        .card-footer {
            padding: var(--space-4) var(--space-6);
            border-top: 1px solid var(--color-slate-200);
            background: var(--color-slate-50);
            border-radius: 0 0 var(--radius-lg) var(--radius-lg);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: var(--radius-full);
        }

        .badge-primary { background: var(--color-purple-lighter); color: var(--color-indigo-darker); }
        .badge-success { background: var(--color-emerald-lighter); color: var(--color-emerald); }
        .badge-warning { background: var(--color-amber-lighter); color: var(--color-amber); }
        .badge-danger { background: var(--color-red-lighter); color: var(--color-red); }
        .badge-info { background: var(--color-purple-lighter); color: var(--color-purple-electric); }
        .badge-neutral { background: var(--color-slate-100); color: var(--color-slate-600); }

        .table-container { overflow-x: auto; }

        table { width: 100%; border-collapse: collapse; }

        th, td { padding: var(--space-3) var(--space-4); text-align: left; border-bottom: 1px solid var(--color-slate-200); }

        th {
            font-weight: 600;
            color: var(--color-slate-700);
            background: var(--color-slate-50);
            font-size: 0.8125rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        td { color: var(--color-slate-900); font-size: 0.875rem; }

        tr:last-child td { border-bottom: none; }
        tr:hover td { background: var(--color-slate-50); }

        .alert {
            padding: var(--space-4) var(--space-5);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-4);
            display: flex;
            align-items: flex-start;
            gap: var(--space-3);
        }

        .alert-success { background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; }
        .alert-error { background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; }
        .alert-warning { background: #FFFBEB; border: 1px solid #FDE68A; color: #92400E; }
        .alert-info { background: #EFF6FF; border: 1px solid #BFDBFE; color: #1E40AF; }

        .flash-messages {
            position: fixed;
            top: var(--space-4);
            right: var(--space-4);
            z-index: var(--z-toast);
            display: flex;
            flex-direction: column;
            gap: var(--space-2);
        }

        .toast {
            padding: var(--space-4) var(--space-6);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-xl);
            min-width: 300px;
            max-width: 480px;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(100%); }
            to { opacity: 1; transform: translateX(0); }
        }

        .toast-success { background: var(--color-emerald); color: var(--color-white); }
        .toast-error { background: var(--color-red); color: var(--color-white); }
        .toast-warning { background: var(--color-amber); color: #92400E; }
        .toast-info { background: var(--color-purple-electric); color: var(--color-white); }

        /* Page header */
        .page-header { margin-bottom: var(--space-8); }
        .page-title { font-size: 1.875rem; font-weight: 700; color: var(--color-indigo-darker); margin-bottom: var(--space-2); }
        .page-subtitle { color: var(--color-slate-500); font-size: 1rem; }

        .breadcrumb { display: flex; align-items: center; gap: var(--space-2); font-size: 0.875rem; color: var(--color-slate-500); margin-bottom: var(--space-6); }
        .breadcrumb a { color: var(--color-slate-500); }
        .breadcrumb a:hover { color: var(--color-purple-electric); }
        .breadcrumb-separator { color: var(--color-slate-400); }
        .breadcrumb-current { color: var(--color-slate-900); font-weight: 500; }

        /* Grid */
        .grid { display: grid; gap: var(--space-6); }
        .grid-2 { grid-template-columns: repeat(2, 1fr); }
        .grid-3 { grid-template-columns: repeat(3, 1fr); }
        .grid-4 { grid-template-columns: repeat(4, 1fr); }

        @media (max-width: 1024px) { .grid-4, .grid-3 { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) { .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; } }

        /* Stat cards */
        .stat-card { padding: var(--space-6); }
        .stat-value { font-size: 2rem; font-weight: 700; color: var(--color-indigo-darker); margin-bottom: var(--space-1); }
        .stat-label { font-size: 0.875rem; color: var(--color-slate-500); }
        .stat-card.orange .stat-value { color: var(--color-orange-bright); }
        .stat-card.success .stat-value { color: var(--color-emerald); }
        .stat-card.purple .stat-value { color: var(--color-purple-electric); }
        .stat-card.warning .stat-value { color: var(--color-amber); }

        /* Seat map */
        .seat-map { display: grid; gap: var(--space-2); padding: var(--space-4); }
        .seat { aspect-ratio: 1; display: flex; align-items: center; justify-content: center; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: all var(--transition-fast); user-select: none; }
        .seat-available { background: var(--color-emerald); color: var(--color-white); }
        .seat-available:hover { background: var(--color-emerald-light); transform: scale(1.05); }
        .seat-selected { background: var(--color-purple-electric); color: var(--color-white); }
        .seat-locked { background: var(--color-amber); color: var(--color-amber-lighter); cursor: not-allowed; }
        .seat-sold { background: var(--color-slate-300); color: var(--color-slate-500); cursor: not-allowed; }
        .seat input { display: none; }

        /* Modal */
        .modal-overlay { position: fixed; inset: 0; background: rgba(15, 20, 42, 0.5); display: flex; align-items: center; justify-content: center; z-index: var(--z-modal-backdrop); padding: var(--space-4); }
        .modal { background: var(--color-white); border-radius: var(--radius-xl); max-width: 32rem; width: 100%; max-height: 90vh; overflow-y: auto; }
        .modal-header { padding: var(--space-5) var(--space-6); border-bottom: 1px solid var(--color-slate-200); display: flex; align-items: center; justify-content: space-between; }
        .modal-title { font-size: 1.125rem; font-weight: 700; color: var(--color-indigo-darker); }
        .modal-close { background: none; border: none; color: var(--color-slate-500); cursor: pointer; padding: var(--space-1); }
        .modal-close:hover { color: var(--color-red); }
        .modal-body { padding: var(--space-6); }
        .modal-footer { padding: var(--space-4) var(--space-6); border-top: 1px solid var(--color-slate-200); display: flex; justify-content: flex-end; gap: var(--space-3); }

        /* Footer */
        footer { background: var(--color-indigo-dark); color: var(--color-white); padding: var(--space-8) var(--space-4); margin-top: auto; }
        footer .container { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: var(--space-4); }
        footer p { color: var(--color-slate-400); font-size: 0.875rem; }

        /* Print styles */
        @media print {
            .header, .nav, .btn, .flash-messages, .modal-overlay { display: none !important; }
            .main { padding: 0; }
            .card { box-shadow: none; border: 1px solid #ccc; }
            body { background: var(--color-white); }
        }

        /* Utility */
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
    </style>
    <?php if ($csp_nonce): ?>
    <script nonce="<?= $e($csp_nonce) ?>">
        window.cspNonce = '<?= $e($csp_nonce) ?>';
    </script>
    <?php endif; ?>
</head>
<body>
    <div class="flash-messages" id="flash-messages"></div>

    <header class="header">
        <div class="header-inner">
            <a href="/" class="logo">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                </svg>
                Pensi Ticketing
            </a>

            <nav class="nav">
                <?php if ($current_user): ?>
                    <?php if ($current_user['role'] === 'customer'): ?>
                        <a href="/my-bookings" class="nav-link">Pemesanan Saya</a>
                    <?php elseif ($current_user['role'] === 'admin_eo'): ?>
                        <a href="/admin" class="nav-link">Dashboard</a>
                        <a href="/admin/events" class="nav-link">Kelola Event</a>
                        <a href="/admin/tiers" class="nav-link">Tier Tiket</a>
                        <a href="/admin/vouchers" class="nav-link">Voucher</a>
                        <a href="/admin/analytics" class="nav-link">Analitik</a>
                    <?php elseif ($current_user['role'] === 'finance'): ?>
                        <a href="/finance" class="nav-link">Verifikasi Pembayaran</a>
                        <a href="/gate" class="nav-link">Gate Check-in</a>
                    <?php elseif ($current_user['role'] === 'gate_officer'): ?>
                        <a href="/gate" class="nav-link">Gate Check-in</a>
                        <a href="/gate/audit" class="nav-link">Audit Log</a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="/login" class="nav-link">Masuk</a>
                    <a href="/register" class="btn btn-primary btn-sm">Daftar</a>
                <?php endif; ?>
            </nav>

            <?php if ($current_user): ?>
            <div class="user-menu">
                <div class="dropdown">
                    <button class="user-avatar">
                        <?= $e(strtoupper(mb_substr($current_user['nama'], 0, 1))) ?>
                    </button>
                    <div class="dropdown-menu">
                        <span class="dropdown-item" style="font-weight: 600;"><?= $e($current_user['nama']) ?></span>
                        <span class="dropdown-item" style="font-size: 0.75rem; color: var(--color-slate-500);"><?= $e(ucfirst($current_user['role'])) ?></span>
                        <div class="dropdown-divider"></div>
                        <a href="/logout" class="dropdown-item" style="color: var(--color-red);">Keluar</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <main class="main">
        <div class="container">
            <?php if (!empty($flash)): ?>
                <div class="flash-messages">
                    <?php foreach ($flash as $type => $message): ?>
                        <div class="toast toast-<?= $e($type) ?>">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <?php if ($type === 'success'): ?>
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    <?php elseif ($type === 'error'): ?>
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10 7.293 11.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293a1 1 0 00-1.414 0z" clip-rule="evenodd"/>
                                    <?php else: ?>
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                    <?php endif; ?>
                                </svg>
                                <span><?= $e($message) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div id="content">
                <!-- Page content will be inserted here -->
            </div>
        </div>
    </main>

    <footer>
        <div class="container" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
            <p style="color: var(--color-slate-400); font-size: 0.875rem;">© <?= date('Y') ?> Pensi Ticketing Platform. All rights reserved.</p>
            <p style="color: var(--color-slate-400); font-size: 0.875rem;">Server Time: <?= date('d-m-Y H:i:s') ?> WIB</p>
        </div>
    </footer>

    <script nonce="<?= $e($csp_nonce ?? '') ?>">
        // Flash messages auto-hide
        document.addEventListener('DOMContentLoaded', function() {
            const flashContainer = document.getElementById('flash-messages');
            if (flashContainer) {
                const toasts = flashContainer.querySelectorAll('.toast');
                toasts.forEach(toast => {
                    setTimeout(() => {
                        toast.style.opacity = '0';
                        toast.style.transform = 'translateX(100%)';
                        setTimeout(() => toast.remove(), 300);
                    }, 5000);
                });
            }

            // Dropdown menus
            document.querySelectorAll('.dropdown').forEach(dropdown => {
                dropdown.addEventListener('mouseenter', () => dropdown.classList.add('open'));
                dropdown.addEventListener('mouseleave', () => dropdown.classList.remove('open'));
            });

            // CSRF token for AJAX
            window.csrfToken = '<?= $e($csrf_token) ?>';

            // Helper for AJAX requests
            window.api = {
                post: async (url, data) => {
                    const formData = new FormData();
                    formData.append('csrf_token', window.csrfToken);
                    for (const [key, value] of Object.entries(data)) {
                        formData.append(key, value);
                    }
                    const response = await fetch(url, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    });
                    return response.json();
                }
            };
        });
    </script>
</body>
</html>