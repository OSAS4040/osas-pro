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
  title?: string
  includeFormalFrame?: boolean
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

  const printRoot = root as HTMLElement
  const includeFormalFrame = opts.includeFormalFrame !== false
  const printTitle = String(opts.title || document.title || 'تقرير النظام').trim()
  const printedAt = new Date().toLocaleString('ar-SA-u-ca-gregory')

  const formalHeader = document.createElement('div')
  formalHeader.className = 'app-print-header app-print-only'
  formalHeader.setAttribute('data-print-role', 'header')
  formalHeader.innerHTML = `
    <div class="app-print-header__left">
      <div class="app-print-brand">Verdent</div>
      <div class="app-print-subtitle">نظام إدارة عمليات العميل</div>
    </div>
    <div class="app-print-header__right">
      <div class="app-print-doc-title">${printTitle}</div>
      <div class="app-print-doc-stamp">نسخة طباعة رسمية</div>
    </div>
  `

  const formalFooter = document.createElement('div')
  formalFooter.className = 'app-print-footer app-print-only'
  formalFooter.setAttribute('data-print-role', 'footer')
  formalFooter.innerHTML = `
    <div class="app-print-footer__left app-print-page-counter"></div>
    <div class="app-print-footer__right">ختم الوقت: ${printedAt}</div>
  `

  if (includeFormalFrame) {
    printRoot.prepend(formalHeader)
    printRoot.append(formalFooter)
    printRoot.classList.add('print-container--active', 'print-formal-template')
  }
  document.body.classList.add('print-content-isolated')
  const done = () => {
    document.body.classList.remove('print-content-isolated')
    if (includeFormalFrame) {
      formalHeader.remove()
      formalFooter.remove()
      printRoot.classList.remove('print-container--active', 'print-formal-template')
    }
    window.removeEventListener('afterprint', done)
  }
  window.addEventListener('afterprint', done, { once: true })
  window.setTimeout(done, 2000)
  window.print()
}
