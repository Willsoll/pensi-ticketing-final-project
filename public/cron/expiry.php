#!/usr/bin/env php
<?php
/**
 * Cron script for processing expired reservations
 * Run every minute via cron: * * * * * /path/to/pensi-ticketing/public/cron/expiry.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Load environment
$envPath = __DIR__ . '/../../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
        putenv(trim($key) . '=' . trim($value));
    }
}

// Load configs
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';

// Initialize
\App\Security\SessionManager::start();
\App\Security\Database::getConnection();

use App\Services\ExpiryService;
use App\Security\Database;

try {
    echo "[" . date('Y-m-d H:i:s') . "] Starting expiry processing...\n";
    
    $result = \App\Services\ExpiryService::process();
    
    echo "[" . date('Y-m-d H:i:s') . "] Processed: {$result['processed']}, Expired: {$result['expired']}\n";
    
    if (!empty($result['errors'])) {
        foreach ($result['errors'] as $error) {
            error_log("Expiry error: $error");
            echo "ERROR: $error\n";
        }
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Expiry processing completed.\n";
    
} catch (\Throwable $e) {
    error_log("Expiry cron failed: " . $e->getMessage());
    echo "FATAL: " . $e->getMessage() . "\n";
    exit(1);
}