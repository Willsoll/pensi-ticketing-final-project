<?php
/**
 * login.php - Login & Registrasi (halaman tunggal, dua tab)
 * Login: identifier (email atau NIK) + password.
 * Registrasi (customer only): nama_lengkap, nik, email, no_hp, password, konfirmasi.
 */
require_once __DIR__ . '/src/Config/database.php';
require_once __DIR__ . '/src/Middleware/middleware.php';

// Jika sudah login, arahkan ke dashboard sesuai role.
if (is_logged_in()) {
    $role = current_user()['role'];
    switch ($role) {
        case 'admin_eo':     header('Location: dashboard_eo.php'); break;
        case 'finance':      header('Location: verifikasi_pembayaran.php'); break;
        case 'gate_officer': header('Location: gate_checkin.php'); break;
        default:             header('Location: dashboard_peserta.php'); break;
    }
    exit;
}

$loginError  = '';
$regErrors   = [];
$regOld      = ['nama_lengkap'=>'', 'nik'=>'', 'email'=>'', 'no_hp'=>''];

/* Ambil input POST sebagai string; tolak nilai array/objek. */
$post_str = static function (string $key, string $default = ''): string {
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? $value : $default;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('CSRF tidak valid.');
    }

    $formType = $post_str('form_type');

    /* ============ LOGIN ============ */
    if ($formType === 'login') {
        $identifier = trim($post_str('identifier'));
        $password   = $post_str('password');

        if ($identifier === '' || $password === '') {
            $loginError = 'Identifier (email atau NIK) dan password wajib diisi.';
        } else {
            // Canonicalise email case, and rate-limit both the identifier and
            // the source IP. This closes the uppercase-email rate-limit bypass.
            $rateKey  = strtolower($identifier);
            $ipKey    = 'ip:' . client_ip();
            $rate     = check_login_rate_limit($pdo, $rateKey, 5);
            $ipRate   = check_login_rate_limit($pdo, $ipKey, 30);
            if (!$rate['allowed']) {
                $loginError = $rate['msg'];
            } elseif (!$ipRate['allowed']) {
                $loginError = 'Terlalu banyak percobaan dari jaringan ini. Coba lagi nanti.';
            } else {
                $stmt = $pdo->prepare("
                    SELECT id_user, nik, nama_lengkap, email, role, password
                    FROM users
                    WHERE email = ? OR nik = ?
                    LIMIT 1
                ");
                $stmt->execute([$identifier, $identifier]);
                $user = $stmt->fetch();

                // Hash palsu untuk menyamakan waktu respons saat identifier tidak terdaftar.
                $dummyHash = '$2y$12$59aYdfYs.9iYYF5cfwqM4.rjJqGyl.k5dL5Rf5DuYMGX3pbe7zfcK';
                $hashToCheck = $user ? (string)$user['password'] : $dummyHash;
                $passwordOk  = password_verify($password, $hashToCheck);

                if ($user && $passwordOk) {
                    if (password_needs_rehash((string)$user['password'], PASSWORD_BCRYPT, ['cost' => 12])) {
                        $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id_user = ?");
                        $upd->execute([password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), (int)$user['id_user']]);
                    }

                    record_login_attempt($pdo, $rateKey, true);
                    record_login_attempt($pdo, $ipKey, true);
                    catat_audit_log($pdo, (int)$user['id_user'], 'LOGIN', 'users',
                        'Login berhasil sebagai ' . $user['role'] . '.');

                    session_regenerate_id(true);
                    unset($_SESSION['csrf_token']);
                    $_SESSION['id_user']      = (int)$user['id_user'];
                    $_SESSION['nik']          = $user['nik'];
                    $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                    $_SESSION['email']        = $user['email'];
                    $_SESSION['role']         = $user['role'];

                    set_flash('SUCCESS', 'Selamat datang, ' . e($user['nama_lengkap']) . '.');

                    switch ($user['role']) {
                        case 'admin_eo':     header('Location: dashboard_eo.php'); break;
                        case 'finance':      header('Location: verifikasi_pembayaran.php'); break;
                        case 'gate_officer': header('Location: gate_checkin.php'); break;
                        default:             header('Location: dashboard_peserta.php'); break;
                    }
                    exit;
                } else {
                    record_login_attempt($pdo, $rateKey, false);
                    record_login_attempt($pdo, $ipKey, false);
                    if ($user) {
                        catat_audit_log($pdo, (int)$user['id_user'], 'LOGIN_GAGAL', 'users',
                            'Percobaan login gagal.');
                    }
                    $loginError = 'Identifier atau password tidak cocok.';
                }
            }
        }
    }

    /* ============ REGISTRASI (customer only) ============ */
    if ($formType === 'register') {
        $regOld['nama_lengkap'] = trim($post_str('nama_lengkap'));
        $regOld['nik']          = trim($post_str('nik'));
        $regOld['email']        = strtolower(trim($post_str('email')));
        $regOld['no_hp']        = preg_replace('/\s+/', '', trim($post_str('no_hp')));
        $passReg   = $post_str('password');
        $passKonf  = $post_str('password_confirm');

        if (mb_strlen($regOld['nama_lengkap']) < 3 || mb_strlen($regOld['nama_lengkap']) > 100) {
            $regErrors[] = 'Nama lengkap wajib diisi (3 - 100 karakter).';
        }
        if (!preg_match('/^[0-9]{16}$/', $regOld['nik'])) {
            $regErrors[] = 'NIK wajib tepat 16 digit angka.';
        }
        if (!filter_var($regOld['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($regOld['email']) > 100) {
            $regErrors[] = 'Alamat email tidak valid.';
        }
        if (!preg_match('/^[0-9]{10,15}$/', $regOld['no_hp'])) {
            $regErrors[] = 'Nomor HP wajib 10 - 15 digit angka.';
        }
        if (strlen($passReg) < 8) {
            $regErrors[] = 'Password minimal 8 karakter.';
        }
        if ($passReg !== $passKonf) {
            $regErrors[] = 'Konfirmasi password tidak cocok.';
        }

        if (empty($regErrors)) {
            $cek = $pdo->prepare("SELECT COUNT(*) FROM users WHERE nik = ? OR email = ?");
            $cek->execute([$regOld['nik'], $regOld['email']]);
            if ((int)$cek->fetchColumn() > 0) {
                $regErrors[] = 'NIK atau Email tersebut sudah terdaftar.';
            } else {
                try {
                    $hashed = password_hash($passReg, PASSWORD_BCRYPT, ['cost' => 12]);
                    $ins = $pdo->prepare("
                        INSERT INTO users (nik, nama_lengkap, email, no_hp, password, role)
                        VALUES (?, ?, ?, ?, ?, 'customer')
                    ");
                    $ins->execute([$regOld['nik'], $regOld['nama_lengkap'], $regOld['email'], $regOld['no_hp'], $hashed]);
                    $idUser = (int)$pdo->lastInsertId();

                    catat_audit_log($pdo, $idUser, 'REGISTER', 'users',
                        'Registrasi akun customer baru: ' . $regOld['email']);

                    // Auto-login setelah registrasi.
                    session_regenerate_id(true);
                    unset($_SESSION['csrf_token']);
                    $_SESSION['id_user']      = $idUser;
                    $_SESSION['nik']          = $regOld['nik'];
                    $_SESSION['nama_lengkap'] = $regOld['nama_lengkap'];
                    $_SESSION['email']        = $regOld['email'];
                    $_SESSION['role']         = 'customer';

                    set_flash('SUCCESS', 'Registrasi berhasil! Selamat datang, ' . e($regOld['nama_lengkap']) . '.');
                    header('Location: dashboard_peserta.php');
                    exit;
                } catch (PDOException $ex) {
                    error_log('[REGISTRATION] ' . $ex->getMessage());
                    $regErrors[] = 'Registrasi gagal karena kesalahan sistem.';
                }
            }
        }
    }
}

$pageTitle   = 'Login & Registrasi';
$pageSubtitle = 'Masuk atau daftar akun baru';
$activeNav   = 'login.php';
require __DIR__ . '/src/Views/layouts/header.php';
?>

<div style="max-width:720px;margin:0 auto;">
    <?php if ($loginError !== ''): ?>
        <div class="alert alert-danger" role="alert" style="margin-bottom:var(--space-5);">
            <div class="alert-content">
                <div class="alert-message"><?= e($loginError) ?></div>
            </div>
        </div>
    <?php endif; ?>

    <div style="display:flex;gap:8px;margin-bottom:var(--space-5);border-bottom:1px solid var(--color-border);padding-bottom:var(--space-3);">
        <button type="button" class="btn btn-ghost" id="tabLogin" onclick="showTab('login')" style="flex:1;border-bottom:2px solid var(--color-primary);color:var(--color-primary);font-weight:700;">Masuk</button>
        <button type="button" class="btn btn-ghost" id="tabRegis" onclick="showTab('register')" style="flex:1;border-bottom:2px solid transparent;color:var(--color-text-muted);">Daftar</button>
    </div>

    <!-- LOGIN -->
    <div id="panelLogin" style="display:block;">
        <form method="POST" action="login.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
            <input type="hidden" name="form_type" value="login">

            <div class="form-group">
                <label class="form-label">Email atau NIK <span style="color:var(--color-error);">*</span></label>
                <div class="input-wrap">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/></svg>
                    <input type="text" name="identifier" class="form-control has-icon" required
                           placeholder="contoh: dewi@student.id atau 3273011005990001"
                           autocomplete="username">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Password <span style="color:var(--color-error);">*</span></label>
                <div class="input-wrap">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                    <input type="password" name="password" id="loginPass" class="form-control has-icon" required
                           placeholder="Masukkan password" autocomplete="current-password">
                    <button type="button" class="toggle-pass" onclick="togglePass('loginPass', this)">Lihat</button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;padding:var(--space-4);font-size:var(--text-base);">Masuk</button>
        </form>
    </div>

    <!-- REGISTRASI -->
    <div id="panelRegister" style="display:none;">
        <?php if (!empty($regErrors)): ?>
            <div class="alert alert-danger" role="alert" style="margin-bottom:var(--space-4);">
                <div class="alert-content">
                    <div class="alert-title">Periksa data pendaftaran:</div>
                    <ul style="margin:var(--space-2) 0 0 var(--space-5);">
                        <?php foreach ($regErrors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
            <input type="hidden" name="form_type" value="register">

            <div class="form-group">
                <label class="form-label">Nama Lengkap <span style="color:var(--color-error);">*</span></label>
                <input type="text" name="nama_lengkap" class="form-control" value="<?= e($regOld['nama_lengkap']) ?>" required minlength="3" maxlength="100" placeholder="Nama lengkap sesuai KTP">
            </div>
            <div class="form-group">
                <label class="form-label">NIK (16 digit) <span style="color:var(--color-error);">*</span></label>
                <input type="text" name="nik" class="form-control" value="<?= e($regOld['nik']) ?>" required pattern="[0-9]{16}" maxlength="16" placeholder="3273011005990001">
            </div>
            <div class="form-group">
                <label class="form-label">Email <span style="color:var(--color-error);">*</span></label>
                <input type="email" name="email" class="form-control" value="<?= e($regOld['email']) ?>" required placeholder="nama@contoh.com">
            </div>
            <div class="form-group">
                <label class="form-label">Nomor HP <span style="color:var(--color-error);">*</span></label>
                <input type="tel" name="no_hp" class="form-control" value="<?= e($regOld['no_hp']) ?>" required pattern="[0-9]{10,15}" placeholder="081234567890">
            </div>
            <div class="form-group">
                <label class="form-label">Password <span style="color:var(--color-error);">*</span></label>
                <input type="password" name="password" class="form-control" required minlength="8" placeholder="Minimal 8 karakter">
            </div>
            <div class="form-group">
                <label class="form-label">Ulangi Password <span style="color:var(--color-error);">*</span></label>
                <input type="password" name="password_confirm" class="form-control" required minlength="8" placeholder="Ulangi password">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;padding:var(--space-4);font-size:var(--text-base);">Daftar &amp; Masuk</button>
        </form>
    </div>


</div>

<script>
function togglePass(id, btn) {
    var el = document.getElementById(id);
    if (!el) return;
    var show = el.type === 'password';
    el.type = show ? 'text' : 'password';
    btn.textContent = show ? 'Sembunyi' : 'Lihat';
}
function showTab(name) {
    document.getElementById('panelLogin').style.display = name === 'login' ? 'block' : 'none';
    document.getElementById('panelRegister').style.display = name === 'register' ? 'block' : 'none';
    var tl = document.getElementById('tabLogin');
    var tr = document.getElementById('tabRegis');
    if (name === 'login') {
        tl.style.borderBottomColor = 'var(--color-primary)'; tl.style.color = 'var(--color-primary)'; tl.style.fontWeight = '700';
        tr.style.borderBottomColor = 'transparent'; tr.style.color = 'var(--color-text-muted)'; tr.style.fontWeight = '400';
    } else {
        tr.style.borderBottomColor = 'var(--color-primary)'; tr.style.color = 'var(--color-primary)'; tr.style.fontWeight = '700';
        tl.style.borderBottomColor = 'transparent'; tl.style.color = 'var(--color-text-muted)'; tl.style.fontWeight = '400';
    }
}
</script>

<?php require __DIR__ . '/src/Views/layouts/footer.php'; ?>