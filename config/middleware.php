<?php
/**
 * config/middleware.php
 * Middleware autentikasi, otorisasi (RBAC), helper, audit log, dan fungsi utilitas
 * Termasuk: release_expired_transactions (auto-kadaluarsa 2 jam), render_barcode_svg
 */
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); // set 1 jika HTTPS
    session_start();
}

/**
 * Escape output HTML (XSS prevention)
 */
function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Format angka ke Rupiah
 */
function format_rupiah(int $nominal): string
{
    return 'Rp ' . number_format($nominal, 0, ',', '.');
}

/**
 * Label role untuk tampilan
 */
function label_role(string $role): string
{
    $roles = [
        'pembeli'           => 'Pembeli (Peserta)',
        'panitia_keuangan'  => 'Panitia Keuangan',
        'petugas_gate'      => 'Petugas Gate',
        'admin_osis'        => 'Admin OSIS',
    ];
    return $roles[$role] ?? $role;
}

/**
 * Label tipe pengguna
 */
function label_tipe_pengguna(string $tipe): string
{
    return $tipe === 'Internal' ? 'Siswa Internal (NIS)' : 'Tamu Eksternal (NIK)';
}

/**
 * Generate CSRF token
 */
function generate_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf_token(?string $token): bool
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Set flash message
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Get & clear flash message
 */
function get_flash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Check login status
 */
function is_logged_in(): bool
{
    return !empty($_SESSION['user']) && !empty($_SESSION['user']['id_user']);
}

/**
 * Get current user data
 */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * Catat jejak aktivitas ke audit_logs
 */
function catat_audit_log(PDO $pdo, int $id_user, string $aktivitas, string $tabel_terkait, string $detail_perubahan): void
{
    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (id_user, aktivitas, tabel_terkait, detail_perubahan, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$id_user, $aktivitas, $tabel_terkait, $detail_perubahan]);
}

/**
 * Auto-kadaluarsa: Ubah pemesanan 'Menunggu Bayar' yang lewat batas_waktu_bayar
 * Kembalikan stok_tersedia, kurangi stok_tertahan, ubah kursi Locked -> Available
 */
