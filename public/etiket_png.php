<?php
/**
 * etiket_png.php - Unduhan E-Ticket sebagai gambar JPG (GD, 800x1130)
 *
 * Atribut autentikasi sama dengan etiket.php:
 *   - require_login() (wajib login)
 *   - pembeli hanya boleh unduh tiket milik pesanannya sendiri
 *   - status_bayar harus 'Lunas'
 *
 * Parameter GET:
 *   kode_transaksi : string, maksimal 64 karakter (wajib)
 *   n              : indeks tiket 1-based dalam detail_tiket_satuan (default 1,
 *                    divalidasi terhadap jumlah detail)
 *
 * Respons: image/jpeg, Content-Disposition attachment E-Ticket-KODE-N.jpg
 * Jika ekstensi GD tidak dimuat: HTTP 500 dengan pesan yang jelas.
 */

require_once __DIR__ . '/../src/Config/database.php';
require_once __DIR__ . '/../src/Middleware/middleware.php';

require_login();
$user = current_user();

// ---------- Input: kode_transaksi (string, maks 64 karakter) ----------
$kode_raw         = $_GET['kode_transaksi'] ?? '';
$kode_transaksi   = is_string($kode_raw) ? mb_substr(trim($kode_raw), 0, 64) : '';
if ($kode_transaksi === '') {
    http_response_code(400);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<h2>E-Ticket JPG tidak dapat dibuat</h2><p>Kode transaksi tidak ditemukan.</p>';
    exit;
}

// ---------- Input: n (1-based, default 1) ----------
$n = isset($_GET['n']) ? (int)($_GET['n']) : 1;
if ($n < 1) {
    $n = 1;
}

// ---------- Ambil pesanan ----------
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
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<h2>E-Ticket JPG tidak dapat dibuat</h2><p>Data pesanan tidak ditemukan.</p>';
    exit;
}

// ---------- Akses: pembeli hanya boleh lihat milik sendiri ----------
if (($user['role'] ?? '') === 'pembeli' && (int)$pesanan['id_user'] !== (int)($user['id_user'] ?? 0)) {
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<h2>E-Ticket JPG tidak dapat dibuat</h2><p>Akses ditolak! E-Ticket ini bukan milik akun Anda.</p>';
    exit;
}

// ---------- Status bayar harus Lunas ----------
if ($pesanan['status_bayar'] !== 'Lunas') {
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<h2>E-Ticket JPG tidak dapat dibuat</h2><p>E-Ticket hanya dapat diunduh setelah pembayaran berstatus LUNAS.</p>';
    exit;
}

