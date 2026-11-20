<?php
// src/Services/AuditLogger.php
// Centralized audit logging (Matches ROMBEL 3 schema - uses 'waktu')

namespace App\Services;

use App\Security\Database;

class AuditLogger
{
    public static function log(
        int $userId,
        string $aksi,
        string $entitas,
        string $keterangan,
        ?int $entityId = null
    ): void {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (id_user, aksi, entitas, keterangan)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $userId,
            $aksi,
            $entitas,
            $keterangan,
        ]);
    }

    /**
     * Pre-defined action constants for consistency
     */
    public const ACTION_CREATE = 'CREATE';
    public const ACTION_UPDATE = 'UPDATE';
    public const ACTION_DELETE = 'DELETE';
    public const ACTION_LOGIN = 'LOGIN';
    public const ACTION_LOGOUT = 'LOGOUT';
    public const ACTION_PAYMENT_UPLOAD = 'PAYMENT_UPLOAD';
    public const ACTION_PAYMENT_VERIFY = 'PAYMENT_VERIFY';
    public const ACTION_PAYMENT_REJECT = 'PAYMENT_REJECT';
    public const ACTION_EXPIRE = 'EXPIRE';
    public const ACTION_CHECKIN = 'GATE_CHECKIN';
    public const ACTION_ISSUE_TICKET = 'ISSUE_ETICKET';
    public const ACTION_VOUCHER_CREATE = 'VOUCHER_CREATE';
    public const ACTION_VOUCHER_UPDATE = 'VOUCHER_UPDATE';
    public const ACTION_VOUCHER_DELETE = 'VOUCHER_DELETE';
    public const ACTION_SEAT_GENERATE = 'SEAT_GENERATE';
    public const ACTION_EXPIRE_RESERVATION = 'EXPIRE_RESERVATION';
    public const ACTION_ISSUE_ETICKET = 'ISSUE_ETICKET';
    public const ACTION_GATE_CHECKIN = 'GATE_CHECKIN';
    public const ACTION_VERIFY_PAYMENT = 'VERIFY_PAYMENT';
    public const ACTION_REJECT_PAYMENT = 'REJECT_PAYMENT';

    /**
     * Entity constants
     */
    public const ENTITY_USER = 'users';
    public const ENTITY_EVENT = 'events';
    public const ENTITY_TIER = 'tiket_tier';
    public const ENTITY_SEAT = 'kursi_event';
    public const ENTITY_VOUCHER = 'voucher_promo';
    public const ENTITY_TRANSACTION = 'transaksi';
    public const ENTITY_RESERVATION = 'reservations';
    public const ENTITY_ETICKET = 'e_tickets';
    public const ENTITY_AUDIT = 'audit_logs';

    /**
     * Helper methods for common audit actions
     */
    public static function logCreate(int $userId, string $entity, int $entityId, string $description): void
    {
        self::log($userId, self::ACTION_CREATE, $entity,
            "$description (ID: $entityId)");
    }

    public static function logUpdate(int $userId, string $entity, int $entityId, string $description): void
    {
        self::log($userId, self::ACTION_UPDATE, $entity,
            "$description (ID: $entityId)");
    }

    public static function logDelete(int $userId, string $entity, int $entityId, string $description): void
    {
        self::log($userId, self::ACTION_DELETE, $entity,
            "$description (ID: $entityId)");
    }

    public static function logAuth(int $userId, string $action, string $description): void
    {
        self::log($userId, $action, self::ENTITY_USER, $description);
    }

    public static function logPayment(int $userId, string $action, int $transaksiId, string $description): void
    {
        self::log($userId, $action, self::ENTITY_TRANSACTION,
            "$description (Transaksi ID: $transaksiId)");
    }

    public static function logGate(int $userId, string $action, int $etiketId, string $description): void
    {
        self::log($userId, $action, self::ENTITY_ETICKET,
            "$description (E-Ticket ID: $etiketId)");
    }
}