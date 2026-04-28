<template>
  <div class="app-shell-page space-y-6" dir="rtl">
    <div v-if="customerLoading" class="state-loading py-16">{{ l('جاري تحميل بيانات العميل…', 'Loading customer…') }}</div>
    <div v-else-if="customerError" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-rose-800 text-sm">
      {{ customerError }}
    </div>
    <template v-else-if="customer">
      <CustomerReportHeader
        :customer-name="customer.name ?? '—'"
        :customer-type="String(customer.type ?? 'b2c')"
        :branch-name="branchName"
        :is-active="Boolean(customer.is_active)"
        :last-activity-at="summary?.last_activity_at ?? null"
        :period-from="range.from"
        :period-to="range.to"
        :badges="headerBadges"
        :ar="isAr"
      >
        <template #actions>
          <div class="flex flex-col gap-2 min-w-[200px]">
            <SmartDatePicker
              mode="range"
              :from-value="range.from"
              :to-value="range.to"
              @change="onRangeChange"
            />
            <select v-model="branchId" class="field-sm text-sm">
              <option value="">{{ l('كل الفروع', 'All branches') }}</option>
              <option v-for="b in branches" :key="b.id" :value="String(b.id)">{{ b.name }}</option>
            </select>
          </div>
          <button
            type="button"
            class="btn btn-primary self-start"
            :disabled="pulseLoading"
            @click="reload"
          >
            {{ pulseLoading ? l('جاري التحديث…', 'Refreshing…') : l('تحديث', 'Refresh') }}
          </button>
        </template>
      </CustomerReportHeader>

      <AppliedFiltersBar v-if="appliedFilters" :filters="appliedFilters" :ar="isAr" />

      <CustomerStatusBanner v-if="banner" :tone="banner.tone" :message="banner.message" />

      <div v-if="pulseError" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900 text-sm">
        {{ pulseError }}
      </div>

      <div v-else-if="pulseLoading && !current" class="state-loading py-12">{{ l('جاري تحميل التقرير…', 'Loading report…') }}</div>

      <template v-else-if="summary">
        <section v-if="isEmptyPeriod" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-800/40 px-5 py-8 text-center">
          <p class="text-slate-700 dark:text-slate-200 font-medium">{{ l('لا يوجد نشاط ضمن هذه الفترة', 'No activity in this period') }}</p>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">
            {{ l('غيّر النطاق الزمني أو راجع بيانات العميل والفروع.', 'Change the date range or check branch filters.') }}
          </p>
        </section>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
          <InsightMetricCard
            :label="l('العمليات خلال الفترة', 'Work orders in period')"
            :value="summary.work_orders_in_period"
            :delta-percent="deltas.workOrders"
            :hint="hintForDelta(deltas.workOrders, false)"
            :clickable="true"
            @click="goWorkOrders"
          />
          <InsightMetricCard
            v-if="financialIncluded"
            :label="l('الفواتير خلال الفترة', 'Invoices in period')"
            :value="summary.invoices_in_period"
            :delta-percent="deltas.invoices"
            :hint="hintForDelta(deltas.invoices, false)"
            :clickable="true"
            @click="goInvoices"
          />
          <InsightMetricCard
            v-if="financialIncluded"
            :label="l('المدفوعات خلال الفترة', 'Payments in period')"
            :value="summary.payments_in_period"
            :delta-percent="deltas.payments"
            :hint="hintForDelta(deltas.payments, false)"
            :clickable="true"
            @click="goInvoices"
          />
          <InsightMetricCard
            :label="l('التذاكر المفتوحة', 'Open tickets')"
            :value="summary.tickets_open"
            :hint="summary.tickets_overdue ? overdueHint : ''"
            :clickable="true"
            @click="goSupport"
          />
          <InsightMetricCard
            :label="l('آخر نشاط', 'Last activity')"
            :value="lastActivityLabel"
            :clickable="Boolean(summary.last_activity_at)"
            @click="goBookings"
          />
          <InsightMetricCard
            :label="l('المركبات المرتبطة', 'Linked vehicles')"
            :value="summary.vehicles_count"
            :delta-percent="deltas.vehicles"
            :hint="hintForDelta(deltas.vehicles, false)"
            :clickable="true"
            @click="goVehicles"
          />
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
          <div class="xl:col-span-2 space-y-4">
            <CustomerTrendPanel
              :work-orders="current?.data?.breakdown?.by_time_period?.work_orders ?? []"
              :invoices="current?.data?.breakdown?.by_time_period?.invoices ?? []"
              :financial="financialIncluded"
              :heading="l('مسار النشاط', 'Activity trend')"
              :tab-ops="l('أوامر العمل', 'Work orders')"
              :tab-fin="l('الفواتير', 'Invoices')"
              :caption="trendCaption"
              :ar="isAr"
            />
          </div>
          <div class="space-y-4">
            <CustomerAttentionPanel
              v-if="attentionItems.length"
              :items="attentionItems"
              :title="l('يستحق الانتباه', 'Requires attention')"
              :ar="isAr"
            />
            <section
              v-if="financialIncluded"
              class="rounded-2xl border border-slate-200/80 dark:border-slate-700/70 bg-white dark:bg-slate-900/40 px-5 py-4 shadow-sm"
            >
              <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100 mb-2">
                {{ l('لمحة مالية سريعة', 'Financial snapshot') }}
              </h2>
              <dl class="space-y-2 text-sm text-slate-600 dark:text-slate-300">
                <div class="flex justify-between gap-2">
                  <dt>{{ l('فواتير', 'Invoices') }}</dt>
                  <dd class="font-semibold tabular-nums">{{ summary.invoices_in_period }}</dd>
                </div>
                <div class="flex justify-between gap-2">
                  <dt>{{ l('مدفوعات', 'Payments') }}</dt>
                  <dd class="font-semibold tabular-nums">{{ summary.payments_in_period }}</dd>
                </div>
              </dl>
            </section>
            <section
              v-else
              class="rounded-2xl border border-slate-200/70 dark:border-slate-700/60 bg-slate-50/80 dark:bg-slate-800/30 px-4 py-3 text-sm text-slate-600 dark:text-slate-300"
            >
              {{ l('عرض مالي غير متاح لصلاحياتك — التركيز على مسار التشغيل.', 'Financial block hidden by permissions — operations-focused layout.') }}
            </section>
          </div>
        </div>

        <CustomerBreakdownPanel
          :title="l('تفصيل النشاط', 'Activity breakdown')"
          :wo-title="l('أوامر العمل حسب الحالة', 'Work orders by status')"
          :inv-title="l('الفواتير حسب الحالة', 'Invoices by status')"
          :tk-title="l('التذاكر حسب الحالة', 'Tickets by status')"
          :work-orders="current?.data?.breakdown?.by_status?.work_orders ?? []"
          :invoices="current?.data?.breakdown?.by_status?.invoices ?? []"
          :tickets="current?.data?.breakdown?.by_status?.support_tickets ?? []"
          :financial="financialIncluded"
          :ar="isAr"
        />

        <CustomerRecentActivityTimeline
          :lines="activityLines"
          :title="l('نشاط حديث (مختصر)', 'Recent activity (summary)')"
          :subtitle="l('مُستخرج تلقائياً من مؤشرات الفترة — سيتم ربط سجل الأحداث الكامل لاحقاً.', 'Derived from period metrics — full event feed will link later.')"
          :empty="l('لا عناصر للعرض.', 'Nothing to show.')"
          :ar="isAr"
        />

        <section class="rounded-2xl border border-slate-200/80 dark:border-slate-700/70 bg-white dark:bg-slate-900/40 px-5 py-4 shadow-sm">
          <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100 mb-3">{{ l('انتقال سريع', 'Quick drill-down') }}</h2>
          <div class="flex flex-wrap gap-2">
            <RouterLink
              class="inline-flex items-center rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800"
              :to="{ path: '/work-orders', query: { customer_id: String(customerId) } }"
            >
              {{ l('أوامر العمل', 'Work orders') }}
            </RouterLink>
            <RouterLink
              v-if="financialIncluded"
              class="inline-flex items-center rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800"
              :to="{ path: '/invoices', query: { customer_id: String(customerId) } }"
            >
              {{ l('الفواتير', 'Invoices') }}
            </RouterLink>
            <RouterLink
              class="inline-flex items-center rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800"
              :to="{ path: '/vehicles', query: { customer_id: String(customerId) } }"
            >
              {{ l('المركبات', 'Vehicles') }}
            </RouterLink>
            <RouterLink
              class="inline-flex items-center rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800"
              :to="{ path: '/bookings', query: { customer_id: String(customerId) } }"
            >
              {{ l('المواعيد', 'Bookings') }}
            </RouterLink>
            <RouterLink
              to="/support"
              class="inline-flex items-center rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800"
            >
              {{ l('الدعم', 'Support') }}
            </RouterLink>
          </div>
        </section>
      </template>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import apiClient from '@/lib/apiClient'
