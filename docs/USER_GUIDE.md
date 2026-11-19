# PANDUAN PENGGUNA — PENSI EVENTPRO
**Aplikasi Pemesanan Tiket Event (Multi-Event & Seat/Quota Ticketing)**
Versi 4.2 · 19 November 2026 · Bahasa Indonesia

Selamat datang! Panduan ini menjelaskan cara memakai aplikasi langkah demi langkah,
mulai dari membuat akun, memilih tiket, mengunggah bukti transfer, sampai masuk ke
lokasi acara. Panduan dibagi per peran:

- **Peserta (Customer)** — bagian 4
- **Verifikator Keuangan (Finance)** — bagian 5
- **Petugas Gate** — bagian 6
- **Event Organizer (Admin EO)** — bagian 7

---

## 1. Tentang aplikasi

PENSI EVENTPRO adalah situs pemesanan tiket untuk beberapa acara sekaligus
(seminar, workshop, pameran, festival). Setiap acara punya beberapa kelas tiket
(**Early Bird / Reguler / VIP**), kuota, dan — untuk acara bertipe **Seated** —
peta kursi yang bisa dipilih langsung.

**Alamat aplikasi (lokal):** `http://127.0.0.1/`
**Akun demo (password: `password123`):**

| Peran | Email |
|---|---|
| Peserta | `dewi@student.id`, `rizki@alumni.id`, `maya@guest.id` |
| Event Organizer | `admin.eo@eventpro.id` |
| Verifikator Keuangan | `finance@eventpro.id` |
| Petugas Gate | `gate@eventpro.id` |

> Disarankan memakai akun peserta baru saat mencoba alur pembelian agar data demo
> tidak tercampur. Caranya ada di bagian 4.1.

---

## 2. Istilah status transaksi

| Status | Arti | Yang harus dilakukan |
|---|---|---|
| **Menunggu Bayar** | Pesanan dibuat, kursi dikunci, timer 15 menit berjalan | Segera transfer & unggah bukti |
| **Menunggu Verifikasi** | Bukti sudah diunggah, menunggu dicek panitia | Tunggu; pantau dashboard |
| **Lunas** | Pembayaran disetujui; e-ticket terbit | Unduh / cetak e-ticket |
| **Ditolak** | Bukti tidak valid; alasan tampil di detail | Buat pesanan baru dengan bukti benar |
| **Kedaluwarsa** | Lewat 15 menit tanpa bukti; kursi & kuota dikembalikan | Buat pesanan baru |

---

## 3. Mulai cepat (5 menit)

1. Buka `http://127.0.0.1/`.
2. Klik **Login & Registrasi** → tab **Daftar** → isi data → **Daftar & Masuk**.
3. Pilih event → **Lihat Detail** → pilih tier/kursi → **Lanjut Pemesanan**.
4. Isi nama & NIK tiap peserta → masukkan voucher (opsional) → **Lanjut ke Checkout**.
5. Dalam 15 menit: transfer sesuai total, unggah bukti → **Unggah Bukti**.
6. Tunggu panitia menyetujui (status **Lunas**).
7. Buka **Dashboard Saya → E-Ticket** → **Download PDF/JPG** atau **Cetak**.
8. Datang ke acara, tunjukkan e-ticket + KTP/NIK ke petugas gate.

---

## 4. Panduan Peserta (Customer)

### 4.1 Membuat akun

1. Buka halaman **Login & Registrasi**.
2. Klik tab **Daftar**.
3. Isi:
   - **Nama Lengkap** — sesuai KTP (boleh nama peserta lain? Tidak; ini akun Anda).
   - **NIK (16 digit)** — angka saja, unik, tidak boleh sama dengan akun lain.
   - **Email** — aktif dan belum terdaftar.
   - **Nomor HP** — 10–15 digit.
   - **Password** — minimal 8 karakter.
   - **Ulangi Password**.
4. Klik **Daftar & Masuk**. Anda langsung masuk ke dashboard peserta.

**Jika gagal:**
- “Email sudah terdaftar” → pakai email lain atau login.
- “NIK sudah terdaftar” → satu NIK hanya untuk satu akun.
- NIK kurang/lebih dari 16 digit → perbaiki.

### 4.2 Login

