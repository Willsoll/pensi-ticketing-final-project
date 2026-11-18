<?php
/**
 * detail_event.php - Detail Jenis Tiket & Pemilihan Kursi/Kuantitas
 * Hanya bisa diakses setelah login sebagai pembeli
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/middleware.php';

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
require_once __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb">
    <a href="index.php">Katalog Acara</a> > <a href="detail_event.php?id_jenis=<?= $id_jenis ?>"><?= e($jenis['nama_jenis']) ?></a>
</div>

<div class="card">
    <div class="card-header">
        <h2 style="margin: 0;"><?= e($jenis['nama_jenis']) ?></h2>
        <div style="display: flex; gap: 12px; align-items: center;">
            <span class="badge badge-<?= $jenis['target_pembeli'] === 'Internal' ? 'internal' : ($jenis['target_pembeli'] === 'Eksternal' ? 'eksternal' : 'available') ?>">
                Target: <?= e($jenis['target_pembeli']) ?>
            </span>
            <span class="badge badge-<?= $jenis['tipe_tiket'] === 'Seated' ? 'available' : 'belum-masuk' ?>">
                <?= e($jenis['tipe_tiket']) ?>
            </span>
        </div>
    </div>
    
    <table style="margin-bottom: 20px;">
        <tr>
            <td style="width: 180px; border: none;"><strong>Harga Satuan</strong></td>
            <td style="border: none;"><strong style="font-size: 1.25rem; color: #4F46E5;"><?= format_rupiah((int)$jenis['harga']) ?></strong></td>
        </tr>
        <tr>
            <td style="border: none;"><strong>Total Stok</strong></td>
            <td style="border: none;"><?= (int)$jenis['stok_tiket'] ?></td>
        </tr>
        <tr>
            <td style="border: none;"><strong>Sisa Tersedia</strong></td>
            <td style="border: none;"><strong style="color: #16A34A;"><?= (int)$jenis['stok_tersedia'] ?></strong></td>
        </tr>
        <tr>
            <td style="border: none;"><strong>Tertahan (Locked)</strong></td>
            <td style="border: none;"><?= (int)$jenis['stok_tertahan'] ?></td>
        </tr>
    </table>
</div>

<?php if ($error !== ''): ?>
    <div class="flash ERROR"><?= e($error) ?></div>
<?php endif; ?>

<?php if (!is_logged_in()): ?>
    <div class="card" style="background: #EFF6FF; border-color: #BFDBFE;">
        <strong style="color: #1E40AF;">🔐 Silakan <a href="login.php">login sebagai Pembeli</a> terlebih dahulu untuk memesan tiket.</strong>
    </div>
<?php else: 
    $user = current_user();
    // Cek segmentasi
    $can_buy = true;
    $seg_msg = '';
    if ($jenis['target_pembeli'] === 'Internal' && $user['tipe_pengguna'] === 'Eksternal') {
        $can_buy = false;
        $seg_msg = '❌ Tiket ini hanya untuk Siswa Internal (NIS). Akun Anda terdaftar sebagai Tamu Eksternal (NIK).';
    } elseif ($jenis['target_pembeli'] === 'Eksternal' && $user['tipe_pengguna'] === 'Internal') {
        $can_buy = false;
        $seg_msg = '❌ Tiket ini hanya untuk Tamu Eksternal (NIK). Akun Anda terdaftar sebagai Siswa Internal (NIS).';
    } else {
        $seg_msg = '✅ Akun Anda (' . label_tipe_pengguna($user['tipe_pengguna']) . ') diperbolehkan memesan tiket ini.';
    }
?>
    <div class="card" style="background: <?= $can_buy ? '#F0FDF4' : '#FEF2F2' ?>; border-color: <?= $can_buy ? '#BBF7D0' : '#FECACA' ?>;">
        <strong style="color: <?= $can_buy ? '#166534' : '#991B1B' ?>;"><?= e($seg_msg) ?></strong>
    </div>
<?php endif; ?>

<?php if ((int)$jenis['stok_tersedia'] <= 0): ?>
    <div class="flash ERROR">❌ Stok tiket untuk jenis ini sudah habis (Sold Out).</div>
<?php elseif (is_logged_in() && $can_buy): ?>

<form method="POST" action="detail_event.php" onsubmit="return validasiPilihKursiQty();" novalidate>
    <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
    <input type="hidden" name="aksi" value="lanjut_pemesanan">
    <input type="hidden" name="id_jenis" value="<?= $id_jenis ?>">

    <div class="card">
        <div class="card-header">
            <h3 style="margin: 0;">Konfigurasi Pemesanan</h3>
        </div>

        <?php if ($jenis['tipe_tiket'] === 'Seated'): ?>
            <p><strong>Denah Kursi Interaktif (Tipe: Seated):</strong> Centang nomor kursi berstatus <span class="badge badge-available">Available</span> (maksimal 5 kursi).</p>
            
            <div style="text-align: center; padding: 12px; background: #F3F4F6; border-radius: 6px; margin-bottom: 16px; font-weight: 700; color: #4F46E5;">
                [ PANGGUNG UTAMA / LAYAR PRESENTASI ]
            </div>

            <?php if (empty($kursi_list)): ?>
                <p style="color: #6B7280;">Nomor kursi untuk jenis ini belum dibuat oleh Admin OSIS.</p>
            <?php else: ?>
                <div class="seat-map">
                    <?php foreach ($kursi_list as $kr): 
                        $cls = 'seat seat-' . strtolower($kr['status_kursi']);
                        $label_cls = '';
                    ?>
                        <div class="<?= $cls ?>">
                            <?php if ($kr['status_kursi'] === 'Available'): ?>
                                <label>
                                    <input type="checkbox" name="kursi_ids[]" value="<?= (int)$kr['id_kursi'] ?>" class="seat-cb">
                                    <strong><?= e($kr['nomor_kursi']) ?></strong><br>
                                    <small>[Available]</small>
                                </label>
                            <?php elseif ($kr['status_kursi'] === 'Locked'): ?>
                                <input type="checkbox" disabled>
                                <del><?= e($kr['nomor_kursi']) ?></del><br>
                                <small>[Locked]</small>
                            <?php else: ?>
                                <input type="checkbox" disabled>
                                <del><?= e($kr['nomor_kursi']) ?></del><br>
                                <small>[Sold]</small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p><small>Keterangan: <span class="badge badge-available">Available</span> = Tersedia | <span class="badge badge-locked">Locked</span> = Sedang dibayar orang lain | <span class="badge badge-sold">Sold</span> = Terjual.</small></p>
            <?php endif; ?>

        <?php else: ?>
            <p><strong>Tipe Venue: Free Standing</strong> (Tanpa nomor kursi tetap).</p>
            <label for="qty"><strong>Jumlah Tiket Dipesan (1 - 5 lembar):</strong></label>
            <input type="number" id="qty" name="qty" min="1" max="<?= min(5, max(1, (int)$jenis['stok_tersedia'])) ?>" value="1" required style="max-width: 120px;">
        <?php endif; ?>

        <hr style="margin: 20px 0;">

        <?php if (!is_logged_in()): ?>
            <p><a href="login.php" class="button">[ Login Sebagai Pembeli Dulu ]</a></p>
        <?php endif; ?>

        <button type="submit" class="success" style="font-size: 1rem; padding: 14px 28px;">
            <strong>LANJUT KE PENGISIAN DATA PEMEGANG TIKET (IDENTITY BINDING) &rarr;</strong>
        </button>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>