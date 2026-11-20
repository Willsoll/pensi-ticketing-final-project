<?php
// config/security.php
// Security configuration - FIXED

return [
    // Password hashing
    'password' => [
        'algo' => PASSWORD_BCRYPT,
        'cost' => 12,
    ],

    // CSP (Content Security Policy) - FIXED: No unsafe-inline
    'csp' => [
        'default-src' => ["'self'"],
        'script-src'  => ["'self'"],
        'style-src'   => ["'self'"],
        'img-src'     => ["'self'", 'data:', 'blob:'],
        'font-src'    => ["'self'"],
        'connect-src' => ["'self'"],
        'frame-ancestors' => ["'none'"],
        'form-action' => ["'self'"],
        'base-uri'    => ["'self'"],
    ],

    // Security headers
    'headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options'        => 'DENY',
        'X-XSS-Protection'       => '1; mode=block',
        'Referrer-Policy'        => 'strict-origin-when-cross-origin',
        'Permissions-Policy'     => 'geolocation=(), microphone=(), camera=()',
    ],

    // HSTS - enabled via env var
    'hsts' => [
        'enabled' => filter_var($_ENV['HSTS_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
        'max_age' => 31536000,
        'include_subdomains' => true,
        'preload' => true,
    ],

    // Session cookie settings - secure via env var
    'session_cookie' => [
        'secure' => filter_var($_ENV['SESSION_SECURE'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
        'httponly' => true,
        'samesite' => 'Strict',
    ],

    // File upload security
    'uploads' => [
        'scan_mime' => true,
        'strip_exif' => true,
        'randomize_names' => true,
        'blocked_extensions' => ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'php8', 'phar', 'exe', 'bat', 'sh', 'pl', 'cgi', 'svg', 'html', 'htm', 'js'],
    ],

    // Rate limiting
    'rate_limit' => [
        'storage' => 'file', // or 'redis' in production
        'lockout_duration' => 900, // 15 minutes
    ],

    // Audit logging
    'audit' => [
        'log_all_crud' => true,
        'log_auth' => true,
        'log_payment' => true,
        'log_gate' => true,
        'mask_sensitive' => ['nik', 'nik_peserta', 'password', 'bukti_bayar'],
    ],
];