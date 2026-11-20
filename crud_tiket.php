<?php
/**
 * crud_tiket.php - CRUD Master: Jenis Tiket, Rundown Acara, Kode Promo
 * Hanya role admin_osis
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/middleware.php';

require_role(['admin_osis']);
release_expired_transactions($pdo);

$is_post = $_SERVER['REQUEST_METHOD'] === 'POST';
$action = $_POST['aksi'] ?? '';
$csrf_ok = verify_csrf_token($_POST['csrf_token'] ?? '');
$errors = [];
$success = '';

// ===== HANDLE POST ACTIONS =====
if ($is_post && $csrf_ok) {
    // --- JENIS TIKET ---
    if ($action === 'tambah_jenis') {
        $nama = trim($_POST['nama_jenis'] ?? '');
        $target = $_POST['target_pembeli'] ?? 'Semua';
        $tipe = $_POST['tipe_tiket'] ?? 'Free Standing';
        $harga = (int)($_POST['harga'] ?? 0);
        $stok = (int)($_POST['stok_tiket'] ?? 0);

        if ($nama === '' || $harga <= 0 || $stok <= 0) {
            $errors[] = 'Semua field wajib diisi, harga & stok > 0.';
        } elseif (!in_array($target, ['Internal', 'Eksternal', 'Semua'])) {
            $errors[] = 'Target pembeli tidak valid.';
        } elseif (!in_array($tipe, ['Seated', 'Free Standing'])) {
            $errors[] = 'Tipe tiket tidak valid.';
        } else {
            $ins = $pdo->prepare("INSERT INTO jenis_tiket (nama_jenis, target_pembeli, tipe_tiket, harga, stok_tiket, stok_tersedia, stok_tertahan) VALUES (?, ?, ?, ?, ?, ?, 0)");
            $ins->execute([$nama, $target, $tipe, $harga, $stok, $stok]);
            $id_jenis = (int)$pdo->lastInsertId();
            
            // Jika Seated, generate kursi otomatis
            if ($tipe === 'Seated') {
                for ($i = 1; $i <= $stok; $i++) {
                    $nomor = ($target === 'Internal') ? 'REG-' . str_pad($i, 3, '0', STR_PAD_LEFT) 
                        : (($target === 'Eksternal') ? 'VIP-E-' . str_pad($i, 2, '0', STR_PAD_LEFT)
                        : 'STD-' . str_pad($i, 3, '0', STR_PAD_LEFT));
                    $insK = $pdo->prepare("INSERT INTO kursi_pensi (id_jenis, nomor_kursi, status_kursi) VALUES (?, ?, 'Available')");
                    $insK->execute([$id_jenis, $nomor]);
                }
            }
            
            catat_audit_log($pdo, (int)current_user()['id_user'], 'CREATE_JENIS_TIKET', 'jenis_tiket', "Menambah jenis tiket: {$nama} ({$target}, {$tipe}, " . format_rupiah($harga) . ", Stok: {$stok})");
            $success = 'Jenis tiket berhasil ditambahkan!';
        }
    } elseif ($action === 'edit_jenis') {
        $id = (int)($_POST['id_jenis'] ?? 0);
        $nama = trim($_POST['nama_jenis'] ?? '');
        $target = $_POST['target_pembeli'] ?? 'Semua';
        $tipe = $_POST['tipe_tiket'] ?? 'Free Standing';
        $harga = (int)($_POST['harga'] ?? 0);
        $stok = (int)($_POST['stok_tiket'] ?? 0);

        if ($id > 0 && $nama !== '' && $harga > 0 && $stok > 0) {
            // Get old stok for adjustment
            $old = $pdo->prepare("SELECT stok_tiket, stok_tersedia FROM jenis_tiket WHERE id_jenis = ?");
            $old->execute([$id]);
            $old_data = $old->fetch();
            
            if ($old_data) {
                $diff_stok = $stok - (int)$old_data['stok_tiket'];
                $new_tersedia = max(0, (int)$old_data['stok_tersedia'] + $diff_stok);
                
                $upd = $pdo->prepare("UPDATE jenis_tiket SET nama_jenis=?, target_pembeli=?, tipe_tiket=?, harga=?, stok_tiket=?, stok_tersedia=? WHERE id_jenis=?");
                $upd->execute([$nama, $target, $tipe, $harga, $stok, $new_tersedia, $id]);
                
                // If Seated and was Free Standing, generate kursi
                if ($tipe === 'Seated') {
                    $cekK = $pdo->prepare("SELECT COUNT(*) FROM kursi_pensi WHERE id_jenis = ?");
                    $cekK->execute([$id]);
                    if ((int)$cekK->fetchColumn() === 0) {
                        for ($i = 1; $i <= $stok; $i++) {
                            $nomor = 'STD-' . str_pad($i, 3, '0', STR_PAD_LEFT);
                            $insK = $pdo->prepare("INSERT INTO kursi_pensi (id_jenis, nomor_kursi, status_kursi) VALUES (?, ?, 'Available')");
                            $insK->execute([$id, $nomor]);
                        }
                    }
                }
                
                catat_audit_log($pdo, (int)current_user()['id_user'], 'EDIT_JENIS_TIKET', 'jenis_tiket', "Mengubah jenis tiket ID {$id}: {$nama}");
                $success = 'Jenis tiket berhasil diubah!';
            }
        }
    } elseif ($action === 'hapus_jenis') {
        $id = (int)($_POST['id_jenis'] ?? 0);
        if ($id > 0) {
            // Cek apakah ada pemesanan
            $cek = $pdo->prepare("SELECT COUNT(*) FROM pemesanan WHERE id_jenis = ?");
            $cek->execute([$id]);
            if ((int)$cek->fetchColumn() > 0) {
                $errors[] = 'Tidak bisa hapus: sudah ada pesanan untuk jenis tiket ini.';
            } else {
                $pdo->prepare("DELETE FROM kursi_pensi WHERE id_jenis = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM jenis_tiket WHERE id_jenis = ?")->execute([$id]);
                catat_audit_log($pdo, (int)current_user()['id_user'], 'DELETE_JENIS_TIKET', 'jenis_tiket', "Menghapus jenis tiket ID {$id}");
                $success = 'Jenis tiket berhasil dihapus!';
            }
        }
    }
    
    // --- RUNDOWN ACARA ---
    elseif ($action === 'tambah_rundown') {
        $nama = trim($_POST['nama_penampil'] ?? '');
        $kategori = $_POST['kategori_penampil'] ?? 'Ekskul';
        $mulai = $_POST['jam_mulai'] ?? '';
        $selesai = $_POST['jam_selesai'] ?? '';
        $lokasi = trim($_POST['lokasi_panggung'] ?? '');

        if ($nama === '' || $mulai === '' || $selesai === '' || $lokasi === '') {
            $errors[] = 'Semua field rundown wajib diisi.';
        } elseif (!in_array($kategori, ['Guest Star', 'Ekskul', 'Band Siswa'])) {
            $errors[] = 'Kategori penampil tidak valid.';
        } else {
            $ins = $pdo->prepare("INSERT INTO rundown_acara (nama_penampil, kategori_penampil, jam_mulai, jam_selesai, lokasi_panggung) VALUES (?, ?, ?, ?, ?)");
            $ins->execute([$nama, $kategori, $mulai, $selesai, $lokasi]);
            catat_audit_log($pdo, (int)current_user()['id_user'], 'CREATE_RUNDOWN', 'rundown_acara', "Menambah rundown: {$nama} ({$kategori})");
            $success = 'Rundown berhasil ditambahkan!';
        }
    } elseif ($action === 'edit_rundown') {
        $id = (int)($_POST['id_rundown'] ?? 0);
        $nama = trim($_POST['nama_penampil'] ?? '');
        $kategori = $_POST['kategori_penampil'] ?? 'Ekskul';
        $mulai = $_POST['jam_mulai'] ?? '';
        $selesai = $_POST['jam_selesai'] ?? '';
        $lokasi = trim($_POST['lokasi_panggung'] ?? '');

        if ($id > 0 && $nama !== '' && $mulai !== '' && $selesai !== '' && $lokasi !== '') {
            $upd = $pdo->prepare("UPDATE rundown_acara SET nama_penampil=?, kategori_penampil=?, jam_mulai=?, jam_selesai=?, lokasi_panggung=? WHERE id_rundown=?");
            $upd->execute([$nama, $kategori, $mulai, $selesai, $lokasi, $id]);
            catat_audit_log($pdo, (int)current_user()['id_user'], 'EDIT_RUNDOWN', 'rundown_acara', "Mengubah rundown ID {$id}: {$nama}");
            $success = 'Rundown berhasil diubah!';
        }
    } elseif ($action === 'hapus_rundown') {
        $id = (int)($_POST['id_rundown'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM rundown_acara WHERE id_rundown = ?")->execute([$id]);
            catat_audit_log($pdo, (int)current_user()['id_user'], 'DELETE_RUNDOWN', 'rundown_acara', "Menghapus rundown ID {$id}");
            $success = 'Rundown berhasil dihapus!';
        }
    }
    
    // --- KODE PROMO ---
    elseif ($action === 'tambah_promo') {
        $kode = strtoupper(trim($_POST['kode_voucher'] ?? ''));
        $potongan = (int)($_POST['potongan_nominal'] ?? 0);
        $kuota = (int)($_POST['kuota_voucher'] ?? 0);
        $berlaku = $_POST['berlaku_sampai'] ?? '';

        if ($kode === '' || $potongan <= 0 || $kuota <= 0 || $berlaku === '') {
            $errors[] = 'Semua field promo wajib diisi, potongan & kuota > 0.';
        } elseif (!preg_match('/^[A-Z0-9]{3,20}$/', $kode)) {
            $errors[] = 'Kode voucher 3-20 karakter (huruf besar/angka saja).';
        } else {
            $cek = $pdo->prepare("SELECT COUNT(*) FROM kode_promo WHERE kode_voucher = ?");
            $cek->execute([$kode]);
            if ((int)$cek->fetchColumn() > 0) {
                $errors[] = 'Kode voucher sudah ada.';
            } else {
                $ins = $pdo->prepare("INSERT INTO kode_promo (kode_voucher, potongan_nominal, kuota_voucher, berlaku_sampai) VALUES (?, ?, ?, ?)");
                $ins->execute([$kode, $potongan, $kuota, $berlaku]);
                catat_audit_log($pdo, (int)current_user()['id_user'], 'CREATE_PROMO', 'kode_promo', "Menambah promo: {$kode} (Potongan: " . format_rupiah($potongan) . ", Kuota: {$kuota})");
                $success = 'Kode promo berhasil ditambahkan!';
            }
        }
    } elseif ($action === 'edit_promo') {
        $id = (int)($_POST['id_promo'] ?? 0);
        $kode = strtoupper(trim($_POST['kode_voucher'] ?? ''));
        $potongan = (int)($_POST['potongan_nominal'] ?? 0);
        $kuota = (int)($_POST['kuota_voucher'] ?? 0);
        $berlaku = $_POST['berlaku_sampai'] ?? '';

        if ($id > 0 && $kode !== '' && $potongan > 0 && $kuota > 0 && $berlaku !== '') {
            $upd = $pdo->prepare("UPDATE kode_promo SET kode_voucher=?, potongan_nominal=?, kuota_voucher=?, berlaku_sampai=? WHERE id_promo=?");
            $upd->execute([$kode, $potongan, $kuota, $berlaku, $id]);
            catat_audit_log($pdo, (int)current_user()['id_user'], 'EDIT_PROMO', 'kode_promo', "Mengubah promo ID {$id}: {$kode}");
            $success = 'Kode promo berhasil diubah!';
        }
    } elseif ($action === 'hapus_promo') {
        $id = (int)($_POST['id_promo'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM kode_promo WHERE id_promo = ?")->execute([$id]);
            catat_audit_log($pdo, (int)current_user()['id_user'], 'DELETE_PROMO', 'kode_promo', "Menghapus promo ID {$id}");
            $success = 'Kode promo berhasil dihapus!';
        }
    }
}

// ===== FETCH DATA FOR DISPLAY =====
$stmtJenis = $pdo->query("SELECT * FROM jenis_tiket ORDER BY id_jenis");
$jenis_list = $stmtJenis->fetchAll();

$stmtRundown = $pdo->query("SELECT * FROM rundown_acara ORDER BY jam_mulai");
$rundown_list = $stmtRundown->fetchAll();

$stmtPromo = $pdo->query("SELECT * FROM kode_promo ORDER BY id_promo");
$promo_list = $stmtPromo->fetchAll();

$page_title = 'CRUD Master Data - Admin OSIS';
require_once __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb">
    <a href="index.php">Katalog</a> > 
    <a href="dashboard_panitia.php">Dashboard Panitia</a> > 
    CRUD Master Data
</div>

<h2 style="color: #4F46E5; margin-bottom: 8px;">CRUD Master Data (Admin OSIS)</h2>
<p style="margin-bottom: 20px; color: #6B7280;">Kelola Jenis Tiket, Rundown Acara, dan Kode Promo untuk Pensi School 2026.</p>

<?php if (!empty($errors)): ?>
    <div class="flash ERROR">
        <strong>Error:</strong>
        <ul style="margin: 8px 0 0 20px;">
            <?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="flash SUKSES"><?= e($success) ?></div>
<?php endif; ?>

<!-- ===== JENIS TIKET ===== -->
<div class="card">
    <div class="card-header">
        <h3 style="margin: 0;">🎫 Jenis Tiket</h3>
    </div>
    
    <!-- Form Tambah -->
    <form method="POST" action="crud_tiket.php" style="margin-bottom: 24px; padding: 16px; background: #F9FAFB; border-radius: 8px; border: 1px solid #E5E7EB;">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
        <input type="hidden" name="aksi" value="tambah_jenis">
        <h4 style="margin-bottom: 12px;">➕ Tambah Jenis Tiket Baru</h4>
        <table style="border: none; width: 100%; max-width: 800px;">
            <tr style="border: none;">
                <td style="width: 140px; border: none;"><label><strong>Nama Jenis *</strong></label></td>
                <td style="border: none;"><input type="text" name="nama_jenis" required maxlength="80" size="40" placeholder="Contoh: VIP Internal (Presale)"></td>
            </tr>
            <tr style="border: none;">
                <td style="border: none;"><label><strong>Target Pembeli *</strong></label></td>
                <td style="border: none;">
                    <select name="target_pembeli" required>
                        <option value="Internal">Internal (Siswa NIS)</option>
                        <option value="Eksternal">Eksternal (Tamu NIK)</option>
                        <option value="Semua" selected>Semua (Internal & Eksternal)</option>
                    </select>
                </td>
            </tr>
            <tr style="border: none;">
                <td style="border: none;"><label><strong>Tipe Tiket *</strong></label></td>
                <td style="border: none;">
                    <select name="tipe_tiket" required>
                        <option value="Free Standing">Free Standing (Bebas, tanpa kursi)</option>
                        <option value="Seated">Seated (Bernomor Kursi)</option>
                    </select>
                </td>
            </tr>
            <tr style="border: none;">
                <td style="border: none;"><label><strong>Harga *</strong></label></td>
                <td style="border: none;"><input type="number" name="harga" required min="1000" value="50000" step="1000" placeholder="Contoh: 50000"></td>
            </tr>
            <tr style="border: none;">
                <td style="border: none;"><label><strong>Total Stok *</strong></label></td>
                <td style="border: none;"><input type="number" name="stok_tiket" required min="1" value="50" placeholder="Contoh: 50"></td>
            </tr>
            <tr style="border: none;">
                <td style="border: none;"></td>
                <td style="border: none;"><button type="submit" class="success"><strong>TAMBAH JENIS TIKET</strong></button></td>
            </tr>
        </table>
    </form>

    <!-- Tabel Daftar -->
    <table>
        <thead>
            <tr>
                <th style="width: 5%;">ID</th>
                <th>Nama Jenis</th>
                <th>Target</th>
                <th>Tipe</th>
                <th style="text-align: right;">Harga</th>
                <th style="text-align: center;">Total Stok</th>
                <th style="text-align: center;">Tersedia</th>
                <th style="text-align: center;">Tertahan</th>
                <th style="width: 180px;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($jenis_list as $jt): ?>
                <tr>
                    <td align="center"><?= (int)$jt['id_jenis'] ?></td>
                    <td><strong><?= e($jt['nama_jenis']) ?></strong></td>
                    <td><span class="badge badge-<?= $jt['target_pembeli'] === 'Internal' ? 'internal' : ($jt['target_pembeli'] === 'Eksternal' ? 'eksternal' : 'available') ?>"><?= e($jt['target_pembeli']) ?></span></td>
                    <td><?= e($jt['tipe_tiket']) ?></td>
                    <td style="text-align: right;"><strong><?= format_rupiah((int)$jt['harga']) ?></strong></td>
                    <td style="text-align: center;"><?= (int)$jt['stok_tiket'] ?></td>
                    <td style="text-align: center;"><strong><?= (int)$jt['stok_tersedia'] ?></strong></td>
                    <td style="text-align: center;"><?= (int)$jt['stok_tertahan'] ?></td>
                    <td>
                        <button type="button" onclick="openEditJenis(<?= htmlspecialchars(json_encode($jt), ENT_QUOTES, 'UTF-8') ?>)" class="secondary" style="font-size: 0.75rem; padding: 4px 8px; margin-right: 4px;">Edit</button>
                        <form method="POST" action="crud_tiket.php" style="display: inline;" onsubmit="return confirm('Hapus jenis tiket ini?');">
                            <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                            <input type="hidden" name="aksi" value="hapus_jenis">
                            <input type="hidden" name="id_jenis" value="<?= (int)$jt['id_jenis'] ?>">
                            <button type="submit" class="danger" style="font-size: 0.75rem; padding: 4px 8px;">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($jenis_list)): ?>
                <tr><td colspan="9" align="center" style="color: #6B7280;">Belum ada jenis tiket.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<br>

<!-- ===== RUNDOWN ACARA ===== -->
<div class="card">
    <div class="card-header">
        <h3 style="margin: 0;">📋 Rundown Acara</h3>
    </div>
    
    <form method="POST" action="crud_tiket.php" style="margin-bottom: 24px; padding: 16px; background: #F9FAFB; border-radius: 8px; border: 1px solid #E5E7EB;">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
        <input type="hidden" name="aksi" value="tambah_rundown">
        <h4 style="margin-bottom: 12px;">➕ Tambah Rundown Baru</h4>
        <table style="border: none; width: 100%; max-width: 800px;">
            <tr style="border: none;">
                <td style="width: 140px; border: none;"><label><strong>Nama Penampil *</strong></label></td>
                <td style="border: none;"><input type="text" name="nama_penampil" required maxlength="120" size="50" placeholder="Contoh: Bintang Tamu: Rendy Pandugo"></td>
            </tr>
            <tr style="border: none;">
                <td style="border: none;"><label><strong>Kategori *</strong></label></td>
                <td style="border: none;">
                    <select name="kategori_penampil" required>
                        <option value="Guest Star">Guest Star</option>
                        <option value="Ekskul" selected>Ekskul</option>
                        <option value="Band Siswa">Band Siswa</option>
                    </select>
                </td>
            </tr>
            <tr style="border: none;">
                <td style="border: none;"><label><strong>Jam Mulai *</strong></label></td>
                <td style="border: none;"><input type="time" name="jam_mulai" required value="17:00"></td>
            </tr>
            <tr style="border: none;">
                <td style="border: none;"><label><strong>Jam Selesai *</strong></label></td>
                <td style="border: none;"><input type="time" name="jam_selesai" required value="18:00"></td>
            </tr>
            <tr style="border: none;">
                <td style="border: none;"><label><strong>Lokasi Panggung *</strong></label></td>
                <td style="border: none;"><input type="text" name="lokasi_panggung" required maxlength="100" size="40" value="Panggung Utama" placeholder="Contoh: Panggung Utama / Panggung Samping"></td>
            </tr>
            <tr style="border: none;">
                <td style="border: none;"></td>
                <td style="border: none;"><button type="submit" class="success"><strong>TAMBAH RUNDOWN</strong></button></td>
            </tr>
        </table>
    </form>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">ID</th>
                <th>Jam</th>
                <th>Penampil</th>
                <th>Kategori</th>
                <th>Lokasi</th>
                <th style="width: 140px;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rundown_list as $rd): ?>
                <tr>
                    <td align="center"><?= (int)$rd['id_rundown'] ?></td>
                    <td><strong><?= e(date('H:i', strtotime($rd['jam_mulai']))) ?> - <?= e(date('H:i', strtotime($rd['jam_selesai']))) ?></strong></td>
                    <td><strong><?= e($rd['nama_penampil']) ?></strong></td>
                    <td><span class="badge badge-<?= strtolower(str_replace(' ', '-', $rd['kategori_penampil'])) ?>"><?= e($rd['kategori_penampil']) ?></span></td>
                    <td><?= e($rd['lokasi_panggung']) ?></td>
                    <td>
                        <button type="button" onclick="openEditRundown(<?= htmlspecialchars(json_encode($rd), ENT_QUOTES, 'UTF-8') ?>)" class="secondary" style="font-size: 0.75rem; padding: 4px 8px; margin-right: 4px;">Edit</button>
                        <form method="POST" action="crud_tiket.php" style="display: inline;" onsubmit="return confirm('Hapus rundown ini?');">
                            <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                            <input type="hidden" name="aksi" value="hapus_rundown">
                            <input type="hidden" name="id_rundown" value="<?= (int)$rd['id_rundown'] ?>">
                            <button type="submit" class="danger" style="font-size: 0.75rem; padding: 4px 8px;">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($rundown_list)): ?>
                <tr><td colspan="6" align="center" style="color: #6B7280;">Belum ada rundown.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<br>

<!-- ===== KODE PROMO ===== -->
<div class="card">
    <div class="card-header">
        <h3 style="margin: 0;">🎁 Kode Promo / Voucher</h3>
    </div>
    
    <form method="POST" action="crud_tiket.php" style="margin-bottom: 24px; padding: 16px; background: #F9FAFB; border-radius: 8px; border: 1px solid #E5E7EB;">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
        <input type="hidden" name="aksi" value="tambah_promo">
        <h4 style="margin-bottom: 12px;">➕ Tambah Kode Promo Baru</h4>
        <table style="border: none; width: 100%; max-width: 800px;">
            <tr style="border: none;">
                <td style="width: 160px; border: none;"><label><strong>Kode Voucher *</strong></label></td>
                <td style="border: none;"><input type="text" name="kode_voucher" required maxlength="20" pattern="[A-Z0-9]{3,20}" size="20" placeholder="Contoh: PENSI10K" style="text-transform: uppercase;"></td>
            </tr>
            <tr style="border: none;">
                <td style="border: none;"><label><strong>Potongan Nominal *</strong></label></td>
                <td style="border: none;"><input type="number" name="potongan_nominal" required min="1000" value="10000" step="1000" placeholder="Contoh: 10000"></td>
            </tr>
            <tr style="border: none;">
                <td style="border: none;"><label><strong>Kuota Voucher *</strong></label></td>
                <td style="border: none;"><input type="number" name="kuota_voucher" required min="1" value="50" placeholder="Contoh: 50"></td>
            </tr>
            <tr style="border: none;">
                <td style="border: none;"><label><strong>Berlaku Sampai *</strong></label></td>
                <td style="border: none;"><input type="date" name="berlaku_sampai" required value="<?= date('Y-m-d', strtotime('+30 days')) ?>"></td>
            </tr>
            <tr style="border: none;">
                <td style="border: none;"></td>
                <td style="border: none;"><button type="submit" class="success"><strong>TAMBAH PROMO</strong></button></td>
            </tr>
        </table>
    </form>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">ID</th>
                <th>Kode Voucher</th>
                <th style="text-align: right;">Potongan</th>
                <th style="text-align: center;">Kuota</th>
                <th>Berlaku Sampai</th>
                <th style="text-align: center;">Status</th>
                <th style="width: 140px;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($promo_list as $pr): 
                $expired = $pr['berlaku_sampai'] < date('Y-m-d') || (int)$pr['kuota_voucher'] <= 0;
            ?>
                <tr>
                    <td align="center"><?= (int)$pr['id_promo'] ?></td>
                    <td><code><strong><?= e($pr['kode_voucher']) ?></strong></code></td>
                    <td style="text-align: right;"><strong><?= format_rupiah((int)$pr['potongan_nominal']) ?></strong></td>
                    <td style="text-align: center;"><?= (int)$pr['kuota_voucher'] ?></td>
                    <td><?= e(date('d-m-Y', strtotime($pr['berlaku_sampai']))) ?></td>
                    <td style="text-align: center;">
                        <?php if ($expired): ?>
                            <span class="badge badge-expired">Kadaluarsa/Habis</span>
                        <?php else: ?>
                            <span class="badge badge-available">Aktif</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button type="button" onclick="openEditPromo(<?= htmlspecialchars(json_encode($pr), ENT_QUOTES, 'UTF-8') ?>)" class="secondary" style="font-size: 0.75rem; padding: 4px 8px; margin-right: 4px;">Edit</button>
                        <form method="POST" action="crud_tiket.php" style="display: inline;" onsubmit="return confirm('Hapus kode promo ini?');">
                            <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                            <input type="hidden" name="aksi" value="hapus_promo">
                            <input type="hidden" name="id_promo" value="<?= (int)$pr['id_promo'] ?>">
                            <button type="submit" class="danger" style="font-size: 0.75rem; padding: 4px 8px;">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($promo_list)): ?>
                <tr><td colspan="7" align="center" style="color: #6B7280;">Belum ada kode promo.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<br>

<!-- EDIT MODALS (Hidden, shown via JS) -->
<div id="modal-edit-jenis" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 24px; border-radius: 12px; max-width: 500px; width: 90%; max-height: 90vh; overflow: auto;">
        <h3 style="margin-bottom: 16px; color: #4F46E5;">✏️ Edit Jenis Tiket</h3>
        <form method="POST" action="crud_tiket.php" id="form-edit-jenis">
            <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
            <input type="hidden" name="aksi" value="edit_jenis">
            <input type="hidden" name="id_jenis" id="edit_jenis_id">
            <table style="border: none; width: 100%;">
                <tr style="border: none;"><td style="width: 130px; border: none;"><label><strong>Nama Jenis *</strong></label></td><td style="border: none;"><input type="text" name="nama_jenis" id="edit_jenis_nama" required maxlength="80" size="35"></td></tr>
                <tr style="border: none;"><td style="border: none;"><label><strong>Target Pembeli *</strong></label></td><td style="border: none;"><select name="target_pembeli" id="edit_jenis_target" required><option value="Internal">Internal</option><option value="Eksternal">Eksternal</option><option value="Semua">Semua</option></select></td></tr>
                <tr style="border: none;"><td style="border: none;"><label><strong>Tipe Tiket *</strong></label></td><td style="border: none;"><select name="tipe_tiket" id="edit_jenis_tipe" required><option value="Free Standing">Free Standing</option><option value="Seated">Seated</option></select></td></tr>
                <tr style="border: none;"><td style="border: none;"><label><strong>Harga *</strong></label></td><td style="border: none;"><input type="number" name="harga" id="edit_jenis_harga" required min="1000" step="1000"></td></tr>
                <tr style="border: none;"><td style="border: none;"><label><strong>Total Stok *</strong></label></td><td style="border: none;"><input type="number" name="stok_tiket" id="edit_jenis_stok" required min="1"></td></tr>
                <tr style="border: none;"><td style="border: none;"></td><td style="border: none;"><button type="submit" class="success"><strong>SIMPAN PERUBAHAN</strong></button> <button type="button" onclick="closeModal('modal-edit-jenis')" class="secondary" style="margin-left: 8px;">Batal</button></td></tr>
            </table>
        </form>
    </div>
</div>

<div id="modal-edit-rundown" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 24px; border-radius: 12px; max-width: 500px; width: 90%; max-height: 90vh; overflow: auto;">
        <h3 style="margin-bottom: 16px; color: #4F46E5;">✏️ Edit Rundown</h3>
        <form method="POST" action="crud_tiket.php" id="form-edit-rundown">
            <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
            <input type="hidden" name="aksi" value="edit_rundown">
            <input type="hidden" name="id_rundown" id="edit_rundown_id">
            <table style="border: none; width: 100%;">
                <tr style="border: none;"><td style="width: 130px; border: none;"><label><strong>Nama Penampil *</strong></label></td><td style="border: none;"><input type="text" name="nama_penampil" id="edit_rundown_nama" required maxlength="120" size="40"></td></tr>
                <tr style="border: none;"><td style="border: none;"><label><strong>Kategori *</strong></label></td><td style="border: none;"><select name="kategori_penampil" id="edit_rundown_kategori" required><option value="Guest Star">Guest Star</option><option value="Ekskul">Ekskul</option><option value="Band Siswa">Band Siswa</option></select></td></tr>
                <tr style="border: none;"><td style="border: none;"><label><strong>Jam Mulai *</strong></label></td><td style="border: none;"><input type="time" name="jam_mulai" id="edit_rundown_mulai" required></td></tr>
                <tr style="border: none;"><td style="border: none;"><label><strong>Jam Selesai *</strong></label></td><td style="border: none;"><input type="time" name="jam_selesai" id="edit_rundown_selesai" required></td></tr>
                <tr style="border: none;"><td style="border: none;"><label><strong>Lokasi Panggung *</strong></label></td><td style="border: none;"><input type="text" name="lokasi_panggung" id="edit_rundown_lokasi" required maxlength="100" size="35"></td></tr>
                <tr style="border: none;"><td style="border: none;"></td><td style="border: none;"><button type="submit" class="success"><strong>SIMPAN PERUBAHAN</strong></button> <button type="button" onclick="closeModal('modal-edit-rundown')" class="secondary" style="margin-left: 8px;">Batal</button></td></tr>
            </table>
        </form>
    </div>
</div>

<div id="modal-edit-promo" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 24px; border-radius: 12px; max-width: 500px; width: 90%; max-height: 90vh; overflow: auto;">
        <h3 style="margin-bottom: 16px; color: #4F46E5;">✏️ Edit Kode Promo</h3>
        <form method="POST" action="crud_tiket.php" id="form-edit-promo">
            <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
            <input type="hidden" name="aksi" value="edit_promo">
            <input type="hidden" name="id_promo" id="edit_promo_id">
            <table style="border: none; width: 100%;">
                <tr style="border: none;"><td style="width: 150px; border: none;"><label><strong>Kode Voucher *</strong></label></td><td style="border: none;"><input type="text" name="kode_voucher" id="edit_promo_kode" required maxlength="20" pattern="[A-Z0-9]{3,20}" size="20" style="text-transform: uppercase;"></td></tr>
                <tr style="border: none;"><td style="border: none;"><label><strong>Potongan Nominal *</strong></label></td><td style="border: none;"><input type="number" name="potongan_nominal" id="edit_promo_potongan" required min="1000" step="1000"></td></tr>
                <tr style="border: none;"><td style="border: none;"><label><strong>Kuota Voucher *</strong></label></td><td style="border: none;"><input type="number" name="kuota_voucher" id="edit_promo_kuota" required min="1"></td></tr>
                <tr style="border: none;"><td style="border: none;"><label><strong>Berlaku Sampai *</strong></label></td><td style="border: none;"><input type="date" name="berlaku_sampai" id="edit_promo_berlaku" required></td></tr>
                <tr style="border: none;"><td style="border: none;"></td><td style="border: none;"><button type="submit" class="success"><strong>SIMPAN PERUBAHAN</strong></button> <button type="button" onclick="closeModal('modal-edit-promo')" class="secondary" style="margin-left: 8px;">Batal</button></td></tr>
            </table>
        </form>
    </div>
</div>

<script>
function openEditJenis(data) {
    document.getElementById('edit_jenis_id').value = data.id_jenis;
    document.getElementById('edit_jenis_nama').value = data.nama_jenis;
    document.getElementById('edit_jenis_target').value = data.target_pembeli;
    document.getElementById('edit_jenis_tipe').value = data.tipe_tiket;
    document.getElementById('edit_jenis_harga').value = data.harga;
    document.getElementById('edit_jenis_stok').value = data.stok_tiket;
    document.getElementById('modal-edit-jenis').style.display = 'flex';
}

function openEditRundown(data) {
    document.getElementById('edit_rundown_id').value = data.id_rundown;
    document.getElementById('edit_rundown_nama').value = data.nama_penampil;
    document.getElementById('edit_rundown_kategori').value = data.kategori_penampil;
    document.getElementById('edit_rundown_mulai').value = data.jam_mulai;
    document.getElementById('edit_rundown_selesai').value = data.jam_selesai;
    document.getElementById('edit_rundown_lokasi').value = data.lokasi_panggung;
    document.getElementById('modal-edit-rundown').style.display = 'flex';
}

function openEditPromo(data) {
    document.getElementById('edit_promo_id').value = data.id_promo;
    document.getElementById('edit_promo_kode').value = data.kode_voucher;
    document.getElementById('edit_promo_potongan').value = data.potongan_nominal;
    document.getElementById('edit_promo_kuota').value = data.kuota_voucher;
    document.getElementById('edit_promo_berlaku').value = data.berlaku_sampai;
    document.getElementById('modal-edit-promo').style.display = 'flex';
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

// Close modal on backdrop click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', e => {
        if (e.target === modal) modal.style.display = 'none';
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>