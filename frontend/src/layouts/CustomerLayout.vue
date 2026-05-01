<template>
  <div class="min-h-screen flex bg-[color:var(--bg-base)] transition-colors" dir="rtl">
    <Transition name="overlay-fade">
      <div
        v-if="mobileOpen"
        data-print-chrome
        class="print:hidden fixed inset-0 bg-black/45 z-40 lg:hidden"
        @click="mobileOpen = false"
      />
    </Transition>

    <aside
      data-print-chrome
      class="print:hidden fixed lg:relative inset-y-0 right-0 z-50 border-l border-[color:var(--border-color)] bg-[color:var(--bg-sidebar)] shadow-[0_0_0_1px_rgba(13,148,136,0.06)] transition-[width,transform] duration-200 ease-out lg:translate-x-0"
      :class="[
        mobileOpen ? 'translate-x-0' : 'translate-x-full',
        collapsed ? 'w-[60px]' : 'w-64',
      ]"
    >
      <div
        v-if="!collapsed"
        class="h-16 border-b border-[color:var(--border-color)] bg-[color:var(--bg-header)] px-4 flex items-center justify-between"
      >
        <div class="flex items-center gap-2.5 min-w-0">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-violet-600 to-purple-700 text-white flex items-center justify-center shadow-sm">
            <UserCircleIcon class="w-6 h-6" />
          </div>
          <div class="min-w-0">
            <p class="text-sm font-bold text-[color:var(--text-primary)] truncate">{{ auth.user?.name }}</p>
            <p class="text-[11px] text-gray-500 truncate">بوابة العميل</p>
          </div>
        </div>
        <div class="flex items-center gap-1">
          <button
            class="p-1.5 rounded-lg border border-primary-200 bg-primary-50 text-primary-700 hover:bg-primary-100 transition-colors"
            title="طي القائمة"
            @click="collapsed = true"
          >
            <ChevronRightIcon class="w-4 h-4" />
          </button>
          <button class="lg:hidden p-1.5 rounded-lg text-gray-500 hover:bg-gray-100" @click="mobileOpen = false">
            <XMarkIcon class="w-4 h-4" />
          </button>
        </div>
      </div>
      <div v-else class="flex justify-center border-b border-gray-100 py-2 dark:border-slate-700">
        <button
          class="p-2 rounded-lg border border-primary-200 bg-primary-50 text-primary-700 hover:bg-primary-100 transition-colors"
          title="توسيع القائمة"
          @click="collapsed = false"
        >
          <ChevronLeftIcon class="w-4 h-4" />
        </button>
      </div>

      <nav class="overflow-y-auto max-h-[calc(100vh-9rem)]" :class="collapsed ? 'p-2 space-y-2' : 'p-3 space-y-3'">
        <section
          v-for="section in navSections"
          :key="section.key"
          class="rounded-2xl border border-gray-100/90 dark:border-slate-700/80 bg-white/70 dark:bg-slate-900/40 px-2 py-2 shadow-sm shadow-primary-900/[0.03] dark:shadow-none"
          :class="collapsed ? 'px-1.5 py-1.5' : ''"
        >
          <button
            type="button"
            class="w-full flex items-center rounded-xl text-sm font-semibold transition-all"
            :class="isSectionActive(section)
              ? 'bg-primary-600 text-white shadow-md ring-1 ring-primary-500/30'
              : 'text-gray-700 dark:text-slate-200 hover:bg-primary-50/90 dark:hover:bg-primary-950/35 hover:text-primary-800 dark:hover:text-primary-200'"
            :title="collapsed ? section.label : undefined"
            @click="toggleSection(section)"
          >
            <div class="flex items-center w-full" :class="collapsed ? 'justify-center px-2 py-2.5' : 'gap-2.5 px-3 py-2.5 justify-between'">
              <div class="flex items-center gap-2.5 min-w-0">
                <component :is="sectionIcon(section)" class="w-4 h-4 flex-shrink-0" />
                <span v-if="!collapsed" class="truncate">{{ section.label }}</span>
              </div>
              <ChevronDownIcon
                v-if="!collapsed"
                class="w-4 h-4 transition-transform duration-200"
                :class="expandedSectionKey === section.key ? 'rotate-180' : ''"
              />
            </div>
          </button>
          <div v-if="!collapsed && expandedSectionKey === section.key" class="mt-1 space-y-1">
            <RouterLink
              v-for="item in section.items"
              :key="item.key"
              :to="item.to"
              class="flex items-center rounded-xl text-sm font-semibold transition-all"
              :class="route.path.startsWith(item.to)
                ? 'bg-primary-100 text-primary-800 dark:bg-primary-900/35 dark:text-primary-200 ring-1 ring-primary-200/80 dark:ring-primary-700/40'
                : 'text-gray-600 dark:text-slate-300 hover:bg-primary-50/90 dark:hover:bg-primary-950/35 hover:text-primary-800 dark:hover:text-primary-200'"
              @click="mobileOpen = false"
            >
              <div class="flex items-center w-full gap-2.5 px-3 py-2.5">
                <component :is="item.icon" class="w-4 h-4 flex-shrink-0" />
                <span>{{ item.label }}</span>
              </div>
            </RouterLink>
          </div>
        </section>
      </nav>
    </aside>

    <div class="flex-1 flex flex-col min-w-0">
      <header
        data-print-chrome
        class="print:hidden sticky top-0 z-30 h-16 border-b border-[color:var(--border-color)] bg-[color:var(--bg-header)]/95 backdrop-blur px-4 lg:px-6 flex items-center justify-between"
      >
        <div class="flex items-center gap-2.5 min-w-0">
          <button
            type="button"
            class="hidden md:inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors"
            title="رجوع"
            @click="goBack"
          >
            <ArrowRightIcon class="w-4 h-4" />
            <span class="text-xs font-semibold">رجوع</span>
          </button>
          <button class="lg:hidden w-9 h-9 rounded-lg hover:bg-gray-100 flex items-center justify-center" @click="mobileOpen = true">
            <Bars3Icon class="w-5 h-5 text-gray-600" />
          </button>
          <div class="min-w-0">
            <p class="text-sm font-bold text-[color:var(--text-primary)] truncate">{{ pageTitle }}</p>
            <p class="text-[11px] text-gray-500">بوابة خدمات العميل</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <div class="hidden lg:flex items-center gap-2 px-2 select-none">
            <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-primary-100 dark:bg-primary-900/40 text-primary-700 dark:text-primary-200 text-[11px] font-bold">
              {{ String(auth.user?.name || 'م').trim().charAt(0).toUpperCase() }}
            </span>
            <span class="text-sm font-semibold text-gray-800 dark:text-slate-100 truncate max-w-[180px]">
              {{ auth.user?.name || 'المستخدم الحالي' }}
            </span>
          </div>
          <button
            type="button"
            class="hidden lg:inline-flex items-center gap-1.5 text-sm font-semibold text-red-600 hover:text-red-700 transition-colors px-1.5"
            title="تسجيل الخروج"
            @click="handleLogout"
          >
            <ArrowLeftOnRectangleIcon class="w-4 h-4" />
            <span>تسجيل الخروج</span>
          </button>
          <RouterLink
            to="/customer/profile"
            class="w-9 h-9 rounded-xl border border-gray-200 dark:border-slate-700 flex items-center justify-center text-gray-600 dark:text-slate-300 hover:bg-violet-50 dark:hover:bg-violet-900/20 hover:text-violet-600 transition-colors"
            title="الملف الشخصي"
          >
            <UserCircleIcon class="w-4 h-4" />
          </RouterLink>
          <RouterLink
            to="/customer/notifications"
            class="relative w-9 h-9 rounded-xl border border-gray-200 dark:border-slate-700 flex items-center justify-center text-gray-600 dark:text-slate-300 hover:bg-violet-50 dark:hover:bg-violet-900/20 hover:text-violet-600 transition-colors"
            title="الإشعارات"
          >
            <BellIcon class="w-4 h-4" />
            <span
              v-if="unreadSupportCount > 0"
              class="absolute -top-1 -left-1 min-w-[16px] h-4 px-1 rounded-full bg-rose-600 text-white text-[10px] leading-4 text-center font-bold"
            >
              {{ unreadSupportCount > 99 ? '99+' : unreadSupportCount }}
            </span>
          </RouterLink>
          <RouterLink
            to="/customer/settings"
            class="w-9 h-9 rounded-xl border border-gray-200 dark:border-slate-700 flex items-center justify-center text-gray-600 dark:text-slate-300 hover:bg-violet-50 dark:hover:bg-violet-900/20 hover:text-violet-600 transition-colors"
            title="الإعدادات"
          >
            <Cog6ToothIcon class="w-4 h-4" />
          </RouterLink>
          <div ref="langMenuRef" class="relative">
            <button
              type="button"
              class="h-9 rounded-xl border border-gray-200 dark:border-slate-700 px-2.5 flex items-center gap-1.5 text-gray-600 dark:text-slate-300 hover:bg-violet-50 dark:hover:bg-violet-900/20 hover:text-violet-600 transition-colors"
              title="اللغة"
              @click="langMenuOpen = !langMenuOpen"
            >
              <GlobeAltIcon class="w-4 h-4" />
              <span class="text-[11px] font-semibold">{{ locale.langInfo.value.flag }}</span>
              <ChevronDownIcon class="w-3 h-3" />
            </button>
            <div v-if="langMenuOpen" class="absolute left-0 top-full mt-1 w-36 rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-lg p-1 z-40">
              <button
                v-for="lang in LANGUAGES"
                :key="lang.code"
                type="button"
                class="w-full text-right rounded-lg px-2.5 py-2 text-xs font-medium"
                :class="locale.lang.value === lang.code ? 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-200' : 'text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700'"
                @click="locale.setLang(lang.code); langMenuOpen = false"
              >
                {{ lang.flag }} {{ lang.native }}
              </button>
            </div>
          </div>
          <button
            type="button"
            class="w-9 h-9 rounded-xl border border-gray-200 dark:border-slate-700 flex items-center justify-center text-gray-600 dark:text-slate-300 hover:bg-violet-50 dark:hover:bg-violet-900/20 hover:text-violet-600 transition-colors"
            :title="darkMode.isDark.value ? 'الوضع النهاري' : 'الوضع الليلي'"
            @click="darkMode.toggle()"
          >
            <SunIcon v-if="darkMode.isDark.value" class="w-4 h-4" />
            <MoonIcon v-else class="w-4 h-4" />
          </button>
        </div>
      </header>

      <main class="flex-1 overflow-auto p-4 md:p-6">
        <div class="mx-auto w-full max-w-[1600px]">
          <RouterView />
        </div>
      </main>
    </div>
    <SystemConfirmModal />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from 'axios'
