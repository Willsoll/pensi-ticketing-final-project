<?php
// config/app.php
// Application configuration

return [
    'name'        => 'Pensi Ticketing Platform',
    'version'     => '1.0.0',
    'env'         => $_ENV['APP_ENV'] ?? 'development',
    'debug'       => filter_var($_ENV['APP_DEBUG'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
    'timezone'    => 'Asia/Jakarta',
    'locale'      => 'id',
    'url'         => $_ENV['APP_URL'] ?? 'http://localhost',
    'base_path'   => dirname(__DIR__),
    'public_path' => dirname(__DIR__) . '/public',
    'storage_path'=> dirname(__DIR__) . '/storage',
    'logs_path'   => dirname(__DIR__) . '/storage/logs',
    'uploads_path'=> dirname(__DIR__) . '/storage/uploads',
    'private_path'=> dirname(__DIR__) . '/storage/private',
    
    // Pagination
    'per_page' => 15,
    
    // Session
    'session' => [
        'lifetime' => 120,           // minutes
        'idle_timeout' => 30,        // minutes
        'secure'   => false,         // true in production with HTTPS
        'httponly' => true,
        'samesite' => 'Strict',
    ],
    
    // CSRF
    'csrf' => [
        'token_name' => 'csrf_token',
        'header_name' => 'X-CSRF-Token',
    ],
    
    // Rate limiting
    'rate_limit' => [
        'login' => ['attempts' => 5, 'window' => 300], // 5 attempts per 5 min
        'api'   => ['attempts' => 60, 'window' => 60],
    ],
    
    // Business rules
    'booking' => [
        'max_tickets_per_event' => 5,
        'payment_timeout_minutes' => 15,
        'service_fee' => 0, // PAKET 7 doesn't have service fee
    ],
    
    // File uploads
    'uploads' => [
        'payment_proof' => [
            'max_size' => 2 * 1024 * 1024, // 2MB
            'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        ],
        'event_banner' => [
            'max_size' => 5 * 1024 * 1024, // 5MB
            'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        ],
    ],
    
    // QR Code
    'qr_code' => [
        'size' => 200,
        'margin' => 2,
        'error_correction' => 'M',
    ],
];