<template>
  <div class="min-h-screen bg-gray-50 dark:bg-slate-900 flex transition-colors" :dir="locale.langInfo.value.dir">

    <!-- Mobile Sidebar Overlay -->
    <Transition name="overlay-fade">
      <div v-if="mobileOpen" class="fixed inset-0 bg-black/50 z-40 lg:hidden" @click="mobileOpen = false"></div>
    </Transition>

    <!-- Sidebar -->
    <aside
      :class="[
        'flex flex-col flex-shrink-0 overflow-hidden transition-all duration-300 ease-in-out bg-white dark:bg-slate-800 border-l dark:border-slate-700 border-gray-200',
        'fixed lg:relative inset-y-0 right-0 z-50 lg:z-auto',
        mobileOpen ? 'translate-x-0' : 'translate-x-full lg:translate-x-0',
        collapsed ? 'w-[60px]' : 'w-64',
      ]"
    >
      <!-- Logo + Collapse Toggle -->
      <div class="h-16 flex items-center border-b border-gray-200 dark:border-slate-700 sticky top-0 bg-white dark:bg-slate-800 z-10 flex-shrink-0 transition-colors"
        :class="collapsed ? 'justify-center px-2' : 'justify-between px-4'"
      >
        <div class="flex items-center gap-2 overflow-hidden">
          <div class="w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center flex-shrink-0">
            <WrenchScrewdriverIcon class="w-4 h-4 text-white" />
          </div>
          <span v-show="!collapsed" class="text-base font-bold text-gray-900 whitespace-nowrap">WorkshopOS</span>
        </div>
        <button
          v-show="!collapsed"
          @click="collapsed = true"
          class="p-1 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors flex-shrink-0"
          title="طيّ القائمة"
        >
          <ChevronRightIcon class="w-4 h-4" />
        </button>
      </div>

      <!-- Expand Button when collapsed -->
      <div v-if="collapsed" class="flex justify-center py-2 border-b border-gray-100">
        <button @click="collapsed = false" class="p-2 rounded-lg text-gray-400 hover:text-primary-600 hover:bg-primary-50 transition-colors" title="توسيع القائمة">
          <ChevronLeftIcon class="w-4 h-4" />
        </button>
      </div>

      <!-- Nav -->
      <nav class="flex-1 overflow-y-auto" :class="collapsed ? 'p-1.5 space-y-1' : 'p-3 space-y-4 pb-6'">

        <template v-if="collapsed">
          <!-- Icon-only mode -->
          <NavIconItem v-for="item in flatItems" :key="item.to" v-bind="item" />
        </template>
        <template v-else>
          <!-- Full Sidebar -->
          <NavSection label="الرئيسي">
            <NavItem to="/"              :icon="HomeIcon"           label="الرئيسية" :exact="true" />
            <NavItem to="/pos"           :icon="ShoppingCartIcon"   label="نقطة البيع" />
            <NavItem to="/work-orders"   :icon="ClipboardDocumentIcon" label="أوامر العمل"
              :locked="!sub.hasFeature('work_orders')" />
            <NavItem to="/invoices"      :icon="DocumentTextIcon"   label="الفواتير" />
            <NavItem to="/crm/quotes"    :icon="DocumentTextIcon"   label="عروض الأسعار" />
          </NavSection>

          <NavSection label="مركز الخدمة">
            <NavItem to="/bays"               :icon="BuildingOfficeIcon"          label="مناطق العمل"
              :locked="!sub.hasFeature('work_orders')" />
            <NavItem to="/bookings"            :icon="CalendarDaysIcon"           label="الحجوزات"
              :locked="!sub.hasFeature('work_orders')" />
            <NavItem to="/bays/heatmap"        :icon="FireIcon"                   label="الخريطة الحرارية"
              :locked="!sub.hasFeature('work_orders')" />
          </NavSection>

          <NavSection label="الموارد البشرية">
            <NavItem to="/workshop/employees"  :icon="UserGroupIcon"              label="إدارة الموظفين"
              :locked="!sub.hasFeature('work_orders')" />
            <NavItem to="/workshop/tasks"      :icon="ClipboardDocumentCheckIcon" label="إدارة المهام"
              :locked="!sub.hasFeature('work_orders')" />
            <NavItem to="/workshop/attendance" :icon="ClockIcon"                  label="الحضور"
              :locked="!sub.hasFeature('work_orders')" />
            <NavItem to="/workshop/leaves"     :icon="CalendarDaysIcon"           label="الإجازات"
              :locked="!sub.hasFeature('work_orders')" />
            <NavItem to="/workshop/salaries"   :icon="BanknotesIcon"              label="مسير الرواتب"
              :locked="!sub.hasFeature('work_orders')" />
            <NavItem to="/workshop/commissions":icon="CurrencyDollarIcon"         label="العمولات"
              :locked="!sub.hasFeature('work_orders')" />
          </NavSection>

          <NavSection label="العملاء">
            <NavItem to="/customers" :icon="UsersIcon"  label="العملاء" />
            <NavItem to="/vehicles"  :icon="TruckIcon"  label="المركبات" />
          </NavSection>

          <NavSection label="الأسطول">
            <NavItem to="/fleet/verify-plate" :icon="MagnifyingGlassIcon" label="التحقق من اللوحة"
              :locked="!sub.hasFeature('fleet')" />
            <NavItem to="/fleet/wallet"       :icon="CreditCardIcon"     label="محافظ الأسطول"
              :locked="!sub.hasFeature('fleet')" />
          </NavSection>

          <NavSection label="المالية">
            <NavItem to="/wallet"            :icon="CreditCardIcon"   label="المحفظة" />
            <NavItem to="/ledger"            :icon="BookOpenIcon"     label="دفتر الأستاذ" />
            <NavItem to="/chart-of-accounts" :icon="TableCellsIcon"   label="دليل الحسابات" />
          </NavSection>

          <NavSection label="المخزون">
            <NavItem to="/products"  :icon="CubeIcon"        label="المنتجات" />
            <NavItem to="/inventory" :icon="ArchiveBoxIcon"  label="المخزون" />
            <NavItem to="/suppliers" :icon="TruckIcon"       label="الموردون" />
            <NavItem to="/purchases" :icon="ShoppingBagIcon" label="المشتريات" />
          </NavSection>

          <NavSection label="الحوكمة">
            <NavItem to="/governance" :icon="ShieldCheckIcon" label="السياسات والموافقات"
              :locked="!sub.hasFeature('work_orders')" />
            <NavItem
              v-if="auth.isManager && featureFlags.intelligenceCommandCenter"
              to="/internal/intelligence"
              :icon="SignalIcon"
              label="مركز العمليات الذكي"
            />
            <NavItem to="/support" :icon="LifebuoyIcon" label="مركز الدعم الفني" />
          </NavSection>

          <NavSection label="التقارير والامتثال">
            <NavItem to="/reports" :icon="ChartBarIcon" label="التقارير"
              :locked="!sub.hasFeature('reports')" />
            <NavItem to="/zatca" :icon="BuildingOffice2Icon" label="ZATCA الزكاة والضريبة"
              :locked="!sub.hasFeature('zatca')" />
          </NavSection>

          <NavSection v-if="auth.user?.role === 'owner'" label="إدارة المنصة">
            <NavItem to="/admin" :icon="CpuChipIcon" label="لوحة الأدمن" />
          </NavSection>

          <NavSection label="الوقود والإحالات">
            <NavItem to="/fuel"      :icon="FireIcon"    label="إدارة الوقود" />
            <NavItem to="/referrals" :icon="GiftIcon"    label="الإحالات والولاء" />
          </NavSection>

          <NavSection label="الاشتراك">
            <NavItem to="/subscription" :icon="StarIcon"           label="اشتراكي" />
            <NavItem to="/plans"        :icon="RectangleStackIcon" label="الباقات" />
            <NavItem to="/plugins"      :icon="SparklesIcon"       label="سوق الإضافات AI" />
          </NavSection>

          <NavSection label="أخرى">
            <NavItem to="/settings"              :icon="Cog6ToothIcon"        label="الإعدادات" />
            <NavItem to="/settings/integrations" :icon="WrenchScrewdriverIcon" label="التكاملات"
              :locked="!sub.hasFeature('api_access')" />
            <NavItem to="/contracts"             :icon="DocumentCheckIcon"     label="العقود"
              :locked="!sub.hasFeature('invoices')" />
          </NavSection>
        </template>
      </nav>

      <!-- Logout -->
      <div class="border-t border-gray-200 dark:border-slate-700 sticky bottom-0 bg-white dark:bg-slate-800 flex-shrink-0 transition-colors"
        :class="collapsed ? 'p-1.5' : 'p-3'"
      >
        <button @click="auth.logout()"
          class="w-full flex items-center rounded-lg text-sm font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
          :class="collapsed ? 'justify-center p-2' : 'gap-3 px-3 py-2'"
          :title="collapsed ? 'تسجيل الخروج' : ''"
        >
          <ArrowLeftOnRectangleIcon class="w-5 h-5 flex-shrink-0" />
          <span v-show="!collapsed">تسجيل الخروج</span>
        </button>
      </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden min-w-0">
      <!-- Header -->
      <header class="h-16 bg-white dark:bg-slate-800 border-b border-gray-200 dark:border-slate-700 flex items-center justify-between px-4 lg:px-6 sticky top-0 z-10 gap-3 transition-colors">
        <div class="flex items-center gap-3 min-w-0">
          <!-- Mobile Hamburger -->
          <button @click="mobileOpen = !mobileOpen" class="lg:hidden flex items-center justify-center w-9 h-9 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors flex-shrink-0">
            <Bars3Icon class="w-5 h-5 text-gray-600 dark:text-slate-300" />
          </button>
          <h1 class="text-base font-semibold text-gray-900 dark:text-slate-100 truncate">{{ pageTitle }}</h1>
          <span class="hidden sm:block text-xs text-gray-400 dark:text-slate-500 font-normal">|</span>
          <span class="hidden sm:block text-xs text-primary-600 dark:text-primary-400 font-medium whitespace-nowrap">{{ greeting }}، {{ auth.user?.name?.split(' ')[0] }}</span>
        </div>

        <div class="flex items-center gap-2">
          <!-- Command Palette Trigger -->
          <button
            @click="openPalette"
            class="hidden md:flex items-center gap-2 px-3 py-1.5 text-sm text-gray-500 dark:text-slate-400 bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 rounded-lg transition-colors"
          >
            <MagnifyingGlassIcon class="w-4 h-4" />
            <span class="text-xs">بحث...</span>
            <kbd class="text-xs bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 rounded px-1 text-gray-400">Ctrl+K</kbd>
          </button>

          <!-- Language Switcher -->
          <div class="relative" ref="langMenuRef">
            <button @click="langMenuOpen = !langMenuOpen"
              class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-sm text-gray-600 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors">
              <span class="text-base leading-none">{{ locale.langInfo.value.flag }}</span>
              <span class="hidden sm:block text-xs font-medium">{{ locale.langInfo.value.native }}</span>
              <ChevronDownIcon class="w-3 h-3 text-gray-400" />
            </button>
            <div v-if="langMenuOpen"
              class="absolute top-full mt-1 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl shadow-lg z-50 py-1 min-w-[160px]"
              :class="locale.langInfo.value.dir === 'rtl' ? 'left-0' : 'right-0'"
            >
              <button v-for="l in LANGUAGES" :key="l.code"
                @click="locale.setLang(l.code); langMenuOpen = false"
                class="w-full flex items-center gap-2.5 px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors"
                :class="locale.lang.value === l.code ? 'text-primary-600 dark:text-primary-400 font-medium' : 'text-gray-700 dark:text-slate-300'"
              >
                <span class="text-base leading-none">{{ l.flag }}</span>
                <span>{{ l.native }}</span>
                <CheckIcon v-if="locale.lang.value === l.code" class="w-3.5 h-3.5 mr-auto text-primary-600 dark:text-primary-400" />
              </button>
            </div>
          </div>

          <!-- Dark Mode Toggle -->
          <button @click="darkMode.toggle()"
            class="p-2 rounded-lg text-gray-500 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors"
            :title="darkMode.isDark.value ? 'الوضع النهاري' : 'الوضع الليلي'"
          >
            <SunIcon v-if="darkMode.isDark.value" class="w-5 h-5" />
            <MoonIcon v-else class="w-5 h-5" />
          </button>

          <!-- Notifications Bell -->
          <div class="relative">
            <button @click="notifOpen = !notifOpen"
              class="p-2 rounded-lg text-gray-500 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors relative"
              title="الإشعارات"
            >
              <BellIcon class="w-5 h-5" />
              <span v-if="unreadCount > 0"
                class="absolute top-1 right-1 w-2 h-2 rounded-full bg-red-500 ring-2 ring-white dark:ring-slate-800 animate-pulse">
              </span>
            </button>
          </div>

          <!-- Portal Switcher -->
          <PortalSwitcher />

          <!-- Plan badge -->
          <RouterLink to="/subscription"
            class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 rounded-lg text-xs font-medium hover:bg-primary-100 dark:hover:bg-primary-900/50 transition-colors">
            <StarIcon class="w-3.5 h-3.5" />
            {{ sub.planName }}
          </RouterLink>

          <!-- User Avatar -->
          <RouterLink to="/profile" class="flex items-center gap-2 hover:bg-gray-50 dark:hover:bg-slate-700 rounded-lg px-2 py-1 transition-colors">
            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center text-white text-sm font-bold shadow-sm">
              {{ auth.user?.name?.charAt(0) }}
            </div>
            <span class="hidden lg:block text-sm text-gray-700 dark:text-slate-300 font-medium">{{ auth.user?.name }}</span>
          </RouterLink>
        </div>
      </header>

      <!-- Breadcrumbs -->
      <div v-if="breadcrumbs.length > 1" class="px-6 py-2 border-b border-gray-100 dark:border-slate-700 bg-white dark:bg-slate-800">
        <nav class="flex items-center gap-1 text-xs text-gray-400 dark:text-slate-500" aria-label="breadcrumb">
          <template v-for="(crumb, i) in breadcrumbs" :key="crumb.path">
            <RouterLink v-if="i < breadcrumbs.length - 1" :to="crumb.path" class="hover:text-primary-600 transition-colors">{{ crumb.label }}</RouterLink>
            <span v-else class="text-gray-600 font-medium">{{ crumb.label }}</span>
            <ChevronRightIcon v-if="i < breadcrumbs.length - 1" class="w-3 h-3 text-gray-300" />
          </template>
        </nav>
      </div>

      <!-- Page -->
      <main class="flex-1 overflow-auto p-6 bg-gray-50 dark:bg-slate-900 transition-colors">
        <RouterView />
      </main>
    </div>

    <!-- Global Overlays -->
    <CommandPalette ref="paletteRef" />
    <ToastContainer />
  </div>
  <AiAssistant />
  <NotificationCenter />
