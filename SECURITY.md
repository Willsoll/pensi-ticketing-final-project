# Security Policy

## Demo credentials

This repository is an academic project. Demo accounts (`admin.eo@eventpro.id`,
`finance@eventpro.id`, `gate@eventpro.id`, `dewi@student.id`, password `password123`)
are intentionally public so reviewers can test the application.

**Before any real deployment:** change every demo password, create a dedicated MySQL
user, and set a strong `BARCODE_HMAC_SECRET`.

## Historical artifacts

Older tags/releases may contain retired local configuration values (local XAMPP
database password, demo barcode secret). Those values were **rotated** when this
repository was made public and are no longer valid. Do not reuse them.

## Reporting

Open a private security advisory on GitHub or contact the repository owner.
