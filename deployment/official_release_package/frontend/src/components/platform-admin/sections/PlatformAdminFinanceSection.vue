<template>
  <section id="admin-section-finance" class="scroll-mt-32 mb-16">
    <div class="mb-5 border-b border-slate-200 pb-4 dark:border-slate-700">
      <h2 class="section-title text-slate-900 dark:text-white">مالية المنصة (سيادي)</h2>
      <p class="mt-1.5 max-w-2xl text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
        هذا السطح يعرض أموال المنصة فقط كمؤشرات سيادية تجميعية (قراءة فقط). لا يتم عرض محافظ العملاء أو دفاتر الشركات هنا.
      </p>
      <p class="mt-2 rounded-lg border border-rose-200/90 bg-rose-50/90 px-3 py-2 text-[11px] font-medium leading-relaxed text-rose-900 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-100">
        فصل مالي صارم: أموال المنصة المعروضة هنا — دون Wallet أو Ledger خاص بالمستأجرين.
      </p>
      <div class="mt-3 flex flex-wrap gap-2">
        <RouterLink
          :to="{ name: 'platform-companies' }"
          class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-bold text-slate-800 shadow-sm transition-colors hover:border-primary-300 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:border-primary-600"
        >
          المشتركون
        </RouterLink>
        <RouterLink
          :to="{ name: 'platform-plans' }"
          class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-bold text-slate-800 shadow-sm transition-colors hover:border-primary-300 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:border-primary-600"
        >
          الباقات والتمكين
        </RouterLink>
        <RouterLink
          :to="{ name: 'platform-overview' }"
          class="inline-flex items-center rounded-lg border border-transparent px-2.5 py-1.5 text-[11px] font-semibold text-primary-700 underline-offset-2 hover:underline dark:text-primary-400"
        >
          العودة للملخص
        </RouterLink>
      </div>
    </div>

    <div v-if="companiesFeedOk && !platformOverviewLoading && allCompanies.length > 0" class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <PlatformKpiCard label="رصيد المنصة (تقدير سيادي)" :value="formatSar(platformWallet.balance)" hint="مشتق من المقبوضات ناقص مصروفات تقديرية — ليس رصيداً بنكياً" />
      <PlatformKpiCard label="المقبوضات (تقدير)" :value="formatSar(platformWallet.receipts)" tone="success" hint="مرتبط بالإيراد الشهري المتكرر النشط من كتالوج الباقات" />
      <PlatformKpiCard label="المصروفات (تقدير)" :value="formatSar(platformWallet.expenses)" tone="danger" hint="نموذج تقديري داخل الواجهة" />
      <PlatformKpiCard label="التسويات (تقدير)" :value="formatSar(platformWallet.settlements)" tone="warning" hint="مرتبط بمخاطر/تعليق في العيّنة" />
    </div>

    <PlatformInsightCard
      v-if="companiesFeedOk && !platformOverviewLoading && allCompanies.length > 0"
      class="mb-8"
      eyebrow="تمييز المصدر"
      title="ما الذي تعنيه أرقام المحفظة أعلاه؟"
      badge="كتالوج + تقدير"
      tone="default"
      :why="sourceMoneyExplainer.why"
      :meaning="sourceMoneyExplainer.meaning"
      :recommendation="sourceMoneyExplainer.recommendation"
      cta-label="مراجعة المشتركين"
      cta-to="/platform/companies"
    />

    <div v-if="companiesFeedOk && !platformOverviewLoading && allCompanies.length > 0" class="mb-8 grid gap-4 lg:grid-cols-3 lg:items-start">
      <div class="space-y-4 lg:col-span-2">
        <div class="rounded-2xl border border-slate-200/90 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/80">
          <h3 class="text-sm font-semibold text-slate-900 dark:text-white">تحليلات الإيراد (شهري)</h3>
          <p class="mt-1 rounded-lg border border-amber-200/80 bg-amber-50/70 px-2.5 py-1.5 text-[10px] font-medium text-amber-950 dark:border-amber-900/40 dark:bg-amber-950/25 dark:text-amber-100">
            تقدير كتالوج — ليس تحصيلاً بنكياً موثَّقاً ولا إقفالاً محاسبياً للمنصة.
          </p>
          <div class="mt-3 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200/90 bg-slate-50/90 px-3 py-2.5 dark:border-slate-700 dark:bg-slate-800/50">
              <p class="text-[10px] font-medium text-slate-500 dark:text-slate-400">إجمالي الإيراد الشهري (كتالوج)</p>
              <p class="mt-1 text-sm font-semibold tabular-nums text-slate-900 dark:text-white">{{ formatSar(summary.totalCatalogMonthly) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200/90 bg-slate-50/90 px-3 py-2.5 dark:border-slate-700 dark:bg-slate-800/50">
              <p class="text-[10px] font-medium text-slate-500 dark:text-slate-400">إيراد شهري متكرر نشط (تقدير)</p>
              <p class="mt-1 text-sm font-semibold tabular-nums text-slate-900 dark:text-white">{{ formatSar(summary.mrrActiveOnly) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200/90 bg-slate-50/90 px-3 py-2.5 dark:border-slate-700 dark:bg-slate-800/50">
              <p class="text-[10px] font-medium text-slate-500 dark:text-slate-400">متوسط لكل شركة</p>
              <p class="mt-1 text-sm font-semibold tabular-nums text-slate-900 dark:text-white">{{ formatSar(averageRevenuePerCompany) }}</p>
            </div>
          </div>
        </div>

        <PlatformInsightCard
          eyebrow="خليط الباقات"
          title="توزيع الإيراد التقديري حسب الباقات"
          :badge="`${revenueByPlans.length.toLocaleString('ar-SA')} شريحة`"
          tone="default"
          :why="revenueByPlansNarrative.why"
          :meaning="revenueByPlansNarrative.meaning"
          :recommendation="revenueByPlansNarrative.recommendation"
          cta-label="كتالوج الباقات"
          cta-to="/platform/plans"
        />

        <div class="rounded-2xl border border-slate-200/90 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/80">
          <h4 class="text-[11px] font-semibold text-slate-700 dark:text-slate-300">توزيع مرئي (حسب الباقات)</h4>
          <p class="mt-0.5 text-[10px] text-slate-500 dark:text-slate-400">الأشرطة نسبية داخل العيّنة المحمّلة.</p>
          <div class="mt-3 space-y-2">
            <div v-for="row in revenueByPlans" :key="'plan-rev-'+row.slug" class="flex items-center gap-2">
              <span class="w-28 truncate text-[11px] text-slate-700 dark:text-slate-300">{{ row.label }}</span>
              <div class="h-2 flex-1 rounded-full bg-slate-200 dark:bg-slate-700">
                <div class="h-2 rounded-full bg-primary-500 transition-all" :style="{ width: row.width + '%' }" />
              </div>
              <span class="w-24 text-left text-[11px] font-medium tabular-nums text-slate-800 dark:text-slate-100">{{ formatSar(row.revenue) }}</span>
            </div>
            <p v-if="revenueByPlans.length === 0" class="text-[11px] text-slate-500 dark:text-slate-400">لا بيانات باقات في العيّنة.</p>
          </div>
        </div>
      </div>

      <div class="space-y-2">
        <h3 class="text-[11px] font-semibold text-slate-900 dark:text-white">أعلى الشركات إيرادًا (تقدير كتالوج)</h3>
        <p class="text-[10px] leading-relaxed text-slate-500 dark:text-slate-400">بطاقات قرار مرتبطة بملف الشركة — نفس منطق شاشة المشتركين.</p>
        <PlatformInsightCard
          v-for="row in topRevenueForInsightCards"
          :key="'co-rev-'+row.id"
          eyebrow="إيراد شهري تقديري"
          :title="row.name"
          :badge="row.amountLabel"
          tone="positive"
          :why="'مرتفع في ترتيب العيّنة الحالية وفق بيانات الاشتراك والكتالوج.'"
          meaning="مؤشر لمتابعة التحصيل أو الترقية عند اتساق الاستخدام."
          recommendation="راجع النموذج المالي وخطط الباقة من ملف الشركة."
          cta-label="فتح ملف الشركة"
          :cta-to="`/platform/companies/${row.id}`"
        />
        <p v-if="topRevenueForInsightCards.length === 0" class="rounded-xl border border-dashed border-slate-200/90 px-3 py-4 text-center text-[11px] text-slate-500 dark:border-slate-700 dark:text-slate-400">
          لا توجد بيانات كافية في التصفية الحالية.
        </p>
      </div>
    </div>

    <details
      v-if="companiesFeedOk"
      id="platform-why-engine"
      class="mb-6 scroll-mt-32 rounded-2xl border border-primary-200/90 bg-gradient-to-bl from-primary-50/90 via-white to-slate-50/80 shadow-sm dark:border-primary-900/50 dark:from-primary-900/25 dark:via-slate-900 dark:to-slate-950"
      dir="rtl"
    >
      <summary
        class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-3 marker:hidden [&::-webkit-details-marker]:hidden"
      >
        <div>
          <h3 class="text-sm font-semibold text-primary-900 dark:text-primary-100">محرك التفسير — لماذا؟</h3>
          <p class="mt-0.5 text-[10px] text-slate-600 dark:text-slate-400">
            تحليلات تفسيرية على مستوى المنصة فقط، بدون أي قراءة من محافظ أو دفاتر المستأجرين.
          </p>
        </div>
        <span class="shrink-0 rounded-full bg-primary-600/10 px-2 py-1 text-[10px] font-bold text-primary-800 dark:bg-primary-500/15 dark:text-primary-200">
          {{ financeInsights.length ? financeInsights.length.toLocaleString('ar-SA') + ' إشارة' : 'بدون إشارات' }}
        </span>
      </summary>
      <div class="border-t border-primary-100/90 px-4 pb-4 pt-3 dark:border-primary-900/40">
        <PlatformInsightList
          :insights="financeInsights"
          :loading="platformOverviewLoading && allCompanies.length === 0"
        />
      </div>
    </details>

    <div class="mb-5 rounded-2xl border border-[color:var(--border-color)] bg-white/90 p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/40 dark:shadow-none">
      <p class="mb-3 text-[11px] font-medium text-slate-600 dark:text-slate-400">
        تصفية العيّنة — الملخص والجداول أعلاه يعكسان نفس التصفية الحالية.
      </p>
      <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
        <div class="sm:col-span-1 lg:col-span-2">
          <label class="mb-1 block text-[10px] font-medium text-slate-500 dark:text-slate-400">الحالة المالية</label>
          <select
            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
            :value="financeStatusFilter"
            @change="onFinanceStatusChange"
          >
            <option value="">كل الحالات المالية</option>
            <option value="pending_platform_review">قيد مراجعة المنصة</option>
            <option value="approved_prepaid">معتمد — شحن مسبق</option>
            <option value="approved_credit">معتمد — ائتمان</option>
            <option value="rejected">مرفوض</option>
            <option value="suspended">معلّق</option>
          </select>
        </div>
        <div class="sm:col-span-1 lg:col-span-2">
          <label class="mb-1 block text-[10px] font-medium text-slate-500 dark:text-slate-400">الباقة</label>
          <select
            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
            :value="financePlanFilter"
            @change="onFinancePlanChange"
          >
            <option value="">كل الباقات</option>
            <option v-for="slug in planSlugOptions" :key="'pf-'+slug" :value="slug">{{ planLabelForRow(slug) }}</option>
          </select>
        </div>
      </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950/20 dark:shadow-none">
      <div class="border-b border-slate-100/90 bg-slate-50/90 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/50">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">سجل عمليات مالية المنصة (قراءة سيادية)</h3>
        <p class="mt-1 text-[10px] text-slate-500 dark:text-slate-400">عمليات مشتقة من قرارات الاشتراك/النموذج المالي على مستوى المنصة فقط.</p>
      </div>
      <table class="w-full min-w-[760px] text-sm">
        <thead class="bg-slate-50/90 dark:bg-slate-900/50">
          <tr>
            <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-500 dark:text-slate-400">التاريخ</th>
            <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-500 dark:text-slate-400">المرجع</th>
            <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-500 dark:text-slate-400">المصدر</th>
            <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-500 dark:text-slate-400">الحالة</th>
            <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-500 dark:text-slate-400">القيمة الشهرية</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
          <template v-if="platformOverviewLoading">
            <tr v-for="sk in 6" :key="'fin-sk-' + sk" class="border-t border-slate-200 dark:border-slate-800">
              <td v-for="col in 5" :key="'fin-sk-' + sk + '-' + col" class="px-4 py-3">
                <div
                  class="h-4 animate-pulse rounded bg-slate-200/90 dark:bg-slate-700/80"
                  :class="col === 2 ? 'w-[80%]' : 'w-[50%]'"
                />
              </td>
            </tr>
          </template>
          <tr v-else-if="!companiesFeedOk">
            <td colspan="5" class="px-4 py-10 text-center text-sm text-amber-800 dark:text-amber-200">
              لم تُحمَّل قائمة المشتركين — لا يمكن بناء سجل العمليات حتى ينجح طلب الشركات.
            </td>
          </tr>
          <tr v-else-if="platformTransactions.length === 0">
            <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
              لا توجد عمليات مطابقة للتصفية الحالية.
            </td>
          </tr>
          <template v-else>
            <tr
              v-for="tx in platformTransactions"
              :key="tx.id"
              class="transition-colors hover:bg-slate-50/90 dark:hover:bg-slate-800/30"
            >
              <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">{{ tx.dateLabel }}</td>
              <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ tx.reference }}</td>
              <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">{{ tx.source }}</td>
              <td class="px-4 py-3">
                <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold" :class="financialBadgeClass(tx.status)">
                  {{ companyFinancialModelStatusLabel(tx.status) }}
                </span>
              </td>
              <td class="px-4 py-3 font-semibold tabular-nums text-slate-900 dark:text-white">{{ formatSar(tx.amount) }}</td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950/20 dark:shadow-none">
      <div class="border-b border-slate-100/90 bg-slate-50/90 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/50">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">قرارات مالية الشركات (إشراف المنصة)</h3>
      </div>
      <table class="w-full min-w-[720px] text-sm">
        <thead class="bg-slate-50/90 dark:bg-slate-900/50">
          <tr>
            <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-500 dark:text-slate-400">الشركة</th>
            <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-500 dark:text-slate-400">الباقة</th>
            <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-500 dark:text-slate-400">حالة الاشتراك</th>
            <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-500 dark:text-slate-400">الحالة المالية</th>
            <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-500 dark:text-slate-400">المبلغ الشهري (تقدير)</th>
            <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-500 dark:text-slate-400">إجراء</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
          <tr v-if="rows.length === 0">
            <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
              لا توجد شركات تطابق التصفية الحالية.
            </td>
          </tr>
          <template v-else>
            <tr v-for="c in rows" :key="'fin-' + c.id" class="transition-colors hover:bg-slate-50/90 dark:hover:bg-slate-800/30">
              <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ c.name }}</td>
              <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ planLabelForRow(c.plan_slug, c.plan_name) }}</td>
              <td class="px-4 py-3">
                <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold" :class="subscriptionBadgeClass(c.subscription_status)">
                  {{ subscriptionLabel(c.subscription_status) }}
                </span>
              </td>
              <td class="px-4 py-3">
                <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold" :class="financialBadgeClass(c.financial_model_status)">
                  {{ companyFinancialModelStatusLabel(c.financial_model_status) }}
                </span>
              </td>
              <td class="px-4 py-3 font-semibold tabular-nums text-emerald-700 dark:text-emerald-300">{{ formatSar(Number(c.monthly_revenue) || 0) }}</td>
              <td class="px-4 py-3">
                <button
                  type="button"
                  class="inline-flex rounded-lg bg-primary-600 px-3 py-1.5 text-[11px] font-medium text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-400 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                  @click="emit('open-financial-edit', c)"
                >
                  تحديث القرار المالي
                </button>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, toRef } from 'vue'
