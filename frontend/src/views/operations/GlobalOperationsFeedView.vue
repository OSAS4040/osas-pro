<template>
  <div class="app-shell-page max-w-[1600px] mx-auto space-y-5" dir="rtl">
    <OperationsFeedHeader
      kicker="Reporting · Operations"
      :title="l('تدفق العمليات', 'Global Operations Feed')"
      :subtitle="l('نشاط تشغيلي موحّد عبر المنصة — للقراءة فقط', 'Real-time operational activity across the platform — read-only')"
      :last-updated="lastUpdatedText"
      :period-text="periodText"
      :result-count="resultCountText"
      :chips="filterChips"
    >
      <template #actions>
        <button type="button" class="btn btn-primary text-sm" :disabled="loading" @click="refresh">
          {{ l('تحديث', 'Refresh') }}
        </button>
        <button type="button" class="btn text-sm border border-slate-300 dark:border-slate-600" @click="scrollToFilters">
          {{ l('تغيير الفترة', 'Change period') }}
        </button>
        <button type="button" class="btn text-sm border border-slate-300 dark:border-slate-600" @click="clearFilters">
          {{ l('مسح الفلاتر', 'Clear filters') }}
        </button>
        <button type="button" class="lg:hidden btn text-sm border border-slate-300 dark:border-slate-600" @click="filtersOpen = !filtersOpen">
          {{ l('الفلاتر', 'Filters') }}
        </button>
      </template>
    </OperationsFeedHeader>

    <CompanyProfileStatusBanner
      v-if="intelligence"
      class="no-print"
      :activity-status="String(intelligence.health_status)"
      :message="feedHealthBanner"
    />
    <p v-if="intelligence && feedIndicatorLine" class="text-xs text-slate-500 dark:text-slate-400 -mt-2 no-print">{{ feedIndicatorLine }}</p>

    <OperationsFeedAttentionStrip
      v-if="summary"
      :summary="summary"
      :title="l('يستحق المتابعة', 'Needs attention')"
      :filter-label="l('عرض العناصر المهمة', 'Show important items')"
      @filter-attention="onAttentionFilter"
    />

    <OperationalAttentionList
      v-if="intelligence && feedAttentionItems.length"
      class="no-print"
      :title="l('ذكاء تشغيلي (ملخص)', 'Operational intelligence (summary)')"
      :items="feedAttentionItems"
      :label-for="(it) => attentionItemLabel(it, l)"
    />

    <OperationsFeedSummaryCards v-if="summary" :cards="summaryCards" />

    <div v-if="loading" class="space-y-3" aria-busy="true">
      <div v-for="n in 4" :key="n" class="h-24 rounded-2xl bg-slate-100/90 dark:bg-slate-800/60 animate-pulse" />
    </div>

    <OperationsFeedErrorState
      v-else-if="error"
      :message="l('تعذر تحميل البيانات. حاول التحديث.', 'Could not load data. Try refresh.')"
      :retry="l('إعادة المحاولة', 'Retry')"
      @retry="refresh"
    />

    <div v-else class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_280px] gap-5 items-start">
      <div class="space-y-6 min-w-0">
        <template v-if="items.length">
          <OperationsFeedTimelineGroup
            v-if="grouped.today.length"
            :label="l('اليوم', 'Today')"
            :items="grouped.today"
            :detail-label="l('التفاصيل', 'Details')"
            :financial-included="financialIncluded"
          />
          <OperationsFeedTimelineGroup
            v-if="grouped.yesterday.length"
            :label="l('أمس', 'Yesterday')"
            :items="grouped.yesterday"
            :detail-label="l('التفاصيل', 'Details')"
            :financial-included="financialIncluded"
          />
          <OperationsFeedTimelineGroup
            v-if="grouped.earlier.length"
            :label="l('سابقاً', 'Earlier')"
            :items="grouped.earlier"
            :detail-label="l('التفاصيل', 'Details')"
            :financial-included="financialIncluded"
          />
        </template>
        <OperationsFeedEmptyState
          v-else
          :title="l('لا توجد عمليات ضمن الفلاتر الحالية', 'No operations in the current filters')"
          :hint="l('جرّب توسيع الفترة أو إزالة بعض الفلاتر.', 'Try widening the date range or removing filters.')"
        />

        <div v-if="pagination && pagination.last_page > 1" class="flex flex-wrap items-center justify-between gap-2 text-xs">
          <span class="text-slate-500">
            {{ l('صفحة', 'Page') }} {{ pagination.page }} / {{ pagination.last_page }}
          </span>
          <div class="flex gap-2">
            <button type="button" class="btn border border-slate-300 text-xs py-1" :disabled="pagination.page <= 1" @click="prevPage">
              {{ l('السابق', 'Prev') }}
            </button>
            <button
              type="button"
              class="btn border border-slate-300 text-xs py-1"
              :disabled="pagination.page >= pagination.last_page"
              @click="nextPage"
            >
              {{ l('التالي', 'Next') }}
            </button>
          </div>
        </div>
      </div>

      <div :class="filtersOpen ? 'block' : 'hidden lg:block'" id="operations-feed-filters">
        <OperationsFeedFilterSidebar
          :from="from"
          :to="to"
          :branch-id="branchId"
          :customer-id="customerId"
          :user-id="userId"
          :types="types"
          :attention-level="attentionLevel"
          :include-financial="includeFinancial"
          :per-page="perPage"
          :collapsed="false"
          :title="l('فلاتر', 'Filters')"
          :from-label="l('من', 'From')"
          :to-label="l('إلى', 'To')"
          :branch-label="l('معرّف الفرع', 'Branch ID')"
          :customer-label="l('معرّف العميل', 'Customer ID')"
          :user-label="l('معرّف المستخدم', 'User ID')"
          :types-label="l('الأنواع', 'Types')"
          :attention-label="l('مستوى الانتباه', 'Attention level')"
          :include-financial-label="l('تضمين المؤشرات المالية (إن سُمح)', 'Include financial metrics (if permitted)')"
          :per-page-label="l('لكل صفحة', 'Per page')"
          :apply-label="l('تطبيق', 'Apply')"
          :any="l('أي', 'Any')"
          :optional="l('اختياري', 'Optional')"
          :legend-title="l('دليل سريع', 'Quick legend')"
          :legend-hint="l('المبالغ تظهر فقط عند وجود صلاحية مالية.', 'Amounts appear only when financial permission is granted.')"
          :collapse-label="l('إغلاق', 'Close')"
          @apply="onSidebarApply"
          @toggle-collapse="filtersOpen = false"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useGlobalOperationsFeed } from '@/composables/useGlobalOperationsFeed'
