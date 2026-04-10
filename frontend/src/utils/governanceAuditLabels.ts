/**
 * عرض عربي لسجل التدقيق (إجراءات وكيانات مخزّنة كمفاتيح/أسماء فئات من الخادم).
 */

const ACTION_AR: Record<string, string> = {
  'policy.saved': 'حفظ سياسة',
  'workflow.approved': 'اعتماد طلب موافقة',
  'workflow.rejected': 'رفض طلب موافقة',
  'employee.created': 'إنشاء موظف',
  'employee.updated': 'تحديث موظف',
  'commission.paid': 'دفع عمولة',
  'bay.created': 'إنشاء منطقة عمل',
  'bay.status_changed': 'تغيير حالة منطقة عمل',
  'platform.financial_model.updated': 'تحديث النموذج المالي (منصة)',
  'platform.company.operational_updated': 'تحديث بيانات تشغيل الشركة (منصة)',
  'platform.subscription.plan_changed': 'تغيير خطة الاشتراك (منصة)',
  'vertical_profile.resolution_check.company': 'فحص دقة إعدادات الشركة (ملف عمودي)',
  'vertical_profile.resolution_check.branch': 'فحص دقة إعدادات الفرع (ملف عمودي)',
  'vertical_profile.assigned.company': 'ربط ملف عمودي بالشركة',
  'vertical_profile.assigned.branch': 'ربط ملف عمودي بالفرع',
  'vertical_profile.unassigned.company': 'إلغاء ربط الملف العمودي عن الشركة',
  'vertical_profile.unassigned.branch': 'إلغاء ربط الملف العمودي عن الفرع',
  'vertical_profile.reassigned.company': 'إعادة تعيين الملف العمودي للشركة',
  'vertical_profile.reassigned.branch': 'إعادة تعيين الملف العمودي للفرع',
  'vertical_profile.assignment.noop.company': 'لم يتغيّر الملف العمودي (شركة)',
  'vertical_profile.assignment.noop.branch': 'لم يتغيّر الملف العمودي (فرع)',
  'meeting.created': 'إنشاء اجتماع',
  'meeting.updated': 'تحديث اجتماع',
  'meeting.minutes_added': 'إضافة محضر',
  'meeting.decision_added': 'إضافة قرار',
  'meeting.decision.approval_started': 'بدء موافقة على قرار',
  'meeting.decision.approved': 'اعتماد قرار',
  'meeting.decision.rejected': 'رفض قرار',
  'meeting.action_added': 'إضافة إجراء',
  'meeting.action.updated': 'تحديث إجراء',
  'meeting.action.closed': 'إغلاق إجراء',
  'meeting.closed': 'إغلاق اجتماع',
}

/** أسماء فئات Eloquent (basename) → عربي */
const SUBJECT_TYPE_AR: Record<string, string> = {
  Company: 'شركة',
  Employee: 'موظف',
  Branch: 'فرع',
  PolicyRule: 'سياسة',
  ApprovalWorkflow: 'سير موافقات',
  Bay: 'منطقة عمل',
  Commission: 'عمولة',
  VerticalProfile: 'ملف عمودي',
  ConfigSetting: 'إعداد',
  Meeting: 'اجتماع',
  Subscription: 'اشتراك',
  Invoice: 'فاتورة',
  WorkOrder: 'أمر عمل',
  Customer: 'عميل',
  Vehicle: 'مركبة',
  Product: 'منتج',
  Inventory: 'مخزون',
  User: 'مستخدم',
  Wallet: 'محفظة',
  Quote: 'عرض سعر',
  Purchase: 'شراء',
  /** قيم غير فئة نموذجية */
  config_resolution: 'فحص إعدادات',
}

const SEGMENT_AR: Record<string, string> = {
  created: 'إنشاء',
  updated: 'تحديث',
  saved: 'حفظ',
  approved: 'اعتماد',
  rejected: 'رفض',
  paid: 'دفع',
  employee: 'موظف',
  company: 'شركة',
  branch: 'فرع',
  platform: 'المنصة',
  financial_model: 'النموذج المالي',
  subscription: 'الاشتراك',
  plan: 'الخطة',
  changed: 'تغيير',
  policy: 'سياسة',
  workflow: 'موافقة',
  commission: 'عمولة',
  bay: 'منطقة عمل',
  meeting: 'اجتماع',
  vertical_profile: 'ملف عمودي',
  operational: 'تشغيلي',
  operational_updated: 'تحديث تشغيلي',
  plan_changed: 'تغيير الخطة',
  status: 'الحالة',
  status_changed: 'تغيير الحالة',
  resolution_check: 'فحص الدقة',
  assigned: 'ربط',
  unassigned: 'إلغاء الربط',
  reassigned: 'إعادة تعيين',
  assignment: 'تعيين',
  noop: 'بدون تغيير',
}

/**
 * ترجمة مفتاح الإجراء؛ عند غياب التعريف يُبنى وصف مقروء من المقاطع.
 */
export function formatAuditAction(action: string | null | undefined): string {
  const raw = String(action ?? '').trim()
  if (!raw) return '—'
  if (ACTION_AR[raw]) return ACTION_AR[raw]
  const parts = raw.split('.').filter(Boolean)
  if (!parts.length) return raw
  const mapped = parts.map((p) => SEGMENT_AR[p] ?? p.replace(/_/g, ' '))
  return mapped.join(' ← ')
}

function basenameSubjectType(subjectType: string | null | undefined): string {
  if (!subjectType) return ''
  const s = String(subjectType).trim()
  const i = s.lastIndexOf('\\')
  return i >= 0 ? s.slice(i + 1) : s
}

/**
 * عرض الكيان: نوع عربي + رقم المعرف.
 */
export function formatAuditSubject(
  subjectType: string | null | undefined,
  subjectId: number | string | null | undefined,
): string {
  const base = basenameSubjectType(subjectType)
  const typeAr = SUBJECT_TYPE_AR[base] ?? (base ? base.replace(/_/g, ' ') : '')
  const id = subjectId != null && subjectId !== '' ? String(subjectId) : ''
  if (!typeAr && !id) return '—'
  if (!id) return typeAr || '—'
  if (!typeAr) return `معرّف ${id}`
  return `${typeAr} · رقم ${id}`
}

export function formatAuditUserId(userId: number | string | null | undefined): string {
  if (userId == null || userId === '') return '—'
  return `مستخدم رقم ${userId}`
}