import SystemConfirmModal from '@/components/SystemConfirmModal.vue'
import {
  UserCircleIcon, ArrowLeftOnRectangleIcon, BellIcon, Bars3Icon, XMarkIcon, ChevronRightIcon, ChevronLeftIcon,
  HomeIcon, TruckIcon, DocumentTextIcon, CalendarDaysIcon, ClipboardDocumentListIcon, CreditCardIcon, CurrencyDollarIcon, ChartBarIcon, MapPinIcon,
  Cog6ToothIcon, BuildingOffice2Icon, ChevronDownIcon, GlobeAltIcon, SunIcon, MoonIcon,   UserGroupIcon, FolderOpenIcon, RectangleStackIcon, QueueListIcon, LockClosedIcon, ArrowRightIcon, PresentationChartLineIcon,
} from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'
import { isCustomerNavHidden } from '@/lib/staffNavKey'
import { useLocale, LANGUAGES } from '@/composables/useLocale'
import { useDarkMode } from '@/composables/useDarkMode'

const auth   = useAuthStore()
const router = useRouter()
const route = useRoute()
const mobileOpen = ref(false)
const collapsed = ref(localStorage.getItem('customer_sidebar_collapsed') === 'true')
const langMenuOpen = ref(false)
const langMenuRef = ref<HTMLElement | null>(null)
const locale = useLocale()
const darkMode = useDarkMode()
watch(collapsed, (v) => localStorage.setItem('customer_sidebar_collapsed', String(v)))
const unreadSupportCount = ref(Number(localStorage.getItem('customer_unread_support_count') || '0'))
let unreadTimer: ReturnType<typeof setInterval> | null = null

