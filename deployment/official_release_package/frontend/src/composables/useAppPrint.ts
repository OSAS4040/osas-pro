/**
 * Unified content-only printing for the SPA.
 *
 * Wrap the printable region in `class="print-container"`. Mark chrome with `no-print` or `data-print-hide`.
 */

export async function ensurePrintFontsReady(): Promise<void> {
  if (typeof document === 'undefined') return
  try {
    await document.fonts?.ready?.catch(() => {})
  } catch {
    /* ignore */
  }
}

export interface AppPrintOptions {
  rootSelector?: string
  root?: HTMLElement | null
}

export async function printDocument(opts: AppPrintOptions = {}): Promise<void> {
  await ensurePrintFontsReady()

  let root: Element | null = opts.root ?? null
  if (!root) {
    const sel = opts.rootSelector ?? '.print-container'
    root = document.querySelector(sel)
  }

  if (!root) {
    document.body.classList.add('print-fallback-no-container')
    const done = () => {
      document.body.classList.remove('print-fallback-no-container')
      window.removeEventListener('afterprint', done)
    }
    window.addEventListener('afterprint', done, { once: true })
    window.setTimeout(done, 2000)
    window.print()
    return
  }

  root.classList.add('print-container--active')
  document.body.classList.add('print-content-isolated')
  const done = () => {
    document.body.classList.remove('print-content-isolated')
    root?.classList.remove('print-container--active')
    window.removeEventListener('afterprint', done)
  }
  window.addEventListener('afterprint', done, { once: true })
  window.setTimeout(done, 2000)
  window.print()
}
