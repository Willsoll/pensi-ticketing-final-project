# PANDUAN TESTING LENGKAP — PENSI EVENTPRO v4.2
**Tanggal:** 19 November 2026
**Build:** v4.2 (`db_tiket_event_pro`) · **URL lokal:** `http://127.0.0.1/`
**Hasil uji otomatis terakhir:** **54 PASS / 0 FAIL** (`docs/E2E_RESULTS_2026-11-19.txt`)

Dokumen ini menjelaskan cara menguji **setiap fungsi dan fitur** secara manual, termasuk
langkah, input, hasil yang diharapkan, dan cara memverifikasi di database. ID uji (`A1`, `B2`, …)
sama dengan ID di suite otomatis `tests/e2e_full_test.php`, sehingga hasil manual dan otomatis
bisa dibandingkan langsung.

---

## 0. Persiapan

### 0.1 Layanan & URL
| Komponen | Nilai |
|---|---|
| Apache | `http://127.0.0.1/` (XAMPP) |
| MySQL/MariaDB | `127.0.0.1:3306` |
| Database | `db_tiket_event_pro` |
| PHP | 8.2+ dengan `pdo_mysql`, `gd`, `fileinfo` |
| Akun DB | `pensi_app` (lokal) / `root` kosong (XAMPP/Laragon default) |

### 0.2 Reset data demo (awal pengujian)
```bash
cd C:\xampp\htdocs
php install.php --user=root --pass= --name=db_tiket_event_pro
```
Atau hapus `storage/installed.lock` lalu buka `setup.php`.

### 0.3 Akun uji (password semua: `password123`)
| Email | Peran | Kegunaan |
|---|---|---|
| `admin.eo@eventpro.id` | admin_eo | Dashboard EO, CRUD event/tier/kursi, voucher, audit |
| `finance@eventpro.id` | finance | Setujui/tolak pembayaran |
| `gate@eventpro.id` | gate_officer | Check-in & anti-passback |
| `dewi@student.id` | customer | Sudah punya transaksi Paid |
| `rizki@alumni.id` | customer | Riwayat campuran (Paid/Expired) |
| `maya@guest.id` | customer | Paid + Waiting |
| `bagas@student.id`, `citra@student.id`, dll | customer | Variasi data |

### 0.4 Data seed
| Item | Jumlah / Nilai |
|---|---|
| Event | 4: AI Cyber Security (Jakarta, Seated), Web Security Bootcamp (Tangerang, Seated), Digital Art Expo (Bandung, Free Standing), PENSI FEST 2026 (Serpong, Free Standing) |
| Tier | 12 (Early Bird / Reguler / VIP per event) |
| Kursi | 230 (hanya event Seated) |
| Voucher | `HEMAT10` (10%, min 100rb), `POTONG25K` (25rb, min 150rb), `EARLYBIRD` (15%, min 75rb), `MAHASISWA20` (20%, min 200rb) |
| Transaksi | 13 (Paid, Waiting, Pending, Rejected, Expired) |
| E-ticket | 13 (sebagian sudah check-in) |

### 0.5 Cara mencatat hasil
Untuk setiap ID: tulis **PASS/FAIL**, screenshot bila gagal, dan tempel query SQL verifikasi.
Gunakan `docs/FEEDBACK_TEMPLATE.md` untuk laporan tim.

---

## A. AUTENTIKASI & HAK AKSES

| ID | Fungsi | Langkah | Hasil yang diharapkan |
|---|---|---|---|
| **A1** | Login 4 peran | Logout, lalu login satu per satu: admin, finance, gate, dewi | admin → `dashboard_eo.php`; finance → `verifikasi_pembayaran.php`; gate → `gate_checkin.php`; dewi → `dashboard_peserta.php` |
| **A2** | Password salah | Login `dewi@student.id` + password salah | Tetap di halaman login, pesan “Password salah”, tidak ada redirect |
| **A3** | Rate limit | 5× login gagal dengan identifier baru, lalu percobaan ke-6 | Percobaan ke-6 menampilkan “Terlalu banyak percobaan. Coba lagi dalam 15 menit.” |
| **A4** | Registrasi peserta | Tab **Daftar**: nama, NIK 16 digit, email baru, HP, password | Redirect ke dashboard peserta; akun baru otomatis login; role `customer` di DB |
| **A5** | Email duplikat | Registrasi dengan email yang sudah ada | Ditolak, pesan email sudah terdaftar |
| **A6** | NIK duplikat | Registrasi dengan NIK yang sudah ada | Ditolak, pesan NIK sudah terdaftar |
| **A7** | Logout | Klik **Keluar**, lalu buka dashboard | Kembali ke login; akses dashboard → 302 ke login |
| **A8** | RBAC | Login sebagai dewi, buka `dashboard_eo.php` | HTTP **403 Akses Ditolak** |
| **A9** | Cookie sesi | Buka DevTools → Application → Cookies | Cookie `PENSI_EVENTPRO` memiliki `HttpOnly`, `SameSite=Strict` |
| **A10** | SQL injection login | Identifier `' OR 1=1 -- ` + password apa pun | Login gagal; tidak masuk dashboard |