const navItemsAll = [
  { to: '/customer/dashboard', icon: HomeIcon,            label: 'الرئيسية' },
  { to: '/customer/work-orders', icon: ClipboardDocumentListIcon, label: 'أوامر العمل' },
  { to: '/customer/bookings',  icon: CalendarDaysIcon,    label: 'الحجوزات' },
  { to: '/customer/coverage-locations', icon: MapPinIcon, label: 'مواقع التغطية' },
  { to: '/customer/vehicles',  icon: TruckIcon,           label: 'إدارة المركبات' },
  { to: '/customer/wallet',    icon: CreditCardIcon,      label: 'المحفظة والمدفوعات' },
  { to: '/customer/wallet/top-up-requests', icon: QueueListIcon, label: 'طلبات شحن الرصيد' },
  { to: '/customer/invoices',  icon: DocumentTextIcon,    label: 'الفواتير' },
  { to: '/customer/reports',   icon: ChartBarIcon,        label: 'التقارير' },
  { to: '/customer/business-intelligence', icon: PresentationChartLineIcon, label: 'ذكاء الأعمال' },
  { to: '/customer/pricing',   icon: CurrencyDollarIcon,  label: 'العقود والتسعير الخاص' },
  { to: '/customer/company-settings', icon: BuildingOffice2Icon, label: 'إعدادات المنشأة' },
  { to: '/customer/profile', icon: UserCircleIcon, label: 'الملف الشخصي' },
  { to: '/customer/team-users', icon: UserGroupIcon,      label: 'حسابات فريق العمل' },
  { to: '/customer/org-units',  icon: FolderOpenIcon,     label: 'هيكل القطاعات' },
  { to: '/customer/activity',   icon: ClipboardDocumentListIcon, label: 'سجل العمليات' },
  { to: '/customer/plans',      icon: RectangleStackIcon, label: 'الباقات' },
  { to: '/customer/subscription', icon: RectangleStackIcon, label: 'اشتراك المنشأة' },
  { to: '/customer/subscription/plans', icon: RectangleStackIcon, label: 'مقارنة الباقات' },
  { to: '/customer/subscription/payment', icon: CreditCardIcon, label: 'الدفع والتجديد' },
  { to: '/customer/subscription/invoices', icon: DocumentTextIcon, label: 'فواتير الاشتراك' },
  { to: '/customer/zatca',      icon: BuildingOffice2Icon, label: 'الزكاة والضريبة (ZATCA)' },
  { to: '/customer/api-keys',   icon: LockClosedIcon,      label: 'مفاتيح API' },
  { to: '/customer/notifications', icon: BellIcon,        label: 'الإشعارات والدعم' },
  { to: '/customer/settings',  icon: Cog6ToothIcon,       label: 'الإعدادات' },
]

