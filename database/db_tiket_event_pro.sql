-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: db_tiket_event_pro
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `db_tiket_event_pro`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `db_tiket_event_pro` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `db_tiket_event_pro`;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id_log` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) DEFAULT NULL,
  `aksi` varchar(80) NOT NULL,
  `entitas` varchar(60) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `waktu` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_log`),
  KEY `idx_audit_waktu` (`waktu`),
  KEY `idx_audit_user` (`id_user`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,1,'CREATE','events','Seed 4 event multi-kategori','2026-11-09 16:29:47'),(2,2,'VERIFY','transaksi','Verifikasi pembayaran contoh','2026-11-15 16:29:47'),(3,3,'CHECKIN','e_tickets','Check-in contoh gate','2026-11-18 16:29:47'),(4,4,'LOGIN','users','Login berhasil sebagai customer.','2026-11-19 22:29:59'),(5,2,'LOGIN','users','Login berhasil sebagai finance.','2026-11-19 22:30:00'),(7,1,'LOGIN','users','Login berhasil sebagai admin_eo.','2026-11-19 22:35:47'),(8,1,'LOGOUT','users','Keluar dari sesi.','2026-11-19 22:35:57'),(9,4,'LOGIN','users','Login berhasil sebagai customer.','2026-11-19 22:36:01'),(10,NULL,'EXPIRE','transaksi','Transaksi #3 kedaluwarsa; kuota & kursi dikembalikan.','2026-11-19 22:44:49'),(11,NULL,'EXPIRE','transaksi','Transaksi #4 kedaluwarsa; kuota & kursi dikembalikan.','2026-11-19 22:44:49'),(12,NULL,'EXPIRE','transaksi','Transaksi #9 kedaluwarsa; kuota & kursi dikembalikan.','2026-11-19 22:44:49'),(13,NULL,'EXPIRE','transaksi','Transaksi #13 kedaluwarsa; kuota & kursi dikembalikan.','2026-11-19 22:44:49'),(14,4,'LOGIN','users','Login berhasil sebagai customer.','2026-11-19 22:45:23'),(15,1,'LOGIN','users','Login berhasil sebagai admin_eo.','2026-11-20 19:42:49'),(16,4,'LOGIN','users','Login berhasil sebagai customer.','2026-11-21 16:32:11'),(17,12,'LOGIN','users','Login berhasil sebagai customer.','2026-11-21 16:32:12'),(18,12,'CREATE','transaksi','Pemesanan BK-20261121-B84C8CB8 untuk 2 tiket Reguler. Total: Rp 95.000.','2026-11-21 16:32:12'),(19,NULL,'EXPIRE','transaksi','Transaksi #14 kedaluwarsa; kuota & kursi dikembalikan.','2026-11-21 16:32:12'),(20,3,'LOGIN','users','Login berhasil sebagai gate_officer.','2026-11-21 16:32:13'),(21,3,'CHECKIN','e_tickets','Gate check-in berhasil: TKT-024C577C9F8EF909-2 (Reguler) atas nama 3174010101000001 — AI & Cyber Security Summit 2026.','2026-11-21 16:32:13'),(22,4,'LOGIN','users','Login berhasil sebagai customer.','2026-11-21 17:32:37'),(23,12,'LOGIN','users','Login berhasil sebagai customer.','2026-11-21 17:32:38'),(24,12,'CREATE','transaksi','Pemesanan BK-20261121-BFECCDFA untuk 2 tiket Reguler. Total: Rp 95.000.','2026-11-21 17:32:38'),(25,NULL,'EXPIRE','transaksi','Transaksi #15 kedaluwarsa; kuota & kursi dikembalikan.','2026-11-21 17:32:38'),(26,3,'LOGIN','users','Login berhasil sebagai gate_officer.','2026-11-21 17:32:39'),(27,3,'CHECKIN','e_tickets','Gate check-in berhasil: TKT-280A2A3B1F94FBDD-1 (VIP) atas nama 3174010101010014 — AI & Cyber Security Summit 2026.','2026-11-21 17:32:39'),(28,4,'LOGIN','users','Login berhasil sebagai customer.','2026-11-21 21:17:44'),(29,3,'LOGIN','users','Login berhasil sebagai gate_officer.','2026-11-21 21:17:51'),(30,1,'LOGIN','users','Login berhasil sebagai admin_eo.','2026-11-21 21:19:16');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `e_tickets`
--

