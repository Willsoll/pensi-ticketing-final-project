<?php
// Temporary helper to inspect/reset DB state over HTTP for race-condition testing.
// DELETE THIS FILE AFTER TESTING.
header('Content-Type: application/json');
header('Cache-Control: no-store');
$pdo = new PDO("mysql:host=localhost;dbname=db_pensi_sekolah;charset=utf8mb4", 'root', '', [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);
$action = $_GET['action'] ?? 'state';
$res = ['action' => $action, 'ok' => false];
try {
    if ($action === 'state') {
        $res['jenis_tiket'] = $pdo->query("SELECT id_jenis,nama_jenis,target_pembeli,tipe_tiket,harga,stok_tiket,stok_tersedia,stok_tertahan FROM jenis_tiket ORDER BY id_jenis")->fetchAll();
        $res['kode_promo']  = $pdo->query("SELECT id_promo,kode_voucher,kuota_voucher,potongan_nominal,berlaku_sampai FROM kode_promo ORDER BY id_promo")->fetchAll();
        $res['users']       = $pdo->query("SELECT id_user,username,role,nis_atau_nik FROM users ORDER BY id_user")->fetchAll();
        $res['pemesanan']   = $pdo->query("SELECT status_bayar, COUNT(*) AS c FROM pemesanan GROUP BY status_bayar")->fetchAll();
        $res['checkin']     = $pdo->query("SELECT status_checkin, COUNT(*) AS c FROM detail_tiket_satuan GROUP BY status_checkin")->fetchAll();
        $res['ok'] = true;
    } elseif ($action === 'reset') {
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        $pdo->exec("TRUNCATE TABLE detail_tiket_satuan");
        $pdo->exec("TRUNCATE TABLE pemesanan");
        $pdo->exec("TRUNCATE TABLE audit_logs");
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
        $pdo->exec("UPDATE jenis_tiket SET stok_tersedia = stok_tiket, stok_tertahan = 0");
        $pdo->exec("UPDATE kursi_pensi SET status_kursi = 'Available'");
        // Reset voucher quotas to seed-like values if table exists
        $res['ok'] = true;
        $res['msg'] = 'reset done';
    } elseif ($action === 'add_users') {
        $n = (int)($_GET['n'] ?? 50);
        $hash = '$2y$10$f4fPuHsZtLAHobWJKlf/W.CiOu5celsNCFVvjEVF7RJ1TWNWcKWjm'; // password123
        $added = 0;
        for ($i = 0; $i < $n; $i++) {
            $username = 'racebuyer_' . ($i + 1);
            $nis = str_pad((string)(2000000000 + $i + 1), 10, '0', STR_PAD_LEFT);
            try {
                $pdo->prepare("INSERT INTO users (nama,tipe_pengguna,nis_atau_nik,kelas_atau_instansi,username,password,role) VALUES (?, 'Internal', ?, 'Kelas 10', ?, ?, 'pembeli')")
                    ->execute(['Race Buyer ' . ($i + 1), $nis, $username, $hash]);
                $added++;
            } catch (PDOException $e) {
                // duplicate username/nis, skip
            }
        }
        $res['ok'] = true;
        $res['added'] = $added;
        $res['requested'] = $n;
    }
} catch (Throwable $e) {
    $res['error'] = $e->getMessage();
}
echo json_encode($res);
