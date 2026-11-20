# Changelog — every iteration

This repository keeps the full development history of **PENSI EVENTPRO** (PAKET 7).
`master` is the latest application; every milestone is a Git tag, and the two earlier
architectures are preserved as legacy branches.

## Branches

| Branch | What it contains |
|---|---|
| `master` | Latest multi-event platform (`db_tiket_event_pro`), 10 themes, installer-ready |
| `legacy/php-flat-template` | Original flat-PHP template (pre-hardening): 19 files, `db_pensi_sekolah`, no CSS |
| `legacy/mvc-rewrite` | PSR-4 MVC experiment (controllers/repositories/views), security docs |

## Tags

| Tag | Date | Milestone |
|---|---|---|
| `v0-base-template` | 2026-11-18 | Base flat-PHP template preserved on `legacy/php-flat-template` |
| `v1.0-hardened` | 2026-11-18 | First hardened flat build — 27 security fixes, all Mandat CEO rules |
| `v1.1-pre-infrastructure-upgrade` | 2026-11-18 | Snapshot before XAMPP infrastructure upgrade research |
| `v2.0-working` | 2026-11-19 | Restructure to `public/` webroot + `src/` (Config, Middleware, Views) |
| `v2.0-mvc-rewrite` | 2026-11-19 | Tag on `legacy/mvc-rewrite` (PSR-4 MVC experiment) |
| `v3-ten-designs` | 2026-11-19 | Design system + 10 themes (5 dark/5 light), e-ticket JPG/PDF |
| `v3.1-verified` | 2026-11-19 | Responsive tables, stock integrity, dark-mode readability, TTF e-ticket |
| `v3.2-review-fixes` | 2026-11-19 | Contrast round 2 from vision review |
| `archive-2026-11-19` | 2026-11-19 | Archive marker (same tree as v3.2) |
| `v4-multievent` | 2026-11-19 | Rewrite to `db_tiket_event_pro`: multi-event, 12 pages, EO analytics, audit log |
| `v4.1-tested` | 2026-11-19 | Theme query param, event description, seat grid/colours, tests |
| `v4-final` | 2026-11-19 | v4 build report |
| `v4.2-portable` | 2026-11-19 | Portable paths (`base_url`), `config.local.php`, installer, font fallback |
| `v4.3-tested` | 2026-11-19 | Manual testing guide + 54-check E2E suite |
| `v4.4-docs` | 2026-11-19 | End-user role-based guide |
| `v5-ui-accessibility` | 2026-11-19 | Visible borders, table grid, dark-mode text inversion, custom controls |
| `v6-payments-eticket` | 2026-11-19 | Train-ticket e-ticket, cash/transfer, proof modal, parallel bars |
| `v6.1-portable` | 2026-11-19 | Portable installer, bundled Lato fonts, env-driven seed |

## Restoring an iteration

```bash
git clone https://github.com/Willsoll/pensi-eventpro.git
cd pensi-eventpro

git checkout v6.1-portable          # latest portable build
git checkout v4-multievent          # first multi-event rewrite
git checkout legacy/php-flat-template   # original flat template
```

The portable ZIP (app + database dump + installer + fonts) is attached to the
`v6.1-portable` GitHub Release.
