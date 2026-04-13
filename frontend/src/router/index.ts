import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useSubscriptionStore } from '@/stores/subscription'
import { featureFlags } from '@/config/featureFlags'
import {
  canAccessStaffBusinessIntelligence,
  canAccessStaffCommandCenter,
  canAccessStaffOperationsArea,
  canAccessWorkshopArea,
} from '@/config/staffFeatureGate'
import { useBusinessProfileStore } from '@/stores/businessProfile'
import { enabledPortals } from '@/config/portalAccess'
import { logActivity } from '@/composables/useActivityLog'
import { useTheme } from '@/composables/useTheme'
/** ضمان تطابق Vue Router مع Vite حتى لا يصبح base ‎./‎ أو فارغًا في بعض البيئات. */
function resolveHistoryBase(): string {
  const raw = import.meta.env.BASE_URL
  if (typeof raw !== 'string' || raw === '' || raw === '.' || raw === './') {
    return '/'
  }
  return raw
}

const routes: RouteRecordRaw[] = [
  /** توافق مع توجيه «غير مشغّل المنصة» بعيداً عن `/admin` */
  { path: '/dashboard', redirect: '/' },
  // ── Auth — صفحة دخول موحّدة لجميع البوابات ──
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/auth/LoginView.vue'),
    meta: { guest: true },
  },
  {
    path: '/platform/login',
    name: 'platform-login',
    component: () => import('@/views/auth/PlatformAdminLoginView.vue'),
    meta: { guest: true, platformAdminLogin: true },
  },
  { path: '/admin/login', redirect: '/platform/login' },
  // Redirect الروابط القديمة للصفحة الموحّدة
  { path: '/fleet/login',    redirect: '/login' },
  { path: '/customer/login', redirect: '/login' },
  {
    path: '/forgot-password',
    name: 'forgot-password',
    component: () => import('@/views/auth/ForgotPasswordView.vue'),
    meta: { guest: true },
  },
  {
    path: '/reset-password',
    name: 'reset-password',
    component: () => import('@/views/auth/ResetPasswordView.vue'),
    meta: { guest: true },
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('@/views/auth/RegisterView.vue'),
    meta: { guest: true },
  },
  {
    path: '/phone',
    name: 'phone-auth',
    component: () => import('@/views/phone/PhoneAuthView.vue'),
    meta: { guest: true },
  },
  {
    path: '/phone/verify',
    name: 'phone-verify',
    component: () => import('@/views/phone/PhoneOtpVerifyView.vue'),
    meta: { guest: true },
  },
  {
    path: '/phone/onboarding',
    name: 'phone-onboarding',
    component: () => import('@/views/phone/PhoneOnboardingHubView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/phone/onboarding/type',
    name: 'phone-onboarding-type',
    component: () => import('@/views/phone/RegistrationAccountTypeView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/phone/onboarding/individual',
    name: 'phone-onboarding-individual',
    component: () => import('@/views/phone/RegistrationIndividualView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/phone/onboarding/company',
    name: 'phone-onboarding-company',
    component: () => import('@/views/phone/RegistrationCompanyView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/phone/onboarding/pending-review',
    name: 'phone-onboarding-pending',
    component: () => import('@/views/phone/CompanyPendingReviewView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/phone/onboarding/done',
    name: 'phone-onboarding-done',
    component: () => import('@/views/phone/PhoneOnboardingDoneView.vue'),
    meta: { requiresAuth: true },
  },

  {
    path:      '/admin/qa',
    name:      'AdminQA',
    component: () => import('@/views/admin/QaValidationView.vue'),
    meta:      {
      requiresAuth: true,
      portal:     'admin',
      requiresPlatformAdmin: true,
      title:      'QA Dashboard',
    },
  },
  {
    path:      '/admin',
    name:      'admin',
    component: () => import('@/views/admin/AdminDashboardView.vue'),
    meta:      {
      requiresAuth: true,
      portal:     'admin',
      requiresPlatformAdmin: true,
    },
  },
  { path: '/admin/overview', redirect: '/admin' },
  {
    path: '/admin/registration-profiles',
    name: 'admin-registration-profiles',
    component: () => import('@/views/admin/AdminRegistrationQueueView.vue'),
    meta: {
      requiresAuth: true,
      portal: 'admin',
      requiresPlatformAdmin: true,
    },
  },

  // ── Staff portal (مسارات /workshop تقنية؛ العرض للمستخدم: مركز خدمة / منفذ بيع) ──
  {
    path: '/',
    component: () => import('@/layouts/AppLayout.vue'),
    meta: { requiresAuth: true, portal: 'staff' },
    children: [
      { path: '',                    name: 'dashboard',          component: () => import('@/views/DashboardView.vue') },
      { path: 'customers',           name: 'customers',          component: () => import('@/views/customers/CustomerListView.vue') },
      {
        path:      'customers/:customerId/reports',
        name:      'customers.reports',
        component: () => import('@/views/customers/CustomerReportsPulseView.vue'),
        meta:      {
          requiresAllPermissions: ['reports.view', 'reports.operations.view'],
          title:    'لوحة العميل',
          titleEn:  'Customer pulse',
        },
      },
      {
        path:      'customers/:customerId',
        name:      'customers.profile',
        component: () => import('@/views/customers/CustomerProfileView.vue'),
        meta:      {
          requiresPermission: 'customers.view',
          title:    'مركز العميل',
          titleEn:  'Customer hub',
        },
      },
      { path: 'vehicles',            name: 'vehicles',           component: () => import('@/views/vehicles/VehicleListView.vue') },
      { path: 'vehicles/:id',        name: 'vehicles.show',      component: () => import('@/views/vehicles/VehicleShowView.vue') },
      { path: 'vehicles/:id/card',   name: 'vehicles.card',      component: () => import('@/views/vehicles/VehicleDigitalCardView.vue') },
      { path: 'vehicles/:id/passport', name: 'vehicles.passport', component: () => import('@/views/vehicles/VehiclePassportView.vue') },
      { path: 'pos',                 name: 'pos',                component: () => import('@/views/pos/POSView.vue') },
      { path: 'work-orders',         name: 'work-orders',        component: () => import('@/views/work-orders/WorkOrderListView.vue') },
      { path: 'work-orders/new',     name: 'work-orders.create', component: () => import('@/views/work-orders/WorkOrderCreateView.vue') },
      { path: 'work-orders/batch',   name: 'work-orders.batch',  component: () => import('@/views/work-orders/WorkOrderBatchCreateView.vue') },
      { path: 'work-orders/:id',     name: 'work-orders.show',   component: () => import('@/views/work-orders/WorkOrderShowView.vue') },
      { path: 'services',            name: 'services',           component: () => import('@/views/services/ServicesView.vue') },
      { path: 'bundles',             name: 'bundles',            component: () => import('@/views/services/BundlesView.vue') },
      { path: 'invoices',            name: 'invoices',           component: () => import('@/views/invoices/InvoiceListView.vue') },
      { path: 'invoices/create',     name: 'invoices.create',    component: () => import('@/views/invoices/InvoiceCreateView.vue') },
      { path: 'invoices/:id',        name: 'invoices.show',      component: () => import('@/views/invoices/InvoiceShowView.vue') },
      { path: 'invoices/:id/smart',  name: 'invoices.smart',     component: () => import('@/views/invoices/SmartInvoiceView.vue') },
      { path: 'crm/quotes',          name: 'crm.quotes',         component: () => import('@/views/crm/QuotesView.vue'), meta: { requiresAuth: true, title: 'عروض الأسعار' } },
      { path: 'crm/relations',       name: 'crm.relations',      component: () => import('@/views/crm/CustomerRelationsView.vue') },
      { path: 'products',            name: 'products',           component: () => import('@/views/products/ProductListView.vue') },
      { path: 'products/new',        name: 'products.create',    component: () => import('@/views/products/ProductFormView.vue') },
      { path: 'products/:id/edit',   name: 'products.edit',      component: () => import('@/views/products/ProductFormView.vue') },
      { path: 'inventory',           name: 'inventory',          component: () => import('@/views/inventory/InventoryView.vue') },
      { path: 'inventory/units',     name: 'inventory.units',    component: () => import('@/views/inventory/UnitsView.vue') },
      { path: 'inventory/reservations', name: 'inventory.reservations', component: () => import('@/views/inventory/ReservationsView.vue') },
      { path: 'suppliers',           name: 'suppliers',          component: () => import('@/views/suppliers/SupplierListView.vue') },
      { path: 'purchases',           name: 'purchases',          component: () => import('@/views/purchases/PurchaseListView.vue') },
      { path: 'purchases/new',       name: 'purchases.create',   component: () => import('@/views/purchases/PurchaseCreateView.vue') },
      { path: 'purchases/:id',       name: 'purchases.show',     component: () => import('@/views/purchases/PurchaseShowView.vue') },
      { path: 'purchases/:id/receive', name: 'purchases.receive', component: () => import('@/views/purchases/GoodsReceiptCreateView.vue') },
      { path: 'goods-receipts/:id',  name: 'goods-receipts.show', component: () => import('@/views/purchases/GoodsReceiptShowView.vue') },
      { path: 'reports',             name: 'reports',            component: () => import('@/views/reports/ReportsView.vue') },
      {
        path:      'operations/global-feed',
        name:      'operations.global-feed',
        component: () => import('@/views/operations/GlobalOperationsFeedView.vue'),
        meta:      {
          requiresAllPermissions: ['reports.view', 'reports.operations.view'],
          title:    'تدفق العمليات',
          titleEn:  'Global operations feed',
        },
      },
      {
        path:      'business-intelligence',
        name:      'business-intelligence',
        component: () => import('@/views/analytics/BusinessIntelligenceView.vue'),
        meta:      { staffIntelligenceBi: true },
      },
      { path: 'contracts',           name: 'contracts',          component: () => import('@/views/contracts/ContractsView.vue'), meta: { requiresManager: true } },
      {
        path:      'contracts/:contractId/catalog',
        name:      'contracts.catalog',
        component: () => import('@/views/contracts/ContractCatalogView.vue'),
        meta:      { requiresPermission: 'contracts.service_items.view', title: 'بنود العقد' },
      },
      {
        path:      'companies/:companyId',
        name:      'companies.profile',
        component: () => import('@/views/companies/CompanyProfileView.vue'),
        meta:      {
          requiresAuth: true,
          title:    'مركز الشركة',
          titleEn:  'Company hub',
        },
      },
      { path: 'settings',            name: 'settings',           component: () => import('@/views/settings/SettingsView.vue'), meta: { requiresManager: true } },
      { path: 'settings/integrations', name: 'settings.integrations', component: () => import('@/views/settings/IntegrationsView.vue'), meta: { requiresManager: true } },
      { path: 'settings/api-keys',   name: 'settings.api-keys',  component: () => import('@/views/settings/ApiKeysView.vue'), meta: { requiresPermission: 'api_keys.manage' } },
      {
        path:      'settings/org-units',
        name:      'settings.org-units',
        component: () => import('@/views/settings/OrgUnitsView.vue'),
        meta:      { requiresManager: true, requiresBusinessFeature: 'org_structure' },
      },
      { path: 'settings/team-users', name: 'settings.team-users', component: () => import('@/views/settings/TeamUsersView.vue'), meta: { requiresManager: true } },
      { path: 'branches',            name: 'branches',           component: () => import('@/views/branches/BranchesView.vue'), meta: { requiresManager: true } },
      /** عرض الخريطة لجميع موظفي مركز الخدمة؛ التعديل يبقى من صفحة الفروع (مدير/مالك). */
      { path: 'branches/map',        name: 'branches.map',       component: () => import('@/views/branches/BranchesMapView.vue') },
      { path: 'profile',             name: 'profile',            component: () => import('@/views/profile/ProfileView.vue') },
      {
        path:      'account/sessions',
        name:      'account.sessions',
        component: () => import('@/views/account/AuthSessionsView.vue'),
        meta:      { requiresAuth: true, title: 'الأجهزة والجلسات' },
      },
      { path: 'about/deployment',    name: 'about.deployment',   component: () => import('@/views/AboutDeploymentView.vue') },
      { path: 'about/taxonomy',       name: 'about.taxonomy',     component: () => import('@/views/about/PlatformTaxonomyView.vue') },
      {
        path:      'about/capabilities',
        name:      'about.capabilities',
        component: () => import('@/views/about/SystemCapabilitiesView.vue'),
        meta:      { title: 'قدرات النظام', titleEn: 'System capabilities' },
      },
      // Wallet
      { path: 'wallet',              name: 'wallet',             component: () => import('@/views/wallet/WalletView.vue') },
      {
        path:      'wallet/top-up-requests',
        name:      'wallet.top-up-requests',
        component: () => import('@/views/wallet/WalletTopUpRequestsView.vue'),
        meta:      {
          requiresAnyPermission: [
            'wallet.top_up_requests.create',
            'wallet.top_up_requests.view',
            'wallet.top_up_requests.review',
          ],
          title: 'طلبات شحن الرصيد',
        },
      },
      { path: 'wallet/transactions', redirect: { name: 'wallet' } },
      {
        path:      'wallet/transactions/:walletId',
        name:      'wallet-transactions',
        component: () => import('@/views/wallet/WalletTransactionsView.vue'),
      },
      // Financial Core
      { path: 'ledger',              name: 'ledger',             component: () => import('@/views/ledger/LedgerView.vue') },
      { path: 'ledger/:id',          name: 'ledger.show',        component: () => import('@/views/ledger/LedgerEntryView.vue') },
      { path: 'chart-of-accounts',   name: 'chart-of-accounts',  component: () => import('@/views/ledger/ChartOfAccountsView.vue') },
      { path: 'fixed-assets',        name: 'fixed-assets',       component: () => import('@/views/ledger/FixedAssetsView.vue') },
      // Fleet Wallet (جانب مركز الخدمة / المنفذ)
      { path: 'fleet/wallet',        name: 'fleet.wallet',       component: () => import('@/views/fleet/FleetWalletView.vue') },
      { path: 'fleet/verify-plate',  name: 'fleet.verify-plate', component: () => import('@/views/fleet/PlateVerificationView.vue') },
      { path: 'fleet/transactions/:walletId', name: 'fleet.transactions', component: () => import('@/views/fleet/WalletTransactionsView.vue') },
      // Governance
      { path: 'governance',          name: 'governance',         component: () => import('@/views/governance/GovernanceView.vue') },
      {
        path:      'financial-reconciliation',
        name:      'financial-reconciliation',
        component: () => import('@/views/finance/FinancialReconciliationView.vue'),
        meta:      { requiresPermission: 'reports.financial.view' },
      },
      {
        path:      'meetings',
        name:      'meetings',
        component: () => import('@/views/meetings/MeetingsView.vue'),
        meta:      { requiresPermission: 'meetings.update' },
      },
      {
        path:      'internal/intelligence',
        name:      'internal.intelligence',
        component: () => import('@/views/internal/IntelligenceCommandCenterView.vue'),
        meta:      { intelligenceCommandCenter: true },
      },
      { path: 'support',             name: 'support',            component: () => import('@/views/support/SupportView.vue') },
      { path: 'zatca',               name: 'zatca',              component: () => import('@/views/zatca/ZatcaView.vue'), meta: { requiresManager: true } },
      // HR & تشغيل الموظفين (مسار URL: workshop)
      { path: 'workshop/employees',  name: 'workshop.employees', component: () => import('@/views/workshop/EmployeesView.vue') },
      { path: 'workshop/tasks',      name: 'workshop.tasks',     component: () => import('@/views/workshop/TasksView.vue') },
      { path: 'workshop/attendance', name: 'workshop.attendance', component: () => import('@/views/workshop/AttendanceView.vue') },
      { path: 'workshop/commissions', name: 'workshop.commissions', component: () => import('@/views/workshop/CommissionsView.vue') },
      { path: 'workshop/leaves',     name: 'workshop.leaves',     component: () => import('@/views/workshop/LeavesView.vue') },
      { path: 'workshop/salaries',   name: 'workshop.salaries',   component: () => import('@/views/workshop/SalariesView.vue') },
      {
        path:      'workshop/hr-comms',
        name:      'workshop.hr-comms',
        component: () => import('@/views/workshop/HrCommunicationsView.vue'),
        meta:      {
          titleAr:    'الاتصالات الإدارية',
          subtitleAr: 'واجهة أولية لمسار المعاملات الإدارية؛ التكامل الكامل مع البريد والإشعارات يُوسَّع حسب خارطة الطريق.',
          bullets:    ['مسودات نشرات للموظفين', 'سجل زمني للمعاملات', 'تجميع ملاحظات الفروع'],
        },
      },
      {
        path:      'workshop/hr-archive',
        name:      'workshop.hr-archive',
        component: () => import('@/views/archive/ElectronicArchiveView.vue'),
        meta:      { electronicArchive: true },
      },
      {
        path:      'workshop/hr-signatures',
        name:      'workshop.hr-signatures',
        component: () => import('@/views/workshop/ElectronicSignatureView.vue'),
        meta:      {
          unavailablePreview: true,
          titleAr:    'التوقيع الإلكتروني',
          subtitleAr: 'مسار حالات المستند (مسودة / مرسل / موقّع)؛ الربط بمزوّد توقيع معتمد يُضاف عند الترخيص.',
          bullets:    ['حالات واضحة للمستند', 'تذكيرات يدوية أو آلية حسب الإعداد', 'أرشفة PDF'],
        },
      },
      {
        path:      'workshop/wage-protection',
        name:      'workshop.wage-protection',
        component: () => import('@/views/hr/WageProtectionView.vue'),
        meta:      { featureInactive: true },
      },
      { path: 'workshop/commission-policies', name: 'workshop.commission-policies', component: () => import('@/views/workshop/CommissionPoliciesView.vue') },
      {
        path:      'compliance/labor-law',
        name:      'compliance.labor-law',
        component: () => import('@/views/compliance/LaborLawLibraryView.vue'),
        meta:      { featureInactive: true },
      },
      { path: 'documents', redirect: { name: 'documents.company' } },
      { path: 'documents/company', name: 'documents.company', component: () => import('@/views/documents/CompanyDocumentsView.vue') },
      {
        path:      'electronic-archive',
        name:      'electronic-archive',
        component: () => import('@/views/archive/ElectronicArchiveView.vue'),
        meta:      { electronicArchive: true },
      },
      // Bays & Bookings
      { path: 'bays',                name: 'bays',               component: () => import('@/views/bays/BaysView.vue') },
      { path: 'bays/heatmap',        name: 'bays.heatmap',       component: () => import('@/views/bays/HeatmapView.vue') },
      { path: 'bookings',            name: 'bookings',           component: () => import('@/views/bookings/BookingsView.vue') },
      // SaaS
      { path: 'plans',               name: 'plans',              component: () => import('@/views/saas/PlansView.vue') },
      { path: 'subscription',        name: 'subscription',       component: () => import('@/views/saas/SubscriptionView.vue') },
      {
        path: 'fuel',
        name: 'fuel',
        redirect: (to) => ({ name: 'access-denied', query: { reason: 'inactive', from: to.fullPath } }),
      },
      // Referrals & Loyalty
      { path: 'referrals',           name: 'referrals',          component: () => import('@/views/referrals/ReferralsView.vue') },
      // AI Plugins Marketplace
      { path: 'plugins',             name: 'plugins',            component: () => import('@/views/plugins/PluginMarketplaceView.vue') },
      { path: 'activity',            name: 'activity',           component: () => import('@/views/ActivityLogView.vue') },
      {
        path:      'access-denied',
        name:      'access-denied',
        component: () => import('@/views/auth/AccessDeniedView.vue'),
        meta:      { requiresAuth: true, title: 'لا صلاحية', titleEn: 'Access denied' },
      },
    ],
  },

  // ── Fleet Portal ──
  {
    path: '/fleet-portal',
    component: () => import('@/layouts/FleetLayout.vue'),
    meta: { requiresAuth: true, portal: 'fleet' },
    children: [
      { path: '',        name: 'fleet-portal',           component: () => import('@/views/fleet-portal/FleetPortalDashboardView.vue') },
      { path: 'new-order', name: 'fleet-portal.new-order', component: () => import('@/views/fleet-portal/FleetNewOrderView.vue') },
      { path: 'top-up',  name: 'fleet-portal.top-up',   component: () => import('@/views/fleet-portal/FleetTopUpView.vue') },
      { path: 'vehicles', name: 'fleet-portal.vehicles', component: () => import('@/views/fleet-portal/FleetVehiclesView.vue') },
      { path: 'orders',  name: 'fleet-portal.orders',   component: () => import('@/views/fleet-portal/FleetOrdersView.vue') },
    ],
  },

  // ── Customer Portal ──
  {
    path: '/customer',
    component: () => import('@/layouts/CustomerLayout.vue'),
    meta: { requiresAuth: true, portal: 'customer' },
    children: [
      { path: '',          name: 'customer',           redirect: '/customer/dashboard' },
      { path: 'dashboard', name: 'customer.dashboard', component: () => import('@/views/customer/CustomerDashboardView.vue') },
      { path: 'bookings',  name: 'customer.bookings',  component: () => import('@/views/customer/CustomerBookingsView.vue') },
      { path: 'vehicles',  name: 'customer.vehicles',  component: () => import('@/views/customer/CustomerVehiclesView.vue') },
      { path: 'invoices',  name: 'customer.invoices',  component: () => import('@/views/customer/CustomerInvoicesView.vue') },
      { path: 'wallet',    name: 'customer.wallet',    component: () => import('@/views/customer/CustomerWalletView.vue') },
      { path: 'notifications', name: 'customer.notifications', component: () => import('@/views/customer/CustomerNotificationsView.vue') },
    ],
  },

  {
    path: '/landing',
    name: 'landing',
    component: () => import('@/views/marketing/LandingView.vue'),
    alias: ['/asas-pro', '/asaspro'],
    meta: { publicPage: true /** صفحة عامة — لا يوجد حارس في beforeEach يعتمد على هذا المفتاح حالياً */ },
  },
  {
    path: '/v/:token',
    name: 'vehicle-identity-public',
    component: () => import('@/views/public/VehicleIdentityPublicView.vue'),
    meta: { publicPage: true },
  },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@/views/NotFoundView.vue') },
]

const router = createRouter({
  history: createWebHistory(resolveHistoryBase()),
  routes,
})

/**
 * مسارات بوابة «فريق العمل» (المستأجر): المحاسبة، الفواتير، العملاء، الموظفون، إلخ.
 * يُسمح بها لمشغّل المنصة حتى بدون company_id حتى لا يُحبَس في /admin فقط
 * (رابط «العودة للتطبيق» وروابط الإدارة).
 */
function isStaffTenantAppExplorationPath(path: string): boolean {
  const raw = path.length > 1 && path.endsWith('/') ? path.slice(0, -1) : path
  if (raw === '' || raw === '/') return true
  const prefixes = [
    '/invoices',
    '/ledger',
    '/chart-of-accounts',
    '/fixed-assets',
    '/reports',
    '/business-intelligence',
    '/customers',
    '/workshop',
    '/work-orders',
    '/pos',
    '/operations',
    '/purchases',
    '/products',
    '/inventory',
    '/suppliers',
    '/wallet',
    '/zatca',
    '/crm',
    '/settings',
    '/integrations',
    '/bays',
    '/bookings',
    '/services',
    '/vehicles',
    '/dashboard',
    '/internal',
  ]
  return prefixes.some((pre) => raw === pre || raw.startsWith(`${pre}/`))
}

async function ensureStaffBusinessProfileForShell(): Promise<void> {
  const auth = useAuthStore()
  const biz = useBusinessProfileStore()
  if (!auth.isAuthenticated) return
  if (!auth.isStaff || auth.isFleet || auth.isCustomer || auth.isPhoneOnboarding) return
  if (typeof auth.user?.company_id !== 'number' || auth.user.company_id <= 0) return
  if (biz.loaded) return
  await biz.load().catch(() => {})
}

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  const sub  = useSubscriptionStore()
  const theme = useTheme()

  if (auth.token) {
    const u = auth.user
    const missingProfile = !u
    const missingBilling =
      !!u &&
      typeof u.company_id === 'number' &&
      u.company_id > 0 &&
      (u.subscription === undefined || u.subscription === null)
    if (missingProfile || missingBilling) {
      await auth.fetchMe().catch(() => {})
    }
  }

  if (auth.isAuthenticated) {
    await ensureStaffBusinessProfileForShell()
    theme.loadCompanyTheme().catch(() => {})
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.requiresPlatformAdmin === true && auth.isAuthenticated && !auth.isPlatform) {
    return { path: '/dashboard' }
  }

  if (auth.isAuthenticated && auth.isPlatform && to.meta.portal === 'staff' && to.name !== 'access-denied') {
    const u = auth.user
    const hasTenantAnchor = typeof u?.company_id === 'number' && u.company_id > 0
    if (!hasTenantAnchor) {
      const p = to.path
      const allowStaffPath =
        p.startsWith('/about')
        || p.startsWith('/profile')
        || p.startsWith('/account/')
      const allowTenantApp = isStaffTenantAppExplorationPath(p)
      if (!allowStaffPath && !allowTenantApp) {
        return { path: '/admin' }
      }
    }
  }

  if (auth.isAuthenticated && String(auth.user?.role ?? '') === 'phone_onboarding' && !to.path.startsWith('/phone')) {
    if (to.meta.publicPage === true) {
      return true
    }
    return { path: '/phone/onboarding' }
  }

  if (to.meta.guest && auth.isAuthenticated) {
    return { path: auth.portalHome }
  }

  if (to.name === 'access-denied') {
    if (to.meta.requiresAuth && !auth.isAuthenticated) {
      return { name: 'login', query: { redirect: to.fullPath } }
    }
    return true
  }

  const deny = (reason: 'manager' | 'owner' | 'permission' | 'feature' | 'portal' | 'preview' | 'inactive') => {
    const from = to.fullPath.length > 512 ? to.fullPath.slice(0, 512) : to.fullPath
    return { name: 'access-denied' as const, query: { reason, from } }
  }

  if (to.meta.unavailablePreview === true) {
    return deny('preview')
  }

  if (to.meta.featureInactive === true) {
    return deny('inactive')
  }

  if (to.meta.electronicArchive === true && !featureFlags.electronicArchive) {
    return deny('feature')
  }

  if (to.meta.requiresManager && !auth.isManager) {
    return deny('manager')
  }

  async function ensureBusinessProfileLoaded() {
    const biz = useBusinessProfileStore()
    if (!auth.isAuthenticated || !auth.isStaff || !auth.user?.company_id) return biz
    if (!biz.loaded) await biz.load().catch(() => {})
    return biz
  }

  const bizProfile = await ensureBusinessProfileLoaded()
  const bfKey = to.meta.requiresBusinessFeature
  if (typeof bfKey === 'string' && bfKey.length > 0 && auth.isStaff) {
    if (!auth.isOwner && !bizProfile.isEnabled(bfKey)) {
      return deny('feature')
    }
  }

  const perm = to.meta.requiresPermission
  if (typeof perm === 'string' && perm.length > 0 && !auth.hasPermission(perm)) {
    return deny('permission')
  }

  const anyPerms = to.meta.requiresAnyPermission
  if (Array.isArray(anyPerms) && anyPerms.length > 0) {
    const ok = anyPerms.some((p) => auth.hasPermission(p))
    if (!ok) return deny('permission')
  }

  const allPerms = to.meta.requiresAllPermissions
  if (Array.isArray(allPerms) && allPerms.length > 0) {
    const okAll = allPerms.every((p) => auth.hasPermission(p))
    if (!okAll) return deny('permission')
  }

  if (to.meta.requiresOwner && !auth.isOwner) {
    return deny('owner')
  }

  if (to.meta.staffIntelligenceBi === true && auth.isStaff) {
    if (
      !canAccessStaffBusinessIntelligence({
        buildFlagOn: featureFlags.intelligenceCommandCenter,
        isOwner: auth.isOwner,
        isEnabled: (k) => bizProfile.isEnabled(k),
      })
    ) {
      return deny('feature')
    }
  }

  if (to.meta.intelligenceCommandCenter === true && auth.isStaff) {
    if (
      !canAccessStaffCommandCenter({
        buildFlagOn: featureFlags.intelligenceCommandCenter,
        isOwner: auth.isOwner,
        isEnabled: (k) => bizProfile.isEnabled(k),
        hasIntelligenceReportPermission: auth.hasPermission('reports.intelligence.view'),
      })
    ) {
      if (
        featureFlags.intelligenceCommandCenter &&
        (auth.isOwner || bizProfile.isEnabled('intelligence')) &&
        !auth.hasPermission('reports.intelligence.view')
      ) {
        return deny('permission')
      }
      return deny('feature')
    }
  }

  if (auth.isAuthenticated && auth.isStaff && to.path.startsWith('/workshop')) {
    if (!canAccessWorkshopArea(auth.isOwner, (k) => bizProfile.isEnabled(k))) {
      return deny('feature')
    }
  }

  if (auth.isAuthenticated && auth.isStaff) {
    const p = to.path
    const needsOperationsArea =
      p.startsWith('/bays') || p.startsWith('/bookings') || p.startsWith('/meetings')
    if (needsOperationsArea && !canAccessStaffOperationsArea(auth.isOwner, (k) => bizProfile.isEnabled(k))) {
      return deny('feature')
    }
  }

  // بوابات اختيارية معطّلة في البناء — لا تُعرَض ولا تُحمَّل لأدوارها
  if (auth.isAuthenticated && auth.isFleet && !enabledPortals.fleet) {
    if (to.name === 'login') return true
    return { name: 'login', query: { notice: 'portal_disabled', portal: 'fleet' } }
  }
  if (auth.isAuthenticated && auth.isCustomer && !enabledPortals.customer) {
    if (to.name === 'login') return true
    return { name: 'login', query: { notice: 'portal_disabled', portal: 'customer' } }
  }
  if (auth.isAuthenticated && auth.isStaff) {
    if (to.path.startsWith('/fleet-portal') && !enabledPortals.fleet) {
      return deny('portal')
    }
    if (to.path.startsWith('/customer') && !enabledPortals.customer) {
      return deny('portal')
    }
    if (to.path.startsWith('/admin') && !enabledPortals.admin) {
      return deny('portal')
    }
  }

  // load subscription for staff
  if (auth.isAuthenticated && auth.isStaff && !sub.loaded) {
    sub.loadSubscription().catch(() => {})
  }

  // Fleet users stay in /fleet-portal
  if (to.meta.requiresAuth && auth.isFleet && !to.path.startsWith('/fleet-portal')) {
    return { path: '/fleet-portal' }
  }

  // Customer users stay in /customer
  if (to.meta.requiresAuth && auth.isCustomer && !to.path.startsWith('/customer')) {
    return { path: '/customer' }
  }
})

router.afterEach((to) => {
  if (
    !to.name ||
    to.name === 'login' ||
    to.name === 'platform-login' ||
    to.name === 'forgot-password' ||
    to.name === 'reset-password' ||
    to.name === 'not-found' ||
    to.name === 'landing' ||
    to.name === 'vehicle-identity-public' ||
    to.name === 'access-denied'
  )
    return
  if (to.path.startsWith('/fleet-portal') || to.path.startsWith('/customer')) return
  logActivity('زيارة صفحة', String(to.name))
})

export default router
