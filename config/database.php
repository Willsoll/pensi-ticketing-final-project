<?php
// config/database.php
// Database configuration - credentials from environment

return [
    'driver'   => 'mysql',
    'host'     => $_ENV['DB_HOST'] ?? 'localhost',
    'port'     => (int)($_ENV['DB_PORT'] ?? 3306),
    'database' => $_ENV['DB_NAME'] ?? 'db_tiket_event_pro',
    'username' => $_ENV['DB_USER'] ?? 'root',
    'password' => $_ENV['DB_PASS'] ?? '',
    'charset'  => 'utf8mb4',
    'options'  => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ],
];