import { RouterLink } from 'vue-router'
import { companyFinancialModelStatusLabel } from '@/utils/companyFinancialLabels'
import PlatformInsightList from '@/components/platform-admin/intelligence/PlatformInsightList.vue'
import { usePlatformInsights, writeFinanceSnapshot } from '@/components/platform-admin/intelligence/usePlatformInsights'
import PlatformKpiCard from '@/components/platform-admin/ui/PlatformKpiCard.vue'
import PlatformInsightCard from '@/components/platform-admin/ui/PlatformInsightCard.vue'

const props = defineProps<{
  financeStatusFilter: string
  financePlanFilter: string
  platformOverviewLoading: boolean
  companiesFeedOk: boolean
  /** كامل المشتركين المحمّلين — للملخص والقائمة المشتقة في الأب */
  allCompanies: any[]
  rows: any[]
  planLabelForRow: (slug: string | null | undefined, planNameFromApi?: string | null) => string
}>()

const emit = defineEmits<{
  'update:financeStatusFilter': [value: string]
  'update:financePlanFilter': [value: string]
  'open-financial-edit': [company: any]
}>()

const { insights: financeInsightsRaw, metrics: financeMetricsSnapshot } = usePlatformInsights(toRef(props, 'allCompanies'))

const financeInsights = computed(() => {
  if (!props.companiesFeedOk || props.allCompanies.length === 0) return []
  return financeInsightsRaw.value
})

