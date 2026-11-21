<?php
/**
 * gate_checkin.php — Validasi Check-in Pintu Masuk (Gate Officer / Admin EO)
 * POST: kode_tiket + nik_peserta + CSRF.
 * Validasi e_tickets JOIN transaksi (status Paid); anti-passback pada status_checkin = 'Sudah Hadir'.
 */
declare(strict_types=1);
require_once __DIR__ . '/src/Config/database.php';
require_once __DIR__ . '/src/Middleware/middleware.php';
maybe_release_expired_transactions($pdo);

require_role(['gate_officer', 'admin_eo']);

$user = current_user();

if (!function_exists('pensi_mask_nik')) {
    function pensi_mask_nik(?string $nik): string
    {
        if ($nik === null || $nik === '') {
            return '-';
        }
        $nik = (string)$nik;
        $len = strlen($nik);
        if ($len <= 8) {
            return str_repeat('X', $len);
        }
        return substr($nik, 0, 4) . str_repeat('X', $len - 8) . substr($nik, -4);
    }
}

$kodeCari = is_string($_GET['kode_tiket'] ?? null) ? mb_substr(strtoupper(trim($_GET['kode_tiket'])), 0, 50) : '';
$tiket = null;
$error = null;
$justCheckedIn = false;

// ---------------------------------------------------------------------------
// Proses check-in (POST)
// ---------------------------------------------------------------------------
if (( $_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('CSRF tidak valid');
    }

    $kodePost = mb_substr(strtoupper(trim((string)($_POST['kode_tiket'] ?? ''))), 0, 50);
    $nikPost  = trim((string)($_POST['nik_peserta'] ?? ''));
    $kodeCari = $kodePost;

    if ($kodePost === '' || $nikPost === '' || mb_strlen($nikPost) > 20) {
        $error = 'Kode tiket dan NIK peserta harus diisi.';
    } else {
        try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'SELECT et.id_etiket, et.kode_tiket, et.nama_peserta, et.nik_peserta, et.status_checkin, et.waktu_checkin, et.id_petugas_gate,
                    t.id_transaksi, t.kode_booking, t.status_bayar,
                    e.judul_event, e.lokasi, e.tgl_mulai, e.tipe_venue,
                    tt.nama_tier, k.nomor_kursi,
                    pg.nama_lengkap AS nama_petugas
               FROM e_tickets et
               JOIN transaksi t ON t.id_transaksi = et.id_transaksi
               JOIN events e ON e.id_event = t.id_event
               JOIN tiket_tier tt ON tt.id_tier = et.id_tier
               LEFT JOIN kursi_event k ON k.id_kursi = et.id_kursi
               LEFT JOIN users pg ON pg.id_user = et.id_petugas_gate
              WHERE et.kode_tiket = ? AND t.status_bayar = \'Paid\'
              FOR UPDATE'
        );
        $stmt->execute([$kodePost]);
        $t = $stmt->fetch();

        if (!$t) {
            throw new Exception('TIKET TIDAK DITEMUKAN');
        }

        if ($t['status_checkin'] === 'Sudah Hadir') {
            throw new Exception(
                'TIKET SUDAH DIGUNAKAN — check-in sebelumnya pada ' . format_tanggal($t['waktu_checkin'])
                . ' oleh petugas ' . e($t['nama_petugas'] ?? '-') . '.'
            );
        }

        if ($nikPost !== $t['nik_peserta']) {
            throw new Exception('NIK peserta tidak sesuai dengan data e-ticket.');
        }

        $pdo->prepare(
            'UPDATE e_tickets SET status_checkin = \'Sudah Hadir\', waktu_checkin = NOW(), id_petugas_gate = ?
             WHERE id_etiket = ?'
        )->execute([(int)$user['id_user'], (int)$t['id_etiket']]);

        catat_audit_log(
            $pdo,
            (int)$user['id_user'],
            'CHECKIN',
            'e_tickets',
            "Gate check-in berhasil: {$t['kode_tiket']} ({$t['nama_tier']}) atas nama {$t['nik_peserta']} — {$t['judul_event']}."
        );

        $pdo->commit();

        $t['status_checkin'] = 'Sudah Hadir';
        $t['waktu_checkin'] = date('Y-m-d H:i:s');
        $t['nama_petugas'] = $user['nama_lengkap'];
        $tiket = $t;
        $justCheckedIn = true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[GATE_CHECKIN] ' . $e->getMessage());
        $error = 'Terjadi kesalahan sistem saat memproses check-in.';
    }
    }
}

