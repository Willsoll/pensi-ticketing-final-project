# PAKET 7 — Security Configuration Guide

**Application:** Multi-Event Ticketing Platform (PAKET 7)
**Version:** 1.0
**Date:** 2025-11-14
**Audience:** DevOps, System Administrators, Security Engineers

---

## Overview

This document describes the security configuration for production deployment. All security-sensitive settings are externalized to environment variables (`.env`) to prevent credential leakage and enable environment-specific hardening.

---

## Environment Variables (`.env`)

```bash
# ============================================================
# APPLICATION
# ============================================================
APP_ENV=production                    # development | production
APP_DEBUG=false                       # true only in development
APP_URL=https://tiket.pensi.sch.id    # Production URL (HTTPS)
APP_TIMEZONE=Asia/Jakarta

# ============================================================
# DATABASE
# ============================================================
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=db_pensi_ticketing
DB_USER=ticketing_app                 # Dedicated app user (not root)
DB_PASS=CHANGE_ME_STRONG_PASSWORD     # 32+ char random string

# ============================================================
# SESSION & COOKIES
# ============================================================
SESSION_SECURE=true                   # HTTPS only (production MUST be true)
SESSION_SAMESITE=Strict               # Strict | Lax | None
SESSION_LIFETIME=14400                # 4 hours absolute (seconds)
SESSION_IDLE_TIMEOUT=1800             # 30 minutes idle (seconds)
SESSION_COOKIE_NAME=pensi_session     # Custom session name

# ============================================================
# SECURITY HEADERS
# ============================================================
HSTS_ENABLED=true                     # Enable HSTS (requires HTTPS)
HSTS_MAX_AGE=31536000                 # 1 year
HSTS_INCLUDE_SUBDOMAINS=true
HSTS_PRELOAD=true

CSP_NONCE_ENABLED=true                # Nonce-based CSP (required)
CSP_REPORT_ONLY=false                 # Set true for initial deployment testing

# ============================================================
# RATE LIMITING
# ============================================================
RATE_LIMIT_STORAGE=file               # file | redis
RATE_LIMIT_REDIS_HOST=127.0.0.1       # If redis
RATE_LIMIT_REDIS_PORT=6379
RATE_LIMIT_LOGIN_MAX=5                # Attempts per window
RATE_LIMIT_LOGIN_WINDOW=900           # 15 minutes (seconds)
RATE_LIMIT_REGISTER_MAX=3             # Registrations per window
RATE_LIMIT_REGISTER_WINDOW=3600       # 1 hour (seconds)

# ============================================================
# FILE UPLOADS
# ============================================================
UPLOAD_MAX_SIZE=2097152               # 2 MB (bytes)
UPLOAD_ALLOWED_MIMES=image/jpeg,image/png,image/webp
UPLOAD_PATH=/var/www/pensi-ticketing/storage/uploads/bukti
UPLOAD_SCAN_MIME=true
UPLOAD_STRIP_EXIF=true

# ============================================================
# LOGGING & AUDIT
# ============================================================
LOG_LEVEL=warning                     # debug | info | warning | error
LOG_PATH=/var/www/pensi-ticketing/storage/logs
AUDIT_LOG_RETENTION_DAYS=2555         # 7 years
AUDIT_LOG_PARTITION_MONTHLY=true

# ============================================================
# CRON / SCHEDULED TASKS
# ============================================================
CRON_EXPIRY_ENABLED=true
CRON_EXPIRY_SCHEDULE="* * * * *"      # Every minute
CRON_CLEANUP_ENABLED=true
CRON_CLEANUP_SCHEDULE="0 2 * * 0"     # Weekly Sunday 2 AM
CRON_CLEANUP_DAYS=30

# ============================================================
# EMAIL (Future)
# ============================================================
MAIL_ENABLED=false
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@pensi.sch.id
MAIL_FROM_NAME="Pensi Ticketing"
```

---

## Production Deployment Checklist

### Server Hardening (OS Level)

| Item | Configuration | Verification |
|------|---------------|--------------|
| OS Updates | Auto-security updates enabled | `apt list --upgradable` |
| Firewall | Only 80/443 (HTTP/HTTPS) + 22 (SSH key-only) | `ufw status` |
| SSH | Key-only auth, non-root user, port changed | `sshd_config` audit |
| Fail2Ban | Enabled for SSH, HTTP auth | `fail2ban-client status` |
| Kernel | Hardened (grsecurity/AppArmor) | `aa-status` |
| Disk | Encrypted (LUKS) | `lsblk -f` |
| Backups | Automated, encrypted, offsite, tested | Restore test monthly |

### Web Server (Apache/Nginx)