1. Buka **Login & Registrasi**.
2. Isi **Email atau NIK** (boleh salah satu) dan **Password**.
3. Klik **Masuk**.

Anda akan diarahkan otomatis:
- Peserta → **Dashboard Saya**
- Admin EO → **Dashboard EO**
- Finance → **Verifikasi Pembayaran**
- Gate → **Gate Check-in**

**Lupa password?** Saat ini belum ada reset mandiri. Hubungi admin EO untuk
dibuatkan password baru.

### 4.3 Mencari dan memilih event

Di halaman **Katalog Event**:

1. Gunakan kolom pencarian **“Cari judul, penyelenggara, atau lokasi”** —
   hasil menyaring saat Anda mengetik.
2. Filter tambahan:
   - **Kategori** — Seminar / Workshop / Pameran / Festival.
   - **Kota / Lokasi** — mis. `Bandung`.
   - **Tanggal Mulai Dari / Sampai** — rentang tanggal acara.
   - **Status** — Tersedia / Sold Out.
3. Klik **Terapkan Filter**; klik **Reset** untuk kembali.
4. Klik **Lihat Detail** pada kartu event.

Setiap kartu menampilkan kategori, tanggal, lokasi, tipe venue
(**Seated** / **Free Standing**), harga mulai, dan sisa tiket.

### 4.4 Detail event, rundown, dan memilih tiket

Halaman detail berisi:

- **Deskripsi** acara.
- **Rundown Acara** — jam, sesi, pembicara, panggung.
- **Pilih Tiket** — kartu per tier: harga, sisa kuota, batas jual.

**Acara Seated (berkursi):**
1. Pilih kursi pada grid. Warna kursi:
   - **Hijau** = tersedia
   - **Kuning** = sedang dikunci orang lain
   - **Abu-abu** = sudah terjual
   - **Oranye** = pilihan Anda
2. Maksimal **5 kursi** per transaksi. Penghitung di atas grid menunjukkan jumlah.
3. Klik **Lanjut Pemesanan**.

**Acara Free Standing (berdiri):**
1. Isi **Jumlah tiket** (1–5, sesuai sisa kuota).
2. Klik **Lanjut Pemesanan**.

> Jika belum login, tombol bertuliskan **Login untuk Memesan** — Anda bisa melihat
> kursi lebih dulu, tetapi harus login untuk melanjutkan.

### 4.5 Mengisi data peserta dan voucher

Halaman **Pemesanan**:

1. Untuk setiap lembar tiket, isi:
   - **Nama lengkap sesuai KTP**
   - **NIK 16 digit**
2. Aturan penting:
   - Nama **dan** NIK harus **berbeda** untuk setiap lembar dalam satu pesanan.
   - NIK tidak boleh sama dengan peserta lain di pesanan yang sama.
3. (Opsional) Masukkan **kode voucher** di kolom yang tersedia.
4. Periksa ringkasan harga di sisi kanan: harga × jumlah, biaya layanan
   Rp 2.500/tiket, estimasi total.
5. Klik **Lanjut ke Checkout →**.

**Voucher yang tersedia (contoh):**

| Kode | Potongan | Minimal transaksi |
|---|---|---|
| `HEMAT10` | 10% | Rp 100.000 |
| `POTONG25K` | Rp 25.000 | Rp 150.000 |
| `EARLYBIRD` | 15% | Rp 75.000 |
| `MAHASISWA20` | 20% | Rp 200.000 |

Voucher hanya berlaku jika: subtotal ≥ minimal, kuota voucher masih ada, dan
belum kedaluwarsa.

### 4.6 Checkout dan batas waktu 15 menit

Setelah menekan **Lanjut ke Checkout**, pesanan dibuat dan kursi langsung dikunci
untuk Anda.

Halaman checkout menampilkan:

- Kode booking (`BK-…`)
- Rincian: **subtotal**, **diskon voucher**, **biaya layanan**, **total bayar**
- **Hitung mundur 15 menit** (berubah merah di bawah 3 menit)
- Status **Menunggu Bayar**
- Form unggah bukti transfer

**Penting:** jika 15 menit terlewat tanpa bukti, pesanan otomatis **Kedaluwarsa**,
kursi dilepas, dan kuota kembali. Anda harus memesan ulang.

### 4.7 Cara mengunggah bukti transfer

