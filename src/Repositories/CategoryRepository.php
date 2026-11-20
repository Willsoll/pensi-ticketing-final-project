<?php
// src/Repositories/CategoryRepository.php
// Event category data access (Matches ROMBEL 3 schema)

namespace App\Repositories;

class CategoryRepository extends BaseRepository
{
    protected string $table = 'kategori_event';
    protected string $primaryKey = 'id_kat_event';

    public function findAllOrdered(): array
    {
        return $this->findAll([], 'nama_kategori ASC');
    }

    public function getWithEventCount(): array
    {
        $pdo = $this->pdo;
        $stmt = $pdo->query("
            SELECT ke.*, COUNT(e.id_event) as event_count
            FROM kategori_event ke
            LEFT JOIN events e ON ke.id_kat_event = e.id_kat_event
            GROUP BY ke.id_kat_event
            ORDER BY ke.nama_kategori
        ");
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        return parent::create($data);
    }
}