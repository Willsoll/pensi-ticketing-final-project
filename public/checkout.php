<?php
/**
 * checkout.php — Pembuatan pesanan & halaman invoice (v4)
 * POST (create): buat transaksi baru. GET ?kode=: tunjukkan invoice.
 * Hanya role customer, milik user sendiri.
 */
require_once __DIR__ . '/../src/Config/database.php';
require_once __DIR__ . '/../src/Middleware/middleware.php';
require_role(['customer']);
release_expired_transactions($pdo);

$user    = current_user();
$id_user = (int)$user['id_user'];

$kode = $_GET['kode'] ?? '';
$aksi = $_POST['aksi'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('CSRF tidak valid');
    }

    // ---------- UPLOAD BUKTI ----------
    if ($aksi === 'upload_bukti') {
        if ($kode === '') {
            set_flash('ERROR', 'Kode transaksi tidak ditemukan.');
            header('Location: dashboard_peserta.php');
            exit;
        }
        $stmt = $pdo->prepare('SELECT t.* FROM transaksi t WHERE t.kode_booking = ? AND t.id_user = ? LIMIT 1');
        $stmt->execute([$kode, $id_user]);
        $trx = $stmt->fetch();
        if (!$trx) {
            set_flash('ERROR', 'Transaksi tidak ditemukan atau bukan milik Anda.');
            header('Location: dashboard_peserta.php');
            exit;
        }
        if ($trx['status_bayar'] !== 'Pending') {
            set_flash('ERROR', 'Status pesanan saat ini tidak dapat mengunggah bukti.');
            header('Location: checkout.php?kode=' . urlencode($kode));
            exit;
        }
        try {
            $path = handle_bukti_upload($_FILES['bukti'] ?? null, __DIR__ . '/uploads/bukti');
        } catch (Throwable $e) {
            error_log('[BUKTI_UPLOAD] ' . $e->getMessage());
            set_flash('ERROR', $e->getMessage() ?: 'Gagal mengunggah bukti bayar.');
            header('Location: checkout.php?kode=' . urlencode($kode));
            exit;
        }
        $upd = $pdo->prepare("UPDATE transaksi SET bukti_bayar = ?, status_bayar = 'Waiting Verification'
                              WHERE id_transaksi = ? AND id_user = ? AND status_bayar = 'Pending'");
        $upd->execute([$path, (int)$trx['id_transaksi'], $id_user]);
        catat_audit_log($pdo, $id_user, 'UPLOAD', 'transaksi', "Pembeli mengunggah bukti transfer ({$path}) untuk {$kode}.");
        set_flash('SUCCESS', 'Bukti bayar berhasil diunggah. Menunggu verifikasi keuangan.');
        header('Location: checkout.php?kode=' . urlencode($kode));
        exit;
    }

    // ---------- CREATE PESANAN ----------
    $id_event   = (int)($_POST['id_event'] ?? 0);
    $id_tier    = (int)($_POST['id_tier'] ?? 0);
    $qty        = (int)($_POST['qty'] ?? 0);
    $kursiRaw   = $_POST['kursi'] ?? '';
    $kursiIds   = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)$kursiRaw)), fn($v) => $v > 0)));
    $namaList   = $_POST['nama_peserta'] ?? [];
    $nikList    = $_POST['nik_peserta'] ?? [];
    $kodeVoucher = trim((string)($_POST['kode_voucher'] ?? ''));

    if ($id_event <= 0 || $id_tier <= 0 || $qty < 1 || $qty > 5) {
        set_flash('ERROR', 'Data pesanan tidak valid.');
        header('Location: index.php');
        exit;
    }
    if (count($namaList) !== $qty) {
        set_flash('ERROR', 'Jumlah data peserta tidak sesuai.');
        header('Location: index.php');
        exit;
    }

    $namaSeen = []; $nikSeen = []; $formErrors = [];
    foreach ($namaList as $i => $nama) {
        $nama = trim((string)$nama);
        $nik  = trim((string)($nikList[$i] ?? ''));
        if ($nama === '') {
            $formErrors[] = 'Peserta #' . ($i + 1) . ': nama wajib diisi.';
            continue;
        }
        $k = mb_strtolower($nama);
        if (isset($namaSeen[$k])) {
            $formErrors[] = 'Peserta #' . ($i + 1) . ": nama '$nama' duplikat dalam satu pesanan.";
        }
        $namaSeen[$k] = true;
        if (!preg_match('/^[0-9]{16}$/', $nik)) {
            $formErrors[] = 'Peserta #' . ($i + 1) . ': NIK harus 16 digit angka.';
            continue;
        }
        if (isset($nikSeen[$nik])) {
            $formErrors[] = 'Peserta #' . ($i + 1) . ": NIK $nik duplikat dalam satu pesanan.";
        }
        $nikSeen[$nik] = true;
    }
    if ($formErrors) {
        set_flash('ERROR', implode(' ', $formErrors));
        header('Location: index.php');
        exit;
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM events WHERE id_event = ? FOR UPDATE');
        $stmt->execute([$id_event]);
        $event = $stmt->fetch();
        if (!$event || $event['status_event'] !== 'Published') {
            throw new Exception('Event tidak valid atau sudah ditutup.');
        }

        $stmt = $pdo->prepare('SELECT * FROM tiket_tier WHERE id_tier = ? AND id_event = ? FOR UPDATE');
        $stmt->execute([$id_tier, $id_event]);
        $tier = $stmt->fetch();
        if (!$tier) {
            throw new Exception('Tier tidak valid.');
        }
        if (!empty($tier['batas_jual']) && strtotime($tier['batas_jual']) < time()) {
            throw new Exception('Batas penjualan tier sudah lewat.');
        }
        if ((int)$tier['kuota_tersedia'] < $qty) {
            throw new Exception('Kuota tidak mencukupi.');
        }

        $limit = check_user_ticket_limit($pdo, $id_user, $id_event, $qty);
        if (!$limit['ok']) {
            throw new Exception($limit['msg']);
        }

        $tipeVenue = (string)$event['tipe_venue'];
        $idKursiChosen = [];
        if ($tipeVenue === 'Seated') {
            if (empty($kursiIds)) {
                $stmt = $pdo->prepare('SELECT id_kursi FROM kursi_event WHERE id_tier = ? AND status_kursi = "Available" ORDER BY nomor_kursi LIMIT ?');
                $stmt->execute([$id_tier, $qty]);
                $auto = $stmt->fetchAll();
                if (count($auto) < $qty) {
                    throw new Exception('Kursi tersedia tidak mencukupi.');
                }
                foreach ($auto as $a) {
                    $idKursiChosen[] = ['id_kursi' => (int)$a['id_kursi']];
                }
            } else {
                if (count($kursiIds) !== $qty) {
                    throw new Exception('Jumlah kursi tidak sesuai dengan jumlah tiket.');
                }
                sort($kursiIds);
                foreach ($kursiIds as $kid) {
                    $stmt = $pdo->prepare('SELECT id_kursi, nomor_kursi, status_kursi FROM kursi_event WHERE id_kursi = ? AND id_tier = ? FOR UPDATE');
                    $stmt->execute([$kid, $id_tier]);
                    $s = $stmt->fetch();
                    if (!$s || $s['status_kursi'] !== 'Available') {
                        throw new Exception('Kursi #' . $kid . ' sudah tidak tersedia.');
                    }
                    $idKursiChosen[] = ['id_kursi' => (int)$kid, 'nomor_kursi' => $s['nomor_kursi']];
                }
            }
        }

        $idVoucher = null;
        $diskon    = 0;
        if ($kodeVoucher !== '') {
            $v = validate_voucher($pdo, $kodeVoucher, (int)$tier['harga'] * $qty);
            if (!$v['ok']) {
                throw new Exception($v['msg']);
            }
            $idVoucher = (int)$v['id'];
            $diskon    = (int)$v['diskon'];
            $pdo->prepare('UPDATE voucher_promo SET kuota_pakai = kuota_pakai + 1 WHERE id_voucher = ?')->execute([$idVoucher]);
        }

        $subtotal     = (int)$tier['harga'] * $qty;
        $biayaLayanan = BIAYA_LAYANAN_PER_TIKET * $qty;
        $total        = $subtotal - $diskon + $biayaLayanan;

        $kodeBooking = generate_kode_booking();
        $batasBayar  = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $stmt = $pdo->prepare("INSERT INTO transaksi
            (kode_booking,id_user,id_event,id_voucher,subtotal,diskon,biaya_layanan,total_bayar,bukti_bayar,batas_bayar_at,status_bayar,created_at)
            VALUES (?,?,?,?,?,?,?,?,NULL,?,'Pending',NOW())");
        $stmt->execute([$kodeBooking, $id_user, $id_event, $idVoucher, $subtotal, $diskon, $biayaLayanan, $total, $batasBayar]);
        $idTransaksi = (int)$pdo->lastInsertId();

        $idKursiCsv = implode(',', array_column($idKursiChosen, 'id_kursi'));
        $stmt = $pdo->prepare("INSERT INTO transaksi_detail
            (id_transaksi,id_tier,jumlah,harga_satuan,subtotal,id_kursi_list) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$idTransaksi, $id_tier, $qty, (int)$tier['harga'], $subtotal, $idKursiCsv]);

        $stmtP = $pdo->prepare("INSERT INTO transaksi_peserta
            (id_transaksi,id_tier,nama_peserta,nik_peserta,id_kursi) VALUES (?,?,?,?,?)");
        $updTier = $pdo->prepare("UPDATE tiket_tier SET kuota_tersedia = kuota_tersedia - ?, kuota_locked = kuota_locked + ?
                                  WHERE id_tier = ? AND kuota_tersedia >= ?");
        $updTier->execute([$qty, $qty, $id_tier, $qty]);
        if ($updTier->rowCount() !== 1) {
            throw new Exception('Kuota berubah saat diproses.');
        }

        for ($i = 0; $i < $qty; $i++) {
            $idKursi = null;
            if ($tipeVenue === 'Seated') {
                $idKursi = (int)$idKursiChosen[$i]['id_kursi'];
                $updK = $pdo->prepare("UPDATE kursi_event SET status_kursi = 'Locked' WHERE id_kursi = ? AND status_kursi = 'Available'");
                $updK->execute([$idKursi]);
                if ($updK->rowCount() !== 1) {
                    throw new Exception('Kursi sudah diambil peserta lain.');
                }
            }
            $stmtP->execute([$idTransaksi, $id_tier, trim($namaList[$i]), trim($nikList[$i]), $idKursi]);
        }

        catat_audit_log($pdo, $id_user, 'CREATE', 'transaksi',
            "Pemesanan {$kodeBooking} untuk {$qty} tiket {$tier['nama_tier']}. Total: " . format_rupiah($total) . ".");
        $pdo->commit();
        set_flash('SUCCESS', 'Pesanan berhasil dibuat. Selesaikan pembayaran dalam 15 menit.');
        header('Location: checkout.php?kode=' . urlencode($kodeBooking));
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[CREATE_ORDER] ' . $e->getMessage());
        set_flash('ERROR', $e->getMessage() ?: 'Gagal membuat pesanan.');
        header('Location: index.php');
        exit;
    }
}

// ---------- GET ?kode= : INVOICE ----------
if ($kode === '') {
    set_flash('ERROR', 'Kode transaksi tidak ditemukan.');
    header('Location: dashboard_peserta.php');
    exit;
}
$stmt = $pdo->prepare('SELECT t.*, e.judul_event FROM transaksi t JOIN events e ON e.id_event = t.id_event
                       WHERE t.kode_booking = ? AND t.id_user = ? LIMIT 1');
$stmt->execute([$kode, $id_user]);
$trx = $stmt->fetch();
if (!$trx) {
    http_response_code(403);
    require __DIR__ . '/../src/Views/layouts/header.php';
    echo '<div class="card card-elevated" style="max-width:560px;margin:40px auto;">
            <h1 style="font-size:56px;font-weight:800;color:var(--color-error);line-height:1;margin:0;">403</h1>
            <p style="margin-top:12px;">Transaksi tidak ditemukan atau bukan milik akun Anda.</p>
            <a href="dashboard_peserta.php" class="btn btn-outline">Kembali ke Dashboard</a>
          </div>';
    require __DIR__ . '/../src/Views/layouts/footer.php';
    exit;
}

$stmt = $pdo->prepare("SELECT td.*, tt.nama_tier FROM transaksi_detail td
                       JOIN tiket_tier tt ON tt.id_tier = td.id_tier WHERE td.id_transaksi = ?");
$stmt->execute([(int)$trx['id_transaksi']]);
$details = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT tp.*, tt.nama_tier, ke.nomor_kursi
                       FROM transaksi_peserta tp
                       LEFT JOIN tiket_tier tt ON tt.id_tier = tp.id_tier
                       LEFT JOIN kursi_event ke ON ke.id_kursi = tp.id_kursi
                       WHERE tp.id_transaksi = ? ORDER BY tp.id_peserta");
$stmt->execute([(int)$trx['id_transaksi']]);
$peserta = $stmt->fetchAll();

$deadlineIso = $trx['batas_bayar_at'] ? str_replace(' ', 'T', $trx['batas_bayar_at']) . '+07:00' : '';

$pageTitle   = 'Invoice — ' . $kode;
$pageSubtitle = $trx['judul_event'];
$activeNav   = '';
require __DIR__ . '/../src/Views/layouts/header.php';
?>
<div class="breadcrumb">
    <a href="dashboard_peserta.php">Dashboard Peserta</a> &rsaquo; Invoice <?= e($kode) ?>
</div>

<div class="card card-elevated">
    <div style="padding:16px;">
        <h2 style="margin:0 0 4px 0;">Invoice Pemesanan</h2>
        <div style="color:var(--color-text-muted);font-size:0.875rem;">
            <?= e($trx['judul_event']) ?> &bull; Kode: <code><?= e($kode) ?></code>
        </div>
        <div style="margin-top:8px;"><strong>Status:</strong>
            <span class="badge <?= status_class($trx['status_bayar']) ?>"><?= label_status($trx['status_bayar']) ?></span>
        </div>
    </div>
</div>

<?php if ($trx['status_bayar'] === 'Pending'): ?>
<div class="alert alert-warning" role="status" style="margin:16px 0;">
    Waktu pembayaran tersisa:
    <span id="countdown" data-deadline="<?= e($deadlineIso) ?>">Menghitung...</span>
    <span style="color:var(--color-text-muted);font-size:0.875rem;margin-left:8px;">
        Pesanan otomatis kadaluarsa setelah 15 menit.
    </span>
</div>
<?php elseif ($trx['status_bayar'] === 'Waiting Verification'): ?>
<div class="alert alert-info" role="status" style="margin:16px 0;">
    Bukti bayar Anda sudah diunggah dan menunggu verifikasi oleh keuangan.
    <?php if (!empty($trx['bukti_bayar'])): ?>
    <a href="bukti.php?kode=<?= e($kode) ?>" class="btn btn-outline btn-sm">Lihat Bukti</a>
    <?php endif; ?>
</div>
<?php elseif ($trx['status_bayar'] === 'Paid'): ?>
<div class="alert alert-success" role="status" style="margin:16px 0;">
    Pembayaran telah diverifikasi.
    <a href="etiket.php?kode=<?= e($kode) ?>" class="btn btn-primary btn-sm">Unduh E-Ticket</a>
    <a href="dashboard_peserta.php" class="btn btn-outline btn-sm">Kembali ke Dashboard</a>
</div>
<?php elseif ($trx['status_bayar'] === 'Expired'): ?>
<div class="alert alert-danger" role="status" style="margin:16px 0;">Pesanan kadaluarsa. Silakan buat pesanan baru.</div>
<?php elseif ($trx['status_bayar'] === 'Rejected'): ?>
<div class="alert alert-danger" role="status" style="margin:16px 0;">
    Pesanan ditolak.
    <?= !empty($trx['catatan_penolakan']) ? 'Alasan: ' . e($trx['catatan_penolakan']) : '' ?>
</div>
<?php endif; ?>

<div class="card card-elevated" style="margin-top:16px;">
    <div class="card-header"><h3 style="margin:0;">Rincian Biaya</h3></div>
    <div style="padding:16px;">
        <table style="width:100%;border:none;">
            <?php foreach ($details as $d): ?>
            <tr style="border:none;">
                <td style="border:none;"><?= e($d['nama_tier']) ?> x <?= (int)$d['jumlah'] ?></td>
                <td style="border:none;text-align:right;"><?= format_rupiah((int)$d['harga_satuan'] * (int)$d['jumlah']) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr style="border:none;"><td style="border:none;">Subtotal</td><td style="border:none;text-align:right;"><strong><?= format_rupiah((int)$trx['subtotal']) ?></strong></td></tr>
            <tr style="border:none;"><td style="border:none;">Diskon</td><td style="border:none;text-align:right;color:var(--color-success);">- <?= format_rupiah((int)$trx['diskon']) ?></td></tr>
            <tr style="border:none;"><td style="border:none;">Biaya layanan</td><td style="border:none;text-align:right;"><?= format_rupiah((int)$trx['biaya_layanan']) ?></td></tr>
            <tr style="border:none;"><td style="border:none;"><strong>Total</strong></td><td style="border:none;text-align:right;"><strong style="font-size:1.1rem;"><?= format_rupiah((int)$trx['total_bayar']) ?></strong></td></tr>
        </table>
    </div>
</div>

<div class="card card-elevated" style="margin-top:16px;">
    <div class="card-header"><h3 style="margin:0;">Data Peserta</h3></div>
    <div style="padding:16px;">
        <table class="table">
            <thead><tr><th>Nama</th><th>NIK</th><th>Tier</th><th>Kursi</th></tr></thead>
            <tbody>
            <?php foreach ($peserta as $p): ?>
            <tr>
                <td><?= e($p['nama_peserta']) ?></td>
                <td><code><?= e($p['nik_peserta']) ?></code></td>
                <td><?= e($p['nama_tier'] ?? '-') ?></td>
                <td><?= $p['nomor_kursi'] ? e($p['nomor_kursi']) : 'Free Standing' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($trx['status_bayar'] === 'Pending'): ?>
<div class="card card-elevated" style="margin-top:16px;">
    <div style="padding:16px;">
        <h3 style="margin:0 0 8px 0;">Upload Bukti Transfer</h3>
        <p style="color:var(--color-text-muted);font-size:0.875rem;margin-bottom:12px;">
            Transfer sesuai total ke rekening panitia. Cantumkan kode <?= e($kode) ?> sebagai keterangan.
        </p>
        <form method="POST" action="checkout.php?kode=<?= e($kode) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="upload_bukti">
            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label" for="buktiInput">Bukti transfer</label>
                <label class="upload-zone" data-upload-zone for="buktiInput">
                    <svg class="upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/>
                    </svg>
                    <strong>Klik atau seret bukti transfer ke sini</strong>
                    <small>JPG, PNG, atau WEBP &middot; maksimal 2 MB</small>
                    <span class="upload-name" data-upload-name>Belum ada file dipilih</span>
                    <input type="file" id="buktiInput" name="bukti" accept="image/jpeg,image/png,image/webp" required hidden>
                </label>
            </div>
            <button type="submit" class="btn btn-primary">Unggah Bukti</button>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
(function(){
    const el = document.getElementById('countdown');
    if (!el) return;
    const deadline = new Date(el.getAttribute('data-deadline')).getTime();
    function tick(){
        const diff = deadline - new Date().getTime();
        if (diff <= 0){
            el.textContent = 'Waktu habis';
            el.style.color = 'var(--color-error)';
            el.style.fontWeight = '700';
            return;
        }
        if (diff < 3 * 60 * 1000){
            el.style.color = 'var(--color-error)';
            el.style.fontWeight = '700';
        }
        const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const s = Math.floor((diff % (1000 * 60)) / 1000);
        el.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s + ' menit';
    }
    tick();
    setInterval(tick, 1000);
})();
</script>

<?php require __DIR__ . '/../src/Views/layouts/footer.php'; ?>
