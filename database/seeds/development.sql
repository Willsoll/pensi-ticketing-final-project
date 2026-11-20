-- ============================================================
-- PAKET 7 — Development Seed Data (Matches ROMBEL 3 schema)
-- Run AFTER schema.sql
-- Database: db_tiket_event_pro
-- ============================================================

USE `db_tiket_event_pro`;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- USERS (Password: password123 for all)
-- Hash: $2y$12$r/eqX6DA7yTywpcnnnNlZeN5gVTkP8iyB6Wpr.vN18sAx2FP2RGvO
-- Schema: id_user, nik, nama_lengkap, email, no_hp, password, role
-- ============================================================
INSERT INTO `users` (`nik`, `nama_lengkap`, `email`, `no_hp`, `password`, `role`) VALUES
('1234567890123456', 'Budi Santoso (Admin OSIS)', 'admin@pensi.school', '081234567890', '$2y$12$r/eqX6DA7yTywpcnnnNlZeN5gVTkP8iyB6Wpr.vN18sAx2FP2RGvO', 'admin_eo'),
('1234567890123457', 'Siti Rahayu (Finance)', 'finance@pensi.school', '081234567891', '$2y$12$r/eqX6DA7yTywpcnnnNlZeN5gVTkP8iyB6Wpr.vN18sAx2FP2RGvO', 'finance'),
('1234567890123458', 'Ahmad Fauzi (Gate Officer)', 'gate@pensi.school', '081234567892', '$2y$12$r/eqX6DA7yTywpcnnnNlZeN5gVTkP8iyB6Wpr.vN18sAx2FP2RGvO', 'gate_officer'),
('3273011005990001', 'Dewi Lestari (Siswa Internal)', 'dewi@student.school', '081234567893', '$2y$12$r/eqX6DA7yTywpcnnnNlZeN5gVTkP8iyB6Wpr.vN18sAx2FP2RGvO', 'customer'),
('3273011005990002', 'Rizki Pratama (Alumni Eksternal)', 'rizki@alumni.id', '081234567894', '$2y$12$r/eqX6DA7yTywpcnnnNlZeN5gVTkP8iyB6Wpr.vN18sAx2FP2RGvO', 'customer'),
('3273021808000002', 'Maya Sari (Tamu Umum)', 'maya@tamu.com', '081234567895', '$2y$12$r/eqX6DA7yTywpcnnnNlZeN5gVTkP8iyB6Wpr.vN18sAx2FP2RGvO', 'customer');

-- ============================================================
-- KATEGORI EVENT
-- ============================================================
INSERT INTO `kategori_event` (`nama_kategori`) VALUES
('Pentas Seni'),
('Seminar'),
('Workshop'),
('Festival');

-- ============================================================
-- EVENTS
-- ============================================================
INSERT INTO `events` (`id_kat_event`, `judul_event`, `penyelenggara`, `lokasi`, `tipe_venue`, `tgl_mulai`, `tgl_selesai`, `banner`, `status_event`) VALUES
(1, 'PENSI SCHOOL 2026 - Pentas Seni Tahunan', 'OSIS Sekolah Terpadu Pahoa', 'Aula Serbaguna Sekolah Terpadu Pahoa, Jl. Raya Serpong', 'Seated', DATE_ADD(NOW(), INTERVAL 30 DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL 30 DAY), INTERVAL 6 HOUR), NULL, 'Available');

-- ============================================================
-- TIKET TIER
-- ============================================================
INSERT INTO `tiket_tier` (`id_event`, `nama_tier`, `harga`, `kuota_total`, `kuota_tersedia`, `kuota_locked`, `batas_jual`) VALUES
(1, 'VIP Internal (Presale)', 75000, 50, 50, 0, DATE_ADD(NOW(), INTERVAL 25 DAY)),
(1, 'Reguler Internal', 50000, 150, 150, 0, DATE_ADD(NOW(), INTERVAL 28 DAY)),
(1, 'VIP Eksternal', 100000, 30, 30, 0, DATE_ADD(NOW(), INTERVAL 25 DAY)),
(1, 'Reguler Eksternal', 60000, 100, 100, 0, DATE_ADD(NOW(), INTERVAL 28 DAY)),
(1, 'OTS (On The Spot) - Semua', 80000, 50, 50, 0, DATE_ADD(NOW(), INTERVAL 29 DAY));

