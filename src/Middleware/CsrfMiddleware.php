<?php
// src/Middleware/CsrfMiddleware.php
// CSRF protection middleware - FIXED

namespace App\Middleware;

use App\Security\CsrfToken;
use App\Security\SessionManager;

class CsrfMiddleware
{
    private const EXEMPT_METHODS = ['GET', 'HEAD', 'OPTIONS'];
    // Only truly safe routes that don't need CSRF (static assets, etc.)
    private const EXEMPT_ROUTES = [
        '/logout',  // logout is safe to CSRF (it destroys session)
    ];

    public static function handle(): void
    {
        // Only validate state-changing requests
        if (in_array($_SERVER['REQUEST_METHOD'], self::EXEMPT_METHODS, true)) {
            return;
        }

        // Check if route is exempt - EXACT MATCH
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (in_array($path, self::EXEMPT_ROUTES, true)) {
            return;
        }

        // Validate CSRF token
        $token = self::getTokenFromRequest();

        if (!$token || !CsrfToken::validate($token)) {
            SessionManager::start();
            SessionManager::flash('error', 'Token keamanan tidak valid. Silakan coba lagi.');

            // Return JSON for AJAX requests
            if (self::isAjaxRequest()) {
                http_response_code(419);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'CSRF token invalid']);
                exit;
            }

            // Redirect to fixed safe location (NOT user-controlled referer)
            header('Location: ' . $_SERVER['HTTP_REFERER'] ?? '/');
            exit;
        }
    }

    private static function getTokenFromRequest(): ?string
    {
        // Check POST body
        if (isset($_POST['csrf_token'])) {
            return $_POST['csrf_token'];
        }

        // Check headers (for AJAX)
        $headers = getallheaders();
        if (isset($headers['X-CSRF-Token'])) {
            return $headers['X-CSRF-Token'];
        }
        if (isset($headers['X-Csrf-Token'])) {
            return $headers['X-Csrf-Token'];
        }

        return null;
    }

    private static function isAjaxRequest(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    public static function getToken(): string
    {
        return \App\Security\CsrfToken::generate();
    }

    public static function tokenField(): string
    {
        return CsrfToken::getTokenField();
    }

    public static function tokenMeta(): string
    {
        return CsrfToken::getTokenMeta();
    }

    public static function validate(): bool
    {
        if (in_array($_SERVER['REQUEST_METHOD'], self::EXEMPT_METHODS, true)) {
            return true;
        }

        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (in_array($path, self::EXEMPT_ROUTES, true)) {
            return true;
        }

        $token = self::getTokenFromRequest();
        return $token && CsrfToken::validate($token);
    }
}