DROP TABLE IF EXISTS `e_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `e_tickets` (
  `id_etiket` int(11) NOT NULL AUTO_INCREMENT,
  `id_transaksi` int(11) NOT NULL,
  `id_tier` int(11) NOT NULL,
  `id_kursi` int(11) DEFAULT NULL,
  `kode_tiket` varchar(40) NOT NULL,
  `nama_peserta` varchar(100) NOT NULL,
  `nik_peserta` varchar(20) NOT NULL,
  `status_checkin` enum('Belum Hadir','Sudah Hadir') NOT NULL DEFAULT 'Belum Hadir',
  `waktu_checkin` datetime DEFAULT NULL,
  `id_petugas_gate` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_etiket`),
  UNIQUE KEY `uq_etiket_kode` (`kode_tiket`),
  KEY `fk_etiket_tier` (`id_tier`),
  KEY `fk_etiket_kursi` (`id_kursi`),
  KEY `fk_etiket_gate` (`id_petugas_gate`),
  KEY `idx_etiket_trx` (`id_transaksi`),
  KEY `idx_etiket_nik` (`nik_peserta`),
  KEY `idx_etiket_checkin` (`status_checkin`),
  CONSTRAINT `fk_etiket_gate` FOREIGN KEY (`id_petugas_gate`) REFERENCES `users` (`id_user`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_etiket_kursi` FOREIGN KEY (`id_kursi`) REFERENCES `kursi_event` (`id_kursi`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_etiket_tier` FOREIGN KEY (`id_tier`) REFERENCES `tiket_tier` (`id_tier`) ON UPDATE CASCADE,
  CONSTRAINT `fk_etiket_trx` FOREIGN KEY (`id_transaksi`) REFERENCES `transaksi` (`id_transaksi`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `e_tickets`
--

LOCK TABLES `e_tickets` WRITE;
/*!40000 ALTER TABLE `e_tickets` DISABLE KEYS */;
INSERT INTO `e_tickets` VALUES (1,1,2,31,'TKT-21D605C778F339D4-1','Dewi Lestari','3174010101010011','Sudah Hadir','2026-11-10 18:29:47',3),(2,1,2,32,'TKT-024C577C9F8EF909-2','Candra Wijaya','3174010101000001','Sudah Hadir','2026-11-21 16:32:13',3),(3,2,3,131,'TKT-280A2A3B1F94FBDD-1','Bagas Saputra','3174010101010014','Sudah Hadir','2026-11-21 17:32:39',3),(4,5,6,221,'TKT-04379F0D50C44820-1','Eka Putri','3174010101010017','Sudah Hadir','2026-11-12 18:29:47',3),(5,6,5,181,'TKT-0BC8FCC9D3A003F1-1','Fajar Ramadhan','3174010101010018','Belum Hadir',NULL,NULL),(6,6,5,182,'TKT-11925302B1BA60E9-2','Hana Utami','3174010101000004','Belum Hadir',NULL,NULL),(7,8,8,NULL,'TKT-821203E3A8F4EA71-1','Dewi Lestari','3174010101010011','Belum Hadir',NULL,NULL),(8,8,8,NULL,'TKT-9682CBFB2708D70D-2','Joko Santoso','3174010101000005','Belum Hadir',NULL,NULL),(9,8,8,NULL,'TKT-6F33624A8B6F176B-3','Adi Saputra','3174010101000006','Belum Hadir',NULL,NULL),(10,8,8,NULL,'TKT-E858C55B59545448-4','Bella Kartika','3174010101000007','Belum Hadir',NULL,NULL),(11,11,11,NULL,'TKT-42BE8F3A50CBAAA9-1','Rizki Pratama','3174010101010012','Belum Hadir',NULL,NULL),(12,11,11,NULL,'TKT-375811C522753DDC-2','Candra Wijaya','3174010101000009','Belum Hadir',NULL,NULL),(13,12,12,NULL,'TKT-B0496FB6CF2FEBDC-1','Maya Anggraini','3174010101010013','Belum Hadir',NULL,NULL);
/*!40000 ALTER TABLE `e_tickets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `event_rundown`
--

DROP TABLE IF EXISTS `event_rundown`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_rundown` (
  `id_rundown` int(11) NOT NULL AUTO_INCREMENT,
  `id_event` int(11) NOT NULL,
  `judul_sesi` varchar(120) NOT NULL,
  `pembicara` varchar(120) DEFAULT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `lokasi_panggung` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_rundown`),
  KEY `fk_rundown_event` (`id_event`),
  CONSTRAINT `fk_rundown_event` FOREIGN KEY (`id_event`) REFERENCES `events` (`id_event`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_rundown`
--

LOCK TABLES `event_rundown` WRITE;
/*!40000 ALTER TABLE `event_rundown` DISABLE KEYS */;
INSERT INTO `event_rundown` VALUES (1,1,'Registrasi & Opening Keynote','Panitia','13:00:00','13:45:00','Main Hall'),(2,1,'Threat Landscape 2026','Raka Wijaya','13:45:00','15:00:00','Main Hall'),(3,1,'Live Penetration Testing Demo','Tim Red Team','15:15:00','16:45:00','Main Hall'),(4,1,'Panel Diskusi & Closing','Semua Pembicara','16:45:00','18:00:00','Main Hall'),(5,2,'Setup Lab & Pre-assessment','Mentor','09:00:00','09:30:00','Lab 1'),(6,2,'OWASP Top 10 Hands-on','Mentor','09:30:00','12:00:00','Lab 1'),(7,2,'Secure Code Review Workshop','Mentor','13:00:00','15:30:00','Lab 1'),(8,2,'Capture The Flag Sesi','Mentor','15:30:00','16:00:00','Lab 1'),(9,3,'Pameran Dibuka','Panitia','10:00:00','12:00:00','Gallery A'),(10,3,'Artist Talk: Digital Canvas','Nadia Ayu','13:00:00','14:30:00','Stage'),(11,3,'Live Mural & NFT Showcase','Komunitas','15:00:00','18:00:00','Gallery B'),(12,3,'Closing & Awarding','Juri','19:00:00','21:00:00','Stage'),(13,4,'Gate Open & Welcome','OSIS','17:00:00','17:30:00','Main Gate'),(14,4,'Band Siswa Showcase','Ekskul Band','17:30:00','19:00:00','Main Stage'),(15,4,'Guest Star Performance','Special Guest','19:30:00','21:00:00','Main Stage'),(16,4,'DJ Set & Closing Fireworks','DJ Night','21:00:00','23:00:00','Main Stage');
/*!40000 ALTER TABLE `event_rundown` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `events`
--

DROP TABLE IF EXISTS `events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `events` (
  `id_event` int(11) NOT NULL AUTO_INCREMENT,
  `id_kat_event` int(11) NOT NULL,
  `judul_event` varchar(150) NOT NULL,
  `penyelenggara` varchar(100) NOT NULL,
  `lokasi` varchar(120) NOT NULL,
  `tipe_venue` enum('Seated','Free Standing') NOT NULL DEFAULT 'Free Standing',
  `tgl_mulai` datetime NOT NULL,
  `tgl_selesai` datetime NOT NULL,
  `banner` varchar(255) DEFAULT NULL,
  `deskripsi` text DEFAULT NULL,
  `status_event` enum('Draft','Published','Closed') NOT NULL DEFAULT 'Published',
  PRIMARY KEY (`id_event`),
  KEY `fk_events_kategori` (`id_kat_event`),
  KEY `idx_events_status` (`status_event`),
  KEY `idx_events_tgl` (`tgl_mulai`),
  CONSTRAINT `fk_events_kategori` FOREIGN KEY (`id_kat_event`) REFERENCES `kategori_event` (`id_kat_event`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `events`
--

LOCK TABLES `events` WRITE;
/*!40000 ALTER TABLE `events` DISABLE KEYS */;
INSERT INTO `events` VALUES (1,1,'AI & Cyber Security Summit 2026','Himpunan Mahasiswa Informatika','Jakarta Convention Center','Seated','2026-12-05 13:00:00','2026-12-05 18:00:00',NULL,'Summit tahunan yang mempertemukan praktisi keamanan siber, peneliti, dan mahasiswa untuk membahas lanskap ancaman terbaru, teknik penetration testing, serta strategi pertahanan AI terhadap serangan siber.','Published'),(2,2,'Web Security Bootcamp','EventPro Academy','Pahoa Innovation Lab, Tangerang','Seated','2026-12-12 09:00:00','2026-12-12 16:00:00',NULL,'Bootcamp intensif satu hari: peserta mempraktikkan OWASP Top 10, secure code review, dan capture the flag pada lingkungan lab yang disiapkan mentor berpengalaman.','Published'),(3,3,'Digital Art & Creative Expo','Komunitas Seni Digital','Bandung Creative Hub','Free Standing','2026-12-18 10:00:00','2026-12-18 21:00:00',NULL,'Pameran karya digital, mural interaktif, dan showcase NFT dari komunitas seni digital. Nikmati artist talk, live painting, dan awarding karya terbaik.','Published'),(4,4,'PENSI FEST 2026: Neon Night','OSIS Sekolah Terpadu Pahoa','Summarecon Serpong','Free Standing','2027-01-24 17:00:00','2027-01-24 23:00:00',NULL,'Pentas seni tahunan Sekolah Terpadu Pahoa dengan penampilan band siswa, guest star, DJ set, dan kembang api penutup. Malam penuh warna bertema neon.','Published');
/*!40000 ALTER TABLE `events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kategori_event`
--

DROP TABLE IF EXISTS `kategori_event`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kategori_event` (
  `id_kat_event` int(11) NOT NULL AUTO_INCREMENT,
  `nama_kategori` varchar(60) NOT NULL,
  PRIMARY KEY (`id_kat_event`),
  UNIQUE KEY `uq_kategori` (`nama_kategori`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kategori_event`
--

LOCK TABLES `kategori_event` WRITE;
/*!40000 ALTER TABLE `kategori_event` DISABLE KEYS */;
INSERT INTO `kategori_event` VALUES (4,'Festival'),(3,'Pameran'),(1,'Seminar'),(2,'Workshop');
/*!40000 ALTER TABLE `kategori_event` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kursi_event`
--

DROP TABLE IF EXISTS `kursi_event`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kursi_event` (
  `id_kursi` int(11) NOT NULL AUTO_INCREMENT,
  `id_tier` int(11) NOT NULL,
  `nomor_kursi` varchar(20) NOT NULL,
  `status_kursi` enum('Available','Locked','Sold') NOT NULL DEFAULT 'Available',
  PRIMARY KEY (`id_kursi`),
  UNIQUE KEY `uq_kursi_tier_nomor` (`id_tier`,`nomor_kursi`),
  KEY `idx_kursi_status` (`status_kursi`),
  CONSTRAINT `fk_kursi_tier` FOREIGN KEY (`id_tier`) REFERENCES `tiket_tier` (`id_tier`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=231 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kursi_event`
--

LOCK TABLES `kursi_event` WRITE;
/*!40000 ALTER TABLE `kursi_event` DISABLE KEYS */;
INSERT INTO `kursi_event` VALUES (1,1,'A-001','Available'),(2,1,'A-002','Available'),(3,1,'A-003','Available'),(4,1,'A-004','Available'),(5,1,'A-005','Available'),(6,1,'A-006','Available'),(7,1,'A-007','Available'),(8,1,'A-008','Available'),(9,1,'A-009','Available'),(10,1,'A-010','Available'),(11,1,'A-011','Available'),(12,1,'A-012','Available'),(13,1,'A-013','Available'),(14,1,'A-014','Available'),(15,1,'A-015','Available'),(16,1,'A-016','Available'),(17,1,'A-017','Available'),(18,1,'A-018','Available'),(19,1,'A-019','Available'),(20,1,'A-020','Available'),(21,1,'A-021','Available'),(22,1,'A-022','Available'),(23,1,'A-023','Available'),(24,1,'A-024','Available'),(25,1,'A-025','Available'),(26,1,'A-026','Available'),(27,1,'A-027','Available'),(28,1,'A-028','Available'),(29,1,'A-029','Available'),(30,1,'A-030','Available'),(31,2,'B-001','Sold'),(32,2,'B-002','Sold'),(33,2,'B-003','Available'),(34,2,'B-004','Available'),(35,2,'B-005','Available'),(36,2,'B-006','Available'),(37,2,'B-007','Available'),(38,2,'B-008','Available'),(39,2,'B-009','Available'),(40,2,'B-010','Available'),(41,2,'B-011','Available'),(42,2,'B-012','Available'),(43,2,'B-013','Available'),(44,2,'B-014','Available'),(45,2,'B-015','Available'),(46,2,'B-016','Available'),(47,2,'B-017','Available'),(48,2,'B-018','Available'),(49,2,'B-019','Available'),(50,2,'B-020','Available'),(51,2,'B-021','Available'),(52,2,'B-022','Available'),(53,2,'B-023','Available'),(54,2,'B-024','Available'),(55,2,'B-025','Available'),(56,2,'B-026','Available'),(57,2,'B-027','Available'),(58,2,'B-028','Available'),(59,2,'B-029','Available'),(60,2,'B-030','Available'),(61,2,'B-031','Available'),(62,2,'B-032','Available'),(63,2,'B-033','Available'),(64,2,'B-034','Available'),(65,2,'B-035','Available'),(66,2,'B-036','Available'),(67,2,'B-037','Available'),(68,2,'B-038','Available'),(69,2,'B-039','Available'),(70,2,'B-040','Available'),(71,2,'B-041','Available'),(72,2,'B-042','Available'),(73,2,'B-043','Available'),(74,2,'B-044','Available'),(75,2,'B-045','Available'),(76,2,'B-046','Available'),(77,2,'B-047','Available'),(78,2,'B-048','Available'),(79,2,'B-049','Available'),(80,2,'B-050','Available'),(81,2,'B-051','Available'),(82,2,'B-052','Available'),(83,2,'B-053','Available'),(84,2,'B-054','Available'),(85,2,'B-055','Available'),(86,2,'B-056','Available'),(87,2,'B-057','Available'),(88,2,'B-058','Available'),(89,2,'B-059','Available'),(90,2,'B-060','Available'),(91,2,'B-061','Available'),(92,2,'B-062','Available'),(93,2,'B-063','Available'),(94,2,'B-064','Available'),(95,2,'B-065','Available'),(96,2,'B-066','Available'),(97,2,'B-067','Available'),(98,2,'B-068','Available'),(99,2,'B-069','Available'),(100,2,'B-070','Available'),(101,2,'B-071','Available'),(102,2,'B-072','Available'),(103,2,'B-073','Available'),(104,2,'B-074','Available'),(105,2,'B-075','Available'),(106,2,'B-076','Available'),(107,2,'B-077','Available'),(108,2,'B-078','Available'),(109,2,'B-079','Available'),(110,2,'B-080','Available'),(111,2,'B-081','Available'),(112,2,'B-082','Available'),(113,2,'B-083','Available'),(114,2,'B-084','Available'),(115,2,'B-085','Available'),(116,2,'B-086','Available'),(117,2,'B-087','Available'),(118,2,'B-088','Available'),(119,2,'B-089','Available'),(120,2,'B-090','Available'),(121,2,'B-091','Available'),(122,2,'B-092','Available'),(123,2,'B-093','Available'),(124,2,'B-094','Available'),(125,2,'B-095','Available'),(126,2,'B-096','Available'),(127,2,'B-097','Available'),(128,2,'B-098','Available'),(129,2,'B-099','Available'),(130,2,'B-100','Available'),(131,3,'C-001','Sold'),(132,3,'C-002','Available'),(133,3,'C-003','Available'),(134,3,'C-004','Available'),(135,3,'C-005','Available'),(136,3,'C-006','Available'),(137,3,'C-007','Available'),(138,3,'C-008','Available'),(139,3,'C-009','Available'),(140,3,'C-010','Available'),(141,3,'C-011','Available'),(142,3,'C-012','Available'),(143,3,'C-013','Available'),(144,3,'C-014','Available'),(145,3,'C-015','Available'),(146,3,'C-016','Available'),(147,3,'C-017','Available'),(148,3,'C-018','Available'),(149,3,'C-019','Available'),(150,3,'C-020','Available'),(151,3,'C-021','Available'),(152,3,'C-022','Available'),(153,3,'C-023','Available'),(154,3,'C-024','Available'),(155,3,'C-025','Available'),(156,3,'C-026','Available'),(157,3,'C-027','Available'),(158,3,'C-028','Available'),(159,3,'C-029','Available'),(160,3,'C-030','Available'),(161,4,'D-001','Available'),(162,4,'D-002','Available'),(163,4,'D-003','Available'),(164,4,'D-004','Available'),(165,4,'D-005','Available'),(166,4,'D-006','Available'),(167,4,'D-007','Available'),(168,4,'D-008','Available'),(169,4,'D-009','Available'),(170,4,'D-010','Available'),(171,4,'D-011','Available'),(172,4,'D-012','Available'),(173,4,'D-013','Available'),(174,4,'D-014','Available'),(175,4,'D-015','Available'),(176,4,'D-016','Available'),(177,4,'D-017','Available'),(178,4,'D-018','Available'),(179,4,'D-019','Available'),(180,4,'D-020','Available'),(181,5,'E-001','Sold'),(182,5,'E-002','Sold'),(183,5,'E-003','Available'),(184,5,'E-004','Available'),(185,5,'E-005','Available'),(186,5,'E-006','Available'),(187,5,'E-007','Available'),(188,5,'E-008','Available'),(189,5,'E-009','Available'),(190,5,'E-010','Available'),(191,5,'E-011','Available'),(192,5,'E-012','Available'),(193,5,'E-013','Available'),(194,5,'E-014','Available'),(195,5,'E-015','Available'),(196,5,'E-016','Available'),(197,5,'E-017','Available'),(198,5,'E-018','Available'),(199,5,'E-019','Available'),(200,5,'E-020','Available'),(201,5,'E-021','Available'),(202,5,'E-022','Available'),(203,5,'E-023','Available'),(204,5,'E-024','Available'),(205,5,'E-025','Available'),(206,5,'E-026','Available'),(207,5,'E-027','Available'),(208,5,'E-028','Available'),(209,5,'E-029','Available'),(210,5,'E-030','Available'),(211,5,'E-031','Available'),(212,5,'E-032','Available'),(213,5,'E-033','Available'),(214,5,'E-034','Available'),(215,5,'E-035','Available'),(216,5,'E-036','Available'),(217,5,'E-037','Available'),(218,5,'E-038','Available'),(219,5,'E-039','Available'),(220,5,'E-040','Available'),(221,6,'F-001','Sold'),(222,6,'F-002','Available'),(223,6,'F-003','Available'),(224,6,'F-004','Available'),(225,6,'F-005','Available'),(226,6,'F-006','Available'),(227,6,'F-007','Available'),(228,6,'F-008','Available'),(229,6,'F-009','Available'),(230,6,'F-010','Available');
/*!40000 ALTER TABLE `kursi_event` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `identifier` varchar(255) NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_attempt_identifier` (`identifier`),
  KEY `idx_attempt_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
INSERT INTO `login_attempts` VALUES (1,'dewi@student.id',1,'2026-11-19 15:29:59'),(2,'finance@eventpro.id',1,'2026-11-19 15:30:00'),(3,'admin.eo@eventpro.id',1,'2026-11-19 15:35:47'),(4,'dewi@student.id',1,'2026-11-19 15:36:01'),(5,'dewi@student.id',1,'2026-11-19 15:45:23'),(6,'admin.eo@eventpro.id',1,'2026-11-20 12:42:49'),(7,'dewi@student.id',1,'2026-11-21 09:32:11'),(8,'gita@student.id',1,'2026-11-21 09:32:12'),(9,'gate@eventpro.id',1,'2026-11-21 09:32:13'),(10,'dewi@student.id',1,'2026-11-21 10:32:37'),(11,'gita@student.id',1,'2026-11-21 10:32:38'),(12,'gate@eventpro.id',1,'2026-11-21 10:32:39'),(13,'dewi@student.id',1,'2026-11-21 14:17:44'),(14,'ip:127.0.0.1',1,'2026-11-21 14:17:44'),(15,'gate@eventpro.id',1,'2026-11-21 14:17:51'),(16,'ip:127.0.0.1',1,'2026-11-21 14:17:51'),(17,'admin.eo@eventpro.id',1,'2026-11-21 14:19:16'),(18,'ip:127.0.0.1',1,'2026-11-21 14:19:16');
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tiket_tier`
--

DROP TABLE IF EXISTS `tiket_tier`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tiket_tier` (
  `id_tier` int(11) NOT NULL AUTO_INCREMENT,
  `id_event` int(11) NOT NULL,
  `nama_tier` varchar(60) NOT NULL,
  `harga` int(11) NOT NULL,
  `kuota_total` int(11) NOT NULL,
  `kuota_tersedia` int(11) NOT NULL,
  `kuota_locked` int(11) NOT NULL DEFAULT 0,
  `batas_jual` datetime NOT NULL,
  PRIMARY KEY (`id_tier`),
  KEY `idx_tier_event` (`id_event`),
  KEY `idx_tier_batas` (`batas_jual`),
  CONSTRAINT `fk_tier_event` FOREIGN KEY (`id_event`) REFERENCES `events` (`id_event`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tiket_tier`
--

LOCK TABLES `tiket_tier` WRITE;
/*!40000 ALTER TABLE `tiket_tier` DISABLE KEYS */;
INSERT INTO `tiket_tier` VALUES (1,1,'Early Bird',75000,30,30,0,'2026-11-30 23:59:59'),(2,1,'Reguler',100000,100,98,0,'2026-12-04 23:59:59'),(3,1,'VIP',175000,30,29,0,'2026-12-04 23:59:59'),(4,2,'Early Bird',150000,20,20,0,'2026-11-28 23:59:59'),(5,2,'Reguler',200000,40,38,0,'2026-12-11 23:59:59'),(6,2,'VIP',300000,10,9,0,'2026-12-11 23:59:59'),(7,3,'Early Bird',35000,100,100,0,'2026-12-01 23:59:59'),(8,3,'Reguler',50000,300,296,0,'2026-12-17 23:59:59'),(9,3,'VIP',90000,50,50,0,'2026-12-17 23:59:59'),(10,4,'Early Bird',60000,200,200,0,'2026-11-25 23:59:59'),(11,4,'Reguler',85000,500,498,0,'2027-01-23 23:59:59'),(12,4,'VIP',150000,80,79,0,'2027-01-23 23:59:59');
/*!40000 ALTER TABLE `tiket_tier` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transaksi`
--

DROP TABLE IF EXISTS `transaksi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transaksi` (
  `id_transaksi` int(11) NOT NULL AUTO_INCREMENT,
  `kode_booking` varchar(40) NOT NULL,
  `id_user` int(11) NOT NULL,
  `id_event` int(11) NOT NULL,
  `id_voucher` int(11) DEFAULT NULL,
  `subtotal` int(11) NOT NULL DEFAULT 0,
  `diskon` int(11) NOT NULL DEFAULT 0,
  `biaya_layanan` int(11) NOT NULL DEFAULT 0,
  `total_bayar` int(11) NOT NULL DEFAULT 0,
  `metode_bayar` enum('Transfer','Cash') NOT NULL DEFAULT 'Transfer',
  `bukti_bayar` varchar(255) DEFAULT NULL,
  `batas_bayar_at` datetime NOT NULL,
  `status_bayar` enum('Pending','Waiting Verification','Paid','Expired','Rejected') NOT NULL DEFAULT 'Pending',
  `catatan_penolakan` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_transaksi`),
  UNIQUE KEY `uq_transaksi_kode` (`kode_booking`),
  KEY `fk_trx_voucher` (`id_voucher`),
  KEY `idx_trx_status` (`status_bayar`),
  KEY `idx_trx_user` (`id_user`),
  KEY `idx_trx_event` (`id_event`),
  KEY `idx_trx_created` (`created_at`),
  CONSTRAINT `fk_trx_event` FOREIGN KEY (`id_event`) REFERENCES `events` (`id_event`) ON UPDATE CASCADE,
  CONSTRAINT `fk_trx_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON UPDATE CASCADE,
  CONSTRAINT `fk_trx_voucher` FOREIGN KEY (`id_voucher`) REFERENCES `voucher_promo` (`id_voucher`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transaksi`
--

LOCK TABLES `transaksi` WRITE;
/*!40000 ALTER TABLE `transaksi` DISABLE KEYS */;
INSERT INTO `transaksi` VALUES (1,'BK-20261119-62270BC3',4,1,1,200000,20000,5000,185000,'Transfer','uploads/bukti/contoh_bukti.png','2026-11-10 16:44:47','Paid',NULL,'2026-11-10 16:29:47'),(2,'BK-20261119-4488AAFF',7,1,NULL,175000,0,2500,177500,'Transfer','uploads/bukti/contoh_bukti.png','2026-11-11 16:44:47','Paid',NULL,'2026-11-11 16:29:47'),(3,'BK-20261119-74B82749',8,1,3,225000,33750,7500,198750,'Transfer','uploads/bukti/contoh_bukti.png','2026-11-19 22:44:47','Expired',NULL,'2026-11-19 22:29:47'),(4,'BK-20261119-A10A026C',9,1,NULL,100000,0,2500,102500,'Cash',NULL,'2026-11-19 22:44:47','Expired',NULL,'2026-11-19 22:29:47'),(5,'BK-20261119-8F6D907F',10,2,NULL,300000,0,2500,302500,'Cash',NULL,'2026-11-12 16:44:47','Paid',NULL,'2026-11-12 16:29:47'),(6,'BK-20261119-9DEFAB99',11,2,2,400000,25000,5000,380000,'Transfer','uploads/bukti/contoh_bukti.png','2026-11-14 16:44:47','Paid',NULL,'2026-11-14 16:29:47'),(7,'BK-20261119-2DBEBF56',12,2,NULL,150000,0,2500,152500,'Transfer','uploads/bukti/contoh_bukti.png','2026-11-13 16:44:47','Rejected','Bukti transfer tidak valid / nominal tidak sesuai.','2026-11-13 16:29:47'),(8,'BK-20261119-6685FB5C',4,3,NULL,200000,0,10000,210000,'Transfer','uploads/bukti/contoh_bukti.png','2026-11-15 16:44:47','Paid',NULL,'2026-11-15 16:29:47'),(9,'BK-20261119-B4562BA5',6,3,NULL,180000,0,5000,185000,'Transfer','uploads/bukti/contoh_bukti.png','2026-11-19 22:44:47','Expired',NULL,'2026-11-19 22:29:47'),(10,'BK-20261119-42A5CCC9',5,3,NULL,35000,0,2500,37500,'Transfer',NULL,'2026-11-16 16:44:47','Expired',NULL,'2026-11-16 16:29:47'),(11,'BK-20261119-0464811D',5,4,1,170000,17000,5000,158000,'Transfer','uploads/bukti/contoh_bukti.png','2026-11-16 16:44:47','Paid',NULL,'2026-11-16 16:29:47'),(12,'BK-20261119-118ED28A',6,4,NULL,150000,0,2500,152500,'Transfer','uploads/bukti/contoh_bukti.png','2026-11-17 16:44:47','Paid',NULL,'2026-11-17 16:29:47'),(13,'BK-20261119-1D753F81',7,4,NULL,180000,0,7500,187500,'Transfer',NULL,'2026-11-19 22:44:47','Expired',NULL,'2026-11-19 22:29:47'),(14,'BK-20261121-B84C8CB8',12,3,1,100000,10000,5000,95000,'Transfer',NULL,'2026-11-21 16:31:12','Expired',NULL,'2026-11-21 16:32:12'),(15,'BK-20261121-BFECCDFA',12,3,1,100000,10000,5000,95000,'Transfer',NULL,'2026-11-21 17:31:38','Expired',NULL,'2026-11-21 17:32:38');
/*!40000 ALTER TABLE `transaksi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transaksi_detail`
--

DROP TABLE IF EXISTS `transaksi_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transaksi_detail` (
  `id_detail` int(11) NOT NULL AUTO_INCREMENT,
  `id_transaksi` int(11) NOT NULL,
  `id_tier` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `harga_satuan` int(11) NOT NULL,
  `subtotal` int(11) NOT NULL,
  `id_kursi_list` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_detail`),
  KEY `fk_detail_tier` (`id_tier`),
  KEY `idx_detail_trx` (`id_transaksi`),
  CONSTRAINT `fk_detail_tier` FOREIGN KEY (`id_tier`) REFERENCES `tiket_tier` (`id_tier`) ON UPDATE CASCADE,
  CONSTRAINT `fk_detail_trx` FOREIGN KEY (`id_transaksi`) REFERENCES `transaksi` (`id_transaksi`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transaksi_detail`
--

LOCK TABLES `transaksi_detail` WRITE;
/*!40000 ALTER TABLE `transaksi_detail` DISABLE KEYS */;
INSERT INTO `transaksi_detail` VALUES (1,1,2,2,100000,200000,'31,32'),(2,2,3,1,175000,175000,'131'),(3,3,1,3,75000,225000,'1,2,3'),(4,4,2,1,100000,100000,'33'),(5,5,6,1,300000,300000,'221'),(6,6,5,2,200000,400000,'181,182'),(7,8,8,4,50000,200000,NULL),(8,9,9,2,90000,180000,NULL),(9,11,11,2,85000,170000,NULL),(10,12,12,1,150000,150000,NULL),(11,13,10,3,60000,180000,NULL),(12,14,8,2,50000,100000,''),(13,15,8,2,50000,100000,'');
/*!40000 ALTER TABLE `transaksi_detail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transaksi_peserta`
--

DROP TABLE IF EXISTS `transaksi_peserta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transaksi_peserta` (
  `id_peserta` int(11) NOT NULL AUTO_INCREMENT,
  `id_transaksi` int(11) NOT NULL,
  `id_tier` int(11) NOT NULL,
  `nama_peserta` varchar(100) NOT NULL,
  `nik_peserta` varchar(20) NOT NULL,
  `id_kursi` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_peserta`),
  UNIQUE KEY `uq_peserta_nik` (`id_transaksi`,`nik_peserta`),
  KEY `fk_peserta_tier` (`id_tier`),
  KEY `fk_peserta_kursi` (`id_kursi`),
  KEY `idx_peserta_trx` (`id_transaksi`),
  CONSTRAINT `fk_peserta_kursi` FOREIGN KEY (`id_kursi`) REFERENCES `kursi_event` (`id_kursi`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_peserta_tier` FOREIGN KEY (`id_tier`) REFERENCES `tiket_tier` (`id_tier`) ON UPDATE CASCADE,
  CONSTRAINT `fk_peserta_trx` FOREIGN KEY (`id_transaksi`) REFERENCES `transaksi` (`id_transaksi`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transaksi_peserta`
--

LOCK TABLES `transaksi_peserta` WRITE;
/*!40000 ALTER TABLE `transaksi_peserta` DISABLE KEYS */;
INSERT INTO `transaksi_peserta` VALUES (1,1,2,'Dewi Lestari','3174010101010011',31),(2,1,2,'Candra Wijaya','3174010101000001',32),(3,2,3,'Bagas Saputra','3174010101010014',131),(4,3,1,'Citra Maharani','3174010101010015',1),(5,3,1,'Erwin Halim','3174010101000002',2),(6,3,1,'Fina Sari','3174010101000003',3),(7,4,2,'Dimas Nugroho','3174010101010016',33),(8,5,6,'Eka Putri','3174010101010017',221),(9,6,5,'Fajar Ramadhan','3174010101010018',181),(10,6,5,'Hana Utami','3174010101000004',182),(11,7,4,'Gita Permata','3174010101010019',NULL),(12,8,8,'Dewi Lestari','3174010101010011',NULL),(13,8,8,'Joko Santoso','3174010101000005',NULL),(14,8,8,'Adi Saputra','3174010101000006',NULL),(15,8,8,'Bella Kartika','3174010101000007',NULL),(16,9,9,'Maya Anggraini','3174010101010013',NULL),(17,9,9,'Adi Saputra','3174010101000008',NULL),(18,10,7,'Rizki Pratama','3174010101010012',NULL),(19,11,11,'Rizki Pratama','3174010101010012',NULL),(20,11,11,'Candra Wijaya','3174010101000009',NULL),(21,12,12,'Maya Anggraini','3174010101010013',NULL),(22,13,10,'Bagas Saputra','3174010101010014',NULL),(23,13,10,'Erwin Halim','3174010101000010',NULL),(24,13,10,'Fina Sari','3174010101000011',NULL),(25,14,8,'Test Satu V4','3174010101077701',NULL),(26,14,8,'Test Dua V4','3174010101077702',NULL),(27,15,8,'Test Satu V4','3174010101077701',NULL),(28,15,8,'Test Dua V4','3174010101077702',NULL);
/*!40000 ALTER TABLE `transaksi_peserta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id_user` int(11) NOT NULL AUTO_INCREMENT,
  `nik` varchar(20) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `no_hp` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('customer','admin_eo','finance','gate_officer') NOT NULL DEFAULT 'customer',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `uq_users_nik` (`nik`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'3174010101010001','Admin EventPro','admin.eo@eventpro.id','081200000001','$2y$12$gronLRj6nw.meBmVSxAYjOMFqvLe7HX2IvlyBa4YD72NGkd6JVcnW','admin_eo','2026-11-19 22:29:47'),(2,'3174010101010002','Finance EventPro','finance@eventpro.id','081200000002','$2y$12$9/HFwtmdPWPSks6ETJotae3hre5nyTM0CNEKV/p9k8DxxPFttfElW','finance','2026-11-19 22:29:47'),(3,'3174010101010003','Gate EventPro','gate@eventpro.id','081200000003','$2y$12$BHRnxbGpgtowaHaHV/A7Gu7ZYQuJoVeEdsAsi1zGWZgG/TRe44CtS','gate_officer','2026-11-19 22:29:47'),(4,'3174010101010011','Dewi Lestari','dewi@student.id','081211110011','$2y$12$f/jnBlqWz0aE/ZeNEBI5jeuELQ8v8pVm9puTR2P89q35hpHMmKU.K','customer','2026-11-19 22:29:47'),(5,'3174010101010012','Rizki Pratama','rizki@alumni.id','081211110012','$2y$12$f/jnBlqWz0aE/ZeNEBI5jeuELQ8v8pVm9puTR2P89q35hpHMmKU.K','customer','2026-11-19 22:29:47'),(6,'3174010101010013','Maya Anggraini','maya@guest.id','081211110013','$2y$12$f/jnBlqWz0aE/ZeNEBI5jeuELQ8v8pVm9puTR2P89q35hpHMmKU.K','customer','2026-11-19 22:29:47'),(7,'3174010101010014','Bagas Saputra','bagas@student.id','081211110014','$2y$12$f/jnBlqWz0aE/ZeNEBI5jeuELQ8v8pVm9puTR2P89q35hpHMmKU.K','customer','2026-11-19 22:29:47'),(8,'3174010101010015','Citra Maharani','citra@student.id','081211110015','$2y$12$f/jnBlqWz0aE/ZeNEBI5jeuELQ8v8pVm9puTR2P89q35hpHMmKU.K','customer','2026-11-19 22:29:47'),(9,'3174010101010016','Dimas Nugroho','dimas@alumni.id','081211110016','$2y$12$f/jnBlqWz0aE/ZeNEBI5jeuELQ8v8pVm9puTR2P89q35hpHMmKU.K','customer','2026-11-19 22:29:47'),(10,'3174010101010017','Eka Putri','eka@guest.id','081211110017','$2y$12$f/jnBlqWz0aE/ZeNEBI5jeuELQ8v8pVm9puTR2P89q35hpHMmKU.K','customer','2026-11-19 22:29:47'),(11,'3174010101010018','Fajar Ramadhan','fajar@student.id','081211110018','$2y$12$f/jnBlqWz0aE/ZeNEBI5jeuELQ8v8pVm9puTR2P89q35hpHMmKU.K','customer','2026-11-19 22:29:47'),(12,'3174010101010019','Gita Permata','gita@student.id','081211110019','$2y$12$f/jnBlqWz0aE/ZeNEBI5jeuELQ8v8pVm9puTR2P89q35hpHMmKU.K','customer','2026-11-19 22:29:47');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `voucher_promo`
--

DROP TABLE IF EXISTS `voucher_promo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `voucher_promo` (
  `id_voucher` int(11) NOT NULL AUTO_INCREMENT,
  `kode_voucher` varchar(40) NOT NULL,
  `tipe_potongan` enum('Persen','Nominal') NOT NULL,
  `nilai_potongan` int(11) NOT NULL,
  `min_transaksi` int(11) NOT NULL DEFAULT 0,
  `kuota_pakai` int(11) NOT NULL DEFAULT 0,
  `expired_date` date NOT NULL,
  PRIMARY KEY (`id_voucher`),
  UNIQUE KEY `uq_voucher_kode` (`kode_voucher`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `voucher_promo`
--

LOCK TABLES `voucher_promo` WRITE;
/*!40000 ALTER TABLE `voucher_promo` DISABLE KEYS */;
INSERT INTO `voucher_promo` VALUES (1,'HEMAT10','Persen',10,100000,98,'2026-12-31'),(2,'POTONG25K','Nominal',25000,150000,49,'2026-12-15'),(3,'EARLYBIRD','Persen',15,75000,28,'2026-11-30'),(4,'MAHASISWA20','Persen',20,200000,20,'2026-12-10');
/*!40000 ALTER TABLE `voucher_promo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'db_tiket_event_pro'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-21 21:22:08
