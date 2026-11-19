<?php
/**
 * index.php - Halaman Katalog Acara Pensi (Public)
 * Menampilkan informasi acara, rundown, dan jenis tiket dengan filter
 * Redesigned with Pensi Design System v1.0
 */
require_once __DIR__ . '/../src/Config/database.php';
require_once __DIR__ . '/../src/Middleware/middleware.php';

release_expired_transactions($pdo);

// Ambil data acara (hanya 1 acara utama untuk Pensi, tapi bisa diperluas)
$stmtEvent = $pdo->query("SELECT * FROM rundown_acara ORDER BY jam_mulai ASC");
$rundown_list = $stmtEvent->fetchAll();

// Ambil jenis tiket dengan stok
$stmtJenis = $pdo->query("SELECT * FROM jenis_tiket ORDER BY harga ASC");
$jenis_list = $stmtJenis->fetchAll();

// Ambil kode promo aktif
$stmtPromo = $pdo->query("SELECT kode_voucher, potongan_nominal FROM kode_promo WHERE kuota_voucher > 0 AND berlaku_sampai >= CURDATE()");
$promo_list = $stmtPromo->fetchAll();

$page_title = 'Katalog Acara Pensi School - Pentas Seni Sekolah';
require_once __DIR__ . '/../src/Views/layouts/header.php';
?>

<div class="breadcrumb">
    <a href="index.php">Beranda</a> > Katalog Acara
</div>