// ---------------------------------------------------------------------------
// Tampilan: pencarian tiket (GET ?kode_tiket=)
// ---------------------------------------------------------------------------
if ($kodeCari !== '') {
    $stmt = $pdo->prepare(
        'SELECT et.id_etiket, et.kode_tiket, et.nama_peserta, et.nik_peserta, et.status_checkin, et.waktu_checkin, et.id_petugas_gate,
                t.kode_booking, t.status_bayar,
                e.judul_event, e.lokasi, e.tgl_mulai, e.tipe_venue,
                tt.nama_tier, k.nomor_kursi,
                pg.nama_lengkap AS nama_petugas
           FROM e_tickets et
           JOIN transaksi t ON t.id_transaksi = et.id_transaksi
           JOIN events e ON e.id_event = t.id_event
           JOIN tiket_tier tt ON tt.id_tier = et.id_tier
           LEFT JOIN kursi_event k ON k.id_kursi = et.id_kursi
           LEFT JOIN users pg ON pg.id_user = et.id_petugas_gate
          WHERE et.kode_tiket = ? AND t.status_bayar = \'Paid\'
          LIMIT 1'
    );
    $stmt->execute([$kodeCari]);
    $tiket = $stmt->fetch();
}

// Riwayat check-in terbaru (10)
$stmtRecent = $pdo->query(
    'SELECT et.kode_tiket, et.nama_peserta, et.nik_peserta, et.waktu_checkin,
            t.kode_booking, e.judul_event, tt.nama_tier, k.nomor_kursi,
            pg.nama_lengkap AS nama_petugas
       FROM e_tickets et
       JOIN transaksi t ON t.id_transaksi = et.id_transaksi
       JOIN events e ON e.id_event = t.id_event
       JOIN tiket_tier tt ON tt.id_tier = et.id_tier
       LEFT JOIN kursi_event k ON k.id_kursi = et.id_kursi
       LEFT JOIN users pg ON pg.id_user = et.id_petugas_gate
      WHERE et.status_checkin = \'Sudah Hadir\'
      ORDER BY et.waktu_checkin DESC
      LIMIT 10'
);
$recentCheckins = $stmtRecent->fetchAll();

$pageTitle = 'Gate Check-in';
$pageSubtitle = 'Validasi e-tiket dan NIK peserta di pintu masuk acara';
$activeNav = 'gate_checkin.php';
require __DIR__ . '/src/Views/layouts/header.php';
?>

