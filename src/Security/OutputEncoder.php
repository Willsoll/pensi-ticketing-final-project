<?php
// src/Security/OutputEncoder.php
// Context-aware output encoding

namespace App\Security;

class OutputEncoder
{
    /**
     * Encode for HTML body context
     */
    public static function html(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    
    /**
     * Encode for HTML attribute context (double quotes)
     */
    public static function attr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    
    /**
     * Encode for HTML attribute context (single quotes)
     */
    public static function attrSingle(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    
    /**
     * Encode for JavaScript string context
     */
    public static function js(string $value): string
    {
        return json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Encode for URL context
     */
    public static function url(string $value): string
    {
        return rawurlencode($value);
    }
    
    /**
     * Encode for CSS context
     */
    public static function css(string $value): string
    {
        // Basic CSS identifier/value escaping
        return preg_replace('/([^a-zA-Z0-9_-])/', '\\\\$1', $value);
    }
    
    /**
     * Encode for HTML data-* attribute
     */
    public static function dataAttr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    
    /**
     * Safe truncate for display
     */
    public static function truncate(string $value, int $length = 100, string $suffix = '…'): string
    {
        if (mb_strlen($value, 'UTF-8') <= $length) {
            return self::html($value);
        }
        return self::html(mb_substr($value, 0, $length - mb_strlen($suffix, 'UTF-8'), 'UTF-8')) . $suffix;
    }
    
    /**
     * Mask sensitive data for display (NIK, etc.)
     */
    public static function mask(string $value, int $visibleStart = 4, int $visibleEnd = 4, string $maskChar = '*'): string
    {
        $len = mb_strlen($value, 'UTF-8');
        if ($len <= $visibleStart + $visibleEnd) {
            return str_repeat($maskChar, $len);
        }
        $start = mb_substr($value, 0, $visibleStart, 'UTF-8');
        $end = mb_substr($value, -$visibleEnd, $visibleEnd, 'UTF-8');
        $middle = str_repeat($maskChar, $len - $visibleStart - $visibleEnd);
        return $start . $middle . $end;
    }
    
    /**
     * Format rupiah for display
     */
    public static function rupiah(int $value): string
    {
        return 'Rp ' . number_format($value, 0, ',', '.');
    }
    
    /**
     * Format date for display
     */
    public static function date(string $date, string $format = 'd-m-Y'): string
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $date);
        if ($dt === false) {
            return self::html($date);
        }
        return $dt->format($format);
    }
    
    /**
     * Format datetime for display
     */
    public static function datetime(string $datetime, string $format = 'd-m-Y H:i'): string
    {
        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
        if ($dt === false) {
            return self::html($datetime);
        }
        return $dt->format($format);
    }
}