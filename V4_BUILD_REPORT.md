# PENSI EVENTPRO v4 — Build & Verification Report
**Date:** 19 Nov 2026 · **Live:** http://127.0.0.1/ · **DB:** `db_tiket_event_pro` · **Git:** tag `v4.1-tested`

---

## 1. What v4 is

The application was rebuilt against the **updated `Paket 7.txt` criteria** while keeping the visual
system from v3.2 (10 themes, ROMBEL palette). v3.2 remains recoverable at
`C:\Users\Wilson\htdocs_v3_backup_public` and in every zip under
`Downloads\PENSI_ARCHIVE_2026-11-19\01_snapshots_zips\`.

| Area | v3.2 | v4 |
|---|---|---|
| Database | `db_pensi_sekolah`, single event | **`db_tiket_event_pro`**, multi-event, exact spec names |
| Roles | pembeli / admin_osis / panitia_keuangan / petugas_gate | **customer / admin_eo / finance / gate_officer** |
| Status | Menunggu Bayar / … / Lunas | **Pending / Waiting Verification / Paid / Expired / Rejected** |
| Catalog | one event, tiers on home | 4 events, filters kategori/kota/tanggal/kuota + real-time search |
| Checkout | total only | **subtotal, diskon, biaya_layanan (Rp 2.500/tiket), total**, 15-min countdown |
| Voucher | nominal only | **Persen / Nominal, min_transaksi, kuota_pakai, expired_date** |
| Tickets | detail table | **e_tickets** (nama_peserta, nik_peserta, status_checkin) + `transaksi_peserta` |
| Dashboard | basic stats | **EO analytics**: gross, discounts, net, service fee, occupancy per tier, 14-day chart |
| Layout | top header | **indigo sidebar** + top bar (spec: header/sidebar #1E1B4B) |

---

## 2. Database (exact graded schema)

All 9 required tables exist with the specified columns:

`users` (nik UNIQUE, email UNIQUE, role ENUM 4 nilai) · `kategori_event` · `events`
(id_kat_event, tipe_venue, tgl_mulai/selesai, banner, status_event, + deskripsi) ·
`tiket_tier` (id_event, harga, kuota_total/tersedia/locked, **batas_jual**) ·
`kursi_event` (id_tier, nomor_kursi, status_kursi) · `voucher_promo` (tipe_potongan,
nilai_potongan, **min_transaksi**, kuota_pakai, expired_date) · `transaksi` (kode_booking,
id_event, id_voucher, **subtotal, diskon, biaya_layanan, total_bayar**, bukti_bayar,
batas_bayar_at, status EN, catatan_penolakan) · `e_tickets` (id_tier, id_kursi, kode_tiket,
nama_peserta, nik_peserta, status_checkin, waktu_checkin, id_petugas_gate) · `audit_logs`
(aksi, entitas, keterangan, waktu).

Support tables (not graded, required by the flows): `transaksi_detail` (qty/tier/seats at
booking), `transaksi_peserta` (identity binding per sheet), `event_rundown`, `login_attempts`.

**Seed:** 4 categories, 4 events (Jakarta/Tangerang/Bandung/Serpong; 2 Seated + 2 Free
Standing), 12 tiers (Early Bird/Reguler/VIP), **230 seats**, 4 vouchers (2 percent + 2
nominal), 12 users (3 staff + 9 customers), 13 transactions, 13 e-tickets, 16 rundown
sessions. All password `password123`.

---

## 3. Pages (12 spec pages + support)

| Spec page | File | Access |
|---|---|---|
| Katalog multi-event + filter | `index.php` | public |
| Detail event + rundown + tier + seat picker | `detail_event.php` | public |
| Login & registrasi multi-role | `login.php` | public |
| Identity binding + promo | `pemesanan.php` | customer |
| Checkout + countdown + upload | `checkout.php` | customer |
| Dashboard peserta + e-ticket | `dashboard_peserta.php`, `etiket.php`, `etiket_png.php`, `etiket_pdf.php` | customer/owner |
| Dashboard eksekutif EO | `dashboard_eo.php` | admin_eo |
| CRUD event, tier, seat generator | `crud_event.php` | admin_eo |
| Manajemen voucher | `kelola_voucher.php` | admin_eo |
| Verifikasi pembayaran | `verifikasi_pembayaran.php` | finance |
| Gate check-in + audit log | `gate_checkin.php`, `audit_log.php` | gate_officer/admin_eo |
| Support | `detail_pesanan.php`, `bukti.php`, `logout.php`, `error.php`, `403` | mixed |

---

## 4. Business rules (Mandat CEO) — implementation

1. **Stock locking 15 min** — booking moves `kuota_tersedia → kuota_locked`, seats → `Locked`,
   `batas_bayar_at = NOW()+15 min`. `release_expired_transactions()` runs on every request and
   reverses both, decrements voucher usage, writes audit.
2. **Max 5 tickets per event per NIK** — `check_user_ticket_limit()` counts Paid e-tickets +
   active `transaksi_detail` for that user+event; enforced on GET and re-checked in the booking
   transaction.
3. **Distinct name + NIK per sheet** — server-side validation in `checkout.php` (case-insensitive
   name uniqueness, NIK uniqueness, 16-digit format).
4. **Conditional voucher** — `validate_voucher()`: subtotal ≥ min_transaksi, kuota_pakai > 0,
   not expired; percent or nominal; usage incremented on booking, restored on expiry/reject.
5. **E-ticket 1-to-many, issued after Paid** — finance approval inserts one `e_tickets` row per
   `transaksi_peserta`, copies tier/seat/NIK, unique `kode_tiket`.
6. **Gate anti-passback** — validates `kode_tiket` + `nik_peserta`; repeat scan returns red
   **TIKET SUDAH DIGUNAKAN** with previous check-in time and officer name.
7. **Tier sale deadline** — `batas_jual` checked on detail, booking GET, and inside the booking
   transaction.
8. **Service fee** — Rp 2.500 per ticket, shown as its own line; `total = subtotal − diskon + fee`.

---

## 5. Verification (real runs)

`C:/xampp/php/php.exe tests/functional_test.php` → **32 PASS, 0 FAIL**

Highlights:
- Schema: all required tables/columns, 4 roles exactly.
- Auth: seed login, 302 protection, customer blocked from EO dashboard (403).
- Voucher: 10% of 200k = 20k; rejected below minimum.
- Limit: used 2 → +3 allowed, +4 rejected.
- Booking E2E: POST → `checkout.php?kode=BK-…`, status Pending,
  total = 100.000 − 10.000 + 5.000 = **95.000**, kuota 296→294, locked 0→2.
- Expiry: status Expired, kuota restored to 296.
- E-ticket: JPG 68.870 bytes (FFD8), PDF 69.600 bytes (`%PDF-1.4…%%EOF`).
- Gate: first scan BERHASIL, rescan shows TIKET SUDAH DIGUNAKAN, DB `Sudah Hadir`.
- Audit rows present; 100 sequential requests, 0 non-200.

Manual E2E previously exercised: upload bukti → Waiting Verification → finance approval →
Paid + e-ticket → gate check-in + anti-passback; seated booking locks the exact `kursi_event`
rows; expiry frees them.

---

## 6. UI/UX

- 10 themes selectable via `?theme=01…10`, cookie-persisted, or the floating switcher.
- Palette exact: `#1E1B4B` primary/sidebar, `#6D28D9` accent, `#F97316` CTA, `#F8FAFC`
  background, `#059669` success.
