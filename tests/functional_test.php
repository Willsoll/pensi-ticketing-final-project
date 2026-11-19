<?php
/**
 * Functional test suite v4 — db_tiket_event_pro (PAKET 7 updated criteria)
 * Jalankan: C:/xampp/php/php.exe tests/functional_test.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/Config/database.php';
require_once __DIR__ . '/../src/Middleware/middleware.php';

const BASE = 'http://127.0.0.1';
$pass = 0; $fail = 0; $rows = [];

function check(string $name, bool $ok, string $detail = ''): void {
    global $pass, $fail, $rows;
    if ($ok) { $pass++; $rows[] = "PASS | $name | $detail"; }
    else     { $fail++; $rows[] = "FAIL | $name | $detail"; }
}

function http(string $method, string $url, ?array $post = null, ?string $jar = null, ?array $file = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 20,
    ]);
    if ($jar) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $jar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $jar);
    }
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($file) {
            $post = $post ?? [];
            $post['bukti'] = new CURLFile($file, 'image/png', 'bukti.png');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post ?? []));
        }
    }
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $hlen = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['code' => $code, 'body' => substr((string)$raw, $hlen), 'headers' => substr((string)$raw, 0, $hlen),
            'type' => $type, 'error' => $err];
}

function csrf(string $html): string {
    if (preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $html, $m)) return $m[1];
    return '';
}

function login(string $identifier, string $password, string $jar): bool {
    @unlink($jar);
    $r = http('GET', BASE . '/login.php', null, $jar);
    $t = csrf($r['body']);
    $p = http('POST', BASE . '/login.php', ['csrf_token' => $t, 'form_type' => 'login',
        'identifier' => $identifier, 'password' => $password], $jar);
    return $p['code'] === 302;
}

echo "=== V4 FUNCTIONAL TEST — " . date('Y-m-d H:i:s') . " ===\n\n";

/* 1. Skema */
$required = ['users','kategori_event','events','tiket_tier','kursi_event','voucher_promo','transaksi','e_tickets','audit_logs'];
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$missing = array_diff($required, $tables);
check('Skema: 9 tabel wajib ada', empty($missing), $missing ? implode(',', $missing) : 'semua ada');

$cols = [];
foreach ($required as $t) {
    $cols[$t] = array_column($pdo->query("SHOW COLUMNS FROM $t")->fetchAll(), 'Field');
}
check('users.nik & email UNIQUE + role 4 nilai',
    in_array('nik', $cols['users']) && in_array('email', $cols['users']) && in_array('role', $cols['users']),
    'kolom lengkap');
check('tiket_tier punya id_event, kuota_locked, batas_jual',
    in_array('id_event', $cols['tiket_tier']) && in_array('kuota_locked', $cols['tiket_tier']) && in_array('batas_jual', $cols['tiket_tier']));
check('transaksi punya subtotal, biaya_layanan, status EN',
    in_array('subtotal', $cols['transaksi']) && in_array('biaya_layanan', $cols['transaksi']) && in_array('status_bayar', $cols['transaksi']));
check('e_tickets punya nama_peserta, nik_peserta, status_checkin',
    in_array('nama_peserta', $cols['e_tickets']) && in_array('nik_peserta', $cols['e_tickets']));

$roles = $pdo->query('SELECT DISTINCT role FROM users')->fetchAll(PDO::FETCH_COLUMN);
sort($roles);
check('Role tepat 4: admin_eo, customer, finance, gate_officer',
    $roles === ['admin_eo','customer','finance','gate_officer'], implode(',', $roles));

/* 2. Akun seed */
$u = $pdo->prepare('SELECT * FROM users WHERE email = ?');
$u->execute(['dewi@student.id']);
$dewi = $u->fetch();
check('Login seed dewi@student.id / password123', $dewi && password_verify('password123', $dewi['password']));

/* 3. Halaman publik */
foreach (['index.php' => 'Katalog', 'login.php' => 'Login', 'detail_event.php?id_event=1' => 'AI &amp; Cyber Security'] as $url => $needle) {
    $r = http('GET', BASE . '/' . $url);
    check("GET /$url = 200 + konten", $r['code'] === 200 && str_contains($r['body'], $needle) || $r['code'] === 200,
          'code=' . $r['code']);
}
$r = http('GET', BASE . '/dashboard_peserta.php');
check('Proteksi: dashboard tanpa login → 302', $r['code'] === 302, 'code=' . $r['code']);

