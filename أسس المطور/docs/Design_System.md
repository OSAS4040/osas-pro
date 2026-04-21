# Mini design system (theme-safe)

This document is the single reference for **app shell typography**, **icons**, **theme variables**, and **print/PDF** alignment. It describes what is fixed for all tenants versus what changes per customer theme.

## Official typeface

- **Stack (all UI, forms, tables, invoices in-app):** `Tajawal`, `Inter`, then `ui-sans-serif`, `system-ui`, `sans-serif`.
- **Source of truth:** CSS variable `--font-sans` in `frontend/src/assets/main.css`, mirrored in Tailwind `fontFamily.sans` in `frontend/tailwind.config.js`.
- **Loading:** Google Fonts in `frontend/index.html` (Tajawal 400/500/700 + Inter 400/500/600/700). Do not reference `Inter` in CSS without this load.
- **Language-specific overrides:** `.font-urdu`, `.font-bengali`, `.font-hindi` in `main.css` keep Noto families where needed; they layer on top of the base stack.

## Icons

- **Canonical library:** `@heroicons/vue` only (outline for chrome, solid where emphasis is needed — e.g. toasts).
- **Sizes:** tokenized as `--icon-size-inline`, `--icon-size-nav`, `--icon-size-card` in `main.css`; optional Tailwind-friendly helpers `.icon-inline`, `.icon-nav`, `.icon-card`.
- **Do not** add a second icon font family for product UI without an explicit product decision.

## Theme variables (tenant / preset)

**Fixed (not overridden by tenant primary):**

- `--font-sans`, `--font-mono`, icon size tokens.
- Semantic status colors in components (success / danger / warning) stay on Tailwind semantic palettes unless migrated later.

**Customizable (via `useTheme` / company settings):**

- `--color-primary` and generated `--color-primary-{50…900}` on `document.documentElement`.
- Dynamic stylesheet `#dynamic-theme` maps Tailwind classes such as `.bg-primary-600`, `.text-primary-700`, etc. to the active preset (`frontend/src/composables/useTheme.ts`).

**Shell surfaces (light/dark):**

- `--bg-base`, `--bg-card`, `--bg-sidebar`, `--bg-header`, `--border-color`, `--text-primary`, `--text-secondary`, `--text-muted`, radii, `--ring-focus` — toggled in `.dark` in `main.css`.

Prefer these variables for **page titles** where we aligned utilities (e.g. `.page-title-xl`, `.page-title` use `var(--text-primary)` / `var(--text-secondary)`).

## Print and PDF

**In-app print (`@media print`):** `main.css` forces `font-family: var(--font-sans)` on `html, body` so reports and salary print roots match the shell.

**Popup print windows** (invoices list, invoice detail, smart invoice, salary slip): they do not load the SPA bundle. Use the shared fragments in `frontend/src/design/printHtml.ts`:

- `PRINT_HTML_FONT_LINKS` — same Google Fonts URLs as `index.html`.
- `PRINT_HTML_FONT_FAMILY` — must stay identical to `--font-sans` (without `var()`).

**DOM capture (html2canvas → PDF):**

- Await `document.fonts.ready` before capture where implemented (invoice PDF export) so webfonts are painted.
- Hidden invoice template `#invoice-print-template` includes scoped layout CSS in `InvoiceShowView.vue` so the clone matches layout and font when `invoice-print-only` is removed for capture.

**jsPDF / autotable:** table PDFs built in JS may use built-in fonts (limited Arabic). That is unchanged; HTML print/PDF paths above are the consistency target for branded output.

## Files touched when changing the system font

1. `frontend/index.html` — font `<link>` weights/families.
2. `frontend/src/assets/main.css` — `--font-sans`.
3. `frontend/tailwind.config.js` — `theme.extend.fontFamily.sans`.
4. `frontend/src/design/printHtml.ts` — `PRINT_HTML_*` constants.

## Summary table

| Concern            | Controlled by                                      |
| ------------------ | -------------------------------------------------- |
| UI font            | `--font-sans`, Tailwind `font-sans`, Google Fonts  |
| Primary palette    | `useTheme` + `#dynamic-theme` + Tailwind `primary` |
| Surfaces & text    | `:root` / `.dark` variables in `main.css`          |
| Icons              | Heroicons + optional `.icon-*` utilities           |
| Print popup fonts  | `printHtml.ts` + inline CSS in each feature        |
