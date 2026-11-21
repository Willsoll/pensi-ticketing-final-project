<?php
declare(strict_types=1);

/**
 * kelola_voucher.php — CRUD voucher_promo (admin_eo only).
 * Kode unik, validasi potongan, status Aktif/Kedaluwarsa/Habis.
 * Hapus diblokir jika terpakai di transaksi; nonaktifkan via kuota_pakai=0.
 */
require_once __DIR__ . '/src/Config/database.php';
require_once __DIR__ . '/src/Middleware/middleware.php';
maybe_release_expired_transactions($pdo);

require_role(['admin_eo']);

$user = current_user();

$q = function (string $sql, array $params = []) use ($pdo): \PDOStatement {
    $s = $pdo->prepare($sql);
    $s->execute($params);
    return $s;
};

$editVoucher = null;
$editId = filter_var($_GET['edit_voucher'] ?? null, FILTER_VALIDATE_INT);

if ((isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST')) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('CSRF tidak valid');
    }
    $action = $_POST['action'] ?? '';
    $go = function () { header('Location: kelola_voucher.php'); exit; };

    if ($action === 'save_voucher') {
        $kode     = strtoupper(trim($_POST['kode_voucher'] ?? ''));
        $tipe     = $_POST['tipe_potongan'] ?? '';
        $nilai    = (int)($_POST['nilai_potongan'] ?? 0);
        $minTrx   = (int)($_POST['min_transaksi'] ?? 0);
        $kuota    = (int)($_POST['kuota_pakai'] ?? 0);
        $expired  = $_POST['expired_date'] ?? '';
        $idVoucher= (int)($_POST['id_voucher'] ?? 0);

        $ok = $kode !== ''
            && in_array($tipe, ['Persen', 'Nominal'], true)
            && $nilai > 0
            && ($tipe !== 'Persen' || ($nilai >= 1 && $nilai <= 100))
            && $minTrx >= 0
            && $kuota >= 0
            && $expired !== ''
            && date_create_from_format('Y-m-d', $expired) !== false;
        if (!$ok) {
            set_flash('ERROR', 'Data voucher tidak lengkap atau tidak valid (nilai persen harus 1-100).');
        } else {
            $st = $q('SELECT COUNT(*) FROM voucher_promo WHERE kode_voucher=? AND id_voucher<>?', [$kode, $idVoucher]);
            $exists = (int)$st->fetchColumn();
            if ($exists) {
                set_flash('ERROR', "Kode voucher '$kode' sudah terdaftar.");
            } elseif ($idVoucher > 0) {
                $pdo->prepare('UPDATE voucher_promo SET kode_voucher=?, tipe_potongan=?, nilai_potongan=?,
                               min_transaksi=?, kuota_pakai=?, expired_date=? WHERE id_voucher=?')
                    ->execute([$kode, $tipe, $nilai, $minTrx, $kuota, $expired, $idVoucher]);
                catat_audit_log($pdo, $user['id_user'], 'UPDATE', 'voucher_promo',
                    "Voucher #$idVoucher ($kode) diperbarui.");
                set_flash('SUCCESS', 'Voucher diperbarui.');
            } else {
                $pdo->prepare('INSERT INTO voucher_promo (kode_voucher,tipe_potongan,nilai_potongan,min_transaksi,kuota_pakai,expired_date)
                               VALUES (?,?,?,?,?,?)')
                    ->execute([$kode, $tipe, $nilai, $minTrx, $kuota, $expired]);
                $newId = (int)$pdo->lastInsertId();
                catat_audit_log($pdo, $user['id_user'], 'CREATE', 'voucher_promo', "Voucher baru #$newId: $kode");
                set_flash('SUCCESS', 'Voucher ditambahkan.');
            }
        }
        $go();
    }

    if ($action === 'delete_voucher') {
        $idVoucher = (int)($_POST['id_voucher'] ?? 0);
        $st = $q('SELECT COUNT(*) FROM transaksi WHERE id_voucher=?', [$idVoucher]);
        $used = (int)$st->fetchColumn();
        if ($used > 0) {
            set_flash('ERROR', "Voucher tidak dapat dihapus karena sudah terpakai pada $used transaksi. Nonaktifkan alih-alih menghapus.");
        } else {
            $pdo->prepare('DELETE FROM voucher_promo WHERE id_voucher=?')->execute([$idVoucher]);
            catat_audit_log($pdo, $user['id_user'], 'DELETE', 'voucher_promo', "Voucher #$idVoucher dihapus.");
            set_flash('SUCCESS', 'Voucher dihapus.');
        }
        $go();
    }

    if ($action === 'deactivate_voucher') {
        $idVoucher = (int)($_POST['id_voucher'] ?? 0);
        $pdo->prepare('UPDATE voucher_promo SET kuota_pakai = 0 WHERE id_voucher=?')->execute([$idVoucher]);
        catat_audit_log($pdo, $user['id_user'], 'UPDATE', 'voucher_promo', "Voucher #$idVoucher dinonaktifkan (kuota dipaksa ke 0).");
        set_flash('SUCCESS', 'Voucher dinonaktifkan (kuota pakai disetel ke 0).');
        $go();
    }
}

if ($editId) {
    $st = $q('SELECT * FROM voucher_promo WHERE id_voucher=?', [$editId]);
    $editVoucher = $st->fetch();
}

$rows = $pdo->query('
    SELECT v.*, (SELECT COUNT(*) FROM transaksi t WHERE t.id_voucher = v.id_voucher) AS used_count
    FROM voucher_promo v
    ORDER BY kode_voucher
')->fetchAll();

$today = date('Y-m-d');

$pageTitle = 'Kelola Voucher';
$pageSubtitle = 'CRUD voucher promosi dan potongan';
$activeNav = 'kelola_voucher.php';

require __DIR__ . '/src/Views/layouts/header.php';
?>

<div style="display:flex;gap:10px;align-items:center;margin-bottom:14px;flex-wrap:wrap;">
    <a class="btn btn-primary" href="#form-voucher">+ Tambah Voucher</a>
</div>

<div class="card">
    <div class="card-header"><span class="card-title">Daftar Voucher Promo</span></div>
    <div class="card-body">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kode Voucher</th>
                        <th>Tipe</th>
                        <th style="text-align:right;">Nilai Potongan</th>
                        <th style="text-align:right;">Min Transaksi</th>
                        <th style="text-align:center;">Sisa Kuota</th>
                        <th style="text-align:center;">Digunakan</th>
                        <th>Expired</th>
                        <th>Status</th>
                        <th style="width:240px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $v): ?>
                        <?php
                        if ($v['kuota_pakai'] <= 0) { $status = 'Habis'; $badge = 'badge-danger'; }
                        elseif ($v['expired_date'] < $today) { $status = 'Kedaluwarsa'; $badge = 'badge-warning'; }
                        else { $status = 'Aktif'; $badge = 'badge-success'; }
                        ?>
                        <tr>
                            <td><?= e($v['kode_voucher']) ?></td>
                            <td><?= e($v['tipe_potongan']) ?></td>
                            <td style="text-align:right;"><?= format_rupiah((int)$v['nilai_potongan']) ?></td>
                            <td style="text-align:right;"><?= format_rupiah((int)$v['min_transaksi']) ?></td>
                            <td style="text-align:center;"><?= (int)$v['kuota_pakai'] ?></td>
                            <td style="text-align:center;"><?= (int)$v['used_count'] ?></td>
                            <td><?= format_tanggal($v['expired_date'], false) ?></td>
                            <td><span class="badge <?= $badge ?>"><?= $status ?></span></td>
                            <td>
                                <a class="btn btn-ghost btn-sm" href="kelola_voucher.php?edit_voucher=<?= (int)$v['id_voucher'] ?>">Ubah</a>
                                <?php if ((int)$v['used_count'] > 0): ?>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Nonaktifkan voucher ini? Kuota pakai akan dipaksa ke 0.')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="deactivate_voucher">
                                        <input type="hidden" name="id_voucher" value="<?= (int)$v['id_voucher'] ?>">
                                        <button class="btn btn-warning btn-sm" type="submit">Nonaktifkan</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Hapus voucher ini?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_voucher">
                                        <input type="hidden" name="id_voucher" value="<?= (int)$v['id_voucher'] ?>">
                                        <button class="btn btn-danger btn-sm" type="submit">Hapus</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?>
                        <tr><td colspan="9" style="color:var(--color-text-muted);text-align:center;">Belum ada voucher.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$selKode  = $editVoucher ? $editVoucher['kode_voucher'] : ($_POST['kode_voucher'] ?? '');
$selTipe  = $editVoucher ? $editVoucher['tipe_potongan'] : ($_POST['tipe_potongan'] ?? '');
$selNilai = $editVoucher ? $editVoucher['nilai_potongan'] : ($_POST['nilai_potongan'] ?? '');
$selMin   = $editVoucher ? $editVoucher['min_transaksi'] : ($_POST['min_transaksi'] ?? '');
$selKuota = $editVoucher ? $editVoucher['kuota_pakai'] : ($_POST['kuota_pakai'] ?? '');
$selDate  = $editVoucher ? $editVoucher['expired_date'] : ($_POST['expired_date'] ?? '');
?>
<div class="card" id="form-voucher">
    <div class="card-header"><span class="card-title"><?= $editVoucher ? 'Ubah Voucher' : 'Tambah Voucher' ?></span></div>
    <div class="card-body">
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_voucher">
            <?php if ($editVoucher): ?><input type="hidden" name="id_voucher" value="<?= (int)$editVoucher['id_voucher'] ?>"><?php endif; ?>
            <div class="form-group">
                <label class="form-label">Kode Voucher</label>
                <input type="text" name="kode_voucher" class="form-input" required
                       value="<?= e($selKode) ?>">
                <p class="form-hint">Disimpan huruf kapital, harus unik.</p>
            </div>
            <div class="form-group">
                <label class="form-label">Tipe Potongan</label>
                <select name="tipe_potongan" class="form-select" required>
                    <option value="Persen" <?= ($selTipe === 'Persen') ? 'selected' : '' ?>>Persen (%)</option>
                    <option value="Nominal" <?= ($selTipe === 'Nominal') ? 'selected' : '' ?>>Nominal (Rp)</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Nilai Potongan</label>
                <input type="number" name="nilai_potongan" class="form-input" required min="0"
                       value="<?= e($selNilai) ?>">
                <p class="form-hint">Untuk Persen: 1&ndash;100. Untuk Nominal: jumlah rupiah.</p>
            </div>
            <div class="form-group">
                <label class="form-label">Minimal Transaksi</label>
                <input type="number" name="min_transaksi" class="form-input" required min="0"
                       value="<?= e($selMin) ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Kuota Pakai</label>
                <input type="number" name="kuota_pakai" class="form-input" required min="0"
                       value="<?= e($selKuota) ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Tanggal Kadaluarsa</label>
                <input type="date" name="expired_date" class="form-input" required
                       value="<?= e($selDate) ?>">
            </div>
            <div class="form-group">
                <button class="btn btn-primary" type="submit"><?= $editVoucher ? 'Simpan Perubahan' : 'Simpan Voucher' ?></button>
                <a class="btn btn-ghost" href="kelola_voucher.php">Batal</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/src/Views/layouts/footer.php'; ?>
