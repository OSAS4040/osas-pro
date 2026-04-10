<template>
  <Teleport to="body">
    <Transition name="palette">
      <div
        v-if="open"
        data-print-chrome
        class="print:hidden fixed inset-0 z-[9998] flex items-start justify-center bg-black/50 px-4 pt-24 backdrop-blur-sm"
        :dir="paletteDir"
        @click.self="close"
      >
        <div
          class="w-full max-w-xl overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-2xl dark:border-slate-600 dark:bg-slate-800"
        >
          <div class="flex items-center gap-3 border-b border-gray-100 px-4 py-3.5 dark:border-slate-700">
            <MagnifyingGlassIcon class="h-5 w-5 flex-shrink-0 text-gray-400 dark:text-slate-500" />
            <input
              ref="inputRef"
              v-model="query"
              placeholder="صفحة، تقرير، عميل، لوحة، رقم فاتورة…"
              class="flex-1 bg-transparent text-base text-gray-900 outline-none placeholder:text-gray-400 dark:text-slate-100 dark:placeholder:text-slate-500"
              @keydown.down.prevent="move(1)"
              @keydown.up.prevent="move(-1)"
              @keydown.enter.prevent="confirm"
              @keydown.esc="close"
            />
            <kbd class="rounded border border-gray-200 bg-gray-100 px-1.5 py-0.5 text-xs text-gray-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-400">
              Esc
            </kbd>
          </div>

          <div class="max-h-[min(24rem,70vh)] overflow-y-auto p-2">
            <template v-if="combinedRows.length">
              <div
                v-for="(item, i) in combinedRows"
                :key="item.key"
                class="flex cursor-pointer items-center gap-3 rounded-xl px-3 py-2.5 transition-colors"
                :class="
                  i === cursor
                    ? 'bg-primary-50 text-primary-800 dark:bg-primary-950/50 dark:text-primary-100'
                    : 'text-gray-700 hover:bg-gray-50 dark:text-slate-200 dark:hover:bg-slate-700/80'
                "
                @click="go(item)"
                @mouseenter="cursor = i"
              >
                <component
                  :is="item.icon"
                  class="h-4 w-4 flex-shrink-0"
                  :class="i === cursor ? 'text-primary-600 dark:text-primary-400' : 'text-gray-400 dark:text-slate-500'"
                />
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-medium">{{ item.label }}</p>
                  <p class="truncate text-xs text-gray-400 dark:text-slate-500">
                    {{ item.kind === 'entity' ? item.sub : item.group }}
                    <span v-if="item.kind === 'entity'" class="text-[10px] opacity-80">
                      · {{ entityLabel(item.entity) }}
                    </span>
                  </p>
                </div>
                <ArrowUpLeftIcon v-if="i === cursor" class="h-3.5 w-3.5 flex-shrink-0 text-primary-400" />
              </div>
            </template>
            <div v-else class="py-10 text-center text-sm text-gray-400 dark:text-slate-500">
              <MagnifyingGlassIcon class="mx-auto mb-2 h-8 w-8 text-gray-200 dark:text-slate-600" />
              <template v-if="entityLoading">جاري البحث في السجلات…</template>
              <template v-else>لا توجد نتائج لـ «{{ query }}»</template>
            </div>
          </div>

          <div
            class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-gray-100 bg-gray-50 px-4 py-2.5 text-xs text-gray-400 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-500"
          >
            <span class="flex items-center gap-1">
              <kbd class="rounded border border-gray-200 bg-white px-1 dark:border-slate-600 dark:bg-slate-800">↑↓</kbd>
              تنقل
            </span>
            <span class="flex items-center gap-1">
              <kbd class="rounded border border-gray-200 bg-white px-1 dark:border-slate-600 dark:bg-slate-800">Enter</kbd>
              فتح
            </span>
            <span class="flex items-center gap-1">
              <kbd class="rounded border border-gray-200 bg-white px-1 dark:border-slate-600 dark:bg-slate-800">Esc</kbd>
              إغلاق
            </span>
            <span class="mr-auto flex items-center gap-1">
              <kbd class="rounded border border-gray-200 bg-white px-1.5 dark:border-slate-600 dark:bg-slate-800">Ctrl</kbd>
              +
              <kbd class="rounded border border-gray-200 bg-white px-1.5 dark:border-slate-600 dark:bg-slate-800">K</kbd>
              لوحة الأوامر
            </span>
            <span v-if="normQuery" class="w-full text-[10px] text-gray-400 dark:text-slate-600 sm:w-auto sm:mr-0">
              يبحث في الصفحات + العملاء والمركبات والفواتير (من الخادم)
            </span>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import type { Component } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { watchDebounced } from '@vueuse/core'
