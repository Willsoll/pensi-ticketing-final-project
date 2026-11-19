<?php
/**
 * E2E FULL TEST — PENSI EVENTPRO v4
 * Menguji seluruh alur lewat HTTP + verifikasi database.
 *
 * Jalankan terhadap server QA (DB terpisah):
 *   DB_NAME=db_tiket_event_pro_qa DB_USER=root DB_PASS= \
 *   BASE_URL=http://127.0.0.1:8020 php tests/e2e_full_test.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/Config/database.php';
require_once __DIR__ . '/../src/Middleware/middleware.php';

define('BASE', rtrim(getenv('BASE_URL') ?: 'http://127.0.0.1', '/'));

$pass = 0; $fail = 0; $rows = [];
function check(string $id, string $name, bool $ok, string $detail = ''): void {
    global $pass, $fail, $rows;
    if ($ok) { $pass++; $rows[] = "PASS | $id | $name" . ($detail ? " | $detail" : ''); }
    else     { $fail++; $rows[] = "FAIL | $id | $name" . ($detail ? " | $detail" : ''); }
}

function jar(string $name): string {
    $p = sys_get_temp_dir() . '/pensi_qa_' . $name . '.jar';
    @unlink($p);
    return $p;
}

function http(string $method, string $url, ?array $post = null, ?string $jar = null, ?array $file = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_USERAGENT      => 'PENSI-QA/1.0',
    ]);
    if ($jar) { curl_setopt($ch, CURLOPT_COOKIEJAR, $jar); curl_setopt($ch, CURLOPT_COOKIEFILE, $jar); }
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($file) {
            $post = $post ?? [];
            $post['bukti'] = new CURLFile($file['path'], $file['mime'], basename($file['path']));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post ?? []));
        }
    }
    $raw = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $hlen = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    return ['code' => $code, 'body' => substr($raw, $hlen), 'headers' => substr($raw, 0, $hlen), 'type' => $type];
}

function csrf(string $html): string {
    return preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $html, $m) ? $m[1] : '';
}

function location(array $r): string {
    return preg_match('/^Location:\s*(\S+)/mi', $r['headers'], $m) ? trim($m[1]) : '';
}

function login(string $identifier, string $password, string $jar): array {
    $r = http('GET', BASE . '/login.php', null, $jar);
    $t = csrf($r['body']);
    return http('POST', BASE . '/login.php', [
        'csrf_token' => $t, 'form_type' => 'login', 'identifier' => $identifier, 'password' => $password,
    ], $jar);
}

function register(string $nama, string $nik, string $email, string $hp, string $passwd, string $jar): array {
    $r = http('GET', BASE . '/login.php', null, $jar);
    $t = csrf($r['body']);
    return http('POST', BASE . '/login.php', [
        'csrf_token' => $t, 'form_type' => 'register', 'nama_lengkap' => $nama, 'nik' => $nik,
        'email' => $email, 'no_hp' => $hp, 'password' => $passwd, 'password_confirm' => $passwd,
    ], $jar);
}

function book(string $jar, int $eventId, int $tierId, int $qty, array $names, array $niks, string $voucher = '', string $seats = ''): array {
    $r = http('GET', BASE . "/pemesanan.php?id_event=$eventId&id_tier=$tierId&qty=$qty&kursi=" . urlencode($seats), null, $jar);
    $t = csrf($r['body']);
    $post = [
        'csrf_token' => $t, 'id_event' => $eventId, 'id_tier' => $tierId, 'qty' => $qty,
        'kursi' => $seats, 'nama_peserta' => $names, 'nik_peserta' => $niks, 'kode_voucher' => $voucher,
    ];
    return http('POST', BASE . '/checkout.php', $post, $jar);
}

function code_from(array $r): string {
    return preg_match('/kode=(BK-[A-Z0-9-]+)/', location($r), $m) ? $m[1] : '';
}

function upload_proof(string $jar, string $code, string $file, string $mime = 'image/png'): array {
    $r = http('GET', BASE . "/checkout.php?kode=$code", null, $jar);
    $t = csrf($r['body']);
    return http('POST', BASE . "/checkout.php?kode=$code",
        ['csrf_token' => $t, 'aksi' => 'upload_bukti'], $jar, ['path' => $file, 'mime' => $mime]);
}

function trx(string $code): ?array {
    global $pdo;
    $s = $pdo->prepare('SELECT * FROM transaksi WHERE kode_booking = ?');
    $s->execute([$code]);
    return $s->fetch() ?: null;
}

function tier_row(int $id): array {
    global $pdo;
    $s = $pdo->prepare('SELECT * FROM tiket_tier WHERE id_tier = ?');
    $s->execute([$id]);
    return $s->fetch();
}

function audit_count(string $aksi, string $entitas = ''): int {
    global $pdo;
    if ($entitas !== '') {
        $s = $pdo->prepare('SELECT COUNT(*) FROM audit_logs WHERE aksi=? AND entitas=?');
        $s->execute([$aksi, $entitas]);
    } else {
        $s = $pdo->prepare('SELECT COUNT(*) FROM audit_logs WHERE aksi=?');
        $s->execute([$aksi]);
    }
    return (int)$s->fetchColumn();
}

echo "=== E2E FULL TEST — " . date('Y-m-d H:i:s') . " ===\nBASE=" . BASE . " DB=" . $db_name . "\n\n";

/* ================= A. AUTENTIKASI ================= */
$jAdmin = jar('admin'); $jFin = jar('fin'); $jGate = jar('gate'); $jCust = jar('cust');

