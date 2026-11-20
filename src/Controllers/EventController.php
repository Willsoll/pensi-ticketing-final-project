<?php
// src/Controllers/EventController.php
// Event detail and ticket selection (Matches ROMBEL 3 schema)

namespace App\Controllers;

use App\Repositories\EventRepository;
use App\Repositories\TicketTierRepository;
use App\Services\BusinessRulesService;
use App\Middleware\AuthMiddleware;
use App\Services\AuditLogger;
use App\Validation\InputValidator;
use App\Security\SessionManager;

class EventController extends BaseController
{
    private EventRepository $eventRepo;
    private TicketTierRepository $tierRepo;

    public function __construct()
    {
        parent::__construct();
        $this->eventRepo = new EventRepository($this->pdo);
        $this->tierRepo = new TicketTierRepository($this->pdo);
    }

    public function detail(int $id): void
    {
        $event = $this->eventRepo->find($id);

        if (!$event) {
            SessionManager::flash('error', 'Event tidak ditemukan.');
            $this->redirect('/');
            return;
        }

        $tiers = $this->tierRepo->findAvailableForEvent($id);

        $selectedTier = null;
        if ($this->input('tier_id')) {
            $selectedTier = $this->tierRepo->find((int)$this->input('tier_id'));
        }

        $seats = [];
        if ($selectedTier && $selectedTier['tipe_venue'] === 'Seated') {
            $seats = $this->tierRepo->getAvailableSeats($selectedTier['id_tier']);
        }

        // Get rundown
        $rundown = $this->getRundown($event['rundown'] ?? '');

        $this->view('event/detail', [
            'event' => $event,
            'tiers' => $tiers,
            'selectedTier' => $selectedTier,
            'seats' => $seats,
            'rundown' => $rundown,
            'pageTitle' => $event['judul_event'],
        ]);
    }

    public function selectTier(): void
    {
        $this->auth();
        AuthMiddleware::requireRole('customer');

        $errors = $this->validate([
            'tier_id' => 'required|integer',
            'seat_ids' => 'array', // For seated events
            'quantity' => 'integer|min:1|max:5', // For free standing
        ]);

        $tierId = (int)$this->input('tier_id');
        $tier = $this->tierRepo->find($tierId);

        if (!$tier) {
            SessionManager::flash('error', 'Tier tiket tidak valid.');
            $this->redirect('/event/' . $this->input('event_id'));
            return;
        }

        // No tipe_pengguna in new schema - all customers can buy any tier
        // Segmentasi is handled at checkout via NIK validation if needed

        $seatIds = $this->input('seat_ids', []);
        $quantity = (int)$this->input('quantity', 1);

        if ($tier['tipe_venue'] === 'Seated') {
            if (empty($seatIds)) {
                SessionManager::flash('error', 'Wajib memilih minimal 1 kursi.');
                $this->redirect('/event/' . $tier['id_event'] . '?tier_id=' . $tierId);
                return;
            }

            if (count($seatIds) > 5) {
                SessionManager::flash('error', 'Maksimal 5 kursi per transaksi.');
                $this->redirect('/event/' . $tier['id_event'] . '?tier_id=' . $tierId);
                return;
            }

            // Validate seat availability
            $cleanIds = array_map('intval', $seatIds);
            $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));

            $stmt = $this->pdo->prepare("
                SELECT id_kursi, nomor_kursi
                FROM kursi_event
                WHERE id_tier = ? AND status_kursi = 'Available' AND id_kursi IN (" . implode(',', array_fill(0, count($cleanIds), '?')) . ")
            ");
            $stmt->execute(array_merge([$tierId], $cleanIds));
            $validSeats = $stmt->fetchAll();

            if (count($validSeats) !== count($cleanIds)) {
                SessionManager::flash('error', 'Beberapa kursi sudah tidak tersedia.');
                $this->redirect('/event/' . $tier['id_event'] . '?tier_id=' . $tierId);
                return;
            }

            $qty = count($validSeats);
            $seats = $validSeats;
        } else {
            if ($quantity < 1 || $quantity > 5) {
                SessionManager::flash('error', 'Jumlah tiket 1-5.');
                $this->redirect('/event/' . $tier['id_event'] . '?tier_id=' . $tierId);
                return;
            }

            if ($quantity > (int)$tier['kuota_tersedia']) {
                SessionManager::flash('error', 'Stok tidak mencukupi.');
                $this->redirect('/event/' . $tier['id_event'] . '?tier_id=' . $tierId);
                return;
            }

            $qty = $quantity;
            $seats = [];
        }

        // Check ticket limit (Rule 2)
        if (!\App\Services\BusinessRulesService::checkTicketLimit($this->pdo, $this->user()['id_user'], $tier['id_event'], $tierId, $qty)) {
            SessionManager::flash('error', 'Maksimal 5 tiket per event per akun/NIK.');
            $this->redirect('/event/' . $tier['id_event'] . '?tier_id=' . $tierId);
            return;
        }

        // Store in session for next step
        SessionManager::set('booking_draft', [
            'tier_id' => $tierId,
            'event_id' => $tier['id_event'],
            'qty' => $qty,
            'seats' => $seats,
            'tipe_venue' => $tier['tipe_venue'],
        ]);

        $this->redirect('/booking/identity');
    }

    private function getRundown(string $rundownText): array
    {
        if (empty($rundownText)) return [];

        $lines = explode("\n", $rundownText);
        $rundown = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Parse "HH:MM - HH:MM : Description" or "HH:MM : Description"
            if (preg_match('/(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})\s*:\s*(.+)/', $line, $matches)) {
                $rundown[] = [
                    'jam_mulai' => $matches[1],
                    'jam_selesai' => $matches[2],
                    'deskripsi' => trim($matches[3]),
                ];
            } elseif (preg_match('/(\d{2}:\d{2})\s*:\s*(.+)/', $line, $matches)) {
                $rundown[] = [
                    'jam_mulai' => $matches[1],
                    'jam_selesai' => '',
                    'deskripsi' => trim($matches[2]),
                ];
            } else {
                $rundown[] = [
                    'jam_mulai' => '',
                    'jam_selesai' => '',
                    'deskripsi' => $line,
                ];
            }
        }

        return $rundown;
    }
}