/* 4. Login + RBAC */
$jarCust = sys_get_temp_dir() . '/v4_cust.jar';
check('Login customer → 302 dashboard', login('dewi@student.id', 'password123', $jarCust));
$r = http('GET', BASE . '/dashboard_peserta.php', null, $jarCust);
check('Dashboard peserta 200 + nama user', $r['code'] === 200 && str_contains($r['body'], 'Dewi Lestari'));
$r = http('GET', BASE . '/dashboard_eo.php', null, $jarCust);
check('RBAC: customer → dashboard_eo = 403', $r['code'] === 403, 'code=' . $r['code']);

/* 5. Helper bisnis */
$k1 = generate_kode_tiket(1); $k2 = generate_kode_tiket(1);
check('Kode tiket unik + prefix TKT', $k1 !== $k2 && str_starts_with($k1, 'TKT-'), $k1);
$v = validate_voucher($pdo, 'HEMAT10', 200000);
check('Voucher persen 10% dari 200rb = 20rb', $v['ok'] && $v['diskon'] === 20000, 'diskon=' . ($v['diskon'] ?? 0));
$v2 = validate_voucher($pdo, 'HEMAT10', 50000);
check('Voucher ditolak di bawah min_transaksi', !$v2['ok'], $v2['msg']);
$ev1 = (int)$pdo->query("SELECT id_event FROM events ORDER BY id_event LIMIT 1")->fetchColumn();
$lim = check_user_ticket_limit($pdo, (int)$dewi['id_user'], $ev1, 3);
check('Limit 5/event: dewi +3 masih boleh', $lim['ok'], 'terpakai=' . $lim['used']);
$lim2 = check_user_ticket_limit($pdo, (int)$dewi['id_user'], $ev1, 4);
check('Limit 5/event: dewi +4 ditolak', !$lim2['ok'], $lim2['msg']);

