-- ==========================================================
-- BASIS DATA: db_pensi_sekolah
-- PAKET 7: Aplikasi Pemesanan Tiket Event / Pentas Seni (Pensi) Sekolah
-- Tingkat Kompleksitas: Lebih Kompleks (Tingkat Lanjut)
-- ==========================================================

CREATE DATABASE IF NOT EXISTS `db_pensi_sekolah`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `db_pensi_sekolah`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `detail_tiket_satuan`;
DROP TABLE IF EXISTS `pemesanan`;
DROP TABLE IF EXISTS `kode_promo`;
DROP TABLE IF EXISTS `jenis_tiket`;
DROP TABLE IF EXISTS `rundown_acara`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------
-- 1. Tabel users (Multi-Role: pembeli, panitia_keuangan, petugas_gate, admin_osis)
-- tipe_pengguna: 'Internal' (siswa sekolah, validasi NIS) atau 'Eksternal' (alumni/tamu, validasi NIK)
-- nis_atau_nik: UNIQUE, 10-20 digit (NIS internal / NIK KTP eksternal)
-- ----------------------------------------------------------
CREATE TABLE `users` (
  `id_user` INT(11) NOT NULL AUTO_INCREMENT,
  `nama` VARCHAR(100) NOT NULL,
  `tipe_pengguna` ENUM('Internal', 'Eksternal') NOT NULL,
  `nis_atau_nik` VARCHAR(20) NOT NULL,
  `kelas_atau_instansi` VARCHAR(100) NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('pembeli', 'panitia_keuangan', 'petugas_gate', 'admin_osis') NOT NULL DEFAULT 'pembeli',
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `uq_nis_nik` (`nis_atau_nik`),
  UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password semua akun uji coba di bawah: password123 (bcrypt)
INSERT INTO `users` (`id_user`, `nama`, `tipe_pengguna`, `nis_atau_nik`, `kelas_atau_instansi`, `username`, `password`, `role`) VALUES
(1, 'Budi Santoso (Admin OSIS)', 'Internal', '1234567890', 'OSIS Pusat', 'admin_osis', '$2y$10$f4fPuHsZtLAHobWJKlf/W.CiOu5celsNCFVvjEVF7RJ1TWNWcKWjm', 'admin_osis'),
(2, 'Siti Rahayu (Panitia Keuangan)', 'Internal', '1234567891', 'Kelas 12 IPA 1', 'panitia_keu', '$2y$10$f4fPuHsZtLAHobWJKlf/W.CiOu5celsNCFVvjEVF7RJ1TWNWcKWjm', 'panitia_keuangan'),
(3, 'Ahmad Fauzi (Petugas Gate)', 'Internal', '1234567892', 'Kelas 11 IPS 2', 'petugas_gate', '$2y$10$f4fPuHsZtLAHobWJKlf/W.CiOu5celsNCFVvjEVF7RJ1TWNWcKWjm', 'petugas_gate'),
(4, 'Dewi Lestari (Siswa Internal)', 'Internal', '2024001', 'Kelas 10 IPA 1', 'dewi_siswa', '$2y$10$f4fPuHsZtLAHobWJKlf/W.CiOu5celsNCFVvjEVF7RJ1TWNWcKWjm', 'pembeli'),
(5, 'Rizki Pratama (Alumni Eksternal)', 'Eksternal', '3273011005990001', 'Universitas Indonesia', 'rizki_alumni', '$2y$10$f4fPuHsZtLAHobWJKlf/W.CiOu5celsNCFVvjEVF7RJ1TWNWcKWjm', 'pembeli'),
(6, 'Maya Sari (Tamu Umum)', 'Eksternal', '3273021808000002', 'PT. Maju Jaya', 'maya_tamu', '$2y$10$f4fPuHsZtLAHobWJKlf/W.CiOu5celsNCFVvjEVF7RJ1TWNWcKWjm', 'pembeli');

-- ----------------------------------------------------------
-- 2. Tabel rundown_acara (Jadwal Sesi & Penampilan)
-- kategori_penampil: 'Guest Star', 'Ekskul', 'Band Siswa'
-- ----------------------------------------------------------
CREATE TABLE `rundown_acara` (
  `id_rundown` INT(11) NOT NULL AUTO_INCREMENT,
  `nama_penampil` VARCHAR(120) NOT NULL,
  `kategori_penampil` ENUM('Guest Star', 'Ekskul', 'Band Siswa') NOT NULL,
  `jam_mulai` TIME NOT NULL,
  `jam_selesai` TIME NOT NULL,
  `lokasi_panggung` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id_rundown`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `rundown_acara` (`id_rundown`, `nama_penampil`, `kategori_penampil`, `jam_mulai`, `jam_selesai`, `lokasi_panggung`) VALUES
(1, 'Bintang Tamu: Rendy Pandugo', 'Guest Star', '18:30:00', '19:15:00', 'Panggung Utama'),
(2, 'Ekskul Tari Tradisional', 'Ekskul', '17:00:00', '17:30:00', 'Panggung Utama'),
(3, 'Ekskul Paduan Suara', 'Ekskul', '17:30:00', '18:00:00', 'Panggung Utama'),
(4, 'Band Siswa: The Schoolboys', 'Band Siswa', '19:15:00', '20:00:00', 'Panggung Utama'),
(5, 'Ekskul Teater: Monolog Cinta', 'Ekskul', '20:00:00', '20:30:00', 'Panggung Samping'),
(6, 'Stand-up Comedy: Pak Guru', 'Ekskul', '20:30:00', '21:00:00', 'Panggung Samping');

-- ----------------------------------------------------------
-- 3. Tabel jenis_tiket (Kategori Tiket dengan Segmentasi Pembeli)
-- target_pembeli: 'Internal', 'Eksternal', 'Semua'
-- tipe_tiket: 'Seated' (bernomor kursi) atau 'Free Standing'
-- ----------------------------------------------------------
CREATE TABLE `jenis_tiket` (
  `id_jenis` INT(11) NOT NULL AUTO_INCREMENT,
  `nama_jenis` VARCHAR(80) NOT NULL,
  `target_pembeli` ENUM('Internal', 'Eksternal', 'Semua') NOT NULL DEFAULT 'Semua',
  `tipe_tiket` ENUM('Seated', 'Free Standing') NOT NULL DEFAULT 'Free Standing',
  `harga` INT(11) NOT NULL,
  `stok_tiket` INT(11) NOT NULL,
  `stok_tersedia` INT(11) NOT NULL,
  `stok_tertahan` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_jenis`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `jenis_tiket` (`id_jenis`, `nama_jenis`, `target_pembeli`, `tipe_tiket`, `harga`, `stok_tiket`, `stok_tersedia`, `stok_tertahan`) VALUES
(1, 'VIP Internal (Presale)', 'Internal', 'Seated', 75000, 30, 30, 0),
(2, 'Reguler Internal', 'Internal', 'Seated', 50000, 100, 100, 0),
(3, 'VIP Eksternal', 'Eksternal', 'Seated', 100000, 20, 20, 0),
(4, 'Reguler Eksternal', 'Eksternal', 'Free Standing', 60000, 80, 80, 0),
(5, 'OTS (On The Spot) - Semua', 'Semua', 'Free Standing', 80000, 50, 50, 0);

-- ----------------------------------------------------------
-- 4. Tabel kursi_pensi (Nomor kursi untuk jenis tiket bertipe Seated)
-- ----------------------------------------------------------
CREATE TABLE `kursi_pensi` (
  `id_kursi` INT(11) NOT NULL AUTO_INCREMENT,
  `id_jenis` INT(11) NOT NULL,
  `nomor_kursi` VARCHAR(20) NOT NULL,
  `status_kursi` ENUM('Available', 'Locked', 'Sold') NOT NULL DEFAULT 'Available',
  PRIMARY KEY (`id_kursi`),
  UNIQUE KEY `uq_jenis_kursi` (`id_jenis`, `nomor_kursi`),
  CONSTRAINT `fk_kursi_jenis`
    FOREIGN KEY (`id_jenis`) REFERENCES `jenis_tiket` (`id_jenis`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kursi untuk Jenis 1: VIP Internal (VIP-01 s/d VIP-30)
INSERT INTO `kursi_pensi` (`id_kursi`, `id_jenis`, `nomor_kursi`, `status_kursi`)
SELECT NULL, 1, CONCAT('VIP-', LPAD(n, 2, '0')), 'Available'
FROM (
  SELECT 1 AS n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION
  SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION
  SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15 UNION
  SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20 UNION
  SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24 UNION SELECT 25 UNION
  SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29 UNION SELECT 30
) AS numbers;

-- Kursi untuk Jenis 2: Reguler Internal (REG-01 s/d REG-100)
INSERT INTO `kursi_pensi` (`id_kursi`, `id_jenis`, `nomor_kursi`, `status_kursi`)
SELECT NULL, 2, CONCAT('REG-', LPAD(n, 3, '0')), 'Available'
FROM (
  SELECT 1 AS n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION
  SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION
  SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15 UNION
  SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20 UNION
  SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24 UNION SELECT 25 UNION
  SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29 UNION SELECT 30 UNION
  SELECT 31 UNION SELECT 32 UNION SELECT 33 UNION SELECT 34 UNION SELECT 35 UNION
  SELECT 36 UNION SELECT 37 UNION SELECT 38 UNION SELECT 39 UNION SELECT 40 UNION
  SELECT 41 UNION SELECT 42 UNION SELECT 43 UNION SELECT 44 UNION SELECT 45 UNION
  SELECT 46 UNION SELECT 47 UNION SELECT 48 UNION SELECT 49 UNION SELECT 50 UNION
  SELECT 51 UNION SELECT 52 UNION SELECT 53 UNION SELECT 54 UNION SELECT 55 UNION
  SELECT 56 UNION SELECT 57 UNION SELECT 58 UNION SELECT 59 UNION SELECT 60 UNION
  SELECT 61 UNION SELECT 62 UNION SELECT 63 UNION SELECT 64 UNION SELECT 65 UNION
  SELECT 66 UNION SELECT 67 UNION SELECT 68 UNION SELECT 69 UNION SELECT 70 UNION
  SELECT 71 UNION SELECT 72 UNION SELECT 73 UNION SELECT 74 UNION SELECT 75 UNION
  SELECT 76 UNION SELECT 77 UNION SELECT 78 UNION SELECT 79 UNION SELECT 80 UNION
  SELECT 81 UNION SELECT 82 UNION SELECT 83 UNION SELECT 84 UNION SELECT 85 UNION
  SELECT 86 UNION SELECT 87 UNION SELECT 88 UNION SELECT 89 UNION SELECT 90 UNION
  SELECT 91 UNION SELECT 92 UNION SELECT 93 UNION SELECT 94 UNION SELECT 95 UNION
  SELECT 96 UNION SELECT 97 UNION SELECT 98 UNION SELECT 99 UNION SELECT 100
) AS numbers;

-- Kursi untuk Jenis 3: VIP Eksternal (VIP-E-01 s/d VIP-E-20)
INSERT INTO `kursi_pensi` (`id_kursi`, `id_jenis`, `nomor_kursi`, `status_kursi`)
SELECT NULL, 3, CONCAT('VIP-E-', LPAD(n, 2, '0')), 'Available'
FROM (
  SELECT 1 AS n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION
  SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION
  SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15 UNION
  SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20
) AS numbers;

-- ----------------------------------------------------------
-- 5. Tabel kode_promo (Kode Voucher Diskon Nominal)
-- ----------------------------------------------------------
CREATE TABLE `kode_promo` (
  `id_promo` INT(11) NOT NULL AUTO_INCREMENT,
  `kode_voucher` VARCHAR(40) NOT NULL,
  `potongan_nominal` INT(11) NOT NULL,
  `kuota_voucher` INT(11) NOT NULL,
  `berlaku_sampai` DATE NOT NULL,
  PRIMARY KEY (`id_promo`),
  UNIQUE KEY `uq_kode_voucher` (`kode_voucher`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `kode_promo` (`id_promo`, `kode_voucher`, `potongan_nominal`, `kuota_voucher`, `berlaku_sampai`) VALUES
(1, 'PENSI10K', 10000, 50, DATE_ADD(CURDATE(), INTERVAL 30 DAY)),
(2, 'OSIS20K', 20000, 30, DATE_ADD(CURDATE(), INTERVAL 30 DAY)),
(3, 'ALUMNI15K', 15000, 20, DATE_ADD(CURDATE(), INTERVAL 30 DAY)),
(4, 'EARLYBIRD25K', 25000, 15, DATE_ADD(CURDATE(), INTERVAL 15 DAY));

-- ----------------------------------------------------------
-- 6. Tabel pemesanan (Transaksi Pembelian Tiket)
-- status_bayar: 'Menunggu Bayar', 'Menunggu Verifikasi', 'Lunas', 'Kadaluarsa', 'Ditolak'
-- ----------------------------------------------------------
CREATE TABLE `pemesanan` (
  `id_pesan` INT(11) NOT NULL AUTO_INCREMENT,
  `kode_transaksi` VARCHAR(40) NOT NULL,
  `id_user` INT(11) NOT NULL,
  `id_jenis` INT(11) NOT NULL,
  `id_promo` INT(11) DEFAULT NULL,
  `jumlah` INT(11) NOT NULL,
  `diskon` INT(11) NOT NULL DEFAULT 0,
  `total_harga` INT(11) NOT NULL,
  `bukti_bayar` VARCHAR(255) DEFAULT NULL,
  `batas_waktu_bayar` DATETIME NOT NULL,
  `status_bayar` ENUM('Menunggu Bayar', 'Menunggu Verifikasi', 'Lunas', 'Kadaluarsa', 'Ditolak') NOT NULL DEFAULT 'Menunggu Bayar',
  `catatan_penolakan` VARCHAR(255) DEFAULT NULL,
  `tgl_pesan` DATETIME NOT NULL,
  PRIMARY KEY (`id_pesan`),
  UNIQUE KEY `uq_kode_transaksi` (`kode_transaksi`),
  KEY `idx_pesan_user` (`id_user`),
  KEY `idx_pesan_jenis` (`id_jenis`),
  KEY `idx_pesan_status` (`status_bayar`),
  CONSTRAINT `fk_pesan_user`
    FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_pesan_jenis`
    FOREIGN KEY (`id_jenis`) REFERENCES `jenis_tiket` (`id_jenis`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_pesan_promo`
    FOREIGN KEY (`id_promo`) REFERENCES `kode_promo` (`id_promo`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 7. Tabel detail_tiket_satuan (E-Ticket per individu, 1-to-Many)
-- status_checkin: 'Belum Masuk', 'Sudah Masuk'
-- ----------------------------------------------------------
CREATE TABLE `detail_tiket_satuan` (
  `id_detail_tiket` INT(11) NOT NULL AUTO_INCREMENT,
  `id_pesan` INT(11) NOT NULL,
  `kode_tiket_unik` VARCHAR(50) NOT NULL,
  `nama_pemegang_tiket` VARCHAR(100) NOT NULL,
  `nik_pemegang_tiket` VARCHAR(20) NOT NULL,
  `id_kursi` INT(11) DEFAULT NULL,
  `status_checkin` ENUM('Belum Masuk', 'Sudah Masuk') NOT NULL DEFAULT 'Belum Masuk',
  `waktu_checkin` DATETIME DEFAULT NULL,
  `id_petugas_gate` INT(11) DEFAULT NULL,
  PRIMARY KEY (`id_detail_tiket`),
  UNIQUE KEY `uq_kode_tiket_unik` (`kode_tiket_unik`),
  KEY `idx_detail_pesan` (`id_pesan`),
  KEY `idx_detail_kursi` (`id_kursi`),
  KEY `idx_detail_gate` (`id_petugas_gate`),
  CONSTRAINT `fk_detail_pesan`
    FOREIGN KEY (`id_pesan`) REFERENCES `pemesanan` (`id_pesan`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_detail_kursi`
    FOREIGN KEY (`id_kursi`) REFERENCES `kursi_pensi` (`id_kursi`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_detail_gate`
    FOREIGN KEY (`id_petugas_gate`) REFERENCES `users` (`id_user`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 8. Tabel audit_logs (Jejak Audit Perubahan Data Sensitif)
-- ----------------------------------------------------------
CREATE TABLE `audit_logs` (
  `id_log` INT(11) NOT NULL AUTO_INCREMENT,
  `id_user` INT(11) NOT NULL,
  `aktivitas` VARCHAR(100) NOT NULL,
  `tabel_terkait` VARCHAR(60) NOT NULL,
  `detail_perubahan` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id_log`),
  KEY `idx_log_user` (`id_user`),
  KEY `idx_log_waktu` (`created_at`),
  CONSTRAINT `fk_log_user`
    FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- SELESAI
-- ==========================================================