const navItems = computed(() => {
  const hidden = auth.user?.hidden_customer_nav_keys
  if (!Array.isArray(hidden) || !hidden.length) return navItemsAll
  const set = new Set(hidden)
  const customerCorePaths = new Set([
    '/customer/dashboard',
    '/customer/work-orders',
    '/customer/bookings',
    '/customer/vehicles',
    '/customer/wallet',
    '/customer/wallet/top-up-requests',
    '/customer/invoices',
    '/customer/reports',
    '/customer/business-intelligence',
    '/customer/notifications',
    '/customer/settings',
  ])
  return navItemsAll.filter((item) => {
    if (customerCorePaths.has(item.to)) return true
    return !isCustomerNavHidden(item.to, set)
  })
})

const navSections = computed(() => {
  type NavSectionItem = { key: string; to: string; icon: any; label: string }
  type NavSection = { key: string; label: string; items: NavSectionItem[] }
  const byPath = (path: string, label: string) => {
    const item = navItems.value.find((n) => n.to === path)
    if (!item) return null
    return { key: `${path}-${label}`, to: item.to, icon: item.icon, label }
  }
  const compact = (items: Array<NavSectionItem | null>) => items.filter((entry): entry is NavSectionItem => entry !== null)
  const sections: NavSection[] = [
    {
      key: 'overview',
      label: 'نظرة عامة',
      items: compact([byPath('/customer/dashboard', 'لوحة التشغيل')]),
    },
    {
      key: 'ops',
      label: 'العمليات',
      items: compact([
        byPath('/customer/work-orders', 'أوامر العمل'),
        byPath('/customer/bookings', 'الحجوزات'),
      ]),
    },
    {
      key: 'coverage',
      label: 'مواقع التغطية',
      items: compact([
        byPath('/customer/coverage-locations', 'مواقع المزودين'),
      ]),
    },
    {
      key: 'vehicles',
      label: 'المركبات',
      items: compact([byPath('/customer/vehicles', 'إدارة المركبات')]),
    },
    {
      key: 'finance',
      label: 'المالية',
      items: compact([
        byPath('/customer/invoices', 'الفواتير'),
        byPath('/customer/wallet', 'المحفظة والمدفوعات'),
        byPath('/customer/wallet/top-up-requests', 'طلبات شحن الرصيد'),
        byPath('/customer/reports', 'التقارير'),
        byPath('/customer/business-intelligence', 'ذكاء الأعمال'),
      ]),
    },
    {
      key: 'contract',
      label: 'التعاقد',
      items: compact([
        byPath('/customer/pricing', 'العقود والتسعير الخاص'),
        byPath('/customer/plans', 'الباقات'),
        byPath('/customer/subscription', 'اشتراك المنشأة'),
        byPath('/customer/subscription/plans', 'مقارنة الباقات'),
        byPath('/customer/subscription/payment', 'الدفع والتجديد'),
        byPath('/customer/subscription/invoices', 'فواتير الاشتراك'),
      ]),
    },
    {
      key: 'company',
      label: 'المنشأة',
      items: compact([
        byPath('/customer/company-settings', 'إعدادات المنشأة'),
        byPath('/customer/profile', 'الملف الشخصي'),
        byPath('/customer/team-users', 'حسابات فريق العمل'),
        byPath('/customer/org-units', 'هيكل القطاعات'),
        byPath('/customer/activity', 'سجل العمليات'),
        byPath('/customer/zatca', 'الزكاة والضريبة (ZATCA)'),
        byPath('/customer/api-keys', 'مفاتيح API'),
      ]),
    },
    {
      key: 'support',
      label: 'الدعم',
      items: compact([byPath('/customer/notifications', 'الإشعارات والدعم')]),
    },
    {
      key: 'settings',
      label: 'الإعدادات',
      items: compact([byPath('/customer/settings', 'الإعدادات العامة')]),
    },
  ]
  return sections.filter((section) => section.items.length > 0)
})