import { useAuthStore } from '@/stores/auth'
import { useBusinessProfileStore } from '@/stores/businessProfile'
import { featureFlags } from '@/config/featureFlags'
import {
  canAccessStaffBusinessIntelligence,
  canAccessStaffCommandCenter,
  canAccessWorkshopArea,
  tenantSectionOpen,
} from '@/config/staffFeatureGate'
import { enabledPortals } from '@/config/portalAccess'
import { NAV_SEARCH_ITEMS } from '@/config/navSearchItems'
import { useLocale } from '@/composables/useLocale'
import { foldSearchText, textMatchScore, routeContextBoost } from '@/utils/commandPaletteSearch'
import apiClient from '@/lib/apiClient'
import {
  MagnifyingGlassIcon,
  ArrowUpLeftIcon,
  HomeIcon,
  DocumentTextIcon,
  CubeIcon,
  UsersIcon,
  ChartBarIcon,
  Cog6ToothIcon,
  TruckIcon,
  ShoppingCartIcon,
  ClipboardDocumentIcon,
  BuildingOfficeIcon,
  CalendarDaysIcon,
  FireIcon,
  UserGroupIcon,
  ClockIcon,
  CurrencyDollarIcon,
  CreditCardIcon,
  BookOpenIcon,
  BanknotesIcon,
  TableCellsIcon,
  ArchiveBoxIcon,
  ShoppingBagIcon,
  ShieldCheckIcon,
  StarIcon,
  MagnifyingGlassCircleIcon,
  WrenchScrewdriverIcon,
  PresentationChartLineIcon,
  ClipboardDocumentListIcon,
  AdjustmentsHorizontalIcon,
  CpuChipIcon,
  BuildingLibraryIcon,
  MapPinIcon,
  SparklesIcon,
  GiftIcon,
  HeartIcon,
  ScaleIcon,
  BuildingOffice2Icon,
  ArrowsRightLeftIcon,
  CalendarIcon,
  InformationCircleIcon,
  QueueListIcon,
} from '@heroicons/vue/24/outline'

type PaletteNavItem = {
  label: string
  to: string
  icon: Component
  group: string
  requiresPermission?: string
  requiresAnyPermission?: string[]
  /** فريق العمل (موظف/مدير/مالك) — يستبعد عميل الأسطول وبوابة العميل */
  requiresStaff?: boolean
}

type RouteRow = {
  kind: 'route'
  key: string
  label: string
  group: string
  to: string
  icon: Component
  kw: string
  score: number
}

type EntityRow = {
  kind: 'entity'
  key: string
  label: string
  sub: string
  to: string
  icon: Component
  entity: 'customer' | 'vehicle' | 'invoice'
}

type PaletteRow = RouteRow | EntityRow

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const biz = useBusinessProfileStore()
const locale = useLocale()

const paletteDir = computed(() => locale.langInfo.value.dir)

const open = ref(false)
const query = ref('')
const cursor = ref(0)
const inputRef = ref<HTMLInputElement | null>(null)
const entityHits = ref<EntityRow[]>([])
const entityLoading = ref(false)

const kwByPath = new Map<string, string>(
  NAV_SEARCH_ITEMS.map((i) => [i.to, [...(i.keywords ?? []), i.section].join(' ')]),
)

