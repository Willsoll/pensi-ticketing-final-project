<?php
/**
 * config/database.php
 * Koneksi PDO ke database db_pensi_sekolah
 * Timezone Asia/Jakarta (WIB)
 */
date_default_timezone_set('Asia/Jakarta');

// Konstanta aplikasi
define('BATAS_WAKTU_BAYAR_MENIT', 120);    // 2 jam (120 menit) sesuai Mandat CEO PAKET 7
define('UPLOAD_MAX_SIZE', 2 * 1024 * 1024); // 2 MB
define('UPLOAD_ALLOWED_MIME', ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp']);

$db_host    = 'localhost';
$db_name    = 'db_pensi_sekolah';
$db_user    = 'root';
$db_pass    = '';
$db_charset = 'utf8mb4';

$dsn = "mysql:host={$db_host};dbname={$db_name};charset={$db_charset}";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo "<!DOCTYPE html><html lang='id'><head><meta charset='UTF-8'><title>Kesalahan Koneksi Database</title></head><body>";
    echo "<h2>Koneksi Basis Data Gagal</h2>";
    echo "<p>Pastikan layanan MySQL aktif dan file <strong>docs/schema.sql</strong> sudah diimpor ke database <code>db_pensi_sekolah</code>.</p>";
    echo "<p>Detail teknis: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
    echo "</body></html>";
    exit;
}