import { useLocale } from '@/composables/useLocale'
import { useCustomerPulseReport } from '@/composables/useCustomerPulseReport'
import {
  buildAttentionItems,
  buildDerivedActivityLines,
  buildStatusBanner,
  inferPulseHealth,
  pctDelta,
  trendCaptionFromSeries,
} from '@/utils/customerPulseRules'
import type { CustomerPulseSummary } from '@/types/customerPulseReport'
import CustomerReportHeader from '@/components/customer-reports/CustomerReportHeader.vue'
import CustomerStatusBanner from '@/components/customer-reports/CustomerStatusBanner.vue'
import InsightMetricCard from '@/components/customer-reports/InsightMetricCard.vue'
import CustomerTrendPanel from '@/components/customer-reports/CustomerTrendPanel.vue'
import CustomerBreakdownPanel from '@/components/customer-reports/CustomerBreakdownPanel.vue'
import CustomerRecentActivityTimeline from '@/components/customer-reports/CustomerRecentActivityTimeline.vue'
import CustomerAttentionPanel from '@/components/customer-reports/CustomerAttentionPanel.vue'
import AppliedFiltersBar from '@/components/customer-reports/AppliedFiltersBar.vue'
import SmartDatePicker from '@/components/ui/SmartDatePicker.vue'

