# Pensi Theme Contract v1 (READ BEFORE WRITING ANY THEME)

Every theme file lives in `C:/xampp/htdocs/public/assets/css/themes/` and MUST:
1. Start with a comment block: theme name, mode (dark/light), design intent, layout variation.
2. Override ONLY CSS custom properties on `:root` (plus optional layout rules below).
3. Use the MANDATORY ROMBEL 3.md palette as the base. Do not invent brand hues outside the family.
   - Primary Indigo  #1E1B4B
   - Accent Purple   #6D28D9
   - CTA Orange      #F97316
   - Header/Footer   #111827
   - Light Background#F8FAFC
   - Success Emerald #059669
4. Define EVERY variable below (missing ones fall back to light defaults and break dark themes):

```
--color-primary, --color-primary-light, --color-primary-dark
--color-accent, --color-accent-light, --color-accent-dark
--color-cta, --color-cta-light, --color-cta-dark
--color-header, --color-header-light
--color-bg, --color-bg-card, --color-bg-muted
--color-success, --color-success-light, --color-success-bg, --color-success-border
--color-warning, --color-warning-light, --color-warning-bg, --color-warning-border
--color-error, --color-error-light, --color-error-bg, --color-error-border
--color-info, --color-info-light, --color-info-bg, --color-info-border
--color-text-primary, --color-text-secondary, --color-text-muted, --color-text-inverse
--color-text-on-primary, --color-text-on-cta
--color-border-light, --color-border-medium, --color-border-dark, --color-border-focus
--radius-md, --radius-lg, --radius-xl, --radius-full
--shadow-sm, --shadow-md, --shadow-lg, --shadow-xl, --shadow-primary, --shadow-cta
```

5. Layout variation rules (optional, allowed selectors):
   `.header`, `.header-inner`, `.header-brand`, `.header-nav`, `.header-user`,
   `.main`, `.card`, `.card-header`, `.card-body`, `.stat-card`, `.btn`, `.btn-primary`,
   `.btn-cta`, `.btn-secondary`, `.badge`, `.table th`, `.table td`, `.breadcrumb`
   You MAY change: header orientation (sticky/static/centered), nav pill style,
   card corners/shadows/borders/glass effect, button shape (pill/rounded/square),
   table header style, page max-width, hero grid direction, background patterns.

6. Accessibility floor: text on background contrast >= 4.5:1 for body text; buttons
   keep visible focus (`outline: 2px solid var(--color-border-focus)` already global).
7. No external images/fonts. Gradients, radial-glow, repeating-linear-gradient, SVG
   data-URIs are allowed. No JS in theme files.
8. Max 220 lines per file. Comment each block briefly.

Dark themes must use dark surfaces (#0B1020..#1E1B4B family) with light text (#E5E7EB..#F8FAFC).
Light themes must use #F8FAFC/#FFFFFF surfaces with dark text (#111827..#374151).
CTA orange must remain the prominent action color in every theme.
