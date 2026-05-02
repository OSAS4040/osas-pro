import type { RouteRecordRaw } from 'vue-router'
import { platformPathFromAdminHash } from '@/config/platformAdminNav'

export const platformAdminRoutes: RouteRecordRaw[] = [
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
    path: '/admin/registration-profiles',
    name: 'admin-registration-profiles',
    component: () => import('@/views/admin/AdminRegistrationQueueView.vue'),
    meta: {
      requiresAuth: true,
      portal: 'admin',
      requiresPlatformAdmin: true,
    },
  },
  {
    path: '/admin/companies/:id(\\d+)',
    redirect: (to) => ({ path: `/platform/companies/${String(to.params.id)}`, replace: true }),
    meta: {
      requiresAuth: true,
      portal: 'admin',
      requiresPlatformAdmin: true,
    },
  },
  {
    path: '/admin/subscriptions',
    component: () => import('@/layouts/PlatformAdminLayout.vue'),
    meta: {
      requiresAuth: true,
      portal: 'admin',
      requiresPlatformAdmin: true,
      platformSubscriptionOps: true,
    },
    children: [
      {
        path: '',
        component: () => import('@/modules/subscriptions/layouts/AdminSubscriptionsShellLayout.vue'),
        meta: {
          requiresAuth: true,
          portal: 'admin',
          requiresPlatformAdmin: true,
          platformSubscriptionOps: true,
        },
        children: [
          {
            path: 'list',
            name: 'admin-subscriptions-list',
            component: () => import('@/modules/subscriptions/pages/AdminSubscriptionsListPage.vue'),
            meta: { requiresAuth: true, portal: 'admin', requiresPlatformAdmin: true, platformSubscriptionOps: true },
          },
          {
            path: 'control',
            name: 'admin-subscriptions-control',
            component: () => import('@/modules/subscriptions/pages/AdminSubscriptionsControlPage.vue'),
            meta: { requiresAuth: true, portal: 'admin', requiresPlatformAdmin: true, platformSubscriptionOps: true },
          },
          {
            path: 'transactions',
            name: 'admin-subscriptions-transactions',
            component: () => import('@/modules/subscriptions/pages/AdminSubscriptionsTransactionsPage.vue'),
            meta: { requiresAuth: true, portal: 'admin', requiresPlatformAdmin: true, platformSubscriptionOps: true },
          },
          {
            path: 'wallets',
            name: 'admin-subscriptions-wallets',
            component: () => import('@/modules/subscriptions/pages/AdminSubscriptionsWalletMonitorPage.vue'),
            meta: { requiresAuth: true, portal: 'admin', requiresPlatformAdmin: true, platformSubscriptionOps: true },
          },
          {
            path: 'invoices/:invoiceId(\\d+)',
            name: 'admin-subscriptions-invoice-detail',
            component: () => import('@/modules/subscriptions/pages/AdminInvoiceDetailPage.vue'),
            meta: { requiresAuth: true, portal: 'admin', requiresPlatformAdmin: true, platformSubscriptionOps: true },
          },
          {
            path: 'invoices',
            name: 'admin-subscriptions-invoices',
            component: () => import('@/modules/subscriptions/pages/AdminSubscriptionsInvoicesPage.vue'),
            meta: { requiresAuth: true, portal: 'admin', requiresPlatformAdmin: true, platformSubscriptionOps: true },
          },
          {
            path: 'payment-orders/:id(\\d+)',
            name: 'admin-subscriptions-payment-order',
            component: () => import('@/modules/subscriptions/pages/AdminPaymentOrderDetailPage.vue'),
            meta: { requiresAuth: true, portal: 'admin', requiresPlatformAdmin: true, platformSubscriptionOps: true },
          },
          {
            path: 'bank-transactions/:id(\\d+)',
            name: 'admin-subscriptions-bank-tx',
            component: () => import('@/modules/subscriptions/pages/AdminBankTransactionDetailPage.vue'),
            meta: { requiresAuth: true, portal: 'admin', requiresPlatformAdmin: true, platformSubscriptionOps: true },
          },
          {
            path: ':subscriptionId(\\d+)',
            name: 'admin-subscriptions-detail',
            component: () => import('@/modules/subscriptions/pages/AdminSubscriptionDetailPage.vue'),
            meta: { requiresAuth: true, portal: 'admin', requiresPlatformAdmin: true, platformSubscriptionOps: true },
          },
          {
            path: '',
            name: 'admin-subscriptions-review',
            component: () => import('@/modules/subscriptions/pages/AdminSubscriptionsReviewQueuePage.vue'),
            meta: { requiresAuth: true, portal: 'admin', requiresPlatformAdmin: true, platformSubscriptionOps: true },
          },
        ],
      },
    ],
  },
  {
    path: '/admin',
    name: 'admin-legacy',
    redirect: (to) => ({ path: platformPathFromAdminHash(to.hash || ''), replace: true }),
  },
  { path: '/admin/overview', redirect: '/platform/overview' },
  {
    path: '/platform',
    component: () => import('@/layouts/PlatformAdminLayout.vue'),
    meta: {
      requiresAuth: true,
      portal: 'admin',
      requiresPlatformAdmin: true,
    },
    children: [
      { path: '', redirect: { name: 'platform-overview' } },
      {
        path: 'overview',
        name: 'platform-overview',
        component: () => import('@/views/platform/PlatformOverviewView.vue'),
      },
      {
        path: 'governance',
        name: 'platform-governance',
        component: () => import('@/views/platform/PlatformGovernanceView.vue'),
      },
      {
        path: 'ops',
        name: 'platform-ops',
        component: () => import('@/views/platform/PlatformOpsView.vue'),
      },
      {
        path: 'companies',
        name: 'platform-companies',
        component: () => import('@/views/platform/PlatformCompaniesView.vue'),
      },
      {
        path: 'companies/:id',
        name: 'platform-company-detail',
        component: () => import('@/views/platform/PlatformCompanyDetailView.vue'),
      },
      {
        path: 'customers',
        name: 'platform-customers',
        component: () => import('@/views/platform/PlatformCustomersView.vue'),
      },
      {
        path: 'plans',
        name: 'platform-plans',
        component: () => import('@/views/platform/PlatformPlansView.vue'),
      },
      {
        path: 'operator-commands',
        name: 'platform-operator-commands',
        component: () => import('@/views/platform/PlatformOperatorCommandsView.vue'),
      },
      {
        path: 'audit',
        name: 'platform-audit',
        component: () => import('@/views/platform/PlatformAuditView.vue'),
      },
      {
        path: 'finance',
        name: 'platform-finance',
        component: () => import('@/views/platform/PlatformFinanceView.vue'),
      },
      {
        path: 'cancellations',
        name: 'platform-cancellations',
        component: () => import('@/views/platform/PlatformCancellationsView.vue'),
      },
      {
        path: 'support',
        name: 'platform-support',
        component: () => import('@/views/platform/PlatformSupportView.vue'),
      },
      {
        path: 'announcements',
        name: 'platform-announcements',
        component: () => import('@/views/platform/PlatformAnnouncementsView.vue'),
      },
      {
        path: 'intelligence/incidents',
        name: 'platform-incidents',
        component: () => import('@/views/platform/PlatformIncidentCenterView.vue'),
      },
      {
        path: 'intelligence/command',
        name: 'platform-intelligence-command',
        component: () => import('@/views/platform/PlatformCommandSurfaceView.vue'),
      },
      {
        path: 'notifications',
        name: 'platform-notifications',
        component: () => import('@/views/platform/PlatformNotificationsView.vue'),
      },
      {
        path: 'intelligence/incidents/:incidentKey',
        name: 'platform-incident-detail',
        component: () => import('@/views/platform/PlatformIncidentDetailView.vue'),
      },
      { path: 'purchase-claims', name: 'platform-purchase-claims', component: () => import('@/views/platform/PlatformPurchaseClaimsView.vue') },
      {
        path: 'providers/new',
        name: 'platform-providers-new',
        component: () => import('@/views/platform/pricing/PlatformServiceProvidersView.vue'),
      },
      {
        path: 'providers/costs',
        name: 'platform-provider-costs',
        component: () => import('@/views/platform/pricing/PlatformProviderCostsView.vue'),
      },
      {
        path: 'providers',
        name: 'platform-providers-list',
        component: () => import('@/views/platform/pricing/PlatformServiceProvidersView.vue'),
      },
      {
        path: 'pricing/requests/:uuid',
        name: 'platform-pricing-request-detail',
        component: () => import('@/views/platform/pricing/PlatformPricingRequestDetailView.vue'),
      },
      {
        path: 'pricing/requests',
        name: 'platform-pricing-requests',
        component: () => import('@/views/platform/pricing/PlatformPricingRequestsView.vue'),
      },
      {
        path: 'pricing/review',
        name: 'platform-pricing-review',
        component: () => import('@/views/platform/pricing/PlatformPricingRequestsView.vue'),
      },
      {
        path: 'pricing/approve',
        name: 'platform-pricing-approve',
        component: () => import('@/views/platform/pricing/PlatformPricingRequestsView.vue'),
      },
      {
        path: 'pricing/price-lists',
        name: 'platform-pricing-catalogs',
        component: () => import('@/views/platform/pricing/PlatformPriceListsInfoView.vue'),
      },
      {
        path: 'pricing/customer-prices',
        name: 'platform-pricing-customer-prices',
        component: () => import('@/views/platform/pricing/PlatformCustomerPriceVersionsView.vue'),
      },
      {
        path: 'pricing/price-activation',
        name: 'platform-pricing-price-activation',
        component: () => import('@/views/platform/pricing/PlatformCustomerPriceVersionsView.vue'),
      },
      {
        path: 'contracts',
        name: 'platform-contracts',
        component: () => import('@/views/platform/pricing/PlatformContractsBridgeView.vue'),
      },
      {
        path: 'reports',
        name: 'platform-reports',
        component: () => import('@/views/platform/pricing/PlatformCommercialPulseView.vue'),
      },
    ],
  },
]
