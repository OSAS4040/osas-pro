import { printDocument } from '@/composables/useAppPrint'
import { useToast } from '@/composables/useToast'
import { t } from '@/composables/useLocale'

function escapeCsvCell(v: string): string {
  const s = String(v ?? '')
  if (/[",\n\r]/.test(s)) return `"${s.replace(/"/g, '""')}"`
  return s
}

/**
 * طباعة / مشاركة / حفظ لصفحات مزوّد الخدمة (شريك تنفيذ، مشتريات، مطالبات، إلخ)
 * بنفس روح التقارير: منطقة `print-container` + أزرار خارجها في `page-head`.
 */
export function useProviderPortalDocument() {
  const toast = useToast()

  async function printRegion(rootSelector: string, title: string): Promise<void> {
    await printDocument({ rootSelector, title })
  }

  async function shareUrl(opts: {
    path: string
    query?: Record<string, string | number | undefined | null>
    title: string
    text: string
  }): Promise<void> {
    const u = new URL(opts.path, window.location.origin)
    Object.entries(opts.query ?? {}).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') u.searchParams.set(k, String(v))
    })
    const url = u.toString()
    if (typeof navigator !== 'undefined' && navigator.share) {
      try {
        await navigator.share({ title: opts.title, text: opts.text, url })
        return
      } catch {
        /* fallback */
      }
    }
    try {
      await navigator.clipboard.writeText(url)
      toast.success(
        t('providerPortal.documents.copySuccessTitle'),
        t('providerPortal.documents.copySuccessBody'),
      )
    } catch {
      toast.error(
        t('providerPortal.documents.copyFailTitle'),
        url,
      )
    }
  }

  function saveJson(filename: string, data: unknown): void {
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json;charset=utf-8' })
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = filename.endsWith('.json') ? filename : `${filename}.json`
    a.click()
    URL.revokeObjectURL(a.href)
    toast.success(
      t('providerPortal.documents.savedTitle'),
      t('providerPortal.documents.savedBodyJson'),
    )
  }

  function saveCsv(filename: string, headers: string[], rows: string[][]): void {
    const headerLine = headers.map(escapeCsvCell).join(',')
    const body = rows.map((r) => r.map((c) => escapeCsvCell(String(c))).join(',')).join('\n')
    const bom = '\ufeff'
    const blob = new Blob([bom + headerLine + '\n' + body], { type: 'text/csv;charset=utf-8' })
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = filename.endsWith('.csv') ? filename : `${filename}.csv`
    a.click()
    URL.revokeObjectURL(a.href)
    toast.success(
      t('providerPortal.documents.exportedTitle'),
      t('providerPortal.documents.exportedBodyCsv'),
    )
  }

  return { printRegion, shareUrl, saveJson, saveCsv }
}