const route = useRoute()
const router = useRouter()
const locale = useLocale()
const isAr = computed(() => locale.lang.value === 'ar')
const l = (ar: string, en: string) => (isAr.value ? ar : en)

const customerId = computed(() => {
  const n = Number(route.params.customerId)
  return Number.isFinite(n) && n > 0 ? n : 0
})
interface CustomerRow {
  name?: string | null
  type?: string | null
  is_active?: boolean | null
  branch_id?: number | null
}

const customer = ref<CustomerRow | null>(null)
const customerLoading = ref(true)
const customerError = ref<string | null>(null)
const branches = ref<{ id: number; name: string }[]>([])

const {
  range,
  branchId,
  loading: pulseLoading,
  error: pulseError,
  current,
  financialIncluded,
  prevSummary,
  reload,
  setRange,
} = useCustomerPulseReport(customerId)

async function loadCustomer(): Promise<void> {
  if (!customerId.value) {
    customerError.value = l('معرّف عميل غير صالح', 'Invalid customer id')
    customerLoading.value = false
    return
  }
  customerLoading.value = true
  customerError.value = null
  try {
    const { data } = await apiClient.get(`/customers/${customerId.value}`)
    customer.value = (data as { data?: CustomerRow }).data ?? null
  } catch (e: unknown) {
    customer.value = null
    customerError.value =
      (e as { response?: { data?: { message?: string } } })?.response?.data?.message ??
      l('تعذر تحميل العميل', 'Could not load customer')
  } finally {
    customerLoading.value = false
  }
}

async function loadBranches(): Promise<void> {
  try {
    const { data } = await apiClient.get('/branches', { params: { per_page: 200 } })
    const rows = (data as { data?: { data?: unknown[] } | unknown[] }).data
    const list = Array.isArray(rows) ? rows : (rows as { data?: unknown[] })?.data ?? []
    branches.value = (list as { id: number; name: string }[]).map((b) => ({ id: b.id, name: String(b.name) }))
  } catch {
    branches.value = []
  }
}

onMounted(async () => {
  await Promise.all([loadCustomer(), loadBranches()])
})

watch(customerId, () => {
  void loadCustomer()
})

const branchName = computed(() => {
  const bid = customer.value?.branch_id
  if (bid == null) return null
  const b = branches.value.find((x) => x.id === Number(bid))
  return b?.name ?? null
})

const summary = computed<CustomerPulseSummary | null>(() => current.value?.data?.summary ?? null)
const appliedFilters = computed(() => (current.value?.meta?.filters_applied ?? current.value?.report?.filters) as Record<string, unknown> | undefined)

