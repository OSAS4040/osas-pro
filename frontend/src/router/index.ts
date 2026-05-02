import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { isCustomerNavHidden, isStaffNavHidden } from '@/lib/staffNavKey'
import { useSubscriptionStore } from '@/stores/subscription'
import { featureFlags } from '@/config/featureFlags'
import {
  canAccessStaffBusinessIntelligence,
  canAccessStaffCommandCenter,
  canAccessStaffOperationsArea,
  canAccessWorkshopArea,
} from '@/config/staffFeatureGate'
import { useBusinessProfileStore } from '@/stores/businessProfile'
import { applyWalletTopUpReviewerNavOverride, mergeStaffHiddenNavKeys } from '@/config/staffProviderFocusNav'
import { mergeExecutionPartnerNavKeys } from '@/config/executionPartnerNav'
import { platformExecutionPartnerActiveFromStore } from '@/composables/usePlatformExecutionPartner'
import { enabledPortals } from '@/config/portalAccess'
import { logActivity } from '@/composables/useActivityLog'
import { useTheme } from '@/composables/useTheme'
import { platformAdminNavItems } from '@/config/platformAdminNav'
import { authPhoneGuestRoutes } from './routes/authPhoneGuestRoutes'
import { platformAdminRoutes } from './routes/platformAdminRoutes'
import { staffPortalRoutes } from './routes/staffPortalRoutes'
import { fleetPortalRoutes } from './routes/fleetPortalRoutes'
import { customerPortalRoutes } from './routes/customerPortalRoutes'
import { publicPortalRoutes } from './routes/publicPortalRoutes'
import {
  PLATFORM_ROUTE_EXTRA_ANY_PERMISSIONS,
  PLATFORM_SECTION_ANY_PERMISSIONS,
  canAccessWithAnyPermission,
} from '@/config/platformSectionGate'
import { isTenantShellQuery } from '@/config/platformOperationsHandoff'
/** ضمان تطابق Vue Router مع Vite حتى لا يصبح base ‎./‎ أو فارغًا في بعض البيئات. */
function resolveHistoryBase(): string {
  const raw = import.meta.env.BASE_URL
  if (typeof raw !== 'string' || raw === '' || raw === '.' || raw === './') {
    return '/'
  }
  return raw
}

/**
 * وجهة الزائر غير المسجّل عند طلب `/` (جذر التطبيق).
 * - `landing`: الصفحة التعريفية — مناسب للإنتاج والتجريب العام دون إظهار لوحة التشغيل.
 * - `login`: السلوك السابق (متابعة الحارس العادي → دخول مزوّد الخدمة).
 *
 * `VITE_GUEST_ROOT_REDIRECT=landing` أو `login`. إن وُضع غير صالح يُ ignored.
 * عند عدم الضبط: **build إنتاجي** → landing، **تطوير (vite dev)** → login لتسريع اختبار فريق العمل.
 *
 * لا يؤثر على المستخدمين المسجّلين؛ عزل البوابات (عميل / أسطول / منصة) يبقى لاحقاً في هذا الملف.
 */
function resolveGuestRootRedirectTarget(): 'landing' | 'login' {
  const raw = String(import.meta.env.VITE_GUEST_ROOT_REDIRECT ?? '').trim().toLowerCase()
  if (raw === 'landing' || raw === 'login') {
    return raw
  }
  return import.meta.env.PROD ? 'landing' : 'login'
}

function mapLegacyPathToCustomerPortal(path: string): string | null {
  if (path === '/customers' || path.startsWith('/customers/')) return '/customer/dashboard'
  if (path === '/branches' || path.startsWith('/branches/')) return '/customer/coverage-locations'
  if (path === '/work-orders' || path.startsWith('/work-orders/')) return '/customer/work-orders'
  if (path === '/bookings' || path.startsWith('/bookings/')) return '/customer/bookings'
  if (path === '/vehicles' || path.startsWith('/vehicles/')) return '/customer/vehicles'
  if (path === '/invoices' || path.startsWith('/invoices/')) return '/customer/invoices'
  if (path === '/wallet' || path.startsWith('/wallet/')) return '/customer/wallet'
  if (path === '/support' || path.startsWith('/support/')) return '/customer/notifications'
  if (path === '/settings' || path.startsWith('/settings/')) return '/customer/settings'
  if (path === '/reports' || path.startsWith('/reports/')) return '/customer/reports'
  if (path === '/business-intelligence' || path.startsWith('/business-intelligence/')) return '/customer/business-intelligence'
  if (path === '/plans' || path.startsWith('/plans/')) return '/customer/plans'
  if (path === '/subscription' || path.startsWith('/subscription/')) return '/customer/subscription'
  if (path === '/activity' || path.startsWith('/activity/')) return '/customer/activity'
  if (path === '/zatca' || path.startsWith('/zatca/')) return '/customer/zatca'
  return null
}

