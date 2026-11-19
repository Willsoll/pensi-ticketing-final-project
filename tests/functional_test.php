<?php
/**
 * functional_test.php
 * Functional + smoke test suite for the Pensi School PHP/MySQL ticketing app.
 *
 * Run:  C:/xampp/php/php.exe C:/xampp/htdocs/tests/functional_test.php
 *
 * Requirements:
 *   - Apache serving http://127.0.0.1 (DocumentRoot C:/xampp/htdocs/public)
 *   - MySQL db_pensi_sekolah reachable as pensi_app / PensiApp2026!Secure#Pass
 *
 * Rules followed:
 *   - Only this test file is created/modified; application code is NEVER touched.
 *   - Every DB mutation is rolled back or explicitly deleted: no test rows left behind.
 *   - Prints a PASS/FAIL matrix plus a summary.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('BASE_URL', 'http://127.0.0.1');
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_pensi_sekolah');
define('DB_USER', 'pensi_app');
define('DB_PASS', 'PensiApp2026!Secure#Pass');

require __DIR__ . '/../src/Config/database.php';    // gives $pdo
require __DIR__ . '/../src/Middleware/middleware.php'; // gives helpers + functions under test

$results = [];
$passCount = 0;
$failCount = 0;

function check(string $name, bool $cond, string $detail = ''): void
{
    global $results, $passCount, $failCount;
    $results[] = ['name' => $name, 'pass' => $cond, 'detail' => $detail];
    if ($cond) {
        $passCount++;
        echo sprintf("PASS  | %-55s | %s\n", $name, $detail);
    } else {
        $failCount++;
        echo sprintf("FAIL  | %-55s | %s\n", $name, $detail);
    }
}

/* ------------------------------------------------------------------ */
/* cHTTP helper (cURL, no redirect following, captures cookies/loc)   */
/* ------------------------------------------------------------------ */
function curl_req(string $url, ?string $cookie = null, ?array $post = null): array
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    $headers = [];
    if ($cookie) {
        $headers[] = 'Cookie: ' . $cookie; // cookie is already "PHPSESSID=..."
    }
    if ($post) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    if ($headers) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $raw = curl_exec($ch);
    $httpCode = (string)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['code' => '0', 'error' => $err, 'sessid' => null, 'loc' => null, 'body' => '', 'headers' => []];
    }
    $headerStr = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    $headerLines = explode("\r\n", $headerStr);
    $sessid = null;
    $loc = null;
    foreach ($headerLines as $h) {
        if (stripos($h, 'Set-Cookie:') === 0 && preg_match('/PHPSESSID=([^;]+)/', $h, $m)) {
            $sessid = $m[1];
        }
        if (stripos($h, 'Location:') === 0) {
            $loc = trim(substr($h, strpos($h, ':') + 1));
        }
    }
    return ['code' => $httpCode, 'error' => $err, 'sessid' => $sessid, 'loc' => $loc, 'body' => $body, 'headers' => $headerLines];
}

function loginAs(string $identifier, string $password): ?string
{
    $r = curl_req(BASE_URL . '/login.php');
    if ($r['code'] !== '200' || !$r['sessid']) {
        return null;
    }
    $csrf = null;
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $r['body'] ?? '', $m)) {
        $csrf = $m[1];
    }
    if (!$csrf) {
        return null;
    }
    $r = curl_req(BASE_URL . '/login.php', 'PHPSESSID=' . $r['sessid'], [
        'csrf_token'  => $csrf,
        'form_type'   => 'login',
        'identifier'  => $identifier,
        'password'    => $password,
    ]);
    return $r['sessid']; // new session id issued after session_regenerate_id(true)
}

/**
 * Execute a single-column SELECT and return the scalar first column (null if none).
 */
function scalar(string $sql, array $params = [])
{
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

/* ------------------------------------------------------------------ */
/* TESTS                                                              */
/* ------------------------------------------------------------------ */

// ---- 1. DB connection + all tables present ----
try {
    $pdo->query('SELECT 1');
    $tables = [];
    foreach ($pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . DB_NAME . "'") as $row) {
        $tables[] = strtolower((string)$row['TABLE_NAME']);
    }
    $required = ['users', 'jenis_tiket', 'pemesanan', 'detail_tiket_satuan', 'kursi_pensi',
                 'kode_promo', 'rundown_acara', 'audit_logs', 'login_attempts'];
    $missing = array_diff($required, $tables);
    check('1. DB connection & all 9 tables present', empty($missing),
        'tables=' . implode(',', $tables) . (empty($missing) ? '' : ' missing=' . implode(',', $missing)));
} catch (Throwable $e) {
    check('1. DB connection & all 9 tables present', false, $e->getMessage());
}