// ---------- Ambil detail tiket ----------
$stmtDet = $pdo->prepare("
    SELECT dts.*, kp.nomor_kursi
    FROM detail_tiket_satuan dts
    LEFT JOIN kursi_pensi kp ON dts.id_kursi = kp.id_kursi
    WHERE dts.id_pesan = ?
    ORDER BY dts.id_detail_tiket ASC
");
$stmtDet->execute([(int)$pesanan['id_pesan']]);
$detail_tiket = $stmtDet->fetchAll();

if ($detail_tiket === false || $n > count($detail_tiket)) {
    http_response_code(400);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<h2>E-Ticket JPG tidak dapat dibuat</h2><p>Indeks tiket tidak valid.</p>';
    exit;
}
$dt = $detail_tiket[$n - 1];

/**
 * Gambar e-ticket ke resource gambar GD (800x1130, JPEG).
 * Semua teks render dengan imagestring (font 5) untuk portabilitas.
 *
 * @return resource|false|null GD image resource, atau null jika GD tidak ada.
 */
if (!function_exists('pensi_draw_eticket_image')) {
    function pensi_draw_eticket_image(array $pesanan, array $dt)
    {
        if (!extension_loaded('gd')) {
            return null;
        }

        $w = 800;
        $h = 1010;
        $im = imagecreatetruecolor($w, $h);

        $white  = imagecolorallocate($im, 255, 255, 255);
        $dark   = imagecolorallocate($im, 17, 24, 39);
        $indigo = imagecolorallocate($im, 30, 27, 75);
        $purple = imagecolorallocate($im, 109, 40, 217);
        $orange = imagecolorallocate($im, 249, 115, 22);
        $gray   = imagecolorallocate($im, 107, 114, 128);
        $lgray  = imagecolorallocate($im, 229, 231, 235);
        $green  = imagecolorallocate($im, 5, 150, 105);
        $greenBg = imagecolorallocate($im, 236, 253, 245);

        // TrueType fonts (Windows). Fallback to built-in font 5 if missing.
        $fontReg  = 'C:/Windows/Fonts/arial.ttf';
        $fontBold = 'C:/Windows/Fonts/arialbd.ttf';
        $hasTtf   = is_file($fontReg) && is_file($fontBold);

        $text = function (int $x, int $y, string $str, $color, int $size = 11, bool $bold = false) use ($im, $hasTtf, $fontReg, $fontBold) {
            if ($hasTtf) {
                imagettftext($im, $size, 0, $x, $y, $color, $bold ? $fontBold : $fontReg, $str);
            } else {
                imagestring($im, 5, $x, $y - 12, $str, $color);
            }
        };
        $width = function (string $str, int $size = 11, bool $bold = false) use ($hasTtf, $fontReg, $fontBold) {
            if ($hasTtf) {
                $box = imagettfbbox($size, 0, $bold ? $fontBold : $fontReg, $str);
                return abs($box[2] - $box[0]);
            }
            return strlen($str) * imagefontwidth(5);
        };
        $center = function (string $str, int $y, $color, int $size = 11, bool $bold = false) use ($w, $text, $width) {
            $x = (int)(($w - $width($str, $size, $bold)) / 2);
            $text($x, $y, $str, $color, $size, $bold);
        };

        imagefilledrectangle($im, 0, 0, $w - 1, $h - 1, $white);
        // Aksen pita atas
        imagefilledrectangle($im, 0, 0, $w - 1, 10, $indigo);
        imagefilledrectangle($im, 0, 10, $w - 1, 14, $orange);
        // Border tipis
        imagerectangle($im, 0, 0, $w - 1, $h - 1, $dark);

        $y = 56;
        $center('PENSI SCHOOL 2026', $y, $indigo, 27, true);
        $y += 26;
        $center('Pentas Seni Tahunan Sekolah Terpadu Pahoa', $y, $gray, 12);
        $y += 26;
        $center('Kode Transaksi: ' . $pesanan['kode_transaksi'], $y, $dark, 12);
        $y += 30;

        // Badge status LUNAS
        $badge = 'STATUS PEMBAYARAN: LUNAS';
        $bw = $width($badge, 12, true) + 36;
        $bx = (int)(($w - $bw) / 2);
        imagefilledrectangle($im, $bx, $y - 20, $bx + $bw, $y + 8, $greenBg);
        imagerectangle($im, $bx, $y - 20, $bx + $bw, $y + 8, $green);
        $center($badge, $y, $green, 12, true);
        $y += 42;

        // ---------- Blok data tiket ----------
        $blockX = 46;
        $blockW = $w - 92;
        $blockTop = $y;
        $fields = [
            ['JENIS TIKET', $pesanan['nama_jenis'] . ' (' . $pesanan['tipe_tiket'] . ')'],
            ['KODE E-TICKET UNIK', $dt['kode_tiket_unik']],
            ['NOMOR KURSI / ZONA', (!empty($dt['nomor_kursi']) ? 'Kursi ' . $dt['nomor_kursi'] : 'Free Standing (Bebas)')],
            ['NAMA LENGKAP', $dt['nama_pemegang_tiket']],
            ['NIS / NIK KTP', $dt['nik_pemegang_tiket']],
            ['STATUS KEHADIRAN', $dt['status_checkin']],
        ];
        $rowPad = 46;
        $blockH = 26 + count($fields) * $rowPad;
        imagefilledrectangle($im, $blockX, $blockTop, $blockX + $blockW, $blockTop + $blockH, $white);
        imagerectangle($im, $blockX, $blockTop, $blockX + $blockW, $blockTop + $blockH, $lgray);
        imagefilledrectangle($im, $blockX, $blockTop, $blockX + 6, $blockTop + $blockH, $purple);

        $ty = $blockTop + 28;
        foreach ($fields as $field) {
            $text($blockX + 26, $ty, $field[0], $gray, 9, true);
            $ty += 18;
            $text($blockX + 26, $ty, $field[1], $dark, 13, true);
            $ty += $rowPad - 18;
        }
        $y = $blockTop + $blockH + 34;

        // ---------- Barcode ----------
        $hash = hash_hmac('sha256', $dt['kode_tiket_unik'] . '|' . $dt['nik_pemegang_tiket'], (string)BARCODE_HMAC_SECRET);
        $center('BARCODE UNTUK GATE ENTRY', $y, $dark, 12, true);
        $y += 20;

        $bars = 56;
        $widths = [];
        $totalW = 0;
        for ($i = 0; $i < $bars; $i++) {
            $val = hexdec($hash[$i]);
            $bw = ($val % 3 === 0) ? 6 : (($val % 2 === 0) ? 4 : 3);
            $widths[] = [$bw, $val >= 4];
            $totalW += $bw + 4;
        }
        $bx = (int)(($w - $totalW) / 2);
        $by = $y;
        $bh = 110;
        foreach ($widths as $bar) {
            if ($bar[1]) {
                imagefilledrectangle($im, $bx, $by, $bx + $bar[0] - 1, $by + $bh - 1, $dark);
            }
            $bx += $bar[0] + 4;
        }
        $y += $bh + 26;
        $center($dt['kode_tiket_unik'], $y, $dark, 12, true);
        $y += 22;
        $center('Tunjukkan e-ticket ini di Gate bersama KTP / Kartu Pelajar.', $y, $gray, 10);
        $y += 30;

        // ---------- Info acara ----------
        $center('Acara: Pensi School 2026 - Sabtu, 12 Desember 2026 - 17:00-22:00 WIB', $y, $dark, 11);
        $y += 20;
        $center('Lokasi: Aula Serbaguna Sekolah Terpadu Pahoa, Jl. Raya Serpong, Tangerang Selatan', $y, $gray, 10);
        $y += 26;

        // Garis putus-putus
        for ($dx = 46; $dx < $w - 46; $dx += 16) {
            imagefilledrectangle($im, $dx, $y, $dx + 8, $y + 2, $lgray);
        }
        $y += 24;

        $center('SYARAT & KETENTUAN', $y, $dark, 11, true);
        $y += 18;
        $terms = [
            '1. E-Ticket bersifat personal (identity binding), tidak dapat dipindahtangankan.',
            '2. Berlaku hanya untuk 1 kali masuk. Scan kedua ditolak (anti-passback).',
            '3. Wajib membawa KTP asli / Kartu Pelajar sesuai NIS/NIK di atas.',
            '4. E-Ticket sah hanya bila status pembayaran LUNAS.',
        ];
        foreach ($terms as $t) {
            $center($t, $y, $gray, 9);
            $y += 17;
        }

        // Footer band
        $bandY = $h - 54;
        imagefilledrectangle($im, 14, $bandY, $w - 15, $h - 15, $dark);
        $center('PENSI SCHOOL 2026  -  EVENT TICKETING SYSTEM', $bandY + 25, $white, 11, true);

        return $im;
    }
}

// ---------- Output JPG (diabaikan saat dimuat untuk membangun PDF) ----------
if (!defined('PENSI_ETIKET_PNG_DEFINE_ONLY') || !PENSI_ETIKET_PNG_DEFINE_ONLY) {
    if (!extension_loaded('gd')) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<h2>E-Ticket JPG tidak dapat dibuat</h2><p>Ekstensi GD PHP tidak dimuat. Aktifkan extension=gd pada PHP lalu coba lagi.</p>';
        exit;
    }
    $im = pensi_draw_eticket_image($pesanan, $dt);
    if ($im === false || $im === null) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<h2>E-Ticket JPG tidak dapat dibuat</h2><p>Gagal mengalokasikan gambar GD.</p>';
        exit;
    }
    header('Content-Type: image/jpeg');
    header('Content-Disposition: attachment; filename="E-Ticket-' . $kode_transaksi . '-' . $n . '.jpg"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    imagejpeg($im);
    imagedestroy($im);
    exit;
}
