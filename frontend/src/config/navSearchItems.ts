import type { EnabledPortals } from '@/config/portalAccess'

/**
 * عناصر للبحث السريع في القائمة الجانبية (تصفية نصية + انتقال مباشر).
 * تُصفى حسب الصلاحيات في المكوّن.
 */
export type NavSearchItem = {
  to: string
  label: string
  section: string
  /** كلمات إضافية للمطابقة */
  keywords?: string[]
  requiresManager?: boolean
  requiresStaff?: boolean
  requiresOwner?: boolean
  requiresIntelligence?: boolean
  /** مطابقة صلاحية واجهة برمجة التطبيقات (المالك يتجاوزها في المتجر) */
  requiresPermission?: string
  /** يكفي أحد الصلاحيات */
  requiresAnyPermission?: string[]
  /** مسارات إدارة المنصة — يظهر فقط لمستخدمي platform_employee */
  requiresPlatform?: boolean
}

export const NAV_SEARCH_ITEMS: NavSearchItem[] = [
  { to: '/', label: 'الرئيسية', section: 'تشغيلي', keywords: ['لوحة', 'dashboard', 'home'] },
  { to: '/pos', label: 'نقطة البيع', section: 'تشغيلي', keywords: ['كاشير', 'بيع', 'pos'] },
  { to: '/work-orders', label: 'أوامر العمل', section: 'تشغيلي', keywords: ['مركز خدمة', 'منفذ بيع', 'صيانة', 'wo'] },
  { to: '/bays', label: 'مناطق العمل', section: 'تشغيلي', keywords: ['رافعة', 'خليج', 'bay'] },
  { to: '/bookings', label: 'الحجوزات', section: 'تشغيلي', keywords: ['موعد', 'حجز'] },
  {
    to: '/meetings',
    label: 'الاجتماعات',
    section: 'تشغيلي',
    requiresPermission: 'meetings.update',
    keywords: ['meeting', 'اجتماع', 'موعد عمل'],
  },
  { to: '/bays/heatmap', label: 'الخريطة الحرارية', section: 'تشغيلي', keywords: ['heatmap', 'ازدحام', 'إشغال', 'ذروة', 'ساعات', 'busy'] },
  { to: '/customers', label: 'العملاء', section: 'تشغيلي', keywords: ['عميل', 'b2c'] },
  {
    to: '/crm/quotes',
    label: 'عروض الأسعار',
    section: 'CRM',
    keywords: ['عرض سعر', 'quotation', 'تسعير', 'crm'],
  },
  {
    to: '/crm/relations',
    label: 'علاقات العملاء',
    section: 'CRM',
    keywords: ['crm', 'علاقة', 'عملاء'],
  },
  { to: '/vehicles', label: 'المركبات', section: 'تشغيلي', keywords: ['سيارة', 'لوحة'] },
  { to: '/fleet/verify-plate', label: 'التحقق من اللوحة', section: 'تشغيلي', keywords: ['fleet', 'plate'] },
  { to: '/fleet/wallet', label: 'محافظ الأسطول', section: 'تشغيلي', keywords: ['fleet', 'wallet'] },
  { to: '/invoices', label: 'الفواتير', section: 'المالية والمحاسبة', keywords: ['فاتورة', 'ضريبة'] },
  {
    to: '/financial-reconciliation',
    label: 'المطابقة المالية',
    section: 'المالية والمحاسبة',
    requiresPermission: 'reports.financial.view',
    keywords: ['reconciliation', 'مطابقة', 'تلاقٍ', 'financial'],
  },
  { to: '/wallet', label: 'المحفظة', section: 'المالية والمحاسبة', keywords: ['رصيد', 'محفظة'] },
  {
    to: '/wallet/top-up-requests',
    label: 'طلبات شحن الرصيد',
    section: 'المالية والمحاسبة',
    keywords: ['شحن', 'topup', 'إيصال', 'تحويل', 'مراجعة'],
    requiresAnyPermission: [
      'wallet.top_up_requests.create',
      'wallet.top_up_requests.view',
      'wallet.top_up_requests.review',
    ],
  },
  { to: '/purchases', label: 'المشتريات', section: 'المالية والمحاسبة', keywords: ['شراء'] },
  { to: '/contracts', label: 'العقود', section: 'إداري', keywords: ['contract', 'عقد'] },
  { to: '/ledger', label: 'دفتر الأستاذ', section: 'المالية والمحاسبة', keywords: ['محاسبة', 'قيود'] },
  { to: '/chart-of-accounts', label: 'دليل الحسابات', section: 'المالية والمحاسبة' },
  { to: '/zatca', label: 'ZATCA الزكاة والضريبة', section: 'المالية والمحاسبة', keywords: ['vat', 'tax'] },
  { to: '/products', label: 'المنتجات', section: 'المخزون' },
  { to: '/inventory', label: 'المخزون', section: 'المخزون' },
  { to: '/suppliers', label: 'الموردون', section: 'المخزون' },
  {
    to: '/business-intelligence',
    label: 'ذكاء الأعمال',
    section: 'التحليلات وذكاء الأعمال',
    requiresIntelligence: true,
    keywords: ['bi', 'تحليل', 'kpi', 'analytics', 'مبيعات', 'تشغيل'],
  },
  { to: '/reports', label: 'التقارير', section: 'التحليلات وذكاء الأعمال' },
  {
    to: '/internal/intelligence',
    label: 'مركز العمليات الذكي',
    section: 'التحليلات وذكاء الأعمال',
    requiresIntelligence: true,
    requiresPermission: 'reports.intelligence.view',
    keywords: ['عمليات', 'ذكاء'],
  },
  { to: '/governance', label: 'الحوكمة والسياسات', section: 'إداري' },
  { to: '/workshop/employees', label: 'الموظفون', section: 'الموارد البشرية', keywords: ['hr', 'موظف'] },
  { to: '/workshop/tasks', label: 'إدارة المهام', section: 'الموارد البشرية', keywords: ['مهمة', 'task'] },
  { to: '/workshop/attendance', label: 'الحضور والانصراف', section: 'الموارد البشرية', keywords: ['بصمة', 'دوام'] },
  { to: '/workshop/salaries', label: 'مسير الرواتب', section: 'الموارد البشرية', keywords: ['راتب', 'payroll'] },
  { to: '/workshop/leaves', label: 'الإجازات', section: 'الموارد البشرية' },
  { to: '/workshop/commissions', label: 'العمولات', section: 'الموارد البشرية' },
  { to: '/workshop/commission-policies', label: 'سياسات العمولات', section: 'الموارد البشرية' },
  { to: '/workshop/hr-comms', label: 'الاتصالات الإدارية', section: 'الموارد البشرية' },
  { to: '/settings', label: 'الإعدادات', section: 'إداري', requiresManager: true },
  { to: '/settings/team-users', label: 'حسابات الفريق', section: 'إداري', requiresManager: true, keywords: ['مستخدم', 'users', 'صلاحيات'] },
  { to: '/settings/org-units', label: 'هيكل القطاعات', section: 'إداري', requiresManager: true, keywords: ['org', 'قطاع', 'قسم', 'هيكل'] },
  { to: '/settings/integrations', label: 'التكاملات', section: 'إداري', requiresManager: true },
  { to: '/subscription', label: 'اشتراكي', section: 'الاشتراك' },
  { to: '/plans', label: 'الباقات', section: 'الاشتراك' },
  { to: '/plugins', label: 'سوق الإضافات', section: 'الاشتراك', keywords: ['إضافات', 'ai'] },
  {
    to: '/about/capabilities',
    label: 'قدرات النظام',
    section: 'إداري',
    requiresStaff: true,
    keywords: ['capabilities', 'ميزات', 'نشاط', 'صلاحيات', 'وضع المنتج'],
  },
  { to: '/support', label: 'مركز الدعم', section: 'إداري', keywords: ['دعم', 'تذكرة'] },
  {
    to: '/account/sessions',
    label: 'الأجهزة والجلسات',
    section: 'أمان',
    requiresStaff: true,
    keywords: ['session', 'جلسة', 'device', 'token', 'logout'],
  },
  { to: '/activity', label: 'سجل العمليات', section: 'إداري' },
  { to: '/branches', label: 'إدارة الفروع', section: 'إداري', requiresManager: true, keywords: ['فرع', 'موقع'] },
  { to: '/branches/map', label: 'خريطة الفروع', section: 'إداري', requiresStaff: true, keywords: ['google', 'خريطة', 'map'] },
  { to: '/documents/company', label: 'مستندات المنشأة', section: 'إداري', keywords: ['documents'] },
  { to: '/admin', label: 'لوحة قيادة المنصة', section: 'إدارة منصة أسس برو', requiresPlatform: true },
  { to: '/admin/qa', label: 'فحص الجودة (QA)', section: 'إدارة منصة أسس برو', requiresPlatform: true, keywords: ['qa'] },
]

export function normNavSearch(s: string): string {
  return s.toLowerCase().replace(/\s+/g, ' ').trim()
}

/** إخفاء عناصر التنقل المرتبطة ببوابة معطّلة (build-time). */
export function navSearchItemVisibleForPortals(item: NavSearchItem, p: EnabledPortals): boolean {
  if (item.to.startsWith('/admin') && !p.admin) return false
  if (item.to.startsWith('/fleet/') && !p.fleet) return false
  return true
}

export function itemMatchesNavQuery(item: NavSearchItem, q: string): boolean {
  if (!q) return true
  const words = q.split(/\s+/).filter(Boolean)
  const hay = normNavSearch(`${item.label} ${item.section} ${item.to.replace(/\//g, ' ')} ${(item.keywords ?? []).join(' ')}`)
  return words.every((w) => hay.includes(w))
}
