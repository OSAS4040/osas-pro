/**
 * Popup print/PDF windows cannot read the SPA’s CSS. Keep font links in sync with
 * `frontend/index.html` (Google Fonts) and stack in sync with `--font-sans` in `main.css`.
 */
export const PRINT_HTML_FONT_LINKS = `
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
`

/** Same stack as `:root { --font-sans }` in `src/assets/main.css` */
export const PRINT_HTML_FONT_FAMILY = "'Tajawal','Inter',ui-sans-serif,system-ui,sans-serif"