const allItems: PaletteNavItem[] = [
  { label: 'الرئيسية', to: '/', icon: HomeIcon, group: 'الرئيسي' },
  { label: 'نقطة البيع', to: '/pos', icon: ShoppingCartIcon, group: 'الرئيسي' },
  { label: 'الفواتير', to: '/invoices', icon: DocumentTextIcon, group: 'الرئيسي' },
  { label: 'عروض الأسعار', to: '/crm/quotes', icon: DocumentTextIcon, group: 'الرئيسي' },
  { label: 'أوامر العمل', to: '/work-orders', icon: ClipboardDocumentIcon, group: 'الرئيسي' },
  { label: 'الرافعات والمنافذ', to: '/bays', icon: BuildingOfficeIcon, group: 'مركز الخدمة' },
  { label: 'المواعيد والحجوزات', to: '/bookings', icon: CalendarDaysIcon, group: 'مركز الخدمة' },
  {
    label: 'الاجتماعات',
    to: '/meetings',
    icon: CalendarIcon,
    group: 'مركز الخدمة',
    requiresPermission: 'meetings.update',
  },
  { label: 'الخريطة الحرارية', to: '/bays/heatmap', icon: FireIcon, group: 'مركز الخدمة' },
  { label: 'مسير الرواتب', to: '/workshop/salaries', icon: BanknotesIcon, group: 'الموارد البشرية' },
  { label: 'الإجازات', to: '/workshop/leaves', icon: CalendarDaysIcon, group: 'الموارد البشرية' },
  {
    label: 'مركز العمليات الذكي',
    to: '/internal/intelligence',
    icon: CpuChipIcon,
    group: 'الذكاء',
    requiresPermission: 'reports.intelligence.view',
  },
  { label: 'الموظفون', to: '/workshop/employees', icon: UserGroupIcon, group: 'الموارد البشرية' },
  { label: 'المهام', to: '/workshop/tasks', icon: ClipboardDocumentIcon, group: 'الموارد البشرية' },
  { label: 'الحضور والانصراف', to: '/workshop/attendance', icon: ClockIcon, group: 'الموارد البشرية' },
  { label: 'العمولات', to: '/workshop/commissions', icon: CurrencyDollarIcon, group: 'الموارد البشرية' },
  {
    label: 'سياسات العمولات',
    to: '/workshop/commission-policies',
    icon: AdjustmentsHorizontalIcon,
    group: 'الموارد البشرية',
  },
  { label: 'الاتصالات الإدارية', to: '/workshop/hr-comms', icon: UsersIcon, group: 'الموارد البشرية' },
  { label: 'العملاء', to: '/customers', icon: UsersIcon, group: 'العملاء' },
  { label: 'علاقات العملاء', to: '/crm/relations', icon: HeartIcon, group: 'العملاء' },
  { label: 'المركبات', to: '/vehicles', icon: TruckIcon, group: 'العملاء' },
  { label: 'التحقق من اللوحة', to: '/fleet/verify-plate', icon: MagnifyingGlassIcon, group: 'الأسطول' },
  { label: 'محافظ الأسطول', to: '/fleet/wallet', icon: CreditCardIcon, group: 'الأسطول' },
  { label: 'المحفظة', to: '/wallet', icon: CreditCardIcon, group: 'المالية والمحاسبة' },
  {
    label: 'طلبات شحن الرصيد',
    to: '/wallet/top-up-requests',
    icon: QueueListIcon,
    group: 'المالية والمحاسبة',
    requiresAnyPermission: [
      'wallet.top_up_requests.create',
      'wallet.top_up_requests.view',
      'wallet.top_up_requests.review',
    ],
  },
  {
    label: 'المطابقة المالية',
    to: '/financial-reconciliation',
    icon: ArrowsRightLeftIcon,
    group: 'المالية والمحاسبة',
    requiresPermission: 'reports.financial.view',
  },
  { label: 'دفتر الأستاذ', to: '/ledger', icon: BookOpenIcon, group: 'المالية والمحاسبة' },
  { label: 'دليل الحسابات', to: '/chart-of-accounts', icon: TableCellsIcon, group: 'المالية والمحاسبة' },
  { label: 'ZATCA', to: '/zatca', icon: ScaleIcon, group: 'الامتثال' },
  { label: 'المنتجات', to: '/products', icon: CubeIcon, group: 'المخزون' },
  { label: 'المخزون', to: '/inventory', icon: ArchiveBoxIcon, group: 'المخزون' },
  { label: 'الموردون', to: '/suppliers', icon: TruckIcon, group: 'المخزون' },
  { label: 'المشتريات', to: '/purchases', icon: ShoppingBagIcon, group: 'المخزون' },
  { label: 'الحوكمة والسياسات', to: '/governance', icon: ShieldCheckIcon, group: 'الحوكمة' },
  { label: 'مستندات المنشأة', to: '/documents/company', icon: DocumentTextIcon, group: 'الحوكمة' },
  {
    label: 'ذكاء الأعمال',
    to: '/business-intelligence',
    icon: PresentationChartLineIcon,
    group: 'التقارير',
  },
  { label: 'التقارير', to: '/reports', icon: ChartBarIcon, group: 'التقارير' },
  { label: 'إدارة الفروع', to: '/branches', icon: BuildingLibraryIcon, group: 'الفروع' },
  { label: 'خريطة الفروع', to: '/branches/map', icon: MapPinIcon, group: 'الفروع' },
  { label: 'الإعدادات', to: '/settings', icon: Cog6ToothIcon, group: 'أخرى' },
  { label: 'حسابات الفريق', to: '/settings/team-users', icon: UserGroupIcon, group: 'أخرى' },
  { label: 'هيكل القطاعات', to: '/settings/org-units', icon: BuildingOffice2Icon, group: 'أخرى' },
  { label: 'التكاملات', to: '/settings/integrations', icon: WrenchScrewdriverIcon, group: 'أخرى' },
  { label: 'اشتراكي', to: '/subscription', icon: StarIcon, group: 'الاشتراك' },
  { label: 'الباقات', to: '/plans', icon: StarIcon, group: 'الاشتراك' },
  { label: 'سوق الإضافات', to: '/plugins', icon: SparklesIcon, group: 'الاشتراك' },
  { label: 'الإحالات والولاء', to: '/referrals', icon: GiftIcon, group: 'العمليات' },
  { label: 'مركز الدعم', to: '/support', icon: MagnifyingGlassCircleIcon, group: 'أخرى' },
  { label: 'سجل العمليات', to: '/activity', icon: ClipboardDocumentListIcon, group: 'أخرى' },
  {
    label: 'قدرات النظام',
    to: '/about/capabilities',
    icon: InformationCircleIcon,
    group: 'النظام والمساعدة',
    requiresStaff: true,
  },
  {
    label: 'مسرد المنصة والمستأجر',
    to: '/about/taxonomy',
    icon: BookOpenIcon,
    group: 'النظام والمساعدة',
    requiresStaff: true,
  },
]

