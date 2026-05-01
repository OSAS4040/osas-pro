import type { PlatformAdminSectionId } from '@/config/platformAdminNav'

/** عنصر فهرس داخل الصفحة — يمرّر إلى `scrollIntoView` بدون تغيير المسارات */
export interface PlatformInPageNavItem {
  id: string
  label: string
}

/**
 * فهرس تنقل لكل قسم من أقسام إدارة المنصة.
 * صفحات قصيرة أو بلوك واحد تُترك فارغة (لا يُعرض شريط الفهرس).
 */
export const platformInPageNavBySection: Record<PlatformAdminSectionId, PlatformInPageNavItem[]> = {
  overview: [
    { id: 'platform-overview-executive', label: 'الملخص التنفيذي' },
    { id: 'platform-overview-analytics', label: 'الرسوم والتحليلات' },
    { id: 'platform-overview-pulse', label: 'نبض المنصة' },
    { id: 'platform-overview-recent', label: 'أحدث المشتركين' },
  ],
  governance: [],
  ops: [],
  tenants: [
    { id: 'platform-tenants-snapshot', label: 'لقطة سريعة' },
    { id: 'platform-tenants-panels', label: 'لوحات الأولويات' },
    { id: 'platform-tenants-filters', label: 'تصفية وبحث' },
    { id: 'platform-tenants-table', label: 'جدول المشتركين' },
  ],
  customers: [
    { id: 'platform-customers-head', label: 'عنوان الصفحة' },
    { id: 'platform-customers-filters', label: 'بحث وتصفية' },
    { id: 'platform-customers-table', label: 'جدول العملاء' },
  ],
  plans: [
    { id: 'platform-plans-catalog', label: 'كتالوج الباقات' },
    { id: 'platform-plans-addons', label: 'الإضافات المدفوعة' },
  ],
  'operator-commands': [],
  audit: [],
  finance: [],
  cancellations: [
    { id: 'platform-cancellations-controls', label: 'تصفية وتحديث' },
    { id: 'platform-cancellations-table', label: 'جدول الطلبات' },
  ],
  support: [],
  banner: [],
  incidents: [],
  'command-surface': [],
  notifications: [],
}
