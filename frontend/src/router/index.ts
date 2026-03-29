import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useSubscriptionStore } from '@/stores/subscription'
import { featureFlags } from '@/config/featureFlags'

const routes: RouteRecordRaw[] = [
  // ── Auth — صفحة دخول موحّدة لجميع البوابات ──
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/auth/LoginView.vue'),
    meta: { guest: true },
  },
  // Redirect الروابط القديمة للصفحة الموحّدة
  { path: '/fleet/login',    redirect: '/login' },
  { path: '/customer/login', redirect: '/login' },

  // ── Staff / Workshop Portal ──
  {
    path: '/',
    component: () => import('@/layouts/AppLayout.vue'),
    meta: { requiresAuth: true, portal: 'staff' },
    children: [
      { path: '',                    name: 'dashboard',          component: () => import('@/views/DashboardView.vue') },
      { path: 'customers',           name: 'customers',          component: () => import('@/views/customers/CustomerListView.vue') },
      { path: 'vehicles',            name: 'vehicles',           component: () => import('@/views/vehicles/VehicleListView.vue') },
      { path: 'vehicles/:id',        name: 'vehicles.show',      component: () => import('@/views/vehicles/VehicleShowView.vue') },
      { path: 'vehicles/:id/card',   name: 'vehicles.card',      component: () => import('@/views/vehicles/VehicleDigitalCardView.vue') },
      { path: 'vehicles/:id/passport', name: 'vehicles.passport', component: () => import('@/views/vehicles/VehiclePassportView.vue') },
      { path: 'pos',                 name: 'pos',                component: () => import('@/views/pos/POSView.vue') },
      { path: 'work-orders',         name: 'work-orders',        component: () => import('@/views/work-orders/WorkOrderListView.vue') },
      { path: 'work-orders/new',     name: 'work-orders.create', component: () => import('@/views/work-orders/WorkOrderCreateView.vue') },
      { path: 'work-orders/:id',     name: 'work-orders.show',   component: () => import('@/views/work-orders/WorkOrderShowView.vue') },
      { path: 'services',            name: 'services',           component: () => import('@/views/services/ServicesView.vue') },
      { path: 'bundles',             name: 'bundles',            component: () => import('@/views/services/BundlesView.vue') },
      { path: 'invoices',            name: 'invoices',           component: () => import('@/views/invoices/InvoiceListView.vue') },
      { path: 'invoices/create',     name: 'invoices.create',    component: () => import('@/views/invoices/InvoiceCreateView.vue') },
      { path: 'invoices/:id',        name: 'invoices.show',      component: () => import('@/views/invoices/InvoiceShowView.vue') },
      { path: 'invoices/:id/smart',  name: 'invoices.smart',     component: () => import('@/views/invoices/SmartInvoiceView.vue') },
      { path: 'crm/quotes',          name: 'crm.quotes',         component: () => import('@/views/crm/QuotesView.vue'), meta: { requiresAuth: true, title: 'عروض الأسعار' } },
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
      { path: 'contracts',           name: 'contracts',          component: () => import('@/views/contracts/ContractsView.vue'), meta: { requiresManager: true } },
      { path: 'settings',            name: 'settings',           component: () => import('@/views/settings/SettingsView.vue'), meta: { requiresManager: true } },
      { path: 'settings/integrations', name: 'settings.integrations', component: () => import('@/views/settings/IntegrationsView.vue'), meta: { requiresManager: true } },
      { path: 'profile',             name: 'profile',            component: () => import('@/views/profile/ProfileView.vue') },
      // Wallet
      { path: 'wallet',              name: 'wallet',             component: () => import('@/views/wallet/WalletView.vue') },
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
      // Fleet Wallet (Workshop Side)
      { path: 'fleet/wallet',        name: 'fleet.wallet',       component: () => import('@/views/fleet/FleetWalletView.vue') },
      { path: 'fleet/verify-plate',  name: 'fleet.verify-plate', component: () => import('@/views/fleet/PlateVerificationView.vue') },
      { path: 'fleet/transactions/:walletId', name: 'fleet.transactions', component: () => import('@/views/fleet/WalletTransactionsView.vue') },
      // Governance
      { path: 'governance',          name: 'governance',         component: () => import('@/views/governance/GovernanceView.vue') },
      {
        path:      'internal/intelligence',
        name:      'internal.intelligence',
        component: () => import('@/views/internal/IntelligenceCommandCenterView.vue'),
        meta:      { requiresManager: true, intelligenceCommandCenter: true },
      },
      { path: 'support',             name: 'support',            component: () => import('@/views/support/SupportView.vue') },
      { path: 'zatca',               name: 'zatca',              component: () => import('@/views/zatca/ZatcaView.vue'), meta: { requiresManager: true } },
      { path: 'admin',               name: 'admin',              component: () => import('@/views/admin/AdminDashboardView.vue'), meta: { requiresOwner: true } },
      // Workshop
      { path: 'workshop/employees',  name: 'workshop.employees', component: () => import('@/views/workshop/EmployeesView.vue') },
      { path: 'workshop/tasks',      name: 'workshop.tasks',     component: () => import('@/views/workshop/TasksView.vue') },
      { path: 'workshop/attendance', name: 'workshop.attendance', component: () => import('@/views/workshop/AttendanceView.vue') },
      { path: 'workshop/commissions', name: 'workshop.commissions', component: () => import('@/views/workshop/CommissionsView.vue') },
      { path: 'workshop/leaves',     name: 'workshop.leaves',     component: () => import('@/views/workshop/LeavesView.vue') },
      { path: 'workshop/salaries',   name: 'workshop.salaries',   component: () => import('@/views/workshop/SalariesView.vue') },
      // Bays & Bookings
      { path: 'bays',                name: 'bays',               component: () => import('@/views/bays/BaysView.vue') },
      { path: 'bays/heatmap',        name: 'bays.heatmap',       component: () => import('@/views/bays/HeatmapView.vue') },
      { path: 'bookings',            name: 'bookings',           component: () => import('@/views/bookings/BookingsView.vue') },
      // SaaS
      { path: 'plans',               name: 'plans',              component: () => import('@/views/saas/PlansView.vue') },
      { path: 'subscription',        name: 'subscription',       component: () => import('@/views/saas/SubscriptionView.vue') },
      // Fuel
      { path: 'fuel',                name: 'fuel',               component: () => import('@/views/fuel/FuelView.vue') },
      // Referrals & Loyalty
      { path: 'referrals',           name: 'referrals',          component: () => import('@/views/referrals/ReferralsView.vue') },
      // AI Plugins Marketplace
      { path: 'plugins',             name: 'plugins',            component: () => import('@/views/plugins/PluginMarketplaceView.vue') },
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
      { path: 'notifications', name: 'customer.notifications', component: () => import('@/views/customer/CustomerDashboardView.vue') },
    ],
  },

  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@/views/NotFoundView.vue') },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  const sub  = useSubscriptionStore()

  if (auth.token && !auth.user) {
    await auth.fetchMe().catch(() => {})
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guest && auth.isAuthenticated) {
    return { path: auth.portalHome }
  }

  if (to.meta.requiresManager && !auth.isManager) {
    return { name: 'dashboard' }
  }

  if (to.meta.intelligenceCommandCenter && !featureFlags.intelligenceCommandCenter) {
    return { name: 'dashboard' }
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

export default router
