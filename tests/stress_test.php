<?php
/**
 * stress_test.php
 * Stress / load smoke test for the Pensi School PHP/MySQL ticketing app.
 *
 * Run:  C:/xampp/php/php.exe C:/xampp/htdocs/tests/stress_test.php
 *
 * Requirements:
 *   - Apache serving http://127.0.0.1 (DocumentRoot C:/xampp/htdocs/public)
 *
 * Rules followed:
 *   - Only this test file is created/modified; application code is NEVER touched.
 *   - No database writes are performed (read-only GET requests).
 *   - Total runtime must be under 60 seconds.
 *   - Prints a pass/fail matrix plus a summary.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('BASE_URL', 'http://127.0.0.1');

$results = [];
$passCount = 0;
$failCount = 0;
$suiteStart = microtime(true);

function check(string $name, bool $cond, string $detail = ''): void
{
    global $results, $passCount, $failCount;
    $results[] = ['name' => $name, 'pass' => $cond, 'detail' => $detail];
    echo sprintf("%-5s | %-60s | %s\n", $cond ? 'PASS' : 'FAIL', $name, $detail);
    if ($cond) {
        $passCount++;
    } else {
        $failCount++;
    }
}

/* ------------------------------------------------------------------ */
/* cURL timed GET helper                                              */
/* ------------------------------------------------------------------ */
function curl_get_timed(string $url): array
{
    $t0 = microtime(true);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    $body = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return [
        'code' => $httpCode,
        'latencyMs' => (microtime(true) - $t0) * 1000.0,
        'error' => $err,
        'body' => $body === false ? '' : $body,
    ];
}

function latencyStats(array $latencies): array
{
    if (empty($latencies)) {
        return ['min' => 0.0, 'avg' => 0.0, 'max' => 0.0, 'n' => 0];
    }
    return [
        'min' => min($latencies),
        'avg' => array_sum($latencies) / count($latencies),
        'max' => max($latencies),
        'n' => count($latencies),
    ];
}

/* ------------------------------------------------------------------ */
/* PHASE 1: sequential GETs                                           */
/* ------------------------------------------------------------------ */
echo "PHASE 1 - Sequential GETs (100 x /  + 50 x /login.php)\n";
$paths = [];
for ($i = 0; $i < 100; $i++) {
    $paths[] = '/';
}
for ($i = 0; $i < 50; $i++) {
    $paths[] = '/login.php';
}

$latencies = [];
$codes = [];
$non200 = 0;
$fiveHundreds = 0;
$seqErrors = 0;

foreach ($paths as $path) {
    $r = curl_get_timed(BASE_URL . $path);
    if ($r['code'] === 0) {
        $seqErrors++;
    }
    $latencies[] = $r['latencyMs'];
    $codes[] = $r['code'];
    if ($r['code'] !== 200) {
        $non200++;
    }
    if ($r['code'] === 500) {
        $fiveHundreds++;
    }
}

$s = latencyStats($latencies);
$codeCounts = array_count_values($codes);
ksort($codeCounts);
$codeDetail = implode(', ', array_map(fn($c, $n) => "HTTP $c: $n", array_keys($codeCounts), array_values($codeCounts)));

echo sprintf("  sequential requests: %d  |  latency min=%.2fms avg=%.2fms max=%.2fms\n",
    count($paths), $s['min'], $s['avg'], $s['max']);
echo sprintf("  non-200 count: %d  |  HTTP 500 count: %d  |  connect errors: %d\n",
    $non200, $fiveHundreds, $seqErrors);
echo sprintf("  status breakdown: %s\n", $codeDetail);

check('1a. Sequential: 150 requests completed (100 x /, 50 x /login.php)', count($paths) === 150, 'n=' . count($paths));
check('1b. Sequential: zero HTTP 500 errors', $fiveHundreds === 0, '500s=' . $fiveHundreds);
check('1c. Sequential: zero non-200 responses', $non200 === 0, 'non200=' . $non200);
check('1d. Sequential: no connect errors', $seqErrors === 0, 'errors=' . $seqErrors);

/* ------------------------------------------------------------------ */
/* PHASE 2: 10 parallel requests via curl_multi to /                  */
/* ------------------------------------------------------------------ */
echo "\nPHASE 2 - Parallel GETs (10 x / via curl_multi)\n";
$parallelCount = 10;
$mh = curl_multi_init();
$handles = [];
for ($i = 0; $i < $parallelCount; $i++) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, BASE_URL . '/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_multi_add_handle($mh, $ch);
    $handles[] = $ch;
}

$running = null;
do {
    $rc = curl_multi_exec($mh, $running);
} while ($running > 0 && $rc === CURLM_OK);

$parStatuses = [];
$parErrors = 0;
foreach ($handles as $idx => $ch) {
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($code === 0) {
        $parErrors++;
    }
    $parStatuses[] = $code;
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
}
curl_multi_close($mh);

asort($parStatuses);
echo '  parallel statuses: ' . implode(' ', $parStatuses) . "\n";
echo sprintf("  parallel errors: %d  |  status breakdown: %s\n",
    $parErrors, implode(', ', array_map(fn($c, $n) => "HTTP $c: $n", array_keys(array_count_values($parStatuses)), array_values(array_count_values($parStatuses)))));

check('2a. Parallel: 10 requests all completed', count($parStatuses) === 10, 'n=' . count($parStatuses));
check('2b. Parallel: zero HTTP 500 errors', !in_array(500, $parStatuses, true), 'statuses=' . implode(' ', $parStatuses));
check('2c. Parallel: no connect errors', $parErrors === 0, 'errors=' . $parErrors);

/* ------------------------------------------------------------------ */
/* PHASE 3: runtime assertion                                         */
/* ------------------------------------------------------------------ */
$elapsed = microtime(true) - $suiteStart;
echo sprintf("\nTotal runtime: %.2f s (limit 60 s)\n", $elapsed);
check('3. Total runtime under 60 seconds', $elapsed < 60.0, sprintf('%.2f s', $elapsed));

/* ------------------------------------------------------------------ */
/* REPORT                                                             */
/* ------------------------------------------------------------------ */
echo "\n";
echo str_repeat('=', 78) . "\n";
echo "STRESS TEST SUMMARY\n";
echo str_repeat('=', 78) . "\n";
echo sprintf("%-5s | %-60s | %s\n", '', 'CHECK', 'DETAIL');
echo str_repeat('-', 78) . "\n";
foreach ($results as $res) {
    echo sprintf("%-5s | %-60s | %s\n", $res['pass'] ? 'PASS' : 'FAIL', $res['name'], $res['detail']);
}
echo str_repeat('-', 78) . "\n";
echo sprintf("TOTAL: %d checks | %d PASS | %d FAIL\n", count($results), $passCount, $failCount);
echo str_repeat('=', 78) . "\n";
exit($failCount === 0 ? 0 : 1);
