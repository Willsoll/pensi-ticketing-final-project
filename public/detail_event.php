<?php
/**
 * detail_event.php - Detail Jenis Tiket & Pemilihan Kursi/Kuantitas
 * Hanya bisa diakses setelah login sebagai pembeli
 * Redesigned with Pensi Design System v1.0
 */
require_once __DIR__ . '/../src/Config/database.php';
require_once __DIR__ . '/../src/Middleware/middleware.php';

release_expired_transactions($pdo);

$id_jenis_raw = $_GET['id_jenis'] ?? $_POST['id_jenis'] ?? 0;
$id_jenis     = is_numeric($id_jenis_raw) ? (int)$id_jenis_raw : 0;
if ($id_jenis <= 0) {
    set_flash('ERROR', 'Jenis tiket tidak valid.');
    header('Location: index.php');
    exit;
}

// Ambil info jenis tiket
$stmtJenis = $pdo->prepare("SELECT * FROM jenis_tiket WHERE id_jenis = ? LIMIT 1");
$stmtJenis->execute([$id_jenis]);
$jenis = $stmtJenis->fetch();

if (!$jenis) {
    set_flash('ERROR', 'Data jenis tiket tidak ditemukan.');
    header('Location: index.php');
    exit;
}

// Jika tipe Seated, ambil daftar kursi
$kursi_list = [];
if ($jenis['tipe_tiket'] === 'Seated') {
    $stmtKursi = $pdo->prepare("SELECT * FROM kursi_pensi WHERE id_jenis = ? ORDER BY nomor_kursi ASC");
    $stmtKursi->execute([$id_jenis]);
    $kursi_list = $stmtKursi->fetchAll();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'lanjut_pemesanan') {
    // Cek login & role pembeli
    if (!is_logged_in()) {
        set_flash('ERROR', 'Silakan login sebagai Pembeli terlebih dahulu.');
        header('Location: login.php');
        exit;
    }
    if ((current_user()['role'] ?? '') !== 'pembeli') {
        set_flash('ERROR', 'Hanya akun Pembeli yang dapat memesan tiket.');
        header('Location: detail_event.php?id_jenis=' . $id_jenis);
        exit;
    }

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan (CSRF) tidak valid.';
    } elseif ((int)$jenis['stok_tersedia'] <= 0) {
        $error = 'Maaf, stok tiket untuk jenis ini sudah habis.';
    } else {
        $user = current_user();

        // MANDAT CEO: Segmentasi Hak Beli Tiket
        $user_tipe = $user['tipe_pengguna']; // 'Internal' atau 'Eksternal'
        $target_pembeli = $jenis['target_pembeli']; // 'Internal', 'Eksternal', 'Semua'

        if ($target_pembeli === 'Internal' && $user_tipe === 'Eksternal') {
            $error = 'Tiket ini hanya untuk Siswa Internal (NIS). Akun Eksternal (NIK) tidak diperbolehkan memesan.';
        } elseif ($target_pembeli === 'Eksternal' && $user_tipe === 'Internal') {
            $error = 'Tiket ini hanya untuk Tamu Eksternal (NIK). Akun Siswa Internal (NIS) tidak diperbolehkan memesan.';
        }

        if (empty($error)) {
            if ($jenis['tipe_tiket'] === 'Seated') {
                // Pilih kursi
                $kursi_ids = $_POST['kursi_ids'] ?? [];
                if (!is_array($kursi_ids) || count($kursi_ids) < 1) {
                    $error = 'Wajib memilih minimal 1 nomor kursi yang tersedia.';
                } elseif (count($kursi_ids) > 5) {
                    $error = 'Maksimal pemilihan 5 kursi per transaksi.';
                } elseif (count($kursi_ids) > (int)$jenis['stok_tersedia']) {
                    $error = 'Jumlah kursi melebihi sisa stok tersedia (' . (int)$jenis['stok_tersedia'] . ').';
                } else {
                    $clean_ids = array_map('intval', $kursi_ids);
                    $ph = implode(',', array_fill(0, count($clean_ids), '?'));
                    $cekK = $pdo->prepare("
                        SELECT id_kursi, nomor_kursi FROM kursi_pensi
                        WHERE id_jenis = ? AND status_kursi = 'Available' AND id_kursi IN ({$ph})
                        ORDER BY nomor_kursi ASC
                    ");
                    $cekK->execute(array_merge([$id_jenis], $clean_ids));
                    $valid_seats = $cekK->fetchAll();

                    if (count($valid_seats) !== count($clean_ids)) {
                        $error = 'Sebagian kursi yang dipilih sudah tidak tersedia. Silakan pilih ulang.';
                    } else {
                        // Simpan draft ke session
                        $_SESSION['pensi_booking_draft'] = [
                            'id_jenis'    => $id_jenis,
                            'tipe_tiket'  => 'Seated',
                            'qty'         => count($valid_seats),
                            'seats'       => $valid_seats,
                        ];
                        header('Location: pemesanan.php');
                        exit;
                    }
                }
            } else {
                // Free Standing - input kuantitas
                $qty_raw = $_POST['qty'] ?? 0;
                $qty = is_numeric($qty_raw) ? (int)$qty_raw : 0;
                if ($qty < 1 || $qty > 5) {
                    $error = 'Jumlah tiket minimal 1 dan maksimal 5 lembar per transaksi.';
                } elseif ($qty > (int)$jenis['stok_tersedia']) {
                    $error = 'Jumlah tiket melebihi sisa stok tersedia (' . (int)$jenis['stok_tersedia'] . ' tiket).';
                } else {
                    $_SESSION['pensi_booking_draft'] = [
                        'id_jenis'    => $id_jenis,
                        'tipe_tiket'  => 'Free Standing',
                        'qty'         => $qty,
                        'seats'       => [],
                    ];
                    header('Location: pemesanan.php');
                    exit;
                }
            }
        }
    }
}