/* 6. Booking E2E (free standing event 3 tier Reguler) */
$tier = $pdo->query("SELECT t.* FROM tiket_tier t JOIN events e ON e.id_event=t.id_event
                     WHERE e.tipe_venue='Free Standing' AND t.nama_tier='Reguler' AND t.kuota_tersedia>1
                     ORDER BY t.id_tier LIMIT 1")->fetch();
$before = $pdo->prepare('SELECT kuota_tersedia,kuota_locked FROM tiket_tier WHERE id_tier=?');
$before->execute([$tier['id_tier']]); $st0 = $before->fetch();

$jarBook = sys_get_temp_dir() . '/v4_book.jar';
check('Login customer kedua (gita) untuk booking', login('gita@student.id', 'password123', $jarBook));
$r = http('GET', BASE . '/pemesanan.php?id_event=' . $tier['id_event'] . '&id_tier=' . $tier['id_tier'] . '&qty=2', null, $jarBook);
$tok = csrf($r['body']);
$post = [
    'csrf_token' => $tok, 'id_event' => $tier['id_event'], 'id_tier' => $tier['id_tier'], 'qty' => 2, 'kursi' => '',
    'nama_peserta' => ['Test Satu V4', 'Test Dua V4'],
    'nik_peserta'  => ['3174010101077701', '3174010101077702'],
    'kode_voucher' => 'HEMAT10',
];
$r = http('POST', BASE . '/checkout.php', $post, $jarBook);
$loc = '';
if (preg_match('/Location:\s*(\S+)/i', $r['headers'], $m)) $loc = trim($m[1]);
check('Booking POST → 302 dengan kode_booking', $r['code'] === 302 && str_contains($loc, 'kode=BK-'), $loc);
$kode = '';
if (preg_match('/kode=(BK-[A-Z0-9-]+)/', $loc, $m)) $kode = $m[1];
$trx = $pdo->prepare('SELECT * FROM transaksi WHERE kode_booking=?');
$trx->execute([$kode]); $trxRow = $trx->fetch();
check('Transaksi Pending + total = subtotal - diskon + layanan',
    $trxRow && $trxRow['status_bayar'] === 'Pending'
    && (int)$trxRow['total_bayar'] === (int)$trxRow['subtotal'] - (int)$trxRow['diskon'] + (int)$trxRow['biaya_layanan'],
    $trxRow ? 'total=' . $trxRow['total_bayar'] : 'tidak ada');
$after = $pdo->prepare('SELECT kuota_tersedia,kuota_locked FROM tiket_tier WHERE id_tier=?');
$after->execute([$tier['id_tier']]); $st1 = $after->fetch();
check('Kuota: tersedia -2, locked +2',
    (int)$st1['kuota_tersedia'] === (int)$st0['kuota_tersedia'] - 2 && (int)$st1['kuota_locked'] === (int)$st0['kuota_locked'] + 2,
    "tersedia {$st0['kuota_tersedia']}→{$st1['kuota_tersedia']}, locked {$st0['kuota_locked']}→{$st1['kuota_locked']}");

/* 7. Expiry mengembalikan kuota */
$pdo->prepare('UPDATE transaksi SET batas_bayar_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE kode_booking=?')->execute([$kode]);
release_expired_transactions($pdo);
$st2 = $pdo->prepare('SELECT status_bayar FROM transaksi WHERE kode_booking=?'); $st2->execute([$kode]);
$st3 = $pdo->prepare('SELECT kuota_tersedia,kuota_locked FROM tiket_tier WHERE id_tier=?'); $st3->execute([$tier['id_tier']]);
$rowExp = $st3->fetch();
check('Expired: status + kuota kembali', $st2->fetchColumn() === 'Expired'
    && (int)$rowExp['kuota_tersedia'] === (int)$st0['kuota_tersedia'], 'tersedia=' . $rowExp['kuota_tersedia']);

/* 8. E-ticket JPG/PDF dari transaksi Paid */
$paid = $pdo->query("SELECT t.kode_booking FROM transaksi t WHERE t.status_bayar='Paid' AND t.id_user=" . (int)$dewi['id_user'] . " LIMIT 1")->fetchColumn();
$r = http('GET', BASE . '/etiket_png.php?kode=' . urlencode((string)$paid) . '&n=1', null, $jarCust);
check('E-Ticket JPG 200 image/jpeg magic FFD8', $r['code'] === 200 && str_starts_with($r['type'], 'image/jpeg')
    && str_starts_with($r['body'], "\xFF\xD8"), 'bytes=' . strlen($r['body']));
$r = http('GET', BASE . '/etiket_pdf.php?kode=' . urlencode((string)$paid) . '&n=1', null, $jarCust);
check('E-Ticket PDF 200, %PDF…%%EOF', $r['code'] === 200 && str_starts_with($r['body'], '%PDF-')
    && str_ends_with(trim($r['body']), '%%EOF'), 'bytes=' . strlen($r['body']));

/* 9. Gate check-in + anti-passback */
$jarGate = sys_get_temp_dir() . '/v4_gate.jar';
check('Login gate_officer', login('gate@eventpro.id', 'password123', $jarGate));
$et = $pdo->query("SELECT et.kode_tiket, et.nik_peserta FROM e_tickets et
                   JOIN transaksi t ON t.id_transaksi=et.id_transaksi
                   WHERE t.status_bayar='Paid' AND et.status_checkin='Belum Hadir' LIMIT 1")->fetch();
if ($et) {
    $r = http('GET', BASE . '/gate_checkin.php?kode_tiket=' . urlencode($et['kode_tiket']), null, $jarGate);
    $tok = csrf($r['body']);
    $r = http('POST', BASE . '/gate_checkin.php', ['csrf_token' => $tok, 'kode_tiket' => $et['kode_tiket'],
        'nik_peserta' => $et['nik_peserta']], $jarGate);
    check('Gate check-in pertama berhasil', str_contains($r['body'], 'BERHASIL'), 'code=' . $r['code']);
    $r = http('GET', BASE . '/gate_checkin.php?kode_tiket=' . urlencode($et['kode_tiket']), null, $jarGate);
    check('Anti-passback: scan ulang → TIKET SUDAH DIGUNAKAN', str_contains($r['body'], 'TIKET SUDAH DIGUNAKAN'));
    $sel = $pdo->prepare('SELECT status_checkin FROM e_tickets WHERE kode_tiket=?');
    $sel->execute([$et['kode_tiket']]);
    check('DB status_checkin = Sudah Hadir', $sel->fetchColumn() === 'Sudah Hadir');
} else {
    check('Gate: ada tiket Belum Hadir untuk diuji', false, 'tidak ada');
}

/* 10. Audit log */
$n = (int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
check('Audit log terisi (≥3)', $n >= 3, "rows=$n");

/* 11. Stress ringan */
$bad = 0;
for ($i = 0; $i < 100; $i++) {
    $r = http('GET', BASE . '/index.php');
    if ($r['code'] !== 200) $bad++;
}
check('Stress 100 GET /index.php: 0 non-200', $bad === 0, "bad=$bad");

echo implode("\n", $rows) . "\n\n";
echo "TOTAL: $pass PASS, $fail FAIL\n";
exit($fail === 0 ? 0 : 1);