1. Lakukan transfer **tepat sebesar total bayar** ke rekening panitia yang
   diinformasikan (contoh di aplikasi demo: BCA 1234567890 a.n. Panitia PENSI).
2. Simpan bukti transfer sebagai **JPG, PNG, atau WEBP**, ukuran **maksimal 2 MB**.
3. Di halaman checkout, klik **Bukti transfer (JPG/PNG/WEBP, maks 2MB)** →
   pilih file.
4. Klik **Unggah Bukti**.
5. Status berubah menjadi **Menunggu Verifikasi**.

**Yang menyebabkan unggahan gagal:**

| Pesan | Penyebab | Solusi |
|---|---|---|
| Format bukti harus JPG, PNG, atau WEBP | File PDF/HEIC/TXT | Konversi ke JPG/PNG |
| Ukuran file maksimal 2 MB | File terlalu besar | Kompres / screenshot ulang |
| Nama file tidak valid | Ekstensi ganda (`bukti.jpg.php`) | Ganti nama file |
| Waktu habis | Lewat 15 menit | Pesan ulang |

> Jangan mengunggah bukti palsu atau milik orang lain. Panitia memverifikasi
> nominal, tanggal, dan nama pengirim.

### 4.8 Memantau status pesanan

Buka **Dashboard Saya**:

- Kartu statistik: total pesanan, tiket lunas, menunggu bayar, total belanja.
- Tabel **Pesanan Saya**: kode booking, event, tanggal, total, status, aksi.
- Tombol **Detail** → rincian peserta, kursi, dan riwayat.
- Tombol **E-Ticket** → hanya aktif saat status **Lunas**.

Status berubah otomatis setelah finance memverifikasi. Muat ulang halaman untuk
melihat status terbaru.

### 4.9 Mengunduh dan mencetak e-ticket

1. Buka **Dashboard Saya** → pesanan **Lunas** → **E-Ticket**.
2. Setiap peserta memiliki satu kartu e-ticket berisi:
   - Nama & NIK peserta
   - Event, tanggal, lokasi
   - Tier & nomor kursi (atau *Free Standing*)
   - **Kode tiket unik** dan barcode
3. Pilihan:
   - **Download JPG** — gambar tiket untuk disimpan di HP.
   - **Download PDF** — file siap cetak.
   - **Cetak** — print langsung (hanya tiket yang tercetak).
4. Simpan file atau cetak. Bawa saat hari-H.

E-ticket **tidak terbit** sebelum status Lunas.

### 4.10 Hari-H: check-in di gate

1. Datang ke pintu masuk acara.
2. Tunjukkan **e-ticket** (HP atau cetak) dan **KTP/NIK**.
3. Petugas memasukkan **kode tiket** → **Periksa Tiket**.
4. Petugas meminta Anda menyebutkan/menunjukkan **NIK**; masukkan NIK.
5. Jika cocok dan belum pernah masuk → **YA, IZINKAN MASUK** → check-in berhasil.
6. Jika tiket sudah pernah dipakai → muncul peringatan merah
   **TIKET SUDAH DIGUNAKAN** beserta jam masuk sebelumnya.

Satu tiket hanya untuk satu orang dan satu kali masuk.

---

## 5. Panduan Verifikator Keuangan (Finance)

### 5.1 Membuka halaman verifikasi
Login dengan akun finance → otomatis ke **Verifikasi Pembayaran**.

Halaman menampilkan:
- Ringkasan: menunggu verifikasi, lunas hari ini, ditolak.
- Daftar transaksi **Menunggu Verifikasi** / **Pending**.
- Kode booking, pembeli, event, total, link bukti, waktu pesan, batas bayar.

### 5.2 Memeriksa bukti
1. Klik kode booking / **Lihat** untuk membuka detail.
2. Periksa:
   - **Nominal** sesuai total bayar.
   - **Tanggal/jam** transfer masih wajar.
   - **Nama pengirim** (bila terlihat).
   - Bukti bukan hasil edit/berulang.
3. Klik bukti untuk memperbesar.

### 5.3 Menyetujui pembayaran
1. Klik **Setujui (Paid)**.
2. Sistem otomatis:
   - Status → **Lunas**
   - Kursi **Locked → Sold**
   - Kuota terkunci berkurang
   - **E-ticket terbit** untuk setiap peserta
   - Audit log `VERIFY`
