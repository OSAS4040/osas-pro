/**
 * منطق موحّد لمبلغ الدفع عند إنشاء فاتورة (واجهة فقط — الخادم يفرض القواعد النهائية).
 */

export const IMMEDIATE_INVOICE_PAYMENT_METHODS = ['cash', 'card', 'wallet', 'bank_transfer'] as const
export type ImmediateInvoicePaymentMethod = (typeof IMMEDIATE_INVOICE_PAYMENT_METHODS)[number]

export const DEFERRED_INVOICE_PAYMENT_METHODS = ['credit'] as const

export function isImmediateInvoicePaymentMethod(method: string): boolean {
  return (IMMEDIATE_INVOICE_PAYMENT_METHODS as readonly string[]).includes(method)
}

/** تقريب مالي لمنزلتين عشريتين */
export function roundMoney2(n: number): number {
  if (!Number.isFinite(n)) return 0
  return Math.round((n + Number.EPSILON) * 100) / 100
}

/** المبلغ الافتراضي للحقل حسب طريقة الدفع وإجمالي الفاتورة */
export function getDefaultPaidAmountByPaymentMethod(method: string, invoiceTotal: number): number {
  const t = roundMoney2(invoiceTotal)
  if (t < 0) return 0
  return isImmediateInvoicePaymentMethod(method) ? t : 0
}

export function parsePaidInput(raw: unknown): number {
  if (raw === '' || raw === null || raw === undefined) return 0
  const n = typeof raw === 'number' ? raw : Number(String(raw).replace(/,/g, '').trim())
  return Number.isFinite(n) ? n : NaN
}

export type PaidValidationIssue =
  | 'non_numeric'
  | 'negative'
  | 'over_total'
  | 'credit_partial_not_supported'

export function validatePaidForSubmit(params: {
  method: string
  paid: number
  invoiceTotal: number
}): { ok: true } | { ok: false; issue: PaidValidationIssue; messageAr: string } {
  const total = roundMoney2(params.invoiceTotal)
  const p = params.paid

  if (!Number.isFinite(p)) {
    return {
      ok: false,
      issue: 'non_numeric',
      messageAr: 'أدخل مبلغاً رقماً صالحاً للمبلغ المدفوع.',
    }
  }
  const pr = roundMoney2(p)
  if (pr < 0) {
    return { ok: false, issue: 'negative', messageAr: 'المبلغ المدفوع لا يمكن أن يكون سالباً.' }
  }
  if (pr - total > 0.005) {
    return {
      ok: false,
      issue: 'over_total',
      messageAr: 'المبلغ المدفوع لا يجوز أن يتجاوز إجمالي الفاتورة.',
    }
  }
  if (params.method === 'credit' && pr > 0.005) {
    return {
      ok: false,
      issue: 'credit_partial_not_supported',
      messageAr:
        'طريقة «ائتمان» لا تدعم تسجيل دفعة عند الإنشاء. اترك المبلغ 0 أو غيّر طريقة الدفع، وسجّل الدفعات لاحقاً من صفحة الفاتورة.',
    }
  }
  return { ok: true }
}

/** المتبقي = الإجمالي − المدفوع (للعرض؛ قد يكون سالباً عند إدخال خاطئ قبل التحقق) */
export function remainingFromTotalAndPaid(invoiceTotal: number, paid: number): number {
  return roundMoney2(roundMoney2(invoiceTotal) - roundMoney2(paid))
}