// ---- 2. Six seed accounts verify + expected role/tipe ----
$expected = [
    'admin_osis'     => ['role' => 'admin_osis',     'tipe' => 'Internal'],
    'panitia_keu'    => ['role' => 'panitia_keuangan', 'tipe' => 'Internal'],
    'petugas_gate'   => ['role' => 'petugas_gate',   'tipe' => 'Internal'],
    'dewi_siswa'     => ['role' => 'pembeli',        'tipe' => 'Internal'],
    'rizki_alumni'   => ['role' => 'pembeli',        'tipe' => 'Eksternal'],
    'maya_tamu'      => ['role' => 'pembeli',        'tipe' => 'Eksternal'],
];
$seedDetail = [];
$seedOk = true;
try {
    $placeholders = implode(',', array_fill(0, count($expected), '?'));
    $stmt = $pdo->prepare("SELECT username, role, tipe_pengguna, password FROM users WHERE username IN ($placeholders) ORDER BY username");
    $stmt->execute(array_keys($expected));
    while ($u = $stmt->fetch()) {
        $uname = (string)$u['username'];
        $exp = $expected[$uname] ?? null;
        $ok = $exp && password_verify('password123', (string)$u['password'])
            && (string)$u['role'] === $exp['role']
            && (string)$u['tipe_pengguna'] === $exp['tipe'];
        $seedDetail[] = $uname . '=' . ($ok ? 'OK' : 'FAIL');
        if (!$ok) {
            $seedOk = false;
        }
    }
    // ensure all 6 were found
    $found = count($seedDetail);
    if ($found < 6) {
        $seedOk = false;
        $seedDetail[] = 'only ' . $found . ' of 6 seed accounts found';
    }
} catch (Throwable $e) {
    $seedOk = false;
    $seedDetail[] = 'ERROR: ' . $e->getMessage();
}
check('2. 6 seed accounts password_verify(password123) + role/tipe', $seedOk, implode('; ', $seedDetail));

// ---- 3. HTTP GET / 200 + PENSI SCHOOL ; /login.php 200 ; /dashboard_peserta.php unauth 302 to login ----
$rIndex = curl_req(BASE_URL . '/');
$check3a = ($rIndex['code'] === '200' && strpos($rIndex['body'] ?? '', 'PENSI SCHOOL') !== false);
check('3a. GET / returns 200 and contains PENSI SCHOOL', $check3a, 'code=' . $rIndex['code']);

$rLoginPage = curl_req(BASE_URL . '/login.php');
$check3b = ($rLoginPage['code'] === '200');
check('3b. GET /login.php returns 200', $check3b, 'code=' . $rLoginPage['code']);

$rDashUnauth = curl_req(BASE_URL . '/dashboard_peserta.php');
$check3c = ($rDashUnauth['code'] === '302' && strtolower((string)($rDashUnauth['loc'] ?? '')) === 'login.php');
check('3c. GET /dashboard_peserta.php unauthenticated 302 to login.php', $check3c, 'code=' . $rDashUnauth['code'] . ' loc=' . ($rDashUnauth['loc'] ?? '-'));

