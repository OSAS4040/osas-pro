<template>
  <div
    data-testid="platform-admin-root"
    class="flex min-h-screen text-slate-900 transition-colors dark:text-white"
    :dir="locale.langInfo.value.dir"
  >
    <!-- خلفية شفافة للموبايل عند فتح القائمة الجانبية -->
    <button
      v-if="mobileDrawerOpen && !isLgUp"
      type="button"
      class="fixed inset-0 z-40 cursor-default border-0 bg-black/45 backdrop-blur-[1px] lg:hidden"
      aria-label="إغلاق القائمة"
      @click="mobileDrawerOpen = false"
    />

    <aside
      id="platform-admin-sidebar"
      class="flex w-64 shrink-0 flex-col overflow-hidden border-l border-[color:var(--border-color)] bg-[color:var(--bg-sidebar)] shadow-[0_0_0_1px_rgba(13,148,136,0.06)] transition-[transform] duration-200 ease-out dark:shadow-none lg:relative lg:z-auto lg:max-w-none lg:translate-x-0 max-lg:fixed max-lg:inset-y-0 max-lg:right-0 max-lg:z-[45] max-lg:max-w-[min(100vw,16rem)] xl:w-[17rem]"
      :class="mobileAsideTransformClass"
      :inert="sidebarInert"
      aria-label="قائمة إدارة المنصة"
    >
      <div
        class="sticky top-0 z-[1] flex min-h-[3.25rem] shrink-0 items-center gap-2.5 border-b border-[color:var(--border-color)] bg-[color:var(--bg-header)] px-3 py-2.5 dark:border-slate-700/80"
      >
        <div
          class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-primary-500 to-primary-700 text-center text-[9px] font-extrabold leading-tight text-white shadow-sm ring-1 ring-primary-400/40"
          aria-hidden="true"
        >
          {{ locale.t('app.name') }}
        </div>
        <p class="min-w-0 flex-1 text-sm font-bold leading-snug text-[color:var(--text-primary)] dark:text-slate-100">
          {{ locale.t('platformAdmin.sidebarTitle') }}
        </p>
      </div>
      <nav class="flex flex-1 flex-col gap-2 overflow-y-auto p-2.5 pb-4">
        <div
          class="sticky top-0 z-[1] -mx-0.5 mb-1 border-b border-[color:var(--border-color)] bg-[color:var(--bg-sidebar)]/95 px-0.5 pb-2 backdrop-blur-sm dark:border-slate-700/80"
        >
          <div class="relative">
            <MagnifyingGlassIcon
              class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-slate-500"
              aria-hidden="true"
            />
            <input
              ref="sidebarSearchInputRef"
              v-model="sidebarNavQuery"
              type="search"
              autocomplete="off"
              enterkeyhint="search"
              class="w-full rounded-xl border border-gray-200 bg-gray-50/90 py-2 pr-9 pl-9 text-sm text-gray-800 placeholder:text-gray-400 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/25 dark:border-slate-600 dark:bg-slate-900/50 dark:text-slate-100 dark:placeholder:text-slate-500"
              :placeholder="locale.t('platformAdmin.searchPlaceholder')"
              :aria-label="locale.t('platformAdmin.searchPlaceholder')"
              :title="locale.t('platformAdmin.searchShortcutsHint')"
              :dir="locale.langInfo.value.dir"
              @keydown.escape="sidebarNavQuery = ''"
            />
            <button
              v-if="sidebarNavQuery.trim().length > 0"
              type="button"
              class="absolute left-1.5 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-slate-800 dark:hover:text-slate-200"
              :aria-label="locale.t('platformAdmin.clearSearch')"
              @click="sidebarNavQuery = ''"
            >
              <XMarkIcon class="h-4 w-4" aria-hidden="true" />
            </button>
          </div>
        </div>

        <PlatformAdminNavSection
          v-for="group in visibleSidebarGroups"
          :key="'grp-' + group.id"
          :label="group.title"
          :expanded="isSidebarGroupExpanded(group.id)"
          :force-expand="sidebarNavQueryActive"
          @toggle="() => toggleSidebarGroup(group.id)"
        >
          <RouterLink
            v-for="item in filteredNavItemsForGroup(group)"
            :key="item.id"
            :to="{ name: item.routeName }"
            :class="platformNavLinkClass(isActiveRoute(item.routeName))"
          >
            <component :is="item.icon" class="h-4 w-4 shrink-0 opacity-95" aria-hidden="true" />
            <span class="flex-1 leading-snug">{{ item.label }}</span>
          </RouterLink>
        </PlatformAdminNavSection>

        <p
          v-if="sidebarNavQueryActive && !sidebarNavHasAnyMatch"
          class="rounded-xl border border-dashed border-gray-200 px-2 py-3 text-center text-[11px] text-gray-500 dark:border-slate-600 dark:text-slate-400"
        >
          {{ locale.t('platformAdmin.noNavResults') }}
        </p>

        <PlatformAdminNavSection
          v-if="commerceSidebarSectionVisible"
          :label="locale.t('platformAdmin.sectionCommerce')"
          :expanded="commerceSectionOpen"
          :force-expand="sidebarNavQueryActive"
          @toggle="toggleCommerceSection"
        >
          <RouterLink
            v-if="subscriptionCardVisible"
            to="/admin/subscriptions"
            :class="platformNavLinkClass(isSubscriptionOpsRoute)"
          >
            <ClipboardDocumentCheckIcon class="h-4 w-4 shrink-0 opacity-95" aria-hidden="true" />
            <span class="flex-1 leading-snug">طلبات الاشتراكات</span>
            <span
              v-if="subscriptionBadgeCount > 0"
              class="min-w-[1.35rem] rounded-full bg-rose-600 px-1.5 py-0.5 text-center text-[10px] font-extrabold leading-none text-white shadow"
              :class="subscriptionBadgeCount >= 10 ? 'ring-2 ring-rose-300/90 dark:ring-rose-400/50' : ''"
            >
              {{ subscriptionBadgeCount > 99 ? '99+' : subscriptionBadgeCount }}
            </span>
          </RouterLink>
          <template v-if="providerCardVisible">
            <RouterLink
              v-for="link in providerLinksFiltered"
              :key="'pv-' + link.routeName"
              :to="{ name: link.routeName }"
              :class="platformNavLinkClass(isActiveRoute(link.routeName))"
            >
              <TruckIcon class="h-4 w-4 shrink-0 opacity-95" aria-hidden="true" />
              <span class="flex-1 leading-snug">{{ link.label }}</span>
            </RouterLink>
          </template>
          <template v-if="commercialCardVisible">
            <RouterLink
              v-for="link in commercialLinksFiltered"
              :key="'cp-' + link.routeName"
              :to="{ name: link.routeName }"
              :class="platformNavLinkClass(isActiveRoute(link.routeName))"
            >
              <CurrencyDollarIcon class="h-4 w-4 shrink-0 opacity-95" aria-hidden="true" />
              <span class="flex-1 leading-snug">{{ link.label }}</span>
            </RouterLink>
          </template>
          <RouterLink
            v-if="contractsCardVisible"
            :to="{ name: 'platform-contracts' }"
            :class="platformNavLinkClass(isActiveRoute('platform-contracts'))"
          >
            <DocumentTextIcon class="h-4 w-4 shrink-0 opacity-95" aria-hidden="true" />
            <span class="flex-1 leading-snug">العقود</span>
          </RouterLink>
          <RouterLink
            v-if="reportsCardVisible"
            :to="{ name: 'platform-reports' }"
            :class="platformNavLinkClass(isActiveRoute('platform-reports'))"
          >
            <ChartBarIcon class="h-4 w-4 shrink-0 opacity-95" aria-hidden="true" />
            <span class="flex-1 leading-snug">التقارير</span>
          </RouterLink>
        </PlatformAdminNavSection>

        <PlatformAdminNavSection
          v-if="staffPortalCardVisible"
          :label="locale.t('platformAdmin.sectionStaff')"
          :expanded="staffSectionOpen"
          :force-expand="sidebarNavQueryActive"
          @toggle="toggleStaffSection"
        >
          <RouterLink
            v-for="link in staffPortalLinksFiltered"
            :key="'sp-' + link.to"
            :to="link.to"
            :title="PLATFORM_OPERATIONS_EXIT_TOOLTIP"
            :aria-label="platformOperationsExitAriaLabel(link.label)"
            :class="platformNavLinkClass(isActiveStaffPath(link.to), 'compact')"
          >
            <component :is="link.icon" class="h-3.5 w-3.5 shrink-0 opacity-95" aria-hidden="true" />
            <span class="flex-1 leading-snug">{{ link.label }}</span>
          </RouterLink>
        </PlatformAdminNavSection>

        <PlatformAdminNavSection
          v-if="toolsSidebarSectionVisible"
          :label="locale.t('platformAdmin.sectionTools')"
          :expanded="toolsSectionOpen"
          :force-expand="sidebarNavQueryActive"
          @toggle="toggleToolsSection"
        >
          <RouterLink
            v-if="registrationCardVisible"
            to="/admin/registration-profiles"
            :title="PLATFORM_OPERATIONS_EXIT_TOOLTIP"
            :aria-label="platformOperationsExitAriaLabel('طلبات التسجيل (مراجعات)')"
            :class="platformNavLinkClass(isActiveStaffPath('/admin/registration-profiles'))"
          >
            <span class="flex-1 leading-snug">طلبات التسجيل (مراجعات)</span>
          </RouterLink>
          <RouterLink
            v-if="qaCardVisible"
            to="/admin/qa"
            :title="PLATFORM_OPERATIONS_EXIT_TOOLTIP"
            :aria-label="platformOperationsExitAriaLabel('اختبار النظام وضمان الجودة')"
            :class="platformNavLinkClass(isActiveStaffPath('/admin/qa'))"
          >
            <BeakerIcon class="h-4 w-4 shrink-0 opacity-95" aria-hidden="true" />
            <span class="flex-1 leading-snug">اختبار النظام (ضمان الجودة)</span>
          </RouterLink>
          <RouterLink
            v-if="taxonomyCardVisible"
            to="/about/taxonomy"
            :title="PLATFORM_OPERATIONS_EXIT_TOOLTIP"
            :aria-label="platformOperationsExitAriaLabel('مسرد المفاهيم')"
            :class="platformNavLinkClass(isActiveStaffPath('/about/taxonomy'))"
          >
            <span class="flex-1 leading-snug">مسرد المفاهيم</span>
          </RouterLink>
        </PlatformAdminNavSection>
      </nav>
      <div class="shrink-0 border-t border-[color:var(--border-color)] bg-[color:var(--bg-sidebar)] p-2 dark:border-slate-800">
        <RouterLink
          :to="tenantStaffHomeRoute()"
          :title="PLATFORM_OPERATIONS_EXIT_TOOLTIP"
          :aria-label="platformOperationsExitAriaLabel(locale.t('platformAdmin.footerLinkStaff'))"
          :class="platformNavLinkClass(isActiveStaffPath('/'))"
        >
          <HomeIcon class="h-4 w-4 shrink-0 opacity-95" aria-hidden="true" />
          <span class="flex-1 leading-snug">{{ locale.t('platformAdmin.footerLinkStaff') }}</span>
        </RouterLink>
      </div>
    </aside>

    <div class="relative flex min-h-screen min-w-0 flex-1 flex-col overflow-hidden">
      <a
        href="#platform-admin-main"
        class="sr-only start-4 top-20 z-[60] rounded-lg border border-primary-300 bg-white px-3 py-2 text-sm font-semibold text-primary-800 shadow-lg outline-none ring-2 ring-primary-500/30 focus:not-sr-only focus:absolute dark:border-primary-700 dark:bg-slate-900 dark:text-primary-100"
      >
        {{ locale.t('platformAdmin.skipToContent') }}
      </a>
      <header
        class="sticky top-0 h-16 border-b border-[color:var(--border-color)] bg-white/90 px-4 backdrop-blur-md dark:border-slate-700 dark:bg-[color:var(--bg-header)]/95 lg:px-6"
        :class="mobileDrawerOpen && !isLgUp ? 'z-[50]' : 'z-10'"
      >
        <div class="mx-auto flex h-full max-w-[1600px] items-center justify-between gap-3">
          <div class="flex min-w-0 flex-1 items-center gap-2">
            <button
              id="platform-admin-mobile-menu-trigger"
              type="button"
              class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-800 shadow-sm transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800 lg:hidden"
              :aria-expanded="mobileDrawerOpen"
              aria-controls="platform-admin-sidebar"
              aria-label="فتح قائمة إدارة المنصة أو إغلاقها"
              @click="mobileDrawerOpen = !mobileDrawerOpen"
            >
              <Bars3Icon class="h-6 w-6" aria-hidden="true" />
            </button>
            <nav class="min-w-0 flex-1 text-[11px] text-slate-500 dark:text-slate-400" aria-label="مسار التنقّل">
              <ol class="flex flex-wrap items-center gap-1">
                <li>
                  <RouterLink :to="{ name: 'platform-overview' }" class="font-semibold text-primary-700 hover:underline dark:text-primary-400">
                    {{ locale.t('platformAdmin.breadcrumbRoot') }}
                  </RouterLink>
                </li>
                <li v-if="breadcrumbCurrent" class="flex items-center gap-1">
                  <span aria-hidden="true" class="px-0.5 text-slate-400" dir="ltr">/</span>
                  <span class="font-medium text-slate-800 dark:text-slate-200">{{ breadcrumbCurrent }}</span>
                </li>
              </ol>
            </nav>
          </div>
          <div class="flex shrink-0 items-center gap-2">
            <PlatformNotificationsBell />
            <PlatformOperationsExitLink :to="tenantStaffHomeRoute()" v-bind="{ ariaName: 'فريق العمل' }" variant="toolbar" class="lg:hidden">
              فريق العمل
            </PlatformOperationsExitLink>
          </div>
        </div>
      </header>

      <main
        id="platform-admin-main"
        data-testid="platform-admin-main"
        tabindex="-1"
        class="flex-1 scroll-mt-16 overflow-auto p-4 outline-none transition-colors md:p-6"
        style="background-color: var(--bg-base);"
      >
        <router-view v-slot="{ Component }">
          <component :is="Component" :key="routeKey" />
        </router-view>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, provide, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import {
  BeakerIcon,
  HomeIcon,
  DocumentTextIcon,
  BanknotesIcon,
  ClipboardDocumentListIcon,
  ChartBarIcon,
  PresentationChartLineIcon,
  UsersIcon,
  UserGroupIcon,
  ClipboardDocumentCheckIcon,
  TruckIcon,
  CurrencyDollarIcon,
  MagnifyingGlassIcon,
  XMarkIcon,
  MagnifyingGlassCircleIcon,
  Bars3Icon,
} from '@heroicons/vue/24/outline'
import {
  platformAdminNavItems,
  platformAdminSidebarGroups,
  platformProviderSidebarLinks,
  platformCommercialPricingSidebarLinks,
  platformContractsAndReportsSidebarLinks,
  type PlatformAdminNavItem,
  type PlatformAdminSidebarGroup,
  type PlatformAdminSectionId,
} from '@/config/platformAdminNav'
import { canAccessPlatformSection } from '@/config/platformSectionGate'
import {
  PLATFORM_OPERATIONS_EXIT_TOOLTIP,
  platformOperationsExitAriaLabel,
  tenantStaffHomeRoute,
} from '@/config/platformOperationsHandoff'
import PlatformAdminNavSection from '@/components/platform-admin/PlatformAdminNavSection.vue'
import PlatformOperationsExitLink from '@/components/platform-admin/PlatformOperationsExitLink.vue'
import PlatformNotificationsBell from '@/components/platform-admin/PlatformNotificationsBell.vue'
import { useLocale } from '@/composables/useLocale'
import { useAuthStore } from '@/stores/auth'
import { usePlatformSubscriptionAttention } from '@/composables/platform-admin/usePlatformSubscriptionAttention'
import { platformSubscriptionAttentionKey } from '@/components/platform-admin/PlatformSubscriptionAttentionInjectKey'

