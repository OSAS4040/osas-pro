import type { Component } from 'vue'
import {
  ChartBarIcon,
  ShieldCheckIcon,
  WrenchScrewdriverIcon,
  BuildingOffice2Icon,
  UsersIcon,
  Cog8ToothIcon,
  CommandLineIcon,
  ClipboardDocumentListIcon,
  BanknotesIcon,
  ExclamationTriangleIcon,
  MegaphoneIcon,
  LifebuoyIcon,
  BoltIcon,
  Squares2X2Icon,
  BellAlertIcon,
} from '@heroicons/vue/24/outline'

/** معرف القسم الداخلي لـ PlatformAdminDashboardPage (sectionKey) والتنقل */
export type PlatformAdminSectionId =
  | 'overview'
  | 'governance'
  | 'ops'
  | 'tenants'
  | 'customers'
  | 'plans'
  | 'operator-commands'
  | 'audit'
  | 'finance'
  | 'cancellations'
  | 'banner'
  | 'support'
  | 'incidents'
  | 'command-surface'
  | 'notifications'

export interface PlatformAdminNavItem {
  id: PlatformAdminSectionId
  label: string
  routeName: string
  icon: Component
  /** تسمية صغيرة فوق الرابط (تجميع تشغيلي) */
  navEyebrow: string
  /** سطر توضيحي تحت الرابط */
  navHint: string
}

/** مجموعات الشريط الجانبي — كل `sectionIds` يجب أن يغطي عناصر `platformAdminNavItems` مرة واحدة فقط */
export interface PlatformAdminSidebarGroup {
  id: string
  title: string
  sectionIds: PlatformAdminSectionId[]
}

export const platformAdminSidebarGroups: PlatformAdminSidebarGroup[] = [
  {
    id: 'run',
    title: 'الملخص والتشغيل',
    sectionIds: ['overview', 'ops', 'incidents', 'notifications', 'command-surface'],
  },
  {
    id: 'tenancy',
    title: 'المشتركون والخدمات',
    sectionIds: ['tenants', 'customers', 'plans', 'support', 'banner'],
  },
  {
    id: 'money',
    title: 'المال والالتزام',
    sectionIds: ['finance', 'cancellations', 'audit'],
  },
  {
    id: 'gov',
    title: 'الحوكمة والمشغّل',
    sectionIds: ['governance', 'operator-commands'],
  },
]

export const platformAdminNavItems: PlatformAdminNavItem[] = [
  {
    id: 'overview',
    label: 'الملخص والمؤشرات',
    routeName: 'platform-overview',
    icon: ChartBarIcon,
    navEyebrow: 'الرصد والملخص',
    navHint: 'مؤشرات المنصة والشركات والنبض في لوحة واحدة واضحة.',
  },
  {
    id: 'governance',
    label: 'الحوكمة والسياق',
    routeName: 'platform-governance',
    icon: ShieldCheckIcon,
    navEyebrow: 'الحوكمة',
    navHint: 'سياق التشغيل والصلاحيات وبيئة المنصة.',
  },
  {
    id: 'ops',
    label: 'تشغيل المنصة',
    routeName: 'platform-ops',
    icon: WrenchScrewdriverIcon,
    navEyebrow: 'التشغيل اليومي',
    navHint: 'عمليات التشغيل والمراقبة التشغيلية للمنصة.',
  },
  {
    id: 'tenants',
    label: 'المشتركون (شركات)',
    routeName: 'platform-companies',
    icon: BuildingOffice2Icon,
    navEyebrow: 'المشتركون',
    navHint: 'قائمة الشركات والحالة والمخاطر والأولوية التشغيلية.',
  },
  {
    id: 'customers',
    label: 'عملاء المنصة',
    routeName: 'platform-customers',
    icon: UsersIcon,
    navEyebrow: 'العملاء',
    navHint: 'عرض عملاء المستأجرين عبر جميع الشركات بلا سياق شركة واحدة.',
  },
  {
    id: 'plans',
    label: 'الباقات والتمكين',
    routeName: 'platform-plans',
    icon: Cog8ToothIcon,
    navEyebrow: 'الباقات والتمكين',
    navHint: 'كتالوج الباقات والإضافات المدفوعة وسياسات التمكين.',
  },
  {
    id: 'operator-commands',
    label: 'أوامر المشغّل',
    routeName: 'platform-operator-commands',
    icon: CommandLineIcon,
    navEyebrow: 'أوامر المشغّل',
    navHint: 'أوامر تشغيل منظّمة وآمنة للمشغّلين المعتمدين.',
  },
  {
    id: 'incidents',
    label: 'مركز الحوادث',
    routeName: 'platform-incidents',
    icon: BoltIcon,
    navEyebrow: 'الحوادث والذكاء',
    navHint: 'متابعة الحوادث والمرشحين والأولويات التشغيلية.',
  },
  {
    id: 'command-surface',
    label: 'سطح القيادة (ذكاء)',
    routeName: 'platform-intelligence-command',
    icon: Squares2X2Icon,
    navEyebrow: 'ذكاء المنصة',
    navHint: 'سطح القيادة والإجراءات الموجّهة والمتابعة التشغيلية.',
  },
  {
    id: 'notifications',
    label: 'مركز التنبيهات والمتابعة',
    routeName: 'platform-notifications',
    icon: BellAlertIcon,
    navEyebrow: 'التنبيهات',
    navHint: 'ما يحتاج انتباهك الآن مع روابط مباشرة للمتابعة.',
  },
  {
    id: 'audit',
    label: 'تدقيق المنصة',
    routeName: 'platform-audit',
    icon: ClipboardDocumentListIcon,
    navEyebrow: 'التدقيق',
    navHint: 'سجلات التدقيق والتغييرات والامتثال على مستوى المنصة.',
  },
  {
    id: 'finance',
    label: 'النموذج المالي',
    routeName: 'platform-finance',
    icon: BanknotesIcon,
    navEyebrow: 'النموذج المالي',
    navHint: 'اعتماد النماذج المالية والمتابعة لكل مشترك.',
  },
  {
    id: 'cancellations',
    label: 'إلغاء أوامر العمل',
    routeName: 'platform-cancellations',
    icon: ExclamationTriangleIcon,
    navEyebrow: 'الإلغاءات',
    navHint: 'طلبات إلغاء أوامر العمل التي تحتاج قراراً تشغيلياً.',
  },
  {
    id: 'support',
    label: 'الدعم الفني (كل المشتركين)',
    routeName: 'platform-support',
    icon: LifebuoyIcon,
    navEyebrow: 'الدعم الفني',
    navHint: 'تذاكر الدعم عبر كل المشتركين والمتابعة المركزية.',
  },
  {
    id: 'banner',
    label: 'شريط الإعلان',
    routeName: 'platform-announcements',
    icon: MegaphoneIcon,
    navEyebrow: 'الإعلانات',
    navHint: 'شريط الإعلان على مستوى المنصة والرسائل العامة.',
  },
]

