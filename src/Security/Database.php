<?php
// src/Security/Database.php
// Centralized database connection

namespace App\Security;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;
    
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $config = require __DIR__ . '/../../config/database.php';
            
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset']
            );
            
            try {
                self::$instance = new PDO($dsn, $config['username'], $config['password'], $config['options']);
            } catch (PDOException $e) {
                if (self::isDevelopment()) {
                    throw new PDOException('Database connection failed: ' . $e->getMessage(), (int)$e->getCode(), $e);
                }
                error_log('Database connection failed: ' . $e->getMessage());
                throw new PDOException('Database connection failed', 500);
            }
        }
        
        return self::$instance;
    }
    
    public static function close(): void
    {
        self::$instance = null;
    }
    
    private static function isDevelopment(): bool
    {
        return ($_ENV['APP_ENV'] ?? 'development') === 'development';
    }
    
    // Prevent cloning
    private function __clone() {}
    public function __wakeup() {}
}