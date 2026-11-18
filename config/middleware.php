<?php
/**
 * config/middleware.php
 * Middleware autentikasi, otorisasi (RBAC), helper, audit log, dan fungsi utilitas
 * Termasuk: release_expired_transactions (auto-kadaluarsa 15 menit), render_barcode_svg
 *
 * Catatan keamanan:
 * - Cookie sesi Secure hanya diaktifkan saat HTTPS (agar localhost HTTP tetap berfungsi).
 * - SameSite=Strict, HttpOnly, use_strict_mode, idle timeout 15 menit, absolute timeout 8 jam.
 */

if (!defined('SESSION_IDLE_TIMEOUT')) {
    define('SESSION_IDLE_TIMEOUT', 900);        // 15 menit idle
}
if (!defined('SESSION_ABSOLUTE_TIMEOUT')) {
    define('SESSION_ABSOLUTE_TIMEOUT', 28800); // 8 jam sejak login
}

// Jangan bocorkan versi PHP melalui header respons.
header_remove('X-Powered-By');

// PHP version check - warn if outdated (PHP2Shell affects < 8.2.35)
$php_version = PHP_VERSION_ID;
$php_min_safe = 80235; // 8.2.35
if ($php_version < $php_min_safe) {
    error_log('[SECURITY] PHP version ' . PHP_VERSION . ' is outdated. PHP2Shell (CVE-2026-109619) mitigation requires PHP >= 8.2.35. Consider upgrading or ensure session.upload_progress.enabled=Off in php.ini.');
}

if (session_status() === PHP_SESSION_NONE) {
    $is_https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443');

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $is_https ? '1' : '0');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.cookie_path', '/');
    ini_set('session.gc_maxlifetime', (string)SESSION_IDLE_TIMEOUT);
    ini_set('session.sid_length', '48');
    ini_set('session.sid_bits_per_character', '5');
    session_start();
}

// Idle timeout & absolute timeout untuk sesi yang sudah login.
if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user']['id_user'])) {
    $now = time();
    $last_activity = (int)($_SESSION['_last_activity'] ?? $now);
    $login_time    = (int)($_SESSION['_login_time'] ?? $now);
    $expired = ($now - $last_activity > SESSION_IDLE_TIMEOUT)
        || ($now - $login_time > SESSION_ABSOLUTE_TIMEOUT);

    if ($expired) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['flash'] = [
            'type'    => 'ERROR',
            'message' => 'Sesi Anda telah berakhir karena tidak ada aktivitas. Silakan login kembali.',
        ];
        header('Location: login.php?timeout=1');
        exit;
    }

    $_SESSION['_last_activity'] = $now;
    if (empty($_SESSION['_login_time'])) {
        $_SESSION['_login_time'] = $now;
    }
}

/**
 * Strip debug parameters from request to prevent info disclosure
 */
function strip_debug_params(): void
{
    $debug_params = ['debug', 'display_errors', 'xdebug', 'dev', 'sql_debug', 'trace'];
    foreach ($debug_params as $param) {
        if (isset($_GET[$param])) {
            unset($_GET[$param]);
        }
        if (isset($_POST[$param])) {
            unset($_POST[$param]);
        }
    }
}
strip_debug_params();

/**
 * Escape output HTML (XSS prevention)
 */
function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Format angka ke Rupiah
 */
function format_rupiah(int $nominal): string
{
    return 'Rp ' . number_format($nominal, 0, ',', '.');
}

/**
 * Label role untuk tampilan
 */
function label_role(string $role): string
{
    $roles = [
        'pembeli'           => 'Pembeli (Peserta)',
        'panitia_keuangan'  => 'Panitia Keuangan',
        'petugas_gate'      => 'Petugas Gate',
        'admin_osis'        => 'Admin OSIS',
    ];
    return $roles[$role] ?? $role;
}

/**
 * Label tipe pengguna
 */
function label_tipe_pengguna(string $tipe): string
{
    return $tipe === 'Internal' ? 'Siswa Internal (NIS)' : 'Tamu Eksternal (NIK)';
}

/**
 * Generate CSRF token
 */