foreach ([
    ['admin.eo@eventpro.id', 'dashboard_eo.php', $jAdmin],
    ['finance@eventpro.id', 'verifikasi_pembayaran.php', $jFin],
    ['gate@eventpro.id', 'gate_checkin.php', $jGate],
    ['dewi@student.id', 'dashboard_peserta.php', $jCust],
] as [$email, $target, $j]) {
    $r = login($email, 'password123', $j);
    check('A1-' . $email, "Login $email redirect ke $target",
        $r['code'] === 302 && str_contains(location($r), $target), 'code=' . $r['code'] . ' loc=' . location($r));
}

$jBad = jar('bad');
$r1 = login('dewi@student.id', 'salah-password', $jBad);
check('A2', 'Password salah tidak redirect', $r1['code'] === 200 && !str_contains(location($r1), 'dashboard'));

// Rate limit: identifier baru, 5 gagal lalu ke-6 diblokir
$jRl = jar('rl');
for ($i = 0; $i < 5; $i++) login('qa.ratelimit@test.local', 'x' . $i, $jRl);
$r = login('qa.ratelimit@test.local', 'x-final', $jRl);
check('A3', 'Rate limit login aktif setelah 5 kegagalan', str_contains($r['body'], 'Terlalu banyak'), 'code=' . $r['code']);

// Registrasi akun baru
$jBuyer = jar('buyer');
$nikBuyer = '3174010101060001';
$r = register('QA Buyer', $nikBuyer, 'qa.buyer@test.local', '081299990001', 'password123', $jBuyer);
check('A4', 'Registrasi akun baru berhasil', $r['code'] === 302 && str_contains(location($r), 'dashboard_peserta'));

$r = register('QA Buyer Duplicate', '3174010101060099', 'qa.buyer@test.local', '081299990002', 'password123', jar('dup1'));
check('A5', 'Email duplikat ditolak', $r['code'] === 200 && (stripos($r['body'], 'sudah') !== false || stripos($r['body'], 'terdaftar') !== false));

$r = register('QA NIK Duplicate', $nikBuyer, 'qa.nikdup@test.local', '081299990003', 'password123', jar('dup2'));
check('A6', 'NIK duplikat ditolak', $r['code'] === 200 && (stripos($r['body'], 'nik') !== false));

// Logout
$r = http('GET', BASE . '/logout.php', null, $jBad);
$r2 = http('GET', BASE . '/dashboard_peserta.php', null, $jBad);
check('A7', 'Logout menghapus sesi', $r['code'] === 302 && $r2['code'] === 302);

// RBAC
$r = http('GET', BASE . '/dashboard_eo.php', null, $jCust);
check('A8', 'RBAC customer diblokir dari dashboard_eo (403)', $r['code'] === 403);

// Header keamanan cookie
$r = http('GET', BASE . '/login.php', null, jar('hdr'));
check('A9', 'Cookie sesi HttpOnly + SameSite=Strict',
    stripos($r['headers'], 'HttpOnly') !== false && stripos($r['headers'], 'SameSite=Strict') !== false);

// SQL injection login
$r = login("' OR 1=1 -- ", 'x', jar('sqli'));
check('A10', 'SQL injection login gagal', $r['code'] === 200 && !str_contains($r['body'], 'Dashboard Peserta'));