$page_title = 'Detail & Pemesanan - ' . $jenis['nama_jenis'];
require_once __DIR__ . '/../src/Views/layouts/header.php';
?>

<div class="breadcrumb">
    <a href="index.php">Katalog Acara</a> > <a href="detail_event.php?id_jenis=<?= $id_jenis ?>"><?= e($jenis['nama_jenis']) ?></a>
</div>

<div class="card card-elevated" style="margin-bottom: var(--space-6);">
    <div class="card-header">
        <h2 class="card-title" style="margin: 0;"><?= e($jenis['nama_jenis']) ?></h2>
        <div style="display: flex; gap: var(--space-3); align-items: center;">
            <?php 
            $target_badge = $jenis['target_pembeli'] === 'Internal' ? 'badge-primary' : ($jenis['target_pembeli'] === 'Eksternal' ? 'badge-accent' : 'badge-success');
            $tipe_badge = $jenis['tipe_tiket'] === 'Seated' ? 'badge-success' : 'badge-primary';
            ?>
            <span class="badge <?= $target_badge ?>">Target: <?= e($jenis['target_pembeli']) ?></span>
            <span class="badge <?= $tipe_badge ?>"><?= e($jenis['tipe_tiket']) ?></span>
        </div>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-1 grid-cols-4" style="gap: var(--space-4);">
            <div class="stat-card stat-card-primary">
                <div class="stat-card-label">Harga Satuan</div>
                <div class="stat-card-value" style="color: var(--color-primary);"><?= format_rupiah((int)$jenis['harga']) ?></div>
            </div>
            <div class="stat-card stat-card-success">
                <div class="stat-card-label">Total Stok</div>
                <div class="stat-card-value"><?= (int)$jenis['stok_tiket'] ?></div>
            </div>
            <div class="stat-card stat-card-info">
                <div class="stat-card-label">Sisa Tersedia</div>
                <div class="stat-card-value" style="color: var(--color-success);"><?= (int)$jenis['stok_tersedia'] ?></div>
            </div>
            <div class="stat-card stat-card-warning">
                <div class="stat-card-label">Tertahan (Locked)</div>
                <div class="stat-card-value"><?= (int)$jenis['stok_tertahan'] ?></div>
            </div>
        </div>
    </div>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-error" role="alert" style="margin-bottom: var(--space-6);">
        <div class="alert-icon">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
        </div>
        <div class="alert-content">
            <div class="alert-message"><?= e($error) ?></div>
        </div>
    </div>
