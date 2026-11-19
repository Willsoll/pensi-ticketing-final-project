<?php
require_once __DIR__ . '/src/Config/database.php';
require_once __DIR__ . '/src/Middleware/middleware.php';

echo "=== DIAGNOSTIC KATEGORI ===\n";
echo "DB: $db_name\n\n";

try {
    $cats = $pdo->query("SELECT id_kat_event, nama_kategori FROM kategori_event ORDER BY id_kat_event")->fetchAll();
    echo "kategori_event rows: " . count($cats) . "\n";
    foreach ($cats as $c) {
        echo "  {$c['id_kat_event']} => {$c['nama_kategori']}\n";
    }

    $events = $pdo->query("SELECT id_event, judul_event, id_kat_event FROM events")->fetchAll();
    echo "\nevents rows: " . count($events) . "\n";
    foreach ($events as $e) {
        $cat = $pdo->prepare("SELECT nama_kategori FROM kategori_event WHERE id_kat_event=?");
        $cat->execute([$e['id_kat_event']]);
        $cname = $cat->fetchColumn() ?? 'TIDAK DITEMUKAN';
        echo "  {$e['id_event']}: {$e['judul_event']} => id_kat_event={$e['id_kat_event']} => kategori=$cname\n";
    }

    // Test index.php query
    $stmt = $pdo->query("SELECT e.id_event, e.judul_event, k.nama_kategori FROM events e JOIN kategori_event k ON k.id_kat_event=e.id_kat_event WHERE e.status_event='Published'");
    $rows = $stmt->fetchAll();
    echo "\nJOIN result (what index.php uses): " . count($rows) . " rows\n";
    foreach ($rows as $r) {
        echo "  {$r['id_event']}: {$r['judul_event']} [{$r['nama_kategori']}]\n";
    }

    // Check crud_event $katOptions
    $katOpt = $pdo->query("SELECT id_kat_event, nama_kategori FROM kategori_event ORDER BY nama_kategori")->fetchAll();
    echo "\nkatOptions (crud_event dropdown): " . count($katOpt) . " options\n";
    foreach ($katOpt as $k) {
        echo "  value={$k['id_kat_event']} => {$k['nama_kategori']}\n";
    }

} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
