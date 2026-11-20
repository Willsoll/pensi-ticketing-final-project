<?php
// src/Controllers/GateController.php
// Gate check-in and audit log - FIXED

namespace App\Controllers;

use App\Repositories\ETicketRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;
use App\Middleware\AuthMiddleware;
use App\Services\BusinessRulesService;
use App\Services\AuditLogger;
use App\Validation\InputValidator;
use App\Security\SessionManager;
use App\Middleware\CsrfMiddleware;

class GateController extends BaseController
{
    private ETicketRepository $etiketRepo;
    private TransactionRepository $transactionRepo;
    private UserRepository $userRepo;

    public function __construct()
    {
        parent::__construct();
        $this->etiketRepo = new ETicketRepository($this->pdo);
        $this->transactionRepo = new TransactionRepository($this->pdo);
        $this->userRepo = new UserRepository($this->pdo);
    }

    public function index(): void
    {
        AuthMiddleware::requireRole(['gate_officer', 'admin_eo', 'finance']);

        $tickets = $this->etiketRepo->findPaidForCheckin();

        $this->view('gate/index', [
            'tickets' => $tickets,
            'pageTitle' => 'Gate Check-in & Audit Log',
        ]);
    }

    public function checkin(): void
    {
        AuthMiddleware::requireRole(['gate_officer', 'admin_eo']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/gate');
            return;
        }

        // CSRF validation
        if (!CsrfMiddleware::validate()) {
            SessionManager::flash('error', 'Token keamanan tidak valid.');
            $this->redirect('/gate');
            return;
        }

        $kodeTiket = strtoupper(trim($this->input('kode_tiket', '')));
        $nikInput = trim($this->input('nik_konfirmasi', ''));

        if (empty($kodeTiket) || empty($nikInput)) {
            SessionManager::flash('error', 'Kode tiket dan NIK wajib diisi.');
            $this->redirect('/gate');
            return;
        }

        $user = $this->user();
        if (!$user) {
            SessionManager::flash('error', 'Sesi tidak valid. Silakan login ulang.');
            $this->redirect('/gate');
            return;
        }

        $result = \App\Services\BusinessRulesService::processGateCheckin(
            $this->pdo,
            $kodeTiket,
            $nikInput,
            $user['id_user']
        );

        if ($result['success']) {
            SessionManager::flash('success', $result['message']);
        } else {
            if (!empty($result['already_checked_in'])) {
                SessionManager::flash('error', $result['message']);
            } else {
                SessionManager::flash('error', $result['message']);
            }
        }

        $this->redirect('/gate');
    }

    public function audit(): void
    {
        AuthMiddleware::requireRole(['gate_officer', 'admin_eo', 'finance']);

        $entity = $this->input('entity', '');
        $limit = (int)$this->input('limit', 100);

        $pdo = $this->pdo;

        $where = [];
        $params = [];

        if ($entity !== '') {
            $where[] = 'al.entitas = ?';
            $params[] = $entity;
        }

        $sql = "
            SELECT al.*, u.nama_lengkap as nama, u.role
            FROM audit_logs al
            JOIN users u ON al.id_user = u.id_user
        ";

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " ORDER BY al.waktu DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll();

        // Get entities for filter dropdown
        $entities = $this->pdo->query("
            SELECT DISTINCT entitas FROM audit_logs ORDER BY entitas
        ")->fetchAll(\PDO::FETCH_COLUMN);

        $this->view('gate/audit', [
            'logs' => $logs,
            'entities' => $entities,
            'selectedEntity' => $entity,
            'pageTitle' => 'Audit Log Sistem',
        ]);
    }
}