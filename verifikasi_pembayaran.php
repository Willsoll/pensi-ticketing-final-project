<?php
/**
 * verifikasi_pembayaran.php — Verifikasi Pembayaran (Finance)
 * Daftar transaksi menunggu verifikasi, detail per kode_booking,
 * setujui (Paid) / tolak (Rejected) dengan transaksi FOR UPDATE, kuota & kursi,
 * terbitan e_tickets, dan audit log.
 */
declare(strict_types=1);
require_once __DIR__ . '/src/Config/database.php';
require_once __DIR__ . '/src/Middleware/middleware.php';
maybe_release_expired_transactions($pdo);

require_role(['finance']);

$user = current_user();

if (!function_exists('pensi_mask_nik')) {
    /**
     * Maskir tengah digit NIK untuk tampilan UI.
     */
    function pensi_mask_nik(?string $nik): string
    {
        if ($nik === null || $nik === '') {
            return '-';
        }
        $nik = (string)$nik;
        $len = strlen($nik);
        if ($len <= 8) {
            return str_repeat('X', $len);
        }
        return substr($nik, 0, 4) . str_repeat('X', $len - 8) . substr($nik, -4);
    }
}

$kodeDetail = is_string($_GET['kode'] ?? null) ? mb_substr(trim($_GET['kode']), 0, 64) : '';

