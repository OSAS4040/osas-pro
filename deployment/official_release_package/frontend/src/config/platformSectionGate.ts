import type { PlatformAdminSectionId } from '@/config/platformAdminNav'

/**
 * لكل قسم في لوحة المنصّة: قائمة صلاحيات — يكفي أن يملك المستخدم **واحدة** منها.
 * يُطابق توزيع الأدوار في `backend/config/platform_roles.php`.
 */
export const PLATFORM_SECTION_ANY_PERMISSIONS: Partial<Record<PlatformAdminSectionId, readonly string[]>> = {
  overview: [
    'platform.companies.read',
    'platform.ops.read',
    'platform.reporting.read',
    'platform.pricing.view',
    'platform.audit.read',
    'platform.support.read',
    'platform.subscription.manage',
    'platform.catalog.manage',
    'platform.financial_model.manage',
    'platform.cancellations.read',
    'platform.announcement.read',
    'platform.notifications.read',
    'platform.intelligence.incidents.read',
    'platform.purchase_claims.read',
  ],
  governance: ['platform.companies.read'],
  ops: ['platform.ops.read'],
  tenants: ['platform.companies.read'],
  customers: ['platform.companies.read'],
  plans: ['platform.catalog.manage', 'platform.subscription.manage'],
  'operator-commands': ['platform.ops.read', 'platform.intelligence.guided_workflows.execute'],
  audit: ['platform.audit.read'],
  finance: ['platform.financial_model.manage', 'platform.reporting.read', 'platform.subscription.manage'],
  cancellations: ['platform.cancellations.read', 'platform.cancellations.manage'],
  banner: ['platform.announcement.read', 'platform.announcement.manage'],
  support: ['platform.support.read', 'platform.support.manage'],
  incidents: ['platform.intelligence.incidents.read'],
  'command-surface': [
    'platform.intelligence.controlled_actions.view',
    'platform.intelligence.incidents.read',
  ],
  notifications: ['platform.notifications.read'],
  'purchase-claims': ['platform.purchase_claims.read', 'platform.purchase_claims.review'],
}

/** مسارات إضافية تحت `/platform/*` ليست أقسام dashboard — تُربَط بصلاحية صريحة */
export const PLATFORM_ROUTE_EXTRA_ANY_PERMISSIONS: Record<string, readonly string[]> = {
  'platform-company-detail': ['platform.companies.read'],
  'platform-incident-detail': ['platform.intelligence.incidents.read'],
  'platform-providers-list': ['platform.providers.manage'],
  'platform-providers-new': ['platform.providers.manage'],
  'platform-provider-costs': ['platform.providers.manage'],
  'platform-pricing-requests': ['platform.pricing.view', 'platform.pricing.create'],
  'platform-pricing-review': ['platform.pricing.review'],
  'platform-pricing-approve': ['platform.pricing.approve'],
  'platform-pricing-request-detail': ['platform.pricing.view'],
  'platform-pricing-catalogs': ['platform.pricing.view'],
  'platform-pricing-customer-prices': ['platform.pricing.view'],
  'platform-pricing-price-activation': ['platform.pricing.approve'],
  'platform-contracts': ['platform.pricing.view', 'platform.providers.manage'],
  'platform-reports': ['platform.reporting.read', 'platform.pricing.view'],
}

export function canAccessWithAnyPermission(
  hasPermission: (key: string) => boolean,
  keys: readonly string[] | undefined,
): boolean {
  if (!keys || keys.length === 0) return true
  return keys.some((k) => hasPermission(k))
}

export function canAccessPlatformSection(
  hasPermission: (key: string) => boolean,
  sectionId: PlatformAdminSectionId,
): boolean {
  const keys = PLATFORM_SECTION_ANY_PERMISSIONS[sectionId]
  return canAccessWithAnyPermission(hasPermission, keys)
}
