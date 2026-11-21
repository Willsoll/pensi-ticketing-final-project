<?php
/**
 * dashboard_peserta.php — Dashboard peserta (v4)
 * Hanya role customer. Statistik + daftar transaksi.
 */
require_once __DIR__ . '/src/Config/database.php';
require_once __DIR__ . '/src/Middleware/middleware.php';
require_role(['customer']);
release_expired_transactions($pdo);

$user    = current_user();
$id_user = (int)$user['id_user'];

$stmt = $pdo->prepare('SELECT COUNT(*) FROM transaksi WHERE id_user = ?');
$stmt->execute([$id_user]);
$totalPesanan = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM e_tickets et
                       JOIN transaksi t ON t.id_transaksi = et.id_transaksi
                       WHERE t.id_user = ?");
$stmt->execute([$id_user]);
$tiketLunas = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM transaksi
                       WHERE id_user = ? AND status_bayar = 'Pending'");
$stmt->execute([$id_user]);
$menungguBayar = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_bayar),0) FROM transaksi
                       WHERE id_user = ? AND status_bayar = 'Paid'");
$stmt->execute([$id_user]);
$totalBelanja = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT t.*, e.judul_event FROM transaksi t
                       JOIN events e ON e.id_event = t.id_event
                       WHERE t.id_user = ? ORDER BY t.created_at DESC");
$stmt->execute([$id_user]);
$transaksi = $stmt->fetchAll();

$pageTitle   = 'Dashboard Peserta';
$pageSubtitle = 'Ringkasan pesanan dan e-ticket Anda';
$activeNav   = 'dashboard_peserta.php';
require __DIR__ . '/src/Views/layouts/header.php';
?>
<div class="breadcrumb">
    <a href="index.php">Katalog</a> &rsaquo; Dashboard Peserta
</div>

<div class="stat-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:24px;">
    <div class="stat-box card">
        <div style="color:var(--color-text-muted);font-size:0.875rem;">Total Pesanan</div>
        <div style="font-size:1.5rem;font-weight:700;"><?= $totalPesanan ?></div>
    </div>
    <div class="stat-box card">
        <div style="color:var(--color-text-muted);font-size:0.875rem;">Tiket Lunas</div>
        <div style="font-size:1.5rem;font-weight:700;color:var(--color-success);"><?= $tiketLunas ?></div>
    </div>
    <div class="stat-box card">
        <div style="color:var(--color-text-muted);font-size:0.875rem;">Menunggu Bayar</div>
        <div style="font-size:1.5rem;font-weight:700;color:var(--color-warning);"><?= $menungguBayar ?></div>
    </div>
    <div class="stat-box card">
        <div style="color:var(--color-text-muted);font-size:0.875rem;">Total Belanja</div>
        <div style="font-size:1.5rem;font-weight:700;"><?= format_rupiah($totalBelanja) ?></div>
    </div>
</div>

<div class="card card-elevated">
    <div class="card-header"><h2 style="margin:0;">Pesanan Saya</h2></div>
    <div style="padding:16px;">
    <?php if (!$transaksi): ?>
        <div class="empty-state">Belum ada pesanan.</div>
    <?php else: ?>
        <table class="table">
            <thead>
            <tr>
                <th>Kode</th>
                <th>Event</th>
                <th>Tanggal</th>
                <th>Total</th>
                <th>Status</th>
                <th style="width:140px;">Aksi</th>
                <th style="width:110px;">Waktu</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($transaksi as $t): ?>
            <tr>
                <td><code><?= e($t['kode_booking']) ?></code></td>
                <td><?= e($t['judul_event']) ?></td>
                <td><?= format_tanggal($t['created_at']) ?></td>
                <td><?= format_rupiah((int)$t['total_bayar']) ?></td>
                <td><span class="badge <?= status_class($t['status_bayar']) ?>"><?= label_status($t['status_bayar']) ?></span></td>
                <td>
                    <a href="detail_pesanan.php?kode=<?= e($t['kode_booking']) ?>" class="btn btn-outline btn-sm">Detail</a>
                    <?php if ($t['status_bayar'] === 'Paid'): ?>
                    <a href="etiket.php?kode=<?= e($t['kode_booking']) ?>" class="btn btn-primary btn-sm">E-Ticket</a>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($t['status_bayar'] === 'Pending'): ?>
                    <span class="countdown" data-deadline="<?= e(str_replace(' ', 'T', $t['batas_bayar_at']) . '+07:00') ?>">-</span>
                    <?php else: ?>
                    <span style="color:var(--color-text-muted);font-size:0.875rem;">-</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    </div>
</div>

<script>
(function(){
    document.querySelectorAll('[data-deadline]').forEach(function(el){
        const deadline = new Date(el.getAttribute('data-deadline')).getTime();
        function tick(){
            const diff = deadline - new Date().getTime();
            if (diff <= 0){
                el.textContent = 'Waktu habis';
                el.style.color = 'var(--color-error)';
                el.style.fontWeight = '700';
                return;
            }
            const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const s = Math.floor((diff % (1000 * 60)) / 1000);
            el.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
        }
        tick();
        setInterval(tick, 1000);
    });
})();
</script>

<?php require __DIR__ . '/src/Views/layouts/footer.php'; ?>
