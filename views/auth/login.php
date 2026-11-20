<?php
// views/auth/login.php
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
            <h1 style="font-size: 1.875rem; font-weight: 700; color: var(--color-indigo-darker); margin-bottom: var(--space-2);">Masuk</h1>
            <p style="color: var(--color-slate-500);">Masuk ke akun Anda untuk memesan tiket</p>
        </div>

        <?php if ($flash['error'] ?? false): ?>
            <div class="alert alert-error"><?= $e($flash['error']) ?></div>
        <?php endif; ?>

        <form method="POST" action="/login">
            <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
            <input type="hidden" name="redirect" value="<?= $e($redirect) ?>">

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="input" required autocomplete="email" value="<?= $e($_POST['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="input" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: var(--space-2);">Masuk</button>
        </form>

        <p style="text-align: center; margin-top: var(--space-6); color: var(--color-slate-500);">
            Belum punya akun? <a href="/register">Daftar di sini</a>
        </p>
    </div>
</div>

<div style="max-width: 480px; margin: var(--space-6) auto; text-align: center; color: var(--color-slate-500); font-size: 0.875rem;">
    <p><strong>Akun Demo (password: password123):</strong></p>
    <p>Admin: admin@pensi.school<br>Finance: finance@pensi.school<br>Gate: gate@pensi.school<br>Customer: dewi@student.school / rizki@alumni.id / maya@tamu.com</p>
</div>