3. Peserta dapat mengunduh e-ticket.

### 5.4 Menolak pembayaran
1. Klik **Tolak (Rejected)**.
2. Isi **catatan penolakan** (wajib, minimal 5 karakter), mis.
   “Nominal transfer tidak sesuai total bayar.”
3. Klik **Konfirmasi Tolak**.
4. Sistem mengembalikan kursi & kuota, serta mengembalikan kuota voucher.
5. Peserta melihat alasan di detail pesanan dan dapat memesan ulang.

> Jangan menolak tanpa catatan — tombol tidak akan memproses.

---

## 6. Panduan Petugas Gate

1. Login dengan akun gate → halaman **Gate Check-in**.
2. Masukkan **Kode E-Ticket** (contoh `TKT-20261119-XXXXXXXXXX-1`).
3. Klik **Periksa Tiket**.
4. Layar menampilkan:
   - Nama peserta (NIK disamarkan)
   - Event, tier, kursi
   - Status check-in
5. Minta peserta menunjukkan KTP, lalu masukkan **NIK peserta**.
6. Klik **YA, IZINKAN MASUK**.
   - Berhasil → kartu hijau, waktu & nama petugas tercatat.
   - NIK salah → ditolak.
   - Sudah pernah masuk → peringatan merah **TIKET SUDAH DIGUNAKAN** + jam masuk + petugas sebelumnya.
   - Kode tidak ada / belum lunas → **TIKET TIDAK DITEMUKAN**.
7. Tabel **Check-in Terbaru** menampilkan 10 check-in terakhir.

---

## 7. Panduan Event Organizer (Admin EO)

Login admin → **Dashboard EO**. Sidebar kiri berisi:
**Katalog Event · Dashboard EO · Kelola Event & Tier · Kelola Voucher · Audit Log**.

### 7.1 Dashboard EO
Menampilkan analitik transaksi **Lunas**:
- **Omzet kotor** (subtotal), **total potongan promo**, **pendapatan bersih**,
  **biaya layanan**, dan jumlah transaksi.
- **Rekapitulasi per event**: tiket terjual, kuota, occupancy %, omzet, diskon, bersih.
- **Occupancy per tier** dalam bentuk bar.
- **Grafik penjualan 14 hari terakhir**.
- **Transaksi lunas terbaru**.

Gunakan filter **Event** di atas untuk melihat angka per acara.

### 7.2 Mengelola event
Menu **Kelola Event & Tier** → tab **Kelola Event**.

**Tambah event:**
1. Isi **Judul**, **Kategori**, **Penyelenggara**, **Lokasi**.
2. Pilih **Tipe Venue** (Seated / Free Standing).
3. Isi **Tanggal Mulai & Selesai**.
4. Pilih **Status** = Published agar tampil di katalog.
5. Klik **Simpan**.

**Ubah/Hapus:** gunakan tombol **Ubah** / **Hapus** pada daftar.
Event yang sudah memiliki transaksi tidak dapat dihapus (pesan akan muncul).

### 7.3 Mengelola tier tiket
Tab **Kelola Tier**:
1. Pilih event.
2. Isi **Nama Tier**, **Harga**, **Kuota Total**, **Batas Jual**.
3. Klik **Simpan**.
4. Mengubah kuota total akan menyesuaikan **kuota tersedia** secara otomatis.

### 7.4 Generator kursi
Tab **Kelola Kursi** (khusus event Seated):
1. Pilih event & tier.
2. Isi **Prefix** (mis. `A`) dan **Jumlah** (1–1000).
3. Klik **Generate** → kursi `A001…A0xx` dibuat; nomor duplikat dilewati.
4. Kursi yang masih **Available** bisa dihapus; kursi Sold/Locked tidak.

### 7.5 Mengelola voucher
Menu **Kelola Voucher**:
1. Isi **Kode**, **Tipe Potongan** (Persen/Nominal), **Nilai**, **Minimal
   Transaksi**, **Kuota Pakai**, **Berlaku Sampai**.
2. Klik **Simpan**.
3. **Nonaktifkan** mengubah kuota menjadi 0; **Hapus** hanya untuk voucher tanpa transaksi.