import { useLocale } from '@/composables/useLocale'
import { groupOperationsFeedByDay } from '@/utils/groupOperationsFeedByDay'
import CompanyProfileStatusBanner from '@/components/company-profile/CompanyProfileStatusBanner.vue'
import OperationalAttentionList from '@/components/operational-intelligence/OperationalAttentionList.vue'
import { attentionItemLabel, healthStatusBannerMessage, indicatorHint } from '@/composables/useOperationalIntelligenceDisplay'
import OperationsFeedHeader from '@/components/operations-feed/OperationsFeedHeader.vue'
import OperationsFeedSummaryCards from '@/components/operations-feed/OperationsFeedSummaryCards.vue'
import OperationsFeedAttentionStrip from '@/components/operations-feed/OperationsFeedAttentionStrip.vue'
import OperationsFeedFilterSidebar from '@/components/operations-feed/OperationsFeedFilterSidebar.vue'
import OperationsFeedTimelineGroup from '@/components/operations-feed/OperationsFeedTimelineGroup.vue'
import OperationsFeedEmptyState from '@/components/operations-feed/OperationsFeedEmptyState.vue'
import OperationsFeedErrorState from '@/components/operations-feed/OperationsFeedErrorState.vue'

const locale = useLocale()
const l = (ar: string, en: string) => (locale.lang.value === 'ar' ? ar : en)