**SQL verifikasi A4–A6**
```sql
SELECT id_user,nama_lengkap,nik,email,role FROM users WHERE email='qa.buyer@test.local';
SELECT COUNT(*) FROM users WHERE nik='3174010101060001'; -- harus 1
```

---

## B. KATALOG & DETAIL EVENT

| ID | Fungsi | Langkah | Hasil yang diharapkan |
|---|---|---|---|
| **B1** | Katalog multi-event | Buka `/` | 4 kartu event, statistik (event/tiket/kategori), badge kategori & Seated/Free Standing |
| **B2** | Filter kategori | Pilih **Workshop**, klik Terapkan | Hanya “Web Security Bootcamp” yang tampil |
| **B3** | Pencarian | Ketik `cyber` | Hanya “AI & Cyber Security Summit”; pencarian real-time menyaring kartu tanpa reload |
| **B4** | Detail lengkap | Buka event 1 | Deskripsi, tabel rundown 4 sesi, 3 tier, kuota, batas jual, seat map berwarna + bar PANGGUNG |
| **B5** | Filter kota/tanggal/status | Kota `Bandung`; tanggal 18–19 Des 2026; status Tersedia | Hasil menyempit sesuai kombinasi; kombinasi kosong menampilkan empty state, bukan error |
| **B6** | Free Standing | Buka event 3 (Digital Art Expo) | Input **Jumlah tiket**, bukan seat map; tombol Lanjut Pemesanan |
| **B7** | Tema via URL | Buka `/index.php?theme=01` lalu `?theme=10` | `data-theme` berubah; warna halaman ikut berubah dan tersimpan di cookie |

**SQL verifikasi B4/B6**
```sql
SELECT e.id_event,e.judul_event,e.tipe_venue,COUNT(k.id_kursi) AS kursi
FROM events e LEFT JOIN tiket_tier t ON t.id_event=e.id_event
LEFT JOIN kursi_event k ON k.id_tier=t.id_tier
GROUP BY e.id_event;
-- Event Seated: kursi > 0; Free Standing: 0
```

---

## C. PEMESANAN (BOOKING) & ATURAN BISNIS

| ID | Fungsi | Langkah | Hasil yang diharapkan |
|---|---|---|---|
| **C1** | Booking seated | Login peserta baru → event 1 → Reguler → pilih 2 kursi → isi 2 peserta (nama & NIK berbeda) → Lanjut | Redirect ke checkout `?kode=BK-…`; status **Menunggu Bayar**; ringkasan tampil |
| **C2** | Voucher persen + biaya layanan | Pada checkout di atas pakai `HEMAT10` (subtotal 200rb) | Subtotal 200.000; diskon 20.000; biaya layanan 5.000; **total 185.000** |
| **C3** | Kursi terkunci | Setelah booking, buka detail event dengan akun lain | Dua kursi yang dipilih berstatus **Locked** (kuning), tidak bisa dipilih |
| **C4** | Kuota berpindah | Bandingkan tier sebelum/sesudah | `kuota_tersedia` −2, `kuota_locked` +2 |
| **C5** | NIK sama dalam satu pesanan | Booking 2 tiket dengan NIK identik | Ditolak; tidak ada transaksi baru |
| **C6** | Batas 5 tiket/event | Akun yang sudah punya 4 tiket event sama, minta 2 lagi | Ditolak “Batas maksimal 5 tiket per event terlampaui” |
| **C7** | Expiry 15 menit | Buat booking, ubah `batas_bayar_at` ke masa lalu (SQL), buka halaman mana pun | Status **Expired**; kursi **Available**; kuota tersedia & locked kembali |
| **C8** | Voucher min transaksi | Pakai `HEMAT10` pada subtotal 50rb; `MAHASISWA20` pada 100rb | Ditolak dengan pesan minimal transaksi |
| **C9** | Voucher habis/expired | Set `kuota_pakai=0` atau `expired_date` lampau (SQL), lalu coba | Ditolak “Kuota habis” / “kedaluwarsa” |
| **C10** | Batas jual tier | Set `batas_jual` tier ke masa lalu (SQL) | Detail menampilkan “lewat waktu”; pemesanan diblokir |

