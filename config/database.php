<?php
/**
 * config/database.php
 * Koneksi PDO ke database db_pensi_sekolah
 * Timezone Asia/Jakarta (WIB)
 *
 * Catatan produksi:
 * - Kredensial dapat dioverride via environment variable (DB_HOST, DB_NAME, DB_USER, DB_PASS).
 * - Jangan pernah menampilkan detail exception database ke pengguna.
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

date_default_timezone_set('Asia/Jakarta');

// Konstanta aplikasi
define('BATAS_WAKTU_BAYAR_MENIT', 15);       // 15 menit (per Mandat CEO PAKET 7)
define('UPLOAD_MAX_SIZE', 2 * 1024 * 1024);  // 2 MB
define('UPLOAD_ALLOWED_MIME', ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp']);

/**
 * Muat secret HMAC untuk barcode.
 * Urutan: environment variable -> storage/barcode_secret.key (di luar webroot)
 * -> generate acak sekali secara atomik. Jika semua gagal, fail closed (HTTP 500).
 * Tidak ada fallback hardcoded.
 */
if (!function_exists('pensi_secret_fatal')) {
    function pensi_secret_fatal(): void
    {
        http_response_code(500);
        echo "<!DOCTYPE html><html lang='id'><head><meta charset='UTF-8'><title>Kesalahan Server</title></head><body>";
        echo "<h2>Layanan Sedang Tidak Tersedia</h2>";
        echo "<p>Konfigurasi keamanan server belum lengkap. Hubungi administrator sistem.</p>";
        echo "</body></html>";
        exit;
    }
}

if (!function_exists('pensi_load_hmac_secret')) {
    function pensi_load_hmac_secret(): string
    {
        $env = getenv('BARCODE_HMAC_SECRET');
        if (is_string($env) && strlen(trim($env)) >= 32) {
            return trim($env);
        }
        if ($env !== false && $env !== '') {
            error_log('[BARCODE_SECRET] BARCODE_HMAC_SECRET diabaikan karena kurang dari 32 karakter.');
        }

        $key_file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'barcode_secret.key';

        if (is_file($key_file)) {
            // Retry singkat untuk mengantisipasi proses lain yang baru membuat file.
            for ($i = 0; $i < 20; $i++) {
                $stored = trim((string)@file_get_contents($key_file));
                if (strlen($stored) >= 32) {
                    return $stored;
                }
                usleep(50000);
            }
            error_log('[BARCODE_SECRET] File secret ada tetapi tidak valid (minimal 32 karakter).');
            pensi_secret_fatal();
        }

        try {
            $dir = dirname($key_file);
            if (!is_dir($dir) || !is_writable($dir)) {
                throw new RuntimeException('Direktori storage tidak dapat ditulis.');
            }

            $new_secret = bin2hex(random_bytes(32));
            $fp = @fopen($key_file, 'xb');
            if ($fp === false) {
                // Kemungkinan proses lain menang balapan; baca file yang dibuatnya.
                $stored = trim((string)@file_get_contents($key_file));
                if (strlen($stored) >= 32) {
                    return $stored;
                }
                throw new RuntimeException('Gagal membuat file secret secara eksklusif.');
            }

            fwrite($fp, $new_secret . PHP_EOL);
            fclose($fp);
            @chmod($key_file, 0600);
            return $new_secret;
        } catch (Throwable $e) {
            error_log('[BARCODE_SECRET] ' . $e->getMessage());
            pensi_secret_fatal();
        }

        return '';
    }
}

define('BARCODE_HMAC_SECRET', pensi_load_hmac_secret());

$db_host    = getenv('DB_HOST') !== false ? getenv('DB_HOST') : 'localhost';
$db_name    = getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'db_pensi_sekolah';
$db_user    = getenv('DB_USER') !== false ? getenv('DB_USER') : 'pensi_app';
$db_pass    = getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'PensiApp2026!Secure#Pass';
$db_charset = 'utf8mb4';

$dsn = "mysql:host={$db_host};dbname={$db_name};charset={$db_charset}";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    // Selaraskan NOW()/CURDATE() MySQL dengan zona waktu aplikasi (WIB).
    $pdo->exec("SET time_zone = '+07:00'");
} catch (PDOException $e) {
    error_log('[DB_CONNECTION] ' . $e->getMessage());
    http_response_code(500);
    echo "<!DOCTYPE html><html lang='id'><head><meta charset='UTF-8'><title>Kesalahan Server</title></head><body>";
    echo "<h2>Layanan Sedang Tidak Tersedia</h2>";
    echo "<p>Terjadi kesalahan internal. Silakan hubungi administrator sistem.</p>";
    echo "</body></html>";
    exit;
}
