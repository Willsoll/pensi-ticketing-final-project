# PENSI EVENTPRO v4 — Deployment Package
**Built:** 19 Nov 2026 · **Target:** XAMPP (Apache + MariaDB + PHP 8.2+)

---

## Contents
```
pensi_v4_multievent_deploy.zip
├── public/              ← DocumentRoot (web-facing files)
│   ├── index.php        ← Multi-event catalog + filters
│   ├── login.php        ← Login / Register (4 roles)
│   ├── detail_event.php ← Event detail + rundown + seat picker
│   ├── pemesanan.php    ← Identity binding + promo
│   ├── checkout.php     ← 15-min countdown + upload bukti
│   ├── dashboard_peserta.php
│   ├── etiket.php       ← E-ticket list + downloads
│   ├── etiket_png.php   ← JPG generator (GD + TTF)
│   ├── etiket_pdf.php   ← PDF generator (manual A4)
│   ├── verifikasi_pembayaran.php
│   ├── gate_checkin.php
│   ├── dashboard_eo.php
│   ├── crud_event.php
│   ├── kelola_voucher.php
│   ├── audit_log.php
│   ├── assets/
│   │   ├── css/design-system.css
│   │   ├── css/themes/theme-01..10 + _dark-fixes.css + THEME_CONTRACT.md
│   │   └── js/ui.js
│   └── .htaccess
├── src/
│   ├── Config/database.php        ← PDO, constants, HMAC secret
│   ├── Middleware/middleware.php  ← Auth, RBAC, CSRF, expiry, helpers
│   └── Views/layouts/*.php        ← Header (sidebar) + Footer
├── database/
│   ├── schema.sql    ← EXACT `db_tiket_event_pro` (9 graded tables + support)
│   └── seed.php      ← Seeds 4 events, 12 tiers, 230 seats, 4 vouchers, 12 users
├── tests/functional_test.php    ← 32 checks (schema, auth, booking, gate, e-ticket)
├── CONTRACT.md                  ← Dev contract (schema, helpers, rules, design)
└── V4_BUILD_REPORT.md           ← Full verification report
```

---

## Quick Deploy on Target Machine

### 1. Prerequisites
- XAMPP 8.2+ (Apache 2.4, MariaDB 10.4+, PHP 8.2+)
- `extension=gd` and `extension=fileinfo` enabled in `php.ini`
- `php_fileinfo.dll` and `php_gd.dll` present in `ext/`

### 2. Extract & Place
```bash
# Extract zip to XAMPP htdocs
# Example: C:\xampp\htdocs\
# Structure must be:
# C:\xampp\htdocs\public\*.php
# C:\xampp\htdocs\src\...
```

### 3. Apache DocumentRoot
Edit `C:\xampp\apache\conf\httpd.conf`:
```apache
DocumentRoot "C:/xampp/htdocs/public"
<Directory "C:/xampp/htdocs/public">
    AllowOverride All
    Require all granted
</Directory>
```
Restart Apache.

### 4. Database
```bash
# Start MySQL
cd C:\xampp\mysql\bin
mysqld.exe --defaults-file=..\bin\my.ini --console

# Import schema + seed
mysql -u root < C:\xampp\htdocs\database\schema.sql
php C:\xampp\htdocs\database\seed.php
```
Default users created (password **`password123`**):
- `admin.eo@eventpro.id` (admin_eo)
- `finance@eventpro.id` (finance)
- `gate@eventpro.id` (gate_officer)
- `dewi@student.id` / `rizki@alumni.id` / `maya@guest.id` (customer)

### 5. Verify
| URL | Expected |
|-----|----------|
| `http://127.0.0.1/` | Catalog (200) |
| `http://127.0.0.1/login.php` | Login (200) |
| `http://127.0.0.1/detail_event.php?id_event=1` | Detail + seats (200) |

Run tests:
```bash
php C:\xampp\htdocs\tests\functional_test.php
# Should output: 32 PASS, 0 FAIL
```

---

## Rubric Coverage (PAKET 7 Updated)

| Component | Weight | Status |
|-----------|--------|--------|
| **Perancangan Basis Data** | 20% | ✅ Exact `db_tiket_event_pro` with all 9 tables, FKs, ENUMs, UNIQUEs |
| **Antarmuka (UI/UX)** | 30% | ✅ 10 themes, ROMBEL palette, indigo sidebar, seat grid, responsive |
| **Fungsionalitas & Hak Akses** | 20% | ✅ 4-role auth, RBAC, upload, e-ticket, gate, 32/32 tests pass |
| **Logika Bisnis (Mandat CEO)** | 30% | ✅ Stock lock 15 min, 5/event cap, distinct NIK, conditional voucher, 1-to-many e-ticket, anti-passback, service fee |

---

## Theme Switching
- Default: `07` (Aurora Glass, light)
- Query param: `?theme=01`…`10` (persists via cookie)
- Floating switcher bottom-right on every page
- Dark themes: 01–05; Light themes: 06–10

---

## Known Requirements for Target Machine
1. Windows fonts: `C:/Windows/Fonts/arial.ttf` used by `etiket_png.php` (falls back to `imagestring` if missing)
2. `session.upload_progress.enabled = Off` in `php.ini` (PHP2Shell mitigation)
3. Timezone `Asia/Jakarta` (UTC+7) set in `database.php`
4. Write permissions on `public/uploads/bukti/` and `storage/`

---

## Support
See `V4_BUILD_REPORT.md` for full test log and `CONTRACT.md` for schema/helper reference.