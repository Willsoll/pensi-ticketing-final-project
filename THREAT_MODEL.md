# PAKET 7 — Threat Model

**Application:** Multi-Event Ticketing Platform (PAKET 7)
**Methodology:** STRIDE + Attack Trees
**Date:** 2025-11-14

---

## System Context Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                            TRUST BOUNDARIES                                  │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌──────────────┐     HTTPS      ┌──────────────────────────────────────┐  │
│  │   INTERNET   │ ─────────────▶ │         WEB SERVER (Apache)          │  │
│  │  (Untrusted) │                │  • TLS Termination                   │  │
│  └──────────────┘                │  • Static Assets                     │  │
│        │                         │  • PHP-FPM Proxy                     │  │
│        │                         └──────────────┬───────────────────────┘  │
│        │                                        │                           │
│        │                              ┌─────────▼─────────┐                │
│        │                              │  APPLICATION LAYER │                │
│        │                              │  (PHP Controllers) │                │
│        │                              │  • Auth Middleware │                │
│        │                              │  • CSRF Protection │                │
│        │                              │  • Rate Limiting   │                │
│        │                              │  • Input Validation│                │
│        │                              └─────────┬─────────┘                │
│        │                                        │                           │
│        │              ┌─────────────────────────┼────────────────────────┐  │
│        │              │                         │                        │  │
│        │       ┌──────▼──────┐          ┌───────▼───────┐        ┌───────▼────────┐  │
│        │       │   DATABASE  │          │  FILE STORAGE │        │  EXTERNAL      │  │
│        │       │  (MariaDB)  │          │  (Uploads)    │        │  SERVICES      │  │
│        │       │  • Users    │          │  • Payment    │        │  • None (self- │  │
│        │       │  • Events   │          │    proofs     │        │    contained)  │  │
│        │       │  • Tickets  │          │  • Outside    │        │                │  │
│        │       │  • Audit    │          │    webroot    │        └────────────────┘  │
│        │       └─────────────┘          └───────────────┘                        │
│        │                                                                         │
│        └────────────────────────────────────────────────────────────────────────┘
│
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Assets Requiring Protection

| Asset | Classification | Description |
|-------|----------------|-------------|
| User credentials (password hashes) | **Critical** | BCRYPT cost 12; must never be exposed |
| NIK/NIS (PII) | **Critical** | Indonesian ID numbers; masked in UI, encrypted at rest |
| Payment proof images | **High** | Financial evidence; contains bank details |
| E-Ticket codes (`kode_tiket`) | **High** | Unique, single-use; gate access control |
| Transaction records | **High** | Financial audit trail; integrity critical |
| Voucher codes & quotas | **Medium** | Promotional value; quota exhaustion = revenue loss |
| Seat assignments | **Medium** | Inventory integrity for seated events |
| Admin session tokens | **High** | Privileged access to all data |
| Audit logs | **High** | Non-repudiation; tamper-evidence required |

---

## User Roles & Trust Boundaries

| Role | Trust Level | Permissions | Data Access |
|------|-------------|-------------|-------------|
| **Anonymous** | None | Browse catalog, view event details, register | Public event data only |
| **Customer** | Low (authenticated) | Book tickets, upload payments, view own bookings/E-Tickets | Own data only (enforced by middleware) |
| **Finance** | Medium | Verify/reject payments, view all transactions | All transactions + payment proofs |
| **Gate Officer** | Medium | Check-in attendees, view audit log | E-Tickets (check-in only), audit logs |
| **Admin (EO)** | High | Full CRUD: events, tiers, seats, vouchers, analytics | All system data |

### Trust Boundary Crossings

| From → To | Data Flow | Validation |
|-----------|-----------|------------|
| Browser → Controller | HTTP POST (JSON/form) | CSRF token, input validation, rate limit |
| Controller → Service | PHP method calls | Type hints, business rule validation |
| Service → Database | PDO prepared statements | Parameter binding, transactions |
| Service → Filesystem | `move_uploaded_file()` | MIME + magic bytes, random names, outside webroot |
| Database → Controller | Result sets | Output encoding for display |

---

## STRIDE Analysis

### Spoofing Identity

