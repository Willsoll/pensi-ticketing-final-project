<?php
/**
 * logout.php - Keluar dari sesi (dilindungi CSRF token)
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/middleware.php';

// Guest: langsung arahkan ke halaman login.
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$token = $_GET['csrf'] ?? $_POST['csrf_token'] ?? '';
if (!verify_csrf_token(is_string($token) ? $token : '')) {
    set_flash('ERROR', 'Permintaan logout tidak valid (CSRF token tidak cocok).');
    header('Location: index.php');
    exit;
}

// Hapus data sesi & cookie sesi.
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 42000,
            'path'     => $params['path'] ?: '/',
            'domain'   => $params['domain'] ?? '',
            'secure'   => (bool)($params['secure'] ?? false),
            'httponly' => (bool)($params['httponly'] ?? true),
            'samesite' => $params['samesite'] ?? 'Strict',
        ]
    );
}
session_destroy();

header('Location: login.php?logout=1');
exit;
