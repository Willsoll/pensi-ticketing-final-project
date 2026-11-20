<?php
// src/Services/InventoryService.php
// Atomic stock and seat management

namespace App\Services;

use App\Security\Database;
use App\Validation\InputValidator;

class InventoryService
{
    /**
     * Lock stock for reservation (15-minute booking window)
     * Must be called within a database transaction
     */
    public static function lockStock(\PDO $pdo, int $tierId, int $quantity): bool
    {
        if ($quantity <= 0) {
            return false;
        }
        
        $stmt = $pdo->prepare("
            SELECT kuota_tersedia, kuota_locked, kuota_total
            FROM tiket_tier
            WHERE id_tier = ?
            FOR UPDATE
        ");
        $stmt->execute([$tierId]);
        $tier = $stmt->fetch();
        
        if (!$tier) {
            return false;
        }
        
        if ((int)$tier['kuota_tersedia'] < $quantity) {
            return false;
        }
        
        $newTersedia = (int)$tier['kuota_tersedia'] - $quantity;
        $newLocked = (int)$tier['kuota_locked'] + $quantity;
        
        $stmt = $pdo->prepare("
            UPDATE tiket_tier
            SET kuota_tersedia = ?, kuota_locked = ?
            WHERE id_tier = ?
        ");
        $stmt->execute([$newTersedia, $newLocked, $tierId]);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Release locked stock (on expiry or rejection)
     * Must be called within a database transaction
     */
    public static function releaseStock(\PDO $pdo, int $tierId, int $quantity): bool
    {
        if ($quantity <= 0) {
            return false;
        }
        
        $stmt = $pdo->prepare("
            SELECT kuota_tersedia, kuota_locked
            FROM tiket_tier
            WHERE id_tier = ?
            FOR UPDATE
        ");
        $stmt->execute([$tierId]);
        $tier = $stmt->fetch();
        
        if (!$tier) {
            return false;
        }
        
        $newTersedia = (int)$tier['kuota_tersedia'] + $quantity;
        $newLocked = max(0, (int)$tier['kuota_locked'] - $quantity);
        
        $stmt = $pdo->prepare("
            UPDATE tiket_tier
            SET kuota_tersedia = ?, kuota_locked = ?
            WHERE id_tier = ?
        ");
        $stmt->execute([$newTersedia, $newLocked, $tierId]);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Confirm locked stock (on payment approval)
     * Decrements kuota_locked (already deducted from tersedia)
     */
    public static function confirmStock(\PDO $pdo, int $tierId, int $quantity): bool
    {
        if ($quantity <= 0) {
            return false;
        }
        
        $stmt = $pdo->prepare("
            SELECT kuota_locked
            FROM tiket_tier
            WHERE id_tier = ?
            FOR UPDATE
        ");
        $stmt->execute([$tierId]);
        $tier = $stmt->fetch();
        
        if (!$tier) {
            return false;
        }
        
        $newLocked = max(0, (int)$tier['kuota_locked'] - $quantity);
        
        $stmt = $pdo->prepare("
            UPDATE tiket_tier
            SET kuota_locked = ?
            WHERE id_tier = ?
        ");
        $stmt->execute([$newLocked, $tierId]);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Lock specific seats for reservation
     * Must be called within a database transaction
     */
    public static function lockSeats(\PDO $pdo, array $seatIds): bool
    {
        if (empty($seatIds)) {
            return true;
        }
        
        $placeholders = implode(',', array_fill(0, count($seatIds), '?'));
        
        // Check all seats are available
        $stmt = $pdo->prepare("
            SELECT id_kursi, nomor_kursi, status_kursi
            FROM kursi_event
            WHERE id_kursi IN ($placeholders)
            FOR UPDATE
        ");
        $stmt->execute($seatIds);
        $seats = $stmt->fetchAll();
        
        if (count($seats) !== count($seatIds)) {
            return false; // Some seats not found
        }
        
        foreach ($seats as $seat) {
            if ($seat['status_kursi'] !== 'Available') {
                return false; // Seat not available
            }
        }
        
        // Lock all seats
        $stmt = $pdo->prepare("
            UPDATE kursi_event
            SET status_kursi = 'Locked'
            WHERE id_kursi IN ($placeholders)
        ");
        $stmt->execute($seatIds);
        
        return $stmt->rowCount() === count($seatIds);
    }
    
    /**
     * Release locked seats (on expiry or rejection)
     */
    public static function releaseSeats(\PDO $pdo, array $seatIds): bool
    {
        if (empty($seatIds)) {
            return true;
        }
        
        $placeholders = implode(',', array_fill(0, count($seatIds), '?'));
        
        $stmt = $pdo->prepare("
            UPDATE kursi_event
            SET status_kursi = 'Available'
            WHERE id_kursi IN ($placeholders)
            AND status_kursi = 'Locked'
        ");
        $stmt->execute($seatIds);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Confirm seats as sold (on payment approval)
     */
    public static function confirmSeats(\PDO $pdo, array $seatIds): bool
    {
        if (empty($seatIds)) {
            return true;
        }
        
        $placeholders = implode(',', array_fill(0, count($seatIds), '?'));
        
        $stmt = $pdo->prepare("
            UPDATE kursi_event
            SET status_kursi = 'Sold'
            WHERE id_kursi IN ($placeholders)
            AND status_kursi = 'Locked'
        ");
        $stmt->execute($seatIds);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Get seat map for a tier
     */
    public static function getSeatMap(\PDO $pdo, int $tierId): array
    {
        $stmt = $pdo->prepare("
            SELECT id_kursi, nomor_kursi, status_kursi
            FROM kursi_event
            WHERE id_tier = ?
            ORDER BY nomor_kursi
        ");
        $stmt->execute([$tierId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Generate seats for a tier (auto-generate)
     */
    public static function generateSeats(\PDO $pdo, int $tierId, int $quantity, string $prefix = 'SEAT'): bool
    {
        $pdo->beginTransaction();
        
        try {
            // Check if seats already exist
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM kursi_event WHERE id_tier = ?");
            $stmt->execute([$tierId]);
            if ((int)$stmt->fetchColumn() > 0) {
                $pdo->rollBack();
                return false; // Seats already exist
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO kursi_event (id_tier, nomor_kursi, status_kursi)
                VALUES (?, ?, 'Available')
            ");
            
            for ($i = 1; $i <= $quantity; $i++) {
                $nomor = $prefix . '-' . str_pad($i, 3, '0', STR_PAD_LEFT);
                $stmt->execute([$tierId, $nomor]);
            }
            
            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Check stock availability
     */
    public static function checkAvailability(\PDO $pdo, int $tierId, int $quantity): bool
    {
        $stmt = $pdo->prepare("
            SELECT kuota_tersedia FROM tiket_tier WHERE id_tier = ?
        ");
        $stmt->execute([$tierId]);
        $tier = $stmt->fetch();
        
        if (!$tier) {
            return false;
        }
        
        return (int)$tier['kuota_tersedia'] >= $quantity;
    }
    
    /**
     * Get stock info
     */
    public static function getStockInfo(\PDO $pdo, int $tierId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT kuota_total, kuota_tersedia, kuota_locked
            FROM tiket_tier
            WHERE id_tier = ?
        ");
        $stmt->execute([$tierId]);
        return $stmt->fetch() ?: null;
    }
}