<?php endif; ?>

<?php if (!is_logged_in()): ?>
    <div class="card card-elevated" style="background: var(--color-info-bg); border-color: var(--color-info-border); margin-bottom: var(--space-6);">
        <div class="card-body">
            <div class="flex items-center gap-4">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-info)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    <circle cx="12" cy="10" r="3"/>
                </svg>
                <div>
                    <strong style="color: var(--color-info);">Silakan login terlebih dahulu</strong>
                    <p style="margin-top: var(--space-1); color: var(--color-text-secondary);">Anda harus login sebagai Pembeli untuk memesan tiket.</p>
                </div>
            </div>
            <div style="margin-top: var(--space-4);">
                <a href="login.php" class="btn btn-primary">Login Sebagai Pembeli</a>
            </div>
        </div>
    </div>
<?php else: 
    $user = current_user();
    // Cek segmentasi
    $can_buy = true;
    $seg_msg = '';
    $seg_type = 'success';
    if ($jenis['target_pembeli'] === 'Internal' && $user['tipe_pengguna'] === 'Eksternal') {
        $can_buy = false;
        $seg_msg = 'Tiket ini hanya untuk Siswa Internal (NIS). Akun Anda terdaftar sebagai Tamu Eksternal (NIK).';
        $seg_type = 'error';
    } elseif ($jenis['target_pembeli'] === 'Eksternal' && $user['tipe_pengguna'] === 'Internal') {
        $can_buy = false;
        $seg_msg = 'Tiket ini hanya untuk Tamu Eksternal (NIK). Akun Anda terdaftar sebagai Siswa Internal (NIS).';
        $seg_type = 'error';
    } else {
        $seg_msg = 'Akun Anda (' . label_tipe_pengguna($user['tipe_pengguna']) . ') diperbolehkan memesan tiket ini.';
        $seg_type = 'success';
    }
?>
    <div class="alert alert-<?= $seg_type ?>" role="alert" style="margin-bottom: var(--space-6);">
        <div class="alert-icon">
            <?php if ($seg_type === 'success'): ?>
                <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            <?php else: ?>
                <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
            <?php endif; ?>
        </div>
        <div class="alert-content">
            <div class="alert-message"><?= e($seg_msg) ?></div>
        </div>
    </div>
<?php endif; ?>

<?php if ((int)$jenis['stok_tersedia'] <= 0): ?>
    <div class="alert alert-error" role="alert" style="margin-bottom: var(--space-6);">
        <div class="alert-icon">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
        </div>
        <div class="alert-content">
            <div class="alert-message">Stok tiket untuk jenis ini sudah habis (Sold Out).</div>
        </div>
    </div>
<?php elseif (is_logged_in() && $can_buy): ?>