const SIDEBAR_COLLAPSE_LS = 'platform_admin_sidebar_collapsed_groups'
const SECTION_EXPAND_LS = 'platform_admin_extra_sections_v1'

function readInitialExtraSections(): { c: boolean; s: boolean; t: boolean } {
  const defaults = { c: true, s: true, t: false }
  if (typeof window === 'undefined') return defaults
  try {
    const raw = localStorage.getItem(SECTION_EXPAND_LS)
    if (!raw) return defaults
    const o = JSON.parse(raw) as { c?: boolean; s?: boolean; t?: boolean }
    return {
      c: typeof o.c === 'boolean' ? o.c : defaults.c,
      s: typeof o.s === 'boolean' ? o.s : defaults.s,
      t: typeof o.t === 'boolean' ? o.t : defaults.t,
    }
  } catch {
    return defaults
  }
}

function readIsLgUp(): boolean {
  if (typeof window === 'undefined') return true
  return window.matchMedia('(min-width: 1024px)').matches
}

const locale = useLocale()
const route = useRoute()
const auth = useAuthStore()

const _extraInit = readInitialExtraSections()
const commerceSectionOpen = ref(_extraInit.c)
const staffSectionOpen = ref(_extraInit.s)
const toolsSectionOpen = ref(_extraInit.t)

