<?php
// src/Controllers/BookingController.php
// Booking flow: Identity binding -> Checkout -> Payment - FIXED

namespace App\Controllers;

use App\Repositories\ReservationRepository;
use App\Repositories\TicketTierRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\EventRepository;
use App\Services\BusinessRulesService;
use App\Services\InventoryService;
use App\Services\VoucherService;
use App\Services\PricingService;
use App\Middleware\AuthMiddleware;
use App\Services\AuditLogger;
use App\Validation\InputValidator;
use App\Security\SessionManager;
use App\Middleware\CsrfMiddleware;

class BookingController extends BaseController
{
    private TicketTierRepository $tierRepo;
    private ReservationRepository $reservationRepo;
    private TransactionRepository $transactionRepo;
    private EventRepository $eventRepo;

    public function __construct()
    {
        parent::__construct();
        $this->tierRepo = new TicketTierRepository($this->pdo);
        $this->reservationRepo = new ReservationRepository($this->pdo);
        $this->transactionRepo = new TransactionRepository($this->pdo);
        $this->eventRepo = new EventRepository($this->pdo);
    }

    public function identity(): void
    {
        $this->auth();
        AuthMiddleware::requireRole('customer');

        $draft = SessionManager::get('booking_draft');
        if (!$draft) {
            SessionManager::flash('error', 'Sesi pemesanan tidak ditemukan.');
            $this->redirect('/');
            return;
        }

        $tier = $this->tierRepo->find($draft['tier_id']);
        if (!$tier) {
            SessionManager::flash('error', 'Data tiket tidak valid.');
            $this->redirect('/');
            return;
        }

        $event = $this->eventRepo->find($draft['event_id']);
        $qty = $draft['qty'];
        $seats = $draft['seats'] ?? [];

        // Get active vouchers
        $voucherRepo = new \App\Repositories\VoucherRepository($this->pdo);
        $vouchers = $voucherRepo->findActive();

        // Calculate subtotal
        $subtotal = PricingService::subtotal($tier['harga'], $draft['qty']);

        $this->view('booking/identity', [
            'tier' => $tier,
            'event' => $event,
            'qty' => $draft['qty'],
            'seats' => $draft['seats'],
            'tipe_venue' => $draft['tipe_venue'],
            'subtotal' => $subtotal,
            'vouchers' => $vouchers,
            'pageTitle' => 'Identity Binding',
        ]);
    }

    public function processIdentity(): void
    {
        $this->auth();
        AuthMiddleware::requireRole('customer');

        $draft = SessionManager::get('booking_draft');
        if (!$draft) {
            SessionManager::flash('error', 'Sesi pemesanan tidak ditemukan.');
            $this->redirect('/');
            return;
        }

        $tier = $this->tierRepo->find($draft['tier_id']);
        $qty = $draft['qty'];

        // Validate attendees
        $attendees = [];
        $errors = [];

        for ($i = 0; $i < $qty; $i++) {
            $nama = trim($this->input("nama_peserta_{$i}", ''));
            $nik = trim($this->input("nik_peserta_{$i}", ''));

            if (mb_strlen($nama) < 3) {
                $errors[] = "Tiket #" . ($i + 1) . ": Nama minimal 3 karakter.";
            }

            if (!\App\Validation\InputValidator::nik($nik)) {
                $errors[] = "Tiket #" . ($i + 1) . ": NIK harus 16 digit angka.";
            }

            $attendees[] = [
                'nama' => $nama,
                'nik' => $nik,
            ];
        }

        // Check duplicate NIK
        $niks = array_column($attendees, 'nik');
        if (count($niks) !== count(array_unique($niks))) {
            $errors[] = 'NIK tidak boleh duplikat dalam satu transaksi.';
        }

        if (!empty($errors)) {
            SessionManager::flash('error', implode(' ', $errors));
            $this->redirect('/booking/identity');
            return;
        }

        // Process voucher
        $voucherCode = strtoupper(trim($this->input('voucher_code', '')));
        $voucherId = null;
        $discount = 0;

        if ($voucherCode !== '') {
            $voucherRepo = new \App\Repositories\VoucherRepository($this->pdo);
            $voucher = $voucherRepo->findByCode($voucherCode);

            if ($voucher) {
                $subtotal = PricingService::subtotal($tier['harga'], $qty);
                $validation = \App\Services\VoucherService::validate($this->pdo, $voucherCode, $subtotal);

                if ($validation[0]) {
                    $voucherId = $voucher['id_voucher'];
                    $discount = \App\Services\VoucherService::calculateDiscount($subtotal, $voucher);
                } else {
                    SessionManager::flash('error', $validation[2]);
                    $this->redirect('/booking/identity');
                    return;
                }
            } else {
                SessionManager::flash('error', "Kode voucher tidak valid.");
                $this->redirect('/booking/identity');
                return;
            }
        }

        // Calculate totals
        $subtotal = PricingService::subtotal($tier['harga'], $qty);
        $total = PricingService::total($subtotal, $discount);

        // Check ticket limit (Rule 2) - re-verify
        $user = $this->user();
        if (!\App\Services\BusinessRulesService::checkTicketLimit($this->pdo, $user['id_user'], $tier['id_event'], $tier['id_tier'], $draft['qty'])) {
            SessionManager::flash('error', 'Maksimal 5 tiket per event per akun/NIK.');
            $this->redirect('/booking/identity');
            return;
        }

        // Create reservation with stock locking (Rule 1)
        try {
            $this->pdo->beginTransaction();

            $reservationId = \App\Services\BusinessRulesService::createReservation($this->pdo, [
                'user_id' => $this->user()['id_user'],
                'event_id' => $tier['id_event'],
                'tier_id' => $tier['id_tier'],
                'voucher_id' => $voucherId,
                'quantity' => $draft['qty'],
                'discount' => $discount,
                'total_bayar' => $total,
                'attendees' => $attendees,
                'seat_ids' => $draft['seats'] ?? [],
            ]);

            $this->pdo->commit();

            // Update session
            SessionManager::set('current_reservation', $reservationId);
            SessionManager::forget('booking_draft');

            AuditLogger::log($this->user()['id_user'], 'CREATE_RESERVATION', 'reservations',
                "Reservasi dibuat untuk {$qty} tiket {$tier['nama_tier']}. Total: " . PricingService::formatRupiah($total));

            $this->redirect('/checkout/' . $reservationId);

        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            SessionManager::flash('error', 'Gagal membuat reservasi: ' . $e->getMessage());
            $this->redirect('/booking/identity');
        }
    }