onBeforeUnmount(() => {
  if (!props.companiesFeedOk || props.allCompanies.length === 0) return
  writeFinanceSnapshot(financeMetricsSnapshot.value)
})

const planSlugOptions = computed(() => {
  const set = new Set<string>()
  for (const c of props.allCompanies) {
    const s = String(c?.plan_slug ?? '').trim()
    if (s) set.add(s)
  }
  return [...set].sort()
})

const averageRevenuePerCompany = computed(() => {
  if (props.allCompanies.length === 0) return 0
  return summary.value.totalCatalogMonthly / props.allCompanies.length
})

const platformWallet = computed(() => {
  const receipts = summary.value.mrrActiveOnly
  const expenses = Math.round(summary.value.totalCatalogMonthly * 0.18) + (summary.value.suspendedCount * 1200)
  const settlements = Math.round(summary.value.delinquentOrRisk * (averageRevenuePerCompany.value * 0.6))
  const balance = receipts - expenses
  return { receipts, expenses, settlements, balance }
})

const revenueByPlans = computed(() => {
  const grouped = new Map<string, number>()
  for (const c of props.allCompanies) {
    const key = String(c?.plan_slug ?? '').trim() || 'unknown'
    const rev = Number(c?.monthly_revenue) || 0
    grouped.set(key, (grouped.get(key) ?? 0) + rev)
  }
  const rows = [...grouped.entries()]
    .map(([slug, revenue]) => ({
      slug,
      label: props.planLabelForRow(slug, null),
      revenue,
    }))
    .sort((a, b) => b.revenue - a.revenue)
    .slice(0, 6)
  const max = Math.max(1, ...rows.map((r) => r.revenue))
  return rows.map((r) => ({ ...r, width: Math.round((r.revenue / max) * 100) }))
})