| Threat | Target | Likelihood | Impact | Mitigation |
|--------|--------|------------|--------|------------|
| T1: Credential stuffing | `/login` | High | High | Rate limiting (5/15min/IP), BCRYPT, account lockout |
| T2: Session hijacking | Session cookie | Medium | High | HttpOnly, Secure, SameSite=Strict, regeneration on login |
| T3: Registration with stolen NIK | `/register` | Low | High | NIK uniqueness constraint, email verification (future) |
| T4: Admin impersonation | Admin endpoints | Low | Critical | Role checks on every request, audit logging |

### Tampering

| Threat | Target | Likelihood | Impact | Mitigation |
|--------|--------|------------|--------|------------|
| T5: Price manipulation | Checkout (client-side) | High | High | **Server-side only** pricing (`PricingService`); client countdown is visual only |
| T6: Voucher abuse | Voucher validation | Medium | High | Quota locked with `FOR UPDATE`; redemption recorded atomically |
| T7: Seat double-booking | Seat selection | Medium | High | `FOR UPDATE` on seat rows; status transitions: Available→Locked→Sold |
| T8: Payment proof swap | File upload | Low | High | Randomized names; served via auth script; no direct URL access |
| T9: Ticket code forgery | E-Ticket generation | Low | Critical | Cryptographically random codes (`bin2hex(random_bytes(20))`); DB unique constraint |
| T10: Audit log tampering | `audit_logs` table | Low | Critical | Append-only (no UPDATE/DELETE); DB user lacks DELETE privilege |

### Repudiation

| Threat | Target | Likelihood | Impact | Mitigation |
|--------|--------|------------|--------|------------|
| T11: Deny payment upload | `bukti_bayar` | Medium | High | Audit log: `PAYMENT_UPLOAD` with user, timestamp, IP, file hash |
| T12: Deny gate check-in | `e_tickets` check-in | Medium | High | Audit log: `GATE_CHECKIN` with officer, timestamp, ticket code |
| T13: Finance denies verification | Transaction status | Medium | High | Audit log: `VERIFY_PAYMENT`/`REJECT_PAYMENT` with officer, reason |

### Information Disclosure

| Threat | Target | Likelihood | Impact | Mitigation |
|--------|--------|------------|--------|------------|
| T14: SQL error leakage | Database errors | Medium | Medium | Generic error messages in production; detailed only in dev |
| T15: Directory listing | Uploads directory | Low | Medium | Uploads outside webroot; `.htaccess` denies all |
| T16: Debug log exposure | `router_debug.log` | Medium | Medium | **FIXED** — moved to `storage/logs/` |
| T17: NIK exposure in UI | Attendee lists | Medium | High | `OutputEncoder::mask()` shows only first/last 4 digits |
| T18: Stack traces | Unhandled exceptions | Low | Medium | Custom error handler; no stack traces in production |
| T19: Enum/user enumeration | Registration/Login | Medium | Low | Same error message for "user not found" / "wrong password" |

### Denial of Service

| Threat | Target | Likelihood | Impact | Mitigation |
|--------|--------|------------|--------|------------|
| T20: Reservation lock exhaustion | `reservations` table | Medium | High | 15-min TTL; cron cleanup; max 5 tickets/user |
| T21: Voucher quota exhaustion | `voucher_promo.kuota_pakai` | Low | Medium | Quota limits; admin monitoring |
| T22: File upload flood | Storage disk | Low | Medium | 2MB limit; file count monitoring; virus scan (future) |
| T23: Login brute force | `/login` | High | Medium | Rate limiting + exponential backoff |
| T24: Large query DoS | Admin analytics | Low | Medium | Pagination limits; query timeouts |

### Elevation of Privilege

| Threat | Target | Likelihood | Impact | Mitigation |
|--------|--------|------------|--------|------------|
| T25: Customer → Finance | Role escalation | Low | Critical | RBAC middleware checks role on every request; session role immutable |
| T26: IDOR on bookings | `/my-bookings`, `/checkout/{id}` | Medium | High | Ownership checks: `reservation.id_user == current_user.id_user` |
| T27: IDOR on E-Tickets | `/etiket/{kode_tiket}` | Medium | High | E-Ticket view requires `status_bayar='Paid'` + ownership |
| T28: Admin function access | `/admin/*` by non-admin | Low | Critical | `AuthMiddleware::requireRole('admin_eo')` at controller entry |