</template>

<script setup lang="ts">
import { ref, computed, defineComponent, h, watch, onMounted, onUnmounted } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import {
  HomeIcon, DocumentTextIcon, CubeIcon, UsersIcon, ChartBarIcon, Cog6ToothIcon,
  ArrowLeftOnRectangleIcon, TruckIcon, ShoppingCartIcon, ClipboardDocumentIcon,
  BuildingOfficeIcon, CalendarDaysIcon, FireIcon, UserGroupIcon, ClockIcon,
  CurrencyDollarIcon, CreditCardIcon, BookOpenIcon, TableCellsIcon, BanknotesIcon,
  ArchiveBoxIcon, ShoppingBagIcon, ShieldCheckIcon, StarIcon, RectangleStackIcon, LifebuoyIcon,
  MagnifyingGlassIcon, ClipboardDocumentCheckIcon, WrenchScrewdriverIcon, DocumentCheckIcon,
  LockClosedIcon, ChevronRightIcon, ChevronLeftIcon,
  SunIcon, MoonIcon, CheckIcon, ChevronDownIcon,
  BuildingOffice2Icon, CpuChipIcon, GiftIcon, Bars3Icon, BellIcon, SparklesIcon,
  SignalIcon,
} from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'
import { useSubscriptionStore } from '@/stores/subscription'
import { featureFlags } from '@/config/featureFlags'
import { useLocale, LANGUAGES } from '@/composables/useLocale'
import { useDarkMode } from '@/composables/useDarkMode'
import PortalSwitcher from '@/components/PortalSwitcher.vue'
import CommandPalette from '@/components/CommandPalette.vue'
import ToastContainer from '@/components/ToastContainer.vue'
import AiAssistant from '@/components/AiAssistant.vue'
import NotificationCenter from '@/components/NotificationCenter.vue'