**SQL verifikasi**
```sql
SELECT kode_booking,subtotal,diskon,biaya_layanan,total_bayar,status_bayar,batas_bayar_at
FROM transaksi ORDER BY id_transaksi DESC LIMIT 1;

SELECT id_tier,kuota_total,kuota_tersedia,kuota_locked FROM tiket_tier WHERE id_tier=2;

SELECT nomor_kursi,status_kursi FROM kursi_event WHERE id_tier=2 ORDER BY id_kursi LIMIT 5;

SELECT kode_voucher,tipe_potongan,nilai_potongan,min_transaksi,kuota_pakai,expired_date
FROM voucher_promo;
```
**Konsistensi stok (harus selalu benar):**
```sql
SELECT t.id_tier,t.kuota_total,t.kuota_tersedia,t.kuota_locked,
       (SELECT COUNT(*) FROM e_tickets e JOIN transaksi x ON x.id_transaksi=e.id_transaksi
        WHERE e.id_tier=t.id_tier AND x.status_bayar='Paid') AS sold
FROM tiket_tier t
ORDER BY t.id_tier;
-- kuota_total = kuota_tersedia + kuota_locked + sold
```

---

## D. CHECKOUT, COUNTDOWN & UPLOAD BUKTI

| ID | Fungsi | Langkah | Hasil yang diharapkan |
|---|---|---|---|
| **D1** | Halaman checkout | Setelah booking, buka `checkout.php?kode=…` | Ringkasan item, subtotal/diskon/biaya/total, status, batas bayar |
| **D2** | Countdown 15 menit | Amati timer | Berjalan mundur; merah di bawah 3 menit; “Waktu habis” di 0 |
| **D3** | Upload file salah | Unggah `.txt` atau file non-gambar | Ditolak dengan pesan format; status tetap **Pending**; `bukti_bayar` kosong |
| **D4** | Upload valid | Unggah JPG/PNG/WEBP < 2 MB | Status berubah **Menunggu Verifikasi**; `bukti_bayar` terisi path `uploads/bukti/…` |
| **D5** | Upload > 2 MB | Unggah gambar 3 MB | Ditolak “Ukuran file maksimal 2 MB” |
| **D6** | Ekstensi ganda | Unggah `bukti.jpg.php` | Ditolak sebelum MIME check |
| **D7** | Akses bukti | Klik link bukti sebagai pemilik; lalu sebagai peserta lain | Pemilik melihat gambar; peserta lain 404 |
| **D8** | Path traversal | Buka `bukti.php?kode=../../config.local.php` | 404/403, bukan isi file |

**SQL verifikasi**
```sql
SELECT kode_booking,status_bayar,bukti_bayar,batas_bayar_at FROM transaksi WHERE kode_booking='BK-…';
```

---

## E. VERIFIKASI PEMBAYARAN (FINANCE)

| ID | Fungsi | Langkah | Hasil yang diharapkan |
|---|---|---|---|
| **E1** | Daftar verifikasi | Login finance | Transaksi Waiting/Pending tampil; bukti bisa dibuka; statistik ringkas |
| **E2** | Setujui | Buka transaksi → **Setujui** | Status **Paid**; kursi Locked→Sold; `kuota_locked` berkurang; e-ticket terbit sejumlah peserta; audit `VERIFY` |
| **E3** | Tolak tanpa catatan | Klik Tolak dengan catatan kosong | Ditolak; status tetap Waiting |
| **E4** | Tolak dengan catatan | Isi catatan ≥5 karakter lalu Tolak | Status **Rejected**; catatan tampil ke peserta; kursi Available; kuota tersedia pulih; voucher quota kembali; audit `REJECT` |
| **E5** | E-ticket hanya setelah Paid | Sebelum approve, buka `etiket.php?kode=…` | Pesan bahwa e-ticket terbit setelah lunas; tidak ada tombol download |
| **E6** | Approve ganda | Klik Setujui dua kali / refresh POST | Tidak menerbitkan e-ticket duplikat; transaksi tetap Paid |

