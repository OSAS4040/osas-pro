<template>
  <div class="app-shell-page max-w-[1400px] mx-auto space-y-5" dir="rtl">
    <div v-if="loading" class="space-y-3" aria-busy="true">
      <div class="h-28 rounded-3xl bg-slate-100 dark:bg-slate-800 animate-pulse" />
      <div class="h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 animate-pulse" />
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <div v-for="n in 6" :key="n" class="h-24 rounded-2xl bg-slate-100 dark:bg-slate-800 animate-pulse" />
      </div>
    </div>

    <template v-else-if="payload">
      <CustomerProfileHeader
        :kicker="l('مركز العميل', 'Customer hub')"
        :name="payload.customer.name"
        :type-label="typeLabel(payload.customer.type)"
        :customer-id="payload.customer.id"
        :created-line="createdLine"
        :last-activity="lastActivityText"
        :pulse-label="l('لوحة العميل', 'Customer pulse')"
        :work-orders-label="l('أوامر العمل', 'Work orders')"
        :ops-label="l('العمليات', 'Operations')"
        :vehicles-label="l('المركبات', 'Vehicles')"
        :show-pulse="canPulse"
        :show-ops="canOps"
      />

      <CompanyProfileStatusBanner :activity-status="intelHealth" :message="intelBanner" />
      <p v-if="intelIndicatorLine" class="text-xs text-slate-500 dark:text-slate-400 -mt-2">{{ intelIndicatorLine }}</p>

      <div class="grid grid-cols-1 xl:grid-cols-[1fr_280px] gap-5 items-start">
        <div class="space-y-5 min-w-0">
          <CompanyProfileSummaryCards :cards="summaryCards" />

          <div class="flex gap-1 bg-slate-100 dark:bg-slate-800 p-1 rounded-xl overflow-x-auto">
            <button
              v-for="t in tabDefs"
              :key="t.id"
              type="button"
              class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors"
              :class="tab === t.id ? 'bg-white dark:bg-slate-700 shadow text-slate-900 dark:text-slate-100' : 'text-slate-500 dark:text-slate-400'"
              @click="tab = t.id"
            >
              {{ t.label }}
            </button>
          </div>

          <section v-if="tab === 'overview'" class="rounded-2xl border border-slate-200/80 dark:border-slate-700/50 bg-white dark:bg-slate-900/35 p-5 space-y-3">
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ l('مؤشرات السلوك', 'Behavior indicators') }}</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
              <div class="flex justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-2">
                <dt class="text-slate-500">{{ l('مستوى النشاط', 'Activity level') }}</dt>
                <dd class="font-medium">{{ overviewIndicators.activity_level }}</dd>
              </div>
              <div class="flex justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-2">
                <dt class="text-slate-500">{{ l('سلوك الدفع', 'Payment behavior') }}</dt>
                <dd class="font-medium">{{ overviewIndicators.payment_behavior }}</dd>
              </div>
              <div class="flex justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-2">
                <dt class="text-slate-500">{{ l('مستوى التفاعل', 'Engagement level') }}</dt>
                <dd class="font-medium">{{ overviewIndicators.engagement_level }}</dd>
              </div>
              <div class="flex justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-2">
                <dt class="text-slate-500">{{ l('خمول', 'Inactivity flag') }}</dt>
                <dd class="font-medium">{{ payload.behavior_indicators.inactivity_flag ? l('نعم', 'Yes') : l('لا', 'No') }}</dd>
              </div>
              <div class="flex justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-2">
                <dt class="text-slate-500">{{ l('تذاكر مفتوحة', 'Open tickets') }}</dt>
                <dd class="font-medium tabular-nums">{{ payload.summary.tickets_open }}</dd>
              </div>
            </dl>
          </section>

          <section v-if="tab === 'activity'" class="rounded-2xl border border-slate-200/80 dark:border-slate-700/50 bg-white dark:bg-slate-900/35 p-5 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ l('آخر الأحداث', 'Recent activity') }}</h2>
              <RouterLink v-if="canOps" to="/operations/global-feed" class="text-xs font-medium text-primary-600 hover:underline">
                {{ l('تدفق العمليات', 'Operations feed') }} →
              </RouterLink>
            </div>
            <ul class="space-y-2 text-sm">
              <li v-for="row in activityRows" :key="row.key" class="rounded-xl border border-slate-100 dark:border-slate-800 px-3 py-2 flex flex-wrap justify-between gap-2">
                <span class="font-medium text-slate-800 dark:text-slate-200">{{ row.title }}</span>
                <span class="text-slate-500 text-xs">{{ row.when }}</span>
                <span class="w-full text-xs text-slate-600 dark:text-slate-300">{{ row.detail }}</span>
              </li>
            </ul>
            <div>
              <h3 class="text-xs font-semibold text-slate-600 dark:text-slate-300 mb-3">{{ l('خط زمني للنشاط', 'Activity timeline') }}</h3>
              <CustomerProfileActivityTimeline
                :items="timelineItems"
                :empty-label="l('لا أحداث بتاريخ', 'No dated events')"
                :locale-lang="locale.lang.value"
              />
            </div>
          </section>

          <section v-if="tab === 'financial' && financialIncluded" class="rounded-2xl border border-slate-200/80 dark:border-slate-700/50 bg-white dark:bg-slate-900/35 p-5 space-y-4">
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ l('المالية', 'Financial') }}</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
              <div class="flex justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-2">
                <dt class="text-slate-500">{{ l('الفواتير', 'Invoices') }}</dt>
                <dd class="font-medium tabular-nums">{{ payload.summary.invoices_count ?? '—' }}</dd>
              </div>
              <div class="flex justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-2">
                <dt class="text-slate-500">{{ l('الدفعات', 'Payments') }}</dt>
                <dd class="font-medium tabular-nums">{{ payload.summary.payments_count ?? '—' }}</dd>
              </div>
            </dl>
            <div class="flex flex-col gap-2 text-sm">
              <RouterLink
                v-if="payload.activity_snapshot.last_invoice"
                :to="`/invoices?customer_id=${payload.customer.id}`"
                class="text-primary-600 hover:underline"
              >
                {{ l('عرض الفواتير', 'View invoices') }} →
              </RouterLink>
            </div>
          </section>

          <section v-if="tab === 'relationships'" class="space-y-5">
            <div v-if="customerMap" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <RouterLink
                v-if="customerMap.visibility.vehicle_assets"
                :to="`/vehicles?customer_id=${payload.customer.id}`"
                class="rounded-2xl border border-slate-200/80 dark:border-slate-700/50 bg-white dark:bg-slate-900/35 p-4 block hover:border-primary-300 dark:hover:border-primary-700 transition-colors"
              >
                <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400">{{ l('المركبات', 'Vehicles') }}</p>
                <p class="text-2xl font-semibold tabular-nums text-slate-900 dark:text-slate-50 mt-1">{{ customerMap.counts.vehicles }}</p>
                <p class="text-xs text-primary-600 mt-2">{{ l('القائمة', 'List') }} →</p>
              </RouterLink>
              <div
                v-else
                class="rounded-2xl border border-dashed border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-900/25 p-4 text-xs text-slate-500"
              >
                {{ l('تفاصيل الأصول غير متاحة لصلاحيتك.', 'Asset details are not available for your role.') }}
                <p v-if="customerMap.counts.vehicles > 0" class="mt-2 tabular-nums font-medium text-slate-700 dark:text-slate-200">{{ customerMap.counts.vehicles }}</p>
              </div>

              <RouterLink
                v-if="customerMap.visibility.user_directory && auth.isManager"
                to="/settings/team-users"
                class="rounded-2xl border border-slate-200/80 dark:border-slate-700/50 bg-white dark:bg-slate-900/35 p-4 block hover:border-primary-300 dark:hover:border-primary-700 transition-colors"
              >
                <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400">{{ l('مستخدمون مرتبطون', 'Linked users') }}</p>
                <p class="text-2xl font-semibold tabular-nums text-slate-900 dark:text-slate-50 mt-1">{{ customerMap.counts.assigned_users }}</p>
                <p class="text-xs text-primary-600 mt-2">{{ l('الفريق', 'Team') }} →</p>
              </RouterLink>
              <div
                v-else-if="!customerMap.visibility.user_directory"
                class="rounded-2xl border border-dashed border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-900/25 p-4 text-xs text-slate-500"
              >
                {{ l('أسماء المستخدمين مخفية.', 'User names are hidden for your role.') }}
                <p v-if="customerMap.counts.assigned_users > 0" class="mt-2 tabular-nums font-medium text-slate-700 dark:text-slate-200">{{ customerMap.counts.assigned_users }}</p>
              </div>
              <div v-else class="rounded-2xl border border-dashed border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-900/25 p-4 text-xs text-slate-500">
                {{ l('عرض الفريق للمديرين.', 'Open team directory as a manager.') }}
              </div>

              <div class="rounded-2xl border border-slate-200/80 dark:border-slate-700/50 bg-white dark:bg-slate-900/35 p-4">
                <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400">{{ l('العميل', 'Customer') }}</p>
                <p class="text-sm font-semibold text-slate-900 dark:text-slate-50 mt-1 truncate">{{ payload.customer.name }}</p>
                <RouterLink :to="`/work-orders?customer_id=${payload.customer.id}`" class="text-xs text-primary-600 hover:underline mt-2 inline-block">
                  {{ l('أوامر العمل', 'Work orders') }} →
                </RouterLink>
              </div>
            </div>

            <div class="rounded-2xl border border-slate-200/80 dark:border-slate-700/50 bg-white dark:bg-slate-900/35 p-5">
              <h3 class="text-sm font-semibold mb-3">{{ l('أبرز المركبات', 'Top vehicles') }}</h3>
              <ul class="text-sm space-y-2">
                <li v-for="v in payload.relationships.top_vehicles" :key="v.vehicle_id" class="flex justify-between gap-2 items-center">
                  <RouterLink :to="`/vehicles/${v.vehicle_id}`" class="text-primary-600 hover:underline truncate" dir="ltr">{{ v.plate_number }}</RouterLink>
                  <span class="text-slate-500 text-xs truncate text-end">{{ [v.make, v.model].filter(Boolean).join(' ') }}</span>
                </li>
                <li
                  v-if="customerMap?.visibility.vehicle_assets && !payload.relationships.top_vehicles.length && payload.relationships.vehicles_count === 0"
                  class="text-slate-500 text-xs"
                >
                  {{ l('لا مركبات مسجلة لهذا العميل.', 'No vehicles linked to this customer.') }}
                </li>
                <li
                  v-else-if="customerMap?.visibility.vehicle_assets && !payload.relationships.top_vehicles.length"
                  class="text-slate-500 text-xs"
                >
                  {{ l('لا توجد مركبات ضمن نطاق الفروع الحالي.', 'No vehicles in the current branch scope.') }}
                </li>
                <li v-else-if="!customerMap?.visibility.vehicle_assets" class="text-slate-500 text-xs">{{ l('مقيّد بالصلاحيات', 'Restricted by permissions') }}</li>
              </ul>
              <RouterLink
                v-if="customerMap?.visibility.vehicle_assets"
                :to="`/vehicles?customer_id=${payload.customer.id}`"
                class="text-xs text-primary-600 hover:underline mt-3 inline-block"
              >
                {{ l('كل المركبات', 'All vehicles') }} →
              </RouterLink>
            </div>
            <div class="rounded-2xl border border-slate-200/80 dark:border-slate-700/50 bg-white dark:bg-slate-900/35 p-5">
              <h3 class="text-sm font-semibold mb-3">{{ l('الفروع', 'Branches') }}</h3>
              <ul class="text-sm space-y-2">
                <li v-for="b in payload.relationships.branches" :key="b.branch_id" class="flex justify-between gap-2 items-center">
                  <RouterLink
                    v-if="auth.hasPermission('branches.view')"
                    to="/branches"
                    class="text-primary-600 hover:underline truncate"
                  >
                    {{ b.branch_name }}
                  </RouterLink>
                  <RouterLink
                    v-else-if="auth.hasPermission('work_orders.view') || canReports"
                    to="/branches/map"
                    class="text-primary-600 hover:underline truncate"
                  >
                    {{ b.branch_name }}
                  </RouterLink>
                  <span v-else class="truncate">{{ b.branch_name }}</span>
                </li>
                <li v-if="!payload.relationships.branches.length" class="text-slate-500 text-xs">{{ l('لا بيانات', 'No data') }}</li>
              </ul>
            </div>
            <div class="rounded-2xl border border-slate-200/80 dark:border-slate-700/50 bg-white dark:bg-slate-900/35 p-5">
              <h3 class="text-sm font-semibold mb-3">{{ l('المستخدمون المرتبطون', 'Assigned users') }}</h3>
              <ul class="text-sm space-y-2">
                <li v-for="u in payload.relationships.assigned_users" :key="u.user_id" class="flex justify-between gap-2 items-center">
                  <RouterLink v-if="auth.isManager" to="/settings/team-users" class="text-primary-600 hover:underline truncate">{{ u.user_name }}</RouterLink>
                  <span v-else class="truncate">{{ u.user_name }}</span>
                  <span class="text-slate-500 text-xs shrink-0">{{ u.role_hint }}</span>
                </li>
                <li v-if="customerMap?.visibility.user_directory && !payload.relationships.assigned_users.length" class="text-slate-500 text-xs">
                  {{ l('لا مستخدمين مرتبطين بأوامر عمل لهذا العميل.', 'No users linked via work orders for this customer.') }}
                </li>
                <li v-else-if="!customerMap?.visibility.user_directory" class="text-slate-500 text-xs">{{ l('مقيّد بالصلاحيات', 'Restricted by permissions') }}</li>
              </ul>
            </div>
          </section>
        </div>

        <div class="space-y-4">
          <OperationalAttentionList
            v-if="intelAttentionItems.length"
            :title="l('يستحق المتابعة', 'Needs attention')"
            :items="intelAttentionItems"
            :label-for="(it) => attentionItemLabel(it, l)"
          />
          <aside
            v-else
            class="rounded-2xl border border-slate-200/80 dark:border-slate-700/50 bg-white dark:bg-slate-900/40 p-4 text-sm text-slate-600 dark:text-slate-300"
          >
            <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-2">{{ l('يستحق المتابعة', 'Needs attention') }}</h2>
            {{ l('لا عناصر تنبيه حالياً.', 'No attention items right now.') }}
          </aside>
          <p v-if="payload" class="text-[11px] text-slate-500 px-1">
            {{ l('للقراءة فقط — لا تعديل من هذه الشاشة.', 'Read-only hub — no edits from this screen.') }}
          </p>
        </div>
      </div>
    </template>

    <div v-else class="rounded-2xl border border-rose-200 bg-rose-50/90 p-5 text-sm text-rose-900">
      {{ error ?? l('تعذر التحميل', 'Could not load') }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useCustomerProfile } from '@/composables/useCustomerProfile'
