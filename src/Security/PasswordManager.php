<?php
// src/Security/PasswordManager.php
// Password hashing and verification

namespace App\Security;

class PasswordManager
{
    private const DEFAULT_COST = 12;
    private const ALGO = PASSWORD_BCRYPT;
    
    /**
     * Hash a password
     */
    public static function hash(string $password, int $cost = self::DEFAULT_COST): string
    {
        if (empty($password)) {
            throw new \InvalidArgumentException('Password cannot be empty');
        }
        
        if (strlen($password) > 72) {
            // BCrypt truncates at 72 chars, pre-hash with SHA-256
            $password = hash('sha256', $password, true);
        }
        
        $options = ['cost' => $cost];
        
        $hash = password_hash($password, self::ALGO, $options);
        
        if ($hash === false) {
            throw new \RuntimeException('Password hashing failed');
        }
        
        return $hash;
    }
    
    /**
     * Verify a password against a hash
     */
    public static function verify(string $password, string $hash): bool
    {
        if (empty($password) || empty($hash)) {
            return false;
        }
        
        // Handle pre-hashed long passwords
        if (strlen($password) > 72) {
            $password = hash('sha256', $password, true);
        }
        
        return password_verify($password, $hash);
    }
    
    /**
     * Check if hash needs rehashing
     */
    public static function needsRehash(string $hash, int $cost = self::DEFAULT_COST): bool
    {
        return password_needs_rehash($hash, self::ALGO, ['cost' => $cost]);
    }
    
    /**
     * Rehash password if needed (call after successful login)
     */
    public static function rehashIfNeeded(string $password, string &$hash, int $cost = self::DEFAULT_COST): bool
    {
        if (self::needsRehash($hash, $cost)) {
            $hash = self::hash($password, $cost);
            return true;
        }
        return false;
    }
    
    /**
     * Validate password strength
     */
    public static function validateStrength(string $password): array
    {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'Password minimal 8 karakter.';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password harus mengandung minimal 1 huruf besar.';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password harus mengandung minimal 1 huruf kecil.';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password harus mengandung minimal 1 angka.';
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password harus mengandung minimal 1 simbol.';
        }
        
        // Check common passwords
        $common = ['password', '12345678', 'qwerty123', 'admin123', 'password123'];
        if (in_array(strtolower($password), $common)) {
            $errors[] = 'Password terlalu umum, gunakan yang lebih unik.';
        }
        
        return $errors;
    }
    
    /**
     * Generate secure random password
     */
    public static function generate(int $length = 16): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        $bytes = random_bytes($length);
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[$bytes[$i] % strlen($chars)];
        }
        
        // Ensure at least one of each required type
        if (!preg_match('/[A-Z]/', $password)) $password[0] = chr(rand(65, 90));
        if (!preg_match('/[a-z]/', $password)) $password[1] = chr(rand(97, 122));
        if (!preg_match('/[0-9]/', $password)) $password[2] = chr(rand(48, 57));
        if (!preg_match('/[^A-Za-z0-9]/', $password)) $password[3] = '!@#$%^&*'[rand(0, 7)];
        
        return $password;
    }
}