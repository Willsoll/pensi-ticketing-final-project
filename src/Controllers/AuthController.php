<?php
// src/Controllers/AuthController.php
// Authentication controller - FIXED

namespace App\Controllers;

use App\Repositories\UserRepository;
use App\Security\PasswordManager;
use App\Security\SessionManager;
use App\Middleware\AuthMiddleware;
use App\Services\AuditLogger;
use App\Validation\InputValidator;

class AuthController extends BaseController
{
    private UserRepository $userRepo;
    
    // Allowed redirect paths for security
    private const ALLOWED_REDIRECTS = [
        '/',
        '/my-bookings',
        '/admin',
        '/finance',
        '/gate',
        '/event/',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->userRepo = new UserRepository($this->pdo);
    }

    public function showLogin(): void
    {
        if (AuthMiddleware::isAuthenticated()) {
            $this->redirectByRole();
            return;
        }

        $redirect = $this->validateRedirect($this->input('redirect', '/'));
        $this->view('auth/login', [
            'pageTitle' => 'Login',
            'redirect' => $redirect,
        ]);
    }

    public function login(): void
    {
        if (AuthMiddleware::isAuthenticated()) {
            $this->redirectByRole();
            return;
        }

        $errors = $this->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!empty($errors)) {
            SessionManager::flash('error', 'Email dan password wajib diisi.');
            $this->redirect('/login?redirect=' . urlencode($this->validateRedirect($this->input('redirect', '/'))));
            return;
        }

        $email = trim($this->input('email'));
        $password = $this->input('password');

        $user = $this->userRepo->findByEmail($email);

        if (!$user || !PasswordManager::verify($password, $user['password'])) {
            // FIXED: Don't log user_id when user not found
            AuditLogger::logAuth(0, 'LOGIN_FAILED', "Gagal login untuk email: $email");
            SessionManager::flash('error', 'Email atau password salah.');
            $this->redirect('/login?redirect=' . urlencode($this->validateRedirect($this->input('redirect', '/'))));
            return;
        }

        // Check if password needs rehash
        if (PasswordManager::needsRehash($user['password'])) {
            $newHash = PasswordManager::hash($password);
            $this->userRepo->updatePassword($user['id_user'], $newHash);
        }

        AuthMiddleware::login($user);
        AuditLogger::logAuth($user['id_user'], AuditLogger::ACTION_LOGIN, "Login berhasil");

        $redirect = $this->validateRedirect($this->input('redirect', '/'));
        $this->redirect($redirect);
    }

    public function showRegister(): void
    {
        if (AuthMiddleware::isAuthenticated()) {
            $this->redirectByRole();
            return;
        }

        $this->view('auth/register', [
            'pageTitle' => 'Registrasi',
        ]);
    }

    public function register(): void
    {
        if (AuthMiddleware::isAuthenticated()) {
            $this->redirectByRole();
            return;
        }

        $errors = $this->validate([
            'nama_lengkap' => 'required|min:3|max:150',
            'email' => 'required|email',
            'no_hp' => 'required',
            'nik' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        // Validate NIK (16 digits)
        $nik = $this->input('nik');
        if (!InputValidator::nik($nik)) {
            $errors['nik'] = 'NIK KTP harus tepat 16 digit angka.';
        }

        // Validate phone number
        $no_hp = $this->input('no_hp');
        if (!InputValidator::phoneID($no_hp)) {
            $errors['no_hp'] = 'Nomor HP tidak valid (format Indonesia).';
        }

        // Check unique constraints
        if ($this->userRepo->exists('email', $this->input('email'))) {
            $errors['email'] = 'Email sudah terdaftar.';
        }

        if ($this->userRepo->exists('nik', $nik)) {
            $errors['nik'] = 'NIK sudah terdaftar.';
        }

        // Password strength
        $passwordErrors = PasswordManager::validateStrength($this->input('password'));
        if (!empty($passwordErrors)) {
            $errors['password'] = implode(' ', $passwordErrors);
        }

        if (!empty($errors)) {
            SessionManager::flash('error', 'Silakan periksa kembali data Anda.');
            $this->redirect('/register');
            return;
        }

        // Hash password
        $hashed = PasswordManager::hash($this->input('password'));

        $userId = $this->userRepo->createCustomer([
            'nama_lengkap' => trim($this->input('nama_lengkap')),
            'email' => strtolower(trim($this->input('email'))),
            'no_hp' => trim($this->input('no_hp')),
            'nik' => $nik,
            'password' => $hashed,
        ]);

        AuditLogger::logAuth($userId, AuditLogger::ACTION_LOGIN, "Registrasi customer baru");

        SessionManager::flash('success', 'Registrasi berhasil! Silakan login.');
        $this->redirect('/login');
    }

    public function logout(): void
    {
        $userId = AuthMiddleware::userId();
        if ($userId) {
            AuditLogger::logAuth($userId, AuditLogger::ACTION_LOGOUT, "Logout");
        }
        AuthMiddleware::logout();
        $this->redirect('/');
    }

    private function validateRedirect(string $redirect): string
    {
        // Only allow internal redirects
        foreach (self::ALLOWED_REDIRECTS as $allowed) {
            if ($redirect === $allowed || str_starts_with($redirect, $allowed)) {
                return $redirect;
            }
        }
        return '/'; // Default to home
    }

    private function redirectByRole(): void
    {
        $role = AuthMiddleware::role();
        switch ($role) {
            case 'admin_eo':
                $this->redirect('/admin');
                break;
            case 'finance':
                $this->redirect('/finance');
                break;
            case 'gate_officer':
                $this->redirect('/gate');
                break;
            default:
                $this->redirect('/');
        }
    }
}