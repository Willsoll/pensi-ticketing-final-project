# Design 09 — Soft Pastel (Light)

**View:** `http://127.0.0.1/?theme=09` (persists via cookie `pensi_theme`)

## Design intent
Soft indigo-tinted cards, 16px radius, muted pastel badges, calm typography, pill buttons.

## Palette (ROMBEL 3.md mandatory)
Primary #1E1B4B · Accent #6D28D9 · CTA #F97316 · Header #111827 · Background #F8FAFC · Success #059669 —
all mapped to CSS variables in `artifacts/theme.css`; loaded after `design-system.css`.
Light surfaces keep WCAG AA body-text contrast.

## Layout & component variation
Header/nav treatment, card shape/shadow, button geometry and table styling differ per theme as described above;
content structure and all business logic stay identical so every design is fully functional.

## Interactivity (shared, via /assets/js/ui.js)
- Loading screen overlay with spinner (fades ≤1.2s, respects reduced-motion)
- Parallax on `[data-parallax]` (home hero), rAF-throttled
- Scroll reveal on `.reveal` (IntersectionObserver, once)
- 3D tilt on `[data-tilt]` info cards (mouse only)
- Animated counters on `[data-count-to]`
- Floating theme switcher (bottom-right) listing all 10 designs


## Test status (shared backend, all themes)
- Functional suite: **14/14 PASS** (`artifacts/log_functional.txt`)
- Stress suite: **8/8 PASS** — 150 sequential + 10 parallel requests, zero 500s, 16.1s (`artifacts/log_stress.txt`)
- E-Ticket exports verified: JPG (800x1010, 94 KB, FFD8) and PDF (%PDF-1.4 → %%EOF, 69 KB) with unique `kode_tiket_unik` and HMAC barcode.

## Artifacts
- `artifacts/theme.css` — exact theme stylesheet shipped to production
- `artifacts/screenshot-home.png` — full-page home capture (headless Chrome)
- `artifacts/log_functional.txt`, `artifacts/log_stress.txt` — test evidence

## Files
- Theme: `public/assets/css/themes/theme-09-soft-pastel.css`
- Shared base: `public/assets/css/design-system.css`
- JS: `public/assets/js/ui.js`
- Layout: `src/Views/layouts/header.php`
