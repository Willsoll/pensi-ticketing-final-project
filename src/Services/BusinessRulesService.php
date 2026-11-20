<?php
// src/Services/BusinessRulesService.php
// Implementation of the 4 Mandatory CEO Business Rules (Matches ROMBEL 3 schema)

namespace App\Services;

use App\Security\Database;
use App\Services\InventoryService;
use App\Services\VoucherService;

class BusinessRulesService
{
    /**
     * RULE 1: Stock Locking & 15-min Auto-Expiration
     * 
     * When a reservation is created:
     * - Lock ticket quota (move from tersedia to locked)
     * - Lock selected seats (Available -> Locked)
     * - Set batas_bayar_at = NOW() + 15 minutes (server time)
     * - All done atomically in transaction
     * 
     * On expiry (cron + defensive):
     * - Transaction becomes Expired
     * - Locked seats -> Available
     * - Locked quota -> tersedia
     * - Voucher quota restored (once, via voucher_redemptions table)
     */

    /**
     * Create reservation with stock locking (Rule 1)
     * Must be called within a database transaction
     */
    public static function createReservation(\PDO $pdo, array $data): int
    {
        // Validate input
        if ($data['quantity'] > 5) {
            throw new \InvalidArgumentException('Maksimal 5 tiket per transaksi.');
        }

        // Lock stock
        if (!InventoryService::lockStock($pdo, $data['tier_id'], $data['quantity'])) {
            throw new \RuntimeException('Stok tidak mencukupi atau sudah dikunci.');
        }

        // Lock seats if seated
        if (!empty($data['seat_ids'])) {
            if (!InventoryService::lockSeats($pdo, $data['seat_ids'])) {
                throw new \RuntimeException('Beberapa kursi tidak tersedia untuk dikunci.');
            }
        }

        // Reserve voucher quota if applicable
        if (!empty($data['voucher_id'])) {
            if (!VoucherService::reserveQuota($pdo, $data['voucher_id'])) {
                throw new \RuntimeException('Kuota voucher tidak tersedia.');
            }
        }

        // Calculate expiry (15 minutes from server time)
        $batasBayar = (new \DateTime())->modify('+15 minutes')->format('Y-m-d H:i:s');

        // Insert reservation
        $stmt = $pdo->prepare("
            INSERT INTO reservations (
                id_user, id_event, id_tier, id_voucher,
                jumlah, diskon, total_bayar, batas_bayar_at, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active')
        ");

        $stmt->execute([
            $data['user_id'],
            $data['event_id'],
            $data['tier_id'],
            $data['voucher_id'] ?? null,
            $data['quantity'],
            $data['discount'],
            $data['total_bayar'],
            $batasBayar,
        ]);

        $reservationId = (int)$pdo->lastInsertId();

        // Insert reservation items (attendee identities + seats)
        $stmtItem = $pdo->prepare("
            INSERT INTO reservation_items (id_reservasi, id_kursi, nama_peserta, nik_peserta)
            VALUES (?, ?, ?, ?)
        ");

        foreach ($data['attendees'] as $index => $attendee) {
            $seatId = $data['seat_ids'][$index] ?? null;
            $stmtItem->execute([
                $reservationId,
                $seatId,
                $attendee['nama'],
                $attendee['nik'],
            ]);
        }

        return $reservationId;
    }

    /**
     * Process expired reservations (Rule 1 - Auto Expiry)
     * Called by cron job every minute AND defensively in checkout
     */
    public static function processExpiredReservations(\PDO $pdo): array
    {
        $results = [
            'processed' => 0,
            'expired' => 0,
            'errors' => [],
        ];

        $stmt = $pdo->prepare("
            SELECT id_reservasi, id_user, id_event, id_tier, id_voucher, jumlah
            FROM reservations
            WHERE status = 'Active'
            AND batas_bayar_at < NOW()
            FOR UPDATE
        ");
        $stmt->execute();
        $expired = $stmt->fetchAll();

        foreach ($expired as $res) {
            try {
                $pdo->beginTransaction();

                // Lock reservation
                $stmtLock = $pdo->prepare("SELECT status FROM reservations WHERE id_reservasi = ? FOR UPDATE");
                $stmtLock->execute([$res['id_reservasi']]);
                $currentStatus = $stmtLock->fetchColumn();

                if ($currentStatus !== 'Active') {
                    $pdo->rollBack();
                    continue;
                }

                // Mark as expired
                $stmt = $pdo->prepare("UPDATE reservations SET status = 'Expired' WHERE id_reservasi = ?");
                $stmt->execute([$res['id_reservasi']]);

                // Release stock
                InventoryService::releaseStock($pdo, (int)$res['id_tier'], (int)$res['jumlah']);

                // Release seats
                $stmtSeats = $pdo->prepare("SELECT id_kursi FROM reservation_items WHERE id_reservasi = ?");
                $stmtSeats->execute([$res['id_reservasi']]);
                $seatIds = $stmtSeats->fetchAll(\PDO::FETCH_COLUMN);
                if (!empty($seatIds)) {
                    InventoryService::releaseSeats($pdo, $seatIds);
                }

                // Release voucher quota
                if (!empty($res['id_voucher'])) {
                    VoucherService::releaseQuota($pdo, (int)$res['id_voucher']);
                }

                // Log audit
                self::logAudit($pdo, (int)$res['id_user'], 'EXPIRE_RESERVATION', 'reservations', 
                    "Reservasi otomatis kadaluarsa (15 menit). Stok & kursi dikembalikan.");

                $pdo->commit();
                $results['expired']++;

            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $results['errors'][] = "Reservasi {$res['id_reservasi']}: " . $e->getMessage();
            }

            $results['processed']++;
        }

        return $results;
    }

    /**
     * RULE 2: Maximum 5 Tickets per Account/NIK per Event
     * Count paid tickets + active reservations
     */
    public static function checkTicketLimit(\PDO $pdo, int $userId, int $eventId, int $tierId, int $requestedQty): bool
    {
        // Get user NIK
        $stmt = $pdo->prepare("SELECT nik FROM users WHERE id_user = ?");
        $stmt->execute([$userId]);
        $nik = $stmt->fetchColumn();

        // Count paid tickets for this event (count e_tickets for paid transactions)
        $stmt = $pdo->prepare("
            SELECT COALESCE(COUNT(et.id_etiket), 0)
            FROM e_tickets et
            JOIN transaksi t ON et.id_transaksi = t.id_transaksi
            JOIN tiket_tier tt ON et.id_tier = tt.id_tier
            WHERE t.id_user = ? AND tt.id_event = ? AND t.status_bayar = 'Paid'
        ");
        $stmt->execute([$userId, $eventId]);
        $paidTickets = (int)$stmt->fetchColumn();

        // Count active reservations for this event
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(r.jumlah), 0)
            FROM reservations r
            JOIN tiket_tier tt ON r.id_tier = tt.id_tier
            WHERE r.id_user = ? AND tt.id_event = ? AND r.status = 'Active'
        ");
        $stmt->execute([$userId, $eventId]);
        $activeTickets = (int)$stmt->fetchColumn();

        $total = $paidTickets + $activeTickets + $requestedQty;

        return $total <= 5;
    }

    /**
     * Validate attendee identities (distinct NIK per ticket)
     */
    public static function validateAttendeeIdentities(array $attendees): array
    {
        $errors = [];
        $seenNik = [];

        foreach ($attendees as $index => $attendee) {
            $nik = trim($attendee['nik'] ?? '');
            $nama = trim($attendee['nama'] ?? '');

            if (mb_strlen($nama) < 3) {
                $errors[] = "Tiket #" . ($index + 1) . ": Nama minimal 3 karakter.";
            }

            if (!\App\Validation\InputValidator::nik($nik)) {
                $errors[] = "Tiket #" . ($index + 1) . ": NIK harus 16 digit angka.";
            }

            if (isset($seenNik[$nik])) {
                $errors[] = "Tiket #" . ($index + 1) . ": NIK $nik sudah digunakan dalam transaksi ini.";
            }
            $seenNik[$nik] = true;
        }

        return $errors;
    }

    /**
     * RULE 3: Conditional Voucher Validation
     * All 5 conditions must be true
     */
    public static function validateVoucherConditions(
        \PDO $pdo,
        string $voucherCode,
        int $subtotal
    ): array {
        return VoucherService::validate($pdo, $voucherCode, $subtotal);
    }

    /**
     * RULE 4: One-to-Many E-Tickets & Anti-Passback
     * 
     * E-tickets issued ONLY after payment = Paid
     * Each ticket = unique kode_tiket + QR code
     * Gate check-in: validate serial, NIK, Paid status, not checked in
     * Atomic check-in (FOR UPDATE)
     * Red warning on duplicate with previous timestamp + officer
     */

    /**
     * Issue E-Tickets after payment confirmed (Rule 4)
     * Must be called within a database transaction
     */
    public static function issueETickets(\PDO $pdo, int $transaksiId): array
    {
        // Get transaction details
        $stmt = $pdo->prepare("
            SELECT t.*, tt.id_event
            FROM transaksi t
            JOIN tiket_tier tt ON t.id_tier = tt.id_tier
            WHERE t.id_transaksi = ?
        ");
        $stmt->execute([$transaksiId]);
        $transaksi = $stmt->fetch();

        if (!$transaksi || $transaksi['status_bayar'] !== 'Paid') {
            throw new \RuntimeException('Transaksi tidak valid atau belum lunas.');
        }

        // Get reservation items for attendee data
        if ($transaksi['id_reservasi']) {
            $stmtItems = $pdo->prepare("
                SELECT * FROM reservation_items WHERE id_reservasi = ?
            ");
            $stmtItems->execute([$transaksi['id_reservasi']]);
            $items = $stmtItems->fetchAll();
        } else {
            // Fallback: use transaction quantity with generic data
            $items = [];
            for ($i = 1; $i <= $transaksi['jumlah']; $i++) {
                $items[] = [
                    'id_kursi' => null,
                    'nama_peserta' => 'Peserta ' . $i,
                    'nik_peserta' => '',
                ];
            }
        }

        if (count($items) !== $transaksi['jumlah']) {
            throw new \RuntimeException('Jumlah data peserta tidak sesuai dengan jumlah tiket.');
        }

        $issuedTickets = [];

        $stmtETicket = $pdo->prepare("
            INSERT INTO e_tickets (
                id_transaksi, id_tier, id_kursi, kode_tiket,
                nama_peserta, nik_peserta, status_checkin
            ) VALUES (?, ?, ?, ?, ?, ?, 'Belum Hadir')
        ");

        for ($i = 0; $i < $transaksi['jumlah']; $i++) {
            $item = $items[$i];

            // Generate unique ticket code: PEN-{YMD}-{RANDOM}-{SEQ}
            $kodeTiket = 'PEN-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)) . '-' . ($i + 1);

            $stmtETicket->execute([
                $transaksiId,
                $transaksi['id_tier'],
                $item['id_kursi'] ?? null,
                $kodeTiket,
                $item['nama_peserta'],
                $item['nik_peserta'],
            ]);

            $issuedTickets[] = [
                'id_etiket' => (int)$pdo->lastInsertId(),
                'kode_tiket' => $kodeTiket,
                'nama_peserta' => $item['nama_peserta'],
                'nik_peserta' => $item['nik_peserta'],
                'id_kursi' => $item['id_kursi'] ?? null,
            ];
        }

        // Log audit
        self::logAudit($pdo, (int)$transaksi['id_user'], 'ISSUE_ETICKETS', 'e_tickets',
            "E-Ticket diterbitkan untuk transaksi {$transaksi['kode_booking']} ({$transaksi['jumlah']} tiket).");

        return $issuedTickets;
    }

    /**
     * Process gate check-in (Rule 4 - Anti-Passback)
     * Must be called within a database transaction
     */
    public static function processGateCheckin(
        \PDO $pdo,
        string $kodeTiket,
        string $nikInput,
        int $petugasId
    ): array {
        $stmt = $pdo->prepare("
            SELECT et.*, t.status_bayar, t.kode_booking, e.judul_event
            FROM e_tickets et
            JOIN transaksi t ON et.id_transaksi = t.id_transaksi
            JOIN events e ON t.id_event = e.id_event
            WHERE et.kode_tiket = ?
            FOR UPDATE
        ");
        $stmt->execute([$kodeTiket]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            return ['success' => false, 'message' => "Kode E-Ticket '$kodeTiket' tidak ditemukan."];
        }

        if ($ticket['status_bayar'] !== 'Paid') {
            return ['success' => false, 'message' => "Pembayaran E-Ticket ini berstatus {$ticket['status_bayar']} (belum Paid). Akses ditolak!"];
        }

        if ($ticket['status_checkin'] === 'Sudah Hadir') {
            $prevTime = $ticket['waktu_checkin'] ? date('d-m-Y H:i:s', strtotime($ticket['waktu_checkin'])) : '-';
            $petugasName = '-';

            if ($ticket['id_petugas_gate']) {
                $stmtPetugas = $pdo->prepare("SELECT nama_lengkap FROM users WHERE id_user = ?");
                $stmtPetugas->execute([$ticket['id_petugas_gate']]);
                $petugasName = $stmtPetugas->fetchColumn() ?? '-';
            }

            return [
                'success' => false,
                'message' => "PERINGATAN! E-Ticket $kodeTiket SUDAH CHECK-IN pada $prevTime. Tolak pemakaian ulang!",
                'already_checked_in' => true,
                'previous_checkin' => $prevTime,
                'previous_officer' => $petugasName,
            ];
        }

        if ($nikInput !== $ticket['nik_peserta']) {
            return ['success' => false, 'message' => "NIK KTP peserta tidak sesuai dengan data E-Ticket!"];
        }

        // Perform check-in
        $stmt = $pdo->prepare("
            UPDATE e_tickets
            SET status_checkin = 'Sudah Hadir', waktu_checkin = NOW(), id_petugas_gate = ?
            WHERE id_etiket = ?
        ");
        $stmt->execute([$petugasId, $ticket['id_etiket']]);

        // Log audit
        self::logAudit($pdo, $petugasId, 'GATE_CHECKIN', 'e_tickets',
            "Check-in pintu masuk untuk kode $kodeTiket ({$ticket['judul_event']}) atas nama {$ticket['nama_peserta']} (NIK: {$ticket['nik_peserta']}).");

        return [
            'success' => true,
            'message' => "Check-in berhasil! Selamat datang, {$ticket['nama_peserta']}.",
            'ticket' => $ticket,
        ];
    }

    /**
     * Audit logging helper
     */
    public static function logAudit(\PDO $pdo, int $userId, string $aksi, string $entitas, string $keterangan): void
    {
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
}