function norm(s: string): string {
  return foldSearchText(s)
}

const normQuery = computed(() => norm(query.value))

function roleAllowed(item: PaletteNavItem): boolean {
  if (item.requiresStaff === true && !auth.isStaff) return false
  if (item.requiresPermission && !auth.hasPermission(item.requiresPermission)) return false
  if (item.requiresAnyPermission?.length) {
    const ok = item.requiresAnyPermission.some((p) => auth.hasPermission(p))
    if (!ok) return false
  }
  if (item.to === '/branches' && !auth.isManager) return false
  if (
    (item.to === '/settings'
      || item.to === '/settings/integrations'
      || item.to === '/settings/org-units'
      || item.to === '/settings/team-users')
    && !auth.isManager
  ) {
    return false
  }
  if (item.to === '/settings/org-units' && !tenantSectionOpen(auth.isOwner, (k) => biz.isEnabled(k), 'org_structure')) {
    return false
  }
  if (item.to === '/business-intelligence') {
    if (
      !canAccessStaffBusinessIntelligence({
        buildFlagOn: featureFlags.intelligenceCommandCenter,
        isOwner: auth.isOwner,
        isEnabled: (k) => biz.isEnabled(k),
      })
    ) {
      return false
    }
  }
  if (item.to === '/internal/intelligence') {
    if (
      !canAccessStaffCommandCenter({
        buildFlagOn: featureFlags.intelligenceCommandCenter,
        isOwner: auth.isOwner,
        isEnabled: (k) => biz.isEnabled(k),
        hasIntelligenceReportPermission: auth.hasPermission('reports.intelligence.view'),
      })
    ) {
      return false
    }
  }
  if (
    item.to.startsWith('/bays') ||
    item.to.startsWith('/bookings') ||
    item.to.startsWith('/meetings')
  ) {
    if (!tenantSectionOpen(auth.isOwner, (k) => biz.isEnabled(k), 'operations')) return false
  }
  if (item.to.startsWith('/workshop') && !canAccessWorkshopArea(auth.isOwner, (k) => biz.isEnabled(k))) {
    return false
  }
  if (item.to === '/branches/map' && !auth.isStaff) return false
  return true
}

