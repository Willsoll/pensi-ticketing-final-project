<?php
// src/Repositories/TransactionRepository.php
// Transaction data access (Matches ROMBEL 3 schema - no jumlah column)

namespace App\Repositories;

class TransactionRepository extends BaseRepository
{
    protected string $table = 'transaksi';
    protected string $primaryKey = 'id_transaksi';

    public function findByCode(string $code): ?array
    {
        return $this->findBy('kode_booking', $code);
    }

    public function findByUser(int $userId, int $limit = 20, int $offset = 0): array
    {
        $pdo = $this->pdo;
        $stmt = $pdo->prepare("
            SELECT t.*, tt.nama_tier, e.judul_event, e.tipe_venue
            FROM transaksi t
            JOIN tiket_tier tt ON t.id_tier = tt.id_tier
            JOIN events e ON t.id_event = e.id_event
            WHERE t.id_user = ?
            ORDER BY t.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll();
    }

    public function findByUserAndStatus(int $userId, string $status): array
    {
        return $this->findAll(['id_user' => $userId, 'status_bayar' => $status], 'created_at DESC');
    }

    public function findPendingVerification(int $limit = 50): array
    {
        $pdo = $this->pdo;
        $stmt = $pdo->prepare("
            SELECT t.*, u.nama_lengkap as user_nama, u.email, u.nik, e.judul_event, tt.nama_tier
            FROM transaksi t
            JOIN users u ON t.id_user = u.id_user
            JOIN events e ON t.id_event = e.id_event
            JOIN tiket_tier tt ON t.id_tier = tt.id_tier
            WHERE t.status_bayar = 'Waiting Verification'
            ORDER BY t.created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public function findByReservation(int $reservationId): ?array
    {
        return $this->findBy('id_reservasi', $reservationId);
    }

    public function getDashboardStats(): array
    {
        $pdo = $this->pdo;
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total_transaksi,
                SUM(CASE WHEN status_bayar = 'Paid' THEN 
                    (SELECT COUNT(*) FROM e_tickets WHERE id_transaksi = t.id_transaksi) 
                    ELSE 0 END) as tiket_lunas,
                SUM(CASE WHEN status_bayar = 'Paid' THEN total_bayar ELSE 0 END) as total_pemasukan,
                SUM(CASE WHEN status_bayar = 'Paid' THEN diskon ELSE 0 END) as total_diskon,
                SUM(CASE WHEN status_bayar = 'Waiting Verification' THEN 1 ELSE 0 END) as menunggu_verif,
                SUM(CASE WHEN status_bayar = 'Pending' THEN 1 ELSE 0 END) as menunggu_bayar,
                SUM(CASE WHEN status_bayar = 'Expired' THEN 1 ELSE 0 END) as kadaluarsa,
                SUM(CASE WHEN status_bayar = 'Rejected' THEN 1 ELSE 0 END) as ditolak
            FROM transaksi t
        ");
        return $stmt->fetch();
    }

    public function getSegmentStats(): array
    {
        $pdo = $this->pdo;
        $stmt = $pdo->query("
            SELECT 
                tt.nama_tier,
                SUM(CASE WHEN t.status_bayar = 'Paid' THEN 
                    (SELECT COUNT(*) FROM e_tickets WHERE id_transaksi = t.id_transaksi) 
                    ELSE 0 END) as terjual,
                SUM(CASE WHEN t.status_bayar = 'Waiting Verification' THEN 
                    (SELECT COUNT(*) FROM e_tickets WHERE id_transaksi = t.id_transaksi) 
                    ELSE 0 END) as pending_verif,
                SUM(CASE WHEN t.status_bayar = 'Pending' THEN 
                    (SELECT COUNT(*) FROM e_tickets WHERE id_transaksi = t.id_transaksi) 
                    ELSE 0 END) as pending_bayar
            FROM transaksi t
            JOIN tiket_tier tt ON t.id_tier = tt.id_tier
            GROUP BY tt.id_tier
            ORDER BY tt.id_tier
        ");
        return $stmt->fetchAll();
    }

    public function getRecent(int $limit = 10): array
    {
        $pdo = $this->pdo;
        $stmt = $pdo->prepare("
            SELECT t.*, u.nama_lengkap as nama_pembeli, tt.nama_tier
            FROM transaksi t
            JOIN users u ON t.id_user = u.id_user
            JOIN tiket_tier tt ON t.id_tier = tt.id_tier
            ORDER BY t.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public function getWithDetails(int $transaksiId): ?array
    {
        $pdo = $this->pdo;
        $stmt = $pdo->prepare("
            SELECT t.*, u.nama_lengkap as nama_pembeli, u.email, u.nik,
                   e.judul_event, e.lokasi, e.tgl_mulai, tt.nama_tier, e.tipe_venue
            FROM transaksi t
            JOIN users u ON t.id_user = u.id_user
            JOIN events e ON t.id_event = e.id_event
            JOIN tiket_tier tt ON t.id_tier = tt.id_tier
            WHERE t.id_transaksi = ?
        ");
        $stmt->execute([$transaksiId]);
        return $stmt->fetch() ?: null;
    }

    public function updateStatus(int $transaksiId, string $status, ?string $catatan = null): bool
    {
        $data = [
            'status_bayar' => $status,
        ];

        if ($catatan !== null) {
            $data['catatan_penolakan'] = $catatan;
        }

        return $this->update($transaksiId, $data);
    }

    public function setPaymentProof(int $transaksiId, string $path): bool
    {
        return $this->update($transaksiId, [
            'bukti_bayar' => $path,
            'status_bayar' => 'Waiting Verification',
        ]);
    }
}