function isToolsRouteActive(): boolean {
  const p = route.path
  return (
    p.startsWith('/admin/registration-profiles')
    || p.startsWith('/admin/qa')
    || p.startsWith('/about/taxonomy')
  )
}

function toggleCommerceSection(): void {
  if (sidebarNavQueryActive.value) return
  commerceSectionOpen.value = !commerceSectionOpen.value
}

function toggleStaffSection(): void {
  if (sidebarNavQueryActive.value) return
  staffSectionOpen.value = !staffSectionOpen.value
}

function toggleToolsSection(): void {
  if (sidebarNavQueryActive.value) return
  toolsSectionOpen.value = !toolsSectionOpen.value
}

const commerceSidebarSectionVisible = computed(() => {
  if (!sidebarNavQueryActive.value) {
    return (
      subscriptionCardVisible.value
      || providerCardVisible.value
      || commercialCardVisible.value
      || contractsCardVisible.value
      || reportsCardVisible.value
    )
  }
  return (
    subscriptionCardVisible.value
    || providerLinksFiltered.value.length > 0
    || commercialLinksFiltered.value.length > 0
    || contractsCardVisible.value
    || reportsCardVisible.value
  )
})

const toolsSidebarSectionVisible = computed(() => {
  if (!sidebarNavQueryActive.value) return true
  return registrationCardVisible.value || qaCardVisible.value || taxonomyCardVisible.value
})

