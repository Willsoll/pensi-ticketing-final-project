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
        $w = 800;
        $h = 1130;
        $im = imagecreatetruecolor($w, $h);

        $white  = imagecolorallocate($im, 255, 255, 255);
        $dark   = imagecolorallocate($im, 17, 24, 39);
        $indigo = imagecolorallocate($im, 30, 27, 75);
        $gray   = imagecolorallocate($im, 107, 114, 128);
        $lgray  = imagecolorallocate($im, 229, 231, 235);

        imagefilledrectangle($im, 0, 0, $w - 1, $h - 1, $white);
        imagesetthickness($im, 12);
        imagerectangle($im, 0, 0, $w - 1, $h - 1, $dark);
        imagesetthickness($im, 1);

        // Cari font yang tersedia (portabel: Windows, Linux hosting, atau folder assets/fonts).
        $fontPairs = [
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
        $center = function (string $str, int $y, $color, int $size = 13, bool $bold = false) use ($w, $text, $width) {
            $x = (int)(($w - $width($str, $size, $bold)) / 2);
            $text($x, $y, $str, $color, $size, $bold);
        };

        // ---------- Header ----------
        $y = 78;
        $center('PENSI EVENTPRO', $y, $indigo, 36, true);
        $y += 48;
        $center($row['judul_event'] ?? '-', $y, $dark, 22, true);
        $y += 32;
        $center(
            (($row['tgl_mulai'] ?? '') ? date('d M Y, H:i', strtotime($row['tgl_mulai'])) : '-')
            . '  ·  ' . ($row['lokasi'] ?? '-'),
            $y, $gray, 14);
        $y += 40;

        // Garis pemisah
        $lineW = 320;
        $lx = (int)(($w - $lineW) / 2);
        for ($dx = $lx; $dx < $lx + $lineW; $dx += 14) {
            imagefilledrectangle($im, $dx, $y, $dx + 8, $y + 2, $lgray);
        }
        $y += 44;

        // ---------- Blok data tiket ----------
        $blockX = 56;
        $blockW = $w - 112;
        $fields = [
            ['NAMA', $row['nama_peserta'] ?? '-'],
            ['NIK', $row['nik_peserta'] ?? '-'],
            ['TIER', $row['nama_tier'] ?? '-'],
            ['KURSI', !empty($row['nomor_kursi']) ? 'Kursi ' . $row['nomor_kursi'] : 'Free Standing'],
            ['KODE TIKET', $row['kode_tiket'] ?? '-'],
        ];
        $rowH = 56;
        imagefilledrectangle($im, $blockX, $y, $blockX + $blockW, $y + count($fields) * $rowH, $white);
        imagerectangle($im, $blockX, $y, $blockX + $blockW, $y + count($fields) * $rowH, $lgray);
        imagefilledrectangle($im, $blockX, $y, $blockX + 7, $y + count($fields) * $rowH, $indigo);

        $ty = $y + 32;
        foreach ($fields as $f) {
            $text($blockX + 30, $ty, $f[0], $gray, 12, true);
            $ty += 22;
            $text($blockX + 30, $ty, $f[1], $dark, 17, true);
            $ty += $rowH - 22;
        }
        $y += count($fields) * $rowH + 46;

        // ---------- Barcode ----------
        $center('BARCODE UNTUK GATE ENTRY', $y, $dark, 13, true);
        $y += 26;

        $hex = hash_hmac('sha256', ($row['kode_tiket'] ?? '') . '|' . ($row['nik_peserta'] ?? ''), BARCODE_HMAC_SECRET);
        $barCount = 56;
        $barW = [];
        $totalW = 0;
        for ($i = 0; $i < $barCount; $i++) {
            $wd = (hexdec($hex[$i]) % 3) + 1;
            $barW[] = $wd;
            $totalW += $wd + 1;
        }
        $barH = 110;
        $bx = (int)(($w - $totalW) / 2);
        $by = $y;
        foreach ($barW as $i => $wd) {
            if ($i % 2 === 0) {
                imagefilledrectangle($im, $bx, $by, $bx + $wd - 1, $by + $barH - 1, $dark);
            }
            $bx += $wd + 1;
        }
        $y += $barH + 30;
        $center($row['kode_tiket'] ?? '-', $y, $dark, 18, true);
        $y += 40;

        // ---------- Footer ----------
        $center('Tunjukkan e-ticket ini di gate bersama KTP asli.', $y, $gray, 12);
        $y += 30;
        $terms = [
            'E-ticket bersifat personal (identity binding) dan tidak dapat dipindahtangankan.',
            'Berlaku untuk 1 kali masuk; scan kedua ditolak (anti-passback).',
            'Sah hanya jika status pembayaran LUNAS.',
        ];
        foreach ($terms as $t) {
            $center($t, $y, $gray, 11);
            $y += 20;
        }

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
