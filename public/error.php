<?php
/**
 * error.php - Halaman kesalahan sederhana (menggunakan header/footer)
 * Pesan diambil dari ?msg (disaring) atau pesan generik.
 */
require_once __DIR__ . '/../src/Config/database.php';
require_once __DIR__ . '/../src/Middleware/middleware.php';

$msg = trim($_GET['msg'] ?? '');
if ($msg === '') {
    $msg = 'Halaman yang Anda cari tidak tersedia.';
}

$pageTitle   = 'Kesalahan';
$pageSubtitle = 'Maaf, ada yang tidak sesuai.';
$activeNav   = '';
require __DIR__ . '/../src/Views/layouts/header.php';
?>

<div style="max-width:560px;margin:0 auto;text-align:center;">
    <div class="card card-elevated" style="margin-top:var(--space-10);">
        <div class="card-body" style="padding:var(--space-10) var(--space-6);">
            <svg width="72" height="72" viewBox="0 0 24 24" fill="none" stroke="var(--color-error)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="margin:0 auto var(--space-4);display:block;">
                <circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>
            </svg>
            <h2 style="font-size:var(--text-2xl);font-weight:700;margin:0 0 var(--space-2) 0;color:var(--color-text-primary);">Kesalahan</h2>
            <p style="color:var(--color-text-muted);margin:0 0 var(--space-5) 0;"><?= e($msg) ?></p>
            <a href="index.php" class="btn btn-primary">Kembali ke Katalog</a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../src/Views/layouts/footer.php'; ?>