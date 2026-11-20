<?php
// src/Controllers/FinanceController.php
// Finance verification - FIXED

namespace App\Controllers;

use App\Repositories\TransactionRepository;
use App\Repositories\ETicketRepository;
use App\Repositories\ReservationRepository;
use App\Middleware\AuthMiddleware;
use App\Services\BusinessRulesService;
use App\Services\InventoryService;
use App\Services\VoucherService;
use App\Services\AuditLogger;
use App\Security\SessionManager;
use App\Middleware\CsrfMiddleware;

class FinanceController extends BaseController
{
    private TransactionRepository $transactionRepo;
    private ETicketRepository $etiketRepo;
    private ReservationRepository $reservationRepo;

    public function __construct()
    {
        parent::__construct();
        $this->transactionRepo = new TransactionRepository($this->pdo);
        $this->etiketRepo = new ETicketRepository($this->pdo);
        $this->reservationRepo = new ReservationRepository($this->pdo);
    }

    public function index(): void
    {
        AuthMiddleware::requireRole('finance');

        $transactions = $this->transactionRepo->findPendingVerification(50);

        $this->view('finance/index', [
            'transactions' => $transactions,
            'pageTitle' => 'Verifikasi Pembayaran',
        ]);
    }

    public function verify(int $transaksiId): void
    {
        AuthMiddleware::requireRole('finance');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/finance');
            return;
        }

        // CSRF validation
        if (!CsrfMiddleware::validate()) {
            SessionManager::flash('error', 'Token keamanan tidak valid.');
            $this->redirect('/finance');
            return;
        }

        $action = $this->input('action'); // 'approve' or 'reject'
        $catatan = trim($this->input('catatan_penolakan', ''));

        // Validate action
        if (!in_array($action, ['approve', 'reject'], true)) {
            SessionManager::flash('error', 'Aksi tidak valid.');
            $this->redirect('/finance');
            return;
        }

        // Validate rejection reason length
        if ($action === 'reject' && mb_strlen($catatan) < 5) {
            SessionManager::flash('error', 'Catatan penolakan minimal 5 karakter.');
            $this->redirect('/finance');
            return;
        }

        $transaction = $this->transactionRepo->findById($transaksiId);

        if (!$transaction || $transaction['status_bayar'] !== 'Waiting Verification') {
            SessionManager::flash('error', 'Transaksi tidak valid untuk verifikasi.');
            $this->redirect('/finance');
            return;
        }

        try {
            $this->pdo->beginTransaction();

            if ($action === 'approve') {
                // Approve payment
                $this->transactionRepo->updateStatus($transaksiId, 'Paid');

                // Confirm stock (locked -> confirmed sold)
                \App\Services\InventoryService::confirmStock($this->pdo, $transaction['id_tier'], $transaction['jumlah']);

                // Confirm seats - FIXED: join through reservation_items
                $stmt = $this->pdo->prepare("
                    SELECT ri.id_kursi 
                    FROM reservation_items ri 
                    JOIN reservations r ON ri.id_reservasi = r.id_reservasi 
                    WHERE r.id_transaksi = ?
                ");
                $stmt->execute([$transaksiId]);
                $seatIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                if (!empty($seatIds)) {
                    \App\Services\InventoryService::confirmSeats($this->pdo, $seatIds);
                }

                // Issue E-Tickets (Rule 4)
                \App\Services\BusinessRulesService::issueETickets($this->pdo, $transaksiId);

                \App\Services\AuditLogger::log($this->user()['id_user'], 'VERIFY_PAYMENT', 'transaksi',
                    "Pembayaran disetujui untuk transaksi {$transaction['kode_booking']} ({$transaction['total_bayar']}). E-Ticket diterbitkan.");

                SessionManager::flash('success', "Pembayaran disetujui. E-Ticket telah diterbitkan.");

            } elseif ($action === 'reject') {
                $catatan = trim($this->input('catatan_penolakan'));

                // Reject payment
                $this->transactionRepo->updateStatus($transaksiId, 'Rejected', $catatan);

                // Get tier and quantity from transaction
                $stmt = $this->pdo->prepare("SELECT id_tier FROM transaksi WHERE id_transaksi = ?");
                $stmt->execute([$transaksiId]);
                $trx = $stmt->fetch();

                if ($trx) {
                    // Get quantity from e_tickets count (since transaksi doesn't have jumlah)
                    $stmtQty = $this->pdo->prepare("SELECT COUNT(*) FROM e_tickets WHERE id_transaksi = ?");
                    $stmtQty->execute([$transaksiId]);
                    $qty = (int)$stmtQty->fetchColumn();

                    // Release stock
                    \App\Services\InventoryService::releaseStock($this->pdo, $trx['id_tier'], $qty);

                    // Release seats - FIXED: join through reservation_items
                    $stmt = $this->pdo->prepare("
                        SELECT ri.id_kursi 
                        FROM reservation_items ri 
                        JOIN reservations r ON ri.id_reservasi = r.id_reservasi 
                        WHERE r.id_transaksi = ?
                    ");
                    $stmt->execute([$transaksiId]);
                    $seatIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                    if (!empty($seatIds)) {
                        \App\Services\InventoryService::releaseSeats($this->pdo, $seatIds);
                    }

                    // Release voucher quota
                    $stmt = $this->pdo->prepare("SELECT id_voucher FROM transaksi WHERE id_transaksi = ?");
                    $stmt->execute([$transaksiId]);
                    $voucherId = $stmt->fetchColumn();
                    if ($voucherId) {
                        \App\Services\VoucherService::releaseQuota($this->pdo, $voucherId);
                    }
                }

                \App\Services\AuditLogger::log($this->user()['id_user'], 'REJECT_PAYMENT', 'transaksi',
                    "Pembayaran ditolak untuk transaksi {$transaction['kode_booking']}: {$catatan}. Stok & kursi dikembalikan.");

                SessionManager::flash('success', "Pembayaran ditolak. Stok & kursi dikembalikan.");

            } else {
                SessionManager::flash('error', 'Aksi tidak valid.');
                $this->redirect('/finance');
                return;
            }

            $this->pdo->commit();
            SessionManager::flash('success', $action === 'approve' ? 'Pembayaran disetujui.' : 'Pembayaran ditolak.');

        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            SessionManager::flash('error', 'Gagal memverifikasi: ' . $e->getMessage());
        }

        $this->redirect('/finance');
    }
}