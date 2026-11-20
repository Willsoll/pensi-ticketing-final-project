<?php
// src/Security/CsrfToken.php
// CSRF token generation and validation

namespace App\Security;

class CsrfToken
{
    private const TOKEN_LENGTH = 32;
    private const SESSION_KEY = '_csrf_tokens';
    private const MAX_TOKENS = 10;
    
    public static function generate(): string
    {
        \App\Security\SessionManager::start();
        
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        
        $tokens = $_SESSION[self::SESSION_KEY] ?? [];
        $tokens[] = [
            'token' => $token,
            'created' => time(),
        ];
        
        // Keep only recent tokens
        if (count($tokens) > self::MAX_TOKENS) {
            $tokens = array_slice($tokens, -self::MAX_TOKENS);
        }
        
        $_SESSION[self::SESSION_KEY] = $tokens;
        
        return $token;
    }
    
    public static function validate(?string $token): bool
    {
        if (empty($token)) {
            return false;
        }
        
        \App\Security\SessionManager::start();
        
        $tokens = $_SESSION[self::SESSION_KEY] ?? [];
        
        foreach ($tokens as $index => $stored) {
            if (hash_equals($stored['token'], $token)) {
                // Token found - remove it (one-time use)
                unset($tokens[$index]);
                $_SESSION[self::SESSION_KEY] = array_values($tokens);
                return true;
            }
        }
        
        return false;
    }
    
    public static function getToken(): string
    {
        return self::generate();
    }
    
    public static function getTokenField(): string
    {
        $token = self::generate();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
    
    public static function getTokenMeta(): string
    {
        $token = self::generate();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}