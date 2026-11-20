<?php
// src/Services/VoucherService.php
// Voucher validation and quota management

namespace App\Services;

use App\Security\Database;
use App\Validation\InputValidator;

class VoucherService
{
    /**
     * Validate voucher for checkout
     * Returns [isValid, voucherData, errorMessage]
     */
    public static function validate(\PDO $pdo, string $code, int $subtotal): array
    {
        if (empty($code)) {
            return [true, null, '']; // No voucher is valid
        }
        
        $stmt = $pdo->prepare("
            SELECT *
            FROM voucher_promo
            WHERE kode_voucher = ?
            FOR UPDATE
        ");
        $stmt->execute([strtoupper(trim($code))]);
        $voucher = $stmt->fetch();
        
        if (!$voucher) {
            return [false, null, "Kode voucher '$code' tidak ditemukan."];
        }
        
        // Check active
        if (!$voucher['is_active']) {
            return [false, null, "Voucher '$code' tidak aktif."];
        }
        
        // Check expired
        if ($voucher['expired_date'] < date('Y-m-d')) {
            return [false, null, "Voucher '$code' sudah kadaluarsa."];
        }
        
        // Check quota
        if ((int)$voucher['kuota_pakai'] <= 0) {
            return [false, null, "Kuota voucher '$code' sudah habis."];
        }
        
        // Check minimum transaction
        if ($subtotal < (int)$voucher['min_transaksi']) {
            $min = \App\Services\PricingService::formatRupiah((int)$voucher['min_transaksi']);
            return [false, null, "Voucher '$code' membutuhkan minimal transaksi $min."];
        }
        
        return [true, $voucher, ''];
    }
    
    /**
     * Calculate discount from voucher
     */
    public static function calculateDiscount(int $subtotal, array $voucher): int
    {
        if ($voucher['tipe_potongan'] === 'Persen') {
            $discount = (int)floor($subtotal * (int)$voucher['nilai_potongan'] / 100);
        } else {
            $discount = min($subtotal, (int)$voucher['nilai_potongan']);
        }
        
        return max(0, $discount);
    }
    
    /**
     * Reserve voucher quota (lock for reservation)
     * Must be called within a database transaction
     */
    public static function reserveQuota(\PDO $pdo, int $voucherId): bool
    {
        $stmt = $pdo->prepare("
            SELECT kuota_pakai
            FROM voucher_promo
            WHERE id_voucher = ?
            FOR UPDATE
        ");
        $stmt->execute([$voucherId]);
        $voucher = $stmt->fetch();
        
        if (!$voucher || (int)$voucher['kuota_pakai'] <= 0) {
            return false;
        }
        
        $stmt = $pdo->prepare("
            UPDATE voucher_promo
            SET kuota_pakai = kuota_pakai - 1
            WHERE id_voucher = ?
        ");
        $stmt->execute([$voucherId]);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Release voucher quota (on expiry or rejection)
     */
    public static function releaseQuota(\PDO $pdo, int $voucherId): bool
    {
        $stmt = $pdo->prepare("
            UPDATE voucher_promo
            SET kuota_pakai = kuota_pakai + 1
            WHERE id_voucher = ?
        ");
        $stmt->execute([$voucherId]);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Record voucher redemption (prevent double restoration)
     * Must be called within a database transaction
     */
    public static function recordRedemption(\PDO $pdo, int $voucherId, int $transaksiId): bool
    {
        // Check if already recorded
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM voucher_redemptions
            WHERE id_voucher = ? AND id_transaksi = ?
        ");
        $stmt->execute([$voucherId, $transaksiId]);
        if ((int)$stmt->fetchColumn() > 0) {
            return true; // Already recorded
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO voucher_redemptions (id_voucher, id_transaksi)
            VALUES (?, ?)
        ");
        $stmt->execute([$voucherId, $transaksiId]);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Check if voucher redemption already recorded
     */
    public static function isRedeemed(\PDO $pdo, int $voucherId, int $transaksiId): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM voucher_redemptions
            WHERE id_voucher = ? AND id_transaksi = ?
        ");
        $stmt->execute([$voucherId, $transaksiId]);
        return (int)$stmt->fetchColumn() > 0;
    }
    
    /**
     * Get active vouchers for display
     */
    public static function getActiveVouchers(\PDO $pdo): array
    {
        $stmt = $pdo->prepare("
            SELECT kode_voucher, tipe_potongan, nilai_potongan, min_transaksi, kuota_pakai, expired_date
            FROM voucher_promo
            WHERE is_active = 1
            AND kuota_pakai > 0
            AND expired_date >= CURDATE()
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get voucher by code
     */
    public static function getByCode(\PDO $pdo, string $code): ?array
    {
        $stmt = $pdo->prepare("
            SELECT * FROM voucher_promo WHERE kode_voucher = ?
        ");
        $stmt->execute([strtoupper(trim($code))]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Create voucher (admin)
     */
    public static function create(\PDO $pdo, array $data): int
    {
        $stmt = $pdo->prepare("
            INSERT INTO voucher_promo (
                kode_voucher, tipe_potongan, nilai_potongan,
                min_transaksi, kuota_pakai, expired_date, is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            strtoupper($data['kode_voucher']),
            $data['tipe_potongan'],
            (int)$data['nilai_potongan'],
            (int)$data['min_transaksi'],
            (int)$data['kuota_pakai'],
            $data['expired_date'],
            $data['is_active'] ?? 1,
        ]);
        
        return (int)Database::getConnection()->lastInsertId();
    }
    
    /**
     * Update voucher (admin)
     */
    public static function update(\PDO $pdo, int $id, array $data): bool
    {
        $fields = [];
        $values = [];
        
        $allowed = ['kode_voucher', 'tipe_potongan', 'nilai_potongan', 'min_transaksi', 'kuota_pakai', 'expired_date', 'is_active'];
        
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $values[] = $field === 'kode_voucher' ? strtoupper($data[$field]) : $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $values[] = $id;
        
        $stmt = Database::getConnection()->prepare("
            UPDATE voucher_promo SET " . implode(', ', $fields) . " WHERE id_voucher = ?
        ");
        $stmt->execute($values);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Delete voucher (admin)
     */
    public static function delete(\PDO $pdo, int $id): bool
    {
        $stmt = Database::getConnection()->prepare("DELETE FROM voucher_promo WHERE id_voucher = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}