const routes: RouteRecordRaw[] = [
  ...authPhoneGuestRoutes,
  ...platformAdminRoutes,
  ...staffPortalRoutes,
  ...fleetPortalRoutes,
  ...customerPortalRoutes,
  ...publicPortalRoutes,
]

const router = createRouter({
  history: createWebHistory(resolveHistoryBase()),
  routes,
})

const LOGIN_ROUTE_NAMES = new Set(['login', 'customer-login', 'platform-login'])

/**
 * مسارات بوابة «فريق العمل» (المستأجر): المحاسبة، الفواتير، العملاء، الموظفون، إلخ.
 * يُسمح بها لمشغّل المنصة حتى بدون company_id حتى لا يُحبَس في /admin فقط
 * (رابط «العودة للتطبيق» وروابط الإدارة).
 */
function isStaffTenantAppExplorationPath(path: string): boolean {
  const raw = path.length > 1 && path.endsWith('/') ? path.slice(0, -1) : path
  if (raw === '' || raw === '/') return true
  const prefixes = [
    '/about',
    '/access-denied',
    '/account',
    '/activity',
    '/bays',
    '/bookings',
    '/branches',
    '/bundles',
    '/business-intelligence',
    '/chart-of-accounts',
    '/companies',
    '/compliance',
    '/contracts',
    '/crm',
    '/customers',
    '/dashboard',
    '/documents',
    '/execution-hub',
    '/electronic-archive',
    '/financial-reconciliation',
    '/fixed-assets',
    '/fleet',
    '/goods-receipts',
    '/governance',
    '/integrations',
    '/internal',
    '/inventory',
    '/invoices',
    '/ledger',
    '/meetings',
    '/operations',
    '/plans',
    '/plugins',
    '/pos',
    '/products',
    '/profile',
    '/purchases',
    '/referrals',
    '/reports',
    '/services',
    '/services-products',
    '/settings',
    '/subscription',
    '/suppliers',
    '/support',
    '/vehicles',
    '/wallet',
    '/work-orders',
    '/workshop',
    '/zatca',
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
  const accountKind = String(auth.accountContext?.principal_kind ?? '')
  const guardHint = String(auth.accountContext?.guard_hint ?? '')

  // يوجد token في التخزين لكن user لم يُحمَّل بعد (تحديث كامل للصفحة، أو تجهيز E2E): يجب جلب /auth/me
  // قبل حارس المسارات القديمة؛ وإلا يُفسَّر «غير مصدَّق» ويُحوَّل الفني من /work-orders إلى دخول العميل.
  if (auth.token && !auth.user) {
    await auth.fetchMe().catch(() => {})
  }

  // توحيد بوابة العميل: أي مسار عميل قديم ينتقل إلى /customer/*.
  const customerPortalTarget = mapLegacyPathToCustomerPortal(to.path)
  if (customerPortalTarget) {
    if (!auth.isAuthenticated) {
      return { name: 'customer-login', query: { redirect: customerPortalTarget } }
    }
    if (!auth.isStaff || auth.isCustomer) {
      return { path: customerPortalTarget }
    }
  }

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

  if (
    !auth.isAuthenticated
    && resolveGuestRootRedirectTarget() === 'landing'
    && to.path === '/'
  ) {
    return { path: '/landing' }
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    const targetPortal = String(to.meta.portal ?? '')
    if (targetPortal === 'fleet') {
      return { name: 'login', query: { redirect: to.fullPath } }
    }
    if (targetPortal === 'customer') {
      return { name: 'customer-login', query: { redirect: to.fullPath } }
    }
    if (targetPortal === 'admin') {
      return { name: 'platform-login', query: { redirect: to.fullPath } }
    }
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  // Hard isolation by backend account context (stronger than role-only checks).
  if (to.meta.requiresAuth && auth.isAuthenticated) {
    if ((accountKind === 'customer_user' || guardHint === 'customer') && !to.path.startsWith('/customer')) {
      return { path: '/customer/dashboard' }
    }
    if ((accountKind === 'customer_user' || guardHint === 'fleet') && auth.isFleet && !to.path.startsWith('/fleet-portal')) {
      return { path: '/fleet-portal' }
    }
    if (accountKind === 'tenant_user' && (to.path.startsWith('/customer') || to.path.startsWith('/fleet-portal'))) {
      return { path: '/' }
    }
  }

  if (to.meta.requiresPlatformAdmin === true && auth.isAuthenticated && !auth.isPlatform) {
    return { path: '/dashboard' }
  }

  /**
   * مشغّل منصة مرتبط بشركة قد يُوجَّه خطأً إلى `/` عبر home_route_hint أو إشارات مرجعية؛
   * نسمح بالرئيسية التشغيلية فقط عند `?shell=tenant` (من «العودة لفريق العمل» في لوحة المنصة).
   */
  if (
    auth.isAuthenticated &&
    auth.isPlatform &&
    to.path === '/' &&
    !isTenantShellQuery(to.query as Record<string, unknown>)
  ) {
    return { path: '/platform/overview' }
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
        return { path: '/platform/overview' }
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
    // Root fix: keep login pages renderable even with stale tokens/session.
    // This avoids blank screens caused by forced redirect to heavy async routes.
    if (typeof to.name === 'string' && LOGIN_ROUTE_NAMES.has(to.name)) {
      return true
    }
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

  if (
    auth.isAuthenticated &&
    to.matched.some((r) => r.meta.platformSubscriptionOps === true) &&
    !auth.hasPermission('platform.subscription.manage')
  ) {
    return deny('permission')
  }

  /** عزل أقسام إدارة المنصّة — يطابق تصفية الشريط الجانبي في {@link PlatformAdminLayout} */
  if (auth.isAuthenticated && auth.isPlatform && to.path.startsWith('/platform') && typeof to.name === 'string') {
    const n = to.name
    const extra = PLATFORM_ROUTE_EXTRA_ANY_PERMISSIONS[n]
    if (extra && !canAccessWithAnyPermission(auth.hasPermission, extra)) {
      return deny('permission')
    }
    const main = platformAdminNavItems.find((i) => i.routeName === n)
    if (main) {
      const keys = PLATFORM_SECTION_ANY_PERMISSIONS[main.id]
      if (keys && !canAccessWithAnyPermission(auth.hasPermission, keys)) {
        return deny('permission')
      }
    }
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
    if (to.name === 'customer-login') return true
    return { name: 'customer-login', query: { notice: 'portal_disabled', portal: 'customer' } }
  }
  if (auth.isAuthenticated && auth.isStaff) {
    if (to.path.startsWith('/fleet-portal') && !enabledPortals.fleet) {
      return deny('portal')
    }
    if (to.path.startsWith('/customer') && !enabledPortals.customer) {
      return deny('portal')
    }
    if ((to.path.startsWith('/admin') || to.path.startsWith('/platform')) && !enabledPortals.admin) {
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

  // Staff tenant users are isolated from customer portal paths.
  if (
    to.meta.requiresAuth
    && auth.isStaff
    && !auth.isFleet
    && !auth.isCustomer
    && !auth.isPlatform
    && to.path.startsWith('/customer')
  ) {
    return { path: '/' }
  }

  // Platform users stay within platform/admin surfaces.
  if (to.meta.requiresAuth && auth.isPlatform && to.path.startsWith('/customer')) {
    return { path: '/platform/overview' }
  }

  if (
    auth.isAuthenticated &&
    auth.isStaff &&
    !auth.isFleet &&
    !auth.isCustomer &&
    !auth.isPhoneOnboarding &&
    to.meta.requiresAuth
  ) {
    const bizProfile = useBusinessProfileStore()
    const hidden = applyWalletTopUpReviewerNavOverride(
      mergeExecutionPartnerNavKeys(
        mergeStaffHiddenNavKeys(
          auth.user?.hidden_staff_nav_keys,
          bizProfile.businessType,
          bizProfile.loaded,
          auth.isPlatform,
        ),
        platformExecutionPartnerActiveFromStore(),
      ),
      auth.user?.hidden_staff_nav_keys,
      (p) => auth.hasPermission(p),
    )
    if (Array.isArray(hidden) && hidden.length > 0) {
      const p = to.path
      const skip =
        to.name === 'access-denied'
        || p.startsWith('/phone')
        || p.startsWith('/platform')
        || p.startsWith('/admin')
      if (!skip && isStaffNavHidden(to.path, to.hash, new Set(hidden))) {
        return { name: 'access-denied', query: { reason: 'feature', from: to.fullPath } }
      }
    }
  }

  if (auth.isAuthenticated && auth.isCustomer && to.path.startsWith('/customer')) {
    const hidden = auth.user?.hidden_customer_nav_keys
    if (
      Array.isArray(hidden)
      && hidden.length > 0
      && to.name !== 'access-denied'
      && isCustomerNavHidden(to.path, new Set(hidden))
    ) {
      return { name: 'access-denied', query: { reason: 'feature', from: to.fullPath } }
    }
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

router.onError((error) => {
  const msg = String((error as Error)?.message ?? '')
  const isViteChunkGlitch =
    msg.includes('Failed to fetch dynamically imported module')
    || msg.includes('Outdated Optimize Dep')
  if (!isViteChunkGlitch) return
  if (typeof window === 'undefined') return
  const onceKey = '__vite_chunk_retry_once__'
  if (sessionStorage.getItem(onceKey) === '1') return
  sessionStorage.setItem(onceKey, '1')
  window.location.reload()
})

export default router