/* ================= B. KATALOG & DETAIL ================= */
$r = http('GET', BASE . '/index.php');
check('B1', 'Katalog memuat 4 event',
    $r['code'] === 200 && substr_count($r['body'], 'event-card') >= 4);

$r = http('GET', BASE . '/index.php?kategori=2');
check('B2', 'Filter kategori Workshop menyaring event',
    str_contains($r['body'], 'Web Security Bootcamp') && !str_contains($r['body'], 'AI &amp; Cyber Security Summit'));

$r = http('GET', BASE . '/index.php?q=cyber');
check('B3', 'Pencarian real-time/server "cyber"', str_contains($r['body'], 'AI &amp; Cyber Security Summit'));

$r = http('GET', BASE . '/detail_event.php?id_event=1');
check('B4', 'Detail event: rundown + tier + seat map',
    str_contains($r['body'], 'Rundown Acara') && str_contains($r['body'], 'Early Bird')
    && str_contains($r['body'], 'A-001') && str_contains($r['body'], 'PANGGUNG / STAGE'));

$r = http('GET', BASE . '/index.php?theme=01');
check('B5', 'Tema via query param diterapkan', str_contains($r['body'], 'data-theme="01"'));

$r = http('GET', BASE . '/detail_event.php?id_event=3');
check('B6', 'Event Free Standing menampilkan qty, bukan seat map',
    str_contains($r['body'], 'Jumlah tiket') && str_contains($r['body'], 'name="qty"') && !str_contains($r['body'], 'class="seat-chip'));

