<?php
// src/Repositories/UserRepository.php
// User data access (Matches ROMBEL 3 schema)

namespace App\Repositories;

class UserRepository extends BaseRepository
{
    protected string $table = 'users';
    protected string $primaryKey = 'id_user';

    public function findByEmail(string $email): ?array
    {
        return $this->findBy('email', $email);
    }

    public function findByNik(string $nik): ?array
    {
        return $this->findBy('nik', $nik);
    }

    public function findByRole(string $role): array
    {
        return $this->findAll(['role' => $role], 'nama_lengkap ASC');
    }

    public function createCustomer(array $data): int
    {
        $data['role'] = 'customer';
        return $this->create($data);
    }

    public function createAdmin(array $data, string $role): int
    {
        if (!in_array($role, ['admin_eo', 'finance', 'gate_officer'])) {
            throw new \InvalidArgumentException('Invalid admin role');
        }

        $data['role'] = $role;
        return $this->create($data);
    }

    public function updatePassword(int $userId, string $newHashedPassword): bool
    {
        return $this->update($userId, [
            'password' => $newHashedPassword,
        ]);
    }

    public function getStats(): array
    {
        $pdo = $this->pdo;

        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN role = 'customer' THEN 1 ELSE 0 END) as customers,
                SUM(CASE WHEN role = 'admin_eo' THEN 1 ELSE 0 END) as admins,
                SUM(CASE WHEN role = 'finance' THEN 1 ELSE 0 END) as finance,
                SUM(CASE WHEN role = 'gate_officer' THEN 1 ELSE 0 END) as gate
            FROM users
        ");
        return $stmt->fetch();
    }
}