-- ============================================================
-- KURSI EVENT (Auto-generated for Seated tiers)
-- VIP Internal (tier 1): VIP-01 to VIP-50
-- Reguler Internal (tier 2): REG-001 to REG-150
-- VIP Eksternal (tier 3): VIP-E-01 to VIP-E-30
-- ============================================================
-- VIP Internal (tier 1): VIP-01 to VIP-50
INSERT INTO `kursi_event` (`id_tier`, `nomor_kursi`, `status_kursi`)
SELECT 1, CONCAT('VIP-', LPAD(n, 2, '0')), 'Available'
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
    SELECT 46 UNION SELECT 47 UNION SELECT 48 UNION SELECT 49 UNION SELECT 50
) AS numbers;

-- Reguler Internal (tier 2): REG-001 to REG-150
INSERT INTO `kursi_event` (`id_tier`, `nomor_kursi`, `status_kursi`)
SELECT 2, CONCAT('REG-', LPAD(n, 3, '0')), 'Available'
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
    SELECT 96 UNION SELECT 97 UNION SELECT 98 UNION SELECT 99 UNION SELECT 100 UNION
    SELECT 101 UNION SELECT 102 UNION SELECT 103 UNION SELECT 104 UNION SELECT 105 UNION
    SELECT 106 UNION SELECT 107 UNION SELECT 108 UNION SELECT 109 UNION SELECT 110 UNION
    SELECT 111 UNION SELECT 112 UNION SELECT 113 UNION SELECT 114 UNION SELECT 115 UNION
    SELECT 116 UNION SELECT 117 UNION SELECT 118 UNION SELECT 119 UNION SELECT 120 UNION
    SELECT 121 UNION SELECT 122 UNION SELECT 123 UNION SELECT 124 UNION SELECT 125 UNION
    SELECT 126 UNION SELECT 127 UNION SELECT 128 UNION SELECT 129 UNION SELECT 130 UNION
    SELECT 131 UNION SELECT 132 UNION SELECT 133 UNION SELECT 134 UNION SELECT 135 UNION
    SELECT 136 UNION SELECT 137 UNION SELECT 138 UNION SELECT 139 UNION SELECT 140 UNION
    SELECT 141 UNION SELECT 142 UNION SELECT 143 UNION SELECT 144 UNION SELECT 145 UNION
    SELECT 146 UNION SELECT 147 UNION SELECT 148 UNION SELECT 149 UNION SELECT 150
) AS numbers;

-- VIP Eksternal (tier 3): VIP-E-01 to VIP-E-30
INSERT INTO `kursi_event` (`id_tier`, `nomor_kursi`, `status_kursi`)
SELECT 3, CONCAT('VIP-E-', LPAD(n, 2, '0')), 'Available'
FROM (
    SELECT 1 AS n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION
    SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION
    SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15 UNION
    SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20 UNION
    SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24 UNION SELECT 25 UNION
    SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29 UNION SELECT 30
) AS numbers;

-- ============================================================
-- VOUCHER PROMO
-- ============================================================
INSERT INTO `voucher_promo` (`kode_voucher`, `tipe_potongan`, `nilai_potongan`, `min_transaksi`, `kuota_pakai`, `expired_date`) VALUES
('PENSI10K', 'Nominal', 10000, 50000, 100, DATE_ADD(CURDATE(), INTERVAL 30 DAY)),
('OSIS20K', 'Nominal', 20000, 100000, 50, DATE_ADD(CURDATE(), INTERVAL 30 DAY)),
('ALUMNI15K', 'Nominal', 15000, 75000, 75, DATE_ADD(CURDATE(), INTERVAL 30 DAY)),
('EARLYBIRD25K', 'Nominal', 25000, 100000, 25, DATE_ADD(CURDATE(), INTERVAL 15 DAY));

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- END OF SEEDS
-- ============================================================