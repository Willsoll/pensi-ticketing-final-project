<?php
// src/Repositories/EventRepository.php
// Event data access (Matches ROMBEL 3 schema)

namespace App\Repositories;

class EventRepository extends BaseRepository
{
    protected string $table = 'events';
    protected string $primaryKey = 'id_event';

    public function findActive(): array
    {
        return $this->findAll(
            ['status_event' => 'Available'],
            'tgl_mulai ASC'
        );
    }

    public function findByCategory(int $categoryId): array
    {
        return $this->findAll(['id_kat_event' => $categoryId], 'tgl_mulai ASC');
    }

    public function findUpcoming(int $limit = 10): array
    {
        $pdo = $this->pdo;
        $stmt = $pdo->prepare("
            SELECT e.*, ke.nama_kategori
            FROM events e
            JOIN kategori_event ke ON e.id_kat_event = ke.id_kat_event
            WHERE e.status_event = 'Available'
            AND e.tgl_mulai >= NOW()
            ORDER BY e.tgl_mulai ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public function search(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $pdo = $this->pdo;
        $where = ["e.status_event = 'Available'"];
        $params = [];

        if (!empty($filters['category'])) {
            $where[] = "e.id_kat_event = ?";
            $params[] = $filters['category'];
        }

        if (!empty($filters['city'])) {
            $where[] = "e.lokasi LIKE ?";
            $params[] = "%{$filters['city']}%";
        }

        if (!empty($filters['date_from'])) {
            $where[] = "e.tgl_mulai >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "e.tgl_mulai <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        if (!empty($filters['search'])) {
            $where[] = "(e.judul_event LIKE ? OR e.penyelenggara LIKE ? OR e.lokasi LIKE ?)";
            $search = "%{$filters['search']}%";
            $params = array_merge($params, [$search, $search, $search]);
        }

        $sql = "
            SELECT e.*, ke.nama_kategori
            FROM events e
            JOIN kategori_event ke ON e.id_kat_event = ke.id_kat_event
            WHERE " . implode(' AND ', $where) . "
            ORDER BY e.tgl_mulai ASC
            LIMIT ? OFFSET ?
        ";

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getWithTierStats(int $eventId): ?array
    {
        $pdo = $this->pdo;
        $stmt = $pdo->prepare("
            SELECT e.*, ke.nama_kategori,
                COUNT(tt.id_tier) as tier_count,
                SUM(tt.kuota_total) as total_kuota,
                SUM(tt.kuota_tersedia) as total_tersedia,
                MIN(tt.harga) as min_harga,
                MAX(tt.harga) as max_harga
            FROM events e
            JOIN kategori_event ke ON e.id_kat_event = ke.id_kat_event
            LEFT JOIN tiket_tier tt ON tt.id_event = e.id_event
            WHERE e.id_event = ?
            GROUP BY e.id_event
        ");
        $stmt->execute([$eventId]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        return parent::create($data);
    }

    public function updateStatus(int $eventId, string $status): bool
    {
        return $this->update($eventId, [
            'status_event' => $status,
        ]);
    }
}