function portalAllowed(item: PaletteNavItem): boolean {
  if (item.to.startsWith('/fleet/') && !enabledPortals.fleet) return false
  if (item.to.startsWith('/admin') && !enabledPortals.admin) return false
  return true
}

const scoredRoutes = computed((): RouteRow[] => {
  void locale.lang.value
  void biz.loaded
  void biz.effectiveFeatureMatrix
  const q = normQuery.value
  const path = route.path
  const routeLabel = (to: string, fallback: string) => {
    if (to === '/settings/team-users') return locale.t('teamUsers.nav')
    if (to === '/settings/org-units') return locale.t('orgUnits.nav')
    if (to === '/about/capabilities') return locale.t('pages.capabilities')
    if (to === '/about/taxonomy') return locale.t('pages.taxonomy')
    return fallback
  }
  const pool = allItems
    .filter((i) => roleAllowed(i) && portalAllowed(i))
    .map((i) => ({ ...i, label: routeLabel(i.to, i.label) }))

  const rows: RouteRow[] = pool.map((item) => {
    const kw = kwByPath.get(item.to) ?? ''
    const ctx = routeContextBoost(path, item.to, item.group)
    if (!q) {
      return {
        kind: 'route',
        key: `r:${item.to}`,
        label: item.label,
        group: item.group,
        to: item.to,
        icon: item.icon,
        kw,
        score: ctx + 2,
      }
    }
    const tm = textMatchScore(item.label, item.group, item.to, kw, query.value)
    const starts = foldSearchText(item.label).startsWith(q) ? 8 : 0
    return {
      kind: 'route',
      key: `r:${item.to}`,
      label: item.label,
      group: item.group,
      to: item.to,
      icon: item.icon,
      kw,
      score: tm * 10 + ctx + starts,
    }
  })

  if (!q) {
    return rows.sort((a, b) => b.score - a.score).slice(0, 14)
  }
  return rows
    .filter((r) => textMatchScore(r.label, r.group, r.to, r.kw, query.value) > 0)
    .sort((a, b) => b.score - a.score)
    .slice(0, 18)
})

const combinedRows = computed((): PaletteRow[] => {
  const q = normQuery.value
  const ent = entityHits.value
  const routes = scoredRoutes.value
  if (!q) {
    return [...routes] as PaletteRow[]
  }
  return [...ent, ...routes] as PaletteRow[]
})

watch(combinedRows, (r) => {
  if (cursor.value >= r.length) cursor.value = Math.max(0, r.length - 1)
})

watch(query, () => {
  cursor.value = 0
})

function extractPaginated(data: unknown): any[] {
  const d = (data as { data?: unknown })?.data
  return Array.isArray(d) ? d : (d as { data?: any[] })?.data ?? []
}

