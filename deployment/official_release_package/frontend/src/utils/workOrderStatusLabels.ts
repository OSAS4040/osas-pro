/**
 * عرض عربي لحالات **أمر العمل** (`WorkOrderStatus`) وطلبات إلغائه (`WorkOrderCancellationRequestStatus`).
 *
 * لا تُستخدم هنا لـ:
 * - حالة **مهمة ورشة** (`TasksView`: pending / review / …)
 * - حالة **حجز** أو **ركن** (`HeatmapView`)
 * - تذاكر الدعم (`support/StatusBadge`)
 *
 * عند إضافة مفتاح جديد: يفضّل مطابقته مع `backend/app/Enums/WorkOrderStatus.php` (أو قيم fleet-portal المعروفة).
 */

const WORK_ORDER_STATUS_AR: Record<string, string> = {
  draft: 'مسودة',
  pending_manager_approval: 'بانتظار اعتماد المدير',
  approved: 'معتمد',
  cancellation_requested: 'طلب إلغاء قيد المراجعة',
  in_progress: 'قيد التنفيذ',
  on_hold: 'موقوف',
  completed: 'مكتمل',
  delivered: 'مسلّم',
  cancelled: 'ملغي',
  /** مفاتيح قديمة أو من واجهات أخرى */
  pending: 'في الانتظار',
  new: 'جديد',
  assigned: 'مُسنَد',
  invoiced: 'مُفوتر',
}

const WORK_ORDER_STATUS_BADGE_CLASS: Record<string, string> = {
  draft: 'bg-gray-100 text-gray-500 dark:bg-slate-700 dark:text-slate-300',
  pending_manager_approval: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
  approved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
  cancellation_requested: 'bg-amber-100 text-amber-900 dark:bg-amber-900/25 dark:text-amber-200',
  in_progress: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
  on_hold: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
  completed: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
  delivered: 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300',
  cancelled: 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400',
  pending: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
  new: 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-300',
  assigned: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
  invoiced: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
}

const CANCELLATION_REQUEST_STATUS_AR: Record<string, string> = {
  pending: 'قيد المراجعة',
  approved: 'معتمد',
  rejected: 'مرفوض',
}

/** نقطة زمنية صلبة (مثلاً خط سير في جواز المركبة) */
const WORK_ORDER_STATUS_DOT_CLASS: Record<string, string> = {
  draft: 'bg-slate-400',
  pending_manager_approval: 'bg-amber-500',
  approved: 'bg-emerald-500',
  cancellation_requested: 'bg-orange-500',
  in_progress: 'bg-blue-500',
  on_hold: 'bg-orange-400',
  completed: 'bg-green-500',
  delivered: 'bg-teal-500',
  cancelled: 'bg-red-500',
  pending: 'bg-yellow-500',
  new: 'bg-slate-400',
  assigned: 'bg-blue-500',
  invoiced: 'bg-purple-500',
}

export function workOrderStatusLabel(status: string | null | undefined): string {
  if (status == null || status === '') return '—'
  return WORK_ORDER_STATUS_AR[status] ?? status
}

export function workOrderStatusBadgeClass(status: string | null | undefined): string {
  if (status == null || status === '') return 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-300'
  return WORK_ORDER_STATUS_BADGE_CLASS[status] ?? 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-300'
}

export function workOrderStatusTimelineDotClass(status: string | null | undefined): string {
  if (status == null || status === '') return 'bg-gray-300 dark:bg-slate-600'
  return WORK_ORDER_STATUS_DOT_CLASS[status] ?? 'bg-gray-400 dark:bg-slate-500'
}

export function cancellationRequestStatusLabel(status: string | null | undefined): string {
  if (status == null || status === '') return '—'
  return CANCELLATION_REQUEST_STATUS_AR[status] ?? status
}