/* ================= C. BOOKING ================= */
// ambil tier & kursi
$evSeated = (int)$pdo->query("SELECT id_event FROM events WHERE tipe_venue='Seated' ORDER BY id_event LIMIT 1")->fetchColumn();
$tierReg = $pdo->query("SELECT * FROM tiket_tier WHERE id_event=$evSeated AND nama_tier='Reguler' LIMIT 1")->fetch();
$seats = $pdo->query("SELECT id_kursi FROM kursi_event WHERE id_tier=" . (int)$tierReg['id_tier'] . " AND status_kursi='Available' ORDER BY id_kursi LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);

$beforeTier = tier_row((int)$tierReg['id_tier']);
$r = book($jBuyer, $evSeated, (int)$tierReg['id_tier'], 2, ['QA Seat A', 'QA Seat B'], ['3174010101060101', '3174010101060102'], 'HEMAT10', implode(',', $seats));
$codeSeat = code_from($r);
$trxSeat = trx($codeSeat);
$afterTier = tier_row((int)$tierReg['id_tier']);
check('C1', 'Booking seated membuat transaksi Pending', $r['code'] === 302 && $trxSeat && $trxSeat['status_bayar'] === 'Pending', $codeSeat);
check('C2', 'Voucher persen + biaya layanan benar',
    $trxSeat && (int)$trxSeat['diskon'] === (int)round($trxSeat['subtotal'] * 0.10)
    && (int)$trxSeat['biaya_layanan'] === 5000
    && (int)$trxSeat['total_bayar'] === (int)$trxSeat['subtotal'] - (int)$trxSeat['diskon'] + 5000,
    $trxSeat ? "subtotal={$trxSeat['subtotal']} diskon={$trxSeat['diskon']} total={$trxSeat['total_bayar']}" : '');
$seatStatus = $pdo->query("SELECT status_kursi FROM kursi_event WHERE id_kursi IN (" . implode(',', $seats) . ")")->fetchAll(PDO::FETCH_COLUMN);
check('C3', 'Kursi terpilih menjadi Locked', count(array_filter($seatStatus, fn($s) => $s === 'Locked')) === 2, implode(',', $seatStatus));
check('C4', 'Kuota tersedia -2 dan locked +2',
    (int)$afterTier['kuota_tersedia'] === (int)$beforeTier['kuota_tersedia'] - 2
    && (int)$afterTier['kuota_locked'] === (int)$beforeTier['kuota_locked'] + 2,
    "avail {$beforeTier['kuota_tersedia']}→{$afterTier['kuota_tersedia']}");

// Duplicate NIK dalam satu pesanan
$countBefore = (int)$pdo->query('SELECT COUNT(*) FROM transaksi')->fetchColumn();
$r = book($jBuyer, $evSeated, (int)$tierReg['id_tier'], 2, ['QA Dup A', 'QA Dup B'], ['3174010101060103', '3174010101060103'], '', implode(',', array_slice($seats, 0)));
$countAfter = (int)$pdo->query('SELECT COUNT(*) FROM transaksi')->fetchColumn();
check('C5', 'NIK sama dalam satu pesanan ditolak', $countAfter === $countBefore, "trx $countBefore→$countAfter");

// Limit 5 per event
$r = http('GET', BASE . "/pemesanan.php?id_event=$evSeated&id_tier={$tierReg['id_tier']}&qty=6", null, $jBuyer);
check('C6', 'Permintaan 6 tiket ditolak (batas 5)', $r['code'] === 302, 'code=' . $r['code']);

// Expiry
$pdo->prepare('UPDATE transaksi SET batas_bayar_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE kode_booking=?')->execute([$codeSeat]);
release_expired_transactions($pdo);
$exp = trx($codeSeat);
$tierAfterExp = tier_row((int)$tierReg['id_tier']);
$seatAfter = $pdo->query("SELECT status_kursi FROM kursi_event WHERE id_kursi IN (" . implode(',', $seats) . ")")->fetchAll(PDO::FETCH_COLUMN);
check('C7', 'Expiry 15 menit: status Expired + kuota/kursi kembali',
    $exp['status_bayar'] === 'Expired'
    && (int)$tierAfterExp['kuota_tersedia'] === (int)$beforeTier['kuota_tersedia']
    && count(array_filter($seatAfter, fn($s) => $s === 'Available')) === 2,
    'status=' . $exp['status_bayar']);

/* ================= D. CHECKOUT & UPLOAD ================= */
$evFree = (int)$pdo->query("SELECT id_event FROM events WHERE tipe_venue='Free Standing' ORDER BY id_event LIMIT 1")->fetchColumn();
$tierFree = $pdo->query("SELECT * FROM tiket_tier WHERE id_event=$evFree AND nama_tier='Reguler' LIMIT 1")->fetch();
$jPay = jar('pay');
register('QA Payer', '3174010101060002', 'qa.pay@test.local', '081299990004', 'password123', $jPay);
$r = book($jPay, $evFree, (int)$tierFree['id_tier'], 2, ['QA Pay A', 'QA Pay B'], ['3174010101060201', '3174010101060202'], 'HEMAT10');
$codePay = code_from($r);
check('D1', 'Booking free standing berhasil', $r['code'] === 302 && $codePay !== '', $codePay);

$r = http('GET', BASE . "/checkout.php?kode=$codePay", null, $jPay);
check('D2', 'Halaman checkout menampilkan countdown',
    str_contains($r['body'], 'data-deadline=') && str_contains($r['body'], 'Menunggu Bayar'));

// upload invalid
$badFile = sys_get_temp_dir() . '/pensi_qa_bad.txt';
file_put_contents($badFile, "not an image");
$r = upload_proof($jPay, $codePay, $badFile, 'text/plain');
$afterBad = trx($codePay);
check('D3', 'Upload non-gambar ditolak, status tetap Pending',
    $afterBad['status_bayar'] === 'Pending' && empty($afterBad['bukti_bayar']) && ($r['code'] === 302 || stripos($r['body'], 'format') !== false),
    'status=' . $afterBad['status_bayar']);

// upload valid
$proof = __DIR__ . '/../public/uploads/bukti/contoh_bukti.png';
$r = upload_proof($jPay, $codePay, $proof, 'image/png');
$afterUp = trx($codePay);
check('D4', 'Upload bukti valid → Waiting Verification',
    $afterUp['status_bayar'] === 'Waiting Verification' && !empty($afterUp['bukti_bayar']), 'bukti=' . $afterUp['bukti_bayar']);

/* ================= E. FINANCE ================= */
$r = http('GET', BASE . "/verifikasi_pembayaran.php?kode=$codePay", null, $jFin);
$t = csrf($r['body']);
$r = http('POST', BASE . '/verifikasi_pembayaran.php', [
    'csrf_token' => $t, 'aksi' => 'setujui', 'kode_booking' => $codePay,
], $jFin);
$paid = trx($codePay);
$issued = $pdo->prepare('SELECT COUNT(*) FROM e_tickets WHERE id_transaksi=?');
$issued->execute([$paid['id_transaksi']]);
check('E1', 'Finance approve → Paid + 2 e-ticket',
    $paid['status_bayar'] === 'Paid' && (int)$issued->fetchColumn() === 2,
    'status=' . $paid['status_bayar']);

$r = http('GET', BASE . "/etiket_png.php?kode=$codePay&n=1", null, $jPay);
check('E2', 'E-Ticket JPG valid (FFD8)', $r['code'] === 200 && str_starts_with($r['type'], 'image/jpeg') && str_starts_with($r['body'], "\xFF\xD8"), 'bytes=' . strlen($r['body']));
$r = http('GET', BASE . "/etiket_pdf.php?kode=$codePay&n=1", null, $jPay);
check('E3', 'E-Ticket PDF valid (%PDF … %%EOF)', $r['code'] === 200 && str_starts_with($r['body'], '%PDF-') && str_ends_with(trim($r['body']), '%%EOF'));

$jOther = jar('other');
login('rizki@alumni.id', 'password123', $jOther);
$r = http('GET', BASE . "/etiket.php?kode=$codePay", null, $jOther);
check('E4', 'Peserta lain tidak bisa melihat e-ticket orang lain', $r['code'] === 403 || $r['code'] === 404, 'code=' . $r['code']);

// Reject flow
$jRej = jar('rej');
register('QA Reject', '3174010101060003', 'qa.reject@test.local', '081299990005', 'password123', $jRej);
$evFree2 = (int)$pdo->query("SELECT id_event FROM events WHERE tipe_venue='Free Standing' ORDER BY id_event DESC LIMIT 1")->fetchColumn();
$tierFree2 = $pdo->query("SELECT * FROM tiket_tier WHERE id_event=$evFree2 AND nama_tier='VIP' LIMIT 1")->fetch();
$stockBeforeRej = tier_row((int)$tierFree2['id_tier']);
$r = book($jRej, $evFree2, (int)$tierFree2['id_tier'], 1, ['QA Reject A'], ['3174010101060301'], 'POTONG25K');
$codeRej = code_from($r);
$rejSubtotal = (int)trx($codeRej)['subtotal'];
upload_proof($jRej, $codeRej, $proof, 'image/png');

// tolak tanpa catatan (harus gagal)
$r = http('GET', BASE . "/verifikasi_pembayaran.php?kode=$codeRej", null, $jFin);
$t = csrf($r['body']);
$r = http('POST', BASE . '/verifikasi_pembayaran.php', [
    'csrf_token' => $t, 'aksi' => 'tolak', 'kode_booking' => $codeRej, 'catatan_penolakan' => '',
], $jFin);
check('E5', 'Tolak tanpa catatan ditolak', trx($codeRej)['status_bayar'] === 'Waiting Verification');

$r = http('GET', BASE . "/verifikasi_pembayaran.php?kode=$codeRej", null, $jFin);
$t = csrf($r['body']);
$r = http('POST', BASE . '/verifikasi_pembayaran.php', [
    'csrf_token' => $t, 'aksi' => 'tolak', 'kode_booking' => $codeRej,
    'catatan_penolakan' => 'Bukti transfer tidak jelas dan nominal tidak sesuai.',
], $jFin);
$rejected = trx($codeRej);
$stockAfterRej = tier_row((int)$tierFree2['id_tier']);
check('E6', 'Finance reject → Rejected + stok & voucher pulih',
    $rejected['status_bayar'] === 'Rejected'
    && !empty($rejected['catatan_penolakan'])
    && (int)$stockAfterRej['kuota_tersedia'] === (int)$stockBeforeRej['kuota_tersedia'],
    'status=' . $rejected['status_bayar']);

/* ================= F. GATE ================= */
$et = $pdo->prepare('SELECT * FROM e_tickets WHERE id_transaksi=? ORDER BY id_etiket LIMIT 1');
$et->execute([$paid['id_transaksi']]);
$ticket = $et->fetch();

$r = http('GET', BASE . '/gate_checkin.php?kode_tiket=' . urlencode($ticket['kode_tiket']), null, $jGate);
$t = csrf($r['body']);
$r = http('POST', BASE . '/gate_checkin.php', [
    'csrf_token' => $t, 'kode_tiket' => $ticket['kode_tiket'], 'nik_peserta' => $ticket['nik_peserta'],
], $jGate);
check('F1', 'Gate check-in valid berhasil', str_contains($r['body'], 'BERHASIL'));

$r = http('GET', BASE . '/gate_checkin.php?kode_tiket=' . urlencode($ticket['kode_tiket']), null, $jGate);
check('F2', 'Anti-passback menampilkan TIKET SUDAH DIGUNAKAN', str_contains($r['body'], 'TIKET SUDAH DIGUNAKAN'));

$r = http('GET', BASE . '/gate_checkin.php?kode_tiket=TKT-TIDAK-ADA-999', null, $jGate);
check('F3', 'Kode tiket tidak dikenal ditolak', stripos($r['body'], 'TIDAK DITEMUKAN') !== false);

/* ================= G. DASHBOARD EO ================= */
$r = http('GET', BASE . '/dashboard_eo.php', null, $jAdmin);
check('G1', 'Dashboard EO memuat KPI + grafik',
    $r['code'] === 200 && str_contains($r['body'], 'Omzet Kotor')
    && str_contains($r['body'], 'Pendapatan Bersih') && str_contains($r['body'], 'chart-bar'));

$r = http('GET', BASE . '/dashboard_eo.php?id_event=' . $evFree, null, $jAdmin);
check('G2', 'Filter event pada dashboard EO bekerja', $r['code'] === 200 && str_contains($r['body'], 'Digital Art'));

/* ================= H. CRUD ================= */
$r = http('GET', BASE . '/crud_event.php?tab=event', null, $jAdmin);
$t = csrf($r['body']);
$r = http('POST', BASE . '/crud_event.php', [
    'csrf_token' => $t, 'action' => 'save_event', 'tab' => 'event', 'id_event' => '',
    'judul_event' => 'QA Test Event', 'id_kat_event' => 2, 'penyelenggara' => 'QA Team',
    'lokasi' => 'QA City', 'tipe_venue' => 'Seated', 'tgl_mulai' => '2027-03-01T10:00',
    'tgl_selesai' => '2027-03-01T15:00', 'status_event' => 'Published',
], $jAdmin);
$newEventId = (int)$pdo->query("SELECT id_event FROM events WHERE judul_event='QA Test Event' LIMIT 1")->fetchColumn();
check('H1', 'CRUD event: tambah event baru', $newEventId > 0, 'id=' . $newEventId);

$r = http('GET', BASE . '/crud_event.php?tab=tier&event_id=' . $newEventId, null, $jAdmin);
$t = csrf($r['body']);
$r = http('POST', BASE . '/crud_event.php', [
    'csrf_token' => $t, 'action' => 'save_tier', 'tab' => 'tier', 'id_event' => $newEventId,
    'nama_tier' => 'QA Tier', 'harga' => 123000, 'kuota_total' => 10, 'batas_jual' => '2027-02-20T23:59',
], $jAdmin);
$newTierId = (int)$pdo->query("SELECT id_tier FROM tiket_tier WHERE id_event=$newEventId LIMIT 1")->fetchColumn();
check('H2', 'CRUD tier: tambah tier + kuota', $newTierId > 0, 'id=' . $newTierId . ' code=' . $r['code'] . ' loc=' . location($r));

$r = http('GET', BASE . '/crud_event.php?tab=kursi&event_id=' . $newEventId . '&tier_id=' . $newTierId, null, $jAdmin);
$t = csrf($r['body']);
$r = http('POST', BASE . '/crud_event.php', [
    'csrf_token' => $t, 'action' => 'generate_seats', 'tab' => 'kursi',
    'id_tier' => $newTierId, 'prefix' => 'QA', 'count' => 5,
], $jAdmin);
$seatCount = (int)$pdo->query("SELECT COUNT(*) FROM kursi_event WHERE id_tier=$newTierId")->fetchColumn();
check('H3', 'Seat generator membuat 5 kursi', $seatCount === 5, 'count=' . $seatCount);

// update kuota
$r = http('GET', BASE . '/crud_event.php?tab=tier&event_id=' . $newEventId, null, $jAdmin);
$t = csrf($r['body']);
$r = http('POST', BASE . '/crud_event.php', [
    'csrf_token' => $t, 'action' => 'save_tier', 'tab' => 'tier', 'id_event' => $newEventId,
    'id_tier' => $newTierId, 'nama_tier' => 'QA Tier', 'harga' => 123000,
    'kuota_total' => 12, 'batas_jual' => '2027-02-20T23:59',
], $jAdmin);
$tierUpd = $pdo->query("SELECT kuota_total,kuota_tersedia FROM tiket_tier WHERE id_tier=$newTierId")->fetch();
check('H4', 'Edit kuota menyesuaikan kuota_tersedia', (int)$tierUpd['kuota_total'] === 12 && (int)$tierUpd['kuota_tersedia'] === 12,
    "total={$tierUpd['kuota_total']} avail={$tierUpd['kuota_tersedia']}");

// delete event (tanpa transaksi)
$r = http('GET', BASE . '/crud_event.php?tab=event', null, $jAdmin);
$t = csrf($r['body']);
$r = http('POST', BASE . '/crud_event.php', [
    'csrf_token' => $t, 'action' => 'delete_event', 'tab' => 'event', 'id_event' => $newEventId,
], $jAdmin);
$exists = (int)$pdo->query("SELECT COUNT(*) FROM events WHERE id_event=$newEventId")->fetchColumn();
check('H5', 'Hapus event tanpa transaksi berhasil', $exists === 0);

// Voucher CRUD
$r = http('GET', BASE . '/kelola_voucher.php', null, $jAdmin);
$t = csrf($r['body']);
$r = http('POST', BASE . '/kelola_voucher.php', [
    'csrf_token' => $t, 'action' => 'save_voucher', 'kode_voucher' => 'QATEST10',
    'tipe_potongan' => 'Persen', 'nilai_potongan' => 10, 'min_transaksi' => 50000,
    'kuota_pakai' => 5, 'expired_date' => '2027-01-31',
], $jAdmin);
$v = $pdo->query("SELECT * FROM voucher_promo WHERE kode_voucher='QATEST10'")->fetch();
check('H6', 'Voucher CRUD: tambah voucher persen', $v && $v['tipe_potongan'] === 'Persen', $v ? 'id=' . $v['id_voucher'] : '');

$r = http('GET', BASE . '/kelola_voucher.php', null, $jAdmin);
$t = csrf($r['body']);
$r = http('POST', BASE . '/kelola_voucher.php', [
    'csrf_token' => $t, 'action' => 'deactivate_voucher', 'id_voucher' => $v['id_voucher'],
], $jAdmin);
$v2 = $pdo->query("SELECT kuota_pakai FROM voucher_promo WHERE id_voucher=" . (int)$v['id_voucher'])->fetchColumn();
check('H7', 'Voucher dapat dinonaktifkan (kuota 0)', (int)$v2 === 0);

$r = http('GET', BASE . '/audit_log.php?aksi=CREATE', null, $jAdmin);
check('H8', 'Audit log menampilkan aksi CREATE', $r['code'] === 200 && str_contains($r['body'], 'CREATE'));

/* ================= I. KEAMANAN TAMBAHAN ================= */
$r = http('POST', BASE . '/checkout.php', ['id_event' => 1], $jPay);
check('I1', 'POST tanpa CSRF ditolak 403', $r['code'] === 403, 'code=' . $r['code']);

$jXss = jar('xss');
$payload = '<script>alert(1)</script>';
$r = register($payload, '3174010101060004', 'qa.xss@test.local', '081299990006', 'password123', $jXss);
$r2 = http('GET', BASE . '/dashboard_peserta.php', null, $jXss);
check('I2', 'Nama berisi XSS di-escape di dashboard',
    $r['code'] === 302 && str_contains($r2['body'], '&lt;script&gt;') && !str_contains($r2['body'], $payload));

$r = http('GET', BASE . '/bukti.php?kode=../../config.local.php', null, $jPay);
check('I3', 'Path traversal bukti.php ditolak', $r['code'] === 404 || $r['code'] === 403, 'code=' . $r['code']);

/* ================= J. TEMA ================= */
$themeOk = true;
foreach (['01','02','03','04','05','06','07','08','09','10'] as $th) {
    $r = http('GET', BASE . "/index.php?theme=$th");
    if (!str_contains($r['body'], 'data-theme="' . $th . '"')) { $themeOk = false; break; }
}
check('J1', '10 tema dapat dipilih via query param', $themeOk);

$r = http('GET', BASE . "/etiket.php?kode=$codePay", null, $jPay);
check('J2', 'Halaman e-ticket memiliki CSS print', str_contains($r['body'], '@media print'));

/* ================= HASIL ================= */
echo implode("\n", $rows) . "\n\n";
echo "TOTAL: $pass PASS, $fail FAIL\n";
exit($fail === 0 ? 0 : 1);