- Indigo sidebar for authenticated users, public header for guests; responsive at 390 px
  (no horizontal overflow).
- Seat picker: grid layout, colour-coded (green available / amber locked / grey sold /
  orange selected), stage bar, legend, max-5 JS guard.
- Vision checks: catalog light 8/10, dark theme 9/10, mobile 8/10, detail 7/10 (before
  final seat-colour pass), EO dashboard 7/10.
- Screenshots: `artifacts/` and `artifacts/theme-catalog/catalog-01…10.png`.

---

## 7. Runbook

```bash
# services
"C:\xampp\apache\bin\httpd.exe"
cd /c/xampp/mysql/bin && ./mysqld.exe --defaults-file=../bin/my.ini --console

# database (fresh)
mysql -u root < C:/xampp/htdocs/database/schema.sql
C:/xampp/php/php.exe C:/xampp/htdocs/database/seed.php

# tests
C:/xampp/php/php.exe C:/xampp/htdocs/tests/functional_test.php
```

Demo accounts: `admin.eo@eventpro.id`, `finance@eventpro.id`, `gate@eventpro.id`,
`dewi@student.id` / `rizki@alumni.id` / `maya@guest.id` — password `password123`.

---

## 8. Known limitations

- PHP 8.2.12 (< 8.2.35): PHP2Shell remains mitigated at app layer (`session.upload_progress`
  off + version logging); full fix needs the XAMPP 8.5.12 upgrade.
- Gate test 2 uses the GET preview state to show anti-passback (no POST form is rendered for an
  already-used ticket); the red warning includes time and officer.
- Event `deskripsi` and `banner` are support columns beyond the spec; `banner` stays NULL and
  the UI draws a category gradient instead.
- Icon-only theme toggle relies on `ui.js`; with JS disabled the default theme still renders.
