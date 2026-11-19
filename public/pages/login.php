<?php
/**
 * login.php - Login & Registrasi Multi-Role dengan Validasi NIS/NIK
 * Role: pembeli, panitia_keuangan, petugas_gate, admin_osis
 * Registrasi hanya untuk role 'pembeli' (customer)
 * Redesigned with Pensi Design System v1.0
 */
require_once __DIR__ . '/../src/Config/database.php';
require_once __DIR__ . '/../src/Middleware/middleware.php';

// Redirect jika sudah login
if (is_logged_in()) {
    $role = current_user()['role'];
    if ($role === 'admin_osis') header('Location: dashboard_panitia.php');
    elseif ($role === 'panitia_keuangan') header('Location: verifikasi_pembayaran.php');
    elseif ($role === 'petugas_gate') header('Location: gate_checkin.php');
    else header('Location: dashboard_peserta.php');
    exit;
}

$login_error = '';
$login_identifier = ''; // email/username/nis_nik

$reg_errors = [];
$reg_old = [
    'tipe_pengguna'  => 'Internal',
    'nis_atau_nik'   => '',
    'nama'           => '',
    'kelas_atau_instansi' => '',
    'username'       => '',
    'email'          => '',
    'no_hp'          => '',
];

/**
 * Ambil input POST sebagai string; tolak nilai array/objek agar tidak
 * menimbulkan TypeError (HTTP 500) saat penyerang mengirim parameter ganda.
 */
