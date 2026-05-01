export type NavVisibilityPolicy = {
  sections: Record<string, boolean>
  groups: Record<string, boolean>
}

export const NAV_SECTION_LABELS: Record<string, string> = {
  operations: 'التشغيلي',
  hr: 'الموارد البشرية',
  finance_accounting: 'المالية والمحاسبة',
  inventory: 'المخزون والكتالوج',
  analytics: 'التقارير والتحليلات',
  admin: 'إداري',
  platform: 'إدارة منصة أسس برو',
  subscription: 'الاشتراك',
}

export const NAV_GROUP_LABELS: Record<string, string> = {
  purchases: 'المشتريات',
  accountant: 'المحاسب',
  'platform-center': 'مركز إدارة المنصة',
  admin_branches: 'الفروع والمواقع',
  admin_contracts_docs: 'العقود والوثائق',
  admin_company_settings: 'إعدادات المنشأة والفريق',
  admin_security_logs: 'الأمان والسجلات',
  admin_support: 'الدعم والولاء',
}

export const DEFAULT_NAV_VISIBILITY: NavVisibilityPolicy = {
  sections: Object.fromEntries(Object.keys(NAV_SECTION_LABELS).map((k) => [k, true])),
  groups: Object.fromEntries(Object.keys(NAV_GROUP_LABELS).map((k) => [k, true])),
}
