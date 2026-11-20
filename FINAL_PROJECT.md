# Informatics Final Project — PENSI EVENTPRO (PAKET 7)

**Student:** Wilson Ryan Chong · **Class:** 11.3 · **School:** Sekolah Terpadu Pahoa
**Subject:** Informatika · **Package:** PAKET 7 — Aplikasi Pemesanan Tiket Event
(Multi-Event & Seat/Quota Ticketing Platform)

---

## 1. Project summary

A multi-event ticketing platform built with **native PHP 8.2 and MariaDB** (no framework,
no Composer dependencies). It covers the full flow: public catalog → event detail with
rundown and seat selection → identity binding → checkout with a 15-minute payment window →
finance verification → e-ticket issuance → gate check-in with anti-passback, plus an
organizer dashboard, event/tier CRUD, voucher management, and an audit log.

The UI uses the mandatory palette (`#1E1B4B`, `#6D28D9`, `#F97316`, `#F8FAFC`, `#059669`)
with an indigo sidebar and **10 switchable themes** (5 dark, 5 light).

## 2. Rubric mapping

| Komponen | Bobot | Implementasi | Bukti |
|---|---|---|---|
| **Perancangan Basis Data** | 20% | Database `db_tiket_event_pro` with the exact 9 required tables (`users`, `kategori_event`, `events`, `tiket_tier`, `kursi_event`, `voucher_promo`, `transaksi`, `e_tickets`, `audit_logs`) + 3 support tables. PK/FK, UNIQUE, ENUM, indexes. | `database/schema.sql`, `database/db_tiket_event_pro_full.sql` |
| **Antarmuka Pengguna (UI/UX)** | 30% | 10 themes, responsive sidebar layout, custom inputs/selects/upload dropzone, seat grid, accessible tables, print styles, e-ticket JPG/PDF. | `public/assets/css/`, `designs/`, `docs/screenshots/` |
| **Fungsionalitas Dasar & Hak Akses** | 20% | 4 roles (`customer`, `admin_eo`, `finance`, `gate_officer`), RBAC middleware, login/registrasi, CRUD, upload, verification, check-in, audit. | `src/Middleware/`, `public/*.php`, `tests/functional_test.php` |
| **Logika Bisnis (Mandat CEO)** | 30% | 15-minute stock lock + auto-expiry, max 5 tickets/event/NIK, distinct name+NIK per sheet, conditional vouchers, 1-to-many e-tickets after `Paid`, gate anti-passback. | `src/Middleware/middleware.php` (`release_expired_transactions`, `check_user_ticket_limit`, `validate_voucher`), `public/checkout.php`, `public/verifikasi_pembayaran.php`, `public/gate_checkin.php` |

## 3. Business rules (Mandat CEO)

1. **Stock locking & auto-expiration** — booking moves `kuota_tersedia → kuota_locked`,
   seats become `Locked`, `batas_bayar_at = NOW() + 15 minutes`. Expired orders are released
   automatically (stock, seats, voucher usage) and marked `Expired`.
2. **Per-NIK limit** — maximum **5 tickets per event per NIK**, accumulated across paid and
   active transactions.
3. **Identity binding** — every sheet needs a different participant name **and** NIK
   (16 digits), validated server-side.
4. **Conditional voucher** — percent or nominal, requires subtotal ≥ minimum, remaining
   quota, and an unexpired date.
5. **E-ticket 1-to-many** — issued only after finance sets the transaction to `Paid`; each
   ticket has a unique code, participant identity, tier, and seat (if seated).
6. **Anti-passback** — gate validates ticket code + NIK; a second scan is rejected with a red
   warning showing the previous check-in time and officer.

## 4. Security

- bcrypt cost-12 (`password_hash`), automatic rehash, session ID regeneration on login
- Login throttling (5 attempts / 15 min), generic error messages, timing-equalised dummy hash
- Prepared statements (`PDO::ATTR_EMULATE_PREPARES = false`), output escaping, CSRF on POST
- Upload MIME sniffing + 2 MB cap + random filenames; PHP execution blocked in `uploads/`
- Security headers, `SameSite=Strict` cookies, audit log for sensitive actions
- Application-layer mitigation for PHP2Shell (CVE-2024-4577); PHP ≥ 8.2.35 recommended

## 5. Testing

| Suite | Result |
|---|---|
| `tests/functional_test.php` | **32/32 PASS** (schema, auth, RBAC, voucher, limit, booking, expiry, e-ticket JPG/PDF, gate, audit, stress) |
| E2E manual suite | 54 checks recorded in `docs/E2E_RESULTS_2026-11-19.txt` |
| Stress | 150 sequential + 10 parallel requests, 0 non-200 / 0 × 500 |

## 6. Version archive

Every milestone is preserved as a Git tag, a legacy branch, or a release asset.

| Version | Git ref | ZIP (release asset) |
|---|---|---|
| Base flat-PHP template | `legacy/php-flat-template`, `v0-base-template` | `ticketing_web_server_1st_round.zip` |
| v1.0 hardened | `v1.0-hardened` | `ticketing-web-server-hardened.zip` |
| v2.0 restructure | `v2.0-working` | `pensi_v2_working_0919.zip` |
| v2.0 MVC experiment | `legacy/mvc-rewrite`, `v2.0-mvc-rewrite` | `pensi-ticketing-final.zip` |
| v3 ten designs | `v3-ten-designs` | `pensi_v3_10designs.zip` |
| v3.2 review fixes | `v3.2-review-fixes` | `pensi_v3.2_final.zip` |
| v4 multi-event | `v4-multievent` | `pensi_v4_multievent.zip` |
| v4.2 portable | `v4.2-portable` | `PENSI_EVENTPRO_v4.2_TeamTest.zip` |
| v6.1 final | `v6.1-portable` | `PENSI_EVENTPRO_v6_PORTABLE.zip` |

**Release:** https://github.com/Willsoll/pensi-ticketing-final-project/releases/tag/archive-all-versions

```bash
git clone https://github.com/Willsoll/pensi-ticketing-final-project.git
git checkout v6.1-portable
```

## 7. Running the project

```bash
# XAMPP: PHP 8.2+, extensions pdo_mysql, gd, fileinfo, mbstring
php install/check.php
php install/install.php --user=root --pass=
# DocumentRoot -> public/  (or open /<folder>/public/)
```

Demo accounts (password `password123`): `admin.eo@eventpro.id`,
`finance@eventpro.id`, `gate@eventpro.id`, `dewi@student.id`.

Full deployment guide: `INSTALL.md` / `docs/DEPLOY_README.md`.
Account list: `docs/TEST_ACCOUNTS.md`.

## 8. Repository map

```
public/      webroot: 19 pages, assets, 10 themes, fonts, uploads
src/         config, middleware, layouts
database/    schema.sql, seed.php, full dump
tests/       functional_test.php (32 checks)
docs/        accounts, deployment, user guide, E2E results, screenshots
designs/     10 theme folders with summary + screenshots
install/     environment check + one-command installer
storage/     runtime (secrets/logs, excluded from Git)
```

## 9. Limitations

- Built for XAMPP/PHP 8.2.12; upgrade PHP to ≥ 8.2.35 for the upstream PHP2Shell fix.
- Demo credentials must be replaced before any real deployment.
- Payment is simulated (transfer proof upload / cash confirmation), no payment gateway.
