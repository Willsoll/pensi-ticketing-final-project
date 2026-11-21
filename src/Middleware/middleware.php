<?php
/**
 * Middleware & helper bersama — db_tiket_event_pro (PAKET 7 v4)
 * Semua halaman wajib meng-include file ini setelah config/database.php.
 */
declare(strict_types=1);

/**
 * Base path aplikasi agar bisa dijalankan di root domain maupun subfolder
 * (XAMPP htdocs/pensi, Laragon www/pensi, vhost, atau DocumentRoot langsung).
 */
function base_path(): string
{
    static $base = null;
    if ($base !== null) return $base;
    $script = str_replace(chr(92), '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $dir = rtrim(str_replace(chr(92), '/', dirname($script)), '/');
    if ($dir === '.' || $dir === '/') $dir = '';
    return $base = $dir;
}

function base_url(string $path = ''): string
{
    $path = ltrim($path, '/');
    $base = base_path();
    return ($base === '' ? '' : $base) . '/' . $path;
}

/* ---------------------------------------------------------------------------
 * Session & header keamanan dasar
 * ------------------------------------------------------------------------- */
if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('PENSI_EVENTPRO');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_path() ?: '/',
        'domain'   => '',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '1800');
    session_start();
}

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

// PHP2Shell (CVE-2024-4577) — pantau versi PHP yang belum dipatch.
if (version_compare(PHP_VERSION, '8.2.35', '<')) {
    error_log('[SECURITY] PHP ' . PHP_VERSION . ' < 8.2.35 — segera upgrade (CVE-2024-4577).');
}

/* ---------------------------------------------------------------------------
 * Util dasar
 * ------------------------------------------------------------------------- */
function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function format_rupiah(int $nominal): string
{
    return 'Rp ' . number_format($nominal, 0, ',', '.');
}

function format_tanggal(?string $dt, bool $withTime = true): string
{
    if (!$dt) return '-';
    $ts = strtotime($dt);
    if ($ts === false) return '-';
    $bulan = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $out = date('j', $ts) . ' ' . $bulan[(int)date('n', $ts)] . ' ' . date('Y', $ts);
    return $withTime ? $out . ', ' . date('H:i', $ts) . ' WIB' : $out;
}

function label_role(string $role): string
{
    return [
        'customer'     => 'Peserta',
        'admin_eo'     => 'Event Organizer',
        'finance'      => 'Verifikator Keuangan',
        'gate_officer' => 'Petugas Gate',
    ][$role] ?? ucfirst($role);
}

function label_status(string $status): string
{
    return [
        'Pending'              => 'Menunggu Bayar',
        'Waiting Verification' => 'Menunggu Verifikasi',
        'Paid'                 => 'Lunas',
        'Expired'              => 'Kadaluarsa',
        'Rejected'             => 'Ditolak',
    ][$status] ?? $status;
}

function status_class(string $status): string
{
    return [
        'Pending'              => 'badge-warning',
        'Waiting Verification' => 'badge-info',
        'Paid'                 => 'badge-success',
        'Expired'              => 'badge-muted',
        'Rejected'             => 'badge-danger',
    ][$status] ?? 'badge-muted';
}

/* ---------------------------------------------------------------------------
 * CSRF
 * ------------------------------------------------------------------------- */
function generate_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(generate_csrf_token()) . '">';
}

