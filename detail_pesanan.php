<?php
/**
 * detail_pesanan.php — Detail pesanan (v4)
 * require_login; owner atau admin_eo/finance boleh melihat.
 */
require_once __DIR__ . '/src/Config/database.php';
require_once __DIR__ . '/src/Middleware/middleware.php';
require_login();
release_expired_transactions($pdo);

$user    = current_user();
$id_user = (int)$user['id_user'];
$role    = $user['role'];

$kode = $_GET['kode'] ?? '';
if ($kode === '') {
    set_flash('ERROR', 'Kode transaksi tidak ditemukan.');
    header('Location: dashboard_peserta.php');
    exit;
}

$stmt = $pdo->prepare('SELECT t.*, e.judul_event FROM transaksi t
                       JOIN events e ON e.id_event = t.id_event
                       WHERE t.kode_booking = ? LIMIT 1');
$stmt->execute([$kode]);
$trx = $stmt->fetch();
if (!$trx) {
    http_response_code(404);
    require __DIR__ . '/src/Views/layouts/header.php';
    echo '<div class="card card-elevated" style="max-width:560px;margin:40px auto;">
            <h1 style="font-size:56px;font-weight:800;color:var(--color-error);line-height:1;margin:0;">404</h1>
            <p style="margin-top:12px;">Transaksi tidak ditemukan.</p>
            <a href="dashboard_peserta.php" class="btn btn-outline">Kembali ke Dashboard</a>
          </div>';
    require __DIR__ . '/src/Views/layouts/footer.php';
    exit;
}

$isOwner  = $trx['id_user'] === $id_user;
$isStaff  = in_array($role, ['admin_eo', 'finance'], true);
if (!$isOwner && !$isStaff) {
    http_response_code(403);
    require __DIR__ . '/src/Views/layouts/header.php';
    require __DIR__ . '/src/Views/403.php';
    require __DIR__ . '/src/Views/layouts/footer.php';
    exit;
}

$stmt = $pdo->prepare("SELECT tp.*, tt.nama_tier, ke.nomor_kursi, et.kode_tiket, et.status_checkin
                       FROM transaksi_peserta tp
                       LEFT JOIN tiket_tier tt ON tt.id_tier = tp.id_tier
                       LEFT JOIN kursi_event ke ON ke.id_kursi = tp.id_kursi
                       LEFT JOIN e_tickets et ON et.id_transaksi = tp.id_transaksi
                                        AND et.nik_peserta = tp.nik_peserta
                       WHERE tp.id_transaksi = ? ORDER BY tp.id_peserta");
$stmt->execute([(int)$trx['id_transaksi']]);
$peserta = $stmt->fetchAll();

$pageTitle   = 'Detail Pesanan — ' . $kode;
$pageSubtitle = $trx['judul_event'];
$activeNav   = 'dashboard_peserta.php';
require __DIR__ . '/src/Views/layouts/header.php';
?>
<div class="breadcrumb">
    <a href="dashboard_peserta.php">Dashboard Peserta</a> &rsaquo; Detail <?= e($kode) ?>
</div>

<div class="card card-elevated" style="margin-bottom:16px;">
    <div style="padding:16px;">
        <h2 style="margin:0 0 4px 0;">Detail Pesanan</h2>
        <div style="color:var(--color-text-muted);font-size:0.875rem;">
            <?= e($trx['judul_event']) ?> &bull; Kode: <code><?= e($kode) ?></code>
        </div>
        <table style="width:100%;border:none;margin-top:10px;">
            <tr style="border:none;">
                <td style="border:none;width:30%;">Status</td>
                <td style="border:none;">
                    <span class="badge <?= status_class($trx['status_bayar']) ?>"><?= label_status($trx['status_bayar']) ?></span>
                </td>
            </tr>
            <tr style="border:none;">
                <td style="border:none;">Total Bayar</td>
                <td style="border:none;"><strong><?= format_rupiah((int)$trx['total_bayar']) ?></strong></td>
            </tr>
            <tr style="border:none;">
                <td style="border:none;">Tanggal Pesan</td>
                <td style="border:none;"><?= format_tanggal($trx['created_at']) ?></td>
            </tr>
            <?php if ($trx['batas_bayar_at']): ?>
            <tr style="border:none;">
                <td style="border:none;">Batas Bayar</td>
                <td style="border:none;"><?= format_tanggal($trx['batas_bayar_at']) ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($trx['catatan_penolakan'])): ?>
            <tr style="border:none;">
                <td style="border:none;">Catatan Penolakan</td>
                <td style="border:none;color:var(--color-error);"><?= e($trx['catatan_penolakan']) ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

<div class="card card-elevated">
    <div class="card-header"><h2 style="margin:0;">Peserta Tiket</h2></div>
    <div style="padding:16px;">
    <?php if (!$peserta): ?>
        <div class="empty-state">Belum ada data peserta.</div>
    <?php else: ?>
        <table class="table">
            <thead>
            <tr>
                <th>Nama</th>
                <th>NIK</th>
                <th>Tier</th>
                <th>Kursi</th>
                <th>Kode Tiket</th>
                <th>Check-in</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($peserta as $p): ?>
            <tr>
                <td><?= e($p['nama_peserta']) ?></td>
                <td><code><?= e($p['nik_peserta']) ?></code></td>
                <td><?= e($p['nama_tier'] ?? '-') ?></td>
                <td><?= $p['nomor_kursi'] ? e($p['nomor_kursi']) : 'Free Standing' ?></td>
                <td><?= !empty($p['kode_tiket']) ? e($p['kode_tiket']) : '-' ?></td>
                <td>
                    <?php if (!empty($p['kode_tiket'])): ?>
                    <?= e($p['status_checkin'] ?? 'Belum Hadir') ?>
                    <?php else: ?>
                    <span style="color:var(--color-text-muted);">-</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    </div>
</div>

<?php if ($trx['status_bayar'] === 'Paid'): ?>
<div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap;">
    <a href="etiket.php?kode=<?= e($kode) ?>" class="btn btn-primary">Unduh E-Ticket</a>
    <a href="dashboard_peserta.php" class="btn btn-outline">Kembali ke Dashboard</a>
</div>
<?php endif; ?>

<?php require __DIR__ . '/src/Views/layouts/footer.php'; ?>
