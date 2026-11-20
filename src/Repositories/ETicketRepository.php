<?php
// src/Repositories/ETicketRepository.php
// E-Ticket data access (Matches ROMBEL 3 schema)

namespace App\Repositories;

class ETicketRepository extends BaseRepository
{
    protected string $table = 'e_tickets';
    protected string $primaryKey = 'id_etiket';

    public function findByCode(string $kodeTiket): ?array
    {
        return $this->findBy('kode_tiket', $kodeTiket);
    }

    public function findByTransaction(int $transaksiId): array
    {
        $pdo = $this->pdo;
        $stmt = $pdo->prepare("
            SELECT et.*, kp.nomor_kursi
            FROM e_tickets et
            LEFT JOIN kursi_event kp ON et.id_kursi = kp.id_kursi
            WHERE et.id_transaksi = ?
            ORDER BY et.id_etiket ASC
        ");
        $stmt->execute([$transaksiId]);
        return $stmt->fetchAll();
    }

    public function findByUser(int $userId): array
    {
        $pdo = $this->pdo;
        $stmt = $pdo->prepare("
            SELECT et.*, t.kode_booking, t.status_bayar, e.judul_event, tt.nama_tier, e.tipe_venue, kp.nomor_kursi
            FROM e_tickets et
            JOIN transaksi t ON et.id_transaksi = t.id_transaksi
            JOIN events e ON t.id_event = e.id_event
            JOIN tiket_tier tt ON et.id_tier = tt.id_tier
            LEFT JOIN kursi_event kp ON et.id_kursi = kp.id_kursi
            WHERE t.id_user = ?
            ORDER BY et.id_etiket ASC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function findPaidForCheckin(): array
    {
        $pdo = $this->pdo;
        $stmt = $pdo->query("
            SELECT et.*, t.kode_booking, e.judul_event, tt.nama_tier, kp.nomor_kursi
            FROM e_tickets et
            JOIN transaksi t ON et.id_transaksi = t.id_transaksi
            JOIN events e ON t.id_event = e.id_event
            JOIN tiket_tier tt ON et.id_tier = tt.id_tier
            LEFT JOIN kursi_event kp ON et.id_kursi = kp.id_kursi
            WHERE t.status_bayar = 'Paid'
            ORDER BY et.status_checkin ASC, et.id_etiket ASC
        ");
        return $stmt->fetchAll();
    }

    public function getCheckinStats(): array
    {
        $pdo = $this->pdo;
        $stmt = $pdo->query("
            SELECT 
                tt.nama_tier,
                COUNT(et.id_etiket) as total_tiket,
                SUM(CASE WHEN et.status_checkin = 'Sudah Hadir' THEN 1 ELSE 0 END) as sudah_masuk,
                SUM(CASE WHEN et.status_checkin = 'Belum Hadir' THEN 1 ELSE 0 END) as belum_masuk
            FROM e_tickets et
            JOIN transaksi t ON et.id_transaksi = t.id_transaksi
            JOIN tiket_tier tt ON et.id_tier = tt.id_tier
            WHERE t.status_bayar = 'Paid'
            GROUP BY tt.id_tier
        ");
        return $stmt->fetchAll();
    }

    public function checkin(int $etiketId, int $petugasId): bool
    {
        $pdo = $this->pdo;
        $stmt = $pdo->prepare("
            UPDATE e_tickets
            SET status_checkin = 'Sudah Hadir', waktu_checkin = NOW(), id_petugas_gate = ?
            WHERE id_etiket = ? AND status_checkin = 'Belum Hadir'
        ");
        $stmt->execute([$petugasId, $etiketId]);
        return $stmt->rowCount() > 0;
    }

    public function findWithDetails(string $kodeTiket): ?array
    {
        $pdo = $this->pdo;
        $stmt = $pdo->prepare("
            SELECT et.*, t.kode_booking, t.status_bayar,
                   e.judul_event, e.penyelenggara, e.lokasi, e.tgl_mulai, e.tgl_selesai, e.tipe_venue,
                   tt.nama_tier,
                   kp.nomor_kursi,
                   pg.nama_lengkap as nama_petugas
            FROM e_tickets et
            JOIN transaksi t ON et.id_transaksi = t.id_transaksi
            JOIN events e ON t.id_event = e.id_event
            JOIN tiket_tier tt ON et.id_tier = tt.id_tier
            LEFT JOIN kursi_event kp ON et.id_kursi = kp.id_kursi
            LEFT JOIN users pg ON et.id_petugas_gate = pg.id_user
            WHERE et.kode_tiket = ?
        ");
        $stmt->execute([$kodeTiket]);
        return $stmt->fetch() ?: null;
    }
}