function isSectionActive(section: { items: Array<{ to: string }> }): boolean {
  return section.items.some((item) => route.path.startsWith(item.to))
}

const activeSectionKey = computed(() => navSections.value.find((section) => isSectionActive(section))?.key ?? 'overview')
const expandedSectionKey = ref(activeSectionKey.value)

watch(activeSectionKey, (key) => {
  if (!key) return
  expandedSectionKey.value = key
}, { immediate: true })

function toggleSection(section: { key: string; items: Array<{ to: string }> }): void {
  if (section.items.length === 1) {
    const only = section.items[0]
    if (only && route.path !== only.to) {
      router.push(only.to)
    }
    mobileOpen.value = false
    return
  }
  expandedSectionKey.value = expandedSectionKey.value === section.key ? '' : section.key
}

function sectionIcon(section: { items: Array<{ icon: any }> }): any {
  return section.items[0]?.icon ?? HomeIcon
}

function calcUnreadCount(stats: any): number {
  const candidates = [
    stats?.unread,
    stats?.unread_count,
    stats?.pending_customer,
    (Number(stats?.open || 0) + Number(stats?.in_progress || 0)),
  ]
  for (const value of candidates) {
    const n = Number(value)
    if (Number.isFinite(n) && n >= 0) return n
  }
  return 0
}