function generate_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf_token($token): bool
{
    if (!is_string($token) || $token === '' || empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Set flash message
 */
function set_flash(string $type, string $message): void
{
    if (!in_array($type, ['SUKSES', 'ERROR', 'INFO'], true)) {
        $type = 'INFO';
    }
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Get & clear flash message
 */
function get_flash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Check login status
 */
function is_logged_in(): bool
{
    return !empty($_SESSION['user']) && !empty($_SESSION['user']['id_user']);
}

/**
 * Get current user data
 */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * IP klien (jangan percaya header proxy secara default)
 */
function client_ip(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/**
 * Catat jejak aktivitas ke audit_logs
 */
function catat_audit_log(PDO $pdo, int $id_user, string $aktivitas, string $tabel_terkait, string $detail_perubahan): void
{
    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (id_user, aktivitas, tabel_terkait, detail_perubahan, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$id_user, $aktivitas, $tabel_terkait, $detail_perubahan]);
}

/**
 * Auto-kadaluarsa: Ubah pemesanan 'Menunggu Bayar' yang lewat batas_waktu_bayar
 * Kembalikan stok_tersedia, kurangi stok_tertahan, ubah kursi Locked -> Available,
 * kembalikan kuota promo, dan catat audit log.
 * Batas waktu: 15 menit (per Mandat CEO PAKET 7)
 */
function release_expired_transactions(PDO $pdo): void
{
    // Fungsi ini tidak boleh dipanggil di tengah transaksi lain (nested transaction).
    if ($pdo->inTransaction()) {
        return;
    }

    $stmtExp = $pdo->query("
        SELECT id_pesan, kode_transaksi, id_user, id_jenis, id_promo, jumlah
        FROM pemesanan
        WHERE status_bayar = 'Menunggu Bayar' AND batas_waktu_bayar < NOW()
    ");
    $expired_rows = $stmtExp->fetchAll();

    foreach ($expired_rows as $trx) {
        try {
            $pdo->beginTransaction();

            // Lock row untuk cek status terbaru
            $stmtLock = $pdo->prepare("SELECT status_bayar FROM pemesanan WHERE id_pesan = ? FOR UPDATE");
            $stmtLock->execute([(int)$trx['id_pesan']]);
            if ($stmtLock->fetchColumn() === 'Menunggu Bayar') {
                // Update status pemesanan
                $updTrx = $pdo->prepare("
                    UPDATE pemesanan
                    SET status_bayar = 'Kadaluarsa', catatan_penolakan = 'Batas waktu pembayaran 15 menit telah habis.'
                    WHERE id_pesan = ?
                ");
                $updTrx->execute([(int)$trx['id_pesan']]);

                // Kembalikan stok tiket
                $updJenis = $pdo->prepare("
                    UPDATE jenis_tiket
                    SET stok_tersedia = stok_tersedia + ?,
                        stok_tertahan = GREATEST(0, stok_tertahan - ?)
                    WHERE id_jenis = ?
                ");
                $updJenis->execute([(int)$trx['jumlah'], (int)$trx['jumlah'], (int)$trx['id_jenis']]);

                // Kembalikan kursi Locked -> Available
                $stmtDet = $pdo->prepare("SELECT id_kursi FROM detail_tiket_satuan WHERE id_pesan = ?");
                $stmtDet->execute([(int)$trx['id_pesan']]);
                foreach ($stmtDet->fetchAll() as $dt) {
                    if (!empty($dt['id_kursi'])) {
                        $updKursi = $pdo->prepare("
                            UPDATE kursi_pensi
                            SET status_kursi = 'Available'
                            WHERE id_kursi = ? AND status_kursi = 'Locked'
                        ");
                        $updKursi->execute([(int)$dt['id_kursi']]);
                    }
                }

                // Kembalikan kuota promo yang sempat terpakai
                if (!empty($trx['id_promo'])) {
                    $updPromo = $pdo->prepare("UPDATE kode_promo SET kuota_voucher = kuota_voucher + 1 WHERE id_promo = ?");
                    $updPromo->execute([(int)$trx['id_promo']]);
                }

                catat_audit_log(
                    $pdo,
                    (int)$trx['id_user'],
                    'EXPIRE_PEMESANAN',
                    'pemesanan',
                    "Pemesanan {$trx['kode_transaksi']} otomatis kadaluarsa. Stok, kursi & kuota promo dikembalikan."
                );
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[EXPIRE_TRANSACTION] ' . $e->getMessage());
        }
    }
}

/**
 * Middleware: Wajib login
 */
function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('ERROR', 'Akses ditolak! Silakan login terlebih dahulu.');
        header('Location: login.php');
        exit;
    }
}

/**
 * Middleware: RBAC - batasi akses berdasarkan role
 * HTTP 403 jika role tidak diizinkan
 */
function require_role(array $allowed_roles): void
{
    require_login();

    $user = current_user();
    $role = $user['role'] ?? '';

    if (!in_array($role, $allowed_roles, true)) {
        http_response_code(403);
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>403 - Akses Ditolak (RBAC)</title>
        </head>
        <body>
            <table border="2" cellpadding="12" cellspacing="0" width="100%">
                <tr>
                    <td>
                        <h2>AKSES DITOLAK &mdash; HTTP 403 FORBIDDEN (RBAC MIDDLEWARE)</h2>
                        <p>
                            Halo <strong><?= e($user['nama']) ?></strong>, akun Anda terdaftar sebagai
                            <strong><?= e(label_role($role)) ?></strong> sehingga tidak diizinkan membuka halaman ini.
                        </p>
                        <p>
                            Halaman ini hanya diperbolehkan untuk role:
                            <strong><?= e(implode(', ', array_map('label_role', $allowed_roles))) ?></strong>.
                        </p>
                        <hr>
                        <p>
                            <a href="index.php">[ Ke Katalog Pensi ]</a>
                            <?php if ($role === 'admin_osis'): ?>
                                | <a href="dashboard_panitia.php">[ Dashboard Panitia ]</a>
                            <?php elseif ($role === 'panitia_keuangan'): ?>
                                | <a href="verifikasi_pembayaran.php">[ Verifikasi Pembayaran ]</a>
                            <?php elseif ($role === 'petugas_gate'): ?>
                                | <a href="gate_checkin.php">[ Gate Check-in ]</a>
                            <?php else: ?>
                                | <a href="dashboard_peserta.php">[ Dashboard Peserta ]</a>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        <?php
        exit;
    }
}

/**
 * Generator SVG Barcode/QR dengan HMAC-SHA256 (aman untuk anti-forgery)
 * Digunakan untuk E-Ticket cetak
 */
function render_barcode_svg(string $kode_tiket, string $nik_peserta): string
{
    if (!defined('BARCODE_HMAC_SECRET') || strlen((string)BARCODE_HMAC_SECRET) < 32) {
        // Fail closed: jangan pernah menandatangani barcode dengan secret lemah/fallback.
        throw new RuntimeException('Secret HMAC barcode tidak tersedia atau tidak valid.');
    }
    $hash = hash_hmac('sha256', $kode_tiket . '|' . $nik_peserta, (string)BARCODE_HMAC_SECRET);
    $rects = '';
    $x = 12;
    for ($i = 0; $i < 48; $i++) {
        $val = hexdec($hash[$i]);
        $w = ($val % 3 === 0) ? 4 : (($val % 2 === 0) ? 3 : 2);
        if ($val >= 4) {
            $rects .= '<rect x="' . $x . '" y="10" width="' . $w . '" height="54" fill="#000000"/>';
        }
        $x += $w + 2;
    }
    $total_w = $x + 10;
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $total_w . '" height="88" viewBox="0 0 ' . $total_w . ' 88">'
        . '<rect x="1" y="1" width="' . ($total_w - 2) . '" height="86" fill="#ffffff" stroke="#000000" stroke-width="1.5"/>'
        . $rects
        . '<text x="' . (int)($total_w / 2) . '" y="79" font-family="monospace" font-size="11" font-weight="bold" fill="#000000" text-anchor="middle">'
        . e($kode_tiket)
        . '</text></svg>';
}

/**
 * Generate kode tiket unik: PEN-YYMMDD-XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX-N
 * Entropy: 128-bit (16 random bytes = 32 hex chars)
 */
function generate_kode_tiket(int $nomor_urut): string
{
    return 'PEN-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(16))) . '-' . $nomor_urut;
}

/**
 * Generate kode transaksi unik: PSN-YYYYMMDD-XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
 * Entropy: 128-bit (16 random bytes = 32 hex chars)
 */
function generate_kode_transaksi(): string
{
    return 'PSN-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(16)));
}

/**
 * Validasi NIK KTP (16 digit) atau NIS (fleksibel, 5-20 digit angka)
 */
function validate_nis_nik(string $value, string $tipe_pengguna): bool
{
    if ($tipe_pengguna === 'Internal') {
        return (bool)preg_match('/^[0-9]{5,20}$/', $value);
    }
    return (bool)preg_match('/^[0-9]{16}$/', $value);
}

/**
 * Validasi nomor HP Indonesia
 */
function validate_no_hp(string $hp): bool
{
    $clean = preg_replace('/[\s\-]/', '', $hp);
    return (bool)preg_match('/^(08|\+628)[0-9]{7,13}$/', $clean);
}

/**
 * Cek apakah user sudah mencapai batas 5 tiket per event (aggregate across orders)
 * Status yang dihitung: Lunas, Menunggu Bayar, Menunggu Verifikasi.
 */
function check_user_ticket_limit(PDO $pdo, int $id_user, int $additional_qty = 1): bool
{
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(jumlah), 0) AS total_tiket
        FROM pemesanan
        WHERE id_user = ? AND status_bayar IN ('Lunas', 'Menunggu Bayar', 'Menunggu Verifikasi')
    ");
    $stmt->execute([$id_user]);
    $current_total = (int)$stmt->fetchColumn();

    return ($current_total + $additional_qty) <= 5;
}

/**
 * Cek apakah user sudah memiliki reservasi aktif (Menunggu Bayar / Menunggu Verifikasi).
 * Mencegah queue hoarding - max 1 reservasi aktif per user per event.
 */
function check_user_active_reservation(PDO $pdo, int $id_user): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS active_count
        FROM pemesanan
        WHERE id_user = ? AND status_bayar IN ('Menunggu Bayar', 'Menunggu Verifikasi')
    ");
    $stmt->execute([$id_user]);

    return (int)$stmt->fetchColumn() === 0;
}

/**
 * Cek apakah kode promo sudah pernah dipakai user pada pesanan aktif.
 * Mencegah reuse kode promo yang sama berkali-kali oleh satu akun.
 */
function check_promo_used_by_user(PDO $pdo, int $id_user, int $id_promo): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM pemesanan
        WHERE id_user = ? AND id_promo = ?
          AND status_bayar IN ('Menunggu Bayar', 'Menunggu Verifikasi', 'Lunas')
    ");
    $stmt->execute([$id_user, $id_promo]);

    return (int)$stmt->fetchColumn() > 0;
}

/**
 * Rate limiting untuk login attempts (hanya kegagalan yang dihitung).
 * Returns array ['allowed' => bool, 'remaining' => int, 'reset_time' => int|null]
 */
function check_login_rate_limit(PDO $pdo, string $identifier, int $max_attempts = 5): array
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS attempts, MAX(created_at) AS last_attempt
        FROM login_attempts
        WHERE identifier = ? AND success = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->execute([$identifier]);
    $row = $stmt->fetch();

    $attempts = (int)($row['attempts'] ?? 0);

    if ($attempts >= $max_attempts) {
        $reset_time = strtotime((string)$row['last_attempt']) + 900;
        return ['allowed' => false, 'remaining' => 0, 'reset_time' => $reset_time];
    }

    return ['allowed' => true, 'remaining' => $max_attempts - $attempts, 'reset_time' => null];
}

/**
 * Record login attempt (success or failure).
 * Saat sukses dengan $clear_failures=true, riwayat gagal untuk identifier tersebut dibersihkan.
 */
function record_login_attempt(PDO $pdo, string $identifier, bool $success, bool $clear_failures = false): void
{
    $stmt = $pdo->prepare("
        INSERT INTO login_attempts (identifier, success, created_at)
        VALUES (?, ?, NOW())
    ");
    $stmt->execute([mb_substr($identifier, 0, 255), $success ? 1 : 0]);

    if ($success && $clear_failures) {
        $del = $pdo->prepare("DELETE FROM login_attempts WHERE identifier = ? AND success = 0");
        $del->execute([mb_substr($identifier, 0, 255)]);
    }

    // Cleanup old attempts (older than 1 hour)
    $pdo->exec("DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
}

/**
 * Check if request has debug parameters (for logging/alerting)
 */
function has_debug_params(): bool
{
    $debug_params = ['debug', 'display_errors', 'xdebug', 'dev', 'sql_debug', 'trace'];
    foreach ($debug_params as $param) {
        if (isset($_GET[$param]) || isset($_POST[$param])) {
            return true;
        }
    }
    return false;
}
