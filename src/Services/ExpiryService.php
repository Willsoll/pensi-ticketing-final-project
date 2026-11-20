<?php
// src/Services/ExpiryService.php
// Scheduled expiry processing for reservations

namespace App\Services;

use App\Security\Database;
use App\Services\BusinessRulesService;
use App\Services\InventoryService;
use App\Services\VoucherService;

class ExpiryService
{
    /**
     * Process all expired reservations
     * Should be called by cron job every minute
     */
    public static function process(): array
    {
        $pdo = Database::getConnection();
        return BusinessRulesService::processExpiredReservations($pdo);
    }
    
    /**
     * Get count of pending expirations (for monitoring)
     */
    public static function getPendingCount(): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM reservations
            WHERE status = 'Active'
            AND batas_bayar_at < NOW()
        ");
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * Get expired reservations details (for reporting)
     */
    public static function getExpiredDetails(int $limit = 100): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT r.*, u.nama as user_nama, u.email, e.judul_event, tt.nama_tier
            FROM reservations r
            JOIN users u ON r.id_user = u.id_user
            JOIN events e ON r.id_event = e.id_event
            JOIN tiket_tier tt ON r.id_tier = tt.id_tier
            WHERE r.status = 'Expired'
            ORDER BY r.converted_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Clean up old expired reservations (older than 30 days)
     * Optional maintenance task
     */
    public static function cleanupOldExpired(int $days = 30): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            DELETE FROM reservations
            WHERE status = 'Expired'
            AND converted_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }
}