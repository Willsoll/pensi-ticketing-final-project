<?php
/**
 * includes/header.php
 * Header shared: branding, user info, navigasi role-based
 * Dipanggil di setiap halaman setelah require middleware
 */
require_once __DIR__ . '/../config/middleware.php';

$page_title  = $page_title ?? 'Pensi School - Event Ticketing';
$logged_user = current_user();
$flash       = get_flash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #FFFFFF; color: #111827; line-height: 1.6; }
        table { border-collapse: collapse; width: 100%; }
        th, td { padding: 8px 10px; border: 1px solid #E5E7EB; }
        th { background: #4F46E5; color: white; text-align: left; }
        tr:nth-child(even) { background: #F9FAFB; }
        a { color: #4F46E5; text-decoration: none; }
        a:hover { text-decoration: underline; }
        button, input[type="submit"], input[type="button"] {
            background: #4F46E5; color: white; border: none; padding: 10px 18px;
            border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px;
        }
        button:hover, input[type="submit"]:hover { background: #4338CA; }
        button.secondary, input.secondary { background: #6B7280; }
        button.secondary:hover, input.secondary:hover { background: #4B5563; }
        button.danger { background: #E11D48; }
        button.danger:hover { background: #BE123C; }
        button.success { background: #16A34A; }
        button.success:hover { background: #15803D; }
        input[type="text"], input[type="email"], input[type="password"], input[type="number"], input[type="tel"], select, textarea {
            padding: 8px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 14px; width: 100%;
        }
        input:focus, select:focus, textarea:focus { outline: none; border-color: #4F46E5; box-shadow: 0 0 0 3px rgba(79,70,229,0.15); }
        fieldset { border: 1px solid #E5E7EB; border-radius: 8px; padding: 16px; margin: 16px 0; }
        legend { font-weight: 700; color: #111827; padding: 0 8px; }
        .flash { padding: 12px 16px; border-radius: 6px; margin: 16px 0; font-weight: 500; }
        .flash.SUKSES { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
        .flash.ERROR { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
        .flash.INFO { background: #EFF6FF; color: #1E40AF; border: 1px solid #BFDBFE; }
        header { background: #111827; color: white; padding: 16px 24px; }
        header h1 { font-size: 1.5rem; font-weight: 700; }
        header .sub { color: #EAB308; font-size: 0.875rem; margin-top: 4px; }
        header .nav { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 12px; font-size: 0.875rem; }
        header .nav a { color: #EAB308; padding: 6px 12px; border-radius: 4px; background: rgba(234,179,8,0.1); }
        header .nav a:hover { background: rgba(234,179,8,0.2); }
        header .user-info { float: right; text-align: right; font-size: 0.875rem; }
        header .user-info .role { color: #EAB308; font-weight: 600; }
        main { padding: 24px; max-width: 1200px; margin: 0 auto; }
        .breadcrumb { margin-bottom: 16px; font-size: 0.875rem; }
        .breadcrumb a { color: #6B7280; }
        .card { background: white; border: 1px solid #E5E7EB; border-radius: 10px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #E5E7EB; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; }
        .badge-available { background: #D1FAE5; color: #065F46; }
        .badge-locked { background: #FEF3C7; color: #92400E; }
        .badge-sold { background: #FEE2E2; color: #991B1B; }
        .badge-pending { background: #FEF3C7; color: #92400E; }
        .badge-waiting { background: #DBEAFE; color: #1E40AF; }
        .badge-paid { background: #D1FAE5; color: #065F46; }
        .badge-expired { background: #F3F4F6; color: #6B7280; }
        .badge-rejected { background: #FEE2E2; color: #991B1B; }
        .badge-belum-masuk { background: #DBEAFE; color: #1E40AF; }
        .badge-sudah-masuk { background: #D1FAE5; color: #065F46; }
        .badge-internal { background: #E0E7FF; color: #3730A3; }
        .badge-eksternal { background: #FCE7F3; color: #9D174D; }
        .seat-map { display: grid; grid-template-columns: repeat(auto-fill, minmax(70px, 1fr)); gap: 8px; margin: 16px 0; }
        .seat { padding: 10px; text-align: center; border-radius: 6px; font-size: 0.75rem; cursor: pointer; transition: all 0.15s; }
        .seat-available { background: #D1FAE5; color: #065F46; border: 2px solid #A7F3D0; }
        .seat-available:hover { background: #A7F3D0; }
        .seat-locked { background: #FEF3C7; color: #92400E; border: 2px solid #FCD34D; text-decoration: line-through; cursor: not-allowed; }
        .seat-sold { background: #FEE2E2; color: #991B1B; border: 2px solid #FCA5A5; text-decoration: line-through; cursor: not-allowed; }
        .seat input { display: none; }
        .seat input:checked + label { background: #4F46E5 !important; color: white !important; border-color: #4F46E5 !important; }
        .countdown { font-size: 2rem; font-weight: 700; color: #E11D48; font-family: monospace; }
        .etiket-print { max-width: 600px; margin: 0 auto; border: 3px solid #111827; padding: 24px; }
        .etiket-print h2 { color: #4F46E5; text-align: center; margin-bottom: 8px; }
        .etiket-print .barcode { text-align: center; margin: 20px 0; }
        .etiket-print table { font-size: 0.875rem; }
        @media print {
            header, .breadcrumb, button, .no-print { display: none !important; }
            main { padding: 0; max-width: none; }
            .etiket-print { border: none; padding: 0; }
            body { background: white; }
        }
    </style>
</head>
<body>

<header>
    <div style="float: left;">
        <h1>PENSI SCHOOL</h1>
        <div class="sub">Pentas Seni Sekolah &bull; Event Ticketing Platform</div>
    </div>
    <div class="user-info">
        <?php if ($logged_user): ?>
            <div><strong><?= e($logged_user['nama']) ?></strong></div>
            <div class="role">[<?= e(label_role($logged_user['role'])) ?>] &bull; <?= e(label_tipe_pengguna($logged_user['tipe_pengguna'])) ?></div>
        <?php else: ?>
            <div class="role">PENGUNJUNG PUBLIK (GUEST)</div>
        <?php endif; ?>
    </div>
    <div style="clear: both;"></div>
    <nav class="nav">
        <a href="index.php">[ Katalog Acara ]</a>
        <?php if (!$logged_user): ?>
            <a href="login.php">[ Login & Registrasi ]</a>
        <?php else: ?>
            <?php if ($logged_user['role'] === 'pembeli'): ?>
                <a href="dashboard_peserta.php">[ Dashboard Peserta & E-Ticket ]</a>
            <?php elseif ($logged_user['role'] === 'admin_osis'): ?>
                <a href="dashboard_panitia.php">[ Dashboard Panitia OSIS & Keuangan ]</a>
                <a href="crud_tiket.php">[ CRUD Jenis Tiket, Rundown & Promo ]</a>
                <a href="gate_checkin.php">[ Gate Check-in & Audit Log ]</a>
            <?php elseif ($logged_user['role'] === 'panitia_keuangan'): ?>
                <a href="verifikasi_pembayaran.php">[ Verifikasi Pembayaran ]</a>
                <a href="gate_checkin.php">[ Audit Log Sistem ]</a>
            <?php elseif ($logged_user['role'] === 'petugas_gate'): ?>
                <a href="gate_checkin.php">[ Gate Check-in & Audit Log ]</a>
            <?php endif; ?>
            <a href="logout.php?csrf=<?= e(generate_csrf_token()) ?>" onclick="return confirm('Keluar dari akun Anda?');">[ Logout ]</a>
        <?php endif; ?>
    </nav>
</header>

<br>

<main>

<?php if ($flash): ?>
    <?php $flash_type = in_array(($flash['type'] ?? ''), ['SUKSES', 'ERROR', 'INFO'], true) ? $flash['type'] : 'INFO'; ?>
    <div class="flash <?= e($flash_type) ?>">
        <strong><?= e($flash_type) ?>:</strong> <?= e($flash['message'] ?? '') ?>
    </div>
<?php endif; ?>