/** درج القائمة على الشاشات الصغيرة — لا يؤثر على md+ حيث يبقى الشريط في التدفق */
const mobileDrawerOpen = ref(false)
const isLgUp = ref(readIsLgUp())

const mobileAsideTransformClass = computed(() =>
  isLgUp.value ? '' : mobileDrawerOpen.value ? 'max-lg:translate-x-0' : 'max-lg:translate-x-full',
)

const sidebarInert = computed(() => !isLgUp.value && !mobileDrawerOpen.value)
const subscriptionAttention = usePlatformSubscriptionAttention({})
const subscriptionBadgeCount = subscriptionAttention.badgeCount

/** يُستبدَل مسار الرئيسية لمشغّل المنصّة في computed أدناه بـ `/?shell=tenant`. */
const staffPortalLinks = [
  { to: '/', label: 'لوحة التحكم', icon: HomeIcon },
  { to: '/execution-hub', label: 'تنفيذ العمليات', icon: MagnifyingGlassCircleIcon },
  { to: '/provider/platform-purchases', label: 'مشتريات المنصّة', icon: TruckIcon },
  { to: '/provider/purchase-claims', label: 'صرف المستحقات', icon: CurrencyDollarIcon },
  { to: '/invoices', label: 'الفواتير', icon: DocumentTextIcon },
  { to: '/ledger', label: 'دفتر الأستاذ', icon: BanknotesIcon },
  { to: '/chart-of-accounts', label: 'دليل الحسابات', icon: ClipboardDocumentListIcon },
  { to: '/reports', label: 'التقارير', icon: ChartBarIcon },
  { to: '/business-intelligence', label: 'ذكاء الأعمال', icon: PresentationChartLineIcon },
  { to: '/customers', label: 'العملاء', icon: UsersIcon },
  { to: '/workshop/employees', label: 'الموظفون', icon: UserGroupIcon },
]