// ---------------------------------------------------------------------------
// Proses SETUJUI / TOLAK (POST)
// ---------------------------------------------------------------------------
if (( $_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('CSRF tidak valid');
    }

    $aksi   = $_POST['aksi'] ?? '';
    $kode   = trim((string)($_POST['kode_booking'] ?? ''));
    $catatanRaw = $_POST['catatan_penolakan'] ?? '';
    $catatan = is_string($catatanRaw) ? mb_substr(trim($catatanRaw), 0, 255) : '';

    if (!in_array($aksi, ['setujui', 'tolak'], true) || $kode === '') {
        set_flash('ERROR', 'Permintaan verifikasi tidak valid.');
        header('Location: verifikasi_pembayaran.php');
        exit;
    }
    if ($aksi === 'tolak' && mb_strlen($catatan) < 5) {
        set_flash('ERROR', 'Catatan penolakan wajib diisi minimal 5 karakter.');
        header('Location: verifikasi_pembayaran.php?kode=' . urlencode($kode));
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'SELECT t.*, u.nama_lengkap AS nama_user
               FROM transaksi t
               JOIN users u ON u.id_user = t.id_user
              WHERE t.kode_booking = ?
              FOR UPDATE'
        );
        $stmt->execute([$kode]);
        $trx = $stmt->fetch();

        if (!$trx) {
            throw new Exception('Transaksi tidak ditemukan.');
        }

        // Hanya Waiting Verification, atau Pending yang sudah melampirkan bukti.
        $bisaDiverifikasi = $trx['status_bayar'] === 'Waiting Verification'
            || ($trx['status_bayar'] === 'Pending'
                && (!empty($trx['bukti_bayar']) || ($trx['metode_bayar'] ?? 'Transfer') === 'Cash'));
        if (!$bisaDiverifikasi) {
            throw new Exception('Transaksi tidak dapat diverifikasi (status/bukti tidak memenuhi syarat).');
        }

        $stmtP = $pdo->prepare(
            'SELECT * FROM transaksi_peserta WHERE id_transaksi = ? ORDER BY id_peserta ASC'
        );
        $stmtP->execute([(int)$trx['id_transaksi']]);
        $peserta = $stmtP->fetchAll();

        $qtyPerTier = [];
        foreach ($peserta as $p) {
            if (!isset($qtyPerTier[(int)$p['id_tier']])) {
                $qtyPerTier[(int)$p['id_tier']] = 0;
            }
            $qtyPerTier[(int)$p['id_tier']]++;
        }

        $updSeat = $pdo->prepare(
            "UPDATE kursi_event SET status_kursi = ? WHERE id_kursi = ? AND status_kursi = 'Locked'"
        );
        $relLock = $pdo->prepare(
            'UPDATE tiket_tier SET kuota_locked = GREATEST(0, kuota_locked - ?) WHERE id_tier = ?'
        );
        $addTersedia = $pdo->prepare(
            'UPDATE tiket_tier SET kuota_tersedia = kuota_tersedia + ? WHERE id_tier = ?'
        );

        $jumlahTiket = count($peserta);

        if ($aksi === 'setujui') {
            $pdo->prepare(
                "UPDATE transaksi SET status_bayar = 'Paid', catatan_penolakan = NULL
                 WHERE id_transaksi = ? AND status_bayar IN ('Waiting Verification','Pending')"
            )->execute([(int)$trx['id_transaksi']]);

            // Kursi Locked -> Sold; e_tickets satu per peserta.
            $maxId = (int)$pdo->query('SELECT COALESCE(MAX(id_etiket), 0) FROM e_tickets')->fetchColumn();
            $insTiket = $pdo->prepare(
                'INSERT INTO e_tickets
                    (id_transaksi, id_tier, id_kursi, kode_tiket, nama_peserta, nik_peserta, status_checkin)
                 VALUES (?, ?, ?, ?, ?, ?, \'Belum Hadir\')'
            );
            $no = $maxId;
            foreach ($peserta as $p) {
                if (!empty($p['id_kursi'])) {
                    $updSeat->execute(['Sold', (int)$p['id_kursi']]);
                }
                $no++;
                $insTiket->execute([
                    (int)$trx['id_transaksi'],
                    (int)$p['id_tier'],
                    !empty($p['id_kursi']) ? (int)$p['id_kursi'] : null,
                    generate_kode_tiket($no),
                    $p['nama_peserta'],
                    $p['nik_peserta'],
                ]);
            }
            foreach ($qtyPerTier as $idTier => $qty) {
                $relLock->execute([$qty, (int)$idTier]);
            }

            catat_audit_log(
                $pdo,
                (int)$user['id_user'],
                'VERIFY',
                'transaksi',
                "Finance menyetujui pembayaran {$kode} (" . format_rupiah((int)$trx['total_bayar']) . ")
                 menjadi Paid; {$jumlahTiket} e-terbit diterbitkan, kursi ditetapkan Sold."
            );
            set_flash('SUCCESS', "Transaksi {$kode} disetujui menjadi PAID dan {$jumlahTiket} e-ticket telah diterbitkan.");
        } else {
            $pdo->prepare(
                "UPDATE transaksi SET status_bayar = 'Rejected', catatan_penolakan = ?
                 WHERE id_transaksi = ? AND status_bayar IN ('Waiting Verification','Pending')"
            )->execute([$catatan, (int)$trx['id_transaksi']]);

            foreach ($peserta as $p) {
                if (!empty($p['id_kursi'])) {
                    $updSeat->execute(['Available', (int)$p['id_kursi']]);
                }
            }
            foreach ($qtyPerTier as $idTier => $qty) {
                $relLock->execute([$qty, (int)$idTier]);
                $addTersedia->execute([$qty, (int)$idTier]);
            }
            if (!empty($trx['id_voucher'])) {
                $pdo->prepare(
                    'UPDATE voucher_promo SET kuota_pakai = kuota_pakai + 1 WHERE id_voucher = ?'
                )->execute([(int)$trx['id_voucher']]);
            }

            catat_audit_log(
                $pdo,
                (int)$user['id_user'],
                'REJECT',
                'transaksi',
                "Finance menolak pembayaran {$kode}. Alasan: {$catatan}. Kursi dan kuota dikembalikan."
            );
            set_flash('SUCCESS', "Transaksi {$kode} DITOLAK; kuota dan kursi telah dikembalikan.");
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[VERIFIKASI_PEMBAYARAN] ' . $e->getMessage());
        set_flash('ERROR', 'Gagal memproses verifikasi (kesalahan sistem).');
    }

    header('Location: verifikasi_pembayaran.php');
    exit;
}

// ---------------------------------------------------------------------------
// Tampilan daftar + detail panel
// ---------------------------------------------------------------------------
$stmtList = $pdo->prepare(
    'SELECT t.*, u.nama_lengkap AS nama_user, u.email AS email_user, e.judul_event
       FROM transaksi t
       JOIN users u ON u.id_user = t.id_user
       JOIN events e ON e.id_event = t.id_event
      WHERE t.status_bayar IN (\'Waiting Verification\', \'Pending\')
      ORDER BY t.created_at DESC'
);
$stmtList->execute();
$transaksiList = $stmtList->fetchAll();

// Ringkasan
$stmtMenunggu = $pdo->query("SELECT COUNT(*) FROM transaksi WHERE status_bayar = 'Waiting Verification'");
$menunggu = (int)$stmtMenunggu->fetchColumn();
$stmtLunas = $pdo->query("SELECT COUNT(*) FROM transaksi WHERE status_bayar = 'Paid' AND DATE(created_at) = CURDATE()");
$lunasHariIni = (int)$stmtLunas->fetchColumn();
$stmtDitolak = $pdo->query("SELECT COUNT(*) FROM transaksi WHERE status_bayar = 'Rejected'");
$ditolak = (int)$stmtDitolak->fetchColumn();

$detail = null;
$detailPeserta = [];
$detailDetail = [];
if ($kodeDetail !== '') {
    $stmtD = $pdo->prepare(
        'SELECT t.*, u.nama_lengkap, u.email, u.no_hp, e.judul_event, e.lokasi, e.tgl_mulai, e.tipe_venue
           FROM transaksi t
           JOIN users u ON u.id_user = t.id_user
           JOIN events e ON e.id_event = t.id_event
          WHERE t.kode_booking = ?'
    );
    $stmtD->execute([$kodeDetail]);
    $detail = $stmtD->fetch();

    if ($detail) {
        $stmtDP = $pdo->prepare(
            'SELECT p.*, t2.nama_tier, k.nomor_kursi
               FROM transaksi_peserta p
               JOIN tiket_tier t2 ON t2.id_tier = p.id_tier
               LEFT JOIN kursi_event k ON k.id_kursi = p.id_kursi
              WHERE p.id_transaksi = ?
              ORDER BY p.id_peserta ASC'
        );
        $stmtDP->execute([(int)$detail['id_transaksi']]);
        $detailPeserta = $stmtDP->fetchAll();

        $stmtDD = $pdo->prepare(
            'SELECT d.*, t2.nama_tier
               FROM transaksi_detail d
               JOIN tiket_tier t2 ON t2.id_tier = d.id_tier
              WHERE d.id_transaksi = ?
              ORDER BY d.id_detail ASC'
        );
        $stmtDD->execute([(int)$detail['id_transaksi']]);
        $detailDetail = $stmtDD->fetchAll();
    }
}

$pageTitle = 'Verifikasi Pembayaran';
$pageSubtitle = 'Tinjau bukti transfer, setujui ke PAID atau tolak dengan catatan';
$activeNav = 'verifikasi_pembayaran.php';
require __DIR__ . '/src/Views/layouts/header.php';
?>

<div class="stat-grid">
    <div class="stat-box">
        <div class="stat-box-label">Menunggu Verifikasi</div>
        <div class="stat-box-value"><?= (int)$menunggu ?></div>
    </div>
    <div class="stat-box">
        <div class="stat-box-label">Lunas Hari Ini</div>
        <div class="stat-box-value"><?= (int)$lunasHariIni ?></div>
    </div>
    <div class="stat-box">
        <div class="stat-box-label">Ditolak</div>
        <div class="stat-box-value"><?= (int)$ditolak ?></div>
    </div>
</div>

<div class="card card-elevated">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;">
        <div>
            <h2 style="margin:0 0 6px 0;font-size:20px;">Daftar Transaksi Menunggu Verifikasi</h2>
            <p style="margin:0;color:var(--color-text-secondary);font-size:14px;">
                Status yang ditampilkan: <strong>Menunggu Verifikasi</strong> dan <strong>Pending (dengan bukti)</strong>.
            </p>
        </div>
        <div style="max-width:320px;"></div>
    </div>

    <?php if (empty($transaksiList)): ?>
        <div class="empty-state">
            <p>Tidak ada transaksi yang menunggu verifikasi saat ini.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kode Booking</th>
                        <th>Pembeli</th>
                        <th>Event</th>
                        <th>Total</th>
                        <th>Dibuat</th>
                        <th>Batas Bayar</th>
                        <th>Bukti</th>
                        <th>Status</th>
                        <th style="width:22%;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transaksiList as $t): ?>
                        <tr>
                            <td><code style="font-size:13px;"><?= e($t['kode_booking']) ?></code></td>
                            <td>
                                <strong><?= e($t['nama_user']) ?></strong><br>
                                <small><?= e($t['email_user']) ?></small>
                            </td>
                            <td><?= e($t['judul_event']) ?></td>
                            <td><strong><?= format_rupiah((int)$t['total_bayar']) ?></strong></td>
                            <td><small><?= format_tanggal($t['created_at']) ?></small></td>
                            <td><small><?= format_tanggal($t['batas_bayar_at']) ?></small></td>
                            <td align="center">
                                <?php if (($t['metode_bayar'] ?? 'Transfer') === 'Cash'): ?>
                                    <span class="badge badge-warning">Tunai</span>
                                <?php elseif (!empty($t['bukti_bayar'])): ?>
                                    <button type="button" class="btn btn-ghost btn-sm" data-proof="bukti.php?kode=<?= urlencode($t['kode_booking']) ?>">Lihat bukti</button>
                                <?php else: ?>
                                    <small style="color:var(--color-text-muted);">Belum ada</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= status_class($t['status_bayar']) ?>">
                                    <?= e(label_status($t['status_bayar'])) ?>
                                </span>
                            </td>
                            <td align="center">
                                <a href="verifikasi_pembayaran.php?kode=<?= urlencode($t['kode_booking']) ?>" class="btn btn-outline btn-sm">Detail &amp; Verifikasi</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($detail): ?>
    <?php $metodeDetail = $detail['metode_bayar'] ?? 'Transfer';
          $eligible = $detail['status_bayar'] === 'Waiting Verification'
        || ($detail['status_bayar'] === 'Pending' && (!empty($detail['bukti_bayar']) || $metodeDetail === 'Cash')); ?>
    <div class="card card-elevated" style="margin-top:24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
            <div>
                <h2 style="margin:0 0 4px 0;font-size:20px;">Detail Transaksi</h2>
                <div style="color:var(--color-text-secondary);font-size:14px;">
                    Kode Booking: <code><?= e($detail['kode_booking']) ?></code>
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <?php if ($detail['status_bayar'] === 'Paid'): ?>
                    <a href="etiket.php?kode=<?= urlencode($detail['kode_booking']) ?>" class="btn btn-primary btn-sm">E-Ticket Peserta</a>
                <?php endif; ?>
                <span class="badge <?= status_class($detail['status_bayar']) ?>">
                    <?= e(label_status($detail['status_bayar'])) ?>
                </span>
                <span class="badge <?= ($detail['metode_bayar'] ?? 'Transfer') === 'Cash' ? 'badge-warning' : 'badge-info' ?>">
                    <?= ($detail['metode_bayar'] ?? 'Transfer') === 'Cash' ? 'Tunai' : 'Transfer' ?>
                </span>
                <?php if ($eligible): ?>
                    <button type="submit" form="formSetujui" class="btn btn-outline btn-sm">Setujui (Paid)</button>
                    <button type="submit" form="formTolak" class="btn btn-outline btn-sm">Tolak (Rejected)</button>
                <?php else: ?>
                    <span class="badge badge-muted" title="Tidak ada bukti bayar">Tidak dapat diverifikasi</span>
                <?php endif; ?>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-bottom:18px;">
            <div class="card" style="padding:14px;border:1px solid var(--color-border);">
                <div class="form-label" style="font-size:12px;color:var(--color-text-muted);text-transform:uppercase;">Pembeli</div>
                <div style="font-weight:600;"><?= e($detail['nama_lengkap']) ?></div>
                <div style="font-size:13px;"><?= e($detail['email']) ?></div>
                <div style="font-size:13px;"><?= e($detail['no_hp'] ?? '-') ?></div>
            </div>
            <div class="card" style="padding:14px;border:1px solid var(--color-border);">
                <div class="form-label" style="font-size:12px;color:var(--color-text-muted);text-transform:uppercase;">Event</div>
                <div style="font-weight:600;"><?= e($detail['judul_event']) ?></div>
                <div style="font-size:13px;"><?= e($detail['lokasi']) ?>, <?= format_tanggal($detail['tgl_mulai'], false) ?></div>
                <div style="font-size:13px;"><?= e($detail['tipe_venue']) ?></div>
            </div>
            <div class="card" style="padding:14px;border:1px solid var(--color-border);">
                <div class="form-label" style="font-size:12px;color:var(--color-text-muted);text-transform:uppercase;">Total Pembayaran</div>
                <div style="font-size:18px;font-weight:700;"><?= format_rupiah((int)$detail['total_bayar']) ?></div>
                <div style="font-size:13px;color:var(--color-text-muted);">Bukti: <?= !empty($detail['bukti_bayar']) ? e($detail['bukti_bayar']) : 'Belum diunggah' ?></div>
            </div>
            <div class="card" style="padding:14px;border:1px solid var(--color-border);">
                <div class="form-label" style="font-size:12px;color:var(--color-text-muted);text-transform:uppercase;">Status / Dibuat</div>
                <div style="font-weight:600;"><?= e(label_status($detail['status_bayar'])) ?></div>
                <div style="font-size:13px;"><?= format_tanggal($detail['created_at']) ?></div>
                <div style="font-size:13px;">Batas: <?= format_tanggal($detail['batas_bayar_at']) ?></div>
            </div>
        </div>

        <?php if (!empty($detail['bukti_bayar'])): ?>
            <div style="margin-bottom:18px;">
                <div class="form-label" style="font-size:12px;color:var(--color-text-muted);text-transform:uppercase;">Pratinjau Bukti Bayar</div>
                <img src="<?= e($detail['bukti_bayar']) ?>" alt="Bukti bayar" style="max-width:340px;border:1px solid var(--color-border);border-radius:8px;background:#fff;padding:6px;display:block;margin-top:8px;">
            </div>
        <?php endif; ?>

        <?php if (!empty($detailDetail)): ?>
            <div class="table-container" style="margin-bottom:16px;">
                <table class="table">
                    <thead>
                        <tr><th>Detail Transaksi (Tier)</th><th style="width:14%;">Qty</th><th style="width:18%;">Harga Satuan</th><th style="width:18%;">Subtotal</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detailDetail as $d): ?>
                            <tr>
                                <td><?= e($d['nama_tier']) ?></td>
                                <td align="center"><?= (int)$d['jumlah'] ?></td>
                                <td><?= format_rupiah((int)$d['harga_satuan']) ?></td>
                                <td><?= format_rupiah((int)$d['subtotal']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="table-container" style="margin-bottom:16px;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Peserta</th>
                        <th style="width:22%;">NIK</th>
                        <th style="width:16%;">Tier</th>
                        <th style="width:18%;">Kursi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detailPeserta as $p): ?>
                        <tr>
                            <td><strong><?= e($p['nama_peserta']) ?></strong></td>
                            <td><code><?= pensi_mask_nik($p['nik_peserta']) ?></code></td>
                            <td><?= e($p['nama_tier']) ?></td>
                                <td><?= !empty($p['nomor_kursi']) ? 'Kursi ' . e($p['nomor_kursi']) : 'Free Standing' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($detail['bukti_bayar'])): ?>
            <div style="margin:8px 0 16px 0;">
                <button type="button" class="btn btn-outline btn-sm" data-proof="bukti.php?kode=<?= urlencode($detail['kode_booking']) ?>">Lihat Bukti Pembayaran</button>
            </div>
        <?php endif; ?>

        <?php if ($eligible): ?>
            <form id="formSetujui" method="POST" action="verifikasi_pembayaran.php" style="display:inline;" data-kode="<?= e($detail['kode_booking']) ?>" onsubmit="return confirm('Setujui transaksi ' + this.dataset.kode + ' menjadi PAID? E-ticket akan diterbitkan dan kursi ditetapkan Sold.');">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="setujui">
                <input type="hidden" name="kode_booking" value="<?= e($detail['kode_booking']) ?>">
            </form>
            <form id="formTolak" method="POST" action="verifikasi_pembayaran.php" style="display:inline;margin-left:10px;">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="tolak">
                <input type="hidden" name="kode_booking" value="<?= e($detail['kode_booking']) ?>">
                <div class="form-group" style="display:inline-block;margin-top:10px;">
                    <label class="form-label">Catatan Penolakan <small style="font-weight:normal;color:var(--color-text-muted);">(min 5 karakter)</small></label>
                    <textarea name="catatan_penolakan" class="form-control" rows="2" required minlength="5"></textarea>
                </div>
                <button type="submit" class="btn btn-outline btn-sm">Konfirmasi Tolak</button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>


<div class="modal-overlay" id="proofModal" hidden>
    <div class="modal-card proof-card" role="dialog" aria-modal="true" aria-label="Bukti pembayaran">
        <button type="button" class="modal-close" data-modal-close aria-label="Tutup">&times;</button>
        <img id="proofImage" src="" alt="Bukti pembayaran">
    </div>
</div>

<?php require __DIR__ . '/src/Views/layouts/footer.php'; ?>
