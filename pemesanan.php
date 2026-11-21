<?php
/**
 * pemesanan.php — Halaman pemesanan tiket (v4)
 * Hanya role customer. GET id_event, id_tier, optional kursi[].
 * Tidak ada POST di sini; form POST ke checkout.php.
 */
require_once __DIR__ . '/src/Config/database.php';
require_once __DIR__ . '/src/Middleware/middleware.php';
require_role(['customer']);
release_expired_transactions($pdo);

$user    = current_user();
$id_user = (int)$user['id_user'];

$id_event = isset($_GET['id_event']) ? (int)$_GET['id_event'] : 0;
$id_tier  = isset($_GET['id_tier'])  ? (int)$_GET['id_tier']  : 0;
$qtyGet   = isset($_GET['qty'])      ? (int)$_GET['qty']      : 1;
if ($qtyGet < 1)  $qtyGet = 1;
if ($qtyGet > 5)  $qtyGet = 5;

$kursiInput = $_GET['kursi'] ?? null;
$kursiIds   = [];
if ($kursiInput !== null) {
    $raw = is_array($kursiInput) ? $kursiInput : array_filter(explode(',', (string)$kursiInput));
    foreach ($raw as $v) {
        $vi = (int)trim((string)$v);
        if ($vi > 0 && !in_array($vi, $kursiIds, true)) {
            $kursiIds[] = $vi;
        }
    }
}