/** روابط التسعير حسب RBAC: المراجعة لمن يملك review، الاعتماد/التفعيل لمن يملك approve */
const commercialPricingSidebarLinksFiltered = computed(() =>
  platformCommercialPricingSidebarLinks.filter((link) => {
    switch (link.routeName) {
      case 'platform-pricing-review':
        return auth.hasPermission('platform.pricing.review')
      case 'platform-pricing-approve':
      case 'platform-pricing-price-activation':
        return auth.hasPermission('platform.pricing.approve')
      case 'platform-pricing-requests':
        return auth.hasPermission('platform.pricing.view') || auth.hasPermission('platform.pricing.create')
      default:
        return auth.hasPermission('platform.pricing.view')
    }
  }),
)

const sidebarNavQuery = ref('')
const sidebarSearchInputRef = ref<HTMLInputElement | null>(null)
const collapsedSidebarGroupIds = ref<string[]>([])

const sidebarNavQueryActive = computed(() => sidebarNavQuery.value.trim().length > 0)

function textMatchesNavQuery(haystack: string, needle: string): boolean {
  const n = needle.trim().toLowerCase()
  if (!n) return true
  return haystack.toLowerCase().includes(n)
}

function itemMatchesNavQuery(item: PlatformAdminNavItem, needle: string): boolean {
  return (
    textMatchesNavQuery(item.label, needle) ||
    textMatchesNavQuery(item.navEyebrow, needle) ||
    textMatchesNavQuery(item.navHint, needle)
  )
}