<div class="card card-elevated" style="max-width:720px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
        <div>
            <h2 style="margin:0 0 4px 0;font-size:20px;">Validasi E-Ticket di Pintu Masuk</h2>
            <p style="margin:0;color:var(--color-text-secondary);font-size:14px;">
                Masukkan kode tiket, lalu konfirmasikan NIK peserta.
            </p>
        </div>
        <span class="badge badge-info">Petugas: <?= e($user['nama_lengkap']) ?></span>
    </div>

    <form method="GET" action="gate_checkin.php" style="margin-bottom:16px;">
        <div class="form-group">
            <label class="form-label">Kode E-Ticket</label>
            <input type="text" name="kode_tiket" class="form-control" value="<?= e($kodeCari) ?>"
                   placeholder="Contoh: TKT-20261119-....-1" autofocus
                   style="font-family:monospace;font-size:16px;text-transform:uppercase;">
        </div>
        <button type="submit" class="btn btn-primary">Periksa Tiket</button>
        <a href="gate_checkin.php" class="btn btn-outline">Reset</a>
    </form>

    <?php if ($error !== null): ?>
        <div class="alert alert-danger" role="status"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($justCheckedIn && $tiket): ?>
        <div class="alert alert-success" role="status" style="text-align:center;">
            <div style="font-size:20px;font-weight:700;color:var(--color-success);margin-bottom:6px;">✔ CHECK-IN BERHASIL</div>
            <p style="margin:4px 0;color:var(--color-text-muted);">
                <strong><?= e($tiket['nama_peserta']) ?></strong> telah diperbolehkan masuk.
            </p>
            <table style="margin:10px auto 0 auto;text-align:left;font-size:14px;color:var(--color-text-secondary);">
                <tr><td style="padding:2px 8px 2px 0;"><strong>Event</strong></td><td><?= e($tiket['judul_event']) ?></td></tr>
                <tr><td style="padding:2px 8px 2px 0;"><strong>Tier / Kursi</strong></td><td><?= e($tiket['nama_tier']) ?> · <?= !empty($tiket['nomor_kursi']) ? 'Kursi ' . e($tiket['nomor_kursi']) : 'Free Standing' ?></td></tr>
                <tr><td style="padding:2px 8px 2px 0;"><strong>Kode Tiket</strong></td><td><code><?= e($tiket['kode_tiket']) ?></code></td></tr>
                <tr><td style="padding:2px 8px 2px 0;"><strong>Waktu Check-in</strong></td><td><?= format_tanggal($tiket['waktu_checkin']) ?></td></tr>
                <tr><td style="padding:2px 8px 2px 0;"><strong>Petugas Gate</strong></td><td><?= e($user['nama_lengkap']) ?></td></tr>
            </table>
        </div>
    <?php elseif ($kodeCari !== ''): ?>
        <?php if (!$tiket): ?>
            <div class="alert alert-danger" role="status">
                <strong style="font-size:16px;">TIKET TIDAK DITEMUKAN</strong><br>
                <code><?= e($kodeCari) ?></code> tidak terdaftar atau pembelumnya belum lunas.
            </div>
        <?php elseif ($tiket['status_checkin'] === 'Sudah Hadir'): ?>
            <div class="alert alert-danger" role="status" style="text-align:center;">
                <div style="font-size:20px;font-weight:700;color:var(--color-danger);margin-bottom:6px;">TIKET SUDAH DIGUNAKAN</div>
                <p style="margin:4px 0;color:var(--color-text-secondary);">
                    E-ticket <code><?= e($tiket['kode_tiket']) ?></code> sudah dilakukan check-in sebelumnya.
                </p>
                <p style="margin:4px 0;color:var(--color-text-secondary);">
                    Waktu: <strong><?= format_tanggal($tiket['waktu_checkin']) ?></strong><br>
                    Petugas gate: <strong><?= e($tiket['nama_petugas'] ?? '-') ?></strong>
                </p>
                <p style="margin-top:10px;color:var(--color-danger);font-weight:600;">
                    TOLAK PEMAKAIAN ULANG — anti-passback aktif.
                </p>
            </div>
        <?php else: ?>
            <div class="alert alert-success" role="status" style="text-align:center;margin-bottom:14px;">
                <div style="font-size:15px;font-weight:600;color:var(--color-success);">TIKET VALID — SILAKAN KONFIRMASI NIK</div>
                <p style="margin:4px 0;color:var(--color-text-muted);">
                    <strong><?= e($tiket['nama_peserta']) ?></strong> ·
                    <?= e($tiket['nama_tier']) ?> ·
                    <?= !empty($tiket['nomor_kursi']) ? 'Kursi ' . e($tiket['nomor_kursi']) : 'Free Standing' ?> ·
                    NIK <code><?= pensi_mask_nik($tiket['nik_peserta']) ?></code>
                </p>
            </div>

            <form method="POST" action="gate_checkin.php">
                <?= csrf_field() ?>
                <input type="hidden" name="kode_tiket" value="<?= e($tiket['kode_tiket']) ?>">
                <div class="form-group">
                    <label class="form-label">Masukkan NIK Peserta (sesuai KTP)</label>
                    <input type="text" name="nik_peserta" class="form-control" autofocus
                           placeholder="Contoh: 3174010101010011"
                           style="font-family:monospace;font-size:16px;" required maxlength="20">
                    <small style="color:var(--color-text-muted);">NIK akan dibandingkan dengan data e-ticket; NIK tidak pernah ditampilkan penuh di layar ini.</small>
                </div>
                <button type="submit" class="btn btn-primary" style="font-size:16px;padding:12px 28px;">YA, IZINKAN MASUK</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="card" style="margin-top:24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:8px;">
        <h3 style="margin:0;font-size:16px;">Check-in Terbaru (10)</h3>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:22%;">Kode Tiket</th>
                    <th>Peserta</th>
                    <th style="width:20%;">NIK</th>
                    <th>Tier / Kursi</th>
                    <th style="width:18%;">Waktu Check-in</th>
                    <th style="width:16%;">Petugas Gate</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentCheckins)): ?>
                    <tr><td colspan="6" align="center" style="color:var(--color-text-muted);padding:24px;">Belum ada check-in hari ini.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentCheckins as $c): ?>
                        <tr>
                            <td><code style="font-size:13px;"><?= e($c['kode_tiket']) ?></code></td>
                            <td><?= e($c['nama_peserta']) ?></td>
                            <td><code><?= pensi_mask_nik($c['nik_peserta']) ?></code></td>
                                <td><?= e($c['nama_tier']) ?> · <?= !empty($c['nomor_kursi']) ? 'Kursi ' . e($c['nomor_kursi']) : 'Free Standing' ?></td>
                            <td><small><?= format_tanggal($c['waktu_checkin']) ?></small></td>
                            <td><small><?= e($c['nama_petugas'] ?? '-') ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/src/Views/layouts/footer.php'; ?>