**SQL verifikasi**
```sql
SELECT status_bayar,catatan_penolakan FROM transaksi WHERE kode_booking='BK-…';
SELECT kode_tiket,nama_peserta,nik_peserta,status_checkin FROM e_tickets
WHERE id_transaksi=(SELECT id_transaksi FROM transaksi WHERE kode_booking='BK-…');
SELECT COUNT(*) FROM audit_logs WHERE aksi IN ('VERIFY','REJECT') ORDER BY id_log DESC LIMIT 5;
```

---

## F. E-TICKET (JPG / PDF)

| ID | Fungsi | Langkah | Hasil yang diharapkan |
|---|---|---|---|
| **F1** | Halaman e-ticket | Login peserta → transaksi Paid → **E-Ticket** | Satu kartu per peserta: nama, NIK tersamar, tier, kursi, kode tiket, barcode SVG, status check-in |
| **F2** | Download JPG | Klik **Download JPG** | File `E-Ticket-<kode>.jpg`; bisa dibuka; 800×1130; teks & barcode jelas |
| **F3** | Download PDF | Klik **Download PDF** | File `E-Ticket-<kode>.pdf`; valid (`%PDF-1.4 … %%EOF`); terbuka di viewer; isi sama dengan JPG |
| **F4** | Kepemilikan | Login akun lain, akses `etiket.php?kode=milik orang` | 403/404 |
| **F5** | Kode unik | Bandingkan 2 tiket dalam satu transaksi | `kode_tiket` berbeda; barcode berbeda |
| **F6** | Barcode HMAC | Ubah 1 karakter kode di URL? | Tidak relevan; barcode dihitung dari kode+NIK di server, bukan input user |
| **F7** | Cetak | Klik **Cetak** (Ctrl+P) | Hanya kartu tiket tercetak; sidebar/tombol hilang |

**SQL verifikasi**
```sql
SELECT kode_tiket,nama_peserta,nik_peserta,status_checkin FROM e_tickets
WHERE id_transaksi=(SELECT id_transaksi FROM transaksi WHERE kode_booking='BK-…');
```

---

## G. GATE CHECK-IN & ANTI-PASSBACK

| ID | Fungsi | Langkah | Hasil yang diharapkan |
|---|---|---|---|
| **G1** | Preview tiket | Login gate → masukkan kode tiket Paid | Data peserta, event, tier, kursi; NIK disamarkan; form NIK |
| **G2** | Check-in valid | Masukkan NIK peserta → konfirmasi | Kartu hijau **BERHASIL**; status `Sudah Hadir`; waktu & petugas tercatat; audit `CHECKIN` |
| **G3** | Anti-passback | Scan kode yang sama lagi | Peringatan merah **TIKET SUDAH DIGUNAKAN** + jam masuk + nama petugas; tidak ada check-in kedua |
| **G4** | NIK salah | Kode benar + NIK salah | Ditolak “NIK tidak sesuai” |
| **G5** | Kode acak | `TKT-TIDAK-ADA-999` | “TIKET TIDAK DITEMUKAN” |
| **G6** | Tiket belum lunas | Kode dari transaksi Pending/Waiting | Ditolak; bukan tiket valid |
| **G7** | Riwayat | Lihat tabel Check-in Terbaru | 10 check-in terakhir tampil dengan NIK tersamar |

**SQL verifikasi**
```sql
SELECT kode_tiket,status_checkin,waktu_checkin,id_petugas_gate FROM e_tickets WHERE kode_tiket='TKT-…';
SELECT aksi,entitas,keterangan,waktu FROM audit_logs WHERE aksi='CHECKIN' ORDER BY id_log DESC LIMIT 5;
```

---

## H. DASHBOARD EKSEKUTIF EO