const {
  from,
  to,
  branchId,
  customerId,
  userId,
  types,
  attentionLevel,
  includeFinancial,
  page,
  perPage,
  loading,
  error,
  envelope,
  items,
  summary,
  intelligence,
  pagination,
  financialIncluded,
  fetchFeed,
  resetFilters,
} = useGlobalOperationsFeed()

const filtersOpen = ref(false)

const grouped = computed(() => groupOperationsFeedByDay(items.value))

const feedHealthBanner = computed(() => healthStatusBannerMessage(intelligence.value?.health_status, l))

const feedIndicatorLine = computed(() => indicatorHint(intelligence.value, l))

const feedAttentionItems = computed(() => intelligence.value?.attention_items ?? [])

const lastUpdatedText = computed(() => {
  const g = envelope.value?.meta?.generated_at
  if (!g) return ''
  try {
    return l(`آخر تحديث: ${new Date(g).toLocaleString('ar-SA')}`, `Last updated: ${new Date(g).toLocaleString()}`)
  } catch {
    return ''
  }
})

const periodText = computed(() => l(`الفترة: ${from.value} — ${to.value}`, `Period: ${from.value} — ${to.value}`))

const resultCountText = computed(() => {
  const t = pagination.value?.total
  if (t == null) return null
  return l(`النتائج: ${t}`, `Results: ${t}`)
})

const filterChips = computed(() => {
  const raw = envelope.value?.meta?.filters_applied
  if (!raw || typeof raw !== 'object') return []
  const chips: string[] = []
  for (const [k, v] of Object.entries(raw)) {
    if (v === null || v === undefined || v === '') continue
    if (Array.isArray(v) && v.length === 0) continue
    chips.push(`${k}: ${typeof v === 'object' ? JSON.stringify(v) : String(v)}`)
  }
  return chips.slice(0, 12)
})

const summaryCards = computed(() => {
  const s = summary.value
  if (!s) return []
  return [
    { key: 'total', label: l('إجمالي الأحداث', 'Total events'), value: s.total_items_in_window },
    { key: 'wo', label: l('أوامر العمل', 'Work orders'), value: s.work_orders_count },
    { key: 'inv', label: l('الفواتير', 'Invoices'), value: s.invoices_count },
    { key: 'pay', label: l('المدفوعات', 'Payments'), value: s.payments_count, hint: financialIncluded.value ? undefined : l('مخفية', 'Hidden') },
    { key: 'tic', label: l('التذاكر', 'Tickets'), value: s.tickets_count },
    { key: 'att', label: l('انتباه', 'Attention'), value: s.attention_count },
  ]
})

function refresh(): void {
  void fetchFeed()
}

function scrollToFilters(): void {
  document.getElementById('operations-feed-filters')?.scrollIntoView({ behavior: 'smooth' })
  filtersOpen.value = true
}

function clearFilters(): void {
  resetFilters()
  void fetchFeed()
}

function onAttentionFilter(level: string): void {
  attentionLevel.value = level
  page.value = 1
  void fetchFeed()
}

function onSidebarApply(payload: {
  from: string
  to: string
  branchId: string
  customerId: string
  userId: string
  types: string[]
  attentionLevel: string
  includeFinancial: boolean
  perPage: number
}): void {
  from.value = payload.from
  to.value = payload.to
  branchId.value = payload.branchId
  customerId.value = payload.customerId
  userId.value = payload.userId
  types.value = payload.types
  attentionLevel.value = payload.attentionLevel
  includeFinancial.value = payload.includeFinancial
  perPage.value = payload.perPage
  page.value = 1
  void fetchFeed()
  filtersOpen.value = false
}

function prevPage(): void {
  if ((pagination.value?.page ?? 1) <= 1) return
  page.value -= 1
  void fetchFeed()
}

function nextPage(): void {
  const p = pagination.value
  if (!p || p.page >= p.last_page) return
  page.value += 1
  void fetchFeed()
}

onMounted(() => {
  void fetchFeed()
})
</script>