<!-- HERO / INFO ACARA UTAMA -->
<div class="card card-elevated reveal" style="margin-bottom: var(--space-8);" data-parallax="0.06">
    <div class="card-header">
        <h2 class="card-title" style="color: var(--color-primary); margin: 0;">PENSI SCHOOL 2026 - Pentas Seni Tahunan</h2>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-1 grid-cols-3" style="gap: var(--space-5);">
            <div class="card card-bordered reveal" data-tilt style="background: var(--color-info-bg); border-color: var(--color-info-border);">
                <div class="card-body" style="padding: var(--space-5);">
                    <div class="flex items-center gap-3 mb-3">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-info)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        <strong style="color: var(--color-info);">Tanggal Acara</strong>
                    </div>
                    <div style="font-size: var(--text-xl); font-weight: var(--font-semibold); color: var(--color-text-primary);">Sabtu, 12 Desember 2026</div>
                    <div style="margin-top: var(--space-1); color: var(--color-text-muted);">17:00 - 22:00 WIB</div>
                </div>
            </div>
            <div class="card card-bordered reveal" data-tilt style="background: var(--color-success-bg); border-color: var(--color-success-border);">
                <div class="card-body" style="padding: var(--space-5);">
                    <div class="flex items-center gap-3 mb-3">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-success)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                            <circle cx="12" cy="10" r="3"/>
                        </svg>
                        <strong style="color: var(--color-success);">Lokasi</strong>
                    </div>
                    <div style="font-weight: var(--font-semibold); color: var(--color-text-primary);">Aula Serbaguna Sekolah Terpadu Pahoa</div>
                    <div style="margin-top: var(--space-1); color: var(--color-text-muted);">Jl. Raya Serpong, Tangerang Selatan</div>
                </div>
            </div>
            <div class="card card-bordered reveal" data-tilt style="background: var(--color-warning-bg); border-color: var(--color-warning-border);">
                <div class="card-body" style="padding: var(--space-5);">
                    <div class="flex items-center gap-3 mb-3">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-warning)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                        <strong style="color: var(--color-warning);">Tipe Tiket Tersedia</strong>
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: var(--space-2);">
                        <?php foreach ($jenis_list as $jt): ?>
                            <span class="badge <?= $jt['target_pembeli'] === 'Internal' ? 'badge-primary' : ($jt['target_pembeli'] === 'Eksternal' ? 'badge-accent' : 'badge-success') ?>">
                                <?= e($jt['nama_jenis']) ?> - <?= format_rupiah((int)$jt['harga']) ?> (Sisa: <?= (int)$jt['stok_tersedia'] ?>)
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- RUNDOWN ACARA -->
<div class="card card-elevated reveal" style="margin-bottom: var(--space-8);">
    <div class="card-header">
        <h3 class="card-title">Rundown Acara & Penampilan</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 15%;">Jam</th>
                        <th style="width: 30%;">Penampil</th>
                        <th style="width: 20%;">Kategori</th>
                        <th>Lokasi Panggung</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rundown_list as $rd): ?>
                        <tr>
                            <td><strong><?= e(date('H:i', strtotime($rd['jam_mulai']))) ?> - <?= e(date('H:i', strtotime($rd['jam_selesai']))) ?></strong></td>
                            <td><strong><?= e($rd['nama_penampil']) ?></strong></td>
                            <td>
                                <?php 
                                $kategori_class = strtolower(str_replace(' ', '-', $rd['kategori_penampil']));
                                $badge_map = [
                                    'guest-star' => 'badge-accent',
                                    'ekskul' => 'badge-success',
                                    'band-siswa' => 'badge-primary'
                                ];
                                $badge = $badge_map[$kategori_class] ?? 'badge-neutral';
                                ?>
                                <span class="badge <?= $badge ?>"><?= e($rd['kategori_penampil']) ?></span>
                            </td>
                            <td><?= e($rd['lokasi_panggung']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- JENIS TIKET & HARGA -->
<div class="card card-elevated reveal" style="margin-bottom: var(--space-8);">
    <div class="card-header">
        <h3 class="card-title">Jenis Tiket, Harga & Stok</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nama Jenis</th>
                        <th>Target Pembeli</th>
                        <th>Tipe</th>
                        <th style="text-align: right;">Harga</th>
                        <th style="text-align: center;">Total Stok</th>
                        <th style="text-align: center;">Sisa Tersedia</th>
                        <th style="text-align: center;">Tertahan</th>
                        <th style="text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jenis_list as $jt): 
                        $is_internal_only = $jt['target_pembeli'] === 'Internal';
                        $is_external_only = $jt['target_pembeli'] === 'Eksternal';
                        $badge_class = $is_internal_only ? 'badge-primary' : ($is_external_only ? 'badge-accent' : 'badge-success');
                        $buy_msg = $is_internal_only ? 'Hanya Siswa Internal (NIS)' : ($is_external_only ? 'Hanya Tamu Eksternal (NIK)' : 'Semua (Internal & Eksternal)');
                    ?>
                        <tr>
                            <td><strong><?= e($jt['nama_jenis']) ?></strong></td>
                            <td>
                                <span class="badge <?= $badge_class ?>">
                                    <?= e($jt['target_pembeli']) ?>
                                </span>
                            </td>
                            <td><?= e($jt['tipe_tiket']) ?></td>
                            <td style="text-align: right;"><strong><?= format_rupiah((int)$jt['harga']) ?></strong></td>
                            <td style="text-align: center;"><?= (int)$jt['stok_tiket'] ?></td>
                            <td style="text-align: center;"><strong><?= (int)$jt['stok_tersedia'] ?></strong></td>
                            <td style="text-align: center;"><?= (int)$jt['stok_tertahan'] ?></td>
                            <td style="text-align: center;">
                                <?php if ((int)$jt['stok_tersedia'] > 0): ?>
                                    <a href="detail_event.php?id_jenis=<?= (int)$jt['id_jenis'] ?>" class="btn btn-primary btn-sm">
                                        Pilih & Pesan
                                    </a>
                                <?php else: ?>
                                    <span class="badge badge-error">Sold Out</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- PROMO AKTIF -->
<?php if (!empty($promo_list)): ?>
<div class="card card-elevated" style="margin-bottom: var(--space-8);">
    <div class="card-header">
        <h3 class="card-title">Kode Promo Aktif</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kode Voucher</th>
                        <th style="text-align: right;">Potongan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($promo_list as $pr): ?>
                        <tr>
                            <td><code><strong><?= e($pr['kode_voucher']) ?></strong></code></td>
                            <td style="text-align: right;"><strong style="color: var(--color-success);">- <?= format_rupiah((int)$pr['potongan_nominal']) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ATURAN PENTING (MANDAT CEO) -->
<div class="card" style="background: var(--color-warning-bg); border-color: var(--color-warning-border);">
    <div class="card-body">
        <h4 style="color: var(--color-warning); margin-bottom: var(--space-4); display: flex; align-items: center; gap: var(--space-2);">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            Aturan Penting (Mandat CEO)
        </h4>
        <ul style="color: var(--color-warning); margin-left: var(--space-6); line-height: 2; font-size: var(--text-sm);">
            <li><strong>Segmentasi Pembeli:</strong> Siswa Internal (NIS) hanya bisa beli tiket target 'Internal' atau 'Semua'. Tamu Eksternal (NIK) hanya bisa beli tiket target 'Eksternal' atau 'Semua'.</li>
            <li><strong>Identity Binding:</strong> Setiap tiket wajib diisi Nama Lengkap & NIK/NIS pemegang tiket yang berbeda (1 tiket = 1 identitas unik).</li>
            <li><strong>Batas Waktu Bayar:</strong> 15 menit dari pembuatan pesanan. Otomatis kadaluarsa & stok dikembalikan.</li>
            <li><strong>Anti-Passback Gate:</strong> 1 kode tiket = 1 kali masuk. Scan kedua menampilkan peringatan merah "TIKET SUDAH DIGUNAKAN".</li>
            <li><strong>Cetak E-Ticket:</strong> Terkunci sampai status pembayaran <strong>Lunas</strong> (diverifikasi Panitia Keuangan).</li>
        </ul>
    </div>
</div>

<?php require_once __DIR__ . '/../src/Views/layouts/footer.php'; ?>