---

## Attack Trees

### Attack Tree: Steal Valid E-Ticket (Gate Bypass)

```
GOAL: Gain unauthorized entry to event
│
├─ A1: Forge valid kode_tiket
│   ├─ A1.1: Guess 40-char hex code → INFEASIBLE (2^160 space)
│   ├─ A1.2: Predict random_bytes() → INFEASIBLE (CSPRNG)
│   └─ A1.3: SQL injection to read e_tickets → MITIGATED (prepared statements)
│
├─ A2: Steal existing kode_tiket
│   ├─ A2.1: XSS to steal from victim's browser → MITIGATED (CSP, output encoding)
│   ├─ A2.2: MITM on HTTP → MITIGATED (HTTPS + HSTS in prod)
│   ├─ A2.3: Compromise admin → MITIGATED (RBAC, audit, strong auth)
│   └─ A2.4: IDOR on /my-bookings → MITIGATED (ownership checks)
│
├─ A3: Bypass gate check-in logic
│   ├─ A3.1: Reuse checked-in ticket → BLOCKED (status_checkin='Sudah Hadir' rejected)
│   ├─ A3.2: Race condition concurrent check-in → BLOCKED (FOR UPDATE row lock)
│   ├─ A3.3: Fake NIK confirmation → BLOCKED (server validates NIK matches e_ticket)
│   └─ A3.4: Direct DB update → BLOCKED (DB user no direct access)
│
└─ A4: Social engineering gate officer
    └─ A4.1: Officer manually overrides → DETECTED (audit log shows officer ID)
```

### Attack Tree: Financial Fraud (Free Tickets)

```
GOAL: Obtain Paid tickets without payment
│
├─ B1: Bypass payment verification
│   ├─ B1.1: Direct call to Finance verify endpoint → BLOCKED (requireRole('finance'))
│   ├─ B1.2: SQL injection to update status_bayar → MITIGATED (prepared statements)
│   └─ B1.3: Session fixation to hijack finance session → MITIGATED (session regeneration)
│
├─ B2: Exploit reservation expiry
│   ├─ B2.1: Let reservation expire, then claim paid → BLOCKED (status='Expired' not convertible)
│   ├─ B2.2: Race: upload proof AFTER expiry but BEFORE cron → BLOCKED (defensive check in controller)
│   └─ B2.3: Manipulate batas_bayar_at → BLOCKED (server-set, immutable after creation)
│
├─ B3: Voucher abuse for 100% discount
│   ├─ B3.1: Create voucher with 100% Persen → BLOCKED (admin only; validation caps at subtotal)
│   ├─ B3.2: Reuse voucher quota → BLOCKED (quota decrement FOR UPDATE; redemption table)
│   └─ B3.3: Client-side discount calc → IGNORED (server recalculates in PricingService)
│
└─ B4: Insider threat (finance officer)
    └─ B4.1: Approve fake payment → DETECTED (audit log: officer ID, timestamp, IP)
```

### Attack Tree: Data Breach (PII Exfiltration)

```
GOAL: Extract NIK/NIS database
│
├─ C1: SQL Injection
│   ├─ C1.1: Search/filter parameters → MITIGATED (all queries parameterized)
│   ├─ C1.2: ORDER BY injection → MITIGATED (validated against allowlist)
│   └─ C1.3: LIMIT/OFFSET injection → MITIGATED (int cast)
│
├─ C2: Broken Access Control
│   ├─ C2.1: Admin panel access → BLOCKED (RBAC middleware)
│   ├─ C2.2: IDOR on user listings → BLOCKED (no user listing endpoint for customers)
│   └─ C2.3: Audit log access → BLOCKED (gate_officer+ only; masked NIKs)
│
├─ C3: File Inclusion/Path Traversal
│   ├─ C3.1: Template path manipulation → MITIGATED (fixed template paths, no user input)
│   └─ C3.2: Uploaded file execution → MITIGATED (outside webroot, served via PHP)
│
└─ C4: Backup/Log Exposure
    ├─ C4.1: Git history → MITIGATED (.gitignore excludes .env, storage/, vendor/)
    ├─ C4.2: Debug logs → FIXED (moved outside webroot)
    └─ C4.3: Database dumps → PROCEDURAL (encrypted, access-controlled)
```