<form method="POST" action="detail_event.php" novalidate>
    <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
    <input type="hidden" name="aksi" value="lanjut_pemesanan">
    <input type="hidden" name="id_jenis" value="<?= $id_jenis ?>">

    <div class="card card-elevated" style="margin-bottom: var(--space-6);">
        <div class="card-header">
            <h3 class="card-title">Konfigurasi Pemesanan</h3>
        </div>
        <div class="card-body">

            <?php if ($jenis['tipe_tiket'] === 'Seated'): ?>
                <p style="margin-bottom: var(--space-4); color: var(--color-text-secondary);">
                    <strong>Denah Kursi Interaktif (Tipe: Seated):</strong> Centang nomor kursi berstatus 
                    <span class="badge badge-success">Available</span> (maksimal 5 kursi).
                </p>

                <div class="stage-indicator" aria-label="Panggung utama">
                    [ PANGGUNG UTAMA / LAYAR PRESENTASI ]
                </div>

                <?php if (empty($kursi_list)): ?>
                    <div class="alert alert-warning" role="alert">
                        <div class="alert-icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        </div>
                        <div class="alert-content">
                            <div class="alert-message">Nomor kursi untuk jenis ini belum dibuat oleh Admin OSIS.</div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="seat-map" role="list" aria-label="Pemilihan kursi">
                        <?php foreach ($kursi_list as $kr): 
                            $cls = 'seat seat-' . strtolower($kr['status_kursi']);
                        ?>
                            <div class="<?= $cls ?>" role="listitem" tabindex="0">
                                <?php if ($kr['status_kursi'] === 'Available'): ?>
                                    <label style="cursor: pointer; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                                        <input type="checkbox" name="kursi_ids[]" value="<?= (int)$kr['id_kursi'] ?>" class="seat-cb" style="position: absolute; opacity: 0; pointer-events: none;">
                                        <strong><?= e($kr['nomor_kursi']) ?></strong>
                                        <span style="font-size: 10px; color: var(--color-success);">[Available]</span>
                                    </label>
                                <?php elseif ($kr['status_kursi'] === 'Locked'): ?>
                                    <del><?= e($kr['nomor_kursi']) ?></del>
                                    <span style="font-size: 10px; color: var(--color-warning);">[Locked]</span>
                                <?php else: ?>
                                    <del><?= e($kr['nomor_kursi']) ?></del>
                                    <span style="font-size: 10px; color: var(--color-error);">[Sold]</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p style="margin-top: var(--space-4); font-size: var(--text-xs); color: var(--color-text-muted);">
                        Keterangan: 
                        <span class="badge badge-success">Available</span> = Tersedia | 
                        <span class="badge badge-warning">Locked</span> = Sedang dibayar orang lain | 
                        <span class="badge badge-error">Sold</span> = Terjual.
                    </p>
                <?php endif; ?>

            <?php else: ?>
                <p style="margin-bottom: var(--space-4); color: var(--color-text-secondary);">
                    <strong>Tipe Venue: Free Standing</strong> (Tanpa nomor kursi tetap).
                </p>
                <div class="form-group">
                    <label for="qty" class="form-label">Jumlah Tiket Dipesan (1 - 5 lembar)</label>
                    <input type="number" id="qty" name="qty" class="form-input" min="1" max="<?= min(5, max(1, (int)$jenis['stok_tersedia'])) ?>" value="1" required style="max-width: 140px;">
                </div>
            <?php endif; ?>

            <hr style="margin: var(--space-6) 0;">

            <div class="flex justify-end">
                <button type="submit" class="btn btn-cta" style="font-size: var(--text-base); padding: var(--space-4) var(--space-8);">
                    <strong>LANJUT KE PENGISIAN DATA PEMEGANG TIKET (IDENTITY BINDING) →</strong>
                </button>
            </div>
        </div>
    </div>
</form>

<?php endif; ?>

<script>
function validasiPilihKursiQty() {
    var seatCbs = document.querySelectorAll('input.seat-cb');
    if (seatCbs.length > 0) {
        var checked = document.querySelectorAll('input.seat-cb:checked');
        if (checked.length === 0) {
            alert('Wajib memilih minimal 1 nomor kursi yang tersedia!');
            return false;
        }
        if (checked.length > 5) {
            alert('Maksimal pemilihan 5 kursi per transaksi.');
            return false;
        }
    } else {
        var qtyEl = document.getElementById('qty');
        if (qtyEl) {
            var q = parseInt(qtyEl.value, 10);
            if (isNaN(q) || q < 1 || q > 5) {
                alert('Jumlah tiket harus antara 1 hingga 5.');
                return false;
            }
        }
    }
    return true;
}
</script>

<?php require_once __DIR__ . '/../src/Views/layouts/footer.php'; ?>