function filteredNavItemsForGroup(group: PlatformAdminSidebarGroup): PlatformAdminNavItem[] {
  const q = sidebarNavQuery.value
  const out: PlatformAdminNavItem[] = []
  for (const id of group.sectionIds) {
    const item = platformAdminNavItems.find((i) => i.id === id)
    if (!item || !itemMatchesNavQuery(item, q)) continue
    if (!canAccessPlatformSection(auth.hasPermission, id as PlatformAdminSectionId)) continue
    out.push(item)
  }
  return out
}

const visibleSidebarGroups = computed(() =>
  platformAdminSidebarGroups.filter((g) => filteredNavItemsForGroup(g).length > 0),
)

function isSidebarGroupExpanded(groupId: string): boolean {
  if (sidebarNavQueryActive.value) return true
  return !collapsedSidebarGroupIds.value.includes(groupId)
}

function toggleSidebarGroup(groupId: string): void {
  if (sidebarNavQueryActive.value) return
  const list = collapsedSidebarGroupIds.value
  const ix = list.indexOf(groupId)
  if (ix >= 0) collapsedSidebarGroupIds.value = list.filter((id) => id !== groupId)
  else collapsedSidebarGroupIds.value = [...list, groupId]
  try {
    localStorage.setItem(SIDEBAR_COLLAPSE_LS, JSON.stringify(collapsedSidebarGroupIds.value))
  } catch {
    /* ignore quota / private mode */
  }
}

const providerLinksFiltered = computed(() => {
  const q = sidebarNavQuery.value.trim()
  if (!q) return [...platformProviderSidebarLinks]
  if (textMatchesNavQuery('مزودو الخدمة مزود تكلفة مورد', q)) return [...platformProviderSidebarLinks]
  return platformProviderSidebarLinks.filter((l) => textMatchesNavQuery(l.label, q))
})

const providerCardVisible = computed(() => {
  if (!auth.hasPermission('platform.providers.manage')) return false
  const q = sidebarNavQuery.value.trim()
  if (!q) return true
  if (textMatchesNavQuery('مزودو الخدمة مزود تكلفة مورد', q)) return true
  return providerLinksFiltered.value.length > 0
})

const commercialLinksFiltered = computed(() => {
  const base = commercialPricingSidebarLinksFiltered.value
  const q = sidebarNavQuery.value.trim()
  if (!q) return base
  if (textMatchesNavQuery('التسعير تسعير سعر اعتماد مراجعة قائمة عميل', q)) return base
  return base.filter((l) => textMatchesNavQuery(l.label, q))
})

const commercialCardVisible = computed(() => commercialLinksFiltered.value.length > 0)