function release_expired_transactions(PDO $pdo): void
{
    $stmtExp = $pdo->query("
        SELECT id_pesan, kode_transaksi, id_user, id_jenis, jumlah
        FROM pemesanan
        WHERE status_bayar = 'Menunggu Bayar' AND batas_waktu_bayar < NOW()
    ");
    $expired_rows = $stmtExp->fetchAll();

    foreach ($expired_rows as $trx) {
        try {
            $pdo->beginTransaction();

            // Lock row untuk cek status terbaru
            $stmtLock = $pdo->prepare("SELECT status_bayar FROM pemesanan WHERE id_pesan = ? FOR UPDATE");
            $stmtLock->execute([(int)$trx['id_pesan']]);
            if ($stmtLock->fetchColumn() === 'Menunggu Bayar') {
                // Update status pemesanan
                $updTrx = $pdo->prepare("
                    UPDATE pemesanan
                    SET status_bayar = 'Kadaluarsa', catatan_penolakan = 'Batas waktu pembayaran 2 jam telah habis.'
                    WHERE id_pesan = ?
                ");
                $updTrx->execute([(int)$trx['id_pesan']]);

                // Kembalikan stok tiket
                $updJenis = $pdo->prepare("
                    UPDATE jenis_tiket
                    SET stok_tersedia = stok_tersedia + ?,
                        stok_tertahan = GREATEST(0, stok_tertahan - ?)
                    WHERE id_jenis = ?
                ");
                $updJenis->execute([(int)$trx['jumlah'], (int)$trx['jumlah'], (int)$trx['id_jenis']]);

                // Kembalikan kursi Locked -> Available
                $stmtDet = $pdo->prepare("SELECT id_kursi FROM detail_tiket_satuan WHERE id_pesan = ?");
                $stmtDet->execute([(int)$trx['id_pesan']]);
                foreach ($stmtDet->fetchAll() as $dt) {
                    if (!empty($dt['id_kursi'])) {
                        $updKursi = $pdo->prepare("
                            UPDATE kursi_pensi
                            SET status_kursi = 'Available'
                            WHERE id_kursi = ? AND status_kursi = 'Locked'
                        ");
                        $updKursi->execute([(int)$dt['id_kursi']]);
                    }
                }

                catat_audit_log(
                    $pdo,
                    (int)$trx['id_user'],
                    'EXPIRE_PEMESANAN',
                    'pemesanan',
                    "Pemesanan {$trx['kode_transaksi']} otomatis kadaluarsa. Stok & kursi dikembalikan."
                );
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }
}

/**
 * Middleware: Wajib login
 */
function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('ERROR', 'Akses ditolak! Silakan login terlebih dahulu.');
        header('Location: login.php');
        exit;
    }
}

/**
 * Middleware: RBAC - batasi akses berdasarkan role
 * HTTP 403 jika role tidak diizinkan
 */
function require_role(array $allowed_roles): void
{
    require_login();

    $user = current_user();
    $role = $user['role'] ?? '';

    if (!in_array($role, $allowed_roles, true)) {
        http_response_code(403);
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>403 - Akses Ditolak (RBAC)</title>
        </head>
        <body>
            <table border="2" cellpadding="12" cellspacing="0" width="100%">
                <tr>
                    <td>
                        <h2>AKSES DITOLAK &mdash; HTTP 403 FORBIDDEN (RBAC MIDDLEWARE)</h2>
                        <p>
                            Halo <strong><?= e($user['nama']) ?></strong>, akun Anda terdaftar sebagai
                            <strong><?= e(label_role($role)) ?></strong> sehingga tidak diizinkan membuka halaman ini.
                        </p>
                        <p>
                            Halaman ini hanya diperbolehkan untuk role:
                            <strong><?= e(implode(', ', array_map('label_role', $allowed_roles))) ?></strong>.
                        </p>
                        <hr>
                        <p>
                            <a href="index.php">[ Ke Katalog Pensi ]</a>
                            <?php if ($role === 'admin_osis'): ?>
                                | <a href="dashboard_panitia.php">[ Dashboard Panitia ]</a>
                            <?php elseif ($role === 'panitia_keuangan'): ?>
                                | <a href="verifikasi_pembayaran.php">[ Verifikasi Pembayaran ]</a>
                            <?php elseif ($role === 'petugas_gate'): ?>
                                | <a href="gate_checkin.php">[ Gate Check-in ]</a>
                            <?php else: ?>
                                | <a href="dashboard_peserta.php">[ Dashboard Peserta ]</a>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        <?php
        exit;
    }
}

/**
 * Generator SVG Barcode/QR Vektor Murni Hitam-Putih (Tanpa CSS eksternal)
 * Digunakan untuk E-Ticket cetak
 */
function render_barcode_svg(string $kode_tiket, string $nik_peserta): string
{
    $hash = hash('sha256', $kode_tiket . '|' . $nik_peserta);
    $rects = '';
    $x = 12;
    for ($i = 0; $i < 48; $i++) {
        $val = hexdec($hash[$i]);
        $w = ($val % 3 === 0) ? 4 : (($val % 2 === 0) ? 3 : 2);
        if ($val >= 4) {
            $rects .= '<rect x="' . $x . '" y="10" width="' . $w . '" height="54" fill="#000000"/>';
        }
        $x += $w + 2;
    }
    $total_w = $x + 10;
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $total_w . '" height="88" viewBox="0 0 ' . $total_w . ' 88">'
        . '<rect x="1" y="1" width="' . ($total_w - 2) . '" height="86" fill="#ffffff" stroke="#000000" stroke-width="1.5"/>'
        . $rects
        . '<text x="' . (int)($total_w / 2) . '" y="79" font-family="monospace" font-size="11" font-weight="bold" fill="#000000" text-anchor="middle">'
        . e($kode_tiket)
        . '</text></svg>';
}

/**
 * Generate kode tiket unik: PEN-YYMMDD-XXXXX-N
 */
function generate_kode_tiket(int $nomor_urut): string
{
    return 'PEN-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5)) . '-' . $nomor_urut;
}

/**
 * Generate kode transaksi unik: PSN-YYYYMMDD-XXXXXX
 */
function generate_kode_transaksi(): string
{
    return 'PSN-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

/**
 * Validasi NIK KTP (16 digit) atau NIS (fleksibel, 5-20 digit angka)
 */
function validate_nis_nik(string $value, string $tipe_pengguna): bool
{
    if ($tipe_pengguna === 'Internal') {
        // NIS: 5-20 digit angka
        return (bool)preg_match('/^[0-9]{5,20}$/', $value);
    } else {
        // NIK KTP: tepat 16 digit angka
        return (bool)preg_match('/^[0-9]{16}$/', $value);
    }
}

/**
 * Validasi nomor HP Indonesia
 */
function validate_no_hp(string $hp): bool
{
    $clean = preg_replace('/[\s\-]/', '', $hp);
    return (bool)preg_match('/^(08|\+628)[0-9]{7,13}$/', $clean);
}