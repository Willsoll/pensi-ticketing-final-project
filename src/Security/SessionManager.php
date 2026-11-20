<?php
// src/Security/SessionManager.php
// Secure session handling

namespace App\Security;

class SessionManager
{
    private static bool $started = false;
    
    public static function start(): void
    {
        if (self::$started) {
            return;
        }
        
        $appConfig = require __DIR__ . '/../../config/app.php';
        $sessionConfig = $appConfig['session'];
        $securityConfig = require __DIR__ . '/../../config/security.php';
        $cookieConfig = $securityConfig['session_cookie'];
        
        // Set session cookie parameters BEFORE session_start()
        session_set_cookie_params([
            'lifetime' => $sessionConfig['lifetime'] * 60,
            'path' => '/',
            'domain' => $_ENV['SESSION_DOMAIN'] ?? '',
            'secure' => $cookieConfig['secure'],
            'httponly' => $cookieConfig['httponly'],
            'samesite' => $cookieConfig['samesite'],
        ]);
        
        // Use strict mode
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        
        if ($cookieConfig['secure']) {
            ini_set('session.cookie_secure', '1');
        }
        
        session_start();
        self::$started = true;
        
        // Regenerate ID periodically for security
        if (!isset($_SESSION['__created'])) {
            $_SESSION['__created'] = time();
        } elseif (time() - $_SESSION['__created'] > 3600) { // 1 hour
            session_regenerate_id(true);
            $_SESSION['__created'] = time();
        }
        
        // Check idle timeout
        $idleTimeout = ($appConfig['session']['idle_timeout'] ?? 30) * 60;
        if (isset($_SESSION['__last_activity']) && (time() - $_SESSION['__last_activity'] > $idleTimeout)) {
            self::destroy();
            self::start();
            return;
        }
        $_SESSION['__last_activity'] = time();
    }
    
    public static function regenerateId(bool $deleteOld = true): void
    {
        if (!self::$started) self::start();
        session_regenerate_id($deleteOld);
    }
    
    public static function set(string $key, mixed $value): void
    {
        if (!self::$started) self::start();
        $_SESSION[$key] = $value;
    }
    
    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::$started) self::start();
        return $_SESSION[$key] ?? $default;
    }
    
    public static function has(string $key): bool
    {
        if (!self::$started) self::start();
        return isset($_SESSION[$key]);
    }
    
    public static function forget(string $key): void
    {
        if (!self::$started) self::start();
        unset($_SESSION[$key]);
    }
    
    public static function flash(string $key, string $message): void
    {
        if (!self::$started) self::start();
        $_SESSION['_flash'][$key] = $message;
    }
    
    public static function getFlash(string $key): ?string
    {
        if (!self::$started) self::start();
        $flash = $_SESSION['_flash'][$key] ?? null;
        if ($flash !== null) {
            unset($_SESSION['_flash'][$key]);
        }
        return $flash;
    }
    
    public static function destroy(): void
    {
        if (!self::$started) self::start();
        
        $_SESSION = [];
        
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        
        session_destroy();
        self::$started = false;
    }
    
    public static function getId(): ?string
    {
        if (!self::$started) self::start();
        return session_id();
    }
    
    public static function isStarted(): bool
    {
        return self::$started;
    }
}