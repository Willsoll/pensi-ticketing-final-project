<?php
// views/booking/my_bookings.php
$e = $e ?? function($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); };
$attr = $attr ?? function($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); };
$rupiah = $rupiah ?? function($v) { return 'Rp ' . number_format($v, 0, ',', '.'); };
$date = $date ?? function($v) { return date('d-m-Y', strtotime($v)); };
$datetime = $datetime ?? function($v) { return date('d-m-Y H:i', strtotime($v)); };
?>
<div class="breadcrumb">
    <a href="/">Beranda</a>
    <span class="breadcrumb-separator">/</span>
    <span class="breadcrumb-current">Pemesanan Saya</span>
</div>

<div class="page-header">
    <h1 class="page-title">Pemesanan Saya</h1>
    <p class="page-subtitle">Kelola dan lacak status pemesanan tiket Anda</p>
</div>

<?php if (empty($bookings)): ?>
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 4rem;">
            <svg width="80" height="80" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--color-slate-300); margin-bottom: 1rem;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h3 style="font-size: 1.25rem; font-weight: 600; color: var(--color-slate-700); margin-bottom: 0.5rem;">Belum Ada Pemesanan</h3>
            <p style="color: var(--color-slate-500); margin-bottom: 1.5rem;">Anda belum memesan tiket apapun.</p>
            <a href="/" class="btn btn-primary">Pesan Tiket Sekarang</a>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <table>
                <thead>
                    <tr>
                        <th style="width: 15%;">Kode Booking</th>
                        <th style="width: 18%;">Tanggal Pesan</th>
                        <th style="width: 18%;">Event & Tier</th>
                        <th style="width: 10%;">Jumlah</th>
                        <th style="width: 15%; text-align: right;">Total Bayar</th>
                        <th style="width: 12%;">Status</th>
                        <th style="width: 12%;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): 
                        $statusClass = match($booking['status']) {
                            'Active' => 'badge-yellow',
                            'Waiting Verification' => 'badge-purple',
                            'Converted' => 'badge-emerald',
                            'Expired' => 'badge-red',
                            default => 'badge-slate',
                        };
                    ?>
                    <tr>
                        <td><code style="font-size: 0.875rem;"><?= $e($booking['kode_booking']) ?></code></td>
                        <td><?= $datetime($booking['created_at']) ?></td>
                        <td>
                            <strong><?= $e($booking['judul_event'] ?? $booking['event_id']) ?></strong>
                            <br><small style="color: var(--color-slate-500);"><?= $e($booking['nama_tier'] ?? '') ?></small>
                        </td>
                        <td style="text-align: center;"><?= (int)$booking['qty'] ?></td>
                        <td style="text-align: right;"><strong><?= $rupiah($booking['total_bayar']) ?></strong></td>
                        <td style="text-align: center;">
                            <span class="badge <?= $statusClass ?>"><?= $e($booking['status']) ?></span>
                        </td>
                        <td style="text-align: center; white-space: nowrap;">
                            <?php if ($booking['status'] === 'Active'): ?>
                                <a href="/checkout/<?= $e($booking['id_reservasi']) ?>" class="btn btn-orange btn-sm">Bayar Sekarang</a>
                            <?php elseif ($booking['status'] === 'Waiting Verification'): ?>
                                <span class="badge badge-purple">Menunggu Verifikasi</span>
                            <?php elseif ($booking['status'] === 'Converted'): ?>
                                <a href="/ticket/<?= $e($booking['id_reservasi']) ?>/print" class="btn btn-success btn-sm" target="_blank">Cetak E-Ticket</a>
                            <?php elseif ($booking['status'] === 'Expired'): ?>
                                <span class="badge badge-red">Kadaluarsa</span>
                            <?php elseif ($booking['status'] === 'Rejected'): ?>
                                <a href="/checkout/<?= $e($booking['id_reservasi']) ?>" class="btn btn-secondary btn-sm">Upload Ulang</a>
                            <?php endif; ?>
                            <a href="/booking/detail/<?= $e($booking['id_reservasi']) ?>" class="btn btn-secondary btn-sm" style="margin-left: 0.5rem;">Detail</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>