| ID | Fungsi | Langkah | Hasil yang diharapkan |
|---|---|---|---|
| **H1** | KPI keuangan | Login admin → Dashboard EO | Omzet kotor, total diskon, pendapatan bersih, biaya layanan, jumlah transaksi Paid |
| **H2** | Rekap per event | Lihat tabel | Tiket terjual, kuota total, occupancy %, omzet, diskon, bersih per event |
| **H3** | Occupancy per tier | Lihat daftar tier | Bar proporsional sold/kuota; 0% tetap tampil |
| **H4** | Grafik harian | Lihat grafik 14 hari | Bar per tanggal; nilai tertinggi dianotasi; data kosong tidak error |
| **H5** | Filter event | Pilih satu event → Terapkan | Semua KPI/tabel/grafik berubah sesuai event |
| **H6** | Transaksi terbaru | Lihat daftar | 10 transaksi Paid terbaru dengan kode, pembeli, event, total, waktu |

**SQL pembanding**
```sql
SELECT SUM(subtotal) omzet,SUM(diskon) diskon,SUM(subtotal-diskon) bersih,
       SUM(biaya_layanan) fee,COUNT(*) trx
FROM transaksi WHERE status_bayar='Paid';
```

---

## I. CRUD MASTER DATA

| ID | Fungsi | Langkah | Hasil yang diharapkan |
|---|---|---|---|
| **I1** | Tambah event | Kelola Event → isi form → Simpan | Event muncul di katalog; audit `CREATE events` |
| **I2** | Edit event | Ubah judul/harga/jadwal → Simpan | Perubahan tampil di katalog/detail |
| **I3** | Hapus event tanpa transaksi | Hapus event baru | Berhasil; tier & kursi ikut terhapus (cascade) |
| **I4** | Hapus event bertransaksi | Hapus event yang punya transaksi | Ditolak dengan pesan jelas (FK) |
| **I5** | Tambah tier | Pilih event → isi nama/harga/kuota/batas jual | Tier tampil di detail; `kuota_tersedia = kuota_total` |
| **I6** | Edit kuota | Ubah kuota total 10 → 12 | `kuota_tersedia` bertambah 2; tidak pernah negatif |
| **I7** | Seat generator | Pilih tier Seated → prefix `QA`, jumlah 5 | 5 kursi `QA001…QA005`; duplikat dilewati; laporan jumlah dibuat |
| **I8** | Hapus kursi available | Hapus kursi tertentu / semua available | Kursi hilang dari seat map; kursi Sold/Locked tidak terhapus |
| **I9** | Voucher CRUD | Tambah voucher Persen 10% min 50rb kuota 5 | Voucher tampil; bisa dipakai di checkout |
| **I10** | Nonaktifkan voucher | Klik Nonaktifkan | `kuota_pakai=0`; checkout menolak “kuota habis” |
| **I11** | Hapus voucher | Hapus voucher tanpa transaksi | Berhasil; voucher bertransaksi ditolak |
| **I12** | Audit log | Buka Audit Log; filter aksi/entitas/tanggal | Aktivitas CREATE/UPDATE/VERIFY/CHECKIN tercatat, urut terbaru, pagination bekerja |

**SQL verifikasi**
```sql
SELECT * FROM events ORDER BY id_event DESC LIMIT 3;
SELECT * FROM tiket_tier WHERE id_event=…;
SELECT nomor_kursi,status_kursi FROM kursi_event WHERE id_tier=… ORDER BY nomor_kursi;
SELECT * FROM voucher_promo ORDER BY id_voucher DESC LIMIT 3;
SELECT aksi,entitas,keterangan,waktu FROM audit_logs ORDER BY id_log DESC LIMIT 10;
```

---

## J. KEAMANAN & UI

| ID | Fungsi | Langkah | Hasil yang diharapkan |
|---|---|---|---|
| **J1** | CSRF | POST checkout/login tanpa token (Postman/curl) | HTTP 403 “CSRF tidak valid” |
| **J2** | XSS | Registrasi nama `<script>alert(1)</script>`, buka dashboard | Teks ter-escape (`&lt;script&gt;`), tidak ada alert |
| **J3** | Path traversal | `bukti.php?kode=../../config.local.php` | 404/403 |
| **J4** | IDOR e-ticket | Akun A buka e-ticket akun B | 403/404 |
| **J5** | SQL injection | `' OR 1=1 --` di login/pencarian | Tidak bypass; halaman normal |
| **J6** | 10 tema | Ganti tema 01–10 di semua halaman utama | Tidak ada teks hilang; kontras cukup; tombol CTA tetap oranye |
| **J7** | Mobile 390px | DevTools responsive 390×844 | Tidak ada horizontal scroll; sidebar jadi tombol menu; form tetap bisa diisi |
| **J8** | Print e-ticket | Ctrl+P di halaman e-ticket | Hanya tiket; tanpa sidebar/tombol |
| **J9** | Reduced motion | Aktifkan “Reduce motion” di OS | Parallax/tilt/animasi berhenti; konten tetap tampil |

