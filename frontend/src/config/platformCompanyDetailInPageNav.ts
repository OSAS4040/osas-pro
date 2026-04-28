import type { PlatformInPageNavItem } from '@/config/platformAdminInPageNav'

export type PlatformCompanyDetailTabId =
  | 'overview'
  | 'finance'
  | 'customers'
  | 'vehicles'
  | 'invoices'

/** فهرس تنقل داخل تبويب صفحة تفاصيل شركة المنصة */
export const platformCompanyDetailInPageNavByTab: Record<PlatformCompanyDetailTabId, PlatformInPageNavItem[]> = {
  overview: [
    { id: 'platform-company-overview-summary', label: 'الملخص والإشارات' },
    { id: 'platform-company-overview-alerts', label: 'التنبيهات' },
    { id: 'platform-company-overview-activity', label: 'النشاط' },
  ],
  finance: [
    { id: 'platform-company-finance-kpis', label: 'المؤشرات' },
    { id: 'platform-company-finance-table', label: 'جدول الفواتير' },
  ],
  customers: [{ id: 'platform-company-customers-table', label: 'جدول العملاء' }],
  vehicles: [{ id: 'platform-company-vehicles-table', label: 'جدول المركبات' }],
  invoices: [{ id: 'platform-company-invoices-table', label: 'جدول الفواتير' }],
}
