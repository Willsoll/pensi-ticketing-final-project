<?php
// src/Repositories/TicketTierRepository.php
// Ticket tier data access (Matches ROMBEL 3 schema)

namespace App\Repositories;

class TicketTierRepository extends BaseRepository
{
    protected string $table = 'tiket_tier';
    protected string $primaryKey = 'id_tier';

    public function findByEvent(int $eventId): array
    {
        return $this->findAll(['id_event' => $eventId], 'harga ASC');
    }

    public function findAvailableForEvent(int $eventId): array
    {
        $pdo = $this->pdo;
        $stmt = $pdo->prepare("
            SELECT * FROM tiket_tier
            WHERE id_event = ?
            AND kuota_tersedia > 0
            AND batas_jual >= NOW()
            ORDER BY harga ASC
        ");
        $stmt->execute([$eventId]);
        return $stmt->fetchAll();
    }

    public function getWithAvailability(int $tierId): ?array
    {
        return $this->find($tierId);
    }

    public function getSeats(int $tierId): array
    {
        $pdo = $this->pdo;
        $stmt = $pdo->prepare("
            SELECT * FROM kursi_event
            WHERE id_tier = ?
            ORDER BY nomor_kursi
        ");
        $stmt->execute([$tierId]);
        return $stmt->fetchAll();
    }

    public function getAvailableSeats(int $tierId): array
    {
        $pdo = $this->pdo;
        $stmt = $pdo->prepare("
            SELECT * FROM kursi_event
            WHERE id_tier = ? AND status_kursi = 'Available'
            ORDER BY nomor_kursi
        ");
        $stmt->execute([$tierId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $data['kuota_tersedia'] = $data['kuota_total'] ?? 0;
        $data['kuota_locked'] = 0;
        return parent::create($data);
    }

    public function updateStock(int $tierId, int $tersedia, int $locked): bool
    {
        return $this->update($tierId, [
            'kuota_tersedia' => max(0, $tersedia),
            'kuota_locked' => max(0, $locked),
        ]);
    }

    public function checkAvailability(int $tierId, int $quantity): bool
    {
        $tier = $this->find($tierId);
        if (!$tier) return false;
        return (int)$tier['kuota_tersedia'] >= $quantity;
    }

    public function getSoldCount(int $tierId): int
    {
        $pdo = $this->pdo;
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(t.jumlah), 0)
            FROM transaksi t
            WHERE t.id_tier = ? AND t.status_bayar = 'Paid'
        ");
        $stmt->execute([$tierId]);
        return (int)$stmt->fetchColumn();
    }
}