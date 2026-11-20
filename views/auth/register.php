<?php
// views/auth/register.php
$e = $e ?? function($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); };
$attr = $attr ?? function($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); };
?>
<div class="card" style="max-width: 480px; margin: var(--space-12) auto;">
    <div class="card-body" style="padding: var(--space-10);">
        <div style="text-align: center; margin-bottom: var(--space-8);">
            <div style="display: inline-flex; align-items: center; justify-content: center; width: 64px; height: 64px; border-radius: var(--radius-xl); background: linear-gradient(135deg, var(--color-indigo-dark), var(--color-purple-electric)); margin: 0 auto var(--space-4);" aria-hidden="true">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-white);">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                </svg>
            </div>
            <h1 style="font-size: 1.875rem; font-weight: 700; color: var(--color-indigo-darker); margin-bottom: var(--space-2);">Daftar Akun Baru</h1>
            <p style="color: var(--color-slate-500);">Buat akun untuk memesan tiket</p>
        </div>

        <?php if ($flash['error'] ?? false): ?>
            <div class="alert alert-error"><?= $e($flash['error']) ?></div>
        <?php endif; ?>

        <form method="POST" action="/register">
            <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">

            <div class="form-group">
                <label for="nama_lengkap">Nama Lengkap</label>
                <input type="text" id="nama_lengkap" name="nama_lengkap" class="input" required minlength="3" maxlength="150" autocomplete="name" value="<?= $e($_POST['nama_lengkap'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="input" required autocomplete="email" value="<?= $e($_POST['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="no_hp">Nomor HP</label>
                <input type="tel" id="no_hp" name="no_hp" class="input" required autocomplete="tel" placeholder="Contoh: 081234567890" value="<?= $e($_POST['no_hp'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="nik">NIK KTP (16 Digit Angka)</label>
                <input type="text" id="nik" name="nik" class="input" required pattern="[0-9]{16}" maxlength="16" autocomplete="off" value="<?= $e($_POST['nik'] ?? '') ?>">
                <small style="color: var(--color-slate-500); font-size: 0.75rem;">Contoh: 3273011005990001</small>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="input" required minlength="8" autocomplete="new-password">
                <small style="color: var(--color-slate-500); font-size: 0.75rem;">Minimal 8 karakter, huruf besar/kecil, angka, simbol</small>
            </div>

            <div class="form-group">
                <label for="password_confirmation">Konfirmasi Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" class="input" required minlength="8" autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: var(--space-2);">Daftar</button>
        </form>

        <p style="text-align: center; margin-top: var(--space-6); color: var(--color-slate-500);">
            Sudah punya akun? <a href="/login">Masuk di sini</a>
        </p>
    </div>
</div>