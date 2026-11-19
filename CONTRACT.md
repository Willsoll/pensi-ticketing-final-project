# KONTRAK PENGEMBANGAN v4 — PENSI EVENTPRO
Baca dokumen ini sampai habis sebelum menulis kode. Semua halaman HARUS mengikuti kontrak ini.

## Lokasi & struktur
- Proyek: `C:/Users/Wilson/pensi-v4/`
- Webroot: `public/` — halaman PHP diletakkan langsung di `public/`
- Include wajib di setiap halaman:
```php
require_once __DIR__ . '/../src/Config/database.php';   // menghasilkan $pdo
require_once __DIR__ . '/../src/Middleware/middleware.php';
release_expired_transactions($pdo);
```
- Layout:
```php
$pageTitle = '...'; $pageSubtitle = '...'; $activeNav = 'index.php';
require __DIR__ . '/../src/Views/layouts/header.php';
... HTML ...
require __DIR__ . '/../src/Views/layouts/footer.php';
```
- JANGAN mengubah: `src/Config/database.php`, `src/Middleware/middleware.php`, `src/Views/layouts/*`, `public/assets/*`. Hanya file yang ditugaskan.

## Database: `db_tiket_event_pro` (JANGAN ubah skema)
- **users**: id_user, nik (UNIQUE), nama_lengkap, email (UNIQUE), no_hp, password, role ENUM('customer','admin_eo','finance','gate_officer')
- **kategori_event**: id_kat_event, nama_kategori
- **events**: id_event, id_kat_event, judul_event, penyelenggara, lokasi, tipe_venue ENUM('Seated','Free Standing'), tgl_mulai, tgl_selesai, banner, status_event ENUM('Draft','Published','Closed')
- **tiket_tier**: id_tier, id_event, nama_tier, harga, kuota_total, kuota_tersedia, kuota_locked, batas_jual DATETIME
- **kursi_event**: id_kursi, id_tier, nomor_kursi, status_kursi ENUM('Available','Locked','Sold')
- **voucher_promo**: id_voucher, kode_voucher, tipe_potongan ENUM('Persen','Nominal'), nilai_potongan, min_transaksi, kuota_pakai, expired_date
- **transaksi**: id_transaksi, kode_booking, id_user, id_event, id_voucher NULL, subtotal, diskon, biaya_layanan, total_bayar, bukti_bayar, batas_bayar_at, status_bayar ENUM('Pending','Waiting Verification','Paid','Expired','Rejected'), catatan_penolakan, created_at
- **transaksi_detail** (pendukung): id_detail, id_transaksi, id_tier, jumlah, harga_satuan, subtotal, id_kursi_list (CSV id_kursi)
- **transaksi_peserta** (pendukung): id_peserta, id_transaksi, id_tier, nama_peserta, nik_peserta, id_kursi NULL — identitas tiap lembar saat booking; sumber data penerbitan e_tickets saat Paid.
- **e_tickets**: id_etiket, id_transaksi, id_tier, id_kursi NULL, kode_tiket UNIQUE, nama_peserta, nik_peserta, status_checkin ENUM('Belum Hadir','Sudah Hadir'), waktu_checkin, id_petugas_gate
- **audit_logs**: id_log, id_user, aksi, entitas, keterangan, waktu
- **event_rundown**: id_rundown, id_event, judul_sesi, pembicara, jam_mulai, jam_selesai, lokasi_panggung
- **login_attempts**: id, identifier, success, created_at

## Aturan bisnis WAJIB
1. Booking: `kuota_tersedia -= qty`, `kuota_locked += qty`; kursi dipilih → `Locked`; `batas_bayar_at = NOW() + 15 MINUTE`; status `Pending`.
2. Upload bukti → status `Waiting Verification`.
3. Finance `Paid`: kursi `Locked→Sold`, `kuota_locked -= qty`, terbitkan `e_tickets` dari baris `transaksi_peserta` (satu per peserta, kode unik).
4. Finance `Rejected`: kursi `Locked→Available`, `kuota_locked -= qty`, `kuota_tersedia += qty`, voucher `kuota_pakai -= 1`.
5. Expired ditangani `release_expired_transactions($pdo)`.
6. Maksimal **5 tiket per event per akun/NIK** (akumulasi) → `check_user_ticket_limit($pdo,$id_user,$id_event,$qty)`.
7. Setiap lembar tiket wajib Nama + NIK berbeda dalam satu pesanan (validasi di server).
8. Voucher hanya valid jika subtotal ≥ min_transaksi, kuota_pakai > 0, belum expired → `validate_voucher($pdo,$kode,$subtotal)` mengembalikan `['ok','msg','id','diskon',...]`.
9. `biaya_layanan = 2500 * qty` (konstanta `BIAYA_LAYANAN_PER_TIKET`), `total_bayar = subtotal - diskon + biaya_layanan`.
10. E-ticket hanya terbit setelah `Paid`. Gate memvalidasi `kode_tiket` + `nik_peserta`; jika `Sudah Hadir` → tolak dengan peringatan merah + jam masuk + nama petugas.
11. `batas_jual` tier: tolak pembelian setelah waktu tersebut.

