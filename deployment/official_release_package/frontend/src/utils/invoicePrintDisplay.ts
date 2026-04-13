/**
 * Print/PDF invoice: normalize legacy demo company names and safe logo monogram (Arabic .slice(0,2) is misleading).
 */

export type InvoicePrintCompany = {
  name?: string | null
  name_ar?: string | null
} | null | undefined

function enKey(s?: string | null): string {
  return (s || '').trim().toLowerCase()
}

const LEGACY_EN = new Set(['osas platform', 'asas platform', 'asas pro'])
const LEGACY_AR = new Set(['منصة أواس', 'أسس'])

/** True for seeded / old demo tenant names that should read as أسس برو / Osas Pro on documents. */
export function isLegacyPlatformBrandCompany(company: InvoicePrintCompany): boolean {
  if (!company) return false
  const en = enKey(company.name)
  const ar = (company.name_ar || '').trim()
  return LEGACY_EN.has(en) || LEGACY_AR.has(ar)
}

/** Issuer line on invoice header/footer — maps legacy demo names to the official product name. */
export function invoicePrintCompanyDisplayName(company: InvoicePrintCompany, lang: 'en' | 'ar'): string {
  if (isLegacyPlatformBrandCompany(company)) {
    return lang === 'ar' ? 'أسس برو' : 'Osas Pro'
  }
  if (!company) return lang === 'ar' ? '—' : '—'
  const en = (company.name || '').trim()
  const ar = (company.name_ar || '').trim()
  return lang === 'ar' ? (ar || en || '—') : (en || ar || '—')
}

/** Center logo chip when no image: avoid Arabic .slice(0,2) (e.g. منصة → "من"). */
export function invoicePrintLogoMonogram(company: InvoicePrintCompany): string {
  if (isLegacyPlatformBrandCompany(company)) return 'OP'
  if (!company) return 'OP'
  const en = (company.name || '').trim()
  const ar = (company.name_ar || '').trim()
  if (en && /^[A-Za-z]/.test(en)) {
    const parts = en.split(/\s+/).filter(Boolean)
    const a = (parts[0]?.[0] || '').toUpperCase()
    const b = (parts[1]?.[0] || parts[0]?.[1] || '').toUpperCase()
    const pair = (a + b).slice(0, 2)
    return pair || 'O'
  }
  const line = ar || en
  const chars = Array.from(line.replace(/\s+/g, ''))
  if (chars.length >= 2) return `${chars[0]}${chars[1]}`
  return chars[0] || 'أ'
}
