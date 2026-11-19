-- ============================================================================
-- PAKET 7 — db_tiket_event_pro
-- Aplikasi Pemesanan Tiket Event (Multi-Event & Seat/Quota Ticketing Platform)
-- Native PHP + MySQL (MariaDB 10.4 compatible)
-- ============================================================================

DROP DATABASE IF EXISTS db_tiket_event_pro;
CREATE DATABASE db_tiket_event_pro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_tiket_event_pro;

-- ---------------------------------------------------------------------------
-- users — 4 peran: customer, admin_eo, finance, gate_officer
-- ---------------------------------------------------------------------------
CREATE TABLE users (
    id_user       INT AUTO_INCREMENT PRIMARY KEY,
    nik           VARCHAR(20)  NOT NULL,
    nama_lengkap  VARCHAR(100) NOT NULL,
    email         VARCHAR(100) NOT NULL,
    no_hp         VARCHAR(20)  NOT NULL,
    password      VARCHAR(255) NOT NULL,
    role          ENUM('customer','admin_eo','finance','gate_officer') NOT NULL DEFAULT 'customer',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_nik   (nik),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- kategori_event
-- ---------------------------------------------------------------------------
CREATE TABLE kategori_event (
    id_kat_event  INT AUTO_INCREMENT PRIMARY KEY,
    nama_kategori VARCHAR(60) NOT NULL,
    UNIQUE KEY uq_kategori (nama_kategori)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- events — multi-event, Seated / Free Standing
-- ---------------------------------------------------------------------------
CREATE TABLE events (
    id_event      INT AUTO_INCREMENT PRIMARY KEY,
    id_kat_event  INT NOT NULL,
    judul_event   VARCHAR(150) NOT NULL,
    penyelenggara VARCHAR(100) NOT NULL,
    lokasi        VARCHAR(120) NOT NULL,
    tipe_venue    ENUM('Seated','Free Standing') NOT NULL DEFAULT 'Free Standing',
    tgl_mulai     DATETIME NOT NULL,
    tgl_selesai   DATETIME NOT NULL,
    banner        VARCHAR(255) DEFAULT NULL,
    deskripsi     TEXT DEFAULT NULL,
    status_event  ENUM('Draft','Published','Closed') NOT NULL DEFAULT 'Published',
    CONSTRAINT fk_events_kategori FOREIGN KEY (id_kat_event)
        REFERENCES kategori_event(id_kat_event) ON UPDATE CASCADE ON DELETE RESTRICT,
    KEY idx_events_status (status_event),
    KEY idx_events_tgl (tgl_mulai)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- tiket_tier — kuota + batas jual per tier
-- ---------------------------------------------------------------------------
CREATE TABLE tiket_tier (
    id_tier         INT AUTO_INCREMENT PRIMARY KEY,
    id_event        INT NOT NULL,
    nama_tier       VARCHAR(60) NOT NULL,
    harga           INT NOT NULL,
    kuota_total     INT NOT NULL,
    kuota_tersedia  INT NOT NULL,
    kuota_locked    INT NOT NULL DEFAULT 0,
    batas_jual      DATETIME NOT NULL,
    CONSTRAINT fk_tier_event FOREIGN KEY (id_event)
        REFERENCES events(id_event) ON UPDATE CASCADE ON DELETE CASCADE,
    KEY idx_tier_event (id_event),
    KEY idx_tier_batas (batas_jual)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- kursi_event — hanya untuk event Seated
-- ---------------------------------------------------------------------------
CREATE TABLE kursi_event (
    id_kursi     INT AUTO_INCREMENT PRIMARY KEY,
    id_tier      INT NOT NULL,
    nomor_kursi  VARCHAR(20) NOT NULL,
    status_kursi ENUM('Available','Locked','Sold') NOT NULL DEFAULT 'Available',
    CONSTRAINT fk_kursi_tier FOREIGN KEY (id_tier)
        REFERENCES tiket_tier(id_tier) ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE KEY uq_kursi_tier_nomor (id_tier, nomor_kursi),
    KEY idx_kursi_status (status_kursi)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- voucher_promo — persen / nominal, syarat minimal transaksi
-- ---------------------------------------------------------------------------
CREATE TABLE voucher_promo (
    id_voucher     INT AUTO_INCREMENT PRIMARY KEY,
    kode_voucher   VARCHAR(40) NOT NULL,
    tipe_potongan  ENUM('Persen','Nominal') NOT NULL,
    nilai_potongan INT NOT NULL,
    min_transaksi  INT NOT NULL DEFAULT 0,
    kuota_pakai    INT NOT NULL DEFAULT 0,
    expired_date   DATE NOT NULL,
    UNIQUE KEY uq_voucher_kode (kode_voucher)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- transaksi — booking + pembayaran
-- ---------------------------------------------------------------------------
CREATE TABLE transaksi (
    id_transaksi      INT AUTO_INCREMENT PRIMARY KEY,
    kode_booking      VARCHAR(40) NOT NULL,
    id_user           INT NOT NULL,
    id_event          INT NOT NULL,
    id_voucher        INT DEFAULT NULL,
    subtotal          INT NOT NULL DEFAULT 0,
    diskon            INT NOT NULL DEFAULT 0,
    biaya_layanan     INT NOT NULL DEFAULT 0,
    total_bayar       INT NOT NULL DEFAULT 0,
    metode_bayar      ENUM('Transfer','Cash') NOT NULL DEFAULT 'Transfer',
    bukti_bayar       VARCHAR(255) DEFAULT NULL,
    batas_bayar_at    DATETIME NOT NULL,
    status_bayar      ENUM('Pending','Waiting Verification','Paid','Expired','Rejected')
                      NOT NULL DEFAULT 'Pending',
    catatan_penolakan VARCHAR(255) DEFAULT NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_transaksi_kode (kode_booking),
    CONSTRAINT fk_trx_user  FOREIGN KEY (id_user)  REFERENCES users(id_user)  ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_trx_event FOREIGN KEY (id_event) REFERENCES events(id_event) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_trx_voucher FOREIGN KEY (id_voucher) REFERENCES voucher_promo(id_voucher)
        ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_trx_status (status_bayar),
    KEY idx_trx_user (id_user),
    KEY idx_trx_event (id_event),
    KEY idx_trx_created (created_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- e_tickets — 1-to-many, identitas peserta melekat di tiket
-- ---------------------------------------------------------------------------
CREATE TABLE e_tickets (
    id_etiket       INT AUTO_INCREMENT PRIMARY KEY,
    id_transaksi    INT NOT NULL,
    id_tier         INT NOT NULL,
    id_kursi        INT DEFAULT NULL,
    kode_tiket      VARCHAR(40) NOT NULL,
    nama_peserta    VARCHAR(100) NOT NULL,
    nik_peserta     VARCHAR(20) NOT NULL,
    status_checkin  ENUM('Belum Hadir','Sudah Hadir') NOT NULL DEFAULT 'Belum Hadir',
    waktu_checkin   DATETIME DEFAULT NULL,
    id_petugas_gate INT DEFAULT NULL,
    UNIQUE KEY uq_etiket_kode (kode_tiket),
    CONSTRAINT fk_etiket_trx  FOREIGN KEY (id_transaksi) REFERENCES transaksi(id_transaksi)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_etiket_tier FOREIGN KEY (id_tier) REFERENCES tiket_tier(id_tier)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_etiket_kursi FOREIGN KEY (id_kursi) REFERENCES kursi_event(id_kursi)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_etiket_gate FOREIGN KEY (id_petugas_gate) REFERENCES users(id_user)
        ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_etiket_trx (id_transaksi),
    KEY idx_etiket_nik (nik_peserta),
    KEY idx_etiket_checkin (status_checkin)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- transaksi_detail — baris pesanan per tier + kursi yang dikunci
-- (tabel pendukung: menyimpan qty & kursi saat status masih Pending)
-- ---------------------------------------------------------------------------
CREATE TABLE transaksi_detail (
    id_detail    INT AUTO_INCREMENT PRIMARY KEY,
    id_transaksi INT NOT NULL,
    id_tier      INT NOT NULL,
    jumlah       INT NOT NULL,
    harga_satuan INT NOT NULL,
    subtotal     INT NOT NULL,
    id_kursi_list VARCHAR(255) DEFAULT NULL,
    CONSTRAINT fk_detail_trx  FOREIGN KEY (id_transaksi) REFERENCES transaksi(id_transaksi)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_detail_tier FOREIGN KEY (id_tier) REFERENCES tiket_tier(id_tier)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    KEY idx_detail_trx (id_transaksi)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- transaksi_peserta — identitas tiap lembar tiket saat booking
-- ---------------------------------------------------------------------------
CREATE TABLE transaksi_peserta (
    id_peserta   INT AUTO_INCREMENT PRIMARY KEY,
    id_transaksi INT NOT NULL,
    id_tier      INT NOT NULL,
    nama_peserta VARCHAR(100) NOT NULL,
    nik_peserta  VARCHAR(20) NOT NULL,
    id_kursi     INT DEFAULT NULL,
    CONSTRAINT fk_peserta_trx  FOREIGN KEY (id_transaksi) REFERENCES transaksi(id_transaksi)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_peserta_tier FOREIGN KEY (id_tier) REFERENCES tiket_tier(id_tier)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_peserta_kursi FOREIGN KEY (id_kursi) REFERENCES kursi_event(id_kursi)
        ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_peserta_nik (id_transaksi, nik_peserta),
    KEY idx_peserta_trx (id_transaksi)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- audit_logs — jejak aktivitas data sensitif
-- ---------------------------------------------------------------------------
CREATE TABLE audit_logs (
    id_log    INT AUTO_INCREMENT PRIMARY KEY,
    id_user   INT DEFAULT NULL,
    aksi      VARCHAR(80) NOT NULL,
    entitas   VARCHAR(60) NOT NULL,
    keterangan TEXT DEFAULT NULL,
    waktu     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (id_user) REFERENCES users(id_user)
        ON UPDATE CASCADE ON DELETE SET NULL,
    KEY idx_audit_waktu (waktu),
    KEY idx_audit_user (id_user)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Support tables (not part of the graded 9, but required by the pages)
-- ---------------------------------------------------------------------------
CREATE TABLE event_rundown (
    id_rundown  INT AUTO_INCREMENT PRIMARY KEY,
    id_event    INT NOT NULL,
    judul_sesi  VARCHAR(120) NOT NULL,
    pembicara   VARCHAR(120) DEFAULT NULL,
    jam_mulai   TIME NOT NULL,
    jam_selesai TIME NOT NULL,
    lokasi_panggung VARCHAR(100) DEFAULT NULL,
    CONSTRAINT fk_rundown_event FOREIGN KEY (id_event) REFERENCES events(id_event)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE login_attempts (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL,
    success    TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_attempt_identifier (identifier),
    KEY idx_attempt_created (created_at)
) ENGINE=InnoDB;
