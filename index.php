<?php
/**
 * index.php - Halaman Katalog Acara Pensi (Public)
 * Menampilkan informasi acara, rundown, dan jenis tiket dengan filter
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/middleware.php';

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
require_once __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb">
    <a href="index.php">Beranda</a> > Katalog Acara
</div>

<!-- HERO / INFO ACARA UTAMA -->
<div class="card">
    <div class="card-header">
        <h2 style="color: #4F46E5; margin: 0;">🎭 PENSI SCHOOL 2026 - Pentas Seni Tahunan</h2>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-top: 16px;">
        <div style="background: #F0F9FF; border: 1px solid #BAE6FD; border-radius: 8px; padding: 16px;">
            <strong style="color: #0369A1;">📅 Tanggal Acara</strong>
            <div style="margin-top: 4px; font-size: 1.1rem;">Sabtu, 12 Desember 2026</div>
            <div style="margin-top: 2px; color: #64748B;">17:00 - 22:00 WIB</div>
        </div>
        <div style="background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 8px; padding: 16px;">
            <strong style="color: #166534;">📍 Lokasi</strong>
            <div style="margin-top: 4px;">Aula Serbaguna Sekolah Terpadu Pahoa</div>
            <div style="margin-top: 2px; color: #64748B;">Jl. Raya Serpong, Tangerang Selatan</div>
        </div>
        <div style="background: #FEF3C7; border: 1px solid #FCD34D; border-radius: 8px; padding: 16px;">
            <strong style="color: #92400E;">🎫 Tipe Tiket Tersedia</strong>
            <div style="margin-top: 4px;">
                <?php foreach ($jenis_list as $jt): ?>
                    <span class="badge <?= $jt['target_pembeli'] === 'Internal' ? 'badge-internal' : ($jt['target_pembeli'] === 'Eksternal' ? 'badge-eksternal' : '') ?>">
                        <?= e($jt['nama_jenis']) ?> - <?= format_rupiah((int)$jt['harga']) ?> (Sisa: <?= (int)$jt['stok_tersedia'] ?>)
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<br>

<!-- RUNDOWN ACARA -->
<div class="card">
    <div class="card-header">
        <h3 style="margin: 0;">📋 Rundown Acara & Penampilan</h3>
    </div>
    <table>
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
                    <td><span class="badge badge-<?= strtolower(str_replace(' ', '-', $rd['kategori_penampil'])) ?>"><?= e($rd['kategori_penampil']) ?></span></td>
                    <td><?= e($rd['lokasi_panggung']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<br>

<!-- JENIS TIKET & HARGA -->
<div class="card">
    <div class="card-header">
        <h3 style="margin: 0;">🎫 Jenis Tiket, Harga & Stok</h3>
    </div>
    <table>
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
                $can_buy = true;
                $buy_msg = '';
                if ($is_internal_only) $buy_msg = 'Hanya Siswa Internal (NIS)';
                elseif ($is_external_only) $buy_msg = 'Hanya Tamu Eksternal (NIK)';
                else $buy_msg = 'Semua (Internal & Eksternal)';
            ?>
                <tr>
                    <td><strong><?= e($jt['nama_jenis']) ?></strong></td>
                    <td>
                        <span class="badge <?= $is_internal_only ? 'badge-internal' : ($is_external_only ? 'badge-eksternal' : 'badge-available') ?>">
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
                            <a href="detail_event.php?id_jenis=<?= (int)$jt['id_jenis'] ?>" style="font-size: 0.8rem;">[ Pilih & Pesan ]</a>
                        <?php else: ?>
                            <span class="badge badge-sold">Sold Out</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<br>

<!-- PROMO AKTIF -->
<?php if (!empty($promo_list)): ?>
<div class="card">
    <div class="card-header">
        <h3 style="margin: 0;">🎁 Kode Promo Aktif</h3>
    </div>
    <table>
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
                    <td style="text-align: right;"><strong>- <?= format_rupiah((int)$pr['potongan_nominal']) ?></strong></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<br>

<div class="card" style="background: #FEF3C7; border-color: #FCD34D;">
    <h4 style="color: #92400E; margin-bottom: 8px;">⚠️ Aturan Penting (Mandat CEO)</h4>
    <ul style="color: #92400E; margin-left: 20px; line-height: 1.8;">
        <li><strong>Segmentasi Pembeli:</strong> Siswa Internal (NIS) hanya bisa beli tiket target 'Internal' atau 'Semua'. Tamu Eksternal (NIK) hanya bisa beli tiket target 'Eksternal' atau 'Semua'.</li>
        <li><strong>Identity Binding:</strong> Setiap tiket wajib diisi Nama Lengkap & NIK/NIS pemegang tiket yang berbeda (1 tiket = 1 identitas unik).</li>
        <li><strong>Batas Waktu Bayar:</strong> 15 menit dari pembuatan pesanan. Otomatis kadaluarsa & stok dikembalikan.</li>
        <li><strong>Anti-Passback Gate:</strong> 1 kode tiket = 1 kali masuk. Scan kedua menampilkan peringatan merah "TIKET SUDAH DIGUNAKAN".</li>
        <li><strong>Cetak E-Ticket:</strong> Terkunci sampai status pembayaran <strong>Lunas</strong> (diverifikasi Panitia Keuangan).</li>
    </ul>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>