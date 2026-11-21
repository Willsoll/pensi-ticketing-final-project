# Portability & path handling

## Runtime code

No runtime PHP file contains an absolute `C:\...`, `htdocs`, `xampp`, or
`laragon` path. Every include, config file, storage key, upload folder, and font
uses one of:

- `__DIR__` for includes/assets
- `dirname(__DIR__, 2)` for `config.local.php`, `storage/`, and `barcode_secret.key`
- `base_path()` / `base_url()` for browser URLs (derived from `SCRIPT_NAME`)

The only absolute paths in the repository are comments in `database/seed.php`
and `tests/*.php` showing an example command.

## Installer

`PENSI_EVENTPRO_Setup.exe` does not assume `C:`. It finds XAMPP/Laragon from:

1. `XAMPP_HOME`, `LARAGON_ROOT`, `LARAGON_HOME`
2. every ready fixed/removable drive (`D:\xampp`, `E:\laragon`, ...)
3. the running `httpd.exe` / `mysqld.exe` process path
4. manual path entry (`[3]`)

It then copies the app into the real webroot (`htdocs` for XAMPP, `www` for
Laragon, or the `DocumentRoot` from `apache/conf/httpd.conf`), imports
`database/db_tiket_event_pro.sql`, writes `config.local.php`, and optionally
starts Apache/MySQL.

## Verified matrix

| Scenario | Result |
|---|---|
| XAMPP at `C:\xampp` (default DocumentRoot) | copy + DB import + page 200 |
| XAMPP DocumentRoot custom (`htdocs/public`) | copied to DocumentRoot; CSS 200 |
| Laragon-shaped root (`www`, `bin/mysql`) | copied to `www/<app>`; dry-run OK |
| App in htdocs subfolder | CSS/theme/JS 200 (v6.2.1 fix) |
| App at DocumentRoot root | CSS/JS 200 |