if ($id_event <= 0 || $id_tier <= 0) {
    set_flash('ERROR', 'Event atau tier tidak valid.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM events WHERE id_event = ? LIMIT 1');
$stmt->execute([$id_event]);
$event = $stmt->fetch();
if (!$event) {
    set_flash('ERROR', 'Event tidak ditemukan.');
    header('Location: index.php');
    exit;
}
if ($event['status_event'] !== 'Published') {
    set_flash('ERROR', 'Event ini belum diterbitkan.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM tiket_tier WHERE id_tier = ? AND id_event = ? LIMIT 1');
$stmt->execute([$id_tier, $id_event]);
$tier = $stmt->fetch();
if (!$tier) {
    set_flash('ERROR', 'Tier tiket tidak valid untuk event ini.');
    header('Location: index.php');
    exit;
}
if (!empty($tier['batas_jual']) && strtotime($tier['batas_jual']) < time()) {
    set_flash('ERROR', 'Batas penjualan tier ini sudah lewat.');
    header('Location: index.php');
    exit;
}
if ((int)$tier['kuota_tersedia'] <= 0) {
    set_flash('ERROR', 'Kuota tier ini sudah habis.');
    header('Location: index.php');
    exit;
}

$tipeVenue = (string)$event['tipe_venue'];
if ($tipeVenue === 'Seated') {
    if (count($kursiIds) < 1 || count($kursiIds) > 5) {
        set_flash('ERROR', 'Pilih 1 hingga 5 kursi.');
        header('Location: index.php');
        exit;
    }
    $qty = count($kursiIds);
} else {
    $qty = $qtyGet;
    $kursiIds = [];
}

$limit = check_user_ticket_limit($pdo, $id_user, $id_event, $qty);
if (!$limit['ok']) {
    set_flash('ERROR', $limit['msg']);
    header('Location: dashboard_peserta.php');
    exit;
}
$hasActive = check_user_active_reservation($pdo, $id_user, $id_event);

$seatLabels = [];
if ($tipeVenue === 'Seated') {
    $stmt = $pdo->prepare('SELECT id_kursi, nomor_kursi FROM kursi_event WHERE id_tier = ? ORDER BY nomor_kursi');
    $stmt->execute([$id_tier]);
    foreach ($stmt->fetchAll() as $r) {
        $seatLabels[(int)$r['id_kursi']] = $r['nomor_kursi'];
    }
}

$pageTitle   = 'Pesan Tiket — ' . $event['judul_event'];
$pageSubtitle = $tier['nama_tier'] . ' · ' . format_rupiah((int)$tier['harga']) . '/tiket';
$activeNav   = 'index.php';
require __DIR__ . '/src/Views/layouts/header.php';
?>
<div class="breadcrumb">
    <a href="index.php">Katalog Event</a> &rsaquo; <span><?= e($event['judul_event']) ?></span> &rsaquo; Pemesanan
</div>

<?php if ($hasActive): ?>
<div class="alert alert-warning" role="status" style="margin-bottom:16px;">
    Anda sudah memiliki reservasi aktif pada event ini. Pastikan total tiket tidak melebihi batas 5 lembar per event.
</div>
<?php endif; ?>

<h2 style="margin:0 0 8px 0;">Formulir Pemesanan</h2>
<p style="color:var(--color-text-muted);margin-bottom:16px;">
    Isi identitas setiap peserta. Untuk <strong>Free Standing</strong>, atur jumlah tiket (maksimal 5).
</p>

<form method="POST" action="checkout.php" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id_event" value="<?= $id_event ?>">
    <input type="hidden" name="id_tier" value="<?= $id_tier ?>">
    <input type="hidden" id="qtyHidden" name="qty" value="<?= $qty ?>">
    <input type="hidden" name="kursi" value="<?= e(implode(',', $kursiIds)) ?>">

    <div class="card card-elevated" style="margin-bottom:16px;">
        <div class="card-header"><h3 style="margin:0;">1. Data Peserta</h3></div>
        <div style="padding:16px;">
            <?php if ($tipeVenue === 'Free Standing'): ?>
            <div class="form-group" style="margin-bottom:12px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <label class="form-label" style="margin:0;">Jumlah tiket</label>
                <input type="number" id="qtySelector" min="1" max="5" value="<?= $qty ?>" style="width:90px;" class="form-control">
                <span style="color:var(--color-text-muted);font-size:0.875rem;">maksimal 5 lembar</span>
                <button type="button" id="btnAdd" class="btn btn-primary btn-sm" style="margin-left:auto;">+ Tambah</button>
            </div>
            <?php endif; ?>
            <div id="participantRows">
                <?php for ($i = 0; $i < $qty; $i++): ?>
                <div class="participant" style="border:1px solid var(--color-border-light);border-radius:8px;padding:14px;margin-bottom:12px;background:var(--color-bg-muted);" data-index="<?= $i ?>">
                    <div style="font-weight:700;color:var(--color-accent);margin-bottom:8px;">Peserta #<?= $i + 1 ?></div>
                    <div class="form-group" style="margin-bottom:8px;">
                        <label class="form-label">Nama Lengkap Peserta</label>
                        <input type="text" name="nama_peserta[]" class="form-control" required maxlength="100" placeholder="Nama lengkap sesuai KTP">
                    </div>
                    <div class="form-group" style="margin-bottom:8px;">
                        <label class="form-label">NIK (16 digit angka)</label>
                        <input type="text" name="nik_peserta[]" class="form-control" required pattern="[0-9]{16}" maxlength="16" placeholder="16 digit">
                    </div>
                    <?php if ($tipeVenue === 'Seated'): ?>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Kursi</label>
                        <input type="text" class="form-control" value="<?= e($seatLabels[$kursiIds[$i]] ?? '-') ?>" readonly>
                    </div>
                    <?php else: ?>
                    <div style="margin-bottom:0;">
                        <button type="button" onclick="removeRow(this)" class="btn btn-ghost btn-sm" style="color:var(--color-error);">Hapus</button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <div class="card card-elevated" style="margin-bottom:16px;">
        <div class="card-header"><h3 style="margin:0;">2. Voucher Promo (Opsional)</h3></div>
        <div style="padding:16px;">
            <div class="form-group" style="margin-bottom:8px;">
                <label class="form-label">Kode voucher</label>
                <input type="text" name="kode_voucher" class="form-control" placeholder="Masukkan kode voucher">
                <small style="color:var(--color-text-muted);">Masukkan kode promo jika akan digunakan; divealidasi ulang saat checkout.</small>
            </div>
        </div>
    </div>

    <div class="card card-elevated">
        <div class="card-header"><h3 style="margin:0;">3. Ringkasan Biaya</h3></div>
        <div style="padding:16px;">
            <table style="width:100%;border:none;" id="summaryTable">
                <tr style="border:none;">
                    <td style="border:none;">Harga tiket</td>
                    <td style="border:none;text-align:right;"><span id="sHarga"><?= format_rupiah((int)$tier['harga']) ?></span> x <span id="sQty"><?= $qty ?></span></td>
                </tr>
                <tr style="border:none;">
                    <td style="border:none;">Subtotal</td>
                    <td style="border:none;text-align:right;" id="sSubtotal"><?= format_rupiah((int)$tier['harga'] * $qty) ?></td>
                </tr>
                <tr style="border:none;">
                    <td style="border:none;">Biaya layanan (<span id="sFeeQty"><?= $qty ?></span> x Rp 2.500)</td>
                    <td style="border:none;text-align:right;" id="sBiaya"><?= format_rupiah(BIAYA_LAYANAN_PER_TIKET * $qty) ?></td>
                </tr>
                <tr style="border:none;">
                    <td style="border:none;"><strong>Estimasi total</strong></td>
                    <td style="border:none;text-align:right;"><strong id="sTotal"><?= format_rupiah((int)$tier['harga'] * $qty + BIAYA_LAYANAN_PER_TIKET * $qty) ?></strong></td>
                </tr>
            </table>
            <p style="color:var(--color-text-muted);font-size:0.875rem;margin-top:8px;">Total akhir dihitung ulang setelah voucher divalidasi di checkout.</p>
        </div>
    </div>

    <div style="margin-top:16px;">
        <div class="form-group" style="margin-bottom:18px;">
            <label class="form-label">Metode Pembayaran</label>
            <div class="pay-methods">
                <label class="pay-option">
                    <input type="radio" name="metode_bayar" value="Transfer" checked>
                    <span class="pay-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="6" width="20" height="13" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/></svg></span>
                    <span><strong>Transfer Bank</strong><small>BCA 7615553221 &middot; a.n. Panitia PENSI</small></span>
                </label>
                <label class="pay-option">
                    <input type="radio" name="metode_bayar" value="Cash">
                    <span class="pay-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 9v6M18 9v6"/></svg></span>
                    <span><strong>Tunai (Cash)</strong><small>Bayar langsung ke sekretariat panitia</small></span>
                </label>
            </div>
        </div>
        <button type="submit" class="btn btn-primary" style="padding:14px 28px;">Lanjut ke Checkout &rarr;</button>
    </div>
</form>

<script>
(function(){
    const tipe = '<?= $tipeVenue === 'Free Standing' ? 'freestanding' : 'seated' ?>';
    if (tipe !== 'freestanding') return;
    const qtySelector = document.getElementById('qtySelector');
    const qtyHidden   = document.getElementById('qtyHidden');
    const container   = document.getElementById('participantRows');
    const btnAdd      = document.getElementById('btnAdd');

    function rowHtml(i){
        return `<div class="participant" style="border:1px solid var(--color-border-light);border-radius:8px;padding:14px;margin-bottom:12px;background:var(--color-bg-muted);" data-index="${i}">
            <div style="font-weight:700;color:var(--color-accent);margin-bottom:8px;">Peserta #${i+1}</div>
            <div class="form-group" style="margin-bottom:8px;">
                <label class="form-label">Nama Lengkap Peserta</label>
                <input type="text" name="nama_peserta[]" class="form-control" required maxlength="100" placeholder="Nama lengkap sesuai KTP">
            </div>
            <div class="form-group" style="margin-bottom:8px;">
                <label class="form-label">NIK (16 digit angka)</label>
                <input type="text" name="nik_peserta[]" class="form-control" required pattern="[0-9]{16}" maxlength="16" placeholder="16 digit">
            </div>
            <div style="margin-bottom:0;">
                <button type="button" onclick="removeRow(this)" class="btn btn-ghost btn-sm" style="color:var(--color-error);">Hapus</button>
            </div>
        </div>`;
    }
    function render(n){
        container.innerHTML = '';
        for (let i=0;i<n;i++) container.innerHTML += rowHtml(i);
        qtyHidden.value = n;
        qtySelector.value = n;
        updateSummary();
    }
    window.removeRow = function(btn){
        if (container.querySelectorAll('.participant').length <= 1) return;
        btn.closest('.participant').remove();
        render(container.querySelectorAll('.participant').length);
    };
    qtySelector.addEventListener('change', function(){
        let n = Math.max(1, Math.min(5, parseInt(this.value) || 1));
        render(n);
    });
    btnAdd.addEventListener('click', function(){
        let n = container.querySelectorAll('.participant').length;
        if (n >= 5) return;
        render(n + 1);
    });
    function updateSummary(){
        const harga = <?= (int)$tier['harga'] ?>;
        const n     = parseInt(qtyHidden.value) || 1;
        const sub   = harga * n;
        const biay  = <?= BIAYA_LAYANAN_PER_TIKET ?> * n;
        const fmt   = (v)=>new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',minimumFractionDigits:0}).format(v).replace('IDR','Rp ');
        document.getElementById('sQty').textContent    = n;
        document.getElementById('sFeeQty').textContent = n;
        document.getElementById('sSubtotal').textContent = fmt(sub);
        document.getElementById('sBiaya').textContent    = fmt(biay);
        document.getElementById('sTotal').textContent    = fmt(sub + biay);
    }
    updateSummary();
})();
</script>

<?php require __DIR__ . '/src/Views/layouts/footer.php'; ?>