    public function checkout(int $reservationId): void
    {
        $this->auth();
        AuthMiddleware::requireRole('customer');

        $reservation = $this->reservationRepo->findWithItems($reservationId);

        if (!$reservation || $reservation['id_user'] !== $this->user()['id_user']) {
            SessionManager::flash('error', 'Reservasi tidak ditemukan.');
            $this->redirect('/my-bookings');
            return;
        }

        if ($reservation['status'] !== 'Active') {
            SessionManager::flash('error', 'Reservasi tidak valid.');
            $this->redirect('/my-bookings');
            return;
        }

        // Check expiry
        if (strtotime($reservation['batas_bayar_at']) < time()) {
            SessionManager::flash('error', 'Waktu pembayaran sudah habis.');
            $this->redirect('/my-bookings');
            return;
        }

        // Calculate time remaining
        $remaining = max(0, strtotime($reservation['batas_bayar_at']) - time());

        $tier = $this->tierRepo->find($reservation['id_tier']);

        $this->view('checkout/index', [
            'reservation' => $reservation,
            'tier' => $tier,
            'remaining' => $remaining,
            'pageTitle' => 'Checkout & Pembayaran',
        ]);
    }

    public function uploadProof(int $reservationId): void
    {
        $this->auth();
        AuthMiddleware::requireRole('customer');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/checkout/' . $reservationId);
            return;
        }

        // Verify CSRF
        if (!CsrfMiddleware::validate()) {
            SessionManager::flash('error', 'Token keamanan tidak valid.');
            $this->redirect('/checkout/' . $reservationId);
            return;
        }

        $reservation = $this->reservationRepo->find($reservationId);

        if (!$reservation || $reservation['id_user'] !== $this->user()['id_user'] || $reservation['status'] !== 'Active') {
            SessionManager::flash('error', 'Reservasi tidak valid.');
            $this->redirect('/my-bookings');
            return;
        }

        // Check expiry
        if (strtotime($reservation['batas_bayar_at']) < time()) {
            SessionManager::flash('error', 'Waktu pembayaran sudah habis. Reservasi kadaluarsa.');
            $this->redirect('/my-bookings');
            return;
        }

        // Handle file upload
        if (!isset($_FILES['bukti_bayar']) || $_FILES['bukti_bayar']['error'] !== UPLOAD_ERR_OK) {
            SessionManager::flash('error', 'Silakan pilih file bukti transfer.');
            $this->redirect('/checkout/' . $reservationId);
            return;
        }

        $file = $_FILES['bukti_bayar'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

        if ($file['size'] > $maxSize) {
            SessionManager::flash('error', 'Ukuran file maksimal 2MB.');
            $this->redirect('/checkout/' . $reservationId);
            return;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        if (!in_array($mime, $allowedMimes, true)) {
            SessionManager::flash('error', 'Format file harus JPG, PNG, atau WEBP.');
            $this->redirect('/checkout/' . $reservationId);
            return;
        }

        // Generate secure filename
        $ext = match($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $filename = 'bukti_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
        $uploadPath = $this->appConfig['storage_path'] . '/uploads/bukti/' . $filename;

        // Ensure directory exists
        $dir = dirname($uploadPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            SessionManager::flash('error', 'Gagal menyimpan file.');
            $this->redirect('/checkout/' . $reservationId);
            return;
        }

        // Update reservation
        $stmt = $this->pdo->prepare("
            UPDATE reservations
            SET bukti_bayar = ?, status = 'Waiting Verification'
            WHERE id_reservasi = ?
        ");
        $stmt->execute([$filename, $reservationId]);

        AuditLogger::log($this->user()['id_user'], 'PAYMENT_UPLOAD', 'reservations',
            "Bukti pembayaran diunggah untuk reservasi ID {$reservationId}");

        SessionManager::flash('success', 'Bukti pembayaran berhasil diunggah! Menunggu verifikasi keuangan.');
        $this->redirect('/my-bookings');
    }

    public function myBookings(): void
    {
        $this->auth();
        AuthMiddleware::requireRole('customer');

        $bookings = $this->reservationRepo->findByUser($this->user()['id_user']);

        $this->view('booking/my_bookings', [
            'bookings' => $bookings,
            'pageTitle' => 'Pemesanan Saya',
        ]);
    }
}