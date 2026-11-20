<?php
// src/Repositories/ReservationRepository.php
// Reservation data access

namespace App\Repositories;

class ReservationRepository extends BaseRepository
{
    protected string $table = 'reservations';
    protected string $primaryKey = 'id_reservasi';
    
    public function findByUser(int $userId): array
    {
        $pdo = $this->pdo;
        $stmt = $pdo->prepare("
            SELECT r.*, e.judul_event, tt.nama_tier
            FROM reservations r
            JOIN events e ON r.id_event = e.id_event
            JOIN tiket_tier tt ON r.id_tier = tt.id_tier
            WHERE r.id_user = ?
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    public function findActiveByUser(int $userId): array
    {
        return $this->findAll(['id_user' => $userId, 'status' => 'Active'], 'created_at DESC');
    }
    
    public function findWithItems(int $reservationId): ?array
    {
        $pdo = $this->pdo;
        
        $stmt = $pdo->prepare("
            SELECT r.*, e.judul_event, tt.nama_tier, tt.tipe_venue
            FROM reservations r
            JOIN events e ON r.id_event = e.id_event
            JOIN tiket_tier tt ON r.id_tier = tt.id_tier
            WHERE r.id_reservasi = ?
        ");
        $stmt->execute([$reservationId]);
        $reservation = $stmt->fetch();
        
        if (!$reservation) {
            return null;
        }
        
        $stmtItems = $pdo->prepare("
            SELECT ri.*, kp.nomor_kursi
            FROM reservation_items ri
            LEFT JOIN kursi_event kp ON ri.id_kursi = kp.id_kursi
            WHERE ri.id_reservasi = ?
            ORDER BY ri.id_item
        ");
        $stmtItems->execute([$reservationId]);
        $reservation['items'] = $stmtItems->fetchAll();
        
        return $reservation;
    }
    
    public function findByCode(string $kodeBooking): ?array
    {
        // Not applicable for reservations, use TransactionRepository
        return null;
    }
    
    public function getActiveExpiringSoon(int $minutes = 15): array
    {
        $pdo = $this->pdo;
        $stmt = $pdo->prepare("
            SELECT r.*, u.email, u.nama as user_nama, e.judul_event
            FROM reservations r
            JOIN users u ON r.id_user = u.id_user
            JOIN events e ON r.id_event = e.id_event
            WHERE r.status = 'Active'
            AND r.batas_bayar_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? MINUTE)
            ORDER BY r.batas_bayar_at ASC
        ");
        $stmt->execute([$minutes]);
        return $stmt->fetchAll();
    }
    
    public function updateStatus(int $reservationId, string $status): bool
    {
        return $this->update($reservationId, [
            'status' => $status,
            'converted_at' => $status === 'Converted' ? date('Y-m-d H:i:s') : null,
        ]);
    }
}