async function runEntitySearch(q: string): Promise<void> {
  const nq = norm(q)
  if (nq.length < 2) {
    entityHits.value = []
    entityLoading.value = false
    return
  }
  entityLoading.value = true
  try {
    const [c, v, inv] = await Promise.allSettled([
      apiClient.get('/customers', { params: { search: q.trim(), per_page: 6 } }),
      apiClient.get('/vehicles', { params: { search: q.trim(), per_page: 6 } }),
      apiClient.get('/invoices', { params: { search: q.trim(), per_page: 6 } }),
    ])
    const rows: EntityRow[] = []
    if (c.status === 'fulfilled') {
      for (const x of extractPaginated(c.value.data)) {
        const name = String(x.name ?? x.name_ar ?? `عميل #${x.id}`)
        rows.push({
          kind: 'entity',
          key: `e:c:${x.id}`,
          entity: 'customer',
          label: name,
          sub: [x.phone, x.email].filter(Boolean).join(' · ') || 'بدون بيانات اتصال',
          to: `/customers?search=${encodeURIComponent(name)}`,
          icon: UsersIcon,
        })
      }
    }
    if (v.status === 'fulfilled') {
      for (const x of extractPaginated(v.value.data)) {
        rows.push({
          kind: 'entity',
          key: `e:v:${x.id}`,
          entity: 'vehicle',
          label: String(x.plate_number ?? `#${x.id}`),
          sub: [x.make, x.model, x.customer?.name].filter(Boolean).join(' · ') || 'مركبة',
          to: `/vehicles/${x.id}`,
          icon: TruckIcon,
        })
      }
    }
    if (inv.status === 'fulfilled') {
      for (const x of extractPaginated(inv.value.data)) {
        rows.push({
          kind: 'entity',
          key: `e:i:${x.id}`,
          entity: 'invoice',
          label: String(x.invoice_number ?? `فاتورة #${x.id}`),
          sub: x.customer?.name ?? x.customer_name ?? 'فاتورة',
          to: `/invoices/${x.id}`,
          icon: DocumentTextIcon,
        })
      }
    }
    entityHits.value = rows.slice(0, 14)
  } catch {
    entityHits.value = []
  } finally {
    entityLoading.value = false
  }
}

watchDebounced(
  query,
  (q) => {
    if (open.value) runEntitySearch(q)
  },
  { debounce: 320, maxWait: 800 },
)

watch(open, (v) => {
  if (v) {
    nextTick(() => inputRef.value?.focus())
    runEntitySearch(query.value)
  } else {
    entityHits.value = []
    entityLoading.value = false
  }
})

function entityLabel(e: EntityRow['entity']): string {
  if (e === 'customer') return 'عميل'
  if (e === 'vehicle') return 'مركبة'
  return 'فاتورة'
}

function move(dir: number) {
  const n = combinedRows.value.length
  if (!n) return
  cursor.value = (cursor.value + dir + n) % n
}

function go(item: PaletteRow) {
  router.push(item.to)
  close()
}

function confirm() {
  const row = combinedRows.value[cursor.value]
  if (row) go(row)
}

function close() {
  open.value = false
  query.value = ''
  cursor.value = 0
  entityHits.value = []
}

function handleKeydown(e: KeyboardEvent) {
  if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
    e.preventDefault()
    open.value = !open.value
    if (open.value) nextTick(() => inputRef.value?.focus())
  }
}

onMounted(() => window.addEventListener('keydown', handleKeydown))
onUnmounted(() => window.removeEventListener('keydown', handleKeydown))

defineExpose({ open: () => { open.value = true }, close })
</script>

<style scoped>
.palette-enter-active {
  transition: all 0.2s ease-out;
}
.palette-leave-active {
  transition: all 0.15s ease-in;
}
.palette-enter-from,
.palette-leave-to {
  opacity: 0;
}
.palette-enter-from .max-w-xl,
.palette-leave-to .max-w-xl {
  transform: scale(0.97) translateY(-8px);
}
</style>
