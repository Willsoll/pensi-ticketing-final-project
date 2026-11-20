<?php
// views/booking/identity.php
$e = $e ?? function($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); };
$attr = $attr ?? function($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); };
$rupiah = $rupiah ?? function($v) { return 'Rp ' . number_format($v, 0, ',', '.'); };
$date = $date ?? function($v) { return date('d-m-Y', strtotime($v)); };
?>
<div class="breadcrumb">
    <a href="/">Beranda</a>
    <span class="breadcrumb-separator">/</span>
    <a href="/event/<?= $e($event['id_event']) ?>"><?= $e($event['judul_event']) ?></a>
    <span class="breadcrumb-separator">/</span>
    <span class="breadcrumb-current">Identity Binding</span>
</div>

<div class="page-header">
    <h1 class="page-title">Identity Binding</h1>
    <p class="page-subtitle">Isi data pemegang tiket untuk setiap tiket yang dipesan</p>
</div>

<div class="grid grid-2" style="gap: var(--space-8);">
    <!-- Form Identity Binding -->
    <div class="card">
        <div class="card-header">
            <h2 style="font-size: 1.125rem; font-weight: 700;">Data Pemegang Tiket (<?= $qty ?> Tiket)</h2>
        </div>
        <div class="card-body">
            <p style="color: var(--color-slate-500); margin-bottom: var(--space-6);">
                Setiap tiket wajib diisi <strong>Nama Lengkap</strong> dan <strong>NIK KTP (16 digit)</strong>
                pemegang tiket yang akan hadir.
                <?= $qty > 1 ? 'Setiap tiket harus identitas yang berbeda.' : '' ?>
            </p>

            <form method="POST" action="/booking/identity" id="identityForm">
                <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">

                <?php for ($i = 0; $i < $qty; $i++): ?>
                    <div style="border: 1px solid var(--color-slate-200); border-radius: var(--radius-md); padding: var(--space-5); margin-bottom: var(--space-5); background: var(--color-slate-50);">
                        <h4 style="font-weight: 600; color: var(--color-indigo-dark); margin-bottom: var(--space-4);">
                            TIKET #<?= $i + 1 ?> &mdash; <?= $e($tier['nama_tier']) ?>
                            <?= $tipe_venue === 'Seated' && isset($seats[$i]) ? '| Kursi: ' . $e($seats[$i]['nomor_kursi']) : '| Free Standing' ?>
                        </h4>

                        <div class="form-group">
                            <label for="nama_peserta_<?= $i ?>">Nama Lengkap Peserta <span style="color: var(--color-red);">*</span></label>
                            <input type="text" id="nama_peserta_<?= $i ?>" name="nama_peserta_<?= $i ?>" class="input" required minlength="3" maxlength="100" value="<?= $e($_POST["nama_peserta_{$i}"] ?? ($i === 0 ? ($current_user['nama'] ?? '') : '')) ?>">
                        </div>

                        <div class="form-group">
                            <label for="nik_peserta_<?= $i ?>">NIK KTP (16 Digit Angka) <span style="color: var(--color-red);">*</span></label>
                            <input type="text" id="nik_peserta_<?= $i ?>" name="nik_peserta_<?= $i ?>" class="input" required pattern="[0-9]{16}" maxlength="16" value="<?= $e($_POST["nik_peserta_{$i}"] ?? ($i === 0 ? ($current_user['nik'] ?? '') : '')) ?>">
                            <small style="color: var(--color-slate-500);">NIK KTP 16 digit angka</small>
                        </div>
                    </div>
                <?php endfor; ?>

                <div style="border-top: 1px solid var(--color-slate-200); padding-top: var(--space-5); margin-top: var(--space-3);">
                    <button type="submit" class="btn btn-primary" style="width: 100%;" formaction="/booking/identity">Lanjut ke Voucher & Checkout</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Voucher & Summary -->
    <div class="card" style="height: fit-content;">
        <div class="card-header">
            <h2 style="font-size: 1.125rem; font-weight: 700;">Voucher & Ringkasan</h2>
        </div>
        <div class="card-body">
            <div class="form-group" style="margin-bottom: var(--space-6);">
                <label for="voucher_code">Kode Voucher (Opsional)</label>
                <div style="display: flex; gap: var(--space-2);">
                    <input type="text" id="voucher_code" name="voucher_code" class="input" placeholder="Contoh: PENSI10K" style="text-transform: uppercase;" value="<?= $e($_POST['voucher_code'] ?? '') ?>">
                    <button type="submit" class="btn btn-secondary" formaction="/booking/identity" name="action" value="apply_voucher">Terapkan</button>
                </div>
                <small style="color: var(--color-slate-500);">Masukkan kode voucher dan klik Terapkan untuk melihat potongan</small>
            </div>

            <?php if ($flash['success'] ?? false): ?>
                <div class="alert alert-success" style="margin-bottom: var(--space-4);"><?= $e($flash['success']) ?></div>
            <?php endif; ?>
            <?php if ($flash['error'] ?? false): ?>
                <div class="alert alert-error" style="margin-bottom: var(--space-4);"><?= $e($flash['error']) ?></div>
            <?php endif; ?>

            <div style="border: 1px solid var(--color-slate-200); border-radius: var(--radius-md); padding: var(--space-5); background: var(--color-slate-50);">
                <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-3);">
                    <span>Subtotal (<?= $qty ?> x <?= $rupiah($tier['harga']) ?>)</span>
                    <strong><?= $rupiah($subtotal) ?></strong>
                </div>

                <?php if ($discount > 0): ?>
                    <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-3); color: var(--color-emerald);">
                        <span>Potongan Voucher (<?= $e($voucher['kode_voucher'] ?? '') ?>)</span>
                        <strong>- <?= $rupiah($discount) ?></strong>
                    </div>
                <?php endif; ?>

                <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-3);">
                    <span>Biaya Layanan</span>
                    <span><?= $rupiah(0) ?></span>
                </div>

                <div style="border-top: 1px solid var(--color-slate-200); padding-top: var(--space-3); display: flex; justify-content: space-between; font-size: 1.125rem; font-weight: 700; color: var(--color-purple-electric);">
                    <span>TOTAL BAYAR</span>
                    <span><?= $rupiah($total) ?></span>
                </div>
            </div>

            <form method="POST" action="/booking/identity" style="margin-top: var(--space-6);">
                <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
                <input type="hidden" name="voucher_code" value="<?= $e($_POST['voucher_code'] ?? '') ?>">
                <button type="submit" name="action" value="create_reservation" class="btn btn-primary" style="width: 100%;">
                    Buat Reservasi & Bayar (15 Menit)
                </button>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('identityForm').addEventListener('submit', function(e) {
    const nikFields = document.querySelectorAll('input[name^="nik_peserta_"]');
    const niks = [];
    for (const field of nikFields) {
        if (!/^[0-9]{16}$/.test(field.value)) {
            alert('NIK harus 16 digit angka: ' + field.id);
            e.preventDefault();
            field.focus();
            return;
        }
        if (niks.includes(field.value)) {
            alert('NIK duplikat: ' + field.value);
            e.preventDefault();
            field.focus();
            return;
        }
        niks.push(field.value);
    }

    const namaFields = document.querySelectorAll('input[name^="nama_peserta_"]');
    for (const field of namaFields) {
        if (field.value.trim().length < 3) {
            alert('Nama minimal 3 karakter: ' + field.id);
            e.preventDefault();
            field.focus();
            return;
        }
    }
});
</script>