$post_str = static function (string $key, string $default = ''): string {
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? $value : $default;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $login_error = 'Token keamanan (CSRF) tidak valid.';
    } else {
        $form_type = $post_str('form_type');

        // ===== LOGIN =====
        if ($form_type === 'login') {
            $login_identifier = trim($post_str('identifier'));
            $password_input   = $post_str('password');
            $identifier_key   = mb_substr(strtolower($login_identifier), 0, 100);
            $ip_key           = 'ip:' . client_ip();

            if ($login_identifier === '' || $password_input === '') {
                $login_error = 'Identifier (Username/Email/NIS/NIK) dan password wajib diisi.';
            } elseif (mb_strlen($login_identifier) > 100 || strlen($password_input) > 200) {
                $login_error = 'Identifier atau password tidak cocok.';
            } else {
                // Rate limit: 5 kegagalan per identifier & 20 kegagalan per IP dalam 15 menit.
                $rate_identifier = check_login_rate_limit($pdo, $identifier_key, 5);
                $rate_ip         = check_login_rate_limit($pdo, $ip_key, 20);

                if (!$rate_identifier['allowed'] || !$rate_ip['allowed']) {
                    $login_error = 'Terlalu banyak percobaan login gagal. Silakan coba lagi dalam 15 menit.';
                } else {
                    $stmt = $pdo->prepare("
                        SELECT id_user, nama, tipe_pengguna, nis_atau_nik, kelas_atau_instansi,
                               username, email, password, role
                        FROM users
                        WHERE username = ? OR email = ? OR nis_atau_nik = ?
                        LIMIT 1
                    ");
                    $stmt->execute([$login_identifier, strtolower($login_identifier), $login_identifier]);
                    $user = $stmt->fetch();

                    // Dummy hash cost 12: menyamakan waktu respons untuk identifier yang tidak ada.
                    $dummy_hash    = '$2y$12$59aYdfYs.9iYYF5cfwqM4.rjJqGyl.k5dL5Rf5DuYMGX3pbe7zfcK';
                    $hash_to_check = $user ? (string)$user['password'] : $dummy_hash;
                    $password_ok   = password_verify($password_input, $hash_to_check);

                    if ($user && $password_ok) {
                        // Rehash otomatis jika hash lama masih cost < 12.
                        if (password_needs_rehash((string)$user['password'], PASSWORD_BCRYPT, ['cost' => 12])) {
                            $new_hash = password_hash($password_input, PASSWORD_BCRYPT, ['cost' => 12]);
                            $updHash  = $pdo->prepare("UPDATE users SET password = ? WHERE id_user = ?");
                            $updHash->execute([$new_hash, (int)$user['id_user']]);
                        }

                        record_login_attempt($pdo, $identifier_key, true, true);
                        record_login_attempt($pdo, $ip_key, true);
                        catat_audit_log(
                            $pdo,
                            (int)$user['id_user'],
                            'LOGIN_BERHASIL',
                            'users',
                            'Login berhasil dari IP ' . client_ip() . ' sebagai ' . $user['role'] . '.'
                        );

                        session_regenerate_id(true);
                        unset($_SESSION['csrf_token']); // rotasi token setelah login
                        $_SESSION['_login_time']    = time();
                        $_SESSION['_last_activity'] = time();
                        $_SESSION['user'] = [
                            'id_user'            => (int)$user['id_user'],
                            'nama'               => $user['nama'],
                            'tipe_pengguna'      => $user['tipe_pengguna'],
                            'nis_atau_nik'       => $user['nis_atau_nik'],
                            'kelas_atau_instansi'=> $user['kelas_atau_instansi'],
                            'username'           => $user['username'],
                            'email'              => $user['email'],
                            'role'               => $user['role'],
                        ];

                        set_flash('SUKSES', 'Login berhasil! Selamat datang, ' . $user['nama'] . ' (' . label_role($user['role']) . ').');

                        // Redirect sesuai role
                        if ($user['role'] === 'admin_osis') header('Location: dashboard_panitia.php');
                        elseif ($user['role'] === 'panitia_keuangan') header('Location: verifikasi_pembayaran.php');
                        elseif ($user['role'] === 'petugas_gate') header('Location: gate_checkin.php');
                        else header('Location: dashboard_peserta.php');
                        exit;
                    } else {
                        record_login_attempt($pdo, $identifier_key, false);
                        record_login_attempt($pdo, $ip_key, false);
                        if ($user) {
                            catat_audit_log(
                                $pdo,
                                (int)$user['id_user'],
                                'LOGIN_GAGAL',
                                'users',
                                'Percobaan login gagal dari IP ' . client_ip() . '.'
                            );
                        }
                        $login_error = 'Identifier atau password tidak cocok.';
                    }
                }
            }
        }

        // ===== REGISTRASI (Hanya role pembeli) =====
        if ($form_type === 'register') {
            $reg_old['tipe_pengguna']      = $post_str('tipe_pengguna', 'Internal');
            $reg_old['nis_atau_nik']       = trim($post_str('nis_atau_nik'));
            $reg_old['nama']               = trim($post_str('nama'));
            $reg_old['kelas_atau_instansi'] = trim($post_str('kelas_atau_instansi'));
            $reg_old['username']           = strtolower(trim($post_str('username')));
            $reg_old['email']              = strtolower(trim($post_str('email')));
            $reg_old['no_hp']              = preg_replace('/[\s\-]/', '', trim($post_str('no_hp')));
            $pass_reg                      = $post_str('password');
            $pass_konf                     = $post_str('password_konfirmasi');

            // Validasi tipe pengguna & identifier
            if (!in_array($reg_old['tipe_pengguna'], ['Internal', 'Eksternal'], true)) {
                $reg_errors[] = 'Tipe pengguna tidak valid.';
            }
            if ($reg_old['tipe_pengguna'] === 'Internal') {
                if (!preg_match('/^[0-9]{5,20}$/', $reg_old['nis_atau_nik'])) {
                    $reg_errors[] = 'NIS wajib 5-20 digit angka untuk Siswa Internal.';
                }
            } else {
                if (!preg_match('/^[0-9]{16}$/', $reg_old['nis_atau_nik'])) {
                    $reg_errors[] = 'NIK KTP wajib tepat 16 digit angka untuk Tamu Eksternal.';
                }
            }
            if (mb_strlen($reg_old['nama']) < 3 || mb_strlen($reg_old['nama']) > 100) {
                $reg_errors[] = 'Nama lengkap wajib diisi (3 - 100 karakter).';
            }
            if (mb_strlen($reg_old['kelas_atau_instansi']) < 2 || mb_strlen($reg_old['kelas_atau_instansi']) > 100) {
                $reg_errors[] = 'Kelas/Instansi wajib diisi.';
            }
            if (!preg_match('/^[a-zA-Z0-9_]{4,30}$/', $reg_old['username'])) {
                $reg_errors[] = 'Username 4-30 karakter (huruf, angka, underscore saja).';
            }
            if (!filter_var($reg_old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($reg_old['email']) > 100) {
                $reg_errors[] = 'Alamat email tidak valid.';
            }
            if (!validate_no_hp($reg_old['no_hp'])) {
                $reg_errors[] = 'Nomor HP tidak valid (diawali 08 atau +628).';
            }
            if (strlen($pass_reg) < 6) {
                $reg_errors[] = 'Password minimal 6 karakter.';
            }
            if ($pass_reg !== $pass_konf) {
                $reg_errors[] = 'Konfirmasi password tidak cocok.';
            }

            if (empty($reg_errors)) {
                // Cek duplikat
                $cek = $pdo->prepare("SELECT COUNT(*) FROM users WHERE nis_atau_nik = ? OR username = ? OR email = ?");
                $cek->execute([$reg_old['nis_atau_nik'], $reg_old['username'], $reg_old['email']]);
                if ((int)$cek->fetchColumn() > 0) {
                    $reg_errors[] = 'NIS/NIK, Username, atau Email tersebut sudah terdaftar.';
                } else {
                    $hashed = password_hash($pass_reg, PASSWORD_BCRYPT, ['cost' => 12]);
                    try {
                        $ins = $pdo->prepare("
                            INSERT INTO users (nama, tipe_pengguna, nis_atau_nik, kelas_atau_instansi, username, email, no_hp, password, role)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pembeli')
                        ");
                        $ins->execute([
                            $reg_old['nama'],
                            $reg_old['tipe_pengguna'],
                            $reg_old['nis_atau_nik'],
                            $reg_old['kelas_atau_instansi'],
                            $reg_old['username'],
                            $reg_old['email'],
                            $reg_old['no_hp'],
                            $hashed,
                        ]);

                        catat_audit_log(
                            $pdo,
                            (int)$pdo->lastInsertId(),
                            'REGISTRASI_AKUN',
                            'users',
                            'Registrasi akun Pembeli baru: ' . $reg_old['username'] . '.'
                        );

                        set_flash('SUKSES', 'Registrasi akun Pembeli berhasil! Silakan login menggunakan username/email/NIS/NIK dan password Anda.');
                        header('Location: login.php');
                        exit;
                    } catch (PDOException $ex) {
                        if ($ex->getCode() === '23000') {
                            $reg_errors[] = 'NIS/NIK, Username, atau Email tersebut sudah terdaftar.';
                        } else {
                            error_log('[REGISTRATION] ' . $ex->getMessage());
                            $reg_errors[] = 'Registrasi gagal karena kesalahan sistem. Silakan coba lagi.';
                        }
                    }
                }
            }
        }
    }
}

$page_title = 'Login & Registrasi Multi-Role - Pensi School';
require_once __DIR__ . '/../src/Views/layouts/header.php';
?>

<div class="breadcrumb">
    <a href="index.php">Katalog Acara</a> > Login & Registrasi
</div>

<div class="grid grid-cols-1 grid-cols-2" style="gap: var(--space-8); align-items: start;">

    <!-- LOGIN FORM -->
    <div class="card card-elevated">
        <div class="card-header">
            <h3 class="card-title">Masuk ke Akun Anda</h3>
        </div>
        <div class="card-body">
            <?php if ($login_error !== ''): ?>
                <div class="alert alert-error" role="alert">
                    <div class="alert-icon">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                    </div>
                    <div class="alert-content">
                        <div class="alert-message"><?= e($login_error) ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                <input type="hidden" name="form_type" value="login">

                <div class="form-group">
                    <label for="login_identifier" class="form-label">Username / Email / NIS / NIK *</label>
                    <input type="text" id="login_identifier" name="identifier" class="form-input" value="<?= e($login_identifier) ?>" required autocomplete="username" placeholder="Masukkan identifier Anda">
                </div>

                <div class="form-group">
                    <label for="login_pass" class="form-label">Password *</label>
                    <input type="password" id="login_pass" name="password" class="form-input" required autocomplete="current-password" placeholder="Masukkan password">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: var(--space-4); font-size: var(--text-base);">
                    <strong>MASUK</strong>
                </button>
            </form>
        </div>
    </div>

    <!-- AKUN UJI COBA -->
    <div class="card" style="margin-top: var(--space-6); border-color: var(--color-primary); background: var(--color-primary-bg);">
        <div class="card-header">
            <h4 class="card-title" style="color: var(--color-primary); margin: 0;">Akun Uji Coba Multi-Role</h4>
            <small style="color: var(--color-text-muted);">Password: <code>password123</code></small>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>Identifier</th>
                            <th>Tipe</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="badge badge-success">Admin OSIS</span></td>
                            <td><code>admin_osis</code></td>
                            <td>Internal (NIS)</td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-info">Panitia Keuangan</span></td>
                            <td><code>panitia_keu</code></td>
                            <td>Internal (NIS)</td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-primary">Petugas Gate</span></td>
                            <td><code>petugas_gate</code></td>
                            <td>Internal (NIS)</td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-warning">Pembeli - Siswa Internal</span></td>
                            <td><code>dewi_siswa</code> / <code>2024001</code></td>
                            <td>Internal (NIS)</td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-warning">Pembeli - Alumni Eksternal</span></td>
                            <td><code>rizki_alumni</code> / <code>3273011005990001</code></td>
                            <td>Eksternal (NIK)</td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-warning">Pembeli - Tamu Umum</span></td>
                            <td><code>maya_tamu</code> / <code>3273021808000002</code></td>
                            <td>Eksternal (NIK)</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- REGISTRASI FORM -->
    <div class="card card-elevated">
        <div class="card-header">
            <h3 class="card-title">Daftar sebagai Pembeli Baru</h3>
        </div>
        <div class="card-body">
            <?php if (!empty($reg_errors)): ?>
                <div class="alert alert-error" role="alert">
                    <div class="alert-icon">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                    </div>
                    <div class="alert-content">
                        <div class="alert-title">Periksa Data Registrasi:</div>
                        <ul style="margin: var(--space-2) 0 0 var(--space-5);">
                            <?php foreach ($reg_errors as $err): ?>
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
                    <label for="reg_tipe" class="form-label">Tipe Pengguna *</label>
                    <select id="reg_tipe" name="tipe_pengguna" class="form-select" required onchange="updateIdentifierLabel()">
                        <option value="Internal" <?= $reg_old['tipe_pengguna'] === 'Internal' ? 'selected' : '' ?>>Siswa Internal (NIS)</option>
                        <option value="Eksternal" <?= $reg_old['tipe_pengguna'] === 'Eksternal' ? 'selected' : '' ?>>Tamu Eksternal / Alumni (NIK KTP)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="reg_nis_nik" class="form-label"><strong id="nis_nik_label">NIS (5-20 Digit Angka) *</strong></label>
                    <input type="text" id="reg_nis_nik" name="nis_atau_nik" class="form-input" value="<?= e($reg_old['nis_atau_nik']) ?>" required pattern="[0-9]{5,20}" maxlength="20" autocomplete="off" placeholder="Contoh: 2024001">
                    <div class="form-hint" id="nis_nik_hint">Contoh NIS: 2024001</div>
                </div>

                <div class="form-group">
                    <label for="reg_nama" class="form-label">Nama Lengkap *</label>
                    <input type="text" id="reg_nama" name="nama" class="form-input" value="<?= e($reg_old['nama']) ?>" required minlength="3" maxlength="100" placeholder="Nama lengkap sesuai KTP/Kartu Pelajar">
                </div>

                <div class="form-group">
                    <label for="reg_kelas" class="form-label">Kelas / Instansi *</label>
                    <input type="text" id="reg_kelas" name="kelas_atau_instansi" class="form-input" value="<?= e($reg_old['kelas_atau_instansi']) ?>" required maxlength="100" placeholder="Contoh: Kelas 10 IPA 1 / Universitas Indonesia / PT. Maju Jaya">
                </div>

                <div class="form-group">
                    <label for="reg_username" class="form-label">Username *</label>
                    <input type="text" id="reg_username" name="username" class="form-input" value="<?= e($reg_old['username']) ?>" required pattern="[a-zA-Z0-9_]{4,30}" maxlength="30" autocomplete="username" placeholder="4-30 karakter (huruf, angka, underscore)">
                </div>

                <div class="form-group">
                    <label for="reg_email" class="form-label">Email *</label>
                    <input type="email" id="reg_email" name="email" class="form-input" value="<?= e($reg_old['email']) ?>" required autocomplete="email" placeholder="email@example.com">
                </div>

                <div class="form-group">
                    <label for="reg_hp" class="form-label">Nomor HP *</label>
                    <input type="tel" id="reg_hp" name="no_hp" class="form-input" value="<?= e($reg_old['no_hp']) ?>" required pattern="(08|\+628)[0-9]{7,13}" autocomplete="tel" placeholder="08xxxxxxxxxx atau +628xxxxxxxxxx">
                </div>

                <div class="form-group">
                    <label for="reg_pass" class="form-label">Password *</label>
                    <input type="password" id="reg_pass" name="password" class="form-input" required minlength="6" autocomplete="new-password" placeholder="Minimal 6 karakter">
                </div>

                <div class="form-group">
                    <label for="reg_konf" class="form-label">Ulangi Password *</label>
                    <input type="password" id="reg_konf" name="password_konfirmasi" class="form-input" required minlength="6" autocomplete="new-password" placeholder="Ulangi password">
                </div>

                <button type="submit" class="btn btn-cta" style="width: 100%; padding: var(--space-4); font-size: var(--text-base);">
                    <strong>DAFTAR SEBAGAI PEMBELI</strong>
                </button>
            </form>
        </div>
    </div>

</div>

<script>
function updateIdentifierLabel() {
    var tipe = document.getElementById('reg_tipe').value;
    var label = document.getElementById('nis_nik_label');
    var hint = document.getElementById('nis_nik_hint');
    var input = document.getElementById('reg_nis_nik');

    if (tipe === 'Internal') {
        label.textContent = 'NIS (5-20 Digit Angka) *';
        hint.textContent = 'Contoh NIS: 2024001';
        input.pattern = '[0-9]{5,20}';
        input.maxLength = 20;
    } else {
        label.textContent = 'NIK KTP (16 Digit Angka) *';
        hint.textContent = 'Contoh NIK: 3273011005990001';
        input.pattern = '[0-9]{16}';
        input.maxLength = 16;
    }
    input.value = '';
}
</script>

<?php require_once __DIR__ . '/../src/Views/layouts/footer.php'; ?>