const topRevenueCompanies = computed(() =>
  [...props.rows]
    .sort((a, b) => (Number(b?.monthly_revenue) || 0) - (Number(a?.monthly_revenue) || 0))
    .slice(0, 6),
)

const sourceMoneyExplainer = computed(() => ({
  why: 'رصيد المنصة والمقبوضات والمصروفات والتسويات المعروضة أعلاه مُشتقة من بيانات المشتركين وأسعار الباقات ونماذج تقدير داخل الواجهة — وليست تقريراً بنكياً أو دفتراً عاماً للمنصة.',
  meaning: 'الاستخدام الصحيح: اتجاهات وقرارات إشرافية، لا إقفالاً محاسبياً نهائياً.',
  recommendation: 'للفواتير والتحصيل داخل كل شركة استخدم بوابة فريق العمل؛ من هنا انتقل للمشتركين أو كتالوج الباقات للسياق.',
}))

const revenueByPlansNarrative = computed(() => {
  const rows = revenueByPlans.value
  if (rows.length === 0) {
    return {
      why: 'لا توجد بيانات باقات كافية في العيّنة لبناء توزيع مرئي.',
      meaning: 'قد يعكس تصفية حالية أو نقصاً في تعيين الباقة للمشتركين.',
      recommendation: 'راجع قائمة المشتركين أو أزل التصفية ثم أعد التحميل.',
    }
  }
  const top = rows
    .slice(0, 3)
    .map((r) => `${r.label} (${formatSar(r.revenue)})`)
    .join(' · ')
  return {
    why: `أكبر حصص الإيراد التقديري حسب الباقات: ${top}.`,
    meaning: 'يعكس وزن كل باقة في إيراد الكتالوج المجمع وليس صافي المنصة النقدي الموثَّق.',
    recommendation: 'عند تعديل التسعير راجع كتالوج الباقات ثم راقب أثره على هذا الخليط.',
  }
})

