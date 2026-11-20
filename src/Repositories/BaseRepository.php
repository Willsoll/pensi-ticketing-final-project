<?php
// src/Repositories/BaseRepository.php
// Base repository with common CRUD operations

namespace App\Repositories;

use App\Security\Database;

abstract class BaseRepository
{
    protected \PDO $pdo;
    protected string $table;
    protected string $primaryKey = 'id';
    
    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? \App\Security\Database::getConnection();
    }
    
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
    
    public function findBy(string $column, mixed $value): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE {$column} = ?");
        $stmt->execute([$value]);
        return $stmt->fetch() ?: null;
    }
    
    public function findAll(array $conditions = [], string $orderBy = '', int $limit = 0, int $offset = 0): array
    {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        
        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $col => $val) {
                $where[] = "{$col} = ?";
                $params[] = $val;
            }
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        
        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
            if ($offset > 0) {
                $sql .= " OFFSET {$offset}";
            }
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function count(array $conditions = []): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        $params = [];
        
        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $col => $val) {
                $where[] = "{$col} = ?";
                $params[] = $val;
            }
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }
    
    public function create(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($c) => ":{$c}", $columns);
        
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        
        return (int)$this->pdo->lastInsertId();
    }
    
    public function update(int $id, array $data): bool
    {
        if (empty($data)) {
            return false;
        }
        
        $set = [];
        foreach (array_keys($data) as $col) {
            $set[] = "{$col} = :{$col}";
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $set) . " WHERE {$this->primaryKey} = :id";
        $data['id'] = $id;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        
        return $stmt->rowCount() > 0;
    }
    
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
    
    public function exists(string $column, mixed $value, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$column} = ?";
        $params = [$value];
        
        if ($excludeId !== null) {
            $sql .= " AND {$this->primaryKey} != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }
}