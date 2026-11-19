<?php
/**
 * gate_checkin.php - Gate Check-in Validator & Audit Log Sistem
 * Role: petugas_gate, admin_osis (check-in), admin_osis/panitia_keuangan/petugas_gate (audit log)
 * Anti-passback: cek status_bayar='Lunas' & status_checkin='Belum Masuk'
 * Scan kedua menampilkan peringatan merah "TIKET SUDAH DIGUNAKAN"
 */
require_once __DIR__ . '/../src/Config/database.php';
require_once __DIR__ . '/../src/Middleware/middleware.php';

// RBAC: Check-in hanya petugas_gate & admin_osis
// Audit log bisa diakses: petugas_gate, admin_osis, panitia_keuangan
$current_role = current_user()['role'] ?? '';
$can_checkin = in_array($current_role, ['petugas_gate', 'admin_osis'], true);
$can_audit = in_array($current_role, ['petugas_gate', 'admin_osis', 'panitia_keuangan'], true);

if (!$can_audit) {
    require_role(['admin_osis']); // fallback to 403
}
release_expired_transactions($pdo);

$user = current_user();
$kode_raw  = $_GET['kode_tiket'] ?? $_POST['kode_tiket'] ?? '';
$kode_cari = is_string($kode_raw) ? mb_substr(strtoupper(trim($kode_raw)), 0, 50) : '';
$tiket_found = null;