import { useLocale } from '@/composables/useLocale'
import CustomerProfileHeader from '@/components/customer-profile/CustomerProfileHeader.vue'
import CustomerProfileActivityTimeline from '@/components/customer-profile/CustomerProfileActivityTimeline.vue'
import CompanyProfileStatusBanner from '@/components/company-profile/CompanyProfileStatusBanner.vue'
import CompanyProfileSummaryCards from '@/components/company-profile/CompanyProfileSummaryCards.vue'
import OperationalAttentionList from '@/components/operational-intelligence/OperationalAttentionList.vue'
import { attentionItemLabel, healthStatusBannerMessage, indicatorHint } from '@/composables/useOperationalIntelligenceDisplay'
import type { CustomerOperationalMap, CustomerProfileActivityItem } from '@/types/customerProfile'

const route = useRoute()
const auth = useAuthStore()
const locale = useLocale()
const l = (ar: string, en: string) => (locale.lang.value === 'ar' ? ar : en)

const customerId = computed(() => Number(route.params.customerId ?? 0))
const { loading, error, payload, financialIncluded, load } = useCustomerProfile(() => customerId.value)

const tab = ref<'overview' | 'activity' | 'financial' | 'relationships'>('overview')

const canPulse = computed(() => auth.hasPermission('reports.view') && auth.hasPermission('reports.operations.view'))
const canOps = computed(() => canPulse.value)
const canReports = computed(() => auth.hasPermission('reports.view'))

