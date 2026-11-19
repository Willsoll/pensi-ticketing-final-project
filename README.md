# PENSI EVENTPRO — Team Test Build (v4.2)

Aplikasi pemesanan tiket event multi-event (PAKET 7) berbasis **Native PHP + MySQL**.
Build ini dibuat agar bisa dijalankan di **XAMPP** maupun **Laragon** tanpa mengubah kode.

---

## 1. Yang dibutuhkan

- XAMPP atau Laragon (Apache + MySQL/MariaDB + PHP 8.0+)
- PHP extension: `pdo_mysql`, `gd`, `fileinfo` (biasanya sudah aktif di XAMPP/Laragon)
- Browser modern (Chrome/Edge/Firefox)

---

## 2. Cara tercepat (XAMPP / Laragon, tanpa ubah DocumentRoot)

1. Ekstrak folder ini sehingga menjadi:
   - XAMPP: `C:\xampp\htdocs\pensi`
   - Laragon: `C:\laragon\www\pensi`
2. Jalankan **Apache** dan **MySQL** dari control panel XAMPP/Laragon.
3. Buka di browser:
   - `http://localhost/pensi/setup.php`
   - (jika 404/403, coba `http://localhost/pensi/public/setup.php`)
4. Isi form database (default sudah benar untuk XAMPP/Laragon):
   - Host `127.0.0.1`, Port `3306`, User `root`, Password **kosong**, Nama DB `db_tiket_event_pro`
5. Klik **Instal Sekarang** → tunggu sampai muncul “Instalasi berhasil”.
6. Klik **Buka Aplikasi**, atau buka `http://localhost/pensi/`.

> Installer membuat database, mengimpor skema, mengisi data demo, dan menulis `config.local.php`.
> File `public/setup.php` otomatis terkunci setelah instalasi.

### Jika rewrite `.htaccess` tidak aktif
Buka langsung: `http://localhost/pensi/public/` — aplikasi tetap berjalan (CSS/JS ikut benar).

---

## 3. Cara alternatif: DocumentRoot / Virtual Host

Arahkan DocumentRoot (atau vhost Laragon) ke folder **`public/`**:

```apache
DocumentRoot "C:/xampp/htdocs/pensi/public"
<Directory "C:/xampp/htdocs/pensi/public">
    AllowOverride All
    Require all granted
</Directory>
```

Lalu buka `http://localhost/` (atau domain vhost) dan jalankan `setup.php`.

---

## 4. Instalasi via CLI (opsional)

```bash
cd C:\xampp\htdocs\pensi
php install.php --user=root --pass= --name=db_tiket_event_pro
```

Atau jalankan `install.bat` (Windows).

---

## 5. Akun demo (password: `password123`)

| Email | Peran |
|---|---|
| `admin.eo@eventpro.id` | Event Organizer (dashboard, CRUD, voucher, audit) |
| `finance@eventpro.id` | Verifikasi pembayaran |
| `gate@eventpro.id` | Gate check-in |
| `dewi@student.id` | Peserta (sudah punya transaksi) |
| `rizki@alumni.id` | Peserta |
| `maya@guest.id` | Peserta |

Data demo: 4 event (Jakarta, Tangerang, Bandung, Serpong), 12 tier, 230 kursi, 4 voucher
(`HEMAT10`, `POTONG25K`, `EARLYBIRD`, `MAHASISWA20`), 13 transaksi, 13 e-ticket.

---

## 6. Tema

- Default: `07` Aurora Glass (light)
- Ganti lewat tombol mengambang di kanan bawah, atau URL `?theme=01` … `?theme=10`
- 01–05 dark, 06–10 light. Pilihan tersimpan di cookie.

---

## 7. Struktur folder

```
pensi/
├─ public/            ← webroot (semua halaman PHP + assets)
├─ src/               ← Config, Middleware, Views
├─ database/          ← schema.sql, seed.php, seed_lib.php
├─ storage/           ← secret & lock (jangan dihapus)
├─ tests/             ← functional_test.php (32 check)
├─ docs/              ← panduan testing & feedback
├─ install.php        ← installer CLI
├─ config.local.php   ← dibuat installer (kredensial DB)
└─ .htaccess          ← rewrite ke public/ + proteksi folder
```

---

## 8. Panduan pengguna

Panduan langkah demi langkah untuk peserta, finance, gate, dan admin:
**`docs/USER_GUIDE.md`**.

## 9. Testing & feedback

1. Baca `docs/TESTING_GUIDE.md` dan jalankan skenario 1–15.
2. Catat hasil di `docs/FEEDBACK_TEMPLATE.md` (salin ke file baru).
3. Sertakan screenshot untuk bug dan isi lingkungan (XAMPP/Laragon, versi PHP, browser).
4. Kirim ke saya → saya perbaiki → build baru dikirim lagi.

Panduan lengkap: `docs/TESTING_GUIDE.md`, `docs/FEEDBACK_TEMPLATE.md`, `docs/TROUBLESHOOTING.md`.

Jika ada error: cek `docs/TROUBLESHOOTING.md`, lalu kirim potongan
`C:\xampp\apache\logs\error.log` (atau `laragon\bin\apache\...\logs\error.log`).

---

## 10. Reset data demo

```bash
php install.php --user=root --pass= --name=db_tiket_event_pro
```
atau hapus `storage/installed.lock` lalu buka `setup.php` lagi.

---

## 11. Catatan keamanan untuk build testing

- `setup.php` hanya menerima akses dari localhost dan mati setelah instalasi.
- Jangan unggah folder `storage/`, `config.local.php`, atau file `.sql` ke hosting publik.
- Untuk demonstrasi publik nanti, gunakan kredensial DB khusus (bukan root) dan hapus `setup.php`.
