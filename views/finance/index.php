<?php
// views/finance/index.php
$e = $e ?? function($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); };
$attr = $attr ?? function($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); };
$rupiah = $rupiah ?? function($v) { return 'Rp ' . number_format($v, 0, ',', '.'); };
$date = $date ?? function($v) { return date('d-m-Y', strtotime($v)); };
$datetime = $datetime ?? function($v) { return date('d-m-Y H:i', strtotime($v)); };
?>
<div class="breadcrumb">
    <a href="/">Beranda</a>
    <span class="breadcrumb-separator">/</span>
    <span class="breadcrumb-current">Verifikasi Pembayaran</span>
</div>

<div class="page-header">
    <h1 class="page-title">Verifikasi Pembayaran</h1>
    <p class="page-subtitle">Tinjau dan verifikasi bukti pembayaran dari peserta</p>
</div>

<?php if ($flash['success'] ?? false): ?>
    <div class="alert alert-success"><?= $e($flash['success']) ?></div>
<?php endif; ?>
<?php if ($flash['error'] ?? false): ?>
    <div class="alert alert-error"><?= $e($flash['error']) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php if (empty($transactions)): ?>
            <div style="text-align: center; padding: 3rem; color: var(--color-slate-500);">
                <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin: 0 auto 1rem; color: var(--color-slate-300);">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2H7a2 2 0 01-2-2v-2"/>
                </svg>
                <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 0.5rem;">Tidak ada transaksi menunggu verifikasi</h3>
                <p>Semua pembayaran telah diverifikasi.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Kode Booking</th>
                            <th>Pembeli</th>
                            <th>Event</th>
                            <th>Jenis Tiket</th>
                            <th>Jumlah</th>
                            <th style="text-align: right;">Total Bayar</th>
                            <th>Tanggal</th>
                            <th>Bukti</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $tx): ?>
                        <tr>
                            <td><code><?= $e($tx['kode_booking']) ?></code></td>
                            <td>
                                <strong><?= $e($tx['user_nama']) ?></strong><br>
                                <small style="color: var(--color-slate-500);"><?= $e($tx['email']) ?></small>
                            </td>
                            <td><?= $e($tx['judul_event']) ?></td>
                            <td><?= $e($tx['nama_tier']) ?></td>
                            <td style="text-align: center;"><?= (int)$tx['jumlah'] ?></td>
                            <td style="text-align: right;"><strong><?= $rupiah($tx['total_bayar']) ?></strong></td>
                            <td><?= $datetime($tx['created_at']) ?></td>
                            <td style="text-align: center;">
                                <a href="<?= $e($tx['bukti_bayar']) ?>" target="_blank" class="btn btn-secondary btn-sm">Lihat Bukti</a>
                            </td>
                            <td>
                                <form method="POST" action="/finance/verify/<?= $e($tx['id_transaksi']) ?>" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Yakin setujui pembayaran ini?')">Setujui</button>
                                </form>
                                <form method="POST" action="/finance/verify/<?= $e($tx['id_transaksi']) ?>" style="display: inline; margin-top: 0.5rem;">
                                    <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <textarea name="catatan_penolakan" placeholder="Alasan penolakan (min. 5 karakter)" required style="width: 100%; margin-bottom: 0.5rem; padding: 0.5rem; border: 1px solid var(--color-slate-300); border-radius: 0.375rem; font-size: 0.75rem;"></textarea>
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Yakin tolak pembayaran ini? Stok akan dikembalikan.')">Tolak</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>