const customerMap = computed(() => payload.value?.relationships.operational_map as CustomerOperationalMap | undefined)

watch([financialIncluded, tab], () => {
  if (tab.value === 'financial' && !financialIncluded.value) {
    tab.value = 'overview'
  }
})

const tabDefs = computed(() => {
  const rows: { id: 'overview' | 'activity' | 'financial' | 'relationships'; label: string }[] = [
    { id: 'overview', label: l('نظرة عامة', 'Overview') },
    { id: 'activity', label: l('النشاط', 'Activity') },
  ]
  if (financialIncluded.value) {
    rows.push({ id: 'financial', label: l('المالية', 'Financial') })
  }
  rows.push({ id: 'relationships', label: l('العلاقات', 'Relationships') })
  return rows
})

function typeLabel(t: string): string {
  if (t === 'b2b') return l('شركة (B2B)', 'Business (B2B)')
  if (t === 'b2c') return l('فرد (B2C)', 'Individual (B2C)')
  return t || '—'
}

const createdLine = computed(() => {
  const iso = payload.value?.customer.created_at
  if (!iso) return ''
  try {
    return l(`أُنشئ في ${new Date(iso).toLocaleDateString('ar-SA')}`, `Created ${new Date(iso).toLocaleDateString()}`)
  } catch {
    return ''
  }
})

