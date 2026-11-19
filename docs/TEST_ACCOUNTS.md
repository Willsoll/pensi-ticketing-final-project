# PENSI EVENTPRO — Test Accounts & Credentials
**Build:** v4.1 (db_tiket_event_pro) · **Password for every account:** `password123`

> Keep this file private. The login page no longer displays demo credentials.

## Staff Accounts

| Role | Email | NIK | Password | Landing page |
|---|---|---|---|---|
| Event Organizer (admin) | `admin.eo@eventpro.id` | `3174010101010001` | `password123` | `dashboard_eo.php` |
| Finance / Verifikator | `finance@eventpro.id` | `3174010101010002` | `password123` | `verifikasi_pembayaran.php` |
| Gate Officer | `gate@eventpro.id` | `3174010101010003` | `password123` | `gate_checkin.php` |

## Customer Accounts

| Name | Email | NIK | Password |
|---|---|---|---|
| Dewi Lestari | `dewi@student.id` | `3174010101010011` | `password123` |
| Rizki Pratama | `rizki@alumni.id` | `3174010101010012` | `password123` |
| Maya Anggraini | `maya@guest.id` | `3174010101010013` | `password123` |
| Bagas Saputra | `bagas@student.id` | `3174010101010014` | `password123` |
| Citra Maharani | `citra@student.id` | `3174010101010015` | `password123` |
| Dimas Nugroho | `dimas@alumni.id` | `3174010101010016` | `password123` |
| Eka Putri | `eka@guest.id` | `3174010101010017` | `password123` |
| Fajar Ramadhan | `fajar@student.id` | `3174010101010018` | `password123` |
| Gita Permata | `gita@student.id` | `3174010101010019` | `password123` |

Login accepts **email or NIK** as the identifier.

## Suggested manual test flow

1. **Customer:** login `dewi@student.id` → Katalog → open an event → pick a tier/seat → fill
   participant names + NIK → apply voucher `HEMAT10` (min Rp 100.000) → checkout →
   upload a JPG/PNG proof → note the 15-minute countdown.
2. **Finance:** login `finance@eventpro.id` → open the waiting transaction → approve → e-tickets
   are issued.
3. **Customer:** Dashboard → E-Ticket → download JPG/PDF, verify unique code + barcode.
4. **Gate:** login `gate@eventpro.id` → enter ticket code → enter participant NIK → check-in
   succeeds; scan the same ticket again → red **TIKET SUDAH DIGUNAKAN** with previous time.
5. **Organizer:** login `admin.eo@eventpro.id` → Dashboard EO analytics → CRUD event/tier/seat
   generator → vouchers → audit log.

## Vouchers (seed)

| Code | Type | Value | Min. transaction | Quota left | Expires |
|---|---|---|---|---|---|
| `HEMAT10` | Percent | 10% | Rp 100.000 | 98 | 31 Dec 2026 |
| `POTONG25K` | Nominal | Rp 25.000 | Rp 150.000 | 49 | 15 Dec 2026 |
| `EARLYBIRD` | Percent | 15% | Rp 75.000 | 29 | 30 Nov 2026 |
| `MAHASISWA20` | Percent | 20% | Rp 200.000 | 20 | 10 Dec 2026 |

## Reset credentials

Re-run the seed to restore all accounts and demo data:

```bash
mysql -u root < C:/xampp/htdocs/database/schema.sql
C:/xampp/php/php.exe C:/xampp/htdocs/database/seed.php
```


## Payment methods (v6)

- **Transfer Bank** — BCA **7615553221**, a.n. **Panitia PENSI**. Upload proof (JPG/PNG/WEBP ≤ 2 MB); finance verifies.
- **Tunai (Cash)** — pay at the secretariat, show the booking code; finance marks it Paid manually.

E-Ticket is issued only after finance sets the transaction to **Paid**; it can then be downloaded as JPG or PDF from the Dashboard or the checkout success panel.
