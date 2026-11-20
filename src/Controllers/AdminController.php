<?php
// src/Controllers/AdminController.php
// Admin EO dashboard and management - FIXED

namespace App\Controllers;

use App\Repositories\EventRepository;
use App\Repositories\TicketTierRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\VoucherRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\ETicketRepository;
use App\Middleware\AuthMiddleware;
use App\Services\AuditLogger;
use App\Validation\InputValidator;
use App\Services\InventoryService;
use App\Security\SessionManager;
use App\Middleware\CsrfMiddleware;

class AdminController extends BaseController
{
    private EventRepository $eventRepo;
    private TicketTierRepository $tierRepo;
    private CategoryRepository $categoryRepo;
    private VoucherRepository $voucherRepo;
    private TransactionRepository $transactionRepo;
    private ETicketRepository $etiketRepo;

    public function __construct()
    {
        parent::__construct();
        $this->eventRepo = new EventRepository($this->pdo);
        $this->tierRepo = new TicketTierRepository($this->pdo);
        $this->categoryRepo = new CategoryRepository($this->pdo);
        $this->voucherRepo = new VoucherRepository($this->pdo);
        $this->transactionRepo = new TransactionRepository($this->pdo);
        $this->etiketRepo = new ETicketRepository($this->pdo);
    }

    public function dashboard(): void
    {
        AuthMiddleware::requireRole(['admin_eo', 'finance']);

        $stats = $this->transactionRepo->getDashboardStats();
        $segmentStats = $this->transactionRepo->getSegmentStats();
        $checkinStats = (new \App\Repositories\ETicketRepository($this->pdo))->getCheckinStats();
        $recentOrders = $this->transactionRepo->getRecent(10);

        $this->view('admin/dashboard', [
            'stats' => $stats,
            'segmentStats' => $segmentStats,
            'checkinStats' => $checkinStats,
            'recentOrders' => $recentOrders,
            'pageTitle' => 'Dashboard Panitia OSIS & Keuangan',
        ]);
    }

    // Event CRUD
    public function events(): void
    {
        AuthMiddleware::requireRole('admin_eo');

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $this->input('action') === 'create') {
            $this->createEvent();
            return;
        }

        $events = $this->eventRepo->findAll();
        $categories = (new \App\Repositories\CategoryRepository($this->pdo))->findAllOrdered();