const auth = useAuthStore()
const sub  = useSubscriptionStore()
const route = useRoute()
const locale = useLocale()
const darkMode = useDarkMode()
const paletteRef = ref<InstanceType<typeof CommandPalette> | null>(null)
const langMenuOpen = ref(false)
const langMenuRef = ref<HTMLElement | null>(null)
const notifOpen = ref(false)
const unreadCount = ref(0)

const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return 'صباح الخير'
  if (h < 17) return 'مساء الخير'
  return 'مساء النور'
})

const collapsed  = ref(localStorage.getItem('sidebar_collapsed') === 'true')
const mobileOpen = ref(false)
watch(collapsed, v => localStorage.setItem('sidebar_collapsed', String(v)))
watch(() => route.path, () => { mobileOpen.value = false })

function handleClickOutside(e: MouseEvent) {
  if (langMenuRef.value && !langMenuRef.value.contains(e.target as Node)) {
    langMenuOpen.value = false
  }
}
onMounted(() => document.addEventListener('mousedown', handleClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', handleClickOutside))

function openPalette() {
  window.dispatchEvent(new KeyboardEvent('keydown', { ctrlKey: true, key: 'k', bubbles: true }))
}

const flatItems = [
  { to: '/',                     icon: HomeIcon,                label: 'الرئيسية',          locked: false },
  { to: '/pos',                  icon: ShoppingCartIcon,        label: 'نقطة البيع',         locked: false },
  { to: '/invoices',             icon: DocumentTextIcon,        label: 'الفواتير',           locked: false },
  { to: '/work-orders',          icon: ClipboardDocumentIcon,   label: 'أوامر العمل',        locked: !sub.hasFeature('work_orders') },
  { to: '/bays',                 icon: BuildingOfficeIcon,      label: 'مناطق العمل',        locked: !sub.hasFeature('work_orders') },
  { to: '/bookings',             icon: CalendarDaysIcon,        label: 'الحجوزات',           locked: !sub.hasFeature('work_orders') },
  { to: '/customers',            icon: UsersIcon,               label: 'العملاء',            locked: false },
  { to: '/vehicles',             icon: TruckIcon,               label: 'المركبات',           locked: false },
  { to: '/wallet',               icon: CreditCardIcon,          label: 'المحفظة',            locked: false },
  { to: '/products',             icon: CubeIcon,                label: 'المنتجات',           locked: false },
  { to: '/reports',              icon: ChartBarIcon,            label: 'التقارير',           locked: !sub.hasFeature('reports') },
  { to: '/settings',             icon: Cog6ToothIcon,           label: 'الإعدادات',          locked: false },
  {
    to:      '/internal/intelligence',
    icon:    SignalIcon,
    label:   'مركز العمليات',
    locked:  !featureFlags.intelligenceCommandCenter,
  },
]

const NavIconItem = defineComponent({
  props: { to: String, icon: Object, label: String, locked: Boolean },
  setup(props) {
    return () => {
      if (props.locked) {
        return h('div', {
          class: 'flex justify-center p-2 rounded-lg text-gray-300 cursor-not-allowed',
          title: `${props.label} — يتطلب ترقية`,
        }, [h(props.icon as any, { class: 'w-5 h-5' })])
      }
      return h(RouterLink as any, {
        to: props.to,
        class: 'flex justify-center p-2 rounded-lg text-gray-500 hover:bg-primary-50 hover:text-primary-700 transition-colors',
        activeClass: 'bg-primary-50 text-primary-700',
        title: props.label,
      }, () => h(props.icon as any, { class: 'w-5 h-5' }))
    }
  },
})

const NavItem = defineComponent({
  props: { to: String, icon: Object, label: String, exact: Boolean, locked: Boolean },
  setup(props) {
    return () => {
      if (props.locked) {
        return h('div', {
          class: 'flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm font-medium text-gray-300 cursor-not-allowed select-none',
          title: 'يتطلب ترقية الباقة',
        }, [
          h(props.icon as any, { class: 'w-4 h-4 flex-shrink-0' }),
          h('span', { class: 'flex-1' }, props.label),
          h(LockClosedIcon, { class: 'w-3 h-3' }),
        ])
      }
      return h(RouterLink as any, {
        to: props.to,
        class: 'flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-primary-50 hover:text-primary-700 transition-colors',
        activeClass: 'bg-primary-50 text-primary-700',
        exactActiveClass: props.exact ? 'bg-primary-50 text-primary-700' : undefined,
      }, () => [
        h(props.icon as any, { class: 'w-4 h-4 flex-shrink-0' }),
        h('span', {}, props.label),
      ])
    }
  },
})

const NavSection = defineComponent({
  props: { label: String },
  setup(props, { slots }) {
    return () => h('div', { class: 'space-y-0.5' }, [
      h('p', { class: 'px-3 text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1' }, props.label),
      ...(slots.default?.() ?? []),
    ])
  },
})

const pageTitles: Record<string, string> = {
  dashboard: 'الرئيسية', pos: 'نقطة البيع', customers: 'العملاء',
  vehicles: 'المركبات', 'vehicles.show': 'تفاصيل المركبة',
  'work-orders': 'أوامر العمل', 'work-orders.show': 'تفاصيل أمر العمل',
  'work-orders.create': 'أمر عمل جديد', invoices: 'الفواتير',
  'invoices.show': 'تفاصيل الفاتورة', products: 'المنتجات',
  'products.create': 'منتج جديد', inventory: 'المخزون',
  'inventory.units': 'وحدات القياس', 'inventory.reservations': 'الحجوزات',
  suppliers: 'الموردون', purchases: 'المشتريات', reports: 'التقارير',
  fuel: 'إدارة الوقود', referrals: 'الإحالات والولاء',
  ledger: 'دفتر الأستاذ العام', 'ledger.show': 'تفاصيل القيد',
  'chart-of-accounts': 'دليل الحسابات', wallet: 'المحفظة',
  'wallet-transactions': 'معاملات المحفظة',
  'fleet.wallet': 'محافظ الأسطول', 'fleet.verify-plate': 'التحقق من اللوحة',
  'fleet.transactions': 'سجل المعاملات', governance: 'الحوكمة والسياسات',
  'workshop.employees': 'الموظفون', 'workshop.tasks': 'إدارة المهام',
  'workshop.attendance': 'الحضور والانصراف', 'workshop.commissions': 'العمولات',
  bays: 'مناطق العمل', 'bays.heatmap': 'الخريطة الحرارية',
  bookings: 'الحجوزات', plans: 'باقات الاشتراك', subscription: 'اشتراكي',
  settings: 'الإعدادات', 'settings.integrations': 'التكاملات',
  'internal.intelligence': 'مركز العمليات الذكي',
}

const pageTitle = computed(() => pageTitles[route.name as string] ?? 'WorkshopOS')

const breadcrumbMap: Record<string, { label: string; parent?: string }> = {
  dashboard:              { label: 'الرئيسية' },
  pos:                    { label: 'نقطة البيع', parent: 'dashboard' },
  invoices:               { label: 'الفواتير', parent: 'dashboard' },
  'invoices.show':        { label: 'تفاصيل الفاتورة', parent: 'invoices' },
  'work-orders':          { label: 'أوامر العمل', parent: 'dashboard' },
  'work-orders.show':     { label: 'تفاصيل أمر العمل', parent: 'work-orders' },
  products:               { label: 'المنتجات', parent: 'dashboard' },
  'products.create':      { label: 'منتج جديد', parent: 'products' },
  customers:              { label: 'العملاء', parent: 'dashboard' },
  'vehicles.show':        { label: 'تفاصيل المركبة', parent: 'vehicles' },
  vehicles:               { label: 'المركبات', parent: 'customers' },
  settings:               { label: 'الإعدادات', parent: 'dashboard' },
  'settings.integrations':{ label: 'التكاملات', parent: 'settings' },
  bays:                   { label: 'مناطق العمل', parent: 'dashboard' },
  'bays.heatmap':         { label: 'الخريطة الحرارية', parent: 'bays' },
  bookings:               { label: 'الحجوزات', parent: 'bays' },
  reports:                { label: 'التقارير', parent: 'dashboard' },
  ledger:                 { label: 'دفتر الأستاذ', parent: 'dashboard' },
  'internal.intelligence': { label: 'مركز العمليات الذكي', parent: 'dashboard' },
}

const routePaths: Record<string, string> = {
  dashboard: '/', pos: '/pos', invoices: '/invoices', 'work-orders': '/work-orders',
  products: '/products', customers: '/customers', vehicles: '/vehicles',
  settings: '/settings', 'settings.integrations': '/settings/integrations',
  bays: '/bays', 'bays.heatmap': '/bays/heatmap', bookings: '/bookings',
  reports: '/reports', ledger: '/ledger',
  'internal.intelligence': '/internal/intelligence',
}

const breadcrumbs = computed(() => {
  const current = route.name as string
  const chain: { label: string; path: string }[] = []
  let key: string | undefined = current
  while (key) {
    const entry: { label: string; parent?: string } | undefined = breadcrumbMap[key]
    if (!entry) break
    chain.unshift({ label: entry.label, path: routePaths[key] ?? '/' })
    key = entry.parent
  }
  return chain
})
</script>

<style scoped>
.overlay-fade-enter-active, .overlay-fade-leave-active { transition: opacity 0.2s; }
.overlay-fade-enter-from, .overlay-fade-leave-to { opacity: 0; }
</style>
