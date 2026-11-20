<?php
// views/gate/audit.php
$e = $e ?? function($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); };
$attr = $attr ?? function($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); };
$rupiah = $rupiah ?? function($v) { return 'Rp ' . number_format($v, 0, ',', '.'); };
$date = $date ?? function($v) { return date('d-m-Y', strtotime($v)); };
$datetime = $datetime ?? function($v) { return date('d-m-Y H:i', strtotime($v)); };
?>
<div class="breadcrumb">
    <a href="/">Beranda</a>
    <span class="breadcrumb-separator">/</span>
    <a href="/gate">Gate Check-in</a>
    <span class="breadcrumb-separator">/</span>
    <span class="breadcrumb-current">Audit Log</span>
</div>

<div class="page-header">
    <h1 class="page-title">Audit Log Sistem</h1>
    <p class="page-subtitle">Rekam jejak aktivitas sistem untuk audit trail</p>
</div>

<div class="card">
    <div class="card-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <h2 style="font-size: 1.125rem; font-weight: 700;">Filter</h2>
        <form method="GET" action="/gate/audit" style="display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
            <div class="form-group" style="margin: 0;">
                <label for="entity">Entitas</label>
                <select id="entity" name="entity" class="input">
                    <option value="">Semua Entitas</option>
                    <?php foreach ($entities as $ent): ?>
                        <option value="<?= $e($ent) ?>" <?= $selectedEntity === $ent ? 'selected' : '' ?>><?= $e($ent) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin: 0;">
                <label for="limit">Limit</label>
                <input type="number" id="limit" name="limit" class="input" value="<?= $e($limit ?? 100) ?>" min="10" max="500" style="width: 100px;">
            </div>
            <button type="submit" class="btn btn-primary" style="height: fit-content;">Filter</button>
            <a href="/gate/audit" class="btn btn-secondary">Reset</a>
        </form>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($logs)): ?>
            <div style="text-align: center; padding: 3rem; color: var(--color-slate-500);">
                <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin: 0 auto 1rem; color: var(--color-slate-300);">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2H7a2 2 0 01-2-2v-2"/>
                </svg>
                <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 0.5rem;">Tidak ada log audit</h3>
                <p>Tidak ada aktivitas yang sesuai dengan filter.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>User</th>
                            <th>Role</th>
                            <th>Aksi</th>
                            <th>Entitas</th>
                            <th>Keterangan</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= $datetime($log['waktu']) ?></td>
                            <td>
                                <strong><?= $e($log['nama']) ?></strong>
                                <?php if ($log['email']): ?>
                                    <br><small style="color: var(--color-slate-500);"><?= $e($log['email']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-<?= $e(match($log['role']) { 'admin_eo' => 'purple', 'finance' => 'orange', 'gate_officer' => 'emerald', default => 'slate' }) ?>"><?= $e(ucfirst($log['role'])) ?></span></td>
                            <td><span class="badge badge-purple"><?= $e($log['aksi']) ?></span></td>
                            <td><?= $e($log['entitas']) ?></td>
                            <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= $e($log['keterangan']) ?></td>
                            <td><code><?= $e($log['ip_address'] ?? '-') ?></code></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>