function verify_csrf_token($token): bool
{
    return is_string($token) && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/* ---------------------------------------------------------------------------
 * Flash message
 * ------------------------------------------------------------------------- */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (empty($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------------------------------------------------------------------------
 * Autentikasi & RBAC
 * ------------------------------------------------------------------------- */
function is_logged_in(): bool
{
    return !empty($_SESSION['id_user']);
}

function current_user(): ?array
{
    if (!is_logged_in()) return null;
    return [
        'id_user'      => (int)$_SESSION['id_user'],
        'nik'          => $_SESSION['nik'] ?? '',
        'nama_lengkap' => $_SESSION['nama_lengkap'] ?? '',
        'email'        => $_SESSION['email'] ?? '',
        'role'         => $_SESSION['role'] ?? 'customer',
    ];
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('ERROR', 'Silakan login terlebih dahulu.');
        header('Location: login.php');
        exit;
    }
}

function require_role(array $allowed): void
{
    require_login();
    $role = current_user()['role'];
    if (!in_array($role, $allowed, true)) {
        http_response_code(403);
        include __DIR__ . '/../Views/403.php';
        exit;
    }
}

/* ---------------------------------------------------------------------------
 * Audit log
 * ------------------------------------------------------------------------- */
function catat_audit_log(PDO $pdo, ?int $id_user, string $aksi, string $entitas, string $keterangan): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO audit_logs (id_user, aksi, entitas, keterangan, waktu)
                               VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([$id_user, mb_substr($aksi, 0, 80), mb_substr($entitas, 0, 60), $keterangan]);
    } catch (Throwable $ex) {
        error_log('[AUDIT] ' . $ex->getMessage());
    }
}

/* ---------------------------------------------------------------------------
 * Pelepasan transaksi kedaluwarsa (15 menit)
 * ------------------------------------------------------------------------- */