const subscriptionCardVisible = computed(() => {
  if (!auth.hasPermission('platform.subscription.manage')) return false
  const q = sidebarNavQuery.value.trim()
  if (!q) return true
  return textMatchesNavQuery('طلبات الاشتراكات اشتراك اشتراكات مراجعة فواتير', q)
})

const contractsCardVisible = computed(() => {
  if (!auth.hasPermission('platform.pricing.view') && !auth.hasPermission('platform.providers.manage')) return false
  const q = sidebarNavQuery.value.trim()
  if (!q) return true
  return textMatchesNavQuery('العقود عقد', q)
})

const reportsCardVisible = computed(() => {
  if (!auth.hasPermission('platform.reporting.read') && !auth.hasPermission('platform.pricing.view')) return false
  const q = sidebarNavQuery.value.trim()
  if (!q) return true
  return textMatchesNavQuery('التقارير تقرير', q)
})

const staffPortalHomePath = computed(() => (auth.isPlatform ? '/?shell=tenant' : '/'))

const staffPortalLinksFiltered = computed(() => {
  const q = sidebarNavQuery.value.trim()
  const base = staffPortalLinks.map((link) =>
    link.to === '/' ? { ...link, to: staffPortalHomePath.value } : link,
  )
  if (!q) return base
  return base.filter((link) => textMatchesNavQuery(link.label, q))
})

const staffPortalCardVisible = computed(() => {
  if (typeof auth.user?.company_id !== 'number' || auth.user.company_id <= 0) return false
  const q = sidebarNavQuery.value.trim()
  if (!q) return true
  return staffPortalLinksFiltered.value.length > 0
})

const registrationCardVisible = computed(() => {
  const q = sidebarNavQuery.value.trim()
  if (!q) return true
  return textMatchesNavQuery('طلبات التسجيل مراجعات تسجيل', q)
})

const qaCardVisible = computed(() => {
  const q = sidebarNavQuery.value.trim()
  if (!q) return true
  return textMatchesNavQuery('اختبار النظام ضمان الجودة جودة qa', q)
})

const taxonomyCardVisible = computed(() => {
  const q = sidebarNavQuery.value.trim()
  if (!q) return true
  return textMatchesNavQuery('المعرفة مسرد مفاهيم taxonomy', q)
})

const sidebarNavHasAnyMatch = computed(() => {
  if (!sidebarNavQueryActive.value) return true
  return (
    visibleSidebarGroups.value.length > 0 ||
    subscriptionCardVisible.value ||
    providerCardVisible.value ||
    commercialCardVisible.value ||
    contractsCardVisible.value ||
    reportsCardVisible.value ||
    staffPortalCardVisible.value ||
    registrationCardVisible.value ||
    qaCardVisible.value ||
    taxonomyCardVisible.value
  )
})

watch([commerceSectionOpen, staffSectionOpen, toolsSectionOpen], () => {
  if (typeof window === 'undefined') return
  try {
    localStorage.setItem(
      SECTION_EXPAND_LS,
      JSON.stringify({
        c: commerceSectionOpen.value,
        s: staffSectionOpen.value,
        t: toolsSectionOpen.value,
      }),
    )
  } catch {
    /* quota / private mode */
  }
})

provide(platformSubscriptionAttentionKey, {
  badgeCount: subscriptionAttention.badgeCount,
  summary: subscriptionAttention.summary,
  refresh: subscriptionAttention.refresh,
})

function onMqChange(ev: MediaQueryListEvent): void {
  isLgUp.value = ev.matches
  if (ev.matches) mobileDrawerOpen.value = false
}

function isTypingInField(target: EventTarget | null): boolean {
  if (!target || !(target instanceof HTMLElement)) return false
  const tag = target.tagName
  if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return true
  if (target.isContentEditable) return true
  if (target.closest('[role="dialog"]') || target.closest('[data-command-palette]')) return true
  return false
}

function onGlobalKeydown(ev: KeyboardEvent): void {
  if (ev.key === 'Escape') {
    if (sidebarNavQuery.value.trim().length > 0) {
      sidebarNavQuery.value = ''
      ev.preventDefault()
      return
    }
    if (!mobileDrawerOpen.value || isLgUp.value) return
    mobileDrawerOpen.value = false
    return
  }
  if (ev.key === '/' || ev.code === 'NumpadDivide') {
    if (isTypingInField(ev.target)) return
    ev.preventDefault()
    sidebarSearchInputRef.value?.focus()
  }
}

