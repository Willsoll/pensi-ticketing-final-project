# PENSI EVENTPRO — secure flat deployment source

This branch preserves the **original website layout** (PHP pages at the webroot,
`src/` beside them) while removing the issues found in the 2026-11-21 audit.

## Fixes in this branch

- Removed hard-coded InfinityFree DB credentials and host fallbacks.
- Fixed `dirname(__DIR__, 1)` config/storage paths to `dirname(__DIR__, 2)`.
- Removed public `tests/`, `install/`, `docs/`, `designs/`, the Windows installer
  and SQL dumps from the deployment package.
- Root `.htaccess` denies `src/`, `storage/`, `database/`, `config.local.php`,
  old dev folders, `.sql`, `.log`, `.md`, `.exe`, and executable uploads.
- Gate officers can no longer download arbitrary paid e-tickets (owner /
  admin_eo / finance only).
- Voucher remaining quota is decremented atomically; rejection/expiry restores it.
- Per-user checkout is serialized, and the 5-ticket limit is enforced **per NIK**
  across accounts.
- Login throttling canonicalises email case and adds an IP-level limit.
- CSP, nosniff, frame denial, referrer policy, permissions policy and HSTS
  (HTTPS) headers; `X-Powered-By` removed.
- PHP2Shell/session-upload-progress hardening in `.htaccess`.

## Deployment

See `SECURE_SETUP.txt`. Import `database/secure_passwords.sql` after importing
the demo database, rotate the MySQL password, and create `config.local.php` from
the example. Never commit `config.local.php`.

## Notes

- `database/` is present in the repo for local setup but is denied by
  `.htaccess` in the webroot. On shared hosting, keep it outside the public
  document root if possible.
- The Windows installer and portable Git bundle are intentionally not part of
  this branch; use the release assets for those.
