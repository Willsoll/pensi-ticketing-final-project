<?php
/**
 * etiket_png.php — Unduhan E-Ticket sebagai gambar JPG (GD, 800x1130)
 *
 * Atribut autentikasi sama dengan etiket.php:
 *   - require_login() (wajib login)
 *   - pemilik transaksi, atau admin_eo / finance
 *   - status_bayar harus 'Paid'
 *
 * Parameter GET:
 *   kode : kode_booking (wajib)
 *   n    : indeks e-ticket 1-based (diurutkan id_etiket), divalidasi 1..jumlah
 *
 * Respons: image/jpeg, Content-Disposition attachment E-Ticket-<kode_tiket>.jpg
 * Jika ekstensi GD tidak dimuat: HTTP 500 dengan pesan yang jelas.
 *
 * Fungsi inti pensi_draw_eticket_image(array $row): GdImage digunakan ulang oleh etiket_pdf.php.
 */

declare(strict_types=1);
require_once __DIR__ . '/../src/Config/database.php';
require_once __DIR__ . '/../src/Middleware/middleware.php';
release_expired_transactions($pdo);

require_login();
$user = current_user();

// ---------------------------------------------------------------------------
// Autentikasi & data (sama dengan etiket.php)
// ---------------------------------------------------------------------------
$kode = is_string($_GET['kode'] ?? null) ? mb_substr(trim($_GET['kode']), 0, 64) : '';
if ($kode === '') {
    http_response_code(400);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<h2>E-Ticket JPG tidak dapat dibuat</h2><p>Kode booking tidak ditemukan.</p>';
    exit;
}

$n = isset($_GET['n']) ? (int)($_GET['n']) : 1;
if ($n < 1) {
    $n = 1;
}

$stmt = $pdo->prepare(
    'SELECT et.id_etiket, et.id_tier, et.id_kursi, et.kode_tiket, et.nama_peserta, et.nik_peserta,
            tt.nama_tier, k.nomor_kursi,
            t.id_transaksi, t.id_user, t.status_bayar,
            e.judul_event, e.lokasi, e.tgl_mulai
       FROM e_tickets et
       JOIN transaksi t ON t.id_transaksi = et.id_transaksi
       JOIN events e ON e.id_event = t.id_event
       JOIN tiket_tier tt ON tt.id_tier = et.id_tier
       LEFT JOIN kursi_event k ON k.id_kursi = et.id_kursi
      WHERE t.kode_booking = ?
      ORDER BY et.id_etiket ASC'
);
$stmt->execute([$kode]);
$rows = $stmt->fetchAll();

if (!$rows) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<h2>E-Ticket JPG tidak dapat dibuat</h2><p>Data e-ticket tidak ditemukan.</p>';
    exit;
}

$trx0 = $rows[0];

if ($user['role'] === 'customer' && (int)$trx0['id_user'] !== (int)$user['id_user']) {
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<h2>E-Ticket JPG tidak dapat dibuat</h2><p>Akses ditolak! E-ticket ini bukan milik akun Anda.</p>';
    exit;
}

if ($trx0['status_bayar'] !== 'Paid') {
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<h2>E-Ticket JPG tidak dapat dibuat</h2><p>E-ticket hanya dapat diunduh setelah pembayaran berstatus LUNAS.</p>';
    exit;
}

if ($n > count($rows)) {
    http_response_code(400);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<h2>E-Ticket JPG tidak dapat dibuat</h2><p>Indeks tiket tidak valid.</p>';
    exit;
}

$row = $rows[$n - 1];

if (!extension_loaded('gd')) {
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<h2>E-Ticket JPG tidak dapat dibuat</h2><p>Ekstensi GD PHP tidak dimuat. Aktifkan extension=gd pada PHP lalu coba lagi.</p>';
    exit;
}