let layoutMq: MediaQueryList | null = null

onMounted(() => {
  try {
    const raw = localStorage.getItem(SIDEBAR_COLLAPSE_LS)
    if (raw) {
      const parsed = JSON.parse(raw) as unknown
      if (Array.isArray(parsed)) {
        collapsedSidebarGroupIds.value = parsed.filter((x): x is string => typeof x === 'string')
      }
    }
  } catch {
    /* ignore */
  }
  if (auth.hasPermission('platform.subscription.manage')) {
    subscriptionAttention.startPolling()
  }

  layoutMq = window.matchMedia('(min-width: 1024px)')
  isLgUp.value = layoutMq.matches
  layoutMq.addEventListener('change', onMqChange)
  window.addEventListener('keydown', onGlobalKeydown)
})

watch(
  () => route.fullPath,
  async () => {
    if (isToolsRouteActive()) toolsSectionOpen.value = true
    mobileDrawerOpen.value = false
    await nextTick()
    const mainEl = document.getElementById('platform-admin-main')
    if (mainEl) mainEl.scrollTop = 0
    const nav = document.getElementById('platform-admin-sidebar')
    const active = nav?.querySelector<HTMLElement>('a.router-link-active')
    active?.scrollIntoView({ block: 'nearest', behavior: 'auto' })
  },
  { immediate: true },
)

watch(
  [mobileDrawerOpen, isLgUp],
  () => {
    const lock = mobileDrawerOpen.value && !isLgUp.value
    document.documentElement.classList.toggle('overflow-hidden', lock)
  },
  { immediate: true },
)

onUnmounted(() => {
  subscriptionAttention.stopPolling()
  layoutMq?.removeEventListener('change', onMqChange)
  window.removeEventListener('keydown', onGlobalKeydown)
  document.documentElement.classList.remove('overflow-hidden')
})

const isSubscriptionOpsRoute = computed(() => route.path.startsWith('/admin/subscriptions'))

/** مفتاح ثابت للانتقال حتى لا يُعاد إنشاء الصفحة عند تغيّر الاستعلام فقط */
const routeKey = computed(() => {
  const n = route.name
  return typeof n === 'string' || typeof n === 'symbol' ? String(n) : route.path
})

function platformSidebarLabelForRouteName(name: unknown): string {
  if (typeof name !== 'string') return ''
  const main = platformAdminNavItems.find((i) => i.routeName === name)
  if (main) return main.label
  const extra = [
    ...platformProviderSidebarLinks,
    ...platformCommercialPricingSidebarLinks,
    ...platformContractsAndReportsSidebarLinks,
  ].find((l) => l.routeName === name)
  return extra?.label ?? ''
}

const breadcrumbCurrent = computed(() => {
  if (route.path.startsWith('/admin/subscriptions')) return 'طلبات الاشتراكات'
  return platformSidebarLabelForRouteName(route.name)
})

function isActiveRoute(routeName: string): boolean {
  return route.name === routeName
}

function platformNavLinkClass(active: boolean, density: 'default' | 'compact' = 'default'): string[] {
  const sizing =
    density === 'compact'
      ? 'px-2.5 py-1.5 text-xs'
      : 'px-3 py-2 text-sm'
  const base = `flex w-full items-center gap-2.5 rounded-xl ${sizing} text-right font-semibold transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900`
  return [
    base,
    active
      ? 'bg-primary-600 text-white shadow-md ring-1 ring-primary-500/30 hover:bg-primary-600 hover:text-white dark:bg-primary-500 dark:ring-primary-400/25'
      : 'text-gray-700 hover:bg-primary-50/90 hover:text-primary-800 dark:text-slate-300 dark:hover:bg-primary-950/35 dark:hover:text-primary-200',
  ]
}

function isActiveStaffPath(path: string): boolean {
  if (path === '/') {
    return route.path === '/' || route.path === '/dashboard'
  }
  return route.path === path || route.path.startsWith(`${path}/`)
}

</script>
