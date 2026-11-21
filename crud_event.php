<?php
declare(strict_types=1);

/**
 * crud_event.php — Manajemen Event, Tier, Kategori, dan Kursi (admin_eo only).
 * Tabs: ?tab=event|tier|kategori|kursi. Semua mutasi via POST + CSRF + audit log.
 */
require_once __DIR__ . '/src/Config/database.php';
require_once __DIR__ . '/src/Middleware/middleware.php';
release_expired_transactions($pdo);

require_role(['admin_eo']);

$user = current_user();

$q = function (string $sql, array $params = []) use ($pdo): \PDOStatement {
    $s = $pdo->prepare($sql);
    $s->execute($params);
    return $s;
};

$tab = $_GET['tab'] ?? 'event';
if (!in_array($tab, ['event', 'tier', 'kategori', 'kursi'], true)) {
    $tab = 'event';
}

$flashOk = function (string $msg) use (&$tab) { set_flash('SUCCESS', $msg); };
$flashErr = function (string $msg) use (&$tab) { set_flash('ERROR', $msg); };

$go = function () use ($tab) {
    header('Location: crud_event.php?tab=' . $tab);
    exit;
};

// ---------- POST mutasi ----------
if ((isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST')) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('CSRF tidak valid');
    }
    $action = $_POST['action'] ?? '';

    /* -------------------- EVENT -------------------- */
    if ($action === 'save_event') {
        $idEvent   = (int)($_POST['id_event'] ?? 0);
        $judul     = trim($_POST['judul_event'] ?? '');
        $idKat     = (int)($_POST['id_kat_event'] ?? 0);
        $penyelenggara = trim($_POST['penyelenggara'] ?? '');
        $lokasi    = trim($_POST['lokasi'] ?? '');
        $tipe      = $_POST['tipe_venue'] ?? 'Free Standing';
        $tglMulai  = $_POST['tgl_mulai'] ?? '';
        $tglSelesai= $_POST['tgl_selesai'] ?? '';
        $status    = $_POST['status_event'] ?? 'Published';

        $ok = $judul !== '' && $idKat > 0 && $penyelenggara !== '' && $lokasi !== ''
            && in_array($tipe, ['Seated', 'Free Standing'], true)
            && $tglMulai !== '' && $tglSelesai !== ''
            && in_array($status, ['Draft', 'Published', 'Closed'], true);
        if (!$ok) {
            set_flash('ERROR', 'Data event tidak lengkap atau tidak valid.');
        } elseif ($idEvent > 0) {
            $pdo->prepare('UPDATE events SET judul_event=?, id_kat_event=?, penyelenggara=?, lokasi=?,
                           tipe_venue=?, tgl_mulai=?, tgl_selesai=?, status_event=? WHERE id_event=?')
                ->execute([$judul, $idKat, $penyelenggara, $lokasi, $tipe, $tglMulai, $tglSelesai, $status, $idEvent]);
            catat_audit_log($pdo, $user['id_user'], 'UPDATE', 'events',
                "Event #$idEvent diubah: $judul");
            set_flash('SUCCESS', 'Event diperbarui.');
        } else {
            $pdo->prepare('INSERT INTO events (id_kat_event,judul_event,penyelenggara,lokasi,tipe_venue,tgl_mulai,tgl_selesai,status_event)
                           VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$idKat, $judul, $penyelenggara, $lokasi, $tipe, $tglMulai, $tglSelesai, $status]);
            $newId = (int)$pdo->lastInsertId();
            catat_audit_log($pdo, $user['id_user'], 'CREATE', 'events',
                "Event baru #$newId: $judul");
            set_flash('SUCCESS', 'Event ditambahkan.');
        }
        $go();
    }

    if ($action === 'delete_event') {
        $idEvent = (int)($_POST['id_event'] ?? 0);
        $st = $q('SELECT COUNT(*) FROM transaksi WHERE id_event=?', [$idEvent]);
        $used = (int)$st->fetchColumn();
        if ($used > 0) {
            set_flash('ERROR', "Event #$idEvent tidak dapat dihapus karena sudah memiliki $used transaksi. Hapus atau pindahkan transaksi terlebih dahulu.");
        } else {
            $pdo->prepare('DELETE FROM events WHERE id_event=?')->execute([$idEvent]);
            catat_audit_log($pdo, $user['id_user'], 'DELETE', 'events', "Event #$idEvent dihapus.");
            set_flash('SUCCESS', 'Event dihapus.');
        }
        $go();
    }

    /* -------------------- KATEGORI -------------------- */
    if ($action === 'save_kategori') {
        $idKat   = (int)($_POST['id_kat_event'] ?? 0);
        $namaKat = trim($_POST['nama_kategori'] ?? '');
        if ($namaKat === '') {
            set_flash('ERROR', 'Nama kategori wajib diisi.');
        } else {
            $st = $q('SELECT COUNT(*) FROM kategori_event WHERE nama_kategori=? AND id_kat_event<>?', [$namaKat, $idKat]);
            $exists = (int)$st->fetchColumn();
            if ($exists) {
                set_flash('ERROR', "Kategori '$namaKat' sudah terdaftar.");
            } elseif ($idKat > 0) {
                $pdo->prepare('UPDATE kategori_event SET nama_kategori=? WHERE id_kat_event=?')
                    ->execute([$namaKat, $idKat]);
                catat_audit_log($pdo, $user['id_user'], 'UPDATE', 'kategori_event', "Kategori #$idKat diubah menjadi '$namaKat'");
                set_flash('SUCCESS', 'Kategori diperbarui.');
            } else {
                $pdo->prepare('INSERT INTO kategori_event (nama_kategori) VALUES (?)')->execute([$namaKat]);
                $newId = (int)$pdo->lastInsertId();
                catat_audit_log($pdo, $user['id_user'], 'CREATE', 'kategori_event', "Kategori baru #$newId: $namaKat");
                set_flash('SUCCESS', 'Kategori ditambahkan.');
            }
        }
        $go();
    }

    if ($action === 'delete_kategori') {
        $idKat = (int)($_POST['id_kat_event'] ?? 0);
        $st  = $q('SELECT COUNT(*) FROM events WHERE id_kat_event=?', [$idKat]);
        $used = (int)$st->fetchColumn();
        if ($used > 0) {
            set_flash('ERROR', "Kategori tidak dapat dihapus karena masih digunakan oleh $used event.");
        } else {
            $pdo->prepare('DELETE FROM kategori_event WHERE id_kat_event=?')->execute([$idKat]);
            catat_audit_log($pdo, $user['id_user'], 'DELETE', 'kategori_event', "Kategori #$idKat dihapus.");
            set_flash('SUCCESS', 'Kategori dihapus.');
        }
        $go();
    }

    /* -------------------- TIER -------------------- */
    if ($action === 'save_tier') {
        $idTier     = (int)($_POST['id_tier'] ?? 0);
        $idEvent    = (int)($_POST['id_event'] ?? 0);
        $namaTier   = trim($_POST['nama_tier'] ?? '');
        $harga      = (int)($_POST['harga'] ?? 0);
        $kuotaTotal = (int)($_POST['kuota_total'] ?? 0);
        $batasJual  = $_POST['batas_jual'] ?? '';

        if ($idEvent <= 0 || $namaTier === '' || $harga < 0 || $kuotaTotal < 0 || $batasJual === '') {
            set_flash('ERROR', 'Data tier tidak lengkap atau tidak valid.');
        } elseif ($idTier > 0) {
            $st = $q('SELECT kuota_total, kuota_tersedia, kuota_locked FROM tiket_tier WHERE id_tier=?', [$idTier]);
            $old = $st->fetch();
            if (!$old) {
                set_flash('ERROR', 'Tier tidak ditemukan.');
            } else {
                $delta      = $kuotaTotal - (int)$old['kuota_total'];
                $newAvail   = max(0, (int)$old['kuota_tersedia'] + $delta);
                $st = $q(
                    'SELECT COALESCE(COUNT(*),0) FROM e_tickets et
                     JOIN transaksi t ON t.id_transaksi = et.id_transaksi
                     WHERE t.status_bayar = ? AND et.id_tier = ?',
                    ['Paid', $idTier]
                );
                $sold = (int)$st->fetchColumn();
                $locked     = (int)$old['kuota_locked'];
                $overwarn   = ($sold + $locked) > $kuotaTotal ? ' Peringatan: terjual + dikunci melebihi kuota baru.' : '';

                $pdo->prepare('UPDATE tiket_tier SET id_event=?, nama_tier=?, harga=?, kuota_total=?,
                               kuota_tersedia=?, batas_jual=? WHERE id_tier=?')
                    ->execute([$idEvent, $namaTier, $harga, $kuotaTotal, $newAvail, $batasJual, $idTier]);
                catat_audit_log($pdo, $user['id_user'], 'UPDATE', 'tiket_tier',
                    "Tier #$idTier ($namaTier): kuota_total {$old['kuota_total']} &rarr; $kuotaTotal, tersedia &rarr; $newAvail");
                set_flash('SUCCESS', 'Tier diperbarui.' . $overwarn);
            }
        } else {
            $pdo->prepare('INSERT INTO tiket_tier (id_event,nama_tier,harga,kuota_total,kuota_tersedia,kuota_locked,batas_jual)
                           VALUES (?,?,?,?,?,0,?)')
                ->execute([$idEvent, $namaTier, $harga, $kuotaTotal, $kuotaTotal, $batasJual]);
            $newId = (int)$pdo->lastInsertId();
            catat_audit_log($pdo, $user['id_user'], 'CREATE', 'tiket_tier', "Tier baru #$newId ($namaTier) pada event #$idEvent");
            set_flash('SUCCESS', 'Tier ditambahkan.');
        }
        $go();
    }

    if ($action === 'delete_tier') {
        $idTier = (int)($_POST['id_tier'] ?? 0);
        $st = $q('SELECT COUNT(*) FROM transaksi_detail WHERE id_tier=?', [$idTier]);
        $ref = (int)$st->fetchColumn();
        $st = $q('SELECT COUNT(*) FROM e_tickets WHERE id_tier=?', [$idTier]);
        $ref += (int)$st->fetchColumn();
        $st = $q('SELECT COUNT(*) FROM kursi_event WHERE id_tier=?', [$idTier]);
        $ref += (int)$st->fetchColumn();
        if ($ref > 0) {
            set_flash('ERROR', "Tier tidak dapat dihapus karena masih dirujuk oleh $ref data (transaksi detail / e-ticket / kursi).");
        } else {
            $pdo->prepare('DELETE FROM tiket_tier WHERE id_tier=?')->execute([$idTier]);
            catat_audit_log($pdo, $user['id_user'], 'DELETE', 'tiket_tier', "Tier #$idTier dihapus.");
            set_flash('SUCCESS', 'Tier dihapus.');
        }
        $go();
    }

    /* -------------------- KURSI: GENERATOR -------------------- */
    if ($action === 'generate_seats') {
        $idTier = (int)($_POST['id_tier'] ?? 0);
        $prefix = trim($_POST['prefix'] ?? '');
        $count  = (int)($_POST['count'] ?? 0);
        $st = $q('SELECT t.id_tier FROM tiket_tier t JOIN events e ON e.id_event=t.id_event
                  WHERE t.id_tier=? AND e.tipe_venue=?', [$idTier, 'Seated']);
        $valid  = $st->fetch() !== false;
        if (!$valid || $prefix === '' || $count < 1 || $count > 1000) {
            set_flash('ERROR', 'Pilih tier bertipe Seated, masukan prefix, dan jumlah 1&ndash;1000.');
        } else {
            $ins = $pdo->prepare('INSERT IGNORE INTO kursi_event (id_tier, nomor_kursi, status_kursi) VALUES (?, ?, "Available")');
            $created = 0;
            for ($n = 1; $n <= $count; $n++) {
                $ins->execute([$idTier, $prefix . sprintf('%03d', $n)]);
                $created += (int)$ins->rowCount();
            }
            catat_audit_log($pdo, $user['id_user'], 'CREATE', 'kursi_event',
                "Generated $created kursi untuk tier #$idTier (prefix $prefix, count $count).");
            set_flash('SUCCESS', "Dibuat $created kursi (nomor duplikat dilewati).");
        }
        $go();
    }

    /* -------------------- KURSI: HAPUS TERSEDIA -------------------- */
    if ($action === 'delete_available_seats') {
        $idTier = (int)($_POST['id_tier'] ?? 0);
        $st = $q('DELETE FROM kursi_event WHERE id_tier=? AND status_kursi="Available"', [$idTier]);
        $deleted = (int)$st->rowCount();
        catat_audit_log($pdo, $user['id_user'], 'DELETE', 'kursi_event', "Hapus $deleted kursi Available pada tier #$idTier.");
        set_flash('SUCCESS', "Dihapus $deleted kursi yang tersedia.");
        $go();
    }

    if ($action === 'delete_seat') {
        $idKursi = (int)($_POST['id_kursi'] ?? 0);
        $idTier  = (int)($_POST['id_tier'] ?? 0);
        $pdo->prepare('DELETE FROM kursi_event WHERE id_kursi=? AND id_tier=? AND status_kursi="Available"')
            ->execute([$idKursi, $idTier]);
        catat_audit_log($pdo, $user['id_user'], 'DELETE', 'kursi_event', "Kursi #$idKursi dihapus dari tier #$idTier.");
        set_flash('SUCCESS', 'Kursi dihapus.');
        $go();
    }
}

// ---------- Data untuk tampilan ----------
$eventOptions = $pdo->query('SELECT id_event, judul_event FROM events ORDER BY judul_event')->fetchAll();
$katOptions   = $pdo->query('SELECT id_kat_event, nama_kategori FROM kategori_event ORDER BY nama_kategori')->fetchAll();

$editEvent   = null;
$editKategori= null;
$editTier    = null;
$eventsWithTier = [];
$rowsEvent   = [];
$rowsKategori= [];
$rowsTier    = [];
$selectedEventId = filter_var($_GET['event_id'] ?? null, FILTER_VALIDATE_INT);
$selectedTierId  = filter_var($_GET['tier_id'] ?? null, FILTER_VALIDATE_INT);
$rowsSeat    = [];
$tierSeatInfo= null;

if ($tab === 'event') {
    $rowsEvent = $pdo->query("
        SELECT e.*, k.nama_kategori
        FROM events e
        JOIN kategori_event k ON k.id_kat_event = e.id_kat_event
        ORDER BY e.tgl_mulai DESC, e.judul_event
    ")->fetchAll();
    if (($evId = filter_var($_GET['edit_event'] ?? null, FILTER_VALIDATE_INT)) !== false) {
        $st = $q('SELECT * FROM events WHERE id_event=?', [$evId]);
        $editEvent = $st->fetch();
    }
} elseif ($tab === 'kategori') {
    $rowsKategori = $pdo->query("
        SELECT k.*, (SELECT COUNT(*) FROM events WHERE id_kat_event = k.id_kat_event) AS used_by
        FROM kategori_event k ORDER BY nama_kategori
    ")->fetchAll();
    if (($katId = filter_var($_GET['edit_kategori'] ?? null, FILTER_VALIDATE_INT)) !== false) {
        $st = $q('SELECT * FROM kategori_event WHERE id_kat_event=?', [$katId]);
        $editKategori = $st->fetch();
    }
} elseif ($tab === 'tier') {
    if (($tierId = filter_var($_GET['edit_tier'] ?? null, FILTER_VALIDATE_INT)) !== false) {
        $st = $q('SELECT * FROM tiket_tier WHERE id_tier=?', [$tierId]);
        $editTier = $st->fetch();
        if ($editTier) {
            $selectedEventId = (int)$editTier['id_event'];
        }
    }
    if ($selectedEventId) {
        $st = $q("
            SELECT t.*,
                   (SELECT COALESCE(COUNT(*),0) FROM e_tickets et
                    JOIN transaksi tx ON tx.id_transaksi = et.id_transaksi
                    WHERE tx.status_bayar = 'Paid' AND et.id_tier = t.id_tier) AS sold
            FROM tiket_tier t WHERE t.id_event = ? ORDER BY t.nama_tier
        ", [$selectedEventId]);
        $rowsTier = $st->fetchAll();
    }
} elseif ($tab === 'kursi') {
    $eventsWithTier = $pdo->query("
        SELECT t.id_tier, t.nama_tier, e.id_event, e.judul_event
        FROM tiket_tier t JOIN events e ON e.id_event = t.id_event
        WHERE e.tipe_venue = 'Seated' ORDER BY e.judul_event, t.nama_tier
    ")->fetchAll();
    if ($selectedTierId) {
        $tierSeatInfo = $q('SELECT t.*, e.judul_event, e.tipe_venue FROM tiket_tier t
                           JOIN events e ON e.id_event = t.id_event WHERE t.id_tier=?', [$selectedTierId])
            ->fetch();
        if ($tierSeatInfo && $tierSeatInfo['tipe_venue'] === 'Seated') {
            $st = $q('SELECT * FROM kursi_event WHERE id_tier=? ORDER BY nomor_kursi', [$selectedTierId]);
            $rowsSeat = $st->fetchAll();
        }
    }
}

$pageTitle = 'Kelola Event & Tier';
$pageSubtitle = 'CRUD event, kategori, tiket tier, dan kursi event';
$activeNav = 'crud_event.php';

require __DIR__ . '/src/Views/layouts/header.php';
?>

<?php if ($tab === 'event'): ?>
    <div style="display:flex;gap:10px;align-items:center;margin-bottom:14px;flex-wrap:wrap;">
        <a class="btn btn-primary" href="#form-event">+ Tambah Event</a>
        <a class="btn btn-outline" href="crud_event.php?tab=tier">Kelola Tier</a>
        <a class="btn btn-outline" href="crud_event.php?tab=kategori">Kelola Kategori</a>
        <a class="btn btn-outline" href="crud_event.php?tab=kursi">Kelola Kursi</a>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">Daftar Event</span></div>
        <div class="card-body">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Judul Event</th>
                            <th>Kategori</th>
                            <th>Penyelenggara</th>
                            <th>Lokasi</th>
                            <th>Tipe Venue</th>
                            <th>Tgl Mulai &rarr; Selesai</th>
                            <th>Status</th>
                            <th style="width:220px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rowsEvent as $e): ?>
                            <tr>
                                <td><?= e($e['judul_event']) ?></td>
                                <td><?= e($e['nama_kategori']) ?></td>
                                <td><?= e($e['penyelenggara']) ?></td>
                                <td><?= e($e['lokasi']) ?></td>
                                <td><?= e($e['tipe_venue']) ?></td>
                                <td><?= format_tanggal($e['tgl_mulai']) ?> &rarr; <?= format_tanggal($e['tgl_selesai']) ?></td>
                                <td><span class="badge badge-info"><?= e($e['status_event']) ?></span></td>
                                <td>
                                    <a class="btn btn-ghost btn-sm" href="crud_event.php?tab=event&edit_event=<?= (int)$e['id_event'] ?>">Ubah</a>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Hapus event ini? Transaksi terkait akan mencegah penghapusan.')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_event">
                                        <input type="hidden" name="id_event" value="<?= (int)$e['id_event'] ?>">
                                        <button class="btn btn-danger btn-sm" type="submit">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$rowsEvent): ?>
                            <tr><td colspan="8" style="color:var(--color-text-muted);text-align:center;">Belum ada event.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card" id="form-event">
        <div class="card-header"><span class="card-title"><?= $editEvent ? 'Ubah Event' : 'Tambah Event' ?></span></div>
        <div class="card-body">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_event">
                <?php if ($editEvent): ?><input type="hidden" name="id_event" value="<?= (int)$editEvent['id_event'] ?>"><?php endif; ?>
                <div class="form-group">
                    <label class="form-label">Judul Event</label>
                    <input type="text" name="judul_event" class="form-input" required
                           value="<?= e($editEvent ? $editEvent['judul_event'] : (($_POST['judul_event'] ?? '') ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <select name="id_kat_event" class="form-select" required>
                        <?php foreach ($katOptions as $k): ?>
                            <option value="<?= (int)$k['id_kat_event'] ?>" <?= (($editEvent ? $editEvent['id_kat_event'] : (($_POST['id_kat_event'] ?? '') ?? '')) == $k['id_kat_event']) ? 'selected' : '' ?>><?= e($k['nama_kategori']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Penyelenggara</label>
                    <input type="text" name="penyelenggara" class="form-input" required
                           value="<?= e($editEvent ? $editEvent['penyelenggara'] : (($_POST['penyelenggara'] ?? '') ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Lokasi</label>
                    <input type="text" name="lokasi" class="form-input" required
                           value="<?= e($editEvent ? $editEvent['lokasi'] : (($_POST['lokasi'] ?? '') ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Tipe Venue</label>
                    <select name="tipe_venue" class="form-select" required>
                        <option value="Seated" <?= ((($editEvent ? $editEvent['tipe_venue'] : (($_POST['tipe_venue'] ?? 'Free Standing')) ?? 'Free Standing')) === 'Seated') ? 'selected' : '' ?>>Seated (dengan kursi)</option>
                        <option value="Free Standing" <?= ((($editEvent ? $editEvent['tipe_venue'] : (($_POST['tipe_venue'] ?? 'Free Standing')) ?? 'Free Standing')) === 'Free Standing') ? 'selected' : '' ?>>Free Standing</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Tanggal Mulai</label>
                    <input type="datetime-local" name="tgl_mulai" class="form-input" required
                           value="<?= e($editEvent ? str_replace(' ', 'T', substr($editEvent['tgl_mulai'], 0, 16)) : (($_POST['tgl_mulai'] ?? '') ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Tanggal Selesai</label>
                    <input type="datetime-local" name="tgl_selesai" class="form-input" required
                           value="<?= e($editEvent ? str_replace(' ', 'T', substr($editEvent['tgl_selesai'], 0, 16)) : (($_POST['tgl_selesai'] ?? '') ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Status Event</label>
                    <select name="status_event" class="form-select" required>
                        <option value="Draft" <?= ((($editEvent ? $editEvent['status_event'] : 'Published') ?? 'Published') === 'Draft') ? 'selected' : '' ?>>Draft</option>
                        <option value="Published" <?= ((($editEvent ? $editEvent['status_event'] : 'Published') ?? 'Published') === 'Published') ? 'selected' : '' ?>>Published</option>
                        <option value="Closed" <?= ((($editEvent ? $editEvent['status_event'] : 'Published') ?? 'Published') === 'Closed') ? 'selected' : '' ?>>Closed</option>
                    </select>
                </div>
                <div class="form-group">
                    <button class="btn btn-primary" type="submit"><?= $editEvent ? 'Simpan Perubahan' : 'Simpan Event' ?></button>
                    <a class="btn btn-ghost" href="crud_event.php?tab=event">Batal</a>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($tab === 'kategori'): ?>
    <div style="display:flex;gap:10px;align-items:center;margin-bottom:14px;flex-wrap:wrap;">
        <a class="btn btn-primary" href="#form-kategori">+ Tambah Kategori</a>
        <a class="btn btn-outline" href="crud_event.php?tab=event">Kelola Event</a>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">Daftar Kategori</span></div>
        <div class="card-body">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr><th>Nama Kategori</th><th>Digunakan Oleh (Event)</th><th style="width:200px;">Aksi</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rowsKategori as $k): ?>
                            <tr>
                                <td><?= e($k['nama_kategori']) ?></td>
                                <td style="text-align:center;"><?= (int)$k['used_by'] ?></td>
                                <td>
                                    <a class="btn btn-ghost btn-sm" href="crud_event.php?tab=kategori&edit_kategori=<?= (int)$k['id_kat_event'] ?>">Ubah</a>
                                    <?php if ((int)$k['used_by'] > 0): ?>
                                        <span class="btn btn-ghost btn-sm" style="color:var(--color-text-muted);" title="Diblokir: digunakan oleh event">Hapus (terkunci)</span>
                                    <?php else: ?>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Hapus kategori ini?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_kategori">
                                            <input type="hidden" name="id_kat_event" value="<?= (int)$k['id_kat_event'] ?>">
                                            <button class="btn btn-danger btn-sm" type="submit">Hapus</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$rowsKategori): ?>
                            <tr><td colspan="3" style="color:var(--color-text-muted);text-align:center;">Belum ada kategori.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card" id="form-kategori">
        <div class="card-header"><span class="card-title"><?= $editKategori ? 'Ubah Kategori' : 'Tambah Kategori' ?></span></div>
        <div class="card-body">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_kategori">
                <?php if ($editKategori): ?><input type="hidden" name="id_kat_event" value="<?= (int)$editKategori['id_kat_event'] ?>"><?php endif; ?>
                <div class="form-group">
                    <label class="form-label">Nama Kategori</label>
                    <input type="text" name="nama_kategori" class="form-input" required
                           value="<?= e($editKategori ? $editKategori['nama_kategori'] : (($_POST['nama_kategori'] ?? '') ?? '')) ?>">
                </div>
                <div class="form-group">
                    <button class="btn btn-primary" type="submit"><?= $editKategori ? 'Simpan Kategori' : 'Simpan Kategori' ?></button>
                    <a class="btn btn-ghost" href="crud_event.php?tab=kategori">Batal</a>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($tab === 'tier'): ?>
    <div style="margin-bottom:14px;">
        <form method="get" style="display:inline-block;gap:8px;align-items:center;">
            <input type="hidden" name="tab" value="tier">
            <label class="form-label" style="margin:0;font-size:12.5px;">Pilih event:</label>
            <select name="event_id" class="form-select" style="width:auto;min-width:260px;" onchange="this.form.submit()">
                <option value="">-- Pilih Event --</option>
                <?php foreach ($eventOptions as $ev): ?>
                    <option value="<?= (int)$ev['id_event'] ?>" <?= ($selectedEventId === (int)$ev['id_event']) ? 'selected' : '' ?>><?= e($ev['judul_event']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <a class="btn btn-outline" href="crud_event.php?tab=event">Kelola Event</a>
        <a class="btn btn-outline" href="crud_event.php?tab=kategori">Kelola Kategori</a>
    </div>

    <?php if ($selectedEventId): ?>
        <div class="card">
            <div class="card-header"><span class="card-title">Tiket Tier</span></div>
            <div class="card-body">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nama Tier</th>
                                <th style="text-align:right;">Harga</th>
                                <th style="text-align:right;">Kuota Total</th>
                                <th style="text-align:right;">Tersedia</th>
                                <th style="text-align:right;">Dikunci</th>
                                <th style="text-align:right;">Terjual (Lunas)</th>
                                <th>Batas Jual</th>
                                <th style="width:220px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rowsTier as $t): ?>
                                <tr>
                                    <td><?= e($t['nama_tier']) ?></td>
                                    <td style="text-align:right;"><?= format_rupiah((int)$t['harga']) ?></td>
                                    <td style="text-align:right;"><?= (int)$t['kuota_total'] ?></td>
                                    <td style="text-align:right;"><?= (int)$t['kuota_tersedia'] ?></td>
                                    <td style="text-align:right;"><?= (int)$t['kuota_locked'] ?></td>
                                    <td style="text-align:right;"><?= (int)$t['sold'] ?></td>
                                    <td><?= format_tanggal($t['batas_jual']) ?></td>
                                    <td>
                                        <a class="btn btn-ghost btn-sm" href="crud_event.php?tab=tier&event_id=<?= (int)$selectedEventId ?>&edit_tier=<?= (int)$t['id_tier'] ?>">Ubah</a>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Hapus tier ini? Diblokir jika masih dirujuk transaksi, e-ticket, atau kursi.')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_tier">
                                            <input type="hidden" name="id_tier" value="<?= (int)$t['id_tier'] ?>">
                                            <button class="btn btn-danger btn-sm" type="submit">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$rowsTier): ?>
                                <tr><td colspan="8" style="color:var(--color-text-muted);text-align:center;">Belum ada tier untuk event ini.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card" id="form-tier">
            <div class="card-header"><span class="card-title"><?= $editTier ? 'Ubah Tier' : 'Tambah Tier' ?></span></div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_tier">
                    <?php if ($editTier): ?><input type="hidden" name="id_tier" value="<?= (int)$editTier['id_tier'] ?>"><?php endif; ?>
                    <input type="hidden" name="id_event" value="<?= (int)($editTier ? $editTier['id_event'] : $selectedEventId) ?>">
                    <div class="form-group">
                        <label class="form-label">Nama Tier</label>
                        <input type="text" name="nama_tier" class="form-input" required
                               value="<?= e($editTier ? $editTier['nama_tier'] : (($_POST['nama_tier'] ?? '') ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Harga</label>
                        <input type="number" name="harga" class="form-input" required min="0"
                               value="<?= e($editTier ? $editTier['harga'] : (($_POST['harga'] ?? '') ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Kuota Total</label>
                        <input type="number" name="kuota_total" class="form-input" required min="0"
                               value="<?= e($editTier ? $editTier['kuota_total'] : (($_POST['kuota_total'] ?? '') ?? '')) ?>">
                        <p class="form-hint" style="margin-top:2px;">Mengubah kuota menyesuaikan kuota tersedia (selisih, tidak di bawah 0).</p>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Batas Jual</label>
                        <input type="datetime-local" name="batas_jual" class="form-input" required
                               value="<?= e($editTier ? str_replace(' ', 'T', substr($editTier['batas_jual'], 0, 16)) : (($_POST['batas_jual'] ?? '') ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <button class="btn btn-primary" type="submit"><?= $editTier ? 'Simpan Tier' : 'Simpan Tier' ?></button>
                        <a class="btn btn-ghost" href="crud_event.php?tab=tier&event_id=<?= (int)$selectedEventId ?>">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

<?php elseif ($tab === 'kursi'): ?>
    <div style="margin-bottom:14px;">
        <form method="get" style="display:inline-block;gap:8px;align-items:center;">
            <input type="hidden" name="tab" value="kursi">
            <label class="form-label" style="margin:0;font-size:12.5px;">Pilih tier (Seated):</label>
            <select name="tier_id" class="form-select" style="width:auto;min-width:300px;" onchange="this.form.submit()">
                <option value="">-- Pilih Tier --</option>
                <?php foreach ($eventsWithTier as $t): ?>
                    <option value="<?= (int)$t['id_tier'] ?>" <?= ($selectedTierId === (int)$t['id_tier']) ? 'selected' : '' ?>><?= e($t['judul_event']) ?> &middot; <?= e($t['nama_tier']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <a class="btn btn-outline" href="crud_event.php?tab=event">Kelola Event</a>
    </div>

    <?php if ($selectedTierId && $tierSeatInfo): ?>
        <div style="margin-bottom:10px;">
            <span class="badge badge-muted"><?= e($tierSeatInfo['judul_event']) ?></span>
            <span class="badge badge-muted"><?= e($tierSeatInfo['nama_tier']) ?></span>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title">Generator Kursi</span></div>
            <div class="card-body">
                <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="generate_seats">
                    <input type="hidden" name="id_tier" value="<?= (int)$selectedTierId ?>">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Prefix</label>
                        <input type="text" name="prefix" class="form-input" required placeholder="misal A" maxlength="10">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Jumlah</label>
                        <input type="number" name="count" class="form-input" required min="1" max="1000" value="50">
                    </div>
                    <button class="btn btn-primary" type="submit">Generate Kursi</button>
                    <form method="post" style="display:inline;margin:0;" onsubmit="return confirm('Hapus semua kursi yang masih Available pada tier ini? Tindakan tidak dapat dibatalkan.')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_available_seats">
                        <input type="hidden" name="id_tier" value="<?= (int)$selectedTierId ?>">
                        <button class="btn btn-danger" type="submit">Hapus Kursi Tersedia</button>
                    </form>
                </form>
            </div>
        </div>

        <div class="card" style="margin-top:14px;">
            <div class="card-header"><span class="card-title">Daftar Kursi</span></div>
            <div class="card-body">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr><th>Nomor Kursi</th><th>Status</th><th style="width:120px;">Aksi</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rowsSeat as $s): ?>
                                <tr>
                                    <td><?= e($s['nomor_kursi']) ?></td>
                                    <td><span class="badge <?= $s['status_kursi'] === 'Available' ? 'badge-success' : ($s['status_kursi'] === 'Sold' ? 'badge-danger' : 'badge-warning') ?>"><?= e($s['status_kursi']) ?></span></td>
                                    <td>
                                        <?php if ($s['status_kursi'] === 'Available'): ?>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Hapus kursi ini?')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_seat">
                                                <input type="hidden" name="id_kursi" value="<?= (int)$s['id_kursi'] ?>">
                                                <input type="hidden" name="id_tier" value="<?= (int)$selectedTierId ?>">
                                                <button class="btn btn-danger btn-sm" type="submit">Hapus</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="btn btn-ghost btn-sm" style="color:var(--color-text-muted);" title="Diblokir: kursi sudah diperjualbelikan atau dikunci">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$rowsSeat): ?>
                                <tr><td colspan="3" style="color:var(--color-text-muted);text-align:center;">Belum ada kursi. Gunakan generator di atas.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/src/Views/layouts/footer.php'; ?>
