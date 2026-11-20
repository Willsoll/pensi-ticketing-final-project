<?php
// views/catalog/index.php
$e = $e ?? function($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); };
$attr = $attr ?? function($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); };
$rupiah = $rupiah ?? function($v) { return 'Rp ' . number_format($v, 0, ',', '.'); };
$date = $date ?? function($v) { return date('d-m-Y', strtotime($v)); };
?>
<div class="breadcrumb">
    <a href="/">Beranda</a>
    <span class="breadcrumb-separator">/</span>
    <span class="breadcrumb-current">Katalog Acara</span>
</div>

<div class="page-header">
    <h1 class="page-title">Katalog Acara Pensi</h1>
    <p class="page-subtitle">Temukan dan pesan tiket acara menarik</p>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom: var(--space-8);">
    <div class="card-body">
        <form method="GET" action="/search" class="grid grid-4" style="align-items: end; gap: var(--space-4);">
            <div class="form-group" style="margin: 0;">
                <label for="search">Cari</label>
                <input type="text" name="search" id="search" class="input" placeholder="Nama event, penyelenggara..." value="<?= $e($_GET['search'] ?? '') ?>">
            </div>
            <div class="form-group" style="margin: 0;">
                <label for="category">Kategori</label>
                <select name="category" id="category" class="input">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $e($cat['id_kat_event']) ?>" <?= (($_GET['category'] ?? '') == $cat['id_kat_event']) ? 'selected' : '' ?>>
                            <?= $e($cat['nama_kategori']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin: 0;">
                <label for="city">Kota</label>
                <input type="text" name="city" id="city" class="input" placeholder="Kota venue" value="<?= $e($_GET['city'] ?? '') ?>">
            </div>
            <div class="form-group" style="margin: 0;">
                <label for="date_from">Tanggal Mulai</label>
                <input type="date" name="date_from" id="date_from" class="input" value="<?= $e($_GET['date_from'] ?? '') ?>">
            </div>
            <div class="form-group" style="margin: 0;">
                <label for="date_to">Tanggal Selesai</label>
                <input type="date" name="date_to" id="date_to" class="input" value="<?= $e($_GET['date_to'] ?? '') ?>">
            </div>
            <div class="form-group" style="margin: 0;">
                <button type="submit" class="btn btn-primary" style="width: 100%;">Cari</button>
            </div>
            <div class="form-group" style="margin: 0;">
                <a href="/" class="btn btn-secondary" style="width: 100%; text-align: center;">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Events Grid -->
<?php if (empty($events)): ?>
    <div class="card">
        <div class="card-body" style="text-align: center; padding: var(--space-16); color: var(--color-slate-500);">
            <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin: 0 auto var(--space-4); color: var(--color-slate-300);" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: var(--space-2);">Tidak ada acara tersedia</h3>
            <p>Belum ada event yang dijadwalkan saat ini.</p>
        </div>
    </div>
<?php else: ?>
    <div class="grid grid-3">
        <?php foreach ($events as $event): ?>
            <?php
                $tiers = $ticketTypes[$event['id_event']] ?? [];
                $minPrice = !empty($tiers) ? min(array_column($tiers, 'harga')) : 0;
                $hasStock = !empty($tiers) && array_sum(array_column($tiers, 'kuota_tersedia')) > 0;
                $statusClass = $event['status_event'] === 'Sold Out' ? 'badge-danger' : 'badge-success';
                $statusText = $event['status_event'] === 'Sold Out' ? 'Habis' : 'Tersedia';
            ?>
            <article class="card" style="display: flex; flex-direction: column; height: 100%; transition: transform var(--transition-base), box-shadow var(--transition-base);" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='var(--shadow-xl)'" onmouseout="this.style.transform=''; this.style.boxShadow=''">
                <!-- Event Image/Placeholder -->
                <div style="position: relative; height: 200px; border-radius: var(--radius-lg) var(--radius-lg) 0 0; overflow: hidden; background: linear-gradient(135deg, var(--color-indigo-dark), var(--color-purple-electric));">
                    <?php if (!empty($event['banner'])): ?>
                        <img src="<?= $e($event['banner']) ?>" alt="<?= $e($event['judul_event']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--color-indigo-dark), var(--color-purple-electric)); color: var(--color-white); font-weight: 600; font-size: 1.125rem; text-align: center; padding: var(--space-4);">
                            <?= $e($event['judul_event']) ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($event['status_event'] === 'Sold Out'): ?>
                        <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center; color: var(--color-white); font-weight: 700; font-size: 1.5rem;">HABIS</div>
                    <?php endif; ?>
                    <span class="badge badge-success" style="position: absolute; top: var(--space-3); left: var(--space-3);"><?= $e($event['nama_kategori'] ?? '') ?></span>
                    <span class="badge <?= $event['status_event'] === 'Sold Out' ? 'badge-danger' : 'badge-success' ?>" style="position: absolute; top: var(--space-3); right: var(--space-3);"><?= $e($statusText) ?></span>
                </div>

                <div class="card-body" style="flex: 1; display: flex; flex-direction: column;">
                    <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-3);">
                        <span class="badge badge-primary"><?= $e($event['tipe_venue']) ?></span>
                    </div>

                    <h3 style="font-size: 1.125rem; font-weight: 700; color: var(--color-indigo-darker); margin-bottom: var(--space-3); line-height: 1.3;"><?= $e($event['judul_event']) ?></h3>

                    <div style="color: var(--color-slate-500); font-size: 0.875rem; margin-bottom: var(--space-4); display: flex; flex-wrap: wrap; gap: var(--space-3);">
                        <span>📍 <?= $e($event['lokasi']) ?></span>
                        <span>📅 <?= $date($event['tgl_mulai']) ?> - <?= $date($event['tgl_selesai']) ?></span>
                        <span>⏰ <?= date('H:i', strtotime($event['tgl_mulai'])) ?> - <?= date('H:i', strtotime($event['tgl_selesai'])) ?></span>
                        <span>🏢 <?= $e($event['penyelenggara']) ?></span>
                    </div>

                    <div style="margin-top: auto; padding-top: var(--space-4); border-top: 1px solid var(--color-slate-200);">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-3);">
                            <span style="font-size: 1.5rem; font-weight: 700; color: var(--color-purple-electric);">
                                <?= $minPrice > 0 ? 'Mulai ' . $rupiah($minPrice) : 'Gratis' ?>
                            </span>
                            <span class="badge <?= $hasStock ? 'badge-success' : 'badge-danger' ?>"><?= $hasStock ? 'Tersedia' : 'Habis' ?></span>
                        </div>
                        <a href="/event/<?= $e($event['id_event']) ?>" class="btn btn-primary" style="width: 100%;">Lihat Detail & Pesan</a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>