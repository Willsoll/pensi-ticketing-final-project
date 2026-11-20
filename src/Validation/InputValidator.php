<?php
// src/Validation/InputValidator.php
// Server-side input validation

namespace App\Validation;

class InputValidator
{
    /**
     * Validate email format
     */
    public static function email(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate required field
     */
    public static function required(mixed $value): bool
    {
        if (is_string($value)) {
            return $value !== '';
        }
        if (is_array($value)) {
            return count($value) > 0;
        }
        return $value !== null;
    }
    
    /**
     * Validate string length
     */
    public static function length(string $value, int $min = 1, int $max = 255): bool
    {
        $len = mb_strlen($value, 'UTF-8');
        return $len >= $min && $len <= $max;
    }
    
    /**
     * Validate integer range
     */
    public static function integer(mixed $value, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): bool
    {
        if (!filter_var($value, FILTER_VALIDATE_INT)) {
            return false;
        }
        $int = (int)$value;
        return $int >= $min && $int <= $max;
    }
    
    /**
     * Validate positive integer
     */
    public static function positiveInt(mixed $value): bool
    {
        return self::integer($value, 1, PHP_INT_MAX);
    }
    
    /**
     * Validate non-negative integer
     */
    public static function nonNegativeInt(mixed $value): bool
    {
        return self::integer($value, 0, PHP_INT_MAX);
    }
    
    /**
     * Validate numeric (float) range
     */
    public static function numeric(mixed $value, float $min = -INF, float $max = INF): bool
    {
        if (!is_numeric($value)) {
            return false;
        }
        $num = (float)$value;
        return $num >= $min && $num <= $max;
    }
    
    /**
     * Validate date format (Y-m-d)
     */
    public static function date(string $value): bool
    {
        $date = date_create_from_format('Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
    
    /**
     * Validate datetime format (Y-m-d H:i:s)
     */
    public static function datetime(string $value): bool
    {
        $date = date_create_from_format('Y-m-d H:i:s', $value);
        return $date !== false && $date->format('Y-m-d H:i:s') === $value;
    }
    
    /**
     * Validate Indonesian phone number
     */
    public static function phoneID(string $value): bool
    {
        $clean = preg_replace('/[\s\-\(\)]/', '', $value);
        return preg_match('/^(?:\+62|62|0)8[1-9]\d{7,11}$/', $clean) === 1;
    }
    
    /**
     * Validate NIK (Indonesian ID - 16 digits)
     */
    public static function nik(string $value): bool
    {
        return preg_match('/^[0-9]{16}$/', $value) === 1;
    }
    
    /**
     * Validate NIS (school ID - 5-20 digits)
     */
    public static function nis(string $value): bool
    {
        return preg_match('/^[0-9]{5,20}$/', $value) === 1;
    }
    
    /**
     * Validate NIK or NIS based on user type
     */
    public static function nikOrNis(string $value, string $type): bool
    {
        if ($type === 'Internal') {
            return self::nis($value);
        }
        return self::nik($value);
    }
    
    /**
     * Validate alphanumeric + underscore
     */
    public static function alphanumericUnderscore(string $value): bool
    {
        return preg_match('/^[a-zA-Z0-9_]+$/', $value) === 1;
    }
    
    /**
     * Validate slug (lowercase alphanumeric + hyphen)
     */
    public static function slug(string $value): bool
    {
        return preg_match('/^[a-z0-9-]+$/', $value) === 1;
    }
    
    /**
     * Validate in array
     */
    public static function inArray(mixed $value, array $allowed, bool $strict = false): bool
    {
        return in_array($value, $allowed, $strict);
    }
    
    /**
     * Validate file upload
     */
    public static function fileUpload(array $file, array $allowedMimes, int $maxSize): array
    {
        $errors = [];
        
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload gagal atau tidak ada file.';
            return $errors;
        }
        
        if ($file['size'] > $maxSize) {
            $errors[] = 'Ukuran file melebihi batas maksimal.';
        }
        
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        
        if (!in_array($mime, $allowedMimes, true)) {
            $errors[] = 'Tipe file tidak diizinkan.';
        }
        
        // Check file extension matches MIME
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExts = [];
        foreach ($allowedMimes as $mime) {
            $exts = self::mimeToExtensions($mime);
            $allowedExts = array_merge($allowedExts, $exts);
        }
        if (!in_array($ext, $allowedExts, true)) {
            $errors[] = 'Ekstensi file tidak sesuai dengan tipe MIME.';
        }
        
        return $errors;
    }
    
    private static function mimeToExtensions(string $mime): array
    {
        $map = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/webp' => ['webp'],
        ];
        return $map[$mime] ?? [];
    }
    
    /**
     * Sanitize string for safe output
     */
    public static function sanitizeString(string $value, int $maxLength = 1000): string
    {
        $value = trim($value);
        $value = mb_substr($value, 0, $maxLength, 'UTF-8');
        return $value;
    }
    
    /**
     * Validate monetary amount (rupiah as integer)
     */
    public static function monetary(int $value, int $min = 0): bool
    {
        return $value >= $min;
    }
}