#### Apache (`/etc/apache2/sites-available/pensi-ticketing.conf`)
```apache
<VirtualHost *:443>
    ServerName tiket.pensi.sch.id
    DocumentRoot /var/www/pensi-ticketing/public

    # SSL/TLS
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/tiket.pensi.sch.id/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/tiket.pensi.sch.id/privkey.pem
    SSLProtocol all -SSLv2 -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite HIGH:!aNULL:!MD5:!3DES
    SSLHonorCipherOrder on

    # Security Headers (also in PHP, but defense-in-depth)
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set Referrer-Policy strict-origin-when-cross-origin
    Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
    Header always set X-Permitted-Cross-Domain-Policies none

    # Hide server version
    ServerTokens Prod
    ServerSignature Off

    # Document root
    <Directory /var/www/pensi-ticketing/public>
        Options -Indexes -FollowSymLinks
        AllowOverride All
        Require all granted
        
        # Clean URLs
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^ index.php [QSA,L]
    </Directory>

    # Deny access to sensitive directories
    <Directory /var/www/pensi-ticketing/storage>
        Require all denied
    </Directory>
    <Directory /var/www/pensi-ticketing/config>
        Require all denied
    </Directory>
    <Directory /var/www/pensi-ticketing/src>
        Require all denied
    </Directory>
    <Directory /var/www/pensi-ticketing/tests>
        Require all denied
    </Directory>
    <Directory /var/www/pensi-ticketing/database>
        Require all denied
    </Directory>
    <Directory /var/www/pensi-ticketing/views>
        Require all denied
    </Directory>

    # PHP-FPM
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.2-fpm.sock|fcgi://localhost"
    </FilesMatch>

    # Logging
    ErrorLog ${APACHE_LOG_DIR}/pensi-ticketing_error.log
    CustomLog ${APACHE_LOG_DIR}/pensi-ticketing_access.log combined
</VirtualHost>

# HTTP → HTTPS redirect
<VirtualHost *:80>
    ServerName tiket.pensi.sch.id
    Redirect permanent / https://tiket.pensi.sch.id/
</VirtualHost>
```

#### Nginx (`/etc/nginx/sites-available/pensi-ticketing`)
```nginx
server {
    listen 80;
    server_name tiket.pensi.sch.id;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name tiket.pensi.sch.id;
    root /var/www/pensi-ticketing/public;
    index index.php;

    # SSL
    ssl_certificate /etc/letsencrypt/live/tiket.pensi.sch.id/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/tiket.pensi.sch.id/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5:!3DES;
    ssl_prefer_server_ciphers on;

    # Security Headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options DENY always;
    add_header Referrer-Policy strict-origin-when-cross-origin always;
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;
    add_header X-Permitted-Cross-Domain-Policies none always;

    # Hide version
    server_tokens off;

    # Deny sensitive directories
    location ~ ^/(storage|config|src|tests|database|views)/ {
        deny all;
        return 404;
    }

    # Clean URLs
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_intercept_errors on;
    }

    # Logging
    access_log /var/log/nginx/pensi-ticketing_access.log;
    error_log /var/log/nginx/pensi-ticketing_error.log;
}
```

### PHP Configuration (`php.ini` Production)

```ini
; Security
expose_php = Off
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/www/pensi-ticketing/storage/logs/php_errors.log
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT

; Session
session.cookie_secure = 1
session.cookie_httponly = 1
session.cookie_samesite = Strict
session.use_strict_mode = 1
session.use_only_cookies = 1
session.gc_maxlifetime = 14400
session.sid_length = 48
session.sid_bits_per_character = 6

; File Uploads
file_uploads = On
upload_max_filesize = 2M
post_max_size = 8M
max_file_uploads = 1
upload_tmp_dir = /var/www/pensi-ticketing/storage/tmp

; Execution Limits
max_execution_time = 30
max_input_time = 30
memory_limit = 128M
max_input_vars = 1000

; Disable Dangerous Functions
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source,pcntl_exec,dl,set_time_limit,symlink,link,syslog,imap_open,ldap_connect

; OPcache (Performance + Security)
opcache.enable = 1
opcache.enable_cli = 1
opcache.validate_timestamps = 0
opcache.save_comments = 1
opcache.fast_shutdown = 1
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 10000
opcache.memory_consumption = 128
opcache.revalidate_freq = 0

; OpenBaseDir (Restrict PHP file access)
open_basedir = /var/www/pensi-ticketing:/tmp:/var/www/pensi-ticketing/storage/tmp
```

### Database (MariaDB/MySQL) Hardening

