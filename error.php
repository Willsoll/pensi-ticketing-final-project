<?php
/**
 * error.php - Halaman error statis (tanpa dependensi database/middleware)
 * Dipanggil oleh ErrorDocument di .htaccess.
 */
$code_raw = $_GET['code'] ?? 404;
$code = is_numeric($code_raw) ? (int)$code_raw : 404;
if (!in_array($code, [403, 404, 500], true)) {
    $code = 404;
}
http_response_code($code);

$messages = [
    403 => ['Akses Ditolak', 'Anda tidak memiliki izin untuk mengakses halaman atau berkas ini.'],
    404 => ['Halaman Tidak Ditemukan', 'Halaman atau berkas yang Anda cari tidak tersedia.'],
    500 => ['Kesalahan Server', 'Terjadi kesalahan internal. Silakan coba beberapa saat lagi.'],
];
[$title, $message] = $messages[$code];
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $code ?> - <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #FFFFFF; color: #111827; display: flex; min-height: 100vh; align-items: center; justify-content: center; padding: 24px; }
        .box { max-width: 560px; width: 100%; border: 1px solid #E5E7EB; border-radius: 12px; padding: 32px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .code { font-size: 4rem; font-weight: 800; color: #4F46E5; line-height: 1; margin-bottom: 12px; }
        h1 { font-size: 1.25rem; margin-bottom: 12px; }
        p { color: #6B7280; margin-bottom: 24px; }
        a { display: inline-block; background: #4F46E5; color: #FFFFFF; text-decoration: none; padding: 10px 18px; border-radius: 6px; font-weight: 600; }
        a:hover { background: #4338CA; }
    </style>
</head>
<body>
    <div class="box">
        <div class="code"><?= $code ?></div>
        <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
        <a href="/index.php">Kembali ke Katalog Acara</a>
    </div>
</body>
</html>
