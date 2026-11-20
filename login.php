<?php
/**
 * login.php - Login & Registrasi Multi-Role dengan Validasi NIS/NIK
 * Role: pembeli, panitia_keuangan, petugas_gate, admin_osis
 * Registrasi hanya untuk role 'pembeli' (customer)
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/middleware.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $login_error = 'Token keamanan (CSRF) tidak valid.';
    } else {
        $form_type = $_POST['form_type'] ?? '';

        // ===== LOGIN =====
        if ($form_type === 'login') {
            $login_identifier = trim($_POST['identifier'] ?? '');
            $password_input = $_POST['password'] ?? '';

            if ($login_identifier === '' || $password_input === '') {
                $login_error = 'Identifier (Username/Email/NIS/NIK) dan password wajib diisi.';
            } else {
                $stmt = $pdo->prepare("
                    SELECT id_user, nama, tipe_pengguna, nis_atau_nik, kelas_atau_instansi, username, email, no_hp, password, role
                    FROM users
                    WHERE username = ? OR email = ? OR nis_atau_nik = ?
                    LIMIT 1
                ");
                $stmt->execute([$login_identifier, $login_identifier, $login_identifier]);
                $user = $stmt->fetch();

                if ($user && password_verify($password_input, $user['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['user'] = [
                        'id_user'            => (int)$user['id_user'],
                        'nama'               => $user['nama'],
                        'tipe_pengguna'      => $user['tipe_pengguna'],
                        'nis_atau_nik'       => $user['nis_atau_nik'],
                        'kelas_atau_instansi'=> $user['kelas_atau_instansi'],
                        'username'           => $user['username'],
                        'email'              => $user['email'],
                        'no_hp'              => $user['no_hp'],
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
                    $login_error = 'Identifier atau password tidak cocok.';
                }
            }
        }

        // ===== REGISTRASI (Hanya role pembeli) =====
        if ($form_type === 'register') {
            $reg_old['tipe_pengguna']      = $_POST['tipe_pengguna'] ?? 'Internal';
            $reg_old['nis_atau_nik']       = trim($_POST['nis_atau_nik'] ?? '');
            $reg_old['nama']               = trim($_POST['nama'] ?? '');
            $reg_old['kelas_atau_instansi'] = trim($_POST['kelas_atau_instansi'] ?? '');
            $reg_old['username']           = strtolower(trim($_POST['username'] ?? ''));
            $reg_old['email']              = strtolower(trim($_POST['email'] ?? ''));
            $reg_old['no_hp']              = preg_replace('/[\s\-]/', '', trim($_POST['no_hp'] ?? ''));
            $pass_reg                      = $_POST['password'] ?? '';
            $pass_konf                     = $_POST['password_konfirmasi'] ?? '';

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
            if (!filter_var($reg_old['email'], FILTER_VALIDATE_EMAIL)) {
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
                    $hashed = password_hash($pass_reg, PASSWORD_DEFAULT);
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

                    set_flash('SUKSES', 'Registrasi akun Pembeli berhasil! Silakan login menggunakan username/email/NIS/NIK dan password Anda.');
                    header('Location: login.php');
                    exit;
                }
            }
        }
    }
}

$page_title = 'Login & Registrasi Multi-Role - Pensi School';
require_once __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb">
    <a href="index.php">Katalog Acara</a> > Login & Registrasi
</div>

<h2 style="color: #4F46E5; margin-bottom: 8px;">Login & Registrasi Multi-Role</h2>
<p style="margin-bottom: 24px; color: #6B7280;">
    Autentikasi terpisah untuk <strong>Pembeli (Peserta)</strong>, <strong>Admin OSIS</strong>, 
    <strong>Panitia Keuangan</strong>, dan <strong>Petugas Gate</strong>.
    Registrasi akun baru hanya untuk role <strong>Pembeli</strong>.
</p>

<table style="border: none;">
    <tr valign="top">
        <!-- LOGIN FORM -->
        <td style="width: 48%; border: none; padding-right: 16px;">
            <?php if ($login_error !== ''): ?>
                <div class="flash ERROR"><?= e($login_error) ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php" onsubmit="return validasiLogin();" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                <input type="hidden" name="form_type" value="login">

                <div class="card">
                    <div class="card-header">
                        <h3 style="margin: 0;">1. Form Login Multi-Role</h3>
                    </div>
                    
                    <table style="border: none;">
                        <tr>
                            <td style="width: 160px; border: none;"><label for="login_identifier"><strong>Username / Email / NIS / NIK *</strong></label></td>
                            <td style="border: none;"><input type="text" id="login_identifier" name="identifier" value="<?= e($login_identifier) ?>" required size="30" autocomplete="username"></td>
                        </tr>
                        <tr>
                            <td style="border: none;"><label for="login_pass"><strong>Password *</strong></label></td>
                            <td style="border: none;"><input type="password" id="login_pass" name="password" required size="30" autocomplete="current-password"></td>
                        </tr>
                        <tr>
                            <td style="border: none;"></td>
                            <td style="border: none;"><button type="submit"><strong>MASUK (LOGIN)</strong></button></td>
                        </tr>
                    </table>
                </div>
            </form>

            <br>

            <!-- AKUN UJI COBA -->
            <div class="card">
                <div class="card-header">
                    <h3 style="margin: 0;">🔐 Akun Uji Coba Multi-Role (Password: <code>password123</code>)</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>Identifier</th>
                            <th>Tipe</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="badge badge-available">Admin OSIS</span></td>
                            <td><code>admin_osis</code></td>
                            <td>Internal (NIS)</td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-available">Panitia Keuangan</span></td>
                            <td><code>panitia_keu</code></td>
                            <td>Internal (NIS)</td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-available">Petugas Gate</span></td>
                            <td><code>petugas_gate</code></td>
                            <td>Internal (NIS)</td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-belum-masuk">Pembeli - Siswa Internal</span></td>
                            <td><code>dewi_siswa</code> / <code>2024001</code></td>
                            <td>Internal (NIS)</td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-belum-masuk">Pembeli - Alumni Eksternal</span></td>
                            <td><code>rizki_alumni</code> / <code>3273011005990001</code></td>
                            <td>Eksternal (NIK)</td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-belum-masuk">Pembeli - Tamu Umum</span></td>
                            <td><code>maya_tamu</code> / <code>3273021808000002</code></td>
                            <td>Eksternal (NIK)</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </td>

        <!-- REGISTRASI FORM -->
        <td style="width: 52%; border: none; padding-left: 16px;">
            <?php if (!empty($reg_errors)): ?>
                <div class="flash ERROR">
                    <strong>Periksa Data Registrasi:</strong>
                    <ul style="margin: 8px 0 0 20px;">
                        <?php foreach ($reg_errors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" onsubmit="return validasiRegistrasi();" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                <input type="hidden" name="form_type" value="register">

                <div class="card">
                    <div class="card-header">
                        <h3 style="margin: 0;">2. Registrasi Akun Pembeli Baru</h3>
                    </div>

                    <table style="border: none;">
                        <tr>
                            <td style="width: 180px; border: none;"><label for="reg_tipe"><strong>Tipe Pengguna *</strong></label></td>
                            <td style="border: none;">
                                <select id="reg_tipe" name="tipe_pengguna" required onchange="updateIdentifierLabel()">
                                    <option value="Internal" <?= $reg_old['tipe_pengguna'] === 'Internal' ? 'selected' : '' ?>>Siswa Internal (NIS)</option>
                                    <option value="Eksternal" <?= $reg_old['tipe_pengguna'] === 'Eksternal' ? 'selected' : '' ?>>Tamu Eksternal / Alumni (NIK KTP)</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td style="border: none;"><label for="reg_nis_nik"><strong id="nis_nik_label">NIS (5-20 Digit Angka) *</strong></label></td>
                            <td style="border: none;">
                                <input type="text" id="reg_nis_nik" name="nis_atau_nik" value="<?= e($reg_old['nis_atau_nik']) ?>" 
                                       required pattern="[0-9]{5,20}" maxlength="20" size="30" autocomplete="off">
                                <br><small id="nis_nik_hint">Contoh NIS: 2024001</small>
                            </td>
                        </tr>
                        <tr>
                            <td style="border: none;"><label for="reg_nama"><strong>Nama Lengkap *</strong></label></td>
                            <td style="border: none;"><input type="text" id="reg_nama" name="nama" value="<?= e($reg_old['nama']) ?>" required minlength="3" maxlength="100" size="35"></td>
                        </tr>
                        <tr>
                            <td style="border: none;"><label for="reg_kelas"><strong>Kelas / Instansi *</strong></label></td>
                            <td style="border: none;">
                                <input type="text" id="reg_kelas" name="kelas_atau_instansi" value="<?= e($reg_old['kelas_atau_instansi']) ?>" required maxlength="100" size="35" placeholder="Contoh: Kelas 10 IPA 1 / Universitas Indonesia / PT. Maju Jaya">
                            </td>
                        </tr>
                        <tr>
                            <td style="border: none;"><label for="reg_username"><strong>Username *</strong></label></td>
                            <td style="border: none;"><input type="text" id="reg_username" name="username" value="<?= e($reg_old['username']) ?>" required pattern="[a-zA-Z0-9_]{4,30}" maxlength="30" size="30" autocomplete="username"></td>
                        </tr>
                        <tr>
                            <td style="border: none;"><label for="reg_email"><strong>Email *</strong></label></td>
                            <td style="border: none;"><input type="email" id="reg_email" name="email" value="<?= e($reg_old['email']) ?>" required size="35" autocomplete="email"></td>
                        </tr>
                        <tr>
                            <td style="border: none;"><label for="reg_hp"><strong>Nomor HP *</strong></label></td>
                            <td style="border: none;"><input type="tel" id="reg_hp" name="no_hp" value="<?= e($reg_old['no_hp']) ?>" required pattern="(08|\+628)[0-9]{7,13}" size="25" autocomplete="tel" placeholder="08xxxxxxxxxx atau +628xxxxxxxxxx"></td>
                        </tr>
                        <tr>
                            <td style="border: none;"><label for="reg_pass"><strong>Password *</strong></label></td>
                            <td style="border: none;"><input type="password" id="reg_pass" name="password" required minlength="6" size="30" autocomplete="new-password"></td>
                        </tr>
                        <tr>
                            <td style="border: none;"><label for="reg_konf"><strong>Ulangi Password *</strong></label></td>
                            <td style="border: none;"><input type="password" id="reg_konf" name="password_konfirmasi" required minlength="6" size="30" autocomplete="new-password"></td>
                        </tr>
                        <tr>
                            <td style="border: none;"></td>
                            <td style="border: none;"><button type="submit"><strong>DAFTAR SEBAGAI PEMBELI</strong></button></td>
                        </tr>
                    </table>
                </div>
            </form>
        </td>
    </tr>
</table>

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

function validasiLogin() {
    var id = document.getElementById('login_identifier').value.trim();
    var p = document.getElementById('login_pass').value;
    if (id === '' || p === '') {
        alert('Identifier dan password wajib diisi.');
        return false;
    }
    return true;
}

function validasiRegistrasi() {
    var tipe = document.getElementById('reg_tipe').value;
    var nisnik = document.getElementById('reg_nis_nik').value.trim();
    var nama = document.getElementById('reg_nama').value.trim();
    var kelas = document.getElementById('reg_kelas').value.trim();
    var username = document.getElementById('reg_username').value.trim();
    var email = document.getElementById('reg_email').value.trim();
    var hp = document.getElementById('reg_hp').value.trim().replace(/[\s\-]/g, '');
    var pass = document.getElementById('reg_pass').value;
    var konf = document.getElementById('reg_konf').value;

    if (tipe === 'Internal') {
        if (!/^[0-9]{5,20}$/.test(nisnik)) {
            alert('NIS wajib 5-20 digit angka.');
            return false;
        }
    } else {
        if (!/^[0-9]{16}$/.test(nisnik)) {
            alert('NIK KTP wajib tepat 16 digit angka.');
            return false;
        }
    }
    if (nama.length < 3) { alert('Nama lengkap minimal 3 karakter.'); return false; }
    if (kelas.length < 2) { alert('Kelas/Instansi wajib diisi.'); return false; }
    if (!/^[a-zA-Z0-9_]{4,30}$/.test(username)) { alert('Username 4-30 karakter (huruf, angka, underscore).'); return false; }
    if (email.indexOf('@') === -1) { alert('Format email tidak valid.'); return false; }
    if (!/^(08|\+628)[0-9]{7,13}$/.test(hp)) { alert('Nomor HP harus diawali 08 atau +628.'); return false; }
    if (pass.length < 6) { alert('Password minimal 6 karakter.'); return false; }
    if (pass !== konf) { alert('Konfirmasi password tidak cocok.'); return false; }
    return true;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>