## Helper tersedia (JANGAN duplikasi)
`e($v)`, `format_rupiah($int)`, `format_tanggal($dt)`, `label_role($role)`, `label_status($status)`, `status_class($status)`, `generate_csrf_token()`, `csrf_field()`, `verify_csrf_token($t)`, `set_flash($type,$msg)`, `get_flash()`, `is_logged_in()`, `current_user()`, `require_login()`, `require_role([...])`, `catat_audit_log($pdo,$id_user,$aksi,$entitas,$keterangan)`, `release_expired_transactions($pdo)`, `generate_kode_tiket($n)`, `generate_kode_booking()`, `render_barcode_svg($kode,$nik)`, `validate_voucher($pdo,$kode,$subtotal)`, `check_user_ticket_limit($pdo,$id_user,$id_event,$qty)`, `check_user_active_reservation($pdo,$id_user,$id_event)`, `handle_bukti_upload($_FILES['x'], __DIR__.'/uploads/bukti')`, `check_login_rate_limit($pdo,$id)`, `record_login_attempt($pdo,$id,$ok)`, `nav_items($user)`.

`current_user()` → `['id_user','nik','nama_lengkap','email','role']`.

## Desain (WAJIB pakai kelas yang sudah ada)
- Palet: `#1E1B4B` primary/header/sidebar, `#6D28D9` accent, `#F97316` CTA, `#F8FAFC` bg, `#059669` success. Jangan hardcode warna baru; gunakan `var(--color-*)`.
- Kelas: `.card`, `.card-elevated`, `.btn`, `.btn-primary` (CTA oranye), `.btn-outline`, `.btn-ghost`, `.btn-sm`, `.badge`, `.badge-success/.badge-warning/.badge-danger/.badge-info/.badge-muted`, `.form-group`, `.form-label`, `.form-control`, `.form-select`, `.table`, `.table-container`, `.alert alert-*`, `.stat-grid`, `.stat-box`, `.event-grid`, `.event-card`, `.event-banner`, `.filter-bar`, `.chart-bars/.chart-col/.chart-bar/.chart-label`, `.occupancy-row`, `.page-wrap`, `.empty-state`.
- Tema aktif otomatis via header (10 tema). Jangan menulis `<html>`/`<head>` sendiri.
- Ikon: SVG inline sederhana. TANPA emoji.
- Bahasa UI: Indonesia. Tanggal `format_tanggal()`, uang `format_rupiah()`.

## Keamanan
- Semua query prepared statement. Semua output `e()`. Semua POST `verify_csrf_token($_POST['csrf_token'] ?? '')` → jika gagal `http_response_code(403); exit('CSRF tidak valid');`.
- RBAC: `require_role(['admin_eo'])` dsb SEBELUM query sensitif.
- Upload hanya via `handle_bukti_upload()`. Jangan `move_uploaded_file` manual.
- Jangan tampilkan error DB mentah.

## Data seed (untuk uji)
- Akun (password semua `password123`): `admin.eo@eventpro.id` (admin_eo), `finance@eventpro.id` (finance), `gate@eventpro.id` (gate_officer), `dewi@student.id` / `rizki@alumni.id` / `maya@guest.id` (customer).
- 4 event: AI & Cyber Security Summit 2026 (Jakarta, Seated), Web Security Bootcamp (Tangerang, Seated), Digital Art & Creative Expo (Bandung, Free Standing), PENSI FEST 2026 (Serpong, Free Standing).
- 12 tier (Early Bird/Reguler/VIP), 230 kursi, 4 voucher: HEMAT10 (10%), POTONG25K (25k min 150k), EARLYBIRD (15%), MAHASISWA20 (20%).
- 13 transaksi seed + 13 e_tickets.

## Referensi kode lama (boleh dibaca, JANGAN diubah)
`C:/xampp/htdocs/public/*.php` (v3.2) — logika serupa tapi skema lama. Port konsepnya, sesuaikan ke skema baru.

## Verifikasi wajib sebelum selesai
- `C:/xampp/php/php.exe -l <file>` untuk setiap file yang dibuat.
- Jelaskan di laporan: file, fitur, query penting, dan asumsi.
