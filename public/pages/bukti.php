<?php
/**
 * bukti.php - Proxy bukti transfer terautentikasi.
 *
 * Bukti bayar tidak lagi dapat diakses langsung dari /uploads/bukti (deny all).
 * Pembeli hanya dapat melihat bukti miliknya sendiri; Panitia Keuangan dan
 * Admin OSIS dapat melihat seluruh bukti untuk proses verifikasi.
 */
require_once __DIR__ . '/../src/Config/database.php';
require_once __DIR__ . '/../src/Middleware/middleware.php';

require_login();

$user = current_user();
$id_pesan = filter_var($_GET['id_pesan'] ?? null, FILTER_VALIDATE_INT);
if ($id_pesan === false || $id_pesan === null || $id_pesan < 1) {
    http_response_code(404);
    echo 'Berkas tidak ditemukan.';
    exit;
}

$stmt = $pdo->prepare("SELECT id_pesan, id_user, bukti_bayar FROM pemesanan WHERE id_pesan = ? LIMIT 1");
$stmt->execute([(int)$id_pesan]);
$pesanan = $stmt->fetch();

if (!$pesanan || empty($pesanan['bukti_bayar'])) {
    http_response_code(404);
    echo 'Berkas tidak ditemukan.';
    exit;
}

$role = (string)($user['role'] ?? '');
$is_owner = ((int)$pesanan['id_user'] === (int)$user['id_user']);
$is_reviewer = in_array($role, ['panitia_keuangan', 'admin_osis'], true);
if (!$is_owner && !$is_reviewer) {
    // 404 (bukan 403) agar keberadaan pesanan tidak dapat dienumerasi.
    http_response_code(404);
    echo 'Berkas tidak ditemukan.';
    exit;
}

$rel_path = (string)$pesanan['bukti_bayar'];
if (!preg_match('#^uploads/bukti/[A-Za-z0-9._-]+$#', $rel_path)) {
    http_response_code(404);
    echo 'Berkas tidak ditemukan.';
    exit;
}

$base_dir = realpath(__DIR__ . '/../uploads/bukti');
$file_path = realpath(__DIR__ . '/' . $rel_path);
if ($base_dir === false || $file_path === false || !is_file($file_path)) {
    http_response_code(404);
    echo 'Berkas tidak ditemukan.';
    exit;
}

$base_prefix = rtrim($base_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (strncasecmp($file_path, $base_prefix, strlen($base_prefix)) !== 0) {
    http_response_code(404);
    echo 'Berkas tidak ditemukan.';
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file_path);
$allowed_mime = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];
if (!is_string($mime) || !isset($allowed_mime[$mime])) {
    http_response_code(404);
    echo 'Berkas tidak ditemukan.';
    exit;
}

$size = filesize($file_path);
if ($size === false) {
    http_response_code(404);
    echo 'Berkas tidak ditemukan.';
    exit;
}

// Bersihkan output buffer agar isi biner tidak rusak.
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)$size);
header('Content-Disposition: inline; filename="bukti-' . (int)$pesanan['id_pesan'] . '.' . $allowed_mime[$mime] . '"');
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'none'; sandbox");
header('X-Content-Type-Options: nosniff');

readfile($file_path);
exit;
