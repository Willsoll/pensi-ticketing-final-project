<?php
/**
 * verifikasi_pembayaran.php - Verifikasi Pembayaran (Panitia Keuangan)
 * Tinjau bukti transfer, ubah status Lunas/Ditolak (auto-return kuota jika Ditolak)
 * Hanya role panitia_keuangan
 */
require_once __DIR__ . '/../src/Config/database.php';
require_once __DIR__ . '/../src/Middleware/middleware.php';

require_role(['panitia_keuangan', 'admin_osis']);
release_expired_transactions($pdo);

$fin_user = current_user();

// Proses verifikasi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('ERROR', 'Token keamanan (CSRF) tidak valid.');
        header('Location: verifikasi_pembayaran.php');
        exit;
    }

    $id_raw = $_POST['id_pesan'] ?? 0;
    $id_pesan = is_numeric($id_raw) ? (int)$id_raw : 0;
    $keputusan = $_POST['keputusan'] ?? '';
    $catatan_raw = $_POST['catatan_penolakan'] ?? '';
    $catatan = is_string($catatan_raw) ? mb_substr(trim($catatan_raw), 0, 255) : '';

    if ($id_pesan <= 0 || !in_array($keputusan, ['Lunas', 'Ditolak'], true)) {
        set_flash('ERROR', 'Parameter verifikasi tidak valid.');
        header('Location: verifikasi_pembayaran.php');
        exit;
    }

    if ($keputusan === 'Ditolak' && mb_strlen($catatan) < 5) {
        set_flash('ERROR', 'Catatan alasan penolakan wajib diisi minimal 5 karakter jika status diubah menjadi Ditolak.');
        header('Location: verifikasi_pembayaran.php');
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmtLock = $pdo->prepare("SELECT * FROM pemesanan WHERE id_pesan = ? FOR UPDATE");
        $stmtLock->execute([$id_pesan]);
        $pesanan = $stmtLock->fetch();

        if (!$pesanan || $pesanan['status_bayar'] !== 'Menunggu Verifikasi') {
            throw new RuntimeException('Pesanan tidak ditemukan atau tidak berstatus Menunggu Verifikasi.');
        }

        $stmtDet = $pdo->prepare("SELECT id_kursi FROM detail_tiket_satuan WHERE id_pesan = ?");
        $stmtDet->execute([$id_pesan]);
        $detail_list = $stmtDet->fetchAll();

        if ($keputusan === 'Lunas') {
            $updPesan = $pdo->prepare("
                UPDATE pemesanan
                SET status_bayar = 'Lunas', catatan_penolakan = NULL
                WHERE id_pesan = ?
            ");
            $updPesan->execute([$id_pesan]);

            // Update stok: stok_tertahan -qty (sudah terjual, tidak dikembalikan)
            $updJenis = $pdo->prepare("
                UPDATE jenis_tiket
                SET stok_tertahan = GREATEST(0, stok_tertahan - ?)
                WHERE id_jenis = ?
            ");
            $updJenis->execute([(int)$pesanan['jumlah'], (int)$pesanan['id_jenis']]);

            // Kursi Locked -> Sold
            foreach ($detail_list as $dt) {
                if (!empty($dt['id_kursi'])) {
                    $updKursi = $pdo->prepare("UPDATE kursi_pensi SET status_kursi = 'Sold' WHERE id_kursi = ?");
                    $updKursi->execute([(int)$dt['id_kursi']]);
                }
            }

            catat_audit_log(
                $pdo,
                (int)$fin_user['id_user'],
                'VERIFIKASI_LUNAS',
                'pemesanan',
                "Panitia Keuangan menyetujui pesanan {$pesanan['kode_transaksi']} (" . format_rupiah((int)$pesanan['total_harga']) . ") menjadi LUNAS. Tiket diaktifkan & kursi ditetapkan Sold."
            );
            set_flash('SUKSES', "Pesanan {$pesanan['kode_transaksi']} disetujui menjadi LUNAS.");

        } else { // Ditolak
            $updPesan = $pdo->prepare("
                UPDATE pemesanan
                SET status_bayar = 'Ditolak', catatan_penolakan = ?
                WHERE id_pesan = ?
            ");
            $updPesan->execute([$catatan, $id_pesan]);

            // Kembalikan stok: stok_tersedia +qty, stok_tertahan -qty
            $updJenis = $pdo->prepare("
                UPDATE jenis_tiket
                SET stok_tersedia = stok_tersedia + ?,
                    stok_tertahan = GREATEST(0, stok_tertahan - ?)
                WHERE id_jenis = ?
            ");
            $updJenis->execute([(int)$pesanan['jumlah'], (int)$pesanan['jumlah'], (int)$pesanan['id_jenis']]);

            // Kursi Locked -> Available
            foreach ($detail_list as $dt) {
                if (!empty($dt['id_kursi'])) {
                    $updKursi = $pdo->prepare("
                        UPDATE kursi_pensi
                        SET status_kursi = 'Available'
                        WHERE id_kursi = ? AND status_kursi = 'Locked'
                    ");
                    $updKursi->execute([(int)$dt['id_kursi']]);
                }
            }

            // Kembalikan kuota promo jika pesanan memakai voucher
            if (!empty($pesanan['id_promo'])) {
                $updPromo = $pdo->prepare("UPDATE kode_promo SET kuota_voucher = kuota_voucher + 1 WHERE id_promo = ?");
                $updPromo->execute([(int)$pesanan['id_promo']]);
            }

            catat_audit_log(
                $pdo,
                (int)$fin_user['id_user'],
                'VERIFIKASI_DITOLAK',
                'pemesanan',
                "Panitia Keuangan menolak pesanan {$pesanan['kode_transaksi']} dengan catatan: '{$catatan}'. Stok, kursi & kuota promo dikembalikan."
            );
            set_flash('SUKSES', "Pesanan {$pesanan['kode_transaksi']} ditolak (Ditolak) dan kuota/kursi telah dilepas kembali.");
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[VERIFIKASI_PEMBAYARAN] ' . $e->getMessage());
        set_flash('ERROR', 'Gagal memverifikasi pesanan karena kesalahan sistem. Silakan coba lagi.');
    }

    header('Location: verifikasi_pembayaran.php');
    exit;
}

// Filter status
$filter_status = is_string($_GET['status'] ?? null) ? trim($_GET['status']) : 'Menunggu Verifikasi';
$where = [];
$params = [];
if (in_array($filter_status, ['Menunggu Verifikasi', 'Lunas', 'Ditolak', 'Menunggu Bayar', 'Kadaluarsa', 'ALL'], true)) {
    if ($filter_status !== 'ALL') {
        $where[] = "p.status_bayar = ?";
        $params[] = $filter_status;
    }
}

$sql = "
    SELECT p.*, u.nama as nama_pembeli, u.tipe_pengguna, u.nis_atau_nik, u.email, u.no_hp, j.nama_jenis, j.tipe_tiket, j.harga
    FROM pemesanan p
    JOIN users u ON p.id_user = u.id_user
    JOIN jenis_tiket j ON p.id_jenis = j.id_jenis
";
if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY p.id_pesan DESC";

$stmtList = $pdo->prepare($sql);
$stmtList->execute($params);
$pesanan_list = $stmtList->fetchAll();

$page_title = 'Verifikasi Pembayaran - Panitia Keuangan';
require_once __DIR__ . '/../src/Views/layouts/header.php';
?>

<div class="breadcrumb">
    <a href="index.php">Katalog</a> > 
    <a href="dashboard_panitia.php">Dashboard Panitia</a> > 
    Verifikasi Pembayaran
</div>

<h2 style="color: #4F46E5; margin-bottom: 8px;">Verifikasi Pembayaran (Panitia Keuangan)</h2>
<p style="margin-bottom: 16px; color: #6B7280;">
    Tinjau foto bukti transfer pembeli untuk mengubah status pesanan menjadi <strong>Lunas</strong> atau <strong>Ditolak</strong> disertai catatan penolakan.
    Jika <strong>Ditolak</strong>, stok tiket & kursi otomatis dikembalikan ke Available.
</p>

<!-- FILTER -->
<div class="card">
    <form method="GET" action="verifikasi_pembayaran.php">
        <div style="display: flex; gap: 12px; align-items: end; flex-wrap: wrap;">
            <div>
                <label for="status"><strong>Filter Status:</strong></label>
                <select id="status" name="status" style="min-width: 200px;">
                    <option value="Menunggu Verifikasi" <?= $filter_status === 'Menunggu Verifikasi' ? 'selected' : '' ?>>Menunggu Verifikasi</option>
                    <option value="Lunas" <?= $filter_status === 'Lunas' ? 'selected' : '' ?>>Lunas</option>
                    <option value="Ditolak" <?= $filter_status === 'Ditolak' ? 'selected' : '' ?>>Ditolak</option>
                    <option value="Menunggu Bayar" <?= $filter_status === 'Menunggu Bayar' ? 'selected' : '' ?>>Menunggu Bayar</option>
                    <option value="Kadaluarsa" <?= $filter_status === 'Kadaluarsa' ? 'selected' : '' ?>>Kadaluarsa</option>
                    <option value="ALL" <?= $filter_status === 'ALL' ? 'selected' : '' ?>>-- Semua Status --</option>
                </select>
            </div>
            <button type="submit" class="button"><strong>Tampilkan</strong></button>
        </div>
    </form>
</div>

<br>

<!-- TABEL PESANAN -->
<div class="card">
    <table>
        <thead>
            <tr>
                <th>Kode Transaksi</th>
                <th>Pembeli</th>
                <th>Jenis Tiket & Rincian</th>
                <th>Bukti Transfer</th>
                <th>Status & Aksi Verifikasi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($pesanan_list)): ?>
                <tr>
                    <td colspan="5" align="center" style="color: #6B7280; padding: 40px;">Tidak ada data pesanan pada filter ini.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($pesanan_list as $ps): ?>
                    <tr valign="top">
                        <td>
                            <code><strong><?= e($ps['kode_transaksi']) ?></strong></code><br>
                            <small><?= e(date('d-m-Y H:i', strtotime($ps['tgl_pesan']))) ?></small>
                        </td>
                        <td>
                            <strong><?= e($ps['nama_pembeli']) ?></strong><br>
                            <small><?= e(label_tipe_pengguna($ps['tipe_pengguna'])) ?>: <?= e($ps['nis_atau_nik']) ?></small><br>
                            <small><?= e($ps['email']) ?><br><?= e($ps['no_hp']) ?></small>
                        </td>
                        <td>
                            <strong><?= e($ps['nama_jenis']) ?></strong> (<?= e($ps['tipe_tiket']) ?>)<br>
                            Jumlah: <strong><?= (int)$ps['jumlah'] ?></strong> x <?= format_rupiah((int)$ps['harga']) ?><br>
                            Total Tagihan: <strong><?= format_rupiah((int)$ps['total_harga']) ?></strong><br>
                            <small>(Sub: <?= format_rupiah((int)$ps['harga'] * (int)$ps['jumlah']) ?> | Diskon: -<?= format_rupiah((int)$ps['diskon']) ?>)</small>
                        </td>
                        <td align="center">
                            <?php if (!empty($ps['bukti_bayar'])): ?>
                                <a href="bukti.php?id_pesan=<?= (int)$ps['id_pesan'] ?>" target="_blank" rel="noopener">
                                    <img src="bukti.php?id_pesan=<?= (int)$ps['id_pesan'] ?>" alt="Bukti Bayar" style="max-width: 180px; border: 1px solid #E5E7EB; border-radius: 6px;"><br>
                                    <small>[ Lihat Ukuran Penuh ]</small>
                                </a>
                            <?php else: ?>
                                <small style="color: #E11D48;"><em>Belum diunggah</em></small>
                            <?php endif; ?>
                        </td>
                        <td style="min-width: 280px;">
                            <strong>Status: 
                                <span class="badge badge-<?= strtolower(str_replace(' ', '-', $ps['status_bayar'])) ?>">
                                    <?= e(strtoupper($ps['status_bayar'])) ?>
                                </span>
                            </strong>
                            <?php if (!empty($ps['catatan_penolakan'])): ?>
                                <br><small style="color: #E11D48;">Catatan: <?= e($ps['catatan_penolakan']) ?></small>
                            <?php endif; ?>

                            <?php if ($ps['status_bayar'] === 'Menunggu Verifikasi'): ?>
                                <hr>
                                <form method="POST" action="verifikasi_pembayaran.php">
                                    <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                                    <input type="hidden" name="id_pesan" value="<?= (int)$ps['id_pesan'] ?>">
                                    
                                    <label for="cat_<?= (int)$ps['id_pesan'] ?>"><small>Catatan Penolakan (wajib jika Ditolak):</small></label><br>
                                    <input type="text" id="cat_<?= (int)$ps['id_pesan'] ?>" name="catatan_penolakan" size="30" placeholder="Alasan penolakan..." style="margin-bottom: 8px;"><br>
                                    
                                    <button type="submit" name="keputusan" value="Lunas" 
                                        onclick="<?= e('return confirm(' . json_encode('Ubah status ' . $ps['kode_transaksi'] . ' menjadi LUNAS?', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ');') ?>"
                                        class="success" style="font-size: 0.8rem; padding: 6px 12px; margin-right: 4px;">
                                        <strong>Setujui (Lunas)</strong>
                                    </button>
                                    <button type="submit" name="keputusan" value="Ditolak" 
                                        onclick="<?= e('return confirm(' . json_encode('Tolak pembayaran ' . $ps['kode_transaksi'] . '?', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ');') ?>"
                                        class="danger" style="font-size: 0.8rem; padding: 6px 12px;">
                                        Tolak (Ditolak)
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../src/Views/layouts/footer.php'; ?>