// ---------------------------------------------------------------------------
// Renderer gambar e-ticket (digunakan ulang oleh etiket_pdf.php)
// ---------------------------------------------------------------------------
if (!function_exists('pensi_draw_eticket_image')) {
    /**
     * Gambar e-ticket ke dalam resource GD 800x1130.
     *
     * @param array $row Kunci: kode_tiket, nama_peserta, nik_peserta, nama_tier,
     *                   nomor_kursi (nullable), judul_event, lokasi, tgl_mulai
     * @return GdImage
     */
    function pensi_draw_eticket_image(array $row): GdImage
    {
        // Tiket landscape bergaya boarding pass / tiket kereta:
        // panel barcode vertikal di kiri, data peserta di kanan.
        $w = 1100;
        $h = 620;
        $im = imagecreatetruecolor($w, $h);

        $white  = imagecolorallocate($im, 255, 255, 255);
        $dark   = imagecolorallocate($im, 17, 24, 39);
        $indigo = imagecolorallocate($im, 30, 27, 75);
        $purple = imagecolorallocate($im, 109, 40, 217);
        $orange = imagecolorallocate($im, 249, 115, 22);
        $gray   = imagecolorallocate($im, 100, 116, 139);
        $lgray  = imagecolorallocate($im, 226, 232, 240);
        $green  = imagecolorallocate($im, 5, 150, 105);

        imagefilledrectangle($im, 0, 0, $w - 1, $h - 1, $white);
        imagesetthickness($im, 4);
        imagerectangle($im, 2, 2, $w - 3, $h - 3, $dark);
        imagesetthickness($im, 1);

        $fontPairs = [
            [__DIR__ . '/assets/fonts/Lato-Regular.ttf', __DIR__ . '/assets/fonts/Lato-Bold.ttf'],
            [__DIR__ . '/assets/fonts/arial.ttf', __DIR__ . '/assets/fonts/arialbd.ttf'],
            ['C:/Windows/Fonts/arial.ttf', 'C:/Windows/Fonts/arialbd.ttf'],
            ['C:/Windows/Fonts/segoeui.ttf', 'C:/Windows/Fonts/segoeuib.ttf'],
            ['C:/Windows/Fonts/calibri.ttf', 'C:/Windows/Fonts/calibrib.ttf'],
            ['/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'],
            ['/usr/share/fonts/dejavu/DejaVuSans.ttf', '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf'],
        ];
        $fontReg = ''; $fontBold = '';
        foreach ($fontPairs as [$reg, $bold]) {
            if (is_file($reg) && is_file($bold)) { $fontReg = $reg; $fontBold = $bold; break; }
        }
        $hasTtf = $fontReg !== '';

        $text = function (int $x, int $y, string $str, $color, int $size = 13, bool $bold = false) use ($im, $hasTtf, $fontReg, $fontBold) {
            if ($hasTtf) {
                imagettftext($im, $size, 0, $x, $y, $color, $bold ? $fontBold : $fontReg, $str);
            } else {
                imagestring($im, 5, $x, $y - 12, $str, $color);
            }
        };
        $width = function (string $str, int $size = 13, bool $bold = false) use ($hasTtf, $fontReg, $fontBold) {
            if ($hasTtf) {
                $box = imagettfbbox($size, 0, $bold ? $fontBold : $fontReg, $str);
                return abs($box[2] - $box[0]);
            }
            return strlen($str) * imagefontwidth(5);
        };

        // ---------- Panel kiri: barcode vertikal ----------
        imagefilledrectangle($im, 0, 0, 170, $h - 1, $indigo);

        $hex = hash_hmac('sha256', ($row['kode_tiket'] ?? '') . '|' . ($row['nik_peserta'] ?? ''), BARCODE_HMAC_SECRET);
        $by = 46;
        $barBottom = $h - 130;
        for ($i = 0; $i < 60 && $by < $barBottom; $i++) {
            $thickness = (hexdec($hex[$i]) % 3) + 1;
            if ($i % 2 === 0) {
                // Bar putih horizontal: hasil rotasi 90° dari barcode biasa.
                imagefilledrectangle($im, 30, $by, 140, $by + $thickness - 1, $white);
            }
            $by += $thickness + 2;
        }

        // Teks vertikal di panel
        if ($hasTtf) {
            imagettftext($im, 11, 90, 24, $h - 46, $white, $fontBold, 'GATE SCAN');
            imagettftext($im, 11, 90, 152, $h - 46, $white, $fontBold, 'E-TICKET');
        } else {
            imagestring($im, 3, 30, $h - 60, 'GATE SCAN', $white);
        }

        // Garis perforasi + lubang tiket
        for ($py = 8; $py < $h - 8; $py += 18) {
            imagefilledrectangle($im, 176, $py, 178, $py + 8, $lgray);
        }
        imagefilledellipse($im, 177, 0, 26, 26, $white);
        imagefilledellipse($im, 177, $h - 1, 26, 26, $white);
        imageellipse($im, 177, 0, 26, 26, $dark);
        imageellipse($im, 177, $h - 1, 26, 26, $dark);

        // ---------- Area kanan: data tiket ----------
        $x0 = 215;
        $xRight = $w - 52;
        $maxW = $xRight - $x0;

        $text($x0, 62, 'PENSI EVENTPRO', $indigo, 20, true);
        $et = 'E-TICKET';
        $text($xRight - $width($et, 15, true), 62, $et, $orange, 15, true);

        $judul = (string)($row['judul_event'] ?? '-');
        if ($width($judul, 26, true) > $maxW) {
            $judul = mb_substr($judul, 0, 46) . '...';
        }
        $text($x0, 112, $judul, $dark, 26, true);

        $when = !empty($row['tgl_mulai']) ? date('d M Y, H:i', strtotime((string)$row['tgl_mulai'])) : '-';
        $text($x0, 146, $when . '  ·  ' . ($row['lokasi'] ?? '-'), $gray, 14);

        // Badge LUNAS
        $badgeX = $xRight - 118;
        imagefilledrectangle($im, $badgeX, 76, $xRight, 106, $green);
        $text($badgeX + 22, 97, 'LUNAS', $white, 13, true);

        // Garis putus-putus
        for ($dx = $x0; $dx < $xRight; $dx += 16) {
            imagefilledrectangle($im, $dx, 172, $dx + 9, 174, $lgray);
        }

        // Dua kolom field
        $colB = 650;
        $text($x0, 212, 'NAMA PESERTA', $gray, 10, true);
        $text($x0, 240, (string)($row['nama_peserta'] ?? '-'), $dark, 21, true);

        $text($colB, 212, 'NIK', $gray, 10, true);
        $text($colB, 240, (string)($row['nik_peserta'] ?? '-'), $dark, 19, true);

        $text($x0, 296, 'TIER', $gray, 10, true);
        $text($x0, 324, (string)($row['nama_tier'] ?? '-'), $dark, 19, true);

        $text($colB, 296, 'KURSI', $gray, 10, true);
        $text($colB, 324, !empty($row['nomor_kursi']) ? (string)$row['nomor_kursi'] : 'Free Standing', $dark, 19, true);

        $text($x0, 380, 'KODE E-TICKET UNIK', $gray, 10, true);
        $text($x0, 410, (string)($row['kode_tiket'] ?? '-'), $purple, 20, true);

        // Catatan
        $text($x0, 470, 'Tunjukkan e-ticket ini bersama KTP asli di gate masuk.', $gray, 12);
        $text($x0, 494, 'Bersifat personal (identity binding) · hanya berlaku 1x masuk · anti-passback aktif.', $gray, 11);

        // Band bawah
        imagefilledrectangle($im, 172, $h - 46, $w - 3, $h - 3, $orange);
        $band = 'PENSI EVENTPRO  ·  E-TICKET  ·  ' . ($row['kode_tiket'] ?? '');
        $text($x0, $h - 16, $band, $white, 12, true);

        return $im;
    }
}

// ---------------------------------------------------------------------------
// Output JPG (dilompati saat etiket_pdf.php memuat file ini)
// ---------------------------------------------------------------------------
if (!defined('PENSI_ETIKET_PNG_SKIP_OUTPUT') || !PENSI_ETIKET_PNG_SKIP_OUTPUT) {
    $im = pensi_draw_eticket_image($row);
    if (!$im instanceof GdImage) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<h2>E-Ticket JPG tidak dapat dibuat</h2><p>Gagal mengalokasikan gambar GD.</p>';
        exit;
    }

    header('Content-Type: image/jpeg');
    header('Content-Disposition: attachment; filename="E-Ticket-' . ($row['kode_tiket'] ?? 'eticket') . '.jpg"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    imagejpeg($im);
    imagedestroy($im);
    exit;
}
