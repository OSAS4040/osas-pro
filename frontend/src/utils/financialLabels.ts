/** UI-only labels and badge classes for money-related statuses (Arabic). */

export const invoiceStatusLabels: Record<string, string> = {
  draft: 'مسودة',
  pending: 'معلقة',
  paid: 'مدفوعة',
  partial_paid: 'مدفوعة جزئياً',
  cancelled: 'ملغية',
  refunded: 'مستردة',
}

export const invoiceStatusClasses: Record<string, string> = {
  draft: 'bg-slate-100 text-slate-600 dark:bg-slate-700/80 dark:text-slate-300',
  pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
  paid: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
  partial_paid: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
  cancelled: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-200',
  refunded: 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-200',
}

export function invoiceStatusLabel(status: string | undefined): string {
  const s = String(status ?? '')
  return invoiceStatusLabels[s] ?? s
}

export function invoiceStatusClass(status: string | undefined): string {
  const s = String(status ?? '')
  return invoiceStatusClasses[s] ?? 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
}

export const paymentStatusLabels: Record<string, string> = {
  completed: 'مكتمل',
  refunded: 'مسترد',
  pending: 'معلق',
  failed: 'فشل',
}

export const paymentStatusClasses: Record<string, string> = {
  completed: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
  refunded: 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200',
  pending: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
  failed: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-200',
}

export function paymentStatusLabel(status: string | undefined): string {
  const s = String(status ?? 'completed')
  return paymentStatusLabels[s] ?? s
}

export function paymentStatusClass(status: string | undefined): string {
  const s = String(status ?? 'completed')
  return paymentStatusClasses[s] ?? paymentStatusClasses.completed
}

export const paymentMethodLabels: Record<string, string> = {
  cash: 'نقدي',
  card: 'بطاقة',
  wallet: 'محفظة',
  bank_transfer: 'تحويل بنكي',
  prepaid: 'مسبق',
}

export function paymentMethodLabel(method: string | undefined): string {
  const m = String(method ?? '')
  return paymentMethodLabels[m] ?? m
}
