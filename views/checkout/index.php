<?php
// views/checkout/index.php
$e = $e ?? function($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); };
$attr = $attr ?? function($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); };
$rupiah = $rupiah ?? function($v) { return 'Rp ' . number_format($v, 0, ',', '.'); };
$date = $date ?? function($v) { return date('d-m-Y', strtotime($v)); };
$datetime = $datetime ?? function($v) { return date('d-m-Y H:i', strtotime($v)); };
?>
<div class="breadcrumb">
    <a href="/">Beranda</a>
    <span class="breadcrumb-separator">/</span>
    <a href="/my-bookings">Pemesanan Saya</a>
    <span class="breadcrumb-separator">/</span>
    <span class="breadcrumb-current">Checkout</span>
</div>

<div class="page-header">
    <h1 class="page-title">Checkout & Pembayaran</h1>
    <p class="page-subtitle">Selesaikan pembayaran dalam 15 menit</p>
</div>

<div class="grid grid-2" style="gap: var(--space-8);">
    <!-- Countdown & Status -->
    <div class="card">
        <div class="card-header">
            <h2 style="font-size: 1.125rem; font-weight: 700;">Status Pembayaran</h2>
        </div>
        <div class="card-body">
            <div style="text-align: center; padding: var(--space-6);">
                <div style="font-size: 3rem; font-weight: 700; color: var(--color-orange-bright); font-family: 'Source Code Pro', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-variant-numeric: tabular-nums;" id="countdown">
                    <?= gmdate('H:i:s', $remaining) ?>
                </div>
                <p style="color: var(--color-slate-500); margin-top: var(--space-2);">Waktu tersisa untuk pembayaran</p>
                <p style="margin-top: var(--space-4); color: var(--color-slate-600);">
                    Batas akhir: <strong><?= $datetime($reservation['batas_bayar_at']) ?> WIB</strong>
                </p>

                <div style="margin-top: var(--space-6); padding: var(--space-4); background: var(--color-slate-50); border-radius: var(--radius-md);">
                    <div style="font-size: 0.875rem; color: var(--color-slate-500); margin-bottom: var(--space-2);">Status Reservasi</div>
                    <span class="badge badge-warning">Menunggu Bayar</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Details & Upload -->
    <div class="card" style="height: fit-content;">
        <div class="card-header">
            <h2 style="font-size: 1.125rem; font-weight: 700;">Rincian Pembayaran</h2>
        </div>
        <div class="card-body">
            <div style="border: 1px solid var(--color-slate-200); border-radius: var(--radius-md); padding: var(--space-5); background: var(--color-slate-50); margin-bottom: var(--space-6);">
                <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-3);">
                    <span>Subtotal (<?= $reservation['qty'] ?> x <?= $rupiah($reservation['harga']) ?>)</span>
                    <strong><?= $rupiah($reservation['harga'] * $reservation['qty']) ?></strong>
                </div>
                <?php if ($reservation['diskon'] > 0): ?>
                    <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-3); color: var(--color-emerald);">
                        <span>Potongan Voucher</span>
                        <strong>- <?= $rupiah($reservation['diskon']) ?></strong>
                    </div>
                <?php endif; ?>
                <div style="border-top: 1px solid var(--color-slate-200); padding-top: var(--space-3); display: flex; justify-content: space-between; font-size: 1.125rem; font-weight: 700; color: var(--color-purple-electric);">
                    <span>TOTAL BAYAR</span>
                    <span><?= $rupiah($reservation['total_bayar']) ?></span>
                </div>
            </div>

            <div style="margin-bottom: var(--space-6);">
                <h3 style="font-weight: 600; margin-bottom: var(--space-4);">Rekening Tujuan</h3>
                <div style="background: var(--color-slate-50); border: 1px solid var(--color-slate-200); border-radius: var(--radius-md); padding: var(--space-5);">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                        <div>
                            <div style="font-size: 0.875rem; color: var(--color-slate-500); margin-bottom: var(--space-1);">Bank</div>
                            <div style="font-weight: 600;">Bank BCA</div>
                        </div>
                        <div>
                            <div style="font-size: 0.875rem; color: var(--color-slate-500); margin-bottom: var(--space-1);">No. Rekening</div>
                            <div style="font-weight: 600; font-family: 'Source Code Pro', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;">123-456-7890</div>
                        </div>
                        <div>
                            <div style="font-size: 0.875rem; color: var(--color-slate-500); margin-bottom: var(--space-1);">Atas Nama</div>
                            <div style="font-weight: 600;">Pensi Ticketing Platform</div>
                        </div>
                        <div>
                            <div style="font-size: 0.875rem; color: var(--color-slate-500); margin-bottom: var(--space-1);">Jumlah</div>
                            <div style="font-weight: 700; color: var(--color-purple-electric); font-size: 1.125rem;"><?= $rupiah($reservation['total_bayar']) ?></div>
                        </div>
                    </div>
                    <div style="margin-top: var(--space-4); padding-top: var(--space-4); border-top: 1px solid var(--color-slate-200); color: var(--color-slate-500); font-size: 0.875rem;">
                        Berikan keterangan transfer: <strong style="color: var(--color-purple-electric); font-family: 'Source Code Pro', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;"><?= $e($reservation['kode_booking']) ?></strong>
                    </div>
                </div>
            </div>

            <!-- Upload Proof -->
            <div style="border: 2px dashed var(--color-slate-300); border-radius: var(--radius-lg); padding: var(--space-8); text-align: center; margin-bottom: var(--space-6); transition: border-color var(--transition-fast), background var(--transition-fast);" id="dropzone">
                <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--color-slate-400); margin-bottom: var(--space-4);" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.882-1.993L12 14l5 5 1.115-1.115A4.003 4.003 0 0018 10.5c0-2.167-1.76-4-4-4H8.882c-.422 0-.834.076-1.206.213l-1.114 1.114A4.003 4.003 0 006 14.5c0 2.167 1.76 4 4 4h2.5a1 1 0 010 2H6a4 4 0 104 4h11a1 1 0 000-2H8.882z"/>
                </svg>
                <p style="color: var(--color-slate-500); margin-bottom: var(--space-4);">Seret file bukti transfer ke sini atau klik untuk memilih</p>
                <p style="font-size: 0.875rem; color: var(--color-slate-500);">Format: JPG, PNG, WEBP | Maksimal 2MB</p>
                <input type="file" id="bukti_bayar" name="bukti_bayar" accept="image/jpeg,image/png,image/webp" style="display: none;" required>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('bukti_bayar').click()">Pilih File</button>
            </div>

            <form method="POST" action="/checkout/<?= $e($reservation['id_reservasi']) ?>/upload" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
                <button type="submit" class="btn btn-primary" style="width: 100%;" id="uploadBtn" disabled>
                    <span id="uploadBtnText">Upload Bukti Bayar</span>
                </button>
            </form>

            <div id="previewContainer" style="display: none; margin-top: var(--space-4);">
                <div style="display: flex; align-items: center; gap: var(--space-4); padding: var(--space-4); background: var(--color-slate-50); border-radius: var(--radius-md); border: 1px solid var(--color-slate-200);">
                    <img id="previewImage" src="" alt="Preview" style="width: 80px; height: 80px; object-fit: cover; border-radius: var(--radius-md);">
                    <div>
                        <div style="font-weight: 600;">Bukti transfer siap diupload</div>
                        <div style="font-size: 0.875rem; color: var(--color-slate-500);" id="previewFileName"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Attendees & Seats -->
