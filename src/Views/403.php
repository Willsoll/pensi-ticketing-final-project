<?php
$pageTitle = 'Akses Ditolak';
$pageSubtitle = 'Anda tidak memiliki hak akses ke halaman ini';
require __DIR__ . '/layouts/header.php';
?>
<div class="card card-elevated" style="max-width:560px;margin:40px auto;text-align:center;">
    <div style="font-size:56px;font-weight:800;color:var(--color-cta);line-height:1;">403</div>
    <h1 style="font-size:22px;margin:10px 0 6px;">Akses Ditolak</h1>
    <p style="color:var(--color-text-secondary);margin-bottom:20px;">
        Halaman ini hanya dapat diakses oleh peran tertentu. Silakan kembali ke katalog atau login dengan akun yang sesuai.
    </p>
    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
        <a href="index.php" class="btn btn-primary">Kembali ke Katalog</a>
        <a href="login.php" class="btn btn-outline">Login</a>
    </div>
</div>
<?php require __DIR__ . '/layouts/footer.php'; ?>
