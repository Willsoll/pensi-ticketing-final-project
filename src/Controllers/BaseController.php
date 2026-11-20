<?php
// src/Controllers/BaseController.php
// Base controller with common functionality

namespace App\Controllers;

use App\Security\Database;
use App\Security\SessionManager;
use App\Security\OutputEncoder;
use App\Security\CsrfToken;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;

abstract class BaseController
{
    protected \PDO $pdo;
    protected array $appConfig;
    protected array $securityConfig;
    
    public function __construct()
    {
        $this->pdo = Database::getConnection();
        $this->appConfig = require __DIR__ . '/../../config/app.php';
        $this->securityConfig = require __DIR__ . '/../../config/security.php';
        
        SessionManager::start();
    }
    
    /**
     * Render a view template
     */
    protected function view(string $template, array $data = [], int $status = 200): void
    {
        http_response_code($status);
        
        // Make data available to template
        $data['app'] = $this->appConfig;
        $data['csrf_token'] = CsrfToken::generate();
        $data['csrf_field'] = CsrfToken::getTokenField();
        $data['csrf_meta'] = CsrfToken::getTokenMeta();
        $data['current_user'] = AuthMiddleware::user();
        $data['flash'] = $this->getFlashes();
        
        // Make helpers available
        $data['e'] = [OutputEncoder::class, 'html'];
        $data['attr'] = [OutputEncoder::class, 'attr'];
        $data['rupiah'] = [OutputEncoder::class, 'rupiah'];
        $data['date'] = [OutputEncoder::class, 'date'];
        $data['datetime'] = [OutputEncoder::class, 'datetime'];
        $data['mask'] = [OutputEncoder::class, 'mask'];
        
        extract($data);
        
        $templatePath = __DIR__ . '/../../views/' . $template . '.php';
        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Template not found: {$template}");
        }
        
        include $templatePath;
    }
    
    /**
     * JSON response
     */
    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Redirect response
     */
    protected function redirect(string $url, int $status = 302): void
    {
        http_response_code($status);
        header('Location: ' . $url);
        exit;
    }
    
    /**
     * Back redirect with flash
     */
    protected function back(string $message = '', string $type = 'info'): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        if ($message) {
            SessionManager::flash($type, $message);
        }
        $this->redirect($referer);
    }
    
    /**
     * Get flash messages
     */
    protected function getFlashes(): array
    {
        $types = ['success', 'error', 'warning', 'info'];
        $flashes = [];
        
        foreach ($types as $type) {
            $msg = SessionManager::getFlash($type);
            if ($msg) {
                $flashes[$type] = $msg;
            }
        }
        
        return $flashes;
    }
    
    /**
     * Validate request input
     */
    protected function validate(array $rules): array
    {
        $errors = [];
        $input = $_POST;
        
        foreach ($rules as $field => $rule) {
            $value = $input[$field] ?? null;
            
            // Required
            if (str_contains($rule, 'required') && empty($value)) {
                $errors[$field] = ucfirst($field) . ' wajib diisi.';
                continue;
            }
            
            if (empty($value)) {
                continue; // Skip other validations for empty optional fields
            }
            
            // Email
            if (str_contains($rule, 'email') && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = 'Format email tidak valid.';
            }
            
            // Min length
            if (preg_match('/min:(\d+)/', $rule, $matches) && mb_strlen($value) < (int)$matches[1]) {
                $errors[$field] = ucfirst($field) . ' minimal ' . $matches[1] . ' karakter.';
            }
            
            // Max length
            if (preg_match('/max:(\d+)/', $rule, $matches) && mb_strlen($value) > (int)$matches[1]) {
                $errors[$field] = ucfirst($field) . ' maksimal ' . $matches[1] . ' karakter.';
            }
            
            // Numeric
            if (str_contains($rule, 'numeric') && !is_numeric($value)) {
                $errors[$field] = ucfirst($field) . ' harus berupa angka.';
            }
            
            // Integer
            if (str_contains($rule, 'integer') && !filter_var($value, FILTER_VALIDATE_INT)) {
                $errors[$field] = ucfirst($field) . ' harus berupa bilangan bulat.';
            }
            
            // In array
            if (preg_match('/in:([^,]+)/', $rule, $matches)) {
                $allowed = explode(',', $matches[1]);
                if (!in_array($value, $allowed, true)) {
                    $errors[$field] = ucfirst($field) . ' tidak valid.';
                }
            }
            
            // Confirmed (password confirmation)
            if (str_contains($rule, 'confirmed') && ($input[$field . '_confirmation'] ?? '') !== $value) {
                $errors[$field] = ucfirst($field) . ' dan konfirmasi tidak cocok.';
            }
        }
        
        return $errors;
    }
    
    /**
     * Get sanitized input
     */
    protected function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }
    
    /**
     * Get all input
     */
    protected function all(): array
    {
        return array_merge($_GET, $_POST);
    }
    
    /**
     * Check if request is AJAX
     */
    protected function isAjax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) 
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Get current user
     */
    protected function user(): ?array
    {
        return \App\Middleware\AuthMiddleware::user();
    }
    
    /**
     * Check if user has role
     */
    protected function hasRole(string|array $roles): bool
    {
        return \App\Middleware\AuthMiddleware::hasRole($roles);
    }
    
    /**
     * Require authentication
     */
    protected function auth(): void
    {
        \App\Middleware\AuthMiddleware::requireAuth();
    }
    
    /**
     * Require specific role
     */
    protected function authorize(string|array $roles): void
    {
        \App\Middleware\AuthMiddleware::requireRole($roles);
    }
}