<?php
/**
 * bukti.php — Proxy bukti transfer terautentikasi (v4)
 * require_login; GET kode. Izinkan owner, finance, admin_eo.
 * File disajikan dengan Content-Type benar, Content-Disposition inline,
 * tanpa traversal path. 404 jika tidak ada.
 */
require_once __DIR__ . '/src/Config/database.php';
require_once __DIR__ . '/src/Middleware/middleware.php';
require_login();
release_expired_transactions($pdo);

$user    = current_user();
$id_user = (int)$user['id_user'];
$role    = $user['role'];

$kode = $_GET['kode'] ?? '';
if ($kode === '') {
    http_response_code(404);
    echo 'Berkas tidak ditemukan.';
    exit;
}

$stmt = $pdo->prepare('SELECT t.* FROM transaksi t WHERE t.kode_booking = ? LIMIT 1');
$stmt->execute([$kode]);
$trx = $stmt->fetch();
if (!$trx || empty($trx['bukti_bayar'])) {
    http_response_code(404);
    echo 'Berkas tidak ditemukan.';
    exit;
}

$isOwner  = $trx['id_user'] === $id_user;
$isStaff  = in_array($role, ['admin_eo', 'finance'], true);
if (!$isOwner && !$isStaff) {
    http_response_code(404);
    echo 'Berkas tidak ditemukan.';
    exit;
}

$rel = (string)$trx['bukti_bayar'];
if (!preg_match('#^uploads/bukti/[A-Za-z0-9._-]+$#', $rel)) {
    http_response_code(404);
    echo 'Berkas tidak ditemukan.';
    exit;
}

$base = realpath(__DIR__ . '/uploads/bukti');
$file = realpath(__DIR__ . '/' . $rel);
if ($base === false || $file === false || !is_file($file)) {
    http_response_code(404);
    echo 'Berkas tidak ditemukan.';
    exit;
}
$prefix = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (strncasecmp($file, $prefix, strlen($prefix)) !== 0) {
    http_response_code(404);
    echo 'Berkas tidak ditemukan.';
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file);
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!is_string($mime) || !isset($allowed[$mime])) {
    http_response_code(404);
    echo 'Berkas tidak ditemukan.';
    exit;
}

$size = filesize($file);
if ($size === false) {
    http_response_code(404);
    echo 'Berkas tidak ditemukan.';
    exit;
}

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)$size);
header('Content-Disposition: inline; filename="bukti-' . e($kode) . '.' . $allowed[$mime] . '"');
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
readfile($file);
exit;
