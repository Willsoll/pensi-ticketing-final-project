# InfinityFree deployment

This package is laid out so the **contents** of the ZIP go directly into
InfinityFree's `htdocs` folder.

```
htdocs/
├── index.php                 redirects / → /public/
├── .htaccess                 redirect + protects app internals
├── public/                   webroot (pages, assets, themes, uploads)
├── src/                      Config, Middleware, Views
├── database/
│   ├── infinityfree_import.sql   import this in phpMyAdmin
│   └── schema.sql                reference only
├── storage/                  writable runtime folder
├── config.local.example.php  copy to config.local.php and fill credentials
└── INFINITYFREE_SETUP.md
```

## 1. Create the database

InfinityFree control panel → **MySQL Databases** → create a database.

Write down:

- **MySQL host** — usually `sqlXXX.infinityfree.com`
- **Database name** — usually `if0_XXXXXXX_pensi`
- **Username** — usually `if0_XXXXXXX`
- **Password** — the one you set

## 2. Import the database

1. Control panel → **phpMyAdmin** → select your database.
2. **Import** → choose `database/infinityfree_import.sql`.
3. Import. It creates the 9 graded tables plus `transaksi_detail`, and loads
   the demo data (4 events, 12 tiers, 230 seats, 4 vouchers, 13 transactions).

Do **not** import `db_tiket_event_pro.sql` on shared hosting: it contains
`CREATE DATABASE`/`USE` statements that InfinityFree's database user cannot run.
`infinityfree_import.sql` is the stripped, hosting-safe version.

## 3. Upload the files

1. Control panel → **File Manager** (or FTP).
2. Open `htdocs`.
3. Upload `PENSI_EVENTPRO_INFINITYFREE_FULL.zip`.
4. Extract it **inside `htdocs`**.
5. If the extractor creates a folder, move its contents up so `index.php`,
   `public/`, `src/`, `database/`, `storage/` and `.htaccess` sit directly in
   `htdocs`.
6. Make sure hidden files are visible; `.htaccess` must be uploaded.

The ZIP is under InfinityFree's 10 MB file-manager upload limit. For FTP, use
the FTP details in the control panel.

## 4. Configure the database

1. Copy `config.local.example.php` → `config.local.php`.
2. Edit it with the control-panel values:

```php
<?php
return array(
    'host' => 'sqlXXX.infinityfree.com',
    'port' => 3306,
    'name' => 'if0_XXXXXXX_pensi',
    'user' => 'if0_XXXXXXX',
    'pass' => 'YOUR_DATABASE_PASSWORD',
);
```

`config.local.php` stays in `htdocs` (next to `public/`), not inside `public/`.

## 5. Permissions / PHP

- PHP version: set **8.2 or 8.3** in the control panel.
- Required extensions: `pdo_mysql`, `mbstring`, `gd`, `fileinfo`.
- `storage/` and `public/uploads/bukti/` must be writable (normally `755`).
- The app creates `storage/barcode_secret.key` on first e-ticket generation.

## 6. Open the site

- `http://your-domain/` → redirects to `/public/`
- If the root redirect is blocked, open `http://your-domain/public/`
- Login: `admin.eo@eventpro.id` / `password123`

## Troubleshooting

| Symptom | Fix |
|---|---|
| 403 at `/` | Open `/public/` directly; check that `.htaccess` uploaded |
| Blank page | Set PHP 8.2+; check `storage/` writable; check DB credentials |
| `SQLSTATE[HY000] [1045]` | Wrong DB user/password in `config.local.php` |
| `Unknown database` | DB name in config must match control panel exactly |
| CSS missing | Upload the entire `public/assets/` tree; keep `public/` name |
| 500 on upload | `public/uploads/bukti/` not writable |

## Security notes

- Do not upload the Windows `.exe`, `git/`, `samples/`, or `tests/` to the host.
- `config.local.php` is blocked by `.htaccess`; never put it inside `public/`.
- After setup, change the demo passwords or delete unused demo accounts.
- This is a school project; free hosting has no SLA.
