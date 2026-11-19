<?php
/**
 * etiket.php - Cetak E-Ticket Satuan (Print View)
 * Hanya bisa diakses jika status_bayar = 'Lunas'
 * Menampilkan barcode SVG + data peserta
 */
require_once __DIR__ . '/../src/Config/database.php';
require_once __DIR__ . '/../src/Middleware/middleware.php';

require_login();
$user = current_user();

$kode_raw       = $_GET['kode_transaksi'] ?? '';
$kode_transaksi = is_string($kode_raw) ? mb_substr(trim($kode_raw), 0, 64) : '';
if ($kode_transaksi === '') {
    set_flash('ERROR', 'Kode transaksi tidak ditemukan.');
    header('Location: dashboard_peserta.php');
    exit;
}

// Ambil pesanan
$stmt = $pdo->prepare("
    SELECT p.*, j.nama_jenis, j.tipe_tiket
    FROM pemesanan p
    JOIN jenis_tiket j ON p.id_jenis = j.id_jenis
    WHERE p.kode_transaksi = ?
    LIMIT 1
");
$stmt->execute([$kode_transaksi]);
$pesanan = $stmt->fetch();

if (!$pesanan) {
    set_flash('ERROR', 'Data pesanan tidak ditemukan.');
    header('Location: dashboard_peserta.php');
    exit;
}

// Cek akses: customer hanya bisa lihat milik sendiri
if ($user['role'] === 'pembeli' && (int)$pesanan['id_user'] !== (int)$user['id_user']) {
    http_response_code(403);
    set_flash('ERROR', 'Akses ditolak! E-Ticket ini bukan milik akun Anda.');
    header('Location: dashboard_peserta.php');
    exit;
}

// Cek status bayar
if ($pesanan['status_bayar'] !== 'Lunas') {
    set_flash('ERROR', 'E-Ticket hanya dapat dicetak setelah pembayaran berstatus LUNAS.');
    header('Location: dashboard_peserta.php');
    exit;
}

// Ambil detail tiket
$stmtDet = $pdo->prepare("
    SELECT dts.*, kp.nomor_kursi
    FROM detail_tiket_satuan dts
    LEFT JOIN kursi_pensi kp ON dts.id_kursi = kp.id_kursi
    WHERE dts.id_pesan = ?
    ORDER BY dts.id_detail_tiket ASC
");
$stmtDet->execute([(int)$pesanan['id_pesan']]);
$detail_tiket = $stmtDet->fetchAll();

$page_title = 'Cetak E-Ticket - ' . $kode_transaksi;
// Gunakan layout print-only (tanpa header/footer standar)
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Ticket - <?= e($kode_transaksi) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f3f4f6; padding: 20px; }
        @media print { body { background: white; padding: 0; } }
        .container { max-width: 700px; margin: 0 auto; }
        .etiket-card { background: white; border: 3px solid #111827; border-radius: 12px; padding: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .etiket-header { text-align: center; border-bottom: 2px solid #111827; padding-bottom: 16px; margin-bottom: 20px; }
        .etiket-header h1 { color: #4F46E5; font-size: 1.75rem; margin-bottom: 4px; }
        .etiket-header .sub { color: #EAB308; font-size: 1rem; font-weight: 600; }
        .etiket-header .booking { margin-top: 8px; font-size: 0.875rem; color: #6B7280; }
        .etiket-header .booking code { background: #F3F4F6; padding: 2px 6px; border-radius: 4px; }
        .etiket-header .status { display: inline-block; margin-top: 8px; padding: 4px 12px; background: #D1FAE5; color: #065F46; border-radius: 9999px; font-weight: 700; font-size: 0.875rem; }
        .ticket-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
        .ticket-item { border: 1px solid #E5E7EB; border-radius: 8px; padding: 16px; }
        .ticket-item.seated { border-left: 4px solid #4F46E5; }
        .ticket-item.free { border-left: 4px solid #16A34A; }
        .ticket-item h3 { color: #111827; font-size: 1rem; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #E5E7EB; }
        .ticket-field { margin-bottom: 8px; font-size: 0.875rem; }
        .ticket-field label { color: #6B7280; font-weight: 500; display: block; margin-bottom: 2px; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .ticket-field span { color: #111827; font-weight: 600; }
        .ticket-field code { background: #F3F4F6; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; }
        .barcode-section { text-align: center; padding: 16px; background: #FAFAFA; border-radius: 8px; margin-bottom: 16px; }
        .barcode-section img, .barcode-section svg { max-width: 100%; height: auto; }
        .footer-note { text-align: center; color: #6B7280; font-size: 0.75rem; border-top: 1px dashed #D1D5DB; padding-top: 12px; }
        .no-print { text-align: center; margin-top: 20px; }
        .no-print button { background: #4F46E5; color: white; border: none; padding: 12px 28px; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; }
        .no-print button:hover { background: #4338CA; }
        .no-print a { display: inline-block; margin-top: 12px; color: #6B7280; text-decoration: none; }
        @media print { .no-print { display: none !important; } .etiket-card { box-shadow: none; border: 2px solid #111827; } }
    </style>
</head>
<body>

<div class="container">
    <?php foreach ($detail_tiket as $idx => $dt): 
        $barcode_svg = render_barcode_svg($dt['kode_tiket_unik'], $dt['nik_pemegang_tiket']);
        $is_seated = !empty($dt['nomor_kursi']);
    ?>
        <div class="etiket-card" style="page-break-after: <?= $idx < count($detail_tiket) - 1 ? 'always' : 'auto' ?>;">

            <div class="etiket-header">
                <h1>🎭 PENSI SCHOOL 2026</h1>
                <div class="sub">Pentas Seni Tahunan Sekolah Terpadu Pahoa</div>
                <div class="booking">
                    Kode Transaksi: <code><?= e($pesanan['kode_transaksi']) ?></code> 
                    &bull; Tiket #<?= $idx + 1 ?> dari <?= count($detail_tiket) ?>
                </div>
                <div class="status">✅ STATUS PEMBAYARAN: LUNAS</div>
            </div>

            <div class="ticket-grid">
                <div class="ticket-item <?= $is_seated ? 'seated' : 'free' ?>">
                    <h3>🎫 Informasi Tiket</h3>
                    <div class="ticket-field">
                        <label>Jenis Tiket</label>
                        <span><?= e($pesanan['nama_jenis']) ?> (<?= e($pesanan['tipe_tiket']) ?>)</span>
                    </div>
                    <div class="ticket-field">
                        <label>Kode E-Ticket Unik</label>
                        <span><code><?= e($dt['kode_tiket_unik']) ?></code></span>
                    </div>
                    <div class="ticket-field">
                        <label>Nomor Kursi / Zona</label>
                        <span>
                            <strong>
                                <?= $is_seated ? 'Kursi ' . e($dt['nomor_kursi']) : 'Free Standing (Bebas)' ?>
                            </strong>
                        </span>
                    </div>
                </div>

                <div class="ticket-item">
                    <h3>👤 Data Peserta (Identity Binding)</h3>
                    <div class="ticket-field">
                        <label>Nama Lengkap</label>
                        <span><strong><?= e($dt['nama_pemegang_tiket']) ?></strong></span>
                    </div>
                    <div class="ticket-field">
                        <label>NIS / NIK KTP</label>
                        <span><code><?= e($dt['nik_pemegang_tiket']) ?></code></span>
                    </div>
                    <div class="ticket-field">
                        <label>Status Kehadiran</label>
                        <span>
                            <span class="badge badge-<?= $dt['status_checkin'] === 'Sudah Masuk' ? 'sudah-masuk' : 'belum-masuk' ?>">
                                <?= e($dt['status_checkin']) ?>
                            </span>
                            <?php if ($dt['status_checkin'] === 'Sudah Masuk'): ?>
                                <br><small>Check-in: <?= e(date('d-m-Y H:i', strtotime($dt['waktu_checkin']))) ?></small>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="barcode-section">
                <strong>BARCODE / QR CODE UNTUK GATE ENTRY</strong><br><br>
                <?= $barcode_svg ?><br><br>
                <small style="color: #6B7280;">
                    Wajib membawa <strong>KTP Asli / Kartu Pelajar</strong> sesuai NIK/NIS tertera saat pemindaian di pintu masuk acara.
                </small>
            </div>

            <div class="footer-note">
                E-Ticket ini bersifat personal (Identity Binding) dan hanya berlaku untuk 1 kali masuk.<br>
                Acara: Pensi School 2026 &bull; Sabtu, 12 Desember 2026 &bull; 17:00-22:00 WIB<br>
                Lokasi: Aula Serbaguna Sekolah Terpadu Pahoa, Jl. Raya Serpong, Tangerang Selatan
            </div>
        </div>
    <?php endforeach; ?>

    <div class="no-print">
        <button type="button" onclick="window.print();"><strong>🖨️ CETAK SEMUA E-TICKET (PRINT)</strong></button>
        <br><br>
        <a href="dashboard_peserta.php">[ &larr; Kembali ke Dashboard Peserta ]</a>
    </div>
</div>

</body>
</html>