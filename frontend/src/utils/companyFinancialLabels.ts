/**
 * عرض عربي لقيم النموذج المالي للشركة (Enums: CompanyFinancialModel / CompanyFinancialModelStatus).
 */

const MODEL_AR: Record<string, string> = {
  prepaid: 'شحن مسبق',
  credit: 'ائتمان',
}

const STATUS_AR: Record<string, string> = {
  pending_platform_review: 'قيد مراجعة المنصة',
  approved_prepaid: 'معتمد — شحن مسبق',
  approved_credit: 'معتمد — ائتمان',
  rejected: 'مرفوض',
  suspended: 'معلّق',
}

export function companyFinancialModelLabel(value: string | null | undefined): string {
  const v = String(value ?? '').trim()
  if (!v) return '—'
  return MODEL_AR[v] ?? v
}

export function companyFinancialModelStatusLabel(value: string | null | undefined): string {
  const v = String(value ?? '').trim()
  if (!v) return '—'
  return STATUS_AR[v] ?? v.replace(/_/g, ' ')
}