function release_expired_transactions(PDO $pdo): int
{
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->query("SELECT id_transaksi, id_voucher FROM transaksi
                             WHERE status_bayar IN ('Pending','Waiting Verification')
                               AND batas_bayar_at < NOW()
                             FOR UPDATE");
        $rows = $stmt->fetchAll();
        if (!$rows) { $pdo->commit(); return 0; }

        $relTier  = $pdo->prepare('UPDATE tiket_tier SET kuota_locked = GREATEST(0, kuota_locked - ?),
                                                          kuota_tersedia = kuota_tersedia + ?
                                   WHERE id_tier = ?');
        $freeSeat = $pdo->prepare("UPDATE kursi_event SET status_kursi='Available' WHERE id_kursi = ? AND status_kursi='Locked'");
        $getDet   = $pdo->prepare('SELECT id_tier, jumlah, id_kursi_list FROM transaksi_detail WHERE id_transaksi = ?');
        $updTrx   = $pdo->prepare("UPDATE transaksi SET status_bayar='Expired' WHERE id_transaksi = ?");
        $decVou   = $pdo->prepare('UPDATE voucher_promo SET kuota_pakai = GREATEST(0, kuota_pakai - 1) WHERE id_voucher = ?');

        foreach ($rows as $r) {
            $getDet->execute([$r['id_transaksi']]);
            foreach ($getDet->fetchAll() as $d) {
                $relTier->execute([$d['jumlah'], $d['jumlah'], $d['id_tier']]);
                if (!empty($d['id_kursi_list'])) {
                    foreach (explode(',', $d['id_kursi_list']) as $idk) {
                        $freeSeat->execute([(int)$idk]);
                    }
                }
            }
            if (!empty($r['id_voucher'])) $decVou->execute([$r['id_voucher']]);
            $updTrx->execute([$r['id_transaksi']]);
            catat_audit_log($pdo, null, 'EXPIRE', 'transaksi',
                'Transaksi #' . $r['id_transaksi'] . ' kedaluwarsa; kuota & kursi dikembalikan.');
        }
        $pdo->commit();
        return count($rows);
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[EXPIRY] ' . $ex->getMessage());
        return 0;
    }
}

/* ---------------------------------------------------------------------------
 * Kode tiket & booking
 * ------------------------------------------------------------------------- */
function generate_kode_tiket(int $nomor_urut = 1): string
{
    return 'TKT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(5))) . '-' . max(1, $nomor_urut);
}

function generate_kode_booking(): string
{
    return 'BK-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

/* ---------------------------------------------------------------------------
 * Barcode SVG (HMAC-SHA256, deterministik per tiket+NIK)
 * ------------------------------------------------------------------------- */
function render_barcode_svg(string $kode_tiket, string $nik_peserta, int $tinggi = 64): string
{
    $hex = hash_hmac('sha256', $kode_tiket . '|' . $nik_peserta, BARCODE_HMAC_SECRET);
    $bars = '';
    $x = 0;
    for ($i = 0; $i < 56; $i++) {
        $w = (hexdec($hex[$i]) % 3) + 1;
        if ($i % 2 === 0) {
            $bars .= '<rect x="' . $x . '" y="0" width="' . $w . '" height="' . $tinggi . '" fill="#111827"/>';
        }
        $x += $w + 1;
    }
    $wTotal = $x;
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $wTotal . ' ' . $tinggi . '" '
        . 'role="img" aria-label="Barcode ' . e($kode_tiket) . '" preserveAspectRatio="none">'
        . '<rect width="' . $wTotal . '" height="' . $tinggi . '" fill="#FFFFFF"/>' . $bars . '</svg>';
}

/* ---------------------------------------------------------------------------
 * Voucher bersyarat
 * ------------------------------------------------------------------------- */
function validate_voucher(PDO $pdo, string $kode, int $subtotal): array
{
    $kode = strtoupper(trim($kode));
    if ($kode === '') return ['ok' => false, 'msg' => 'Kode voucher kosong.'];
    $stmt = $pdo->prepare('SELECT * FROM voucher_promo WHERE kode_voucher = ?');
    $stmt->execute([$kode]);
    $v = $stmt->fetch();
    if (!$v) return ['ok' => false, 'msg' => 'Kode voucher tidak ditemukan.'];
    if ((int)$v['kuota_pakai'] <= 0) return ['ok' => false, 'msg' => 'Kuota voucher sudah habis.'];
    if (strtotime($v['expired_date']) < strtotime(date('Y-m-d'))) {
        return ['ok' => false, 'msg' => 'Voucher sudah kedaluwarsa.'];
    }
    if ($subtotal < (int)$v['min_transaksi']) {
        return ['ok' => false, 'msg' => 'Minimal transaksi ' . format_rupiah((int)$v['min_transaksi']) . ' untuk voucher ini.'];
    }
    $diskon = $v['tipe_potongan'] === 'Persen'
        ? (int)round($subtotal * (int)$v['nilai_potongan'] / 100)
        : min((int)$v['nilai_potongan'], $subtotal);
    return [
        'ok'      => true,
        'msg'     => 'Voucher diterapkan: potongan ' . format_rupiah($diskon) . '.',
        'id'      => (int)$v['id_voucher'],
        'diskon'  => $diskon,
        'tipe'    => $v['tipe_potongan'],
        'nilai'   => (int)$v['nilai_potongan'],
        'kode'    => $v['kode_voucher'],
    ];
}

/* ---------------------------------------------------------------------------
 * Batas 5 tiket per event (akumulasi lintas transaksi)
 * ------------------------------------------------------------------------- */
function check_user_ticket_limit(PDO $pdo, int $id_user, int $id_event, int $qty = 1): array
{
    $paid = $pdo->prepare("SELECT COALESCE(COUNT(*),0) FROM e_tickets et
                           JOIN transaksi t ON t.id_transaksi = et.id_transaksi
                           WHERE t.id_user = ? AND t.id_event = ? AND t.status_bayar = 'Paid'");
    $paid->execute([$id_user, $id_event]);
    $used = (int)$paid->fetchColumn();

    $active = $pdo->prepare("SELECT COALESCE(SUM(d.jumlah),0) FROM transaksi_detail d
                             JOIN transaksi t ON t.id_transaksi = d.id_transaksi
                             WHERE t.id_user = ? AND t.id_event = ?
                               AND t.status_bayar IN ('Pending','Waiting Verification')");
    $active->execute([$id_user, $id_event]);
    $used += (int)$active->fetchColumn();

    if ($used + $qty > MAX_TIKET_PER_EVENT) {
        return ['ok' => false, 'used' => $used,
                'msg' => 'Batas maksimal ' . MAX_TIKET_PER_EVENT . ' tiket per event terlampaui (sudah ' . $used . ').'];
    }
    return ['ok' => true, 'used' => $used];
}

function check_user_active_reservation(PDO $pdo, int $id_user, int $id_event): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM transaksi
                           WHERE id_user = ? AND id_event = ?
                             AND status_bayar IN ('Pending','Waiting Verification')");
    $stmt->execute([$id_user, $id_event]);
    return (int)$stmt->fetchColumn() > 0;
}

/* ---------------------------------------------------------------------------
 * Upload bukti bayar
 * ------------------------------------------------------------------------- */
function handle_bukti_upload(array $file, string $uploadDir): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Tidak ada file bukti bayar yang diunggah.');
    }
    if ((int)$file['size'] > UPLOAD_MAX_SIZE) {
        throw new InvalidArgumentException('Ukuran file maksimal 2 MB.');
    }
    $name = (string)$file['name'];
    if (substr_count($name, '.') > 1) {
        throw new InvalidArgumentException('Nama file tidak valid (ekstensi ganda ditolak).');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!isset(UPLOAD_ALLOWED_MIME[$mime])) {
        throw new InvalidArgumentException('Format bukti harus JPG, PNG, atau WEBP.');
    }
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new RuntimeException('Folder upload tidak tersedia.');
    }
    $ext  = UPLOAD_ALLOWED_MIME[$mime];
    $rand = bin2hex(random_bytes(12));
    $dest = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $rand . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Gagal menyimpan file bukti bayar.');
    }
    @chmod($dest, 0640);
    return 'uploads/bukti/' . $rand . '.' . $ext;
}

