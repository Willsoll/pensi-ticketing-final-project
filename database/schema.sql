-- ============================================================
-- PAKET 7 — Multi-Event Ticketing Platform Database Schema
-- MySQL / MariaDB (Exact match to ROMBEL 3.md db_tiket_event_pro)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `db_tiket_event_pro`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `db_tiket_event_pro`;

SET FOREIGN_KEY_CHECKS = 0;

-- Drop tables in reverse dependency order
DROP TABLE IF EXISTS `voucher_redemptions`;
DROP TABLE IF EXISTS `reservation_items`;
DROP TABLE IF EXISTS `reservations`;
DROP TABLE IF EXISTS `e_tickets`;
DROP TABLE IF EXISTS `transaksi`;
DROP TABLE IF EXISTS `voucher_promo`;
DROP TABLE IF EXISTS `kursi_event`;
DROP TABLE IF EXISTS `tiket_tier`;
DROP TABLE IF EXISTS `events`;
DROP TABLE IF EXISTS `kategori_event`;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 1. USERS (Exact match to ROMBEL 3.md)
-- role: customer, admin_eo, finance, gate_officer
-- nik: UNIQUE (16 digits for NIK)
-- ============================================================
CREATE TABLE `users` (
    `id_user`       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nik`           VARCHAR(16)     NOT NULL,
    `nama_lengkap`  VARCHAR(150)    NOT NULL,
    `email`         VARCHAR(255)    NOT NULL,
    `no_hp`         VARCHAR(20)     NOT NULL,
    `password`      VARCHAR(255)    NOT NULL,
    `role`          ENUM('customer','admin_eo','finance','gate_officer') NOT NULL DEFAULT 'customer',
    PRIMARY KEY (`id_user`),
    UNIQUE KEY `uq_users_nik` (`nik`),
    UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. KATEGORI EVENT (Exact match)
-- ============================================================
CREATE TABLE `kategori_event` (
    `id_kat_event`     INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `nama_kategori`    VARCHAR(100)    NOT NULL,
    PRIMARY KEY (`id_kat_event`),
    UNIQUE KEY `uq_kategori_nama` (`nama_kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. EVENTS (Exact match to ROMBEL 3.md)
-- ============================================================
CREATE TABLE `events` (
    `id_event`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_kat_event`     INT UNSIGNED    NOT NULL,
    `judul_event`      VARCHAR(200)    NOT NULL,
    `penyelenggara`    VARCHAR(200)    NOT NULL,
    `lokasi`           VARCHAR(300)    NOT NULL,
    `tipe_venue`       ENUM('Seated','Free Standing') NOT NULL DEFAULT 'Free Standing',
    `tgl_mulai`        DATETIME        NOT NULL,
    `tgl_selesai`      DATETIME        NOT NULL,
    `banner`           VARCHAR(500)    NULL,
    `status_event`     ENUM('Available','Sold Out') NOT NULL DEFAULT 'Available',
    PRIMARY KEY (`id_event`),
    KEY `idx_events_kategori` (`id_kat_event`),
    KEY `idx_events_tgl_mulai` (`tgl_mulai`),
    KEY `idx_events_status` (`status_event`),
    CONSTRAINT `fk_events_kategori` FOREIGN KEY (`id_kat_event`)
        REFERENCES `kategori_event` (`id_kat_event`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. TIKET TIER (Exact match)
-- ============================================================
CREATE TABLE `tiket_tier` (
    `id_tier`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_event`         BIGINT UNSIGNED NOT NULL,
    `nama_tier`        VARCHAR(100)    NOT NULL,
    `harga`            INT UNSIGNED    NOT NULL COMMENT 'Harga dalam rupiah (integer)',
    `kuota_total`      INT UNSIGNED    NOT NULL DEFAULT 0,
    `kuota_tersedia`   INT UNSIGNED    NOT NULL DEFAULT 0,
    `kuota_locked`     INT UNSIGNED    NOT NULL DEFAULT 0,
    `batas_jual`       DATETIME        NOT NULL,
    PRIMARY KEY (`id_tier`),
    KEY `idx_tier_event` (`id_event`),
    KEY `idx_tier_batas_jual` (`batas_jual`),
    CONSTRAINT `fk_tier_event` FOREIGN KEY (`id_event`)
        REFERENCES `events` (`id_event`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. KURSI EVENT (Exact match)
-- ============================================================
CREATE TABLE `kursi_event` (
    `id_kursi`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_tier`          BIGINT UNSIGNED NOT NULL,
    `nomor_kursi`      VARCHAR(50)     NOT NULL,
    `status_kursi`     ENUM('Available','Locked','Sold') NOT NULL DEFAULT 'Available',
    PRIMARY KEY (`id_kursi`),
    UNIQUE KEY `uq_kursi_tier_nomor` (`id_tier`, `nomor_kursi`),
    KEY `idx_kursi_status` (`status_kursi`),
    CONSTRAINT `fk_kursi_tier` FOREIGN KEY (`id_tier`)
        REFERENCES `tiket_tier` (`id_tier`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. VOUCHER PROMO (Exact match)
-- ============================================================
CREATE TABLE `voucher_promo` (
    `id_voucher`       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kode_voucher`     VARCHAR(50)     NOT NULL,
    `tipe_potongan`    ENUM('Persen','Nominal') NOT NULL DEFAULT 'Nominal',
    `nilai_potongan`   INT UNSIGNED    NOT NULL,
    `min_transaksi`    INT UNSIGNED    NOT NULL DEFAULT 0,
    `kuota_pakai`      INT UNSIGNED    NOT NULL DEFAULT 0,
    `expired_date`     DATE            NOT NULL,
    PRIMARY KEY (`id_voucher`),
    UNIQUE KEY `uq_voucher_kode` (`kode_voucher`),
    KEY `idx_voucher_expired` (`expired_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. TRANSAKSI (Exact match to ROMBEL 3.md)
-- Includes biaya_layanan as specified
-- ============================================================
CREATE TABLE `transaksi` (
    `id_transaksi`      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kode_booking`      VARCHAR(60)     NOT NULL,
    `id_user`           BIGINT UNSIGNED NOT NULL,
    `id_event`          BIGINT UNSIGNED NOT NULL,
    `id_tier`           BIGINT UNSIGNED NOT NULL,
    `id_voucher`        BIGINT UNSIGNED NULL,
    `subtotal`          INT UNSIGNED    NOT NULL,
    `diskon`            INT UNSIGNED    NOT NULL DEFAULT 0,
    `biaya_layanan`     INT UNSIGNED    NOT NULL DEFAULT 0,
    `total_bayar`       INT UNSIGNED    NOT NULL,
    `bukti_bayar`       VARCHAR(500)    NULL,
    `batas_bayar_at`    DATETIME        NOT NULL,
    `status_bayar`      ENUM('Pending','Waiting Verification','Paid','Expired','Rejected') NOT NULL DEFAULT 'Pending',
    `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_transaksi`),
    UNIQUE KEY `uq_transaksi_kode` (`kode_booking`),
    KEY `idx_trans_user` (`id_user`),
    KEY `idx_trans_event` (`id_event`),
    KEY `idx_trans_tier` (`id_tier`),
    KEY `idx_trans_voucher` (`id_voucher`),
    KEY `idx_trans_status` (`status_bayar`),
    KEY `idx_trans_batas_bayar` (`batas_bayar_at`),
    CONSTRAINT `fk_trans_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_trans_event` FOREIGN KEY (`id_event`) REFERENCES `events` (`id_event`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_trans_tier` FOREIGN KEY (`id_tier`) REFERENCES `tiket_tier` (`id_tier`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_trans_voucher` FOREIGN KEY (`id_voucher`) REFERENCES `voucher_promo` (`id_voucher`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. E-TICKETS (Exact match to ROMBEL 3.md)
-- ============================================================
CREATE TABLE `e_tickets` (
    `id_etiket`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_transaksi`      BIGINT UNSIGNED NOT NULL,
    `id_tier`           BIGINT UNSIGNED NOT NULL,
    `id_kursi`          BIGINT UNSIGNED NULL,
    `kode_tiket`        VARCHAR(80)     NOT NULL,
    `nama_peserta`      VARCHAR(150)    NOT NULL,
    `nik_peserta`       VARCHAR(20)     NOT NULL,
    `status_checkin`    ENUM('Belum Hadir','Sudah Hadir') NOT NULL DEFAULT 'Belum Hadir',
    `waktu_checkin`     DATETIME        NULL,
    `id_petugas_gate`   BIGINT UNSIGNED NULL,
    PRIMARY KEY (`id_etiket`),
    UNIQUE KEY `uq_etiket_kode` (`kode_tiket`),
    KEY `idx_etiket_transaksi` (`id_transaksi`),
    KEY `idx_etiket_tier` (`id_tier`),
    KEY `idx_etiket_kursi` (`id_kursi`),
    KEY `idx_etiket_checkin` (`status_checkin`),
    KEY `idx_etiket_petugas` (`id_petugas_gate`),
    CONSTRAINT `fk_etiket_transaksi` FOREIGN KEY (`id_transaksi`) REFERENCES `transaksi` (`id_transaksi`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_etiket_tier` FOREIGN KEY (`id_tier`) REFERENCES `tiket_tier` (`id_tier`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_etiket_kursi` FOREIGN KEY (`id_kursi`) REFERENCES `kursi_event` (`id_kursi`) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_etiket_petugas` FOREIGN KEY (`id_petugas_gate`) REFERENCES `users` (`id_user`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. AUDIT LOGS (Exact match to ROMBEL 3.md - uses 'waktu')
-- ============================================================
CREATE TABLE `audit_logs` (
    `id_log`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_user`          BIGINT UNSIGNED NOT NULL,
    `aksi`             VARCHAR(100)    NOT NULL,
    `entitas`          VARCHAR(100)    NOT NULL,
    `keterangan`       TEXT            NOT NULL,
    `waktu`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_log`),
    KEY `idx_log_user` (`id_user`),
    KEY `idx_log_aksi` (`aksi`),
    KEY `idx_log_entitas` (`entitas`),
    KEY `idx_log_waktu` (`waktu`),
    CONSTRAINT `fk_log_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. RESERVATIONS (Supporting table for 15-min stock lock business rule)
-- Not in ROMBEL 3 schema but REQUIRED for Mandat CEO Rule #1
-- ============================================================
CREATE TABLE `reservations` (
    `id_reservasi`     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_user`          BIGINT UNSIGNED NOT NULL,
    `id_event`         BIGINT UNSIGNED NOT NULL,
    `id_tier`          BIGINT UNSIGNED NOT NULL,
    `id_voucher`       BIGINT UNSIGNED NULL,
    `jumlah`           TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `diskon`           INT UNSIGNED    NOT NULL DEFAULT 0,
    `total_bayar`      INT UNSIGNED    NOT NULL,
    `batas_bayar_at`   DATETIME        NOT NULL,
    `status`           ENUM('Active','Expired','Converted') NOT NULL DEFAULT 'Active',
    `created_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `converted_at`     DATETIME        NULL,
    PRIMARY KEY (`id_reservasi`),
    KEY `idx_res_user` (`id_user`),
    KEY `idx_res_event` (`id_event`),
    KEY `idx_res_tier` (`id_tier`),
    KEY `idx_res_voucher` (`id_voucher`),
    KEY `idx_res_status` (`status`),
    KEY `idx_res_batas_bayar` (`batas_bayar_at`),
    CONSTRAINT `fk_res_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_res_event` FOREIGN KEY (`id_event`) REFERENCES `events` (`id_event`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_res_tier` FOREIGN KEY (`id_tier`) REFERENCES `tiket_tier` (`id_tier`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_res_voucher` FOREIGN KEY (`id_voucher`) REFERENCES `voucher_promo` (`id_voucher`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. RESERVATION ITEMS (Attendee identity + seat per ticket)
-- Supports Identity Binding (Mandat CEO Rule #2)
-- ============================================================
CREATE TABLE `reservation_items` (
    `id_item`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_reservasi`     BIGINT UNSIGNED NOT NULL,
    `id_kursi`         BIGINT UNSIGNED NULL,
    `nama_peserta`     VARCHAR(150)    NOT NULL,
    `nik_peserta`      VARCHAR(20)     NOT NULL,
    `created_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_item`),
    KEY `idx_item_reservasi` (`id_reservasi`),
    KEY `idx_item_kursi` (`id_kursi`),
    CONSTRAINT `fk_item_reservasi` FOREIGN KEY (`id_reservasi`)
        REFERENCES `reservations` (`id_reservasi`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_item_kursi` FOREIGN KEY (`id_kursi`)
        REFERENCES `kursi_event` (`id_kursi`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 12. VOUCHER REDEMPTIONS (Prevents double quota restoration)
-- Supports Voucher Validation (Mandat CEO Rule #3)
-- ============================================================
CREATE TABLE `voucher_redemptions` (
    `id_redeem`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_voucher`       BIGINT UNSIGNED NOT NULL,
    `id_transaksi`     BIGINT UNSIGNED NOT NULL,
    `created_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_redeem`),
    UNIQUE KEY `uq_redeem_voucher_transaksi` (`id_voucher`, `id_transaksi`),
    KEY `idx_redeem_voucher` (`id_voucher`),
    KEY `idx_redeem_transaksi` (`id_transaksi`),
    CONSTRAINT `fk_redeem_voucher` FOREIGN KEY (`id_voucher`) REFERENCES `voucher_promo` (`id_voucher`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_redeem_transaksi` FOREIGN KEY (`id_transaksi`) REFERENCES `transaksi` (`id_transaksi`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PERFORMANCE INDEXES
-- ============================================================
CREATE INDEX `idx_transaksi_user_status` ON `transaksi` (`id_user`, `status_bayar`);
CREATE INDEX `idx_etiket_transaksi_checkin` ON `e_tickets` (`id_transaksi`, `status_checkin`);
CREATE INDEX `idx_reservations_user_event_tier` ON `reservations` (`id_user`, `id_event`, `id_tier`, `status`);
CREATE INDEX `idx_audit_logs_entitas_waktu` ON `audit_logs` (`entitas`, `waktu`);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- END OF SCHEMA
-- ============================================================