```sql
-- Create dedicated application user (no root)
CREATE USER 'ticketing_app'@'127.0.0.1' IDENTIFIED BY 'CHANGE_ME_STRONG_PASSWORD';
GRANT SELECT, INSERT, UPDATE, DELETE ON db_pensi_ticketing.* TO 'ticketing_app'@'127.0.0.1';
-- Explicitly DENY on audit_logs (append-only)
REVOKE DELETE, UPDATE ON db_pensi_ticketing.audit_logs FROM 'ticketing_app'@'127.0.0.1';
FLUSH PRIVILEGES;

-- Audit plugin (optional but recommended)
INSTALL PLUGIN server_audit SONAME 'server_audit.so';
SET GLOBAL server_audit_logging = ON;
SET GLOBAL server_audit_events = 'CONNECT,QUERY,TABLE';
SET GLOBAL server_audit_incl_users = 'ticketing_app';

-- SSL for replication (if applicable)
-- REQUIRE SSL on user
```

---

## File System Permissions

```bash
# Application root owned by root, read-only for www-data
chown -R root:root /var/www/pensi-ticketing
chmod -R 755 /var/www/pensi-ticketing

# Storage writable by www-data
chown -R www-data:www-data /var/www/pensi-ticketing/storage
chmod -R 750 /var/www/pensi-ticketing/storage
chmod -R 770 /var/www/pensi-ticketing/storage/uploads
chmod -R 770 /var/www/pensi-ticketing/storage/logs
chmod -R 770 /var/www/pensi-ticketing/storage/tmp

# .env readable only by www-data
chown root:www-data /var/www/pensi-ticketing/.env
chmod 640 /var/www/pensi-ticketing/.env

# Composer vendor (if any) read-only
chown -R root:root /var/www/pensi-ticketing/vendor
chmod -R 755 /var/www/pensi-ticketing/vendor
```

---

## Cron Jobs (System Crontab)

```bash
# /etc/cron.d/pensi-ticketing
# m h  dom mon dow   user   command

# Reservation expiry processor (every minute)
* * * * * www-data /usr/bin/php /var/www/pensi-ticketing/cron/expire_reservations.php >> /var/www/pensi-ticketing/storage/logs/cron_expiry.log 2>&1

# Old reservation cleanup (weekly Sunday 2 AM)
0 2 * * 0 www-data /usr/bin/php /var/www/pensi-ticketing/cron/cleanup_expired.php >> /var/www/pensi-ticketing/storage/logs/cron_cleanup.log 2>&1

# Log rotation (daily 3 AM)
0 3 * * * www-data /usr/sbin/logrotate /etc/logrotate.d/pensi-ticketing

# Database backup (daily 1 AM)
0 1 * * * www-data /usr/bin/mysqldump --single-transaction --routines --triggers db_pensi_ticketing | gzip > /backup/db_pensi_ticketing_$(date +\%Y\%m\%d).sql.gz
```

---

## Log Rotation (`/etc/logrotate.d/pensi-ticketing`)

```
/var/www/pensi-ticketing/storage/logs/*.log {
    daily
    missingok
    rotate 90
    compress
    delaycompress
    notifempty
    create 640 www-data www-data
    sharedscripts
    postrotate
        systemctl reload apache2 > /dev/null 2>&1 || true
    endscript
}

/var/log/apache2/pensi-ticketing_*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 640 www-data adm
}

/var/log/nginx/pensi-ticketing_*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 640 www-data adm
}
```

---

## Monitoring & Alerting

### Prometheus Metrics (Application-Level)
```php
// Add to public/index.php or middleware
// Exposes /metrics endpoint (protect with IP allowlist)
```

### Key Metrics to Alert On

| Metric | Warning Threshold | Critical Threshold | Action |
|--------|-------------------|-------------------|--------|
| Failed logins/min | >10 | >50 | Block IP, investigate |
| Payment rejections/min | >5 | >20 | Finance review |
| Gate check-in failures/min | >3 | >10 | Gate officer check |
| Voucher quota depletion | >80% | >95% | Admin notify |
| Reservation expiry backlog | >100 | >500 | Cron check |
| DB connection pool usage | >80% | >95% | Scale/optimize |
| PHP error rate | >1/min | >10/min | Check logs |
| Disk usage (storage) | >70% | >90% | Cleanup/expand |

### Log-Based Alerts (SIEM Rules)

```yaml
# Failed login spike
- rule: "failed_login_spike"
  condition: "count(event: LOGIN_FAILED) > 10 in 1m"
  severity: high

# Payment anomaly
- rule: "payment_rejection_spike"
  condition: "count(event: REJECT_PAYMENT) > 5 in 5m"
  severity: medium

# Gate anti-passback trigger
- rule: "gate_passback_attempt"
  condition: "event: GATE_CHECKIN and result: already_checked_in"
  severity: high

# Voucher abuse
- rule: "voucher_quota_depleted"
  condition: "voucher_promo.kuota_pakai = 0 and is_active = 1"
  severity: medium

# Admin privilege escalation attempt
- rule: "admin_access_denied"
  condition: "http_status = 302 and path like '/admin%' and role != 'admin_eo'"
  severity: high
```

