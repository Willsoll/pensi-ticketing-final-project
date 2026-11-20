# PAKET 7 — Pensi Ticketing Platform

> Secure Multi-Event Ticketing Platform for Pensi School — Native PHP + MariaDB

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat-square&logo=php)](https://php.net)
[![MariaDB](https://img.shields.io/badge/MariaDB-10.6%2B-003545?style=flat-square&logo=mariadb)](https://mariadb.org)
[![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)](LICENSE)

---

## 📋 Overview

**PAKET 7** is a secure, production-ready Native PHP + MariaDB event ticketing platform built for the **Pensi School** practical examination. It implements all 11 required functional areas and **4 Mandatory CEO Business Rules** with enterprise-grade security.

### ✨ Key Features

- **11 Functional Areas**: Public catalog, event details, authentication, identity binding, checkout, customer dashboard, admin dashboard, tier/seat management, voucher management, finance verification, gate check-in
- **4 Mandatory CEO Business Rules** fully implemented
- **4 User Roles**: `customer`, `admin_eo`, `finance`, `gate_officer`
- **Enterprise Security**: RBAC, CSRF, XSS prevention, secure file uploads, audit logging
- **Responsive UI**: Modern design with dark indigo theme, mobile-first
- **Zero Dependencies**: Pure Native PHP 8.2+, PDO, no frameworks

---

## 🏗️ Architecture

```
pensi-ticketing/
├── public/                 # Document root (only this exposed)
│   ├── index.php           # Front controller
│   └── assets/             # CSS, JS, images
├── src/
│   ├── Controllers/        # Request handlers
│   ├── Services/           # Business logic (Pricing, Inventory, Voucher, BusinessRules)
│   ├── Repositories/       # Data access layer
│   ├── Middleware/         # Auth, CSRF, RBAC
│   ├── Security/           # Database, Session, CSRF, Password, OutputEncoder
│   └── Validation/         # Input validation
├── config/                 # Configuration files
├── database/
│   ├── schema.sql          # Complete DDL with FKs, indexes
│   └── seeds/              # Development seed data
├── storage/
│   ├── private/            # Private uploads (payment proofs)
│   ├── logs/               # Application logs
│   └── uploads/            # Public uploads (event banners)
├── tests/                  # Unit & integration tests
├── docs/                   # Documentation
├── views/                  # PHP templates
└── vendor/                 # Composer dependencies
```

---

## 🎯 Mandatory CEO Business Rules

### Rule 1: Stock Locking & 15-min Auto-Expiration
- Atomic reservation creation locks stock (kuota_tersedia → kuota_locked) and seats (Available → Locked)
- `batas_bayar_at` = server time + 15 minutes
- Cron job processes expirations every minute + defensive check in checkout
- Voucher quota restored via `voucher_redemptions` table (prevents double restoration)

### Rule 2: Maximum 5 Tickets per Account/NIK per Event
- Counts paid tickets + active reservations across ALL transactions
- Enforced server-side within transaction with row locking
- Rejects 6th ticket with clear error message

### Rule 3: Conditional Voucher Validation
- Validates all 5 conditions: min_transaksi, quota > 0, not expired, active, valid discount
- Voucher row locked with `FOR UPDATE` during reservation
- Redemption tracked in `voucher_redemptions` (prevents double restoration)

### Rule 4: One-to-Many E-Tickets & Anti-Passback
- E-Tickets issued ONLY after payment = `Paid`
- Each ticket = unique `kode_tiket` + QR code
- Gate check-in: validates serial, NIK, Paid status, not checked in
- Atomic check-in with `FOR UPDATE`
- Duplicate scan shows red warning with previous timestamp + officer name

---

## 🔐 Security Features

| Feature | Implementation |
|---------|----------------|
| Password Hashing | `password_hash()` (BCRYPT, cost 12) |
| Session Security | HttpOnly, Secure, SameSite=Strict, regeneration |
| CSRF Protection | Per-form tokens, one-time use, AJAX header support |
| RBAC | Deny-by-default middleware, object-level ownership checks |
| SQL Injection Prevention | PDO prepared statements, `EMULATE_PREPARES=false` |
| XSS Prevention | Context-aware output encoding (HTML, attr, JS, URL) |
| File Upload Security | MIME validation, size limits, random names, private storage |
| Audit Logging | All sensitive operations logged with user, IP, user-agent |

---

## 🚀 Quick Start

### Prerequisites
- PHP 8.2+ with extensions: `pdo_mysql`, `mbstring`, `json`, `fileinfo`, `gd`
- MariaDB 10.6+ / MySQL 8.0+
- Composer 2.x
- Apache/Nginx with mod_rewrite

### Installation

```bash
# Clone repository
git clone <repository-url> pensi-ticketing
cd pensi-ticketing

# Install dependencies
composer install --no-dev --optimize-autoloader

# Configure environment
cp .env.example .env
# Edit .env with your database credentials

# Create database & import schema
mysql -u root -p < database/schema.sql
mysql -u root -p db_pensi_ticketing < database/seeds/development.sql

# Configure Apache virtual host (DocumentRoot: public/)
# Example Apache config:
# <VirtualHost *:80>
#     DocumentRoot /path/to/pensi-ticketing/public
#     <Directory /path/to/pensi-ticketing/public>
#         AllowOverride All
#         Require all granted
#     </Directory>
# </VirtualHost>

# Set up cron for auto-expiry (every minute)
* * * * * cd /path/to/pensi-ticketing && php public/cron/expiry.php >> storage/logs/cron.log 2>&1

# Set permissions
chmod -R 755 storage/
chmod -R 755 public/assets/
```

### Test Accounts (after seeding)

| Role | Email | Password |
|------|-------|----------|
| Admin EO | admin@pensi.school | password123 |
| Finance | finance@pensi.school | password123 |
| Gate Officer | gate@pensi.school | password123 |
| Customer (Internal) | dewi@student.school | password123 |
| Customer (External) | rizki@alumni.id | password123 |

---

## 📚 Documentation

| Document | Description |
|----------|-------------|
| [Architecture](docs/ARCHITECTURE.md) | System design, data flow, security model |
| [Database Schema](database/schema.sql) | Complete DDL with comments |
| [ERD](docs/ERD.md) | Entity-relationship diagram (Mermaid) |
| [Business Rules](docs/BUSINESS_RULES.md) | Detailed implementation of 4 CEO rules |
| [Role-Permission Matrix](docs/ROLE_PERMISSIONS.md) | Role capabilities matrix |
| [Traceability Matrix](docs/TRACEABILITY.md) | Requirements → implementation mapping |
| [Security Notes](docs/SECURITY.md) | Security design & hardening notes |
| [Deployment](docs/DEPLOYMENT.md) | Production deployment guide |

---

## 🧪 Testing

```bash
# Run all tests
composer test

# Static analysis
composer stan

# Code style check
composer cs-check

# Fix code style
composer cs-fix
```

### Test Coverage
- Unit tests for Services (Pricing, Inventory, Voucher, BusinessRules)
- Integration tests for critical flows (booking, payment, gate check-in)
- Security tests (auth bypass, IDOR, CSRF, XSS)
- Business rule edge cases (concurrency, expiry boundaries)

---

## 📦 Deployment

### Production Checklist
- [ ] Set `APP_ENV=production`, `APP_DEBUG=false`
- [ ] Generate strong `APP_KEY` and `JWT_SECRET`
- [ ] Use HTTPS with valid SSL certificate
- [ ] Configure secure database user (not root)
- [ ] Enable HSTS, CSP headers
- [ ] Set up log rotation for `storage/logs/`
- [ ] Configure cron for expiry processing
- [ ] Set up database backups
- [ ] Configure monitoring/alerting

### Docker (Optional)
```dockerfile
FROM php:8.2-apache
RUN docker-php-ext-install pdo_mysql mbstring gd
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html/storage
RUN a2enmod rewrite
```

---

## 📄 License

MIT License — see [LICENSE](LICENSE) for details.

---

## 👨‍💻 Author

Built for **PAKET 7 — Pensi School Practical Examination**  
Native PHP + MariaDB | Secure by Design

---

> **Note**: This is an examination project demonstrating enterprise-grade Native PHP development. For production use, additional hardening and compliance review recommended.