/** إزالة التشكيل وتوحيد بعض الحروف العربية لتحسين المطابقة */
const AR_DIACRITICS = /[\u064B-\u065F\u0670\u0610-\u061A]/g

export function foldSearchText(s: string): string {
  let t = (s ?? '').trim().toLowerCase().replace(AR_DIACRITICS, '')
  t = t.replace(/[أإآٱ]/g, 'ا').replace(/ى/g, 'ي').replace(/ؤ/g, 'و').replace(/ئ/g, 'ي')
  t = t.replace(/ة/g, 'ه')
  return t.replace(/\s+/g, ' ')
}

/** درجة مطابقة بسيطة: كل كلمة من الاستعلام يجب أن تظهر في النص؛ مكافأة لو بداية الحقل تطابق بادئة */
export function textMatchScore(label: string, group: string, path: string, keywords: string | undefined, query: string): number {
  const q = foldSearchText(query)
  if (!q) return 1
  const words = q.split(' ').filter(Boolean)
  if (!words.length) return 1
  const hay = foldSearchText(`${label} ${group} ${path.replace(/\//g, ' ')} ${keywords ?? ''}`)
  let score = 0
  for (const w of words) {
    if (!hay.includes(w)) return 0
    score += 10
    if (hay.startsWith(w)) score += 4
    const labelF = foldSearchText(label)
    if (labelF.includes(w)) score += 3
  }
  return score
}

/** رفع أولوية عناصر ذات صلة بالمسار الحالي */
export function routeContextBoost(currentPath: string, itemTo: string, group: string): number {
  let b = 0
  const p = currentPath || '/'
  const to = itemTo || ''

  if (p.startsWith('/workshop')) {
    if (to.startsWith('/workshop') || group === 'مركز الخدمة' || group === 'الموارد البشرية') b += 6
  }
  if (p.startsWith('/customers') || p.startsWith('/crm')) {
    if (to.includes('customer') || to === '/vehicles' || group === 'العملاء') b += 5
  }
  if (p.startsWith('/invoices') || p.startsWith('/pos')) {
    if (to.includes('invoice') || to === '/pos' || group === 'الرئيسي') b += 4
  }
  if (p.startsWith('/work-orders') || p.startsWith('/bays')) {
    if (to.includes('work-orders') || to.startsWith('/bays') || to === '/bookings') b += 6
  }
  if (p.startsWith('/inventory') || p.startsWith('/products') || p.startsWith('/purchases')) {
    if (group === 'المخزون') b += 6
  }
  if (p.startsWith('/reports') || p.startsWith('/business-intelligence')) {
    if (group === 'التقارير' || to.includes('business-intelligence')) b += 8
  }
  if (p.startsWith('/branches')) {
    if (to.startsWith('/branches')) b += 10
  }
  if (p.startsWith('/fleet')) {
    if (group === 'الأسطول') b += 10
  }
  if (
    p.startsWith('/ledger')
    || p.startsWith('/wallet')
    || p.startsWith('/chart-of-accounts')
    || p.startsWith('/zatca')
    || p.startsWith('/fixed-assets')
    || p.startsWith('/compliance')
  ) {
    if (group === 'المالية والمحاسبة') b += 5
  }
  if (p.startsWith('/settings')) {
    if (to.startsWith('/settings')) b += 10
  }

  return b
}
