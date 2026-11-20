<?php
/**
 * logout.php - Keluar dari sesi
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/middleware.php';

session_unset();
session_destroy();

set_flash('INFO', 'Anda telah logout. Sampai jumpa!');
header('Location: login.php');
exit;