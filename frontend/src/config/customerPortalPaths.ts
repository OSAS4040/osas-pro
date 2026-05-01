/**
 * مسارات الشريط الجانبي لبوابة العميل — مصدر واحد لـ Vitest و Playwright.
 * عند إضافة مسار في `CustomerLayout.vue` (`navItemsAll`) أضفه هنا أيضاً.
 */
export const CUSTOMER_PORTAL_NAV_PATHS: readonly string[] = [
  '/customer/dashboard',
  '/customer/work-orders',
  '/customer/bookings',
  '/customer/coverage-locations',
  '/customer/vehicles',
  '/customer/wallet',
  '/customer/wallet/top-up-requests',
  '/customer/invoices',
  '/customer/reports',
  '/customer/business-intelligence',
  '/customer/pricing',
  '/customer/company-settings',
  '/customer/profile',
  '/customer/team-users',
  '/customer/org-units',
  '/customer/activity',
  '/customer/plans',
  '/customer/subscription',
  '/customer/subscription/plans',
  '/customer/subscription/payment',
  '/customer/subscription/invoices',
  '/customer/zatca',
  '/customer/api-keys',
  '/customer/notifications',
  '/customer/settings',
]