// ===== PROSES CHECK-IN =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'proses_checkin') {
    if (!$can_checkin) {
        set_flash('ERROR', 'Hanya Petugas Gate atau Admin OSIS yang diizinkan melakukan validasi check-in pintu masuk.');
        header('Location: gate_checkin.php');
        exit;
    }

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('ERROR', 'Token keamanan (CSRF) tidak valid.');
        header('Location: gate_checkin.php');
        exit;
    }

    $kode_post = strtoupper(trim((string)($_POST['kode_tiket'] ?? '')));
    $kode_post = mb_substr($kode_post, 0, 50);
    $nik_input = trim((string)($_POST['nik_konfirmasi'] ?? ''));
    if ($kode_post === '' || $nik_input === '' || mb_strlen($nik_input) > 20) {
        set_flash('ERROR', 'Input kode tiket atau NIS/NIK tidak valid.');
        header('Location: gate_checkin.php');
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmtLock = $pdo->prepare("
            SELECT dts.*, p.status_bayar, p.kode_transaksi, j.nama_jenis, j.tipe_tiket
            FROM detail_tiket_satuan dts
            JOIN pemesanan p ON dts.id_pesan = p.id_pesan
            JOIN jenis_tiket j ON p.id_jenis = j.id_jenis
            WHERE dts.kode_tiket_unik = ?
            FOR UPDATE
        ");
        $stmtLock->execute([$kode_post]);
        $dt = $stmtLock->fetch();

        if (!$dt) {
            throw new DomainException("Kode E-Ticket '{$kode_post}' tidak ditemukan di basis data.");
        }
        if ($dt['status_bayar'] !== 'Lunas') {
            throw new DomainException("Pembayaran E-Ticket ini berstatus {$dt['status_bayar']} (belum Lunas). Akses masuk ditolak!");
        }
        if ($dt['status_checkin'] === 'Sudah Masuk') {
            throw new DomainException("PERINGATAN! E-Ticket {$kode_post} SUDAH CHECK-IN pada {$dt['waktu_checkin']}. Tolak pemakaian ulang!");
        }
        if ($nik_input !== $dt['nik_pemegang_tiket']) {
            throw new DomainException("NIK/NIS KTP peserta tidak sesuai dengan data E-Ticket!");
        }

        $upd = $pdo->prepare("
            UPDATE detail_tiket_satuan
            SET status_checkin = 'Sudah Masuk', waktu_checkin = NOW(), id_petugas_gate = ?
            WHERE id_detail_tiket = ?
        ");
        $upd->execute([(int)$user['id_user'], (int)$dt['id_detail_tiket']]);

        catat_audit_log(
            $pdo,
            (int)$user['id_user'],
            'GATE_CHECKIN',
            'detail_tiket_satuan',
            "Check-in pintu masuk berhasil untuk kode {$kode_post} ({$dt['nama_jenis']}) atas nama {$dt['nama_pemegang_tiket']} (NIK/NIS: {$dt['nik_pemegang_tiket']})."
        );

        $pdo->commit();
        set_flash('SUKSES', "VALIDASI GATE BERHASIL! Peserta {$dt['nama_pemegang_tiket']} ({$kode_post}) tercatat Sudah Masuk.");
        header('Location: gate_checkin.php?kode_tiket=' . urlencode($kode_post));
        exit;

    } catch (DomainException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_flash('ERROR', $e->getMessage());
        header('Location: gate_checkin.php?kode_tiket=' . urlencode($kode_post));
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[GATE_CHECKIN] ' . $e->getMessage());
        set_flash('ERROR', 'Terjadi kesalahan sistem saat memproses check-in. Silakan coba lagi.');
        header('Location: gate_checkin.php?kode_tiket=' . urlencode($kode_post));
        exit;
    }
}

// ===== CARI TIKET UNTUK TAMPILAN =====
if ($kode_cari !== '') {
    $stmtFind = $pdo->prepare("
        SELECT 
            dts.*,
            p.kode_transaksi, p.status_bayar, p.id_user,
            u.nama as nama_pembeli, u.tipe_pengguna,
            j.nama_jenis, j.tipe_tiket,
            kp.nomor_kursi,
            pg.nama as nama_petugas
        FROM detail_tiket_satuan dts
        JOIN pemesanan p ON dts.id_pesan = p.id_pesan
        JOIN users u ON p.id_user = u.id_user
        JOIN jenis_tiket j ON p.id_jenis = j.id_jenis
        LEFT JOIN kursi_pensi kp ON dts.id_kursi = kp.id_kursi
        LEFT JOIN users pg ON dts.id_petugas_gate = pg.id_user
        WHERE dts.kode_tiket_unik = ?
        LIMIT 1
    ");
    $stmtFind->execute([$kode_cari]);
    $tiket_found = $stmtFind->fetch();
}

// ===== DAFTAR E-TICKET LUNAS UNTUK REFERENSI SCAN CEPAT =====
$stmtPaid = $pdo->query("
    SELECT dts.kode_tiket_unik, dts.nama_pemegang_tiket, dts.nik_pemegang_tiket, dts.status_checkin, 
           j.nama_jenis, kp.nomor_kursi, p.kode_transaksi
    FROM detail_tiket_satuan dts
    JOIN pemesanan p ON dts.id_pesan = p.id_pesan
    JOIN jenis_tiket j ON p.id_jenis = j.id_jenis
    LEFT JOIN kursi_pensi kp ON dts.id_kursi = kp.id_kursi
    WHERE p.status_bayar = 'Lunas'
    ORDER BY dts.status_checkin ASC, dts.id_detail_tiket ASC
");
$tiket_paid_list = $stmtPaid->fetchAll();

// ===== AUDIT LOGS =====
$f_tabel = is_string($_GET['tabel_terkait'] ?? null) ? trim($_GET['tabel_terkait']) : '';
$whereLog = [];
$paramsLog = [];
if ($f_tabel !== '') {
    $whereLog[] = "al.tabel_terkait = ?";
    $paramsLog[] = $f_tabel;
}
$sqlLog = "
    SELECT al.*, u.nama, u.role
    FROM audit_logs al
    JOIN users u ON al.id_user = u.id_user
";
if (!empty($whereLog)) {
    $sqlLog .= " WHERE " . implode(' AND ', $whereLog);
}
$sqlLog .= " ORDER BY al.created_at DESC LIMIT 100";

$stmtLog = $pdo->prepare($sqlLog);
$stmtLog->execute($paramsLog);
$audit_logs = $stmtLog->fetchAll();

$page_title = 'Gate Check-in & Audit Log - Pensi School';
require_once __DIR__ . '/../src/Views/layouts/header.php';
?>

<div class="breadcrumb">
    <a href="index.php">Katalog</a> > 
    <?php if ($user['role'] === 'admin_osis'): ?>
        <a href="dashboard_panitia.php">Dashboard Panitia</a> > 
    <?php elseif ($user['role'] === 'panitia_keuangan'): ?>
        <a href="verifikasi_pembayaran.php">Verifikasi Pembayaran</a> > 
    <?php endif; ?>
    Gate Check-in & Audit Log
</div>

<h2 style="color: #4F46E5; margin-bottom: 8px;">Gate Check-In Validator & Audit Log Sistem</h2>

<?php if ($can_checkin): ?>
    <!-- ===== BAGIAN 1: PEMINDAIAN E-TIKET DI PINTU MASUK ===== -->
    <form method="GET" action="gate_checkin.php">
        <div class="card">
            <div class="card-header">
                <h3 style="margin: 0;">1. Pemindaian / Input Nomor Seri E-Ticket di Pintu Masuk</h3>
            </div>
            <div style="display: flex; gap: 12px; align-items: end; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 250px;">
                    <label for="kode_tiket"><strong>Kode / Nomor Seri E-Ticket:</strong></label>
                    <input type="text" id="kode_tiket" name="kode_tiket" value="<?= e($kode_cari) ?>" size="35" placeholder="Contoh: PEN-241212-A1B2C-1" required style="font-family: monospace; font-size: 1rem;">
                </div>
                <button type="submit" class="button" style="font-size: 1rem; padding: 12px 24px;"><strong>Periksa Tiket</strong></button>
                <a href="gate_checkin.php" class="button secondary" style="font-size: 0.875rem;">[ Reset ]</a>
            </div>
        </div>
    </form>

    <br>

    <?php if ($kode_cari !== ''): ?>
        <?php if (!$tiket_found): ?>
            <div class="card" style="border-color: #E11D48; background: #FEF2F2;">
                <div class="card-header">
                    <h3 style="margin: 0; color: #991B1B;">âŒ Hasil Pemindaian</h3>
                </div>
                <p style="color: #991B1B; font-size: 1.1rem;"><strong>TIDAK VALID:</strong> E-Ticket dengan kode <code><?= e($kode_cari) ?></code> tidak ditemukan.</p>
            </div>
        <?php else: ?>
            <?php 
            $is_valid_for_checkin = $tiket_found['status_bayar'] === 'Lunas' && $tiket_found['status_checkin'] === 'Belum Masuk';
            $is_already_checked_in = $tiket_found['status_checkin'] === 'Sudah Masuk';
            $is_not_paid = $tiket_found['status_bayar'] !== 'Lunas';
            ?>
            
            <div class="card" style="border-color: <?= $is_already_checked_in ? '#E11D48' : ($is_not_paid ? '#F59E0B' : '#16A34A') ?>; 
                            background: <?= $is_already_checked_in ? '#FEF2F2' : ($is_not_paid ? '#FFFBEB' : '#F0FDF4') ?>;">
                <div class="card-header">
                    <h3 style="margin: 0; color: <?= $is_already_checked_in ? '#991B1B' : ($is_not_paid ? '#92400E' : '#166534') ?>;">
                        <?= $is_already_checked_in ? 'âš ï¸ TIKET SUDAH DIGUNAKAN (ANTI-PASSBACK)' : ($is_not_paid ? 'â³ BELUM LUNAS' : 'âœ… VALID UNTUK CHECK-IN') ?>
                    </h3>
                </div>
                
                <table style="border: none; width: 100%;">
                    <tr style="border: none;">
                        <td style="width: 200px; border: none;"><strong>Kode E-Ticket</strong></td>
                        <td style="border: none;"><code style="font-size: 1.1rem;"><strong><?= e($tiket_found['kode_tiket_unik']) ?></strong></code></td>
                    </tr>
                    <tr style="border: none;">
                        <td style="border: none;"><strong>Jenis Tiket</strong></td>
                        <td style="border: none;"><strong><?= e($tiket_found['nama_jenis']) ?></strong> (<?= e($tiket_found['tipe_tiket']) ?>)</td>
                    </tr>
                    <tr style="border: none;">
                        <td style="border: none;"><strong>Nomor Kursi / Zona</strong></td>
                        <td style="border: none;"><strong><?= $tiket_found['nomor_kursi'] ? 'Kursi ' . e($tiket_found['nomor_kursi']) : 'Free Standing' ?></strong></td>
                    </tr>
                    <tr style="border: none;">
                        <td style="border: none;"><strong>Nama Peserta</strong></td>
                        <td style="border: none;"><strong style="font-size: 1.1rem;"><?= e($tiket_found['nama_pemegang_tiket']) ?></strong></td>
                    </tr>
                    <tr style="border: none;">
                        <td style="border: none;"><strong>NIS / NIK KTP</strong></td>
                        <td style="border: none;"><code style="font-size: 1.1rem;"><strong><?= e($tiket_found['nik_pemegang_tiket']) ?></strong></code></td>
                    </tr>
                    <tr style="border: none;">
                        <td style="border: none;"><strong>Pembeli (Pemesan)</strong></td>
                        <td style="border: none;"><?= e($tiket_found['nama_pembeli']) ?> (<?= e(label_tipe_pengguna($tiket_found['tipe_pengguna'])) ?>)</td>
                    </tr>
                    <tr style="border: none;">
                        <td style="border: none;"><strong>Kode Transaksi</strong></td>
                        <td style="border: none;"><code><?= e($tiket_found['kode_transaksi']) ?></code></td>
                    </tr>
                    <tr style="border: none;">
                        <td style="border: none;"><strong>Status Pembayaran</strong></td>
                        <td style="border: none;">
                            <span class="badge badge-<?= strtolower(str_replace(' ', '-', $tiket_found['status_bayar'])) ?>">
                                <?= e(strtoupper($tiket_found['status_bayar'])) ?>
                            </span>
                        </td>
                    </tr>
                    <tr style="border: none;">
                        <td style="border: none;"><strong>Status Kehadiran</strong></td>
                        <td style="border: none;">
                            <span class="badge badge-<?= $tiket_found['status_checkin'] === 'Sudah Masuk' ? 'sudah-masuk' : 'belum-masuk' ?>" style="font-size: 1rem; padding: 8px 16px;">
                                <?= e(strtoupper($tiket_found['status_checkin'])) ?>
                            </span>
                            <?php if ($is_already_checked_in): ?>
                                <br><small style="color: #E11D48;">
                                    Check-In: <?= e(date('d-m-Y H:i:s', strtotime($tiket_found['waktu_checkin']))) ?> 
                                    (Petugas: <?= e($tiket_found['nama_petugas'] ?? '-') ?>)
                                </small>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <?php if ($is_valid_for_checkin): ?>
                    <hr style="margin: 20px 0;">
                    <form method="POST" action="gate_checkin.php">
                        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                        <input type="hidden" name="aksi" value="proses_checkin">
                        <input type="hidden" name="kode_tiket" value="<?= e($tiket_found['kode_tiket_unik']) ?>">
                        
                        <div style="display: flex; gap: 12px; align-items: end; flex-wrap: wrap;">
                            <div style="flex: 1; min-width: 250px;">
                                <label for="nik_konfirmasi"><strong>Konfirmasi NIS / NIK KTP Peserta (Wajib Sama):</strong></label>
                                <input type="text" id="nik_konfirmasi" name="nik_konfirmasi" value="<?= e($tiket_found['nik_pemegang_tiket']) ?>" 
                                       required pattern="[0-9]{5,20}" maxlength="20" size="30" style="font-family: monospace; font-size: 1rem;">
                            </div>
                            <button type="submit" class="success" style="font-size: 1.1rem; padding: 14px 28px;">
                                <strong>KONFIRMASI CHECK-IN & IZINKAN MASUK</strong>
                            </button>
                        </div>
                    </form>
                    
                <?php elseif ($is_already_checked_in): ?>
                    <div style="background: #FEE2E2; border: 2px solid #E11D48; border-radius: 8px; padding: 20px; text-align: center; margin-top: 16px;">
                        <h3 style="color: #991B1B; margin-bottom: 8px;">ðŸš« TIKET SUDAH DIGUNAKAN</h3>
                        <p style="color: #991B1B; font-size: 1.1rem;">
                            E-Ticket ini sudah berstatus <strong>SUDAH MASUK</strong> pada 
                            <strong><?= e(date('d-m-Y H:i:s', strtotime($tiket_found['waktu_checkin']))) ?></strong> 
                            oleh petugas <strong><?= e($tiket_found['nama_petugas'] ?? '-') ?></strong>.
                        </p>
                        <p style="color: #991B1B; margin-top: 8px;">
                            <strong>TOLAK PEMAKAIAN ULANG!</strong> Peserta ini sudah masuk ke venue.
                        </p>
                    </div>
                <?php elseif ($is_not_paid): ?>
                    <div style="background: #FEF3C7; border: 2px solid #F59E0B; border-radius: 8px; padding: 20px; text-align: center; margin-top: 16px;">
                        <h3 style="color: #92400E; margin-bottom: 8px;">â³ PEMBAYARAN BELUM LUNAS</h3>
                        <p style="color: #92400E;">
                            Status pembayaran: <strong><?= e(strtoupper($tiket_found['status_bayar'])) ?></strong>. 
                            Peserta harus menyelesaikan pembayaran dan diverifikasi Panitia Keuangan terlebih dahulu.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <br>
    <?php endif; ?>

    <!-- ===== DAFTAR E-TICKET LUNAS UNTUK SIMULASI SCAN CEPAT ===== -->
    <div class="card">
        <div class="card-header">
            <h3 style="margin: 0;">Daftar E-Ticket Berstatus LUNAS (Klik untuk Scan Cepat)</h3>
        </div>
        <div style="max-height: 400px; overflow-y: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Kode E-Ticket</th>
                        <th>Jenis Tiket</th>
                        <th>Nama Peserta</th>
                        <th>NIS / NIK</th>
                        <th>Kursi</th>
                        <th style="text-align: center;">Status Check-In</th>
                        <th style="text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tiket_paid_list as $tp): ?>
                        <tr style="background: <?= $tp['status_checkin'] === 'Sudah Masuk' ? '#FEF2F2' : 'white' ?>;">
                            <td><code><?= e($tp['kode_tiket_unik']) ?></code></td>
                            <td><?= e($tp['nama_jenis']) ?></td>
                            <td><strong><?= e($tp['nama_pemegang_tiket']) ?></strong></td>
                            <td><code><?= e($tp['nik_pemegang_tiket']) ?></code></td>
                            <td align="center"><?= $tp['nomor_kursi'] ? e($tp['nomor_kursi']) : 'Free Standing' ?></td>
                            <td align="center">
                                <span class="badge badge-<?= $tp['status_checkin'] === 'Sudah Masuk' ? 'sudah-masuk' : 'belum-masuk' ?>">
                                    <?= e($tp['status_checkin']) ?>
                                </span>
                            </td>
                            <td align="center">
                                <a href="gate_checkin.php?kode_tiket=<?= urlencode($tp['kode_tiket_unik']) ?>" 
                                   class="button" style="font-size: 0.75rem; padding: 4px 10px;">
                                    [ Scan / Periksa ]
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($tiket_paid_list)): ?>
                        <tr><td colspan="7" align="center" style="color: #6B7280; padding: 40px;">Belum ada tiket berstatus Lunas.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <br>
    <hr>
<?php endif; ?>

<!-- ===== BAGIAN 2: LOG RIWAYAT AKTIVITAS (AUDIT LOGS) ===== -->
<div class="card">
    <div class="card-header">
        <h3 style="margin: 0;">2. Log Riwayat Aktivitas Perubahan Data Sensitif (audit_logs)</h3>
    </div>
    
    <form method="GET" action="gate_checkin.php" style="margin-bottom: 16px;">
        <div style="display: flex; gap: 12px; align-items: end; flex-wrap: wrap;">
            <div>
                <label for="tabel_terkait"><strong>Filter Entitas / Tabel:</strong></label>
                <select id="tabel_terkait" name="tabel_terkait" style="min-width: 220px;">
                    <option value="">-- Semua Entitas --</option>
                    <option value="pemesanan" <?= $f_tabel === 'pemesanan' ? 'selected' : '' ?>>pemesanan</option>
                    <option value="detail_tiket_satuan" <?= $f_tabel === 'detail_tiket_satuan' ? 'selected' : '' ?>>detail_tiket_satuan</option>
                    <option value="jenis_tiket" <?= $f_tabel === 'jenis_tiket' ? 'selected' : '' ?>>jenis_tiket</option>
                    <option value="kursi_pensi" <?= $f_tabel === 'kursi_pensi' ? 'selected' : '' ?>>kursi_pensi</option>
                    <option value="kode_promo" <?= $f_tabel === 'kode_promo' ? 'selected' : '' ?>>kode_promo</option>
                    <option value="users" <?= $f_tabel === 'users' ? 'selected' : '' ?>>users</option>
                    <option value="rundown_acara" <?= $f_tabel === 'rundown_acara' ? 'selected' : '' ?>>rundown_acara</option>
                </select>
            </div>
            <button type="submit" class="button"><strong>Filter Log</strong></button>
            <a href="gate_checkin.php" class="button secondary">[ Reset ]</a>
        </div>
    </form>

    <br>

    <div style="max-height: 500px; overflow-y: auto;">
        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">ID</th>
                    <th style="width: 16%;">Waktu</th>
                    <th style="width: 20%;">Pengguna (Role)</th>
                    <th style="width: 18%;">Aktivitas</th>
                    <th style="width: 12%;">Tabel</th>
                    <th>Detail Perubahan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($audit_logs as $lg): ?>
                    <tr>
                        <td align="center">#<?= (int)$lg['id_log'] ?></td>
                        <td align="center"><small><?= e(date('d-m-Y H:i:s', strtotime($lg['created_at']))) ?></small></td>
                        <td>
                            <strong><?= e($lg['nama']) ?></strong><br>
                            <small>[<?= e(label_role($lg['role'])) ?>]</small>
                        </td>
                        <td><code><?= e($lg['aktivitas']) ?></code></td>
                        <td align="center"><code><?= e($lg['tabel_terkait']) ?></code></td>
                        <td><?= e($lg['detail_perubahan']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($audit_logs)): ?>
                    <tr><td colspan="6" align="center" style="color: #6B7280; padding: 40px;">Belum ada log aktivitas.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../src/Views/layouts/footer.php'; ?>