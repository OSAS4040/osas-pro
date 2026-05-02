import type { RouteRecordRaw } from 'vue-router'

export const staffPortalRoutes: RouteRecordRaw[] = [
  /**
   * Staff portal — قائمة المسارات يجب أن تبقى متوافقة مع
   * `docs/Tenant_Navigation_API_Map.md` (كتل nav-doc-route-anchors*).
   * تحقق: من `frontend` شغّل `npm run docs:nav-api-check` (يستخرج من هذا الملف).
   */
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
      /** نقطة البيع غير معروضة — أي زيارة لـ /pos تُحوَّل للرئيسية */
      { path: 'pos', redirect: '/' },
      { path: 'work-orders',         name: 'work-orders',        component: () => import('@/views/work-orders/WorkOrderListView.vue') },
      {
        path:      'execution-hub',
        name:      'execution-hub',
        component: () => import('@/views/execution/ProviderExecutionHubView.vue'),
        meta:      { requiresAuth: true, title: 'تنفيذ العمليات', titleEn: 'Operations execution' },
      },
      {
        path:      'provider/platform-purchases',
        name:      'provider.platform-purchases',
        component: () => import('@/views/provider/ProviderPlatformPurchasesView.vue'),
        meta:      { requiresAuth: true, title: 'مشتريات المنصّة', titleEn: 'Platform purchases' },
      },
      {
        path:      'provider/purchase-claims',
        name:      'provider.purchase-claims',
        component: () => import('@/views/provider/ProviderPurchaseClaimsView.vue'),
        meta:      { requiresAuth: true, title: 'صرف المستحقات', titleEn: 'Payout claims' },
      },
      { path: 'work-orders/new',     name: 'work-orders.create', redirect: '/work-orders' },
      { path: 'work-orders/batch',   name: 'work-orders.batch',  redirect: '/work-orders' },
      { path: 'work-orders/:id',     name: 'work-orders.show',   component: () => import('@/views/work-orders/WorkOrderShowView.vue') },
      {
        path:      'services-products',
        component: () => import('@/views/catalog/ServicesProductsHubView.vue'),
        meta:      { requiresAuth: true, title: 'الخدمات والمنتجات', titleEn: 'Services & products' },
        children:  [
          { path: '', name: 'catalog', redirect: { name: 'catalog.services' } },
          {
            path:      'products',
            name:      'catalog.products',
            component: () => import('@/views/products/ProductListView.vue'),
          },
          {
            path:      'services',
            name:      'catalog.services',
            component: () => import('@/views/services/ServicesView.vue'),
          },
        ],
      },
      { path: 'products', redirect: (to) => ({ name: 'catalog.products', query: to.query, hash: to.hash }) },
      { path: 'services', redirect: (to) => ({ name: 'catalog.services', query: to.query, hash: to.hash }) },
      { path: 'bundles',             name: 'bundles',            component: () => import('@/views/services/BundlesView.vue') },
      { path: 'invoices',            name: 'invoices',           component: () => import('@/views/invoices/InvoiceListView.vue') },
      { path: 'invoices/create',     name: 'invoices.create',    component: () => import('@/views/invoices/InvoiceCreateView.vue') },
      { path: 'invoices/:id',        name: 'invoices.show',      component: () => import('@/views/invoices/InvoiceShowView.vue') },
      { path: 'invoices/:id/smart',  name: 'invoices.smart',     component: () => import('@/views/invoices/SmartInvoiceView.vue') },
      { path: 'crm/quotes',          name: 'crm.quotes',         component: () => import('@/views/crm/QuotesView.vue'), meta: { requiresAuth: true, title: 'عروض الأسعار' } },
      { path: 'crm/relations',       name: 'crm.relations',      component: () => import('@/views/crm/CustomerRelationsView.vue') },
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
      { path: 'wallet',              name: 'wallet',             component: () => import('@/views/wallet/WalletRouteView.vue') },
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
      {
        path:      'fixed-assets',
        name:      'fixed-assets',
        component: () => import('@/views/ledger/FixedAssetsView.vue'),
        meta:      {
          requiresBusinessFeature: 'fixed_assets',
          requiresPermission:      'reports.accounting.view',
          title:                   'الأصول الثابتة',
        },
      },
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
      { path: 'plans',               name: 'plans',              component: () => import('@/modules/subscriptions/pages/ClientPlansPage.vue') },
      { path: 'subscription',        name: 'subscription',       component: () => import('@/modules/subscriptions/pages/ClientSubscriptionOverviewPage.vue') },
      { path: 'subscription/plans',  name: 'subscription.plans', component: () => import('@/modules/subscriptions/pages/ClientPlansPage.vue') },
      { path: 'subscription/payment', name: 'subscription.payment', component: () => import('@/modules/subscriptions/pages/ClientPaymentPage.vue') },
      { path: 'subscription/invoices', name: 'subscription.invoices', component: () => import('@/modules/subscriptions/pages/ClientInvoicesPage.vue') },
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
]