/* ---------------------------------------------------------------------------
 * Rate limit login (5 percobaan / 15 menit per identifier)
 * ------------------------------------------------------------------------- */
function check_login_rate_limit(PDO $pdo, string $identifier, int $maxAttempts = 5): array
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts
                           WHERE identifier = ? AND success = 0
                             AND created_at > (NOW() - INTERVAL 15 MINUTE)");
    $stmt->execute([$identifier]);
    $fails = (int)$stmt->fetchColumn();
    if ($fails >= $maxAttempts) {
        return ['allowed' => false, 'retry_after' => 900,
                'msg' => 'Terlalu banyak percobaan. Coba lagi dalam 15 menit.'];
    }
    return ['allowed' => true, 'retry_after' => 0, 'msg' => ''];
}

function record_login_attempt(PDO $pdo, string $identifier, bool $success): void
{
    $stmt = $pdo->prepare('INSERT INTO login_attempts (identifier, success) VALUES (?, ?)');
    $stmt->execute([mb_substr($identifier, 0, 255), $success ? 1 : 0]);
    if ($success) {
        $del = $pdo->prepare('DELETE FROM login_attempts WHERE identifier = ? AND success = 0');
        $del->execute([mb_substr($identifier, 0, 255)]);
    }
}

/* ---------------------------------------------------------------------------
 * Navigasi berdasarkan role
 * ------------------------------------------------------------------------- */
function nav_items(?array $user): array
{
    $items = [['label' => 'Katalog Event', 'href' => 'index.php', 'icon' => 'grid']];
    if (!$user) {
        $items[] = ['label' => 'Login & Registrasi', 'href' => 'login.php', 'icon' => 'user'];
        return $items;
    }
    switch ($user['role']) {
        case 'customer':
            $items[] = ['label' => 'Dashboard Saya', 'href' => 'dashboard_peserta.php', 'icon' => 'ticket'];
            break;
        case 'admin_eo':
            $items[] = ['label' => 'Dashboard EO', 'href' => 'dashboard_eo.php', 'icon' => 'chart'];
            $items[] = ['label' => 'Kelola Event & Tier', 'href' => 'crud_event.php', 'icon' => 'calendar'];
            $items[] = ['label' => 'Kelola Voucher', 'href' => 'kelola_voucher.php', 'icon' => 'tag'];
            $items[] = ['label' => 'Audit Log', 'href' => 'audit_log.php', 'icon' => 'shield'];
            break;
        case 'finance':
            $items[] = ['label' => 'Verifikasi Pembayaran', 'href' => 'verifikasi_pembayaran.php', 'icon' => 'check'];
            break;
        case 'gate_officer':
            $items[] = ['label' => 'Gate Check-in', 'href' => 'gate_checkin.php', 'icon' => 'scan'];
            break;
    }
    return $items;
}