// ---- 4. Login flow with cookies: GET login, parse CSRF, POST dewi_siswa/password123 -> 302 + cookie ----
$buyerCookie = null;
$loginFlowDetail = [];
try {
    $g = curl_req(BASE_URL . '/login.php');
    $loginFlowDetail[] = 'get_login_code=' . $g['code'];
    $csrf = null;
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $g['body'] ?? '', $m)) {
        $csrf = $m[1];
    }
    $loginFlowDetail[] = 'csrf_parsed=' . ($csrf ? 'yes' : 'no');
    if ($g['code'] === '200' && $g['sessid'] && $csrf) {
        $p = curl_req(BASE_URL . '/login.php', 'PHPSESSID=' . $g['sessid'], [
            'csrf_token'  => $csrf,
            'form_type'   => 'login',
            'identifier'  => 'dewi_siswa',
            'password'    => 'password123',
        ]);
        $loginFlowDetail[] = 'post_login_code=' . $p['code'];
        $loginFlowDetail[] = 'post_login_loc=' . ($p['loc'] ?? '-');
        $loginFlowDetail[] = 'new_cookie_set=' . ($p['sessid'] ? 'yes' : 'no');
        $buyerCookie = $p['sessid'] ? 'PHPSESSID=' . $p['sessid'] : null;
        $check4 = ($p['code'] === '302' && strtolower((string)($p['loc'] ?? '')) === 'dashboard_peserta.php' && $p['sessid']);
    } else {
        $check4 = false;
    }
} catch (Throwable $e) {
    $check4 = false;
    $loginFlowDetail[] = 'ERROR: ' . $e->getMessage();
    $buyerCookie = null;
}
check('4. Login flow dewi_siswa -> 302 dashboard_peserta.php + session cookie', $check4, implode('; ', $loginFlowDetail));

// ---- 5. After login GET dashboard_peserta.php 200 contains user name ----
$check5 = false;
$detail5 = 'no buyer session';
if ($buyerCookie) {
    $d = curl_req(BASE_URL . '/dashboard_peserta.php', $buyerCookie);
    $nama = '';
    try {
        $stmt = $pdo->prepare("SELECT nama FROM users WHERE username = 'dewi_siswa' LIMIT 1");
        $stmt->execute();
        $nama = (string)($stmt->fetchColumn() ?? '');
    } catch (Throwable $e) {
        $detail5 = 'db nama lookup error: ' . $e->getMessage();
    }
    $check5 = ($d['code'] === '200' && $nama !== '' && strpos($d['body'] ?? '', $nama) !== false);
    $detail5 = 'code=' . $d['code'] . ' nama=' . $nama . ' found_in_body=' . ($nama !== '' && strpos($d['body'] ?? '', $nama) !== false ? 'yes' : 'no');
}
check('5. Authenticated dashboard_peserta.php 200 contains user name', $check5, $detail5);