---

## Data Flow Diagrams

### DFD Level 1: Booking & Payment Flow

```
Customer Browser
     │
     ▼
[1] GET /event/{id} ──▶ CatalogController ──▶ EventRepository ──▶ DB (events, tiers, seats)
     │
     ▼
[2] POST /event/select-tier ──▶ EventController ──▶ (session: selected tier + qty)
     │
     ▼
[3] GET /booking/identity ──▶ BookingController ──▶ (renders identity form)
     │
     ▼
[4] POST /booking/identity ──▶ BookingController
     │    ├─ Validate NIK/NIS per ticket (unique, format)
     │    ├─ Validate voucher (VoucherService::validate with FOR UPDATE)
     │    ├─ Calculate price (PricingService - SERVER ONLY)
     │    ├─ START TRANSACTION
     │    │   ├─ InventoryService::lockStock(tier, qty)
     │    │   ├─ InventoryService::lockSeats(seatIds)
     │    │   ├─ VoucherService::reserveQuota(voucherId)
     │    │   ├─ Create reservations record (batas_bayar_at = NOW()+15min)
     │    │   ├─ Create reservation_items (nama, nik, seat)
     │    │   └─ COMMIT
     │    └─ Redirect to /checkout/{reservationId}
     │
     ▼
[5] GET /checkout/{id} ──▶ BookingController ──▶ (shows countdown, bank details, upload)
     │
     ▼
[6] POST /checkout/{id}/upload ──▶ BookingController
     │    ├─ Verify reservation owns current user
     │    ├─ Validate file (MIME + magic bytes + size)
     │    ├─ Move to storage/uploads/bukti/{random}.{ext}
     │    ├─ Update reservations: bukti_bayar, status='Waiting Verification'
     │    ├─ AuditLogger::log(PAYMENT_UPLOAD)
     │    └─ Redirect to /my-bookings
     │
     ▼
Finance Officer Browser
     │
     ▼
[7] GET /finance ──▶ FinanceController ──▶ TransactionRepository (status='Waiting Verification')
     │
     ▼
[8] POST /finance/verify/{id} (approve) ──▶ FinanceController
     │    ├─ START TRANSACTION
     │    │   ├─ Update transaksi: status_bayar='Paid'
     │    │   ├─ InventoryService::confirmStock(tier, qty)
     │    │   ├─ InventoryService::confirmSeats(seatIds)
     │    │   ├─ BusinessRulesService::issueETickets(transaksiId)
     │    │   │   ├─ For each reservation_item: generate kode_tiket
     │    │   │   └─ Insert e_tickets (status_checkin='Belum Hadir')
     │    │   ├─ VoucherService::recordRedemption(voucherId, transaksiId)
     │    │   ├─ AuditLogger::log(VERIFY_PAYMENT)
     │    │   └─ COMMIT
     │    └─ Flash success
     │
     ▼
Gate Officer Browser
     │
     ▼
[9] POST /gate/checkin ──▶ GateController
     │    ├─ BusinessRulesService::processGateCheckin(kode_tiket, nik, officerId)
     │    │   ├─ SELECT * FROM e_tickets WHERE kode_tiket = ? FOR UPDATE
     │    │   ├─ Validate: status_bayar='Paid' (via transaksi join)
     │    │   ├─ Validate: nik_peserta matches input
     │    │   ├─ Validate: status_checkin='Belum Hadir'
     │    │   ├─ UPDATE e_tickets SET status_checkin='Sudah Hadir', waktu_checkin=NOW(), id_petugas_gate=?
     │    │   ├─ AuditLogger::log(GATE_CHECKIN)
     │    │   └─ Return success + attendee name
     │    └─ Flash result (green/red)
```

---

## Abuse Cases by Actor

### Customer (Authenticated)
| Abuse Case | Description | Mitigation |
|------------|-------------|------------|
| AC-01 | Book >5 tickets via multiple orders | `BusinessRulesService::checkTicketLimit()` counts across all orders |
| AC-02 | Use another user's NIK | NIK uniqueness per ticket enforced in `reservation_items` + validation |
| AC-03 | Upload fake payment proof | Finance manual verification; audit trail |
| AC-04 | Access another's booking via IDOR | Ownership check: `reservation.id_user == current_user.id_user` |
| AC-05 | Manipulate client-side countdown | Server validates `batas_bayar_at` on every action |
| AC-06 | Reuse voucher code | Quota decremented with `FOR UPDATE`; redemption recorded |

