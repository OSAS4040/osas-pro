import { pathToStaffNavKey } from '@/lib/staffNavKey'

/**
 * مسارات تُخفى عند تفعيل «شريك تنفيذ المنصة» — لا سجل عملاء/مركبات مستقل ولا إنشاء فاتورة يدوي.
 * الهدف أن يبقى الكتالوج والاشتراك والاعتماد النقدي تحت سياسة المنصّة؛ لا يُعتبر ذلك خللاً بل فصلاً مقصوداً للصلاحيات.
 * مراجعة طلبات شحن المحفظة: تُستثنى من الإخفاء عبر `applyWalletTopUpReviewerNavOverride` عند امتلاك صلاحيات المحفظة المناسبة.
 */
const EXECUTION_PARTNER_HIDDEN_PATHS = [
  '/customers',
  '/vehicles',
  '/invoices/create',
  '/crm/quotes',
  '/crm/relations',
  '/wallet/top-up-requests',
  '/plans',
  '/subscription',
  '/subscription/plans',
  '/subscription/payment',
  '/subscription/invoices',
] as const

export function executionPartnerHiddenNavKeys(): string[] {
  return EXECUTION_PARTNER_HIDDEN_PATHS.map((p) => pathToStaffNavKey(p))
}

export function mergeExecutionPartnerNavKeys(base: string[], executionPartner: boolean): string[] {
  if (!executionPartner) return base
  const out = [...base]
  for (const k of executionPartnerHiddenNavKeys()) {
    if (!out.includes(k)) out.push(k)
  }
  return out
}