const topRevenueForInsightCards = computed(() =>
  topRevenueCompanies.value.slice(0, 4).map((row: any) => ({
    id: row.id,
    name: String(row?.name ?? `شركة #${row.id}`),
    amountLabel: formatSar(Number(row.monthly_revenue) || 0),
  })),
)

const platformTransactions = computed(() => {
  return [...props.rows]
    .map((c: any) => {
      const st = String(c?.financial_model_status ?? '').trim() || 'pending_platform_review'
      const date = c?.updated_at ? new Date(c.updated_at) : new Date()
      return {
        id: `tx-${c.id}-${st}-${String(c?.updated_at ?? '')}`,
        reference: c?.name ?? `شركة #${c.id}`,
        source: st === 'approved_credit' ? 'قرار ائتمان منصة' : st === 'approved_prepaid' ? 'قرار شحن مسبق' : 'مراجعة منصة',
        status: st,
        amount: Number(c?.monthly_revenue) || 0,
        dateMs: Number.isNaN(date.getTime()) ? 0 : date.getTime(),
        dateLabel: Number.isNaN(date.getTime()) ? '—' : date.toLocaleDateString('ar-SA', { dateStyle: 'medium' }),
      }
    })
    .sort((a, b) => b.dateMs - a.dateMs)
    .slice(0, 12)
})