### Finance Officer
| Abuse Case | Description | Mitigation |
|------------|-------------|------------|
| AC-07 | Approve payment without proof | UI requires proof preview; audit log captures officer |
| AC-08 | Reject valid payment maliciously | Rejection requires reason (≥5 chars); audit log; stock restored |
| AC-09 | Access customer PII unnecessarily | NIKs masked in UI (`OutputEncoder::mask()`); full NIK only in export (admin) |

### Gate Officer
| Abuse Case | Description | Mitigation |
|------------|-------------|------------|
| AC-10 | Check in friend without ticket | Requires valid `kode_tiket` + matching NIK |
| AC-11 | Allow re-entry (passback) | `status_checkin='Sudah Hadir'` blocks second check-in; red alert shown |
| AC-12 | Check in wrong event | E-Ticket linked to transaction → event; validated server-side |

### Admin (EO)
| Abuse Case | Description | Mitigation |
|------------|-------------|------------|
| AC-13 | Delete event with active bookings | FK `ON DELETE RESTRICT` prevents |
| AC-14 | Create voucher with 100% discount | Validation caps discount at subtotal; audit log |
| AC-15 | Generate seats for sold tier | `generateSeats()` checks existing seats; admin action logged |

### Anonymous Attacker
| Abuse Case | Description | Mitigation |
|------------|-------------|------------|
| AC-16 | Enumerate valid emails via login | Same error message for invalid email/wrong password |
| AC-17 | Brute force passwords | Rate limiting (5/15min/IP); BCRYPT cost 12 |
| AC-18 | SQL injection via search | All queries parameterized |
| AC-19 | XSS via event search | Output encoding; CSP |
| AC-20 | Upload shell via payment proof | Magic bytes check; outside webroot; served via auth script |

---

## Residual Risk Summary

| Risk ID | Description | Likelihood | Impact | Status |
|---------|-------------|------------|--------|--------|
| RR-01 | Insider threat (finance/gate/admin) | Low | High | **Accepted** — Audit logging + separation of duties |
| RR-02 | Zero-day in PHP/MariaDB | Low | Critical | **Mitigated** — Regular updates; minimal attack surface |
| RR-03 | Physical access to server | Very Low | Critical | **Out of scope** — Infrastructure responsibility |
| RR-04 | Social engineering of staff | Medium | High | **Mitigated** — Training; audit trail deterrence |
| RR-05 | Voucher code leakage (screenshot) | Medium | Low | **Accepted** — Quota limits loss; codes rotatable |

---

## Security Requirements Traceability

| Req ID | Requirement | Implementation | Verified |
|--------|-------------|----------------|----------|
| SR-01 | Passwords hashed with BCRYPT ≥12 | `PasswordManager::hash()` cost 12 | ✅ Unit test |
| SR-02 | All SQL parameterized | PDO `prepare()` + `execute()` everywhere | ✅ Code review |
| SR-03 | Output encoding context-aware | `OutputEncoder` class used in all views | ✅ Code review |
| SR-04 | CSRF on all state-changing POST | `CsrfMiddleware` + per-form tokens | ✅ Integration test |
| SR-05 | RBAC enforced server-side | `AuthMiddleware::requireRole()` in controllers | ✅ Integration test |
| SR-06 | File uploads validated + isolated | `InputValidator::fileUpload()` + `storage/uploads/` | ✅ Integration test |
| SR-07 | Business logic server-only | `PricingService`, `BusinessRulesService` | ✅ Unit test |
| SR-08 | Atomic transactions for money | PDO transactions + `FOR UPDATE` locks | ✅ Unit test |
| SR-09 | Audit trail for sensitive ops | `AuditLogger` called in all controllers | ✅ Code review |
| SR-10 | Session security hardened | HttpOnly, Secure, SameSite, regeneration | ✅ Config review |

---

*This threat model is a living document. Update when architecture changes, new features added, or after security incidents.*