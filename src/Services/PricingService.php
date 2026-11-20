<?php
// src/Services/PricingService.php
// Financial calculations (server-side only)

namespace App\Services;

class PricingService
{
    /**
     * Calculate subtotal
     */
    public static function subtotal(int $price, int $quantity): int
    {
        return $price * $quantity;
    }
    
    /**
     * Calculate voucher discount
     */
    public static function voucherDiscount(
        int $subtotal,
        string $voucherType,
        int $voucherValue,
        int $minTransaction
    ): int {
        if ($subtotal < $minTransaction) {
            return 0;
        }
        
        if ($voucherType === 'Persen') {
            $discount = (int)floor($subtotal * $voucherValue / 100);
        } else {
            $discount = min($subtotal, $voucherValue);
        }
        
        return max(0, $discount);
    }
    
    /**
     * Calculate service fee (PAKET 7 doesn't have service fee, but keeping for flexibility)
     */
    public static function serviceFee(int $subtotalAfterDiscount, int $fee = 0): int
    {
        return $fee;
    }
    
    /**
     * Calculate total payment
     */
    public static function total(int $subtotal, int $discount, int $serviceFee = 0): int
    {
        return max(0, $subtotal - $discount) + $serviceFee;
    }
    
    /**
     * Full calculation breakdown
     */
    public static function calculate(
        int $price,
        int $quantity,
        ?array $voucher = null,
        int $serviceFee = 0
    ): array {
        $subtotal = self::subtotal($price, $quantity);
        $discount = 0;
        
        if ($voucher !== null) {
            $discount = self::voucherDiscount(
                $subtotal,
                $voucher['tipe_potongan'],
                $voucher['nilai_potongan'],
                $voucher['min_transaksi']
            );
        }
        
        $total = self::total($subtotal, $discount, $serviceFee);
        
        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'service_fee' => $serviceFee,
            'total' => $total,
        ];
    }
    
    /**
     * Format rupiah for display
     */
    public static function formatRupiah(int $value): string
    {
        return 'Rp ' . number_format($value, 0, ',', '.');
    }
}