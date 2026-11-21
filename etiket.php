<?php
/**
 * etiket.php — Cetak E-Ticket (Pemilik / Admin EO / Finance)
 * GET kode (kode_booking). Wajib status Paid. Daftar e-ticket per transaksi
 * dengan barcode SVG (HMAC), status check-in, dan unduhan JPG/PDF per lembar.
 */
declare(strict_types=1);
require_once __DIR__ . '/src/Config/database.php';
require_once __DIR__ . '/src/Middleware/middleware.php';
release_expired_transactions($pdo);

require_login();
$user = current_user();

if (!function_exists('pensi_mask_nik')) {
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

$kode = is_string($_GET['kode'] ?? null) ? mb_substr(trim($_GET['kode']), 0, 64) : '';
$transaksi = null;
$tiketList = [];
$notLunas = false;

if ($kode !== '') {
    $stmt = $pdo->prepare(
        'SELECT t.*, e.judul_event, e.lokasi, e.tgl_mulai, e.tipe_venue
           FROM transaksi t
           JOIN events e ON e.id_event = t.id_event
          WHERE t.kode_booking = ?'
    );
    $stmt->execute([$kode]);
    $transaksi = $stmt->fetch();

    if (!$transaksi) {
        set_flash('ERROR', 'Data transaksi tidak ditemukan.');
        header('Location: index.php');
        exit;
    }

    // Akses: pemilik transaksi, atau admin_eo / finance. Gate officers must
    // use gate_checkin.php; they cannot download arbitrary e-tickets/PII.
    $isOwner = ((int)$transaksi['id_user'] === (int)$user['id_user']);
    $isStaff = in_array($user['role'], ['admin_eo', 'finance'], true);
    if (!$isOwner && !$isStaff) {
        http_response_code(403);
        include __DIR__ . '/src/Views/403.php';
        exit;
    }

    if ($transaksi['status_bayar'] !== 'Paid') {
        $notLunas = true;
    } else {
        $stmtT = $pdo->prepare(
            'SELECT et.*, tt.nama_tier, k.nomor_kursi
               FROM e_tickets et
               JOIN tiket_tier tt ON tt.id_tier = et.id_tier
               LEFT JOIN kursi_event k ON k.id_kursi = et.id_kursi
              WHERE et.id_transaksi = ?
              ORDER BY et.id_etiket ASC'
        );
        $stmtT->execute([(int)$transaksi['id_transaksi']]);
        $tiketList = $stmtT->fetchAll();
    }
}

$pageTitle = 'E-Ticket ' . $kode;
$pageSubtitle = $transaksi ? e($transaksi['judul_event']) : 'PENSI EVENTPRO';
$activeNav = 'index.php';
require __DIR__ . '/src/Views/layouts/header.php';
?>

<div class="card card-elevated">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:16px;">
        <div>
            <h2 style="margin:0 0 4px 0;font-size:20px;">E-Ticket <?= e($kode) ?></h2>
            <p style="margin:0;color:var(--color-text-secondary);font-size:14px;">
                <?= e($transaksi['judul_event'] ?? '') ?> ·
                <?= e($transaksi['lokasi'] ?? '') ?> ·
                <?= format_tanggal($transaksi['tgl_mulai'] ?? null, false) ?? '' ?>
            </p>
        </div>
        <div class="no-print" style="display:flex;gap:8px;flex-wrap:wrap;">
            <button type="button" class="btn btn-primary" onclick="window.print();"><strong>🖨️ Cetak</strong></button>
        </div>
    </div>

    <?php if ($notLunas): ?>
        <div class="alert alert-info" role="status">
            <strong>E-ticket belum tersedia.</strong> E-ticket untuk transaksi ini akan diterbitkan
            setelah pembayaran berstatus <strong>Lunas</strong> (saat ini:
            <?= e(label_status($transaksi['status_bayar'])) ?>).
        </div>
    <?php elseif (empty($tiketList)): ?>
        <div class="empty-state">
            <p>Belum ada e-ticket yang diterbitkan untuk transaksi ini.</p>
        </div>
    <?php else: ?>
        <p style="margin-bottom:12px;color:var(--color-text-muted);font-size:14px;">
            Total <strong><?= count($tiketList) ?></strong> lembar e-ticket. NIK ditampilkan dengan maskir;
            NIK lengkap terdapat pada berkas unduhan dan divalidasi petugas gate saat check-in.
        </p>

        <div style="display:grid;grid-template:repeat(auto-fill,minmax(300px,1fr))/1fr;gap:16px;">
            <?php $no = 0; foreach ($tiketList as $t): $no++; ?>
                <div class="card" style="page-break-inside:avoid;">
                    <div style="border-bottom:2px solid #111827;padding-bottom:10px;margin-bottom:12px;">
                        <div style="font-size:16px;font-weight:700;letter-spacing:2px;">PENSI EVENTPRO</div>
                        <div style="font-size:13px;color:var(--color-text-secondary);margin-top:2px;">
                            <?= e($transaksi['judul_event']) ?>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px 16px;margin-bottom:14px;">
                        <div>
                            <div style="font-size:12px;color:var(--color-text-muted);text-transform:uppercase;">Nama Peserta</div>
                            <div style="font-weight:600;"><?= e($t['nama_peserta']) ?></div>
                        </div>
                        <div>
                            <div style="font-size:12px;color:var(--color-text-muted);text-transform:uppercase;">NIK</div>
                            <div style="font-family:monospace;font-size:13px;"><code><?= pensi_mask_nik($t['nik_peserta']) ?></code></div>
                        </div>
                        <div>
                            <div style="font-size:12px;color:var(--color-text-muted);text-transform:uppercase;">Tier</div>
                            <div style="font-weight:600;"><?= e($t['nama_tier']) ?></div>
                        </div>
                        <div>
                            <div style="font-size:12px;color:var(--color-text-muted);text-transform:uppercase;">Kursi</div>
                            <div style="font-weight:600;">
                                <?= !empty($t['nomor_kursi']) ? 'Kursi ' . e($t['nomor_kursi']) : 'Free Standing' ?>
                            </div>
                        </div>
                        <div>
                            <div style="font-size:12px;color:var(--color-text-muted);text-transform:uppercase;">Kode Tiket</div>
                            <div style="font-family:monospace;font-size:13px;font-weight:600;"><code><?= e($t['kode_tiket']) ?></code></div>
                        </div>
                        <div>
                            <div style="font-size:12px;color:var(--color-text-muted);text-transform:uppercase;">Status Check-in</div>
                            <span class="badge <?= $t['status_checkin'] === 'Sudah Hadir' ? 'badge-success' : 'badge-info' ?>">
                                <?= e($t['status_checkin']) ?>
                            </span>
                        </div>
                    </div>

                    <div style="border:1px dashed var(--color-border);border-radius:8px;padding:14px;text-align:center;background:#f8fafc;margin-bottom:12px;">
                        <div style="font-size:12px;color:var(--color-text-muted);margin-bottom:6px;">Barcode untuk Gate Entry</div>
                        <?= render_barcode_svg($t['kode_tiket'], $t['nik_peserta']) ?>
                        <div style="font-size:12px;color:var(--color-text-muted);margin-top:6px;"><?= e($t['kode_tiket']) ?></div>
                    </div>

                    <div class="no-print" style="display:flex;gap:8px;">
                        <a href="etiket_png.php?kode=<?= urlencode($kode) ?>&amp;n=<?= $no ?>" class="btn btn-outline btn-sm">Unduh JPG</a>
                        <a href="etiket_pdf.php?kode=<?= urlencode($kode) ?>&amp;n=<?= $no ?>" class="btn btn-outline btn-sm">Unduh PDF</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/src/Views/layouts/footer.php'; ?>
