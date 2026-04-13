<template>
  <div class="app-shell-page max-w-[1400px] mx-auto space-y-5" dir="rtl">
    <div v-if="wrongCompany" class="rounded-2xl border border-amber-200 bg-amber-50/90 p-6 text-sm text-amber-950">
      {{ l('معرّف الشركة غير مطابق لحسابك.', 'This company URL does not match your account.') }}
    </div>

    <template v-else>
      <div v-if="loading" class="space-y-3" aria-busy="true">
        <div class="h-28 rounded-3xl bg-slate-100 dark:bg-slate-800 animate-pulse" />
        <div class="h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 animate-pulse" />
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
          <div v-for="n in 6" :key="n" class="h-24 rounded-2xl bg-slate-100 dark:bg-slate-800 animate-pulse" />
        </div>
      </div>

      <template v-else-if="payload">
        <CompanyProfileHeader
          :kicker="l('مركز الشركة', 'Company hub')"
          :name="payload.company.name"
          :status="payload.company.status"
          :last-activity="lastActivityText"
          :reports-label="l('التقارير', 'Reports')"
          :ops-label="l('العمليات', 'Operations')"
          :edit-label="l('تعديل', 'Edit')"
          :show-reports="canReports"
          :show-ops="canOps"
          :show-edit="auth.isManager"
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
              <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ l('مؤشرات سريعة', 'Quick indicators') }}</h2>
              <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <div class="flex justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-2">
                  <dt class="text-slate-500">{{ l('حالة النشاط', 'Activity status') }}</dt>
                  <dd class="font-medium">{{ intelHealth }}</dd>
                </div>
                <div class="flex justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-2">
                  <dt class="text-slate-500">{{ l('تذاكر مفتوحة', 'Open tickets') }}</dt>
                  <dd class="font-medium tabular-nums">{{ payload.health_indicators.open_tickets }}</dd>
                </div>
                <div class="flex justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-2">
                  <dt class="text-slate-500">{{ l('نافذة النشاط (يوم)', 'Activity window (days)') }}</dt>
                  <dd class="font-medium tabular-nums">{{ payload.summary.activity_window_days }}</dd>
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
            </section>

            <section v-if="tab === 'relationships'" class="space-y-5">
              <div v-if="companyMap" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <RouterLink
                  v-if="companyMap.visibility.customer_profiles"
                  to="/customers"
                  class="rounded-2xl border border-slate-200/80 dark:border-slate-700/50 bg-white dark:bg-slate-900/35 p-4 block hover:border-primary-300 dark:hover:border-primary-700 transition-colors"
                >
                  <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400">{{ l('العملاء', 'Customers') }}</p>
                  <p class="text-2xl font-semibold tabular-nums text-slate-900 dark:text-slate-50 mt-1">{{ companyMap.counts.customers }}</p>
                  <p class="text-xs text-primary-600 mt-2">{{ l('القائمة', 'List') }} →</p>
                </RouterLink>
                <div
                  v-else
                  class="rounded-2xl border border-dashed border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-900/25 p-4 text-xs text-slate-500"
                >
                  {{ l('بيانات العملاء غير متاحة لصلاحيتك.', 'Customer directory is not available for your role.') }}
                </div>

                <RouterLink
                  v-if="companyMap.visibility.user_directory && auth.isManager"
                  to="/settings/team-users"
                  class="rounded-2xl border border-slate-200/80 dark:border-slate-700/50 bg-white dark:bg-slate-900/35 p-4 block hover:border-primary-300 dark:hover:border-primary-700 transition-colors"
                >
                  <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400">{{ l('المستخدمون', 'Users') }}</p>
                  <p class="text-2xl font-semibold tabular-nums text-slate-900 dark:text-slate-50 mt-1">{{ companyMap.counts.users }}</p>
                  <p class="text-xs text-primary-600 mt-2">{{ l('الفريق', 'Team') }} →</p>
                </RouterLink>
                <div
                  v-else-if="!companyMap.visibility.user_directory"
                  class="rounded-2xl border border-dashed border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-900/25 p-4 text-xs text-slate-500"
                >
                  {{ l('تفاصيل المستخدمين مخفية.', 'User relationship details are hidden for your role.') }}
                  <p v-if="companyMap.counts.users > 0" class="mt-2 tabular-nums font-medium text-slate-700 dark:text-slate-200">{{ companyMap.counts.users }}</p>
                </div>
                <div v-else class="rounded-2xl border border-dashed border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-900/25 p-4 text-xs text-slate-500">
                  {{ l('إدارة الفريق للمديرين فقط.', 'Team management is limited to managers.') }}
                </div>

                <RouterLink
                  v-if="companyMap.visibility.branch_directory && companyMap.visibility.branch_settings"
                  to="/branches"
                  class="rounded-2xl border border-slate-200/80 dark:border-slate-700/50 bg-white dark:bg-slate-900/35 p-4 block hover:border-primary-300 dark:hover:border-primary-700 transition-colors"
                >
                  <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400">{{ l('الفروع', 'Branches') }}</p>
                  <p class="text-2xl font-semibold tabular-nums text-slate-900 dark:text-slate-50 mt-1">{{ companyMap.counts.branches }}</p>
                  <p class="text-xs text-primary-600 mt-2">{{ l('إدارة الفروع', 'Branches') }} →</p>
                </RouterLink>
                <RouterLink
                  v-else-if="companyMap.visibility.branch_directory"
                  to="/branches/map"
                  class="rounded-2xl border border-slate-200/80 dark:border-slate-700/50 bg-white dark:bg-slate-900/35 p-4 block hover:border-primary-300 dark:hover:border-primary-700 transition-colors"
                >
                  <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400">{{ l('الفروع', 'Branches') }}</p>
                  <p class="text-2xl font-semibold tabular-nums text-slate-900 dark:text-slate-50 mt-1">{{ companyMap.counts.branches }}</p>
                  <p class="text-xs text-primary-600 mt-2">{{ l('الخريطة', 'Map') }} →</p>
                </RouterLink>
                <div
                  v-else
                  class="rounded-2xl border border-dashed border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-900/25 p-4 text-xs text-slate-500"
                >
                  {{ l('ملخص الفروع غير متاح لصلاحيتك.', 'Branch relationship summary is not available for your role.') }}
                </div>
              </div>

              <div class="rounded-2xl border border-slate-200/80 dark:border-slate-700/50 bg-white dark:bg-slate-900/35 p-5">
                <h3 class="text-sm font-semibold mb-3">{{ l('أبرز العملاء (الفترة)', 'Top customers (period)') }}</h3>
                <ul class="text-sm space-y-2">
                  <li v-for="c in payload.relationships.top_customers" :key="c.customer_id" class="flex justify-between gap-2 items-center">
                    <RouterLink :to="`/customers/${c.customer_id}`" class="text-primary-600 hover:underline truncate">{{ c.customer_name }}</RouterLink>
                    <span class="text-slate-500 tabular-nums shrink-0">{{ c.work_orders_count }}</span>
                  </li>
                  <li v-if="companyMap?.visibility.customer_profiles && !payload.relationships.top_customers.length" class="text-slate-500 text-xs">
                    {{ l('لا عملاء ضمن نافذة النشاط الحالية.', 'No customers in the current activity window.') }}
                  </li>
                  <li v-else-if="!companyMap?.visibility.customer_profiles" class="text-slate-500 text-xs">{{ l('مقيّد بالصلاحيات', 'Restricted by permissions') }}</li>
                </ul>
              </div>
              <div class="rounded-2xl border border-slate-200/80 dark:border-slate-700/50 bg-white dark:bg-slate-900/35 p-5">
                <h3 class="text-sm font-semibold mb-3">{{ l('أنشط المستخدمين (الفترة)', 'Top users (period)') }}</h3>
                <ul class="text-sm space-y-2">
                  <li v-for="u in payload.relationships.top_users" :key="u.user_id" class="flex justify-between gap-2 items-center">
                    <RouterLink v-if="auth.isManager" to="/settings/team-users" class="text-primary-600 hover:underline truncate">{{ u.user_name }}</RouterLink>
                    <span v-else class="truncate">{{ u.user_name }}</span>
                    <span class="text-slate-500 tabular-nums shrink-0">{{ u.work_orders_touched }}</span>
                  </li>
                  <li v-if="companyMap?.visibility.user_directory && !payload.relationships.top_users.length" class="text-slate-500 text-xs">
                    {{ l('لا مستخدمين ضمن نافذة النشاط الحالية.', 'No users in the current activity window.') }}
                  </li>
                  <li v-else-if="!companyMap?.visibility.user_directory" class="text-slate-500 text-xs">{{ l('مقيّد بالصلاحيات', 'Restricted by permissions') }}</li>
                </ul>
              </div>
              <div class="rounded-2xl border border-slate-200/80 dark:border-slate-700/50 bg-white dark:bg-slate-900/35 p-5">
                <h3 class="text-sm font-semibold mb-3">{{ l('الفروع مقابل أوامر العمل', 'Branches vs work orders') }}</h3>
                <ul class="text-sm space-y-2">
                  <li v-for="b in payload.relationships.branches_summary" :key="b.branch_id" class="flex justify-between gap-2 items-center">
                    <RouterLink
                      v-if="companyMap?.visibility.branch_directory && companyMap?.visibility.branch_settings"
                      :to="`/branches`"
                      class="text-primary-600 hover:underline truncate"
                    >{{ b.branch_name }}</RouterLink>
                    <RouterLink
                      v-else-if="companyMap?.visibility.branch_directory"
                      to="/branches/map"
                      class="text-primary-600 hover:underline truncate"
                    >{{ b.branch_name }}</RouterLink>
                    <span v-else class="truncate">{{ b.branch_name }}</span>
                    <span class="text-slate-500 tabular-nums shrink-0">{{ b.work_orders_in_period }}</span>
                  </li>
                  <li v-if="companyMap?.visibility.branch_directory && !payload.relationships.branches_summary.length" class="text-slate-500 text-xs">
                    {{ l('لا فروع ضمن النطاق أو لا أوامر عمل في الفترة.', 'No branches in scope or no work orders in the window.') }}
                  </li>
                  <li v-else-if="!companyMap?.visibility.branch_directory" class="text-slate-500 text-xs">{{ l('مقيّد بالصلاحيات', 'Restricted by permissions') }}</li>
                </ul>
              </div>
            </section>

            <section v-if="tab === 'reports'" class="rounded-2xl border border-slate-200/80 dark:border-slate-700/50 bg-white dark:bg-slate-900/35 p-5 space-y-3">
              <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ l('انتقال سريع', 'Quick navigation') }}</h2>
              <div class="flex flex-col gap-2 text-sm">
                <RouterLink v-if="canReports" to="/reports" class="text-primary-600 hover:underline">· {{ l('لوحة التقارير', 'Reports dashboard') }}</RouterLink>
                <RouterLink v-if="canOps" to="/operations/global-feed" class="text-primary-600 hover:underline">· {{ l('تدفق العمليات', 'Global operations feed') }}</RouterLink>
                <RouterLink to="/customers" class="text-primary-600 hover:underline">· {{ l('العملاء', 'Customers') }}</RouterLink>
                <RouterLink v-if="auth.isManager" to="/settings/team-users" class="text-primary-600 hover:underline">· {{ l('المستخدمون', 'Users') }}</RouterLink>
              </div>
            </section>
          </div>

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
        </div>
      </template>

      <div v-else class="rounded-2xl border border-rose-200 bg-rose-50/90 p-5 text-sm text-rose-900">
        {{ error ?? l('تعذر التحميل', 'Could not load') }}
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useCompanyProfile } from '@/composables/useCompanyProfile'
import { useLocale } from '@/composables/useLocale'
import CompanyProfileHeader from '@/components/company-profile/CompanyProfileHeader.vue'
import CompanyProfileStatusBanner from '@/components/company-profile/CompanyProfileStatusBanner.vue'
import CompanyProfileSummaryCards from '@/components/company-profile/CompanyProfileSummaryCards.vue'
import OperationalAttentionList from '@/components/operational-intelligence/OperationalAttentionList.vue'
import { attentionItemLabel, healthStatusBannerMessage, indicatorHint } from '@/composables/useOperationalIntelligenceDisplay'
import type { ActivitySnapshotItem, CompanyOperationalMap } from '@/types/companyProfile'

