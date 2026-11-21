<?php
/**
 * logout.php - Keluar dari sesi
 * Hancurkan sesi, hapus cookie, arahkan ke index.php dengan flash.
 */
require_once __DIR__ . '/src/Config/database.php';
require_once __DIR__ . '/src/Middleware/middleware.php';

$idUser = $_SESSION['id_user'] ?? null;
if ($idUser) {
    catat_audit_log($pdo, (int)$idUser, 'LOGOUT', 'users', 'Keluar dari sesi.');
}

// Siapkan flash sebelum membersihkan sesi agar pesan tetap tampil di halaman tujuan.
set_flash('INFO', 'Anda telah keluar.');
$flash = $_SESSION['flash'] ?? null;

// Hapus semua data sesi (termasuk status login), simpan flash untuk redirect.
$_SESSION = [];
if ($flash) {
    $_SESSION['flash'] = $flash;
}
session_write_close();

header('Location: index.php');
exit;