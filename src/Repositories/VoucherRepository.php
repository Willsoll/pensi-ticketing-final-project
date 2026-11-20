<?php
// src/Repositories/VoucherRepository.php
// Voucher data access (Matches ROMBEL 3 schema)

namespace App\Repositories;

class VoucherRepository extends BaseRepository
{
    protected string $table = 'voucher_promo';
    protected string $primaryKey = 'id_voucher';

    public function findByCode(string $code): ?array
    {
        return $this->findBy('kode_voucher', strtoupper($code));
    }

    public function findActive(): array
    {
        $pdo = $this->pdo;
        $stmt = $pdo->prepare("
            SELECT * FROM voucher_promo
            WHERE kuota_pakai > 0
            AND expired_date >= CURDATE()
            ORDER BY id_voucher DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findAllWithUsage(): array
    {
        $pdo = $this->pdo;
        $stmt = $pdo->query("
            SELECT vp.*,
                (vp.kuota_pakai - COALESCE(vr.used, 0)) as remaining_quota
            FROM voucher_promo vp
            LEFT JOIN (
                SELECT id_voucher, COUNT(*) as used
                FROM voucher_redemptions
                GROUP BY id_voucher
            ) vr ON vp.id_voucher = vr.id_voucher
            ORDER BY vp.id_voucher DESC
        ");
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $data['kode_voucher'] = strtoupper($data['kode_voucher']);
        return parent::create($data);
    }

    public function decrementQuota(int $voucherId): bool
    {
        $pdo = $this->pdo;
        $stmt = $pdo->prepare("
            UPDATE voucher_promo
            SET kuota_pakai = kuota_pakai - 1
            WHERE id_voucher = ? AND kuota_pakai > 0
        ");
        $stmt->execute([$voucherId]);
        return $stmt->rowCount() > 0;
    }

    public function incrementQuota(int $voucherId): bool
    {
        $voucher = $this->find($voucherId);
        if (!$voucher) return false;
        return $this->update($voucherId, [
            'kuota_pakai' => $voucher['kuota_pakai'] + 1,
        ]);
    }
}