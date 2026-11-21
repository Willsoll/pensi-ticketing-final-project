# Security

## Deployment checklist

1. Use PHP 8.2.35+ (or 8.3/8.4 current) and keep `display_errors` off.
2. Set `config.local.php` with a least-privilege database user. Never commit it.
3. Keep `storage/` and payment proofs outside the webroot; only `bukti.php` may stream them.
4. Ensure `config.local.php`, `src/`, `database/`, `tests/`, `docs/` and `storage/` return 403 over HTTP.
5. Serve only over HTTPS. The application sends HSTS on HTTPS; configure the host edge to redirect port 80.
6. Rotate demo staff passwords before sharing the site. Delete unused demo accounts.
7. Keep `database/` dumps and test harnesses out of the deployed package.
8. Run `php -l` on changed files and repeat the checks in the audit report after deployment.

## Reporting

Report security issues privately to the project maintainer. Do not open a public issue that contains an exploit or personal data.

## Residual notes

- The live InfinityFree edge is openresty and does not honor `.htaccess` for the port-80 challenge response.
- The current UI uses inline styles and handlers, so the CSP permits `'unsafe-inline'` for style and script. Moving handlers to `assets/js/ui.js` would allow a stricter nonce-based policy.
- Demo customer accounts use seed credentials; they are non-privileged but should be removed before real use.
