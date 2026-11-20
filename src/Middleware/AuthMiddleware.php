<?php
// src/Middleware/AuthMiddleware.php
// Authentication middleware

namespace App\Middleware;

use App\Security\SessionManager;
use App\Security\Database;

class AuthMiddleware
{
    public static function handle(): void
    {
        SessionManager::start();
        
        if (!self::isAuthenticated()) {
            self::redirectToLogin();
        }
        
        // Load user into session if not already loaded
        if (!SessionManager::has('user')) {
            self::loadUser();
        }
    }
    
    public static function isAuthenticated(): bool
    {
        return SessionManager::has('user_id');
    }
    
    public static function user(): ?array
    {
        if (!self::isAuthenticated()) {
            return null;
        }
        
        if (SessionManager::has('user')) {
            return SessionManager::get('user');
        }
        
        return self::loadUser();
    }
    
    public static function userId(): ?int
    {
        return SessionManager::get('user_id');
    }
    
    public static function role(): ?string
    {
        return SessionManager::get('user_role');
    }
    
    public static function hasRole(string|array $roles): bool
    {
        $userRole = self::role();
        if ($userRole === null) {
            return false;
        }
        
        if (is_array($roles)) {
            return in_array($userRole, $roles, true);
        }
        
        return $userRole === $roles;
    }
    
    public static function login(array $user): void
    {
        SessionManager::start();
        session_regenerate_id(true);
        
        SessionManager::set('user_id', $user['id_user']);
        SessionManager::set('user_role', $user['role']);
        SessionManager::set('user', [
            'id_user' => $user['id_user'],
            'nama' => $user['nama'],
            'email' => $user['email'],
            'role' => $user['role'],
        ]);
    }
    
    public static function logout(): void
    {
        SessionManager::destroy();
    }
    
    private static function loadUser(): ?array
    {
        $userId = SessionManager::get('user_id');
        if (!$userId) {
            return null;
        }
        
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id_user, nama, email, role FROM users WHERE id_user = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if ($user) {
            SessionManager::set('user', [
                'id_user' => $user['id_user'],
                'nama' => $user['nama'],
                'email' => $user['email'],
                'role' => $user['role'],
            ]);
            return $user;
        }
        
        // User not found, logout
        self::logout();
        return null;
    }
    
    private static function redirectToLogin(): void
    {
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '/';
        // Don't redirect if already on login/register page
        if (strpos($currentUrl, '/login') === 0 || strpos($currentUrl, '/register') === 0) {
            return;
        }
        
        header('Location: /login?redirect=' . urlencode($currentUrl));
        exit;
    }
    
    public static function requireAuth(): void
    {
        if (!self::isAuthenticated()) {
            self::redirectToLogin();
        }
    }
    
    public static function requireRole(string|array $roles): void
    {
        self::requireAuth();
        
        if (!self::hasRole($roles)) {
            http_response_code(403);
            self::renderForbidden();
            exit;
        }
    }
    
    private static function renderForbidden(): void
    {
        $user = self::user();
        $role = $user['role'] ?? 'unknown';
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>403 - Akses Ditolak</title>
            <style>
                body { font-family: system-ui, sans-serif; background: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
                .card { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-width: 400px; text-align: center; }
                h1 { color: #f97316; margin-bottom: 1rem; }
                p { color: #64748b; line-height: 1.6; }
                a { color: #6d28d9; text-decoration: none; }
                a:hover { text-decoration: underline; }
            </style>
        </head>
        <body>
            <div class="card">
                <h1>403 - Akses Ditolak</h1>
                <p>Anda tidak memiliki izin untuk mengakses halaman ini.</p>
                <p>Role Anda: <strong><?= htmlspecialchars($role) ?></strong></p>
                <p><a href="/">Kembali ke Beranda</a></p>
            </div>
        </body>
        </html>
        <?php
    }
}