const lastActivityText = computed(() => {
  const iso = payload.value?.summary.last_activity_at
  if (!iso) return l('لا يوجد نشاط مسجل', 'No recorded activity')
  try {
    return l(`آخر نشاط: ${new Date(iso).toLocaleString('ar-SA')}`, `Last activity: ${new Date(iso).toLocaleString()}`)
  } catch {
    return ''
  }
})

const intellect = computed(() => payload.value?.intelligence)

const intelHealth = computed(() => {
  const h = intellect.value?.health_status
  return typeof h === 'string' && h.length ? h : 'healthy'
})

const intelBanner = computed(() => healthStatusBannerMessage(intellect.value?.health_status ?? intelHealth.value, l))

const intelIndicatorLine = computed(() => indicatorHint(intellect.value, l))

const intelAttentionItems = computed(() => intellect.value?.attention_items ?? [])

const overviewIndicators = computed(() => {
  const p = payload.value
  const i = p?.intelligence?.indicators
  if (i) {
    return {
      activity_level: i.activity_level,
      payment_behavior: i.payment_behavior,
      engagement_level: i.engagement_level,
    }
  }
  return {
    activity_level: p?.behavior_indicators.activity_level ?? '—',
    payment_behavior: p?.behavior_indicators.payment_behavior ?? '—',
    engagement_level: p?.behavior_indicators.engagement_level ?? '—',
  }
})