const route = useRoute()
const auth = useAuthStore()
const locale = useLocale()
const l = (ar: string, en: string) => (locale.lang.value === 'ar' ? ar : en)

const companyId = computed(() => Number(route.params.companyId ?? 0))
const wrongCompany = computed(
  () => auth.isAuthenticated && auth.user?.company_id && companyId.value > 0 && companyId.value !== auth.user.company_id,
)

const { loading, error, payload, financialIncluded, load } = useCompanyProfile(() => companyId.value)

const tab = ref<'overview' | 'activity' | 'relationships' | 'reports'>('overview')

const companyMap = computed(() => payload.value?.relationships.operational_map as CompanyOperationalMap | undefined)

const canReports = computed(() => auth.hasPermission('reports.view'))
const canOps = computed(() => auth.hasPermission('reports.view') && auth.hasPermission('reports.operations.view'))

const tabDefs = computed(() => [
  { id: 'overview' as const, label: l('نظرة عامة', 'Overview') },
  { id: 'activity' as const, label: l('النشاط', 'Activity') },
  { id: 'relationships' as const, label: l('العلاقات', 'Relationships') },
  { id: 'reports' as const, label: l('التقارير', 'Reports') },
])

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
  const h = intellect.value?.health_status ?? payload.value?.health_indicators.activity_status
  return typeof h === 'string' && h.length ? h : 'healthy'
})

