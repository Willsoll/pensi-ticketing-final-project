<?php
$footerUser = function_exists('current_user') ? current_user() : null;
?>
<?php if ($footerUser): ?>
        </main>
    </div><!-- /.app-main -->
</div><!-- /.app-shell -->
<?php else: ?>
</main>
<?php endif; ?>

<footer class="site-footer" style="border-top:1px solid var(--color-border);margin-top:32px;padding:18px 24px;color:var(--color-text-muted);font-size:12px;display:flex;flex-wrap:wrap;gap:8px;justify-content:space-between;<?= $footerUser ? 'margin-left:248px;' : '' ?>">
    <span>&copy; <?= date('Y') ?> PENSI EVENTPRO — Aplikasi Pemesanan Tiket Event (PAKET 7)</span>
    <span>Palet wajib: #1E1B4B · #6D28D9 · #F97316 · #F8FAFC · #059669</span>
</footer>
<script src="/assets/js/ui.js" defer></script>
</body>
</html>