---

## Incident Response Playbook

### 1. Suspected Account Compromise
1. Lock account: `UPDATE users SET role='locked' WHERE id_user=?`
2. Invalidate sessions: `DELETE FROM sessions WHERE user_id=?` (if session table used)
3. Force password reset on next login
4. Review audit logs for suspicious activity
5. Notify user via registered email

### 2. Payment Fraud Detected
1. Freeze transaction: `UPDATE transaksi SET status_bayar='Frozen' WHERE id_transaksi=?`
2. Reject payment with reason "Suspected fraud - under investigation"
3. Release stock/seats/voucher
4. Review gate check-ins for related tickets
5. Report to management

### 3. Gate Passback Attempt
1. Officer logs show duplicate check-in with details
2. Flag attendee in system
3. Security personnel intercept at venue
4. Audit all tickets from same transaction
5. Update gate officer training

### 4. Data Breach Suspected
1. Isolate affected systems
2. Preserve logs (audit_logs, access logs, error logs)
3. Engage forensic team
4. Notify authorities per local law (PDPA Indonesia)
5. Communicate transparently to affected users

---

## Backup & Recovery

### Backup Strategy
| Data | Frequency | Retention | Storage |
|------|-----------|-----------|---------|
| Database (full) | Daily 1 AM | 30 days | Encrypted offsite (S3/Wasabi) |
| Database (incremental) | Hourly | 7 days | Encrypted offsite |
| Application code | On deploy | Forever | Git (signed commits) |
| Uploads (payment proofs) | Daily | 7 years | Encrypted offsite |
| Config/Secrets | On change | Forever | Vault (HashiCorp/AWS) |

### Recovery Point Objective (RPO)
- **Database:** 1 hour (hourly incrementals)
- **Uploads:** 24 hours
- **Config:** 0 (version controlled)

### Recovery Time Objective (RTO)
- **Full restore:** < 4 hours
- **Database only:** < 1 hour
- **Failover:** Manual (documented runbook)

---

## Security Testing in CI/CD

```yaml
# .github/workflows/security.yml (example)
name: Security Checks
on: [push, pull_request]
jobs:
  security:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Install PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - name: Install dependencies
        run: composer install --no-progress --prefer-dist
      - name: PHPStan Static Analysis
        run: vendor/bin/phpstan analyse src/ --level=5
      - name: Composer Audit
        run: composer audit --locked
      - name: Run Unit Tests
        run: php tests/UnitTestSuite.php
      - name: Run Integration Tests
        run: php tests/IntegrationTestSuite.php
      - name: Run Security Tests
        run: php tests/InjectionTestSuite.php && php tests/AuthorizationTestSuite.php
```

---

## Compliance Mapping

| Regulation | Requirement | Implementation |
|------------|-------------|----------------|
| **PDPA Indonesia** | Consent, purpose limitation, data minimization | Explicit consent on registration; only required fields; NIK masked |
| **PDPA Indonesia** | Right to access/correction/deletion | Admin can export/delete user data (future feature) |
| **PDPA Indonesia** | Security measures | BCRYPT, TLS, RBAC, audit logs, encryption at rest (DB) |
| **PCI DSS (SAQ A)** | No card storage | No payment processing; manual bank transfer only |
| **ISO 27001 Annex A** | Access control, crypto, logging, monitoring | RBAC, BCRYPT/TLS, audit_logs, alerting rules |

---

## Change Management

### Security-Relevant Changes Requiring Review
- Authentication/session logic
- Authorization/RBAC modifications
- File upload handling
- Database schema changes (especially audit_logs)
- Payment/financial logic
- Gate check-in logic
- Voucher/inventory logic
- CSP/security header changes
- Environment variable additions

### Review Process
1. Create feature branch
2. Implement with tests
3. Run full test suite (`tests/run_all.sh`)
4. Security review (this checklist)
5. Approval by security engineer
6. Merge to main
7. Deploy to staging → production

---

## Contact & Escalation

| Role | Name | Contact | Escalation |
|------|------|---------|------------|
| Security Engineer | [Assigned] | security@pensi.sch.id | Level 1 |
| System Admin | [Assigned] | admin@pensi.sch.id | Level 2 |
| Database Admin | [Assigned] | dba@pensi.sch.id | Level 2 |
| Management | [Assigned] | management@pensi.sch.id | Level 3 |

**Security Incident Email:** security-incident@pensi.sch.id (monitored 24/7 during events)

---

## Document Control

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2025-11-14 | Security Engineer | Initial release |

**Classification:** CONFIDENTIAL — Internal Use Only  
**Next Review:** 2026-05-14 (6 months) or after major release

---

*This configuration guide must be updated for every production deployment. Store in version control alongside application code.*