const deltas = computed(() => {
  const s = summary.value
  const p = prevSummary.value
  if (!s || !p) {
    return { workOrders: null as number | null, invoices: null, payments: null, vehicles: null }
  }
  return {
    workOrders: pctDelta(s.work_orders_in_period, p.work_orders_in_period),
    invoices: pctDelta(s.invoices_in_period, p.invoices_in_period),
    payments: pctDelta(s.payments_in_period, p.payments_in_period),
    vehicles: pctDelta(s.vehicles_count, p.vehicles_count),
  }
})

const health = computed(() => {
  if (!summary.value) return 'no_data' as const
  return inferPulseHealth({
    summary: summary.value,
    financial: financialIncluded.value,
    prevSummary: prevSummary.value,
  })
})

const banner = computed(() => {
  if (!summary.value) return null
  return buildStatusBanner({
    health: health.value,
    summary: summary.value,
    financial: financialIncluded.value,
    ar: isAr.value,
  })
})

const attentionItems = computed(() => {
  if (!summary.value) return []
  return buildAttentionItems(summary.value, financialIncluded.value)
})

const activityLines = computed(() => {
  if (!summary.value) return []
  return buildDerivedActivityLines(summary.value, financialIncluded.value)
})

const trendCaption = computed(() => {
  const rows = current.value?.data?.breakdown?.by_time_period?.work_orders ?? []
  return trendCaptionFromSeries(rows, isAr.value)
})

const isEmptyPeriod = computed(() => {
  if (!summary.value) return false
  const s = summary.value
  const anyOps = s.work_orders_in_period > 0 || s.tickets_open > 0 || s.last_activity_at
  const anyFin = financialIncluded.value && (s.invoices_in_period > 0 || s.payments_in_period > 0)
  return !anyOps && !anyFin
})

const headerBadges = computed(() => {
  const s = summary.value
  if (!s) return []
  const badges: { key: string; text: string; class: string }[] = []
  if (s.work_orders_in_period >= 3) {
    badges.push({ key: 'active', text: l('نشط', 'Active'), class: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200' })
  } else if (s.work_orders_in_period === 0 && (s.last_activity_at || s.tickets_open)) {
    badges.push({ key: 'low', text: l('منخفض النشاط', 'Low activity'), class: 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/35 dark:text-amber-200' })
  }
  if (s.tickets_open > 0) {
    badges.push({ key: 'tk', text: l('تذاكر مفتوحة', 'Open tickets'), class: 'border-primary-200 bg-primary-50 text-primary-900 dark:border-primary-800 dark:bg-primary-950/35 dark:text-primary-200' })
  }
  if (financialIncluded.value && s.invoices_in_period > 0 && s.payments_in_period === 0) {
    badges.push({ key: 'due', text: l('مستحقات', 'Receivables'), class: 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-800 dark:bg-rose-950/35 dark:text-rose-200' })
  }
  return badges
})

const overdueHint = computed(() =>
  summary.value?.tickets_overdue
    ? l(`${summary.value.tickets_overdue} متأخرة`, `${summary.value.tickets_overdue} overdue`)
    : '',
)

const lastActivityLabel = computed(() => {
  if (!summary.value?.last_activity_at) return '—'
  try {
    return new Date(summary.value.last_activity_at).toLocaleString(isAr.value ? 'ar-SA' : 'en-GB', {
      dateStyle: 'medium',
      timeStyle: 'short',
    })
  } catch {
    return summary.value.last_activity_at
  }
})

function onRangeChange(payload: { from: string; to: string }): void {
  setRange(payload.from, payload.to)
  void reload()
}

function hintForDelta(d: number | null, _inv: boolean): string {
  if (d === null) return l('لا مقارنة للفترة السابقة', 'No prior-period baseline')
  if (Math.abs(d) < 5) return l('مستقر تقريباً', 'Roughly stable')
  if (d > 0) return l('أعلى من الفترة السابقة', 'Up vs prior period')
  return l('أقل من الفترة السابقة', 'Down vs prior period')
}

function goWorkOrders(): void {
  void router.push({ path: '/work-orders', query: { customer_id: String(customerId.value) } })
}
function goInvoices(): void {
  void router.push({ path: '/invoices', query: { customer_id: String(customerId.value) } })
}
function goVehicles(): void {
  void router.push({ path: '/vehicles', query: { customer_id: String(customerId.value) } })
}
function goBookings(): void {
  void router.push({ path: '/bookings', query: { customer_id: String(customerId.value) } })
}
function goSupport(): void {
  void router.push('/support')
}
</script>