### 7.6 Audit log
Menu **Audit Log** menampilkan aktivitas sensitif: CREATE/UPDATE/DELETE,
VERIFY, REJECT, CHECKIN, LOGIN, EXPIRE. Filter berdasarkan aksi, entitas,
rentang tanggal, atau kata kunci.

---

## 8. Tampilan & tema

- Tombol tema di **kanan bawah** membuka 10 pilihan:
  01–05 gelap, 06–10 terang.
- Bisa juga lewat URL: `?theme=01` … `?theme=10`.
- Pilihan tersimpan di browser (cookie).
- Aplikasi responsif: di layar kecil sidebar berubah menjadi tombol menu.

---

## 9. Aturan penting

1. **15 menit** untuk menyelesaikan pembayaran setelah pesanan dibuat.
2. Maksimal **5 tiket per event per akun/NIK** (akumulasi semua transaksi).
3. **Nama dan NIK** setiap lembar tiket dalam satu pesanan harus berbeda.
4. **Biaya layanan** Rp 2.500 per tiket.
5. **Voucher** hanya berlaku jika minimal transaksi, kuota, dan masa berlaku terpenuhi.
6. **E-ticket** hanya terbit setelah status **Lunas**.
7. **Satu tiket = satu orang = satu kali masuk** (anti-passback).
8. Kursi yang sedang dikunci peserta lain tidak dapat dipilih.

---

## 10. Pertanyaan umum (FAQ)

**Q: Kenapa tombol E-Ticket belum muncul?**
A: E-ticket hanya muncul saat status **Lunas**. Jika masih **Menunggu Verifikasi**,
tunggu finance menyetujui.

**Q: Bukti sudah diunggah, kok status belum berubah?**
A: Status menjadi **Menunggu Verifikasi**; finance memeriksa secara manual. Pantau
dashboard dan muat ulang halaman.

**Q: Kursi yang saya pilih berubah jadi kuning/abu-abu?**
A: Kuning = sedang dikunci peserta lain; abu-abu = sudah terjual. Pilih kursi lain.

**Q: Voucher saya ditolak, padahal kodenya benar.**
A: Periksa subtotal ≥ minimal transaksi, kuota voucher masih ada, dan tanggal
belum kedaluwarsa.

**Q: Pesanan saya hilang / kedaluwarsa.**
A: Timer 15 menit habis. Kursi dan kuota sudah dilepas; silakan pesan ulang.

**Q: File bukti saya ditolak terus.**
A: Pastikan JPG/PNG/WEBP, ≤ 2 MB, bukan PDF/HEIC, dan nama file tidak memiliki
ekstensi ganda.

**Q: Saya lupa password.**
A: Hubungi admin EO; belum ada reset mandiri.

**Q: Apakah bisa memesan untuk teman?**
A: Bisa. Isi nama & NIK teman pada baris peserta; setiap lembar harus berbeda.
Total maksimal 5 tiket per event per akun.

**Q: Apakah e-ticket bisa di-screenshot?**
A: Bisa, tetapi PDF/JPG resmi lebih aman karena memuat barcode dan kode unik.

**Q: Apa yang terjadi jika tiket dipakai dua kali?**
A: Gate menolak dengan peringatan merah dan menampilkan jam masuk pertama.

---

## 11. Glosarium

| Istilah | Arti |
|---|---|
| **Tier** | Kelas tiket (Early Bird, Reguler, VIP) |
| **Seated** | Acara dengan nomor kursi |
| **Free Standing** | Acara tanpa kursi (berdiri) |
| **Kuota tersedia** | Tiket yang masih bisa dibeli |
| **Kuota terkunci** | Tiket yang ditahan sementara untuk pesanan aktif |
| **Identity binding** | Nama & NIK melekat pada setiap lembar tiket |
| **Anti-passback** | Aturan satu tiket hanya boleh masuk sekali |
| **Bukti transfer** | Gambar/foto transfer bank yang diunggah peserta |
| **E-ticket** | Tiket digital berisi kode unik & barcode |

---

## 12. Bantuan

- Panduan teknis & instalasi: `README.md`
- Panduan pengujian lengkap: `docs/MANUAL_TESTING_GUIDE.md`
- Masalah teknis: lihat `docs/TROUBLESHOOTING.md`
- Laporkan bug: gunakan `docs/FEEDBACK_TEMPLATE.md`

Selamat menikmati acara!