const summaryCards = computed(() => {
  const p = payload.value
  if (!p) return []
  const inv = financialIncluded.value ? p.summary.invoices_count ?? 0 : l('—', '—')
  const pay = financialIncluded.value ? p.summary.payments_count ?? 0 : l('—', '—')
  return [
    { key: 'wo', label: l('أوامر العمل', 'Work orders'), display: p.summary.work_orders_count },
    { key: 'inv', label: l('الفواتير', 'Invoices'), display: inv },
    { key: 'pay', label: l('الدفعات', 'Payments'), display: pay },
    { key: 'tic', label: l('تذاكر مفتوحة', 'Open tickets'), display: p.summary.tickets_open },
    { key: 'veh', label: l('المركبات', 'Vehicles'), display: p.relationships.vehicles_count },
    { key: 'eng', label: l('التفاعل', 'Engagement'), display: overviewIndicators.value.engagement_level },
  ]
})

function fmtRow(label: string, it: CustomerProfileActivityItem | null): { key: string; title: string; when: string; detail: string } | null {
  if (!it) return null
  const when = it.occurred_at
    ? new Date(it.occurred_at).toLocaleString(locale.lang.value === 'ar' ? 'ar-SA' : undefined)
    : '—'
  return {
    key: label + String(it.id),
    title: label,
    when,
    detail: [it.reference, it.status, it.subtitle].filter(Boolean).join(' · '),
  }
}

const activityRows = computed(() => {
  const p = payload.value
  if (!p) return []
  const rows = [
    fmtRow(l('آخر أمر عمل', 'Last work order'), p.activity_snapshot.last_work_order),
    financialIncluded.value ? fmtRow(l('آخر فاتورة', 'Last invoice'), p.activity_snapshot.last_invoice) : null,
    financialIncluded.value ? fmtRow(l('آخر دفعة', 'Last payment'), p.activity_snapshot.last_payment) : null,
    fmtRow(l('آخر تذكرة', 'Last ticket'), p.activity_snapshot.last_ticket),
  ]
  return rows.filter(Boolean) as { key: string; title: string; when: string; detail: string }[]
})

const timelineItems = computed(() => {
  const p = payload.value
  if (!p) return []
  const base = [
    { kind: 'wo', label: l('أمر عمل', 'Work order'), item: p.activity_snapshot.last_work_order },
    { kind: 'inv', label: l('فاتورة', 'Invoice'), item: p.activity_snapshot.last_invoice },
    { kind: 'pay', label: l('دفعة', 'Payment'), item: p.activity_snapshot.last_payment },
    { kind: 'tic', label: l('تذكرة', 'Ticket'), item: p.activity_snapshot.last_ticket },
  ]
  if (!financialIncluded.value) {
    return base.filter((x) => x.kind !== 'inv' && x.kind !== 'pay')
  }
  return base
})

async function tryLoad(): Promise<void> {
  if (customerId.value < 1) return
  await load()
}

onMounted(() => {
  void tryLoad()
})

watch(customerId, () => {
  void tryLoad()
})
</script>