async function refreshUnreadSupportCount(): Promise<void> {
  try {
    const { data } = await axios.get('/api/v1/support/stats')
    const next = calcUnreadCount(data?.data ?? {})
    unreadSupportCount.value = next
    localStorage.setItem('customer_unread_support_count', String(next))
  } catch {
    // keep last known count when endpoint is unavailable
  }
}

function syncUnreadFromStorage(): void {
  unreadSupportCount.value = Number(localStorage.getItem('customer_unread_support_count') || '0')
}

async function handleLogout() {
  await auth.logout()
  router.push('/customer/login')
}

function goBack(): void {
  if (window.history.length > 1) {
    router.back()
    return
  }
  router.push('/customer/dashboard')
}

const pageTitles: Record<string, string> = {
  'customer.dashboard': 'لوحة العميل',
  'customer.work-orders': 'أوامر العمل',
  'customer.bookings': 'حجوزاتي',
  'customer.coverage-locations': 'مواقع التغطية',
  'customer.vehicles': 'مركباتي',
  'customer.vehicles.show': 'ملف المركبة',
  'customer.vehicles.card': 'البطاقة الرقمية للمركبة',
  'customer.vehicles.passport': 'سجل المركبة',
  'customer.invoices': 'فواتيري',
  'customer.invoices.show': 'تفاصيل الفاتورة',
  'customer.wallet': 'المحفظة',
  'customer.wallet.top-up-requests': 'طلبات شحن الرصيد',
  'customer.api-keys': 'مفاتيح API',
  'customer.reports': 'التقارير',
  'customer.business-intelligence': 'ذكاء الأعمال',
  'customer.pricing': 'التسعير',
  'customer.company-settings': 'إعدادات المنشأة',
  'customer.profile': 'الملف الشخصي',
  'customer.team-users': 'حسابات فريق العمل',
  'customer.org-units': 'هيكل القطاعات',
  'customer.activity': 'سجل العمليات',
  'customer.plans': 'الباقات',
  'customer.subscription': 'اشتراك المنشأة',
  'customer.subscription.plans': 'مقارنة الباقات',
  'customer.subscription.payment': 'الدفع والتجديد',
  'customer.subscription.invoices': 'فواتير الاشتراك',
  'customer.zatca': 'الزكاة والضريبة',
  'customer.notifications': 'الإشعارات',
  'customer.settings': 'الإعدادات',
}

const pageTitle = computed(() => pageTitles[String(route.name)] ?? 'بوابة العميل')

function closeLangMenuOnOutsideClick(event: MouseEvent): void {
  if (!langMenuOpen.value) return
  const target = event.target as Node | null
  if (!target) return
  if (langMenuRef.value && !langMenuRef.value.contains(target)) {
    langMenuOpen.value = false
  }
}

function closeLangMenuOnEscape(event: KeyboardEvent): void {
  if (event.key === 'Escape') langMenuOpen.value = false
}

watch(() => route.fullPath, () => {
  langMenuOpen.value = false
})

onMounted(() => {
  void refreshUnreadSupportCount()
  unreadTimer = setInterval(() => { void refreshUnreadSupportCount() }, 60000)
  window.addEventListener('storage', syncUnreadFromStorage)
  window.addEventListener('support-unread-refresh', syncUnreadFromStorage as EventListener)
  document.addEventListener('click', closeLangMenuOnOutsideClick)
  document.addEventListener('keydown', closeLangMenuOnEscape)
})

onUnmounted(() => {
  if (unreadTimer) clearInterval(unreadTimer)
  unreadTimer = null
  window.removeEventListener('storage', syncUnreadFromStorage)
  window.removeEventListener('support-unread-refresh', syncUnreadFromStorage as EventListener)
  document.removeEventListener('click', closeLangMenuOnOutsideClick)
  document.removeEventListener('keydown', closeLangMenuOnEscape)
})
</script>

<style scoped>
.overlay-fade-enter-active, .overlay-fade-leave-active { transition: opacity 0.2s; }
.overlay-fade-enter-from, .overlay-fade-leave-to { opacity: 0; }
</style>