const intelBanner = computed(() => healthStatusBannerMessage(intellect.value?.health_status ?? intelHealth.value, l))

const intelIndicatorLine = computed(() => indicatorHint(intellect.value, l))

const intelAttentionItems = computed(() => intellect.value?.attention_items ?? [])

const summaryCards = computed(() => {
  const p = payload.value
  if (!p) return []
  const inv = financialIncluded.value ? p.summary.invoices_in_period ?? 0 : l('—', '—')
  return [
    { key: 'u', label: l('المستخدمون', 'Users'), display: p.summary.users_count },
    { key: 'c', label: l('العملاء', 'Customers'), display: p.summary.customers_count },
    { key: 'b', label: l('الفروع', 'Branches'), display: p.summary.branches_count },
    { key: 'w', label: l('أوامر عمل نشطة', 'Active work orders'), display: p.summary.work_orders_active },
    { key: 'i', label: l('فواتير الفترة', 'Invoices (window)'), display: inv },
    { key: 'a', label: l('النشاط', 'Activity'), display: intelHealth.value },
  ]
})

function fmtRow(label: string, it: ActivitySnapshotItem | null): { key: string; title: string; when: string; detail: string } | null {
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

async function tryLoad(): Promise<void> {
  if (wrongCompany.value || companyId.value < 1) return
  await load()
}

onMounted(() => {
  void tryLoad()
})

watch([companyId, wrongCompany], () => {
  void tryLoad()
})
</script>
