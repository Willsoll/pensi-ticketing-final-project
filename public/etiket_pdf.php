<?php
/**
 * etiket_pdf.php - Unduhan E-Ticket sebagai PDF (minimal, tanpa library eksternal)
 *
 * Atribut autentikasi sama dengan etiket.php / etiket_png.php.
 * Menggunakan logika gambar GD dari etiket_png.php: gambar di-render ke JPEG
 * di memori (ob_start + imagejpeg), lalu disisipkan ke dalam satu halaman A4
 * (595x842 pt) sebagai XObject image dengan filter /DCTDecode.
 *
 * Susunan objek PDF (minimal, valid):
 *   1 0 R : Catalog
 *   2 0 R : Pages
 *   3 0 R : Page (MediaBox A4)
 *   4 0 R : XObject Image (DCTDecode / DeviceRGB)
 *   5 0 R : Contents stream (q W 0 0 H X Y cm /Im0 Do Q)
 *
 * Respons: application/pdf, Content-Disposition attachment E-Ticket-KODE.pdf
 * Jika ekstensi GD tidak dimuat: HTTP 500 dengan pesan yang jelas.
 */

if (!defined('PENSI_ETIKET_PNG_DEFINE_ONLY')) {
    define('PENSI_ETIKET_PNG_DEFINE_ONLY', true);
}
require_once __DIR__ . '/etiket_png.php'; // autentikasi, validasi, dan pensi_draw_eticket_image()

if (!function_exists('pensi_build_eticket_pdf')) {
    /**
     * Bangun bytes PDF satu halaman A4 dengan gambar e-ticket sebagai XObject.
     *
     * @return string|false bytes PDF, atau false jika gagal.
     */
    function pensi_build_eticket_pdf(array $pesanan, array $dt, int $n, string $kode_transaksi)
    {
        if (!extension_loaded('gd')) {
            return false;
        }
        $im = pensi_draw_eticket_image($pesanan, $dt);
        if ($im === false || $im === null) {
            return false;
        }

        // Render gambar tiket ke JPEG di memori (tanpa library).
        ob_start();
        $ok = imagejpeg($im);
        imagedestroy($im);
        if ($ok === false) {
            return false;
        }
        $jpeg = ob_get_clean();
        if ($jpeg === '' || $jpeg === false) {
            return false;
        }

        $w  = 800;                      // lebar gambar tiket (px)
        $h  = 1130;                     // tinggi gambar tiket (px)
        $pw = 595;                      // lebar A4 (pt)
        $ph = 842;                      // tinggi A4 (pt)
        $margin = 36;                   // margin minimum (pt)

        // Skala agar pas di A4 dengan margin, ditempatkan di tengah halaman.
        $scale = min(($pw - 2 * $margin) / $w, ($ph - 2 * $margin) / $h);
        $dw = $w * $scale;
        $dh = $h * $scale;
        $dx = ($pw - $dw) / 2;
        $dy = ($ph - $dh) / 2;

        $contents = sprintf("q %.2f 0 0 %.2f %.2f %.2f cm /Im0 Do Q\n", $dw, $dh, $dx, $dy);
        $clen     = strlen($contents);

        $objs = [];
        $objs[] = sprintf("1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n");
        $objs[] = sprintf("2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /MediaBox [0 0 595 842] >>\nendobj\n");
        $objs[] = sprintf("3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>\nendobj\n");
        $objs[] = sprintf(
            "4 0 obj\n<< /Type /XObject /Subtype /Image /Name /Im0 /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n",
            $w, $h, strlen($jpeg)
        ) . $jpeg . "\nendstream\nendobj\n";
        $objs[] = sprintf("5 0 obj\n<< /Length %d >>\nstream\n%s\nendstream\nendobj\n", $clen, $contents);

        $count = count($objs);

        $xref = "xref\n0 " . ($count + 1) . "\n";
        $xref .= "0000000000 65535 f \n";
        $pos = strlen('%PDF-1.4' . "\n");
        foreach ($objs as $objStr) {
            $xref .= sprintf('%010d 00000 n ', $pos) . "\n";
            $pos += strlen($objStr);
        }

        $pdf = '%PDF-1.4' . "\n";
        $pdf .= implode('', $objs);
        $pdf .= $xref;
        $pdf .= "trailer\n<< /Size " . ($count + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $pos . "\n%%EOF\n";
        return $pdf;
    }
}

// ---------- Output PDF (diabaikan saat dimuat untuk membangun PDF dari CLI) ----------
if (!defined('PENSI_ETIKET_PDF_DEFINE_ONLY') || !PENSI_ETIKET_PDF_DEFINE_ONLY) {
    $pdf = pensi_build_eticket_pdf($pesanan, $dt, $n, $kode_transaksi);
    if ($pdf === false) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<h2>E-Ticket PDF tidak dapat dibuat</h2><p>Ekstensi GD PHP tidak dimuat atau gambar gagal dibuat.</p>';
        exit;
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="E-Ticket-' . $kode_transaksi . '.pdf"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    echo $pdf;
    exit;
}
