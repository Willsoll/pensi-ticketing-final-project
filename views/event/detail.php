<?php
// views/event/detail.php
$e = $e ?? function($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); };
$attr = $attr ?? function($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); };
$rupiah = $rupiah ?? function($v) { return 'Rp ' . number_format($v, 0, ',', '.'); };
$date = $date ?? function($v) { return date('d-m-Y', strtotime($v)); };
$datetime = $datetime ?? function($v) { return date('d-m-Y H:i', strtotime($v)); };
?>
<div class="breadcrumb">
    <a href="/">Beranda</a>
    <span class="breadcrumb-separator">/</span>
    <span class="breadcrumb-current"><?= $e($event['judul_event']) ?></span>
</div>

<div class="grid grid-2" style="gap: var(--space-8); margin-bottom: var(--space-8);">
    <!-- Event Image & Info -->
    <div class="card" style="height: fit-content;">
        <div style="position: relative; height: 320px; border-radius: var(--radius-lg) var(--radius-lg) 0 0; overflow: hidden;">
            <?php if ($event['banner']): ?>
                <img src="<?= $e($event['banner']) ?>" alt="<?= $e($event['judul_event']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
            <?php else: ?>
                <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--color-indigo-dark), var(--color-purple-electric)); color: var(--color-white); font-weight: 600; font-size: 1.5rem; text-align: center; padding: var(--space-8);">
                    <?= $e($event['judul_event']) ?>
                </div>
            <?php endif; ?>
            <?php if ($event['status_event'] === 'Sold Out'): ?>
                <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center; color: var(--color-white); font-weight: 700; font-size: 1.5rem;">HABIS</div>
            <?php endif; ?>
        </div>

        <div class="card-body">
            <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-4);">
                <span class="badge badge-primary"><?= $e($event['nama_kategori'] ?? '') ?></span>
                <span class="badge badge-primary"><?= $e($event['tipe_venue']) ?></span>
            </div>

            <h1 class="page-title"><?= $e($event['judul_event']) ?></h1>

            <div style="color: var(--color-slate-500); margin-bottom: var(--space-6); display: flex; flex-wrap: wrap; gap: var(--space-4);">
                <span>📍 <?= $e($event['lokasi']) ?></span>
                <span>📅 <?= $date($event['tgl_mulai']) ?> - <?= $date($event['tgl_selesai']) ?></span>
                <span>⏰ <?= $datetime($event['tgl_mulai']) ?> - <?= $datetime($event['tgl_selesai']) ?></span>
                <span>🏢 <?= $e($event['penyelenggara']) ?></span>
            </div>

            <div style="padding-top: var(--space-6); border-top: 1px solid var(--color-slate-200);">
                <h3 style="font-weight: 600; margin-bottom: var(--space-3);">Deskripsi</h3>
                <p style="color: var(--color-slate-600); white-space: pre-wrap;"><?= $e($event['deskripsi'] ?? 'Tidak ada deskripsi.') ?></p>
            </div>
        </div>
    </div>

    <!-- Ticket Types -->
    <div class="card" style="height: fit-content;">
        <div class="card-header">
            <h2 style="font-size: 1.25rem; font-weight: 700;">Pilih Tiket</h2>
        </div>
        <div class="card-body">
            <?php if (empty($tiers)): ?>
                <p style="text-align: center; color: var(--color-slate-500); padding: var(--space-8);">Belum ada tier tiket untuk event ini.</p>
            <?php else: ?>
                <?php foreach ($tiers as $tier): 
                    $available = (int)$tier['kuota_tersedia'];
                    $isSoldOut = $available <= 0;
                    $canBuy = !$isSoldOut && $selectedTier && $selectedTier['id_tier'] == $tier['id_tier'];
                ?>
                <div class="card" style="border: 2px solid <?= $selectedTier && $selectedTier['id_tier'] == $tier['id_tier'] ? 'var(--color-purple-electric)' : 'var(--color-slate-200)' ?>; margin-bottom: var(--space-4); transition: border-color var(--color-base), box-shadow var(--transition-base);" onmouseover="this.style.boxShadow='var(--shadow-md)'" onmouseout="this.style.boxShadow=''">
                    <div class="card-body" style="padding: var(--space-5);">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-4);">
                            <div>
                                <h3 style="font-weight: 700; font-size: 1.125rem; color: var(--color-indigo-darker);"><?= $e($tier['nama_tier']) ?></h3>
                                <span class="badge badge-primary"><?= $e($event['tipe_venue']) ?></span>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 1.5rem; font-weight: 700; color: var(--color-purple-electric);"><?= $rupiah($tier['harga']) ?></div>
                                <div style="font-size: 0.875rem; color: var(--color-slate-500);">per tiket</div>
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-4); padding-top: var(--space-4); border-top: 1px solid var(--color-slate-200);">
                            <div>
                                <div style="font-size: 0.875rem; color: var(--color-slate-500);">Stok Tersedia</div>
                                <div style="font-weight: 700; font-size: 1.25rem; color: <?= $available > 0 ? 'var(--color-emerald)' : 'var(--color-red)' ?>;"><?= (int)$tier['kuota_tersedia'] ?> / <?= (int)$tier['kuota_total'] ?></div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 0.875rem; color: var(--color-slate-500);">Dikunci</div>
                                <div style="font-weight: 700; color: var(--color-amber);"><?= (int)$tier['kuota_locked'] ?></div>
                            </div>
                        </div>

                        <?php if ($selectedTier && $selectedTier['id_tier'] == $tier['id_tier']): ?>
                            <a href="/event/<?= $e($event['id_event']) ?>" class="btn btn-secondary" style="width: 100%;">Ubah Pilihan</a>
                        <?php elseif ($available > 0): ?>
                            <form method="POST" action="/event/select-tier" style="width: 100%;">
                                <input type="hidden" name="event_id" value="<?= $e($event['id_event']) ?>">
                                <input type="hidden" name="tier_id" value="<?= $e($tier['id_tier']) ?>">
                                <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
                                <button type="submit" name="action" value="select_tier" class="btn btn-primary" style="width: 100%;">Pilih Tier Ini</button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-secondary" style="width: 100%;" disabled>Habis</button>
                        <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($rundown)): ?>
<div class="card" style="margin-top: var(--space-8);">
    <div class="card-header">
        <h2 style="font-size: 1.25rem; font-weight: 700;">Rundown Acara</h2>
    </div>
    <div class="card-body" style="padding: 0;">
        <table>
            <thead>
                <tr>
                    <th style="width: 15%;">Jam Mulai</th>
                    <th style="width: 15%;">Jam Selesai</th>
                    <th style="width: 50%;">Acara</th>
                    <th>Lokasi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rundown as $item): ?>
                <tr>
                    <td style="font-weight: 600;"><?= $e($item['jam_mulai']) ?></td>
                    <td><?= $e($item['jam_selesai'] ?? '-') ?></td>
                    <td><strong><?= $e($item['deskripsi']) ?></strong></td>
                    <td><?= $e($item['lokasi'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>