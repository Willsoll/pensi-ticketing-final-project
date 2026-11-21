<?php
/**
 * Seed db_tiket_event_pro — konsisten secara stok, kursi, dan e-ticket.
 * Jalankan: php database/seed.php
 */
declare(strict_types=1);

$seedHost = getenv('DB_HOST') ?: '127.0.0.1';
$seedPort = getenv('DB_PORT') ?: '3306';
$seedName = getenv('DB_NAME') ?: 'db_tiket_event_pro';
$seedUser = getenv('DB_USER') ?: 'root';
$seedPass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

$pdo = new PDO("mysql:host={$seedHost};port={$seedPort};dbname={$seedName};charset=utf8mb4", $seedUser, $seedPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec("SET time_zone = '+07:00'");
$pdo->beginTransaction();

$hash = password_hash('password123', PASSWORD_BCRYPT, ['cost' => 12]);

/* ---------------- users ---------------- */
$staff = [
    ['3174010101010001', 'Admin EventPro',   'admin.eo@eventpro.id', '081200000001', 'admin_eo'],
    ['3174010101010002', 'Finance EventPro', 'finance@eventpro.id',  '081200000002', 'finance'],
    ['3174010101010003', 'Gate EventPro',    'gate@eventpro.id',     '081200000003', 'gate_officer'],
];
$customers = [
    ['3174010101010011', 'Dewi Lestari',   'dewi@student.id',  '081211110011'],
    ['3174010101010012', 'Rizki Pratama',  'rizki@alumni.id',  '081211110012'],
    ['3174010101010013', 'Maya Anggraini', 'maya@guest.id',    '081211110013'],
    ['3174010101010014', 'Bagas Saputra',  'bagas@student.id', '081211110014'],
    ['3174010101010015', 'Citra Maharani', 'citra@student.id', '081211110015'],
    ['3174010101010016', 'Dimas Nugroho',  'dimas@alumni.id',  '081211110016'],
    ['3174010101010017', 'Eka Putri',      'eka@guest.id',     '081211110017'],
    ['3174010101010018', 'Fajar Ramadhan', 'fajar@student.id', '081211110018'],
    ['3174010101010019', 'Gita Permata',   'gita@student.id',  '081211110019'],
];
$insUser = $pdo->prepare('INSERT INTO users (nik,nama_lengkap,email,no_hp,password,role) VALUES (?,?,?,?,?,?)');
$userId = [];
foreach (array_merge($staff, array_map(fn($c) => [$c[0], $c[1], $c[2], $c[3], 'customer'], $customers)) as $u) {
    $insUser->execute([$u[0], $u[1], $u[2], $u[3], $hash, $u[4]]);
    $userId[$u[2]] = (int)$pdo->lastInsertId();
}
$gateUserId = $userId['gate@eventpro.id'];

/* ---------------- kategori ---------------- */
foreach (['Seminar', 'Workshop', 'Pameran', 'Festival'] as $k) {
    $pdo->prepare('INSERT INTO kategori_event (nama_kategori) VALUES (?)')->execute([$k]);
}
$kat = [];
foreach ($pdo->query('SELECT * FROM kategori_event') as $r) $kat[$r['nama_kategori']] = (int)$r['id_kat_event'];

/* ---------------- events ---------------- */
$events = [
    ['AI & Cyber Security Summit 2026', 'Himpunan Mahasiswa Informatika', 'Jakarta Convention Center', 'Seated',
     '2026-12-05 13:00:00', '2026-12-05 18:00:00', 'Seminar', 'Summit tahunan yang mempertemukan praktisi keamanan siber, peneliti, dan mahasiswa untuk membahas lanskap ancaman terbaru, teknik penetration testing, serta strategi pertahanan AI terhadap serangan siber.'],
    ['Web Security Bootcamp', 'EventPro Academy', 'Pahoa Innovation Lab, Tangerang', 'Seated',
     '2026-12-12 09:00:00', '2026-12-12 16:00:00', 'Workshop', 'Bootcamp intensif satu hari: peserta mempraktikkan OWASP Top 10, secure code review, dan capture the flag pada lingkungan lab yang disiapkan mentor berpengalaman.'],
    ['Digital Art & Creative Expo', 'Komunitas Seni Digital', 'Bandung Creative Hub', 'Free Standing',
     '2026-12-18 10:00:00', '2026-12-18 21:00:00', 'Pameran', 'Pameran karya digital, mural interaktif, dan showcase NFT dari komunitas seni digital. Nikmati artist talk, live painting, dan awarding karya terbaik.'],
    ['PENSI FEST 2026: Neon Night', 'OSIS Sekolah Terpadu Pahoa', 'Summarecon Serpong', 'Free Standing',
     '2027-01-24 17:00:00', '2027-01-24 23:00:00', 'Festival', 'Pentas seni tahunan Sekolah Terpadu Pahoa dengan penampilan band siswa, guest star, DJ set, dan kembang api penutup. Malam penuh warna bertema neon.'],
];
$insEvent = $pdo->prepare('INSERT INTO events (judul_event,penyelenggara,lokasi,tipe_venue,tgl_mulai,tgl_selesai,id_kat_event,status_event,banner,deskripsi)
                           VALUES (?,?,?,?,?,?,?,"Published",NULL,?)');
$eventId = [];
foreach ($events as $ev) {
    $insEvent->execute([$ev[0], $ev[1], $ev[2], $ev[3], $ev[4], $ev[5], $kat[$ev[6]], $ev[7] ?? null]);
    $eventId[$ev[0]] = (int)$pdo->lastInsertId();
}
$EV = array_keys($eventId);
$EVID = array_values($eventId);

/* ---------------- tiers + seats ---------------- */
$tiers = [
    [$EV[0], 'Early Bird',  75000, 30,  '2026-11-30 23:59:59', 'A'],
    [$EV[0], 'Reguler',    100000, 100, '2026-12-04 23:59:59', 'B'],
    [$EV[0], 'VIP',        175000, 30,  '2026-12-04 23:59:59', 'C'],
    [$EV[1], 'Early Bird', 150000, 20,  '2026-11-28 23:59:59', 'D'],
    [$EV[1], 'Reguler',    200000, 40,  '2026-12-11 23:59:59', 'E'],
    [$EV[1], 'VIP',        300000, 10,  '2026-12-11 23:59:59', 'F'],
    [$EV[2], 'Early Bird',  35000, 100, '2026-12-01 23:59:59', null],
    [$EV[2], 'Reguler',     50000, 300, '2026-12-17 23:59:59', null],
    [$EV[2], 'VIP',         90000, 50,  '2026-12-17 23:59:59', null],
    [$EV[3], 'Early Bird',  60000, 200, '2026-11-25 23:59:59', null],
    [$EV[3], 'Reguler',     85000, 500, '2027-01-23 23:59:59', null],
    [$EV[3], 'VIP',        150000, 80,  '2027-01-23 23:59:59', null],
];
$insTier = $pdo->prepare('INSERT INTO tiket_tier (id_event,nama_tier,harga,kuota_total,kuota_tersedia,kuota_locked,batas_jual)
                          VALUES (?,?,?,?,?,0,?)');
$insSeat = $pdo->prepare('INSERT INTO kursi_event (id_tier,nomor_kursi,status_kursi) VALUES (?,?,"Available")');
$tier = [];
foreach ($tiers as $t) {
    [$evName, $nama, $harga, $kuota, $batas, $prefix] = $t;
    $idEvent = $eventId[$evName];
    $insTier->execute([$idEvent, $nama, $harga, $kuota, $kuota, $batas]);
    $idTier = (int)$pdo->lastInsertId();
    if ($prefix) {
        for ($i = 1; $i <= $kuota; $i++) $insSeat->execute([$idTier, sprintf('%s-%03d', $prefix, $i)]);
    }
    $tier["$idEvent|$nama"] = ['id' => $idTier, 'harga' => $harga, 'kuota' => $kuota, 'seated' => (bool)$prefix];
}

/* ---------------- vouchers ---------------- */
$vouchers = [
    ['HEMAT10',     'Persen',  10, 100000, 100, '2026-12-31'],
    ['POTONG25K',   'Nominal', 25000, 150000, 50, '2026-12-15'],
    ['EARLYBIRD',   'Persen',  15, 75000, 30, '2026-11-30'],
    ['MAHASISWA20', 'Persen',  20, 200000, 20, '2026-12-10'],
];
$insVoucher = $pdo->prepare('INSERT INTO voucher_promo (kode_voucher,tipe_potongan,nilai_potongan,min_transaksi,kuota_pakai,expired_date)
                             VALUES (?,?,?,?,?,?)');
$voucher = [];
foreach ($vouchers as $v) {
    $insVoucher->execute($v);
    $voucher[$v[0]] = ['id' => (int)$pdo->lastInsertId(), 'tipe' => $v[1], 'nilai' => $v[2], 'min' => $v[3], 'kuota' => $v[4]];
}

/* ---------------- rundown ---------------- */
$rundowns = [
    [$EVID[0], 'Registrasi & Opening Keynote', 'Panitia', '13:00:00', '13:45:00', 'Main Hall'],
    [$EVID[0], 'Threat Landscape 2026', 'Raka Wijaya', '13:45:00', '15:00:00', 'Main Hall'],
    [$EVID[0], 'Live Penetration Testing Demo', 'Tim Red Team', '15:15:00', '16:45:00', 'Main Hall'],
    [$EVID[0], 'Panel Diskusi & Closing', 'Semua Pembicara', '16:45:00', '18:00:00', 'Main Hall'],
    [$EVID[1], 'Setup Lab & Pre-assessment', 'Mentor', '09:00:00', '09:30:00', 'Lab 1'],
    [$EVID[1], 'OWASP Top 10 Hands-on', 'Mentor', '09:30:00', '12:00:00', 'Lab 1'],
    [$EVID[1], 'Secure Code Review Workshop', 'Mentor', '13:00:00', '15:30:00', 'Lab 1'],
    [$EVID[1], 'Capture The Flag Sesi', 'Mentor', '15:30:00', '16:00:00', 'Lab 1'],
    [$EVID[2], 'Pameran Dibuka', 'Panitia', '10:00:00', '12:00:00', 'Gallery A'],
    [$EVID[2], 'Artist Talk: Digital Canvas', 'Nadia Ayu', '13:00:00', '14:30:00', 'Stage'],
    [$EVID[2], 'Live Mural & NFT Showcase', 'Komunitas', '15:00:00', '18:00:00', 'Gallery B'],
    [$EVID[2], 'Closing & Awarding', 'Juri', '19:00:00', '21:00:00', 'Stage'],
    [$EVID[3], 'Gate Open & Welcome', 'OSIS', '17:00:00', '17:30:00', 'Main Gate'],
    [$EVID[3], 'Band Siswa Showcase', 'Ekskul Band', '17:30:00', '19:00:00', 'Main Stage'],
    [$EVID[3], 'Guest Star Performance', 'Special Guest', '19:30:00', '21:00:00', 'Main Stage'],
    [$EVID[3], 'DJ Set & Closing Fireworks', 'DJ Night', '21:00:00', '23:00:00', 'Main Stage'],
];
$insRd = $pdo->prepare('INSERT INTO event_rundown (id_event,judul_sesi,pembicara,jam_mulai,jam_selesai,lokasi_panggung)
                        VALUES (?,?,?,?,?,?)');
foreach ($rundowns as $r) $insRd->execute($r);

/* ---------------- helpers ---------------- */
function kode_booking(): string { return 'BK-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4))); }
function kode_tiket(int $n): string { return 'TKT-' . strtoupper(bin2hex(random_bytes(8))) . '-' . $n; }

$pool = ['Adi Saputra', 'Bella Kartika', 'Candra Wijaya', 'Dinda Ayu', 'Erwin Halim',
         'Fina Sari', 'Galih Pratama', 'Hana Utami', 'Indra Kusuma', 'Joko Santoso'];
$nikSeq = 1;
function nik_peserta(int &$seq): string { return '31740101010' . sprintf('%05d', $seq++); }

function seat_pick(PDO $pdo, int $idTier, int $qty, string $newStatus): array {
    $sel = $pdo->prepare('SELECT id_kursi FROM kursi_event WHERE id_tier=? AND status_kursi="Available" ORDER BY id_kursi LIMIT ' . (int)$qty);
    $sel->execute([$idTier]);
    $ids = array_map('intval', $sel->fetchAll(PDO::FETCH_COLUMN));
    if (count($ids) < $qty) throw new RuntimeException("Kursi tidak cukup untuk tier $idTier");
    $upd = $pdo->prepare("UPDATE kursi_event SET status_kursi='$newStatus' WHERE id_kursi=?");
    foreach ($ids as $id) $upd->execute([$id]);
    return $ids;
}

/* ---------------- transaksi + e_tickets ---------------- */
$trxSeeds = [
    ['dewi@student.id',  $EVID[0], 'Reguler',    2, 'Paid',                 'HEMAT10',   9, 1],
    ['bagas@student.id', $EVID[0], 'VIP',        1, 'Paid',                 null,        8, 0],
    ['citra@student.id', $EVID[0], 'Early Bird', 3, 'Waiting Verification', 'EARLYBIRD', 2, 0],
    ['dimas@alumni.id',  $EVID[0], 'Reguler',    1, 'Pending',              null,        0, 0, 'Cash'],
    ['eka@guest.id',     $EVID[1], 'VIP',        1, 'Paid',                 null,        7, 1, 'Cash'],
    ['fajar@student.id', $EVID[1], 'Reguler',    2, 'Paid',                 'POTONG25K', 5, 0],
    ['gita@student.id',  $EVID[1], 'Early Bird', 1, 'Rejected',             null,        6, 0],
    ['dewi@student.id',  $EVID[2], 'Reguler',    4, 'Paid',                 null,        4, 0],
    ['maya@guest.id',    $EVID[2], 'VIP',        2, 'Waiting Verification', null,        1, 0],
    ['rizki@alumni.id',  $EVID[2], 'Early Bird', 1, 'Expired',              null,        3, 0],
    ['rizki@alumni.id',  $EVID[3], 'Reguler',    2, 'Paid',                 'HEMAT10',   3, 0],
    ['maya@guest.id',    $EVID[3], 'VIP',        1, 'Paid',                 null,        2, 0],
    ['bagas@student.id', $EVID[3], 'Early Bird', 3, 'Pending',              null,        1, 0],
];

$insTrx = $pdo->prepare('INSERT INTO transaksi
    (kode_booking,id_user,id_event,id_voucher,subtotal,diskon,biaya_layanan,total_bayar,metode_bayar,bukti_bayar,batas_bayar_at,status_bayar,catatan_penolakan,created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
$insPeserta = $pdo->prepare('INSERT INTO transaksi_peserta (id_transaksi,id_tier,nama_peserta,nik_peserta,id_kursi)
    VALUES (?,?,?,?,?)');
$insDetail = $pdo->prepare('INSERT INTO transaksi_detail (id_transaksi,id_tier,jumlah,harga_satuan,subtotal,id_kursi_list)
    VALUES (?,?,?,?,?,?)');
$insEtiket = $pdo->prepare('INSERT INTO e_tickets
    (id_transaksi,id_tier,id_kursi,kode_tiket,nama_peserta,nik_peserta,status_checkin,waktu_checkin,id_petugas_gate)
    VALUES (?,?,?,?,?,?,?,?,?)');
$lockedByTier = []; $soldByTier = []; $voucherUsed = [];

foreach ($trxSeeds as $s) {
    [$email, $evId, $tierName, $qty, $status, $vCode, $daysAgo, $checkins] = $s;
    $metode = $s[8] ?? 'Transfer';
    $t = $tier["$evId|$tierName"];
    $idTier = $t['id'];
    $subtotal = $t['harga'] * $qty;
    $biaya = 2500 * $qty;
    $diskon = 0; $idVoucher = null;
    if ($vCode) {
        $v = $voucher[$vCode];
        if ($subtotal >= $v['min']) {
            $idVoucher = $v['id'];
            $diskon = $v['tipe'] === 'Persen'
                ? (int)round($subtotal * $v['nilai'] / 100)
                : min($v['nilai'], $subtotal);
            $voucherUsed[$vCode] = ($voucherUsed[$vCode] ?? 0) + 1;
        }
    }
    $total = $subtotal - $diskon + $biaya;
    $created = date('Y-m-d H:i:s', strtotime("-$daysAgo days"));
    $batas = date('Y-m-d H:i:s', strtotime("-$daysAgo days +15 minutes"));
    $bukti = ($metode === 'Cash') ? null : (in_array($status, ['Waiting Verification', 'Paid', 'Rejected'], true)
        ? 'uploads/bukti/contoh_bukti.png' : null);
    $catatan = $status === 'Rejected' ? 'Bukti transfer tidak valid / nominal tidak sesuai.' : null;

    $insTrx->execute([kode_booking(), $userId[$email], $evId, $idVoucher, $subtotal, $diskon, $biaya, $total,
                      $metode, $bukti, $batas, $status, $catatan, $created]);
    $idTrx = (int)$pdo->lastInsertId();

    $seats = [];
    if ($t['seated'] && in_array($status, ['Paid', 'Waiting Verification', 'Pending'], true)) {
        $seats = seat_pick($pdo, $idTier, $qty, $status === 'Paid' ? 'Sold' : 'Locked');
    }
    if (in_array($status, ['Paid', 'Waiting Verification', 'Pending'], true)) {
        $insDetail->execute([$idTrx, $idTier, $qty, $t['harga'], $subtotal, $seats ? implode(',', $seats) : null]);
    }

    $idx = array_search($email, array_column($customers, 2), true);
    $participants = [];
    for ($i = 0; $i < $qty; $i++) {
        $participants[] = [
            'nama' => $i === 0 ? $customers[$idx][1] : $pool[($idTrx + $i) % count($pool)],
            'nik'  => $i === 0 ? $customers[$idx][0] : nik_peserta($nikSeq),
        ];
    }
    foreach ($participants as $i => $pes) {
        $insPeserta->execute([$idTrx, $idTier, $pes['nama'], $pes['nik'], $seats[$i] ?? null]);
    }

    if ($status === 'Paid') {
        $soldByTier[$idTier] = ($soldByTier[$idTier] ?? 0) + $qty;
        foreach ($participants as $i => $pes) {
            $hadir = $i < $checkins ? 'Sudah Hadir' : 'Belum Hadir';
            $waktu = $i < $checkins ? date('Y-m-d H:i:s', strtotime("-$daysAgo days +2 hours")) : null;
            $insEtiket->execute([$idTrx, $idTier, $seats[$i] ?? null, kode_tiket($i + 1), $pes['nama'], $pes['nik'],
                                 $hadir, $waktu, $i < $checkins ? $gateUserId : null]);
        }
    } elseif (in_array($status, ['Waiting Verification', 'Pending'], true)) {
        $lockedByTier[$idTier] = ($lockedByTier[$idTier] ?? 0) + $qty;
    }
}

/* ---------------- reconcile stock ---------------- */
$updTier = $pdo->prepare('UPDATE tiket_tier SET kuota_tersedia=?, kuota_locked=? WHERE id_tier=?');
foreach ($tier as $t) {
    $id = $t['id'];
    $sold = $soldByTier[$id] ?? 0;
    $locked = $lockedByTier[$id] ?? 0;
    $updTier->execute([$t['kuota'] - $sold - $locked, $locked, $id]);
}
foreach ($voucherUsed as $kode => $n) {
    $pdo->prepare('UPDATE voucher_promo SET kuota_pakai = kuota_pakai - ? WHERE kode_voucher=?')
        ->execute([$n, $kode]);
}

/* ---------------- audit seeds ---------------- */
$insAudit = $pdo->prepare('INSERT INTO audit_logs (id_user,aksi,entitas,keterangan,waktu) VALUES (?,?,?,?,?)');
$insAudit->execute([$userId['admin.eo@eventpro.id'], 'CREATE', 'events', 'Seed 4 event multi-kategori', date('Y-m-d H:i:s', strtotime('-10 days'))]);
$insAudit->execute([$userId['finance@eventpro.id'], 'VERIFY', 'transaksi', 'Verifikasi pembayaran contoh', date('Y-m-d H:i:s', strtotime('-4 days'))]);
$insAudit->execute([$gateUserId, 'CHECKIN', 'e_tickets', 'Check-in contoh gate', date('Y-m-d H:i:s', strtotime('-1 days'))]);


// Transaksi aktif harus masih dalam jendela 15 menit agar dashboard finance terisi saat demo.
$pdo->exec("UPDATE transaksi SET created_at = NOW(), batas_bayar_at = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
            WHERE status_bayar IN ('Pending','Waiting Verification')");

$pdo->commit();
echo "SEED OK\n";
foreach (['users','events','tiket_tier','kursi_event','voucher_promo','transaksi','e_tickets','event_rundown'] as $t) {
    echo "$t=" . $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn() . "\n";
}