<div class="card" style="margin-top: var(--space-8);">
    <div class="card-header">
        <h2 style="font-size: 1.125rem; font-weight: 700;">Detail Peserta</h2>
    </div>
    <div class="card-body" style="padding: 0;">
        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th>Nama Peserta</th>
                    <th>NIK</th>
                    <th>Kursi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reservation['items'] as $index => $item): ?>
                <tr>
                    <td style="text-align: center;"><?= $index + 1 ?></td>
                    <td><strong><?= $e($item['nama_peserta']) ?></strong></td>
                    <td><code><?= $e($item['nik_peserta']) ?></code></td>
                    <td style="text-align: center;">
                        <?= $item['nomor_kursi'] ? '<span class="badge badge-primary">' . $e($item['nomor_kursi']) . '</span>' : '<span class="badge badge-neutral">Free Standing</span>' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Countdown timer
    let remaining = <?= (int)$remaining ?>;
    const countdownEl = document.getElementById('countdown');

    function updateCountdown() {
        const hours = Math.floor(remaining / 3600).toString().padStart(2, '0');
        const minutes = Math.floor((remaining % 3600) / 60).toString().padStart(2, '0');
        const seconds = (remaining % 60).toString().padStart(2, '0');
        countdownEl.textContent = `${hours}:${minutes}:${seconds}`;

        if (remaining <= 0) {
            countdownEl.textContent = '00:00:00';
            countdownEl.style.color = 'var(--color-red)';
            clearInterval(interval);
            return;
        }
        remaining--;
    }

    const interval = setInterval(updateCountdown, 1000);
    updateCountdown();

    // File upload preview
    const fileInput = document.getElementById('bukti_bayar');
    const dropzone = document.getElementById('dropzone');
    const previewContainer = document.getElementById('previewContainer');
    const previewImage = document.getElementById('previewImage');
    const previewFileName = document.getElementById('previewFileName');
    const uploadBtn = document.getElementById('uploadBtn');
    const uploadBtnText = document.getElementById('uploadBtnText');
    const uploadForm = document.getElementById('uploadForm');

    fileInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            if (file.size > 2 * 1024 * 1024) {
                alert('Ukuran file maksimal 2MB');
                this.value = '';
                return;
            }

            const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                alert('Format file harus JPG, PNG, atau WEBP');
                this.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('previewImage').src = e.target.result;
                document.getElementById('previewFileName').textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
                document.getElementById('previewContainer').style.display = 'block';
                uploadBtn.disabled = false;
                uploadBtnText.textContent = 'Upload Bukti Bayar';
            };
            reader.readAsDataURL(file);

            dropzone.style.borderColor = 'var(--color-emerald)';
            dropzone.style.background = 'var(--color-emerald-lighter)';
        } else {
            document.getElementById('previewContainer').style.display = 'none';
            uploadBtn.disabled = true;
        }
    });

    // Drag and drop
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => {
            dropzone.style.borderColor = 'var(--color-purple-electric)';
            dropzone.style.background = 'rgba(109, 40, 217, 0.05)';
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => {
            dropzone.style.borderColor = 'var(--color-slate-300)';
            dropzone.style.background = 'transparent';
        }, false);
    });

    dropzone.addEventListener('drop', function(e) {
        const dt = e.dataTransfer;
        const file = dt.files[0];
        if (file) {
            fileInput.files = dt.files;
            fileInput.dispatchEvent(new Event('change'));
        }
    }, false);

    dropzone.addEventListener('click', () => fileInput.click());

    uploadForm.addEventListener('submit', function(e) {
        if (!fileInput.files.length) {
            e.preventDefault();
            alert('Silakan pilih file bukti transfer terlebih dahulu.');
        } else {
            uploadBtn.disabled = true;
            uploadBtnText.textContent = 'Mengupload...';
        }
    });
});
</script>