/** روابط شريط جانبي: مزودو الخدمة + التسعير + عقود/تقارير — تُختبر أن المسارات مسجلة في Vue Router */
export const platformProviderSidebarLinks = [
  { routeName: 'platform-providers-list', label: 'قائمة المزودين' },
  { routeName: 'platform-providers-new', label: 'إضافة مزود خدمة' },
  { routeName: 'platform-provider-costs', label: 'أسعار المزود' },
] as const satisfies readonly { routeName: string; label: string }[]

export const platformCommercialPricingSidebarLinks = [
  { routeName: 'platform-pricing-requests', label: 'طلبات التسعير' },
  { routeName: 'platform-pricing-review', label: 'مراجعة طلبات التسعير' },
  { routeName: 'platform-pricing-approve', label: 'اعتماد طلبات التسعير' },
  { routeName: 'platform-pricing-catalogs', label: 'قوائم الأسعار' },
  { routeName: 'platform-pricing-customer-prices', label: 'أسعار العملاء' },
  { routeName: 'platform-pricing-price-activation', label: 'اعتماد الأسعار' },
] as const satisfies readonly { routeName: string; label: string }[]

export const platformContractsAndReportsSidebarLinks = [
  { routeName: 'platform-contracts', label: 'العقود' },
  { routeName: 'platform-reports', label: 'التقارير' },
] as const satisfies readonly { routeName: string; label: string }[]

export function allPlatformControlPlanePricingRouteNames(): readonly string[] {
  return [
    ...platformProviderSidebarLinks.map((x) => x.routeName),
    ...platformCommercialPricingSidebarLinks.map((x) => x.routeName),
    ...platformContractsAndReportsSidebarLinks.map((x) => x.routeName),
  ]
}

/** تحويل روابط قديمة #admin-section-* إلى مسار /platform/* */
export function platformPathFromAdminHash(hash: string): string {
  const h = hash.startsWith('#') ? hash : `#${hash}`
  const map: Record<string, string> = {
    '#admin-section-overview': '/platform/overview',
    '#admin-section-governance': '/platform/governance',
    '#admin-section-ops': '/platform/ops',
    '#admin-section-tenants': '/platform/companies',
    '#admin-section-customers': '/platform/customers',
    '#admin-section-plans': '/platform/plans',
    '#admin-section-operator-commands': '/platform/operator-commands',
    '#admin-section-audit': '/platform/audit',
    '#admin-section-finance': '/platform/finance',
    '#admin-section-cancellations': '/platform/cancellations',
    '#admin-section-banner': '/platform/announcements',
  }
  return map[h] ?? '/platform/overview'
}
