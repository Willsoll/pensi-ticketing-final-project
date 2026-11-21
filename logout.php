<?php
/**
 * logout.php - Keluar dari sesi
 * Hancurkan sesi, hapus cookie, arahkan ke index.php dengan flash.
 */
require_once __DIR__ . '/src/Config/database.php';
require_once __DIR__ . '/src/Middleware/middleware.php';

// Logout is a state-changing action: require POST + CSRF.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(405);
    header('Allow: POST');
    exit('Metode tidak diizinkan.');
}

$idUser = $_SESSION['id_user'] ?? null;
if ($idUser) {
    catat_audit_log($pdo, (int)$idUser, 'LOGOUT', 'users', 'Keluar dari sesi.');
}

// Siapkan flash sebelum membersihkan sesi agar pesan tetap tampil di halaman tujuan.
set_flash('INFO', 'Anda telah keluar.');
$flash = $_SESSION['flash'] ?? null;

// Destroy the server-side session and expire the browser cookie.
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'] ?: '/', $params['domain'] ?? '',
        !empty($params['secure']), !empty($params['httponly']));
}
session_destroy();

header('Location: index.php');
exit;