// ---- 6. Expiry: temp Menunggu Bayar order w/ past deadline -> release_expired_transactions -> Kadaluarsa + stock restored ----
$check6 = false;
$detail6 = '';
try {
    $idUser = (int)scalar("SELECT id_user FROM users WHERE username = 'dewi_siswa' LIMIT 1");

    // Isolated jenis_tiket + kursi_pensi (deleted in cleanup -> no leftover rows, no cross-order interference)
    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO jenis_tiket (nama_jenis, target_pembeli, tipe_tiket, harga, stok_tiket, stok_tersedia, stok_tertahan)
                   VALUES ('TEST EXPIRY','Semua','Free Standing',10000,10,9,1)")->execute();
    $idJenis = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO kursi_pensi (id_jenis, nomor_kursi, status_kursi) VALUES (?, 'TEST-01', 'Available')")->execute([$idJenis]);
    $idKursi = (int)$pdo->lastInsertId();
    $origStok = (int)scalar("SELECT stok_tersedia FROM jenis_tiket WHERE id_jenis = ?", [$idJenis]);
    $pdo->commit();

    $kode = 'TEST_EXPIRE_' . bin2hex(random_bytes(4));
    $pdo->prepare("INSERT INTO pemesanan (kode_transaksi, id_user, id_jenis, jumlah, diskon, total_harga, batas_waktu_bayar, status_bayar, tgl_pesan)
                   VALUES (?, ?, ?, 1, 0, 10000, DATE_SUB(NOW(), INTERVAL 10 MINUTE), 'Menunggu Bayar', NOW())")
        ->execute([$kode, $idUser, $idJenis]);
    $idPesan = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO detail_tiket_satuan (id_pesan, kode_tiket_unik, nama_pemegang_tiket, nik_pemegang_tiket, id_kursi, status_checkin)
                   VALUES (?, ?, 'Test User', '3273011005990001', ?, 'Belum Masuk')")
        ->execute([$idPesan, $kode . '-T1', $idKursi]);
    $pdo->prepare("UPDATE kursi_pensi SET status_kursi = 'Locked' WHERE id_kursi = ?")->execute([$idKursi]);

    release_expired_transactions($pdo); // not inside a transaction -> performs its own

    $statusAfter   = (string)scalar("SELECT status_bayar FROM pemesanan WHERE id_pesan = ?", [$idPesan]);
    $stokAfter     = (int)scalar("SELECT stok_tersedia FROM jenis_tiket WHERE id_jenis = ?", [$idJenis]);
    $stokTertahanAfter = (int)scalar("SELECT stok_tertahan FROM jenis_tiket WHERE id_jenis = ?", [$idJenis]);
    $kursiAfter    = (string)scalar("SELECT status_kursi FROM kursi_pensi WHERE id_kursi = ?", [$idKursi]);

    $check6 = ($statusAfter === 'Kadaluarsa' && $stokAfter === $origStok + 1 && $stokTertahanAfter === 0 && $kursiAfter === 'Available');
    $detail6 = "status=$statusAfter stok_tersedia=$stokAfter (expected " . ($origStok + 1) . ") stok_tertahan=$stokTertahanAfter kursi=$kursiAfter";

    // Cleanup: remove test rows and restore nothing else needed (isolated jenis/kursi deleted)
    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM detail_tiket_satuan WHERE id_pesan = ?")->execute([$idPesan]);
    $pdo->prepare("DELETE FROM pemesanan WHERE id_pesan = ?")->execute([$idPesan]);
    $pdo->prepare("DELETE FROM kursi_pensi WHERE id_kursi = ?")->execute([$idKursi]);
    $pdo->prepare("DELETE FROM jenis_tiket WHERE id_jenis = ?")->execute([$idJenis]);
    $pdo->commit();

    $leftover = (int)scalar("SELECT COUNT(*) FROM pemesanan WHERE kode_transaksi LIKE 'TEST_EXPIRE_%'");
    $check6 = $check6 && $leftover === 0;
    $detail6 .= '; leftover_rows=' . $leftover;
} catch (Throwable $e) {
    $check6 = false;
    $detail6 = 'ERROR: ' . $e->getMessage();
    try { if ($pdo->inTransaction()) { $pdo->rollBack(); } } catch (Throwable $e2) {}
}
check('6. Expiry: Menunggu Bayar past deadline -> Kadaluarsa, stock restored, cleaned up', $check6, $detail6);

// ---- 7. Ticket limit: 5 Lunas tickets for same user -> check_user_ticket_limit(...,1) === false ----
$check7 = false;
$detail7 = '';
try {
    $idUser = (int)scalar("SELECT id_user FROM users WHERE username = 'dewi_siswa' LIMIT 1");
    $idJenis = (int)scalar("SELECT id_jenis FROM jenis_tiket ORDER BY id_jenis LIMIT 1");
    $existing = (int)scalar("SELECT COALESCE(SUM(jumlah),0) FROM pemesanan WHERE id_user = ? AND status_bayar IN ('Lunas','Menunggu Bayar','Menunggu Verifikasi')", [$idUser]);

    $pdo->beginTransaction();
    for ($i = 0; $i < 5; $i++) {
        $pdo->prepare("INSERT INTO pemesanan (kode_transaksi, id_user, id_jenis, jumlah, diskon, total_harga, batas_waktu_bayar, status_bayar, tgl_pesan)
                       VALUES (?, ?, ?, 1, 0, 10000, DATE_ADD(NOW(), INTERVAL 1 DAY), 'Lunas', NOW())")
            ->execute(['TEST_LIMIT_' . bin2hex(random_bytes(2)) . '_' . $i, $idUser, $idJenis]);
    }
    $rFalse = check_user_ticket_limit($pdo, $idUser, 1); // existing + 5 + 1 > 5  => false
    $rZero  = check_user_ticket_limit($pdo, $idUser, 0); // existing + 5 + 0 <= 5
    $pdo->rollBack(); // no rows persist

    $check7 = ($rFalse === false);
    $detail7 = "existing=$existing check(+1)=" . var_export($rFalse, true) . " (expected false) check(+0)=" . var_export($rZero, true);
} catch (Throwable $e) {
    $check7 = false;
    $detail7 = 'ERROR: ' . $e->getMessage();
    try { if ($pdo->inTransaction()) { $pdo->rollBack(); } } catch (Throwable $e2) {}
}
check('7. Ticket limit: 5 Lunas tickets -> check_user_ticket_limit(user,1) === false', $check7, $detail7);

// ---- 8. Code generation: two generate_kode_tiket(1) differ + match PEN-6digits-32hex-index ----
$check8 = false;
$detail8 = '';
try {
    $k1 = generate_kode_tiket(1);
    $k2 = generate_kode_tiket(1);
    $regex = '/^PEN-\d{6}-[0-9A-F]{32}-\d+$/';
    $check8 = ($k1 !== $k2 && preg_match($regex, $k1) === 1 && preg_match($regex, $k2) === 1);
    $detail8 = "k1=$k1 k2=$k2 differ=" . var_export($k1 !== $k2, true) . " regex=" . var_export(preg_match($regex, $k1) === 1 && preg_match($regex, $k2) === 1, true);
} catch (Throwable $e) {
    $check8 = false;
    $detail8 = 'ERROR: ' . $e->getMessage();
}
check('8. Code generation: distinct + match PEN-6digits-32hex-index', $check8, $detail8);

// ---- 9. render_barcode_svg() returns svg containing the ticket code ----
$check9 = false;
$detail9 = '';
try {
    $tk = 'PEN-261119-' . strtoupper(bin2hex(random_bytes(16))) . '-1';
    $svg = render_barcode_svg($tk, '3273011005990001');
    $check9 = (is_string($svg) && strpos($svg, '<svg') !== false && strpos($svg, $tk) !== false);
    $detail9 = 'svg_start=' . (strpos($svg, '<svg') !== false ? 'yes' : 'no') . ' contains_code=' . (strpos($svg, $tk) !== false ? 'yes' : 'no');
} catch (Throwable $e) {
    $check9 = false;
    $detail9 = 'ERROR: ' . $e->getMessage();
}
check('9. render_barcode_svg() returns svg containing ticket code', $check9, $detail9);

// ---- 10. GET /etiket.php unauthenticated returns 302 ----
$rEtiket = curl_req(BASE_URL . '/etiket.php');
$check10 = ($rEtiket['code'] === '302');
check('10. GET /etiket.php unauthenticated returns 302', $check10, 'code=' . $rEtiket['code'] . ' loc=' . ($rEtiket['loc'] ?? '-'));

// ---- 11. RBAC: GET /crud_tiket.php with buyer session returns 403 ----
$check11 = false;
$detail11 = 'no buyer session';
if ($buyerCookie) {
    $c = curl_req(BASE_URL . '/crud_tiket.php', $buyerCookie);
    $check11 = ($c['code'] === '403');
    $detail11 = 'code=' . $c['code'];
}
check('11. RBAC: buyer on /crud_tiket.php returns 403', $check11, $detail11);

// ---- 12. Unique index on detail_tiket_satuan.kode_tiket_unik exists ----
$check12 = false;
$detail12 = '';
try {
    $rows = $pdo->query("SHOW INDEX FROM detail_tiket_satuan WHERE Key_name = 'uq_kode_tiket_unik'")->fetchAll();
    $unique = false;
    foreach ($rows as $row) {
        if (strtolower((string)$row['Column_name']) === 'kode_tiket_unik' && (int)$row['Non_unique'] === 0) {
            $unique = true;
        }
    }
    $check12 = $unique;
    $detail12 = 'matching_unique_index_rows=' . count($rows);
} catch (Throwable $e) {
    $check12 = false;
    $detail12 = 'ERROR: ' . $e->getMessage();
}
check('12. Unique index detail_tiket_satuan.kode_tiket_unik (SHOW INDEX)', $check12, $detail12);

/* ------------------------------------------------------------------ */
/* REPORT                                                             */
/* ------------------------------------------------------------------ */

echo "\n";
echo str_repeat('=', 78) . "\n";
echo "FUNCTIONAL TEST SUMMARY\n";
echo str_repeat('=', 78) . "\n";
echo sprintf("%-5s | %-55s | %s\n", '', 'CHECK', 'DETAIL');
echo str_repeat('-', 78) . "\n";
foreach ($results as $res) {
    echo sprintf("%-5s | %-55s | %s\n", $res['pass'] ? 'PASS' : 'FAIL', $res['name'], $res['detail']);
}
echo str_repeat('-', 78) . "\n";
echo sprintf("TOTAL: %d checks | %d PASS | %d FAIL\n", count($results), $passCount, $failCount);
echo str_repeat('=', 78) . "\n";
exit($failCount === 0 ? 0 : 1);
