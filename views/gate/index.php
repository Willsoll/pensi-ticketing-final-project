<?php
// views/gate/index.php
$e = $e ?? function($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); };
$attr = $attr ?? function($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); };
$rupiah = $rupiah ?? function($v) { return 'Rp ' . number_format($v, 0, ',', '.'); };
$date = $date ?? function($v) { return date('d-m-Y', strtotime($v)); };
$datetime = $datetime ?? function($v) { return date('d-m-Y H:i', strtotime($v)); };
?>
<div class="breadcrumb">
    <a href="/">Beranda</a>
    <span class="breadcrumb-separator">/</span>
    <span class="breadcrumb-current">Gate Check-in</span>
</div>

<div class="page-header">
    <h1 class="page-title">Gate Check-in</h1>
    <p class="page-subtitle">Scan atau masukkan kode tiket untuk check-in peserta</p>
</div>

<?php if ($flash['success'] ?? false): ?>
    <div class="alert alert-success"><?= $e($flash['success']) ?></div>
<?php endif; ?>
<?php if ($flash['error'] ?? false): ?>
    <div class="alert alert-error"><?= $e($flash['error']) ?></div>
<?php endif; ?>

<!-- Check-in Form -->
<div class="card" style="max-width: 600px; margin-bottom: 2rem;">
    <div class="card-header">
        <h2 style="font-size: 1.125rem; font-weight: 700;">Scan / Masukkan Kode Tiket</h2>
    </div>
    <div class="card-body">
        <form method="POST" action="/gate/checkin">
            <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
            
            <div class="form-group">
                <label for="kode_tiket">Kode Tiket *</label>
                <input type="text" id="kode_tiket" name="kode_tiket" class="input" required placeholder="Contoh: PEN-241216-A1B2C3D4-1" style="text-transform: uppercase;" autocomplete="off">
            </div>
            
            <div class="form-group">
                <label for="nik_konfirmasi">NIK KTP Peserta (16 Digit) *</label>
                <input type="text" id="nik_konfirmasi" name="nik_konfirmasi" class="input" required pattern="[0-9]{16}" maxlength="16" placeholder="Contoh: 3273011005990001" autocomplete="off">
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">Check-in</button>
        </form>
    </div>
</div>

<!-- Tiket Siap Check-in -->
<div class="card">
    <div class="card-header">
        <h2 style="font-size: 1.125rem; font-weight: 700;">Tiket Siap Check-in (Status: Lunas)</h2>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($tickets)): ?>
            <div style="text-align: center; padding: 3rem; color: var(--color-slate-500);">
                <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin: 0 auto 1rem; color: var(--color-slate-300);">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 5v2m0 4v2m0 4v2M9 7h1m0 4h1m-10 4v4c0 1.1.9 2 2 2h2v-4h-2a2 2 0 01-2-2v-6c0-1.1.9-2 2-2h2"/>
                </svg>
                <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 0.5rem;">Tidak ada tiket untuk check-in</h3>
                <p>Belum ada pembayaran yang diverifikasi.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Kode Tiket</th>
                            <th>Peserta</th>
                            <th>NIK</th>
                            <th>Event</th>
                            <th>Jenis Tiket</th>
                            <th>Kursi</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                        <tr>
                            <td><code><?= $e($ticket['kode_tiket']) ?></code></td>
                            <td><strong><?= $e($ticket['nama_peserta']) ?></strong></td>
                            <td><code><?= $e($ticket['nik_peserta']) ?></code></td>
                            <td><?= $e($ticket['judul_event']) ?></td>
                            <td><?= $e($ticket['nama_tier']) ?></td>
                            <td style="text-align: center;">
                                <?= $ticket['nomor_kursi'] ? '<span class="badge badge-purple">' . $e($ticket['nomor_kursi']) . '</span>' : '<span class="badge badge-slate">Free Standing</span>' ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-emerald">Belum Hadir</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>