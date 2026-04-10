/**
 * عرض قيم المطابقة المالية القادمة من الخادم (مفاتيح إنجليزية) للواجهة العربية.
 */

export type ReconciliationHealthTone = 'ok' | 'warn' | 'bad' | 'neutral'

export function reconciliationHealthDisplay(
  value: string | null | undefined,
): { label: string; tone: ReconciliationHealthTone } {
  const v = String(value ?? '')
    .trim()
    .toLowerCase()
  const map: Record<string, string> = {
    healthy: 'سليمة',
    warning: 'تحتاج انتباهاً',
    critical: 'حرجة',
  }
  const label = map[v] ?? (v ? v : '—')
  const tone: ReconciliationHealthTone =
    v === 'healthy' ? 'ok' : v === 'warning' ? 'warn' : v === 'critical' ? 'bad' : 'neutral'
  return { label, tone }
}

export function reconciliationRunStatusLabelAr(value: string | null | undefined): string {
  const v = String(value ?? '')
    .trim()
    .toLowerCase()
  const map: Record<string, string> = {
    running: 'قيد التشغيل',
    succeeded: 'اكتمل بنجاح',
    failed: 'فشل',
    blocked: 'مُحجوب',
  }
  return map[v] ?? (v || '—')
}

export function reconciliationFindingStatusLabelAr(value: string | null | undefined): string {
  const v = String(value ?? '')
    .trim()
    .toLowerCase()
  const map: Record<string, string> = {
    open: 'مفتوحة',
    acknowledged: 'مُقرّ بها',
    resolved: 'محسومة',
    false_positive: 'إيجابية خاطئة',
  }
  return map[v] ?? (v || '—')
}

export function reconciliationFindingTypeLabelAr(value: string | null | undefined): string {
  const v = String(value ?? '')
    .trim()
    .toLowerCase()
  const map: Record<string, string> = {
    invoice_without_ledger: 'فاتورة بلا قيد محاسبي',
    unbalanced_journal_entry: 'قيد يومية غير متوازن',
    anomalous_reversal_settlement: 'تسوية عكسية غير طبيعية',
  }
  return map[v] ?? (v || '—')
}

export function reconciliationReferenceTypeLabelAr(value: string | null | undefined): string {
  const v = String(value ?? '')
    .trim()
    .toLowerCase()
  const map: Record<string, string> = {
    invoice: 'فاتورة',
    journal_entry: 'قيد يومية',
    payment: 'دفعة',
  }
  return map[v] ?? (v || '—')
}