---

## K. UJI MANUAL TAMBAHAN (tidak tercakup otomatis)

| ID | Skenario | Langkah | Kriteria lulus |
|---|---|---|---|
| **M1** | Balapan kursi | Dua browser, akun berbeda, pilih kursi sama, submit hampir bersamaan | Hanya satu berhasil; yang kalah mendapat pesan kursi tidak tersedia; stok tetap konsisten |
| **M2** | Countdown nyata | Tunggu sampai <3 menit lalu 0 | Warna merah; di 0 halaman menolak upload dan status menjadi Expired setelah reload |
| **M3** | Sesi idle | Diamkan 30+ menit lalu klik menu | Diarahkan login ulang (session 1800 detik) |
| **M4** | Upload besar | File 3 MB | Ditolak, tidak ada file tersimpan |
| **M5** | MIME palsu | `gambar.jpg` yang isinya teks | Ditolak oleh finfo |
| **M6** | Back setelah logout | Logout → tombol Back browser | Tidak bisa membuka dashboard dari cache |
| **M7** | PDF di viewer | Buka PDF di Acrobat/Edge | Terbuka, teks tajam, barcode terbaca, tidak terpotong |
| **M8** | Barcode unik | Bandingkan 2 tiket | Pola berbeda; kode berbeda |
| **M9** | Voucher kuota | Habiskan kuota voucher (set 0) | Checkout menolak; tidak ada diskon |
| **M10** | Tier batas jual | Set `batas_jual` lampau | Detail menonaktifkan pembelian; API checkout menolak |
| **M11** | Event sold out | Set `kuota_tersedia=0` | Badge Sold Out; tombol beli nonaktif |
| **M12** | Akses langsung file | Buka `/src/Config/database.php`, `/database/schema.sql`, `/config.local.php` | 403 (rewrite) atau file tidak ditemukan |
| **M13** | Restore & reset | Jalankan installer ulang | Data kembali ke seed; tidak ada error |
| **M14** | Kompatibilitas Laragon | Jalankan build di Laragon (vhost/subfolder) | Semua fungsi sama; tidak ada path error |

---

## L. Perintah uji otomatis

```bash
# DB QA terpisah (aman, tidak menyentuh data demo)
cd C:\xampp\htdocs
php ..\pensi-v4\install.php --user=root --pass= --name=db_tiket_event_pro_qa

# server QA
set DB_NAME=db_tiket_event_pro_qa
set DB_USER=root
set DB_PASS=
php -S 127.0.0.1:8020 -t public

# jalankan suite (terminal lain)
cd C:\xampp\htdocs
set BASE_URL=http://127.0.0.1:8020
php tests\e2e_full_test.php
```

Hasil referensi: **54 PASS, 0 FAIL** — lihat `docs/E2E_RESULTS_2026-11-19.txt`.

---

## M. Format laporan hasil

```
Tanggal:
Tester:
Environment: XAMPP/Laragon, PHP ___, Browser ___
URL:

A1 PASS
A2 PASS
...
Total: __ PASS / __ FAIL

Bug:
- ID:
  Langkah:
  Expected:
  Actual:
  Screenshot:
  Severity: Blocker / Major / Minor
```

Kirim laporan + screenshot + potongan `apache/logs/error.log` (bila ada 500).

---

## N. Catatan perilaku yang disengaja

1. **Kuota & kursi** hanya dikembalikan saat Expired/Rejected — bukan saat Pending.
2. **E-ticket** tidak pernah terbit sebelum status Paid.
3. **Satu NIK** boleh muncul di event berbeda; batas 5 hanya per event.
4. **Nama & NIK** harus unik **dalam satu pesanan**, bukan lintas pesanan.
5. **Voucher** dipotong saat booking dan dikembalikan saat Expired/Rejected.
6. **Gate** menolak tiket yang belum Paid dan tiket yang sudah check-in.
7. **Theme** disimpan di cookie; `?theme=` menimpa cookie.
8. **Installer** menghapus database dengan nama yang sama — jangan jalankan di DB produksi.