        $this->view('admin/events', [
            'events' => $events,
            'categories' => $categories,
            'pageTitle' => 'Kelola Event',
        ]);
    }

    private function createEvent(): void
    {
        // CSRF validation
        if (!CsrfMiddleware::validate()) {
            SessionManager::flash('error', 'Token keamanan tidak valid.');
            $this->redirect('/admin/events');
            return;
        }

        $errors = $this->validate([
            'judul_event' => 'required|max:200',
            'id_kat_event' => 'required|integer',
            'penyelenggara' => 'required|max:200',
            'lokasi' => 'required|max:300',
            'tipe_venue' => 'required|in:Seated,Free Standing',
            'tgl_mulai' => 'required',
            'tgl_selesai' => 'required',
        ]);

        if (!empty($errors)) {
            SessionManager::flash('error', implode(' ', $errors));
            $this->redirect('/admin/events');
            return;
        }

        $eventId = $this->eventRepo->create([
            'id_kat_event' => (int)$this->input('id_kat_event'),
            'judul_event' => trim($this->input('judul_event')),
            'penyelenggara' => trim($this->input('penyelenggara')),
            'lokasi' => trim($this->input('lokasi')),
            'tipe_venue' => $this->input('tipe_venue'),
            'tgl_mulai' => $this->input('tgl_mulai'),
            'tgl_selesai' => $this->input('tgl_selesai'),
            'deskripsi' => trim($this->input('deskripsi', '')),
            'rundown' => trim($this->input('rundown', '')),
            'status_event' => 'Available',
        ]);

        $user = $this->user();
        if ($user) {
            AuditLogger::logCreate($user['id_user'], 'events', $eventId, "Event dibuat: " . $this->input('judul_event'));
        }

        SessionManager::flash('success', 'Event berhasil dibuat.');
        $this->redirect('/admin/events');
    }

    public function editEvent(int $id): void
    {
        AuthMiddleware::requireRole('admin_eo');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // CSRF validation
            if (!CsrfMiddleware::validate()) {
                SessionManager::flash('error', 'Token keamanan tidak valid.');
                $this->redirect('/admin/events');
                return;
            }

            $errors = $this->validate([
                'judul_event' => 'required|max:200',
                'id_kat_event' => 'required|integer',
                'penyelenggara' => 'required|max:200',
                'lokasi' => 'required|max:300',
                'tipe_venue' => 'required|in:Seated,Free Standing',
                'tgl_mulai' => 'required',
                'tgl_selesai' => 'required',
            ]);

            if (!empty($errors)) {
                SessionManager::flash('error', implode(' ', $errors));
                $this->redirect('/admin/events');
                return;
            }

            $this->eventRepo->update($id, [
                'id_kat_event' => (int)$this->input('id_kat_event'),
                'judul_event' => trim($this->input('judul_event')),
                'penyelenggara' => trim($this->input('penyelenggara')),
                'lokasi' => trim($this->input('lokasi')),
                'tipe_venue' => $this->input('tipe_venue'),
                'tgl_mulai' => $this->input('tgl_mulai'),
                'tgl_selesai' => $this->input('tgl_selesai'),
                'deskripsi' => trim($this->input('deskripsi', '')),
                'rundown' => trim($this->input('rundown', '')),
            ]);

            $user = $this->user();
            if ($user) {
                AuditLogger::logUpdate($user['id_user'], 'events', $id, "Event diperbarui");
            }
            SessionManager::flash('success', 'Event diperbarui.');
            $this->redirect('/admin/events');
            return;
        }

        $event = $this->eventRepo->find($id);
        $categories = (new \App\Repositories\CategoryRepository($this->pdo))->findAllOrdered();

        $this->view('admin/edit_event', [
            'event' => $event,
            'categories' => $categories,
            'pageTitle' => 'Edit Event',
        ]);
    }

    public function deleteEvent(int $id): void
    {
        AuthMiddleware::requireRole('admin_eo');

        // CSRF validation
        if (!CsrfMiddleware::validate()) {
            SessionManager::flash('error', 'Token keamanan tidak valid.');
            $this->redirect('/admin/events');
            return;
        }

        // Check if has transactions
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM transaksi WHERE id_event = ?");
        $stmt->execute([$id]);
        if ((int)$stmt->fetchColumn() > 0) {
            SessionManager::flash('error', 'Tidak bisa menghapus event yang sudah punya transaksi.');
            $this->redirect('/admin/events');
            return;
        }

        $this->eventRepo->delete($id);
        $user = $this->user();
        if ($user) {
            AuditLogger::logDelete($user['id_user'], 'events', $id, "Event dihapus");
        }
        SessionManager::flash('success', 'Event dihapus.');
        $this->redirect('/admin/events');
    }

    // Ticket Tier CRUD
    public function tiers(int $eventId = null): void
    {
        AuthMiddleware::requireRole('admin_eo');

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $this->input('action') === 'create') {
            $this->createTier();
            return;
        }

        $tierRepo = new \App\Repositories\TicketTierRepository($this->pdo);
        $events = $this->eventRepo->findActive();

        if ($eventId) {
            $tiers = $tierRepo->findByEvent($eventId);
        } else {
            // Get all tiers with event info
            $stmt = $this->pdo->query("
                SELECT tt.*, e.judul_event
                FROM tiket_tier tt
                JOIN events e ON tt.id_event = e.id_event
                ORDER BY e.tgl_mulai, tt.harga
            ");
            $tiers = $stmt->fetchAll();
        }

        $events = $this->eventRepo->findActive();

        $this->view('admin/tiers', [
            'tiers' => $tiers,
            'events' => $events,
            'selectedEvent' => $eventId,
            'pageTitle' => 'Kelola Tier Tiket',
        ]);
    }

    private function createTier(): void
    {
        // CSRF validation
        if (!CsrfMiddleware::validate()) {
            SessionManager::flash('error', 'Token keamanan tidak valid.');
            $this->redirect('/admin/tiers');
            return;
        }

        $errors = $this->validate([
            'id_event' => 'required|integer',
            'nama_tier' => 'required|max:100',
            'harga' => 'required|integer|min:0',
            'kuota_total' => 'required|integer|min:1',
            'batas_jual' => 'required',
        ]);

        if (!empty($errors)) {
            SessionManager::flash('error', implode(' ', $errors));
            $this->redirect('/admin/tiers');
            return;
        }

        $tierId = $this->tierRepo->create([
            'id_event' => (int)$this->input('id_event'),
            'nama_tier' => trim($this->input('nama_tier')),
            'harga' => (int)$this->input('harga'),
            'kuota_total' => (int)$this->input('kuota_total'),
            'batas_jual' => $this->input('batas_jual'),
        ]);

        // Auto-generate seats if Seated
        $event = $this->eventRepo->find((int)$this->input('id_event'));
        if ($event && $event['tipe_venue'] === 'Seated') {
            \App\Services\InventoryService::generateSeats($this->pdo, $tierId, (int)$this->input('kuota_total'));
        }

        $user = $this->user();
        if ($user) {
            AuditLogger::logCreate($user['id_user'], 'tiket_tier', $tierId, "Tier tiket dibuat");
        }
        SessionManager::flash('success', 'Tier tiket berhasil dibuat.');
        $this->redirect('/admin/tiers');
    }

    // Seat Generator
    public function seats(int $tierId): void
    {
        AuthMiddleware::requireRole('admin_eo');

        $tier = $this->tierRepo->find($tierId);
        $seats = $this->tierRepo->getSeats($tierId);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // CSRF validation
            if (!CsrfMiddleware::validate()) {
                SessionManager::flash('error', 'Token keamanan tidak valid.');
                $this->redirect('/admin/seats/' . $tierId);
                return;
            }

            $action = $this->input('action');

            if ($action === 'generate') {
                $count = (int)$this->input('count', 0);
                // Validate count
                if ($count < 1 || $count > 1000) {
                    SessionManager::flash('error', 'Jumlah kursi harus 1-1000.');
                    $this->redirect('/admin/seats/' . $tierId);
                    return;
                }
                $prefix = trim($this->input('prefix', 'SEAT'));

                \App\Services\InventoryService::generateSeats($this->pdo, $tierId, $count, $prefix);
                $user = $this->user();
                if ($user) {
                    AuditLogger::log($user['id_user'], 'SEAT_GENERATE', 'kursi_event', "Generated {$count} seats for tier {$tierId}");
                }
                SessionManager::flash('success', "{$count} kursi berhasil dibuat.");
            } elseif ($action === 'update_status') {
                $seatId = (int)$this->input('seat_id');
                $status = $this->input('status');

                if (in_array($status, ['Available', 'Locked', 'Sold'])) {
                    $stmt = $this->pdo->prepare("UPDATE kursi_event SET status_kursi = ? WHERE id_kursi = ?");
                    $stmt->execute([$status, $seatId]);
                    $user = $this->user();
                    if ($user) {
                        AuditLogger::logUpdate($user['id_user'], 'kursi_event', $seatId, "Status kursi diubah ke {$status}");
                    }
                }
            }

            $this->redirect('/admin/seats/' . $tierId);
            return;
        }

        $this->view('admin/seats', [
            'tier' => $tier,
            'seats' => $seats,
            'pageTitle' => 'Generator & Kelola Kursi',
        ]);
    }

    // Voucher CRUD
    public function vouchers(): void
    {
        AuthMiddleware::requireRole('admin_eo');

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $this->input('action') === 'create') {
            $this->createVoucher();
            return;
        }

        $vouchers = (new \App\Repositories\VoucherRepository($this->pdo))->findAllWithUsage();

        $this->view('admin/vouchers', [
            'vouchers' => $vouchers,
            'pageTitle' => 'Kelola Voucher Promo',
        ]);
    }

    private function createVoucher(): void
    {
        // CSRF validation
        if (!CsrfMiddleware::validate()) {
            SessionManager::flash('error', 'Token keamanan tidak valid.');
            $this->redirect('/admin/vouchers');
            return;
        }

        $errors = $this->validate([
            'kode_voucher' => 'required|max:50',
            'tipe_potongan' => 'required|in:Persen,Nominal',
            'nilai_potongan' => 'required|integer|min:1',
            'min_transaksi' => 'required|integer|min:0',
            'kuota_pakai' => 'required|integer|min:1',
            'expired_date' => 'required',
        ]);

        if (!empty($errors)) {
            SessionManager::flash('error', implode(' ', $errors));
            $this->redirect('/admin/vouchers');
            return;
        }

        $voucherRepo = new \App\Repositories\VoucherRepository($this->pdo);

        if ($voucherRepo->exists('kode_voucher', strtoupper($this->input('kode_voucher')))) {
            SessionManager::flash('error', 'Kode voucher sudah digunakan.');
            $this->redirect('/admin/vouchers');
            return;
        }

        $voucherRepo->create([
            'kode_voucher' => strtoupper($this->input('kode_voucher')),
            'tipe_potongan' => $this->input('tipe_potongan'),
            'nilai_potongan' => (int)$this->input('nilai_potongan'),
            'min_transaksi' => (int)$this->input('min_transaksi'),
            'kuota_pakai' => (int)$this->input('kuota_pakai'),
            'expired_date' => $this->input('expired_date'),
        ]);

        $user = $this->user();
        if ($user) {
            AuditLogger::logCreate($user['id_user'], 'voucher_promo', $this->pdo->lastInsertId(), "Voucher dibuat: " . $this->input('kode_voucher'));
        }
        SessionManager::flash('success', 'Voucher berhasil dibuat.');
        $this->redirect('/admin/vouchers');
    }

    public function analytics(): void
    {
        AuthMiddleware::requireRole(['admin_eo', 'finance']);

        $segmentStats = (new \App\Repositories\TransactionRepository($this->pdo))->getSegmentStats();
        $checkinStats = (new \App\Repositories\ETicketRepository($this->pdo))->getCheckinStats();

        // Daily sales chart data
        $pdo = $this->pdo;
        $stmt = $pdo->query("
            SELECT DATE(created_at) as date, 
                SUM(CASE WHEN status_bayar = 'Paid' THEN total_bayar ELSE 0 END) as revenue,
                SUM(CASE WHEN status_bayar = 'Paid' THEN 1 ELSE 0 END) as orders
            FROM transaksi
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $dailySales = $stmt->fetchAll();

        $this->view('admin/analytics', [
            'segmentStats' => $segmentStats,
            'checkinStats' => $checkinStats,
            'dailySales' => $dailySales,
            'pageTitle' => 'Analitik & Laporan',
        ]);
    }
}