const summary = computed(() => {
  const list = props.allCompanies
  let totalCatalogMonthly = 0
  let mrrActiveOnly = 0
  let activeSubs = 0
  let delinquentOrRisk = 0
  let suspendedCount = 0
  let cancelledOrRejectedCount = 0

  for (const c of list) {
    const rev = Number(c?.monthly_revenue) || 0
    totalCatalogMonthly += rev
    const sub = String(c?.subscription_status ?? '').trim().toLowerCase()
    const fin = String(c?.financial_model_status ?? '').trim()
    const companySt = String(c?.company_status ?? '').trim().toLowerCase()

    if (sub === 'active' && companySt !== 'suspended') {
      activeSubs += 1
      mrrActiveOnly += rev
    }

    if (sub === 'grace_period' || fin === 'pending_platform_review') {
      delinquentOrRisk += 1
    }

    if (sub === 'suspended' || companySt === 'suspended') {
      suspendedCount += 1
    }

    if (fin === 'rejected' || sub === 'canceled' || sub === 'cancelled' || sub === 'ended') {
      cancelledOrRejectedCount += 1
    }
  }

  return { totalCatalogMonthly, mrrActiveOnly, activeSubs, delinquentOrRisk, suspendedCount, cancelledOrRejectedCount }
})

function formatSar(n: number): string {
  return new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR', maximumFractionDigits: 0 }).format(n || 0)
}

function subscriptionLabel(status: string | null | undefined): string {
  const s = String(status ?? '').trim().toLowerCase()
  if (s === '') return 'لا يوجد اشتراك'
  const map: Record<string, string> = {
    active: 'نشط',
    trial: 'تجريبي',
    grace_period: 'متأخر',
    suspended: 'موقوف',
  }
  return map[s] ?? 'غير نشط'
}

function subscriptionBadgeClass(status: string | null | undefined): string {
  const s = String(status ?? '').trim().toLowerCase()
  if (s === 'active') return 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-200'
  if (s === 'trial') return 'bg-sky-100 text-sky-900 dark:bg-sky-950/40 dark:text-sky-200'
  if (s === 'grace_period') return 'bg-amber-100 text-amber-950 dark:bg-amber-950/40 dark:text-amber-100'
  if (s === 'suspended') return 'bg-slate-200 text-slate-800 dark:bg-slate-700 dark:text-slate-200'
  return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
}

function financialBadgeClass(status: string | null | undefined): string {
  const s = String(status ?? '').trim()
  if (s === 'approved_prepaid' || s === 'approved_credit') {
    return 'bg-primary-100 text-primary-900 dark:bg-primary-900/40 dark:text-primary-200'
  }
  if (s === 'pending_platform_review') return 'bg-amber-100 text-amber-950 dark:bg-amber-950/40 dark:text-amber-100'
  if (s === 'rejected') return 'bg-rose-100 text-rose-900 dark:bg-rose-950/35 dark:text-rose-100'
  if (s === 'suspended') return 'bg-slate-200 text-slate-800 dark:bg-slate-700 dark:text-slate-200'
  return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
}

function onFinanceStatusChange(e: Event): void {
  emit('update:financeStatusFilter', (e.target as HTMLSelectElement).value)
}

function onFinancePlanChange(e: Event): void {
  emit('update:financePlanFilter', (e.target as HTMLSelectElement).value)
}
</script>
