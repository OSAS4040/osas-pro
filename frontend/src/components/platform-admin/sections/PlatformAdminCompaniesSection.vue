<template>
  <div class="space-y-4">
    <div id="platform-tenants-snapshot" class="scroll-mt-28 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
      <PlatformKpiCard label="إجمالي الشركات" :value="snapshot.total.toLocaleString('ar-SA')" />
      <PlatformKpiCard label="الشركات النشطة" :value="snapshot.active.toLocaleString('ar-SA')" tone="success" />
      <PlatformKpiCard label="منخفضة النشاط / متعثرة" :value="snapshot.weak.toLocaleString('ar-SA')" tone="warning" />
      <PlatformKpiCard label="عالية المخاطر" :value="snapshot.highRisk.toLocaleString('ar-SA')" tone="danger" />
      <PlatformKpiCard label="أعلى شريحة أداء" :value="snapshot.topTierLabel" tone="brand" />
    </div>

    <div id="platform-tenants-panels" class="scroll-mt-28 grid gap-4 lg:grid-cols-4 lg:items-start">
      <div class="space-y-2">
        <h3 class="text-[11px] font-semibold text-slate-900 dark:text-white">أعلى الشركات إيرادًا</h3>
        <p class="text-[10px] leading-relaxed text-slate-500 dark:text-slate-400">من عيّنة التصفية الحالية — إيراد شهري تقديري من الباقة والبيانات المعروضة.</p>
        <PlatformInsightCard
          v-for="c in topRevenue"
          :key="'tr-'+c.id"
          eyebrow="إيراد مرجعي"
          :title="c.name"
          :badge="formatCurrency(c.revenue)"
          tone="positive"
          :why="'ضمن القائمة الحالية تُصنَّف هذه الشركة ضمن أعلى الإيراد التقديري.'"
          meaning="مؤشر قوة اشتراك أو حجم تشغيل يستحق مراجعة التحصيل والترقية."
          recommendation="راجع الفوترة وخطط الباقة من ملف الشركة قبل نهاية دورة الفوترة."
          cta-label="فتح ملف الشركة"
          :cta-to="`/platform/companies/${c.id}`"
        />
        <p v-if="topRevenue.length === 0" class="rounded-xl border border-dashed border-slate-200/90 px-3 py-4 text-center text-[11px] text-slate-500 dark:border-slate-700 dark:text-slate-400">
          لا بيانات كافية في العيّنة الحالية.
        </p>
      </div>
      <div class="space-y-2">
        <h3 class="text-[11px] font-semibold text-slate-900 dark:text-white">أعلى الشركات نموًا</h3>
        <p class="text-[10px] leading-relaxed text-slate-500 dark:text-slate-400">تقدير نمو من الإيراد المرجعي والشريحة — وليس قياساً محاسبياً كاملاً.</p>
        <PlatformInsightCard
          v-for="c in topGrowth"
          :key="'tg-'+c.id"
          eyebrow="زخم نمو"
          :title="c.name"
          :badge="c.growthLabel"
          tone="positive"
          why="الترتيب يعكس قوة نسبية داخل عيّنة المشتركين المحمّلة."
          meaning="فرصة لمتابعة الترقية أو التوسعة عند اتساق الاستخدام."
          recommendation="ثبّت قراراً بعد الاطلاع على النشاط والاشتراك في ملف الشركة."
          cta-label="فتح ملف الشركة"
          :cta-to="`/platform/companies/${c.id}`"
        />
        <p v-if="topGrowth.length === 0" class="rounded-xl border border-dashed border-slate-200/90 px-3 py-4 text-center text-[11px] text-slate-500 dark:border-slate-700 dark:text-slate-400">
          لا بيانات كافية في العيّنة الحالية.
        </p>
      </div>
      <div class="space-y-2">
        <h3 class="text-[11px] font-semibold text-slate-900 dark:text-white">أعلى الشركات مخاطر</h3>
        <p class="text-[10px] leading-relaxed text-slate-500 dark:text-slate-400">حسب حالة الاشتراك والتعليق — يتطلب قراراً تشغيلياً أو مالياً.</p>
        <PlatformInsightCard
          v-for="c in topRisk"
          :key="'tk-'+c.id"
          eyebrow="مخاطر مرتفعة"
          :title="c.name"
          badge="خطر مرتفع"
          tone="warning"
          why="فترة سماح أو تعليق أو إشارة خطر في بيانات المشترك."
          meaning="تأخّر التصرف قد يؤثر على التحصيل والاستمرارية."
          recommendation="راجع الاشتراك والتحصيل فوراً وسجّل المتابعة في المنصة."
          cta-label="فتح ملف الشركة"
          :cta-to="`/platform/companies/${c.id}`"
        />
        <p v-if="topRisk.length === 0" class="rounded-xl border border-dashed border-slate-200/90 px-3 py-4 text-center text-[11px] text-slate-500 dark:border-slate-700 dark:text-slate-400">
          لا حالات حرجة في العيّنة الحالية.
        </p>
      </div>
      <div class="space-y-2">
        <h3 class="text-[11px] font-semibold text-slate-900 dark:text-white">تحتاج متابعة فورية</h3>
        <p class="text-[10px] leading-relaxed text-slate-500 dark:text-slate-400">شركات بمخاطر غير مستقرة أو نشاط منخفض في العيّنة.</p>
        <PlatformInsightCard
          v-for="c in urgentFollowUps"
          :key="'uf-'+c.id"
          eyebrow="متابعة"
          :title="c.name"
          badge="أولوية"
          tone="warning"
          :why="c.followWhy"
          meaning="البقاء دون إجراء يزيد احتمال تصعيد المشكلة تشغيلياً أو مالياً."
          recommendation="حدّد موعد متابعة قصيراً وافتح ملف الشركة لاتخاذ القرار."
          cta-label="فتح ملف الشركة"
          :cta-to="`/platform/companies/${c.id}`"
        />
        <p v-if="urgentFollowUps.length === 0" class="rounded-xl border border-dashed border-slate-200/90 px-3 py-4 text-center text-[11px] text-slate-500 dark:border-slate-700 dark:text-slate-400">
          لا أولويات عاجلة في العيّنة الحالية.
        </p>
      </div>
    </div>

    <div
      id="platform-tenants-filters"
      class="scroll-mt-28 rounded-2xl border border-[color:var(--border-color)] bg-white/90 p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/40 dark:shadow-none"
    >
      <p class="mb-3 text-[11px] font-medium text-slate-600 dark:text-slate-400">تصفية العيّنة — نفس البيانات أدناه تنعكس في البطاقات أعلاه.</p>
      <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-6">
        <input
          :value="searchQuery"
          type="search"
          placeholder="بحث باسم الشركة..."
          class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white lg:col-span-2"
          @input="emit('update:searchQuery', ($event.target as HTMLInputElement).value)"
        />
        <select
          :value="statusFilter"
          class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
          @change="emit('update:statusFilter', ($event.target as HTMLSelectElement).value)"
        >
          <option value="">كل الحالات</option>
          <option value="active">نشطة</option>
          <option value="struggling">متعثرة</option>
        </select>
        <select
          :value="riskFilter"
          class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
          @change="emit('update:riskFilter', ($event.target as HTMLSelectElement).value)"
        >
          <option value="">كل المخاطر</option>
          <option value="high">عالية المخاطر</option>
          <option value="normal">مخاطر عادية</option>
        </select>
        <select
          :value="revenueFilter"
          class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
          @change="emit('update:revenueFilter', ($event.target as HTMLSelectElement).value)"
        >
          <option value="">كل الإيرادات</option>
          <option value="high">عالية الإيراد</option>
        </select>
        <select
          :value="planFilter"
          class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
          @change="emit('update:planFilter', ($event.target as HTMLSelectElement).value)"
        >
          <option value="">كل الباقات</option>
          <option v-for="slug in planOptions" :key="'po-'+slug" :value="slug">{{ planLabel(slug) }}</option>
        </select>
      </div>
    </div>

    <div
      id="platform-tenants-table"
      class="scroll-mt-28 overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950/20 dark:shadow-none"
    >
      <table class="w-full text-sm">
        <thead class="bg-slate-50/90 dark:bg-slate-900/50">
          <tr>
            <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-500 dark:text-slate-400">الشركة</th>
            <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-500 dark:text-slate-400">الحالة</th>
            <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-500 dark:text-slate-400">الخطة</th>
            <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-500 dark:text-slate-400">الإيراد</th>
            <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-500 dark:text-slate-400">النشاط</th>
            <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-500 dark:text-slate-400">المخاطر</th>
            <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-500 dark:text-slate-400">فتح</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
          <template v-if="loading">
            <tr v-for="sk in 6" :key="'co-sk-' + sk" class="border-t border-slate-200 dark:border-slate-800">
              <td v-for="col in 7" :key="'co-sk-' + sk + '-' + col" class="px-4 py-3">
                <div class="h-4 animate-pulse rounded bg-slate-200/90 dark:bg-slate-700/80" :class="col === 1 ? 'w-[78%]' : 'w-[52%]'" />
              </td>
            </tr>
          </template>
          <tr v-else-if="companiesFeedOk && companies.length === 0">
            <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
              لا توجد نتائج تطابق عوامل التصفية الحالية.
            </td>
          </tr>
          <template v-else>
            <tr v-for="c in companies" :key="c.id" class="transition-colors hover:bg-slate-50/90 dark:hover:bg-slate-800/30">
              <td class="px-4 py-3">
                <div class="font-medium text-slate-900 dark:text-white">{{ c.name }}</div>
                <div class="text-xs text-slate-500 font-mono">{{ c.slug }}</div>
              </td>
              <td class="px-4 py-3">
                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold" :class="statusBadgeClass(c)">{{ companyOperationalHintAr(c) }}</span>
              </td>
              <td class="px-4 py-3"><PlanBadge :plan="c.plan_slug" :label="rowPlanLabel(c)" /></td>
              <td class="px-4 py-3 font-semibold text-emerald-700 dark:text-emerald-300">{{ formatCurrency(companyRevenue(c)) }}</td>
              <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">{{ activityLabel(c) }}</td>
              <td class="px-4 py-3">
                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold" :class="riskBadgeClass(c)">{{ riskLabel(c) }}</span>
              </td>
              <td class="px-4 py-3">
                <button
                  type="button"
                  class="inline-flex rounded-lg bg-primary-600 px-3 py-1.5 text-[11px] font-medium text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-400 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                  @click="emit('open-company', c.id)"
                >
                  فتح ملف الشركة
                </button>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Phase 3 — Step 2: عرض جدول المشتركين فقط (لا API هنا).
 * emit «go-section» يُستخدم لمسارات المنصة؛ ولطلبات التعليق/التفعيل يُمرَّر مفتاح داخلي يعالجه الأب (لا يُمرَّر لـ vue-router).
 */
import { computed } from 'vue'
import PlanBadge from '@/components/platform-admin/PlatformPlanBadge.vue'
import PlatformKpiCard from '@/components/platform-admin/ui/PlatformKpiCard.vue'
import PlatformInsightCard from '@/components/platform-admin/ui/PlatformInsightCard.vue'

/** يطابق حقول صف شركة من GET /admin/companies في الواجهة الحالية */
interface Company {
  id: number
  name?: string
  slug?: string
  owner_name?: string | null
  plan_slug?: string
  plan_name?: string
  subscription_status?: string | null
  monthly_revenue?: number | null
  users_count?: number | null
  updated_at?: string | null
  company_status?: string
  is_active?: boolean
}

const props = withDefaults(
  defineProps<{
    companies: Company[]
    loading?: boolean
    /** يطابق صف «لا نتائج» السابق عند نجاح التحميل وفراغ التصفية */
    companiesFeedOk?: boolean
    allCompanies?: Company[]
    searchQuery?: string
    statusFilter?: string
    riskFilter?: string
    revenueFilter?: string
    planFilter?: string
  }>(),
  {
    loading: false,
    companiesFeedOk: true,
    allCompanies: () => [],
    searchQuery: '',
    statusFilter: '',
    riskFilter: '',
    revenueFilter: '',
    planFilter: '',
  },
)

const emit = defineEmits<{
  'open-company': [companyId: string | number]
  'update:searchQuery': [value: string]
  'update:statusFilter': [value: string]
  'update:riskFilter': [value: string]
  'update:revenueFilter': [value: string]
  'update:planFilter': [value: string]
}>()

const planPriceMap: Record<string, number> = { trial: 0, basic: 299, professional: 799, enterprise: 2499 }

function formatCurrency(v: number): string {
  return new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR', maximumFractionDigits: 0 }).format(v || 0)
}

function formatDate(d: string | null | undefined): string {
  if (!d) return '—'
  const x = new Date(d)
  return Number.isNaN(x.getTime()) ? '—' : x.toLocaleDateString('ar-SA', { dateStyle: 'medium' })
}

function subscriptionStatusLabelAr(status: string | null | undefined): string {
  const s = String(status ?? '')
    .trim()
    .toLowerCase()
  if (s === '') return 'لا يوجد اشتراك مسجّل'
  const map: Record<string, string> = {
    active: 'اشتراك نشط',
    grace_period: 'فترة سماح',
    suspended: 'اشتراك موقوف',
  }
  return map[s] ?? 'حالة اشتراك غير معروفة لدى المنصة'
}

function companyOperationalHintAr(c: { company_status?: string; is_active?: boolean }): string {
  if (c.company_status === 'suspended') return 'حالة الشركة: موقوفة تشغيلياً'
  if (c.is_active === false) return 'حالة الشركة: غير مفعّلة للدخول'
  if (c.company_status === 'inactive') return 'حالة الشركة: غير نشطة'
  return 'حالة الشركة: نشطة'
}

function companyRevenue(c: Company): number {
  return Number(c.monthly_revenue) || planPriceMap[c.plan_slug || ''] || 0
}

function riskLabel(c: Company): string {
  if (String(c.subscription_status ?? '').toLowerCase() === 'grace_period' || c.company_status === 'suspended') return 'خطر مرتفع'
  if (c.is_active === false || c.company_status === 'inactive') return 'يحتاج متابعة'
  return 'مستقر'
}

function riskBadgeClass(c: Company): string {
  const l = riskLabel(c)
  if (l === 'خطر مرتفع') return 'bg-rose-100 text-rose-900 dark:bg-rose-950/40 dark:text-rose-200'
  if (l === 'يحتاج متابعة') return 'bg-amber-100 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200'
  return 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200'
}

function statusBadgeClass(c: Company): string {
  const s = companyOperationalHintAr(c)
  if (s.includes('موقوف')) return 'bg-rose-100 text-rose-900 dark:bg-rose-950/40 dark:text-rose-200'
  if (s.includes('غير')) return 'bg-amber-100 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200'
  return 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200'
}

function activityLabel(c: Company): string {
  if (!c.updated_at) return 'بدون نشاط حديث'
  const d = new Date(c.updated_at)
  if (Number.isNaN(d.getTime())) return 'غير معروف'
  const days = Math.floor((Date.now() - d.getTime()) / 86400000)
  if (days <= 3) return 'نشاط مرتفع'
  if (days <= 10) return 'نشاط متوسط'
  return 'نشاط منخفض'
}

function growthLabel(c: Company): string {
  const rev = companyRevenue(c)
  if (rev >= 2000) return 'نمو قوي'
  if (rev >= 800) return 'نمو متوسط'
  return 'قابل للنمو'
}

const sourceCompanies = computed(() => (props.allCompanies.length ? props.allCompanies : props.companies))
const totalRevenue = computed(() => sourceCompanies.value.reduce((sum, c) => sum + companyRevenue(c), 0))
const averageRevenue = computed(() => sourceCompanies.value.length ? totalRevenue.value / sourceCompanies.value.length : 0)
const snapshot = computed(() => {
  const list = sourceCompanies.value
  const total = list.length
  const active = list.filter((c) => c.company_status !== 'suspended' && c.is_active !== false).length
  const weak = list.filter((c) => activityLabel(c) === 'نشاط منخفض' || String(c.subscription_status ?? '').toLowerCase() === 'grace_period').length
  const highRisk = list.filter((c) => riskLabel(c) === 'خطر مرتفع').length
  const topTierLabel = averageRevenue.value >= 2000 ? 'شريحة نمو قوي' : averageRevenue.value >= 900 ? 'شريحة نمو متوسط' : 'شريحة أولية'
  return { total, active, weak, highRisk, topTierLabel }
})

const topRevenue = computed(() =>
  [...sourceCompanies.value]
    .map((c) => ({ id: c.id, name: c.name || `شركة #${c.id}`, revenue: companyRevenue(c) }))
    .sort((a, b) => b.revenue - a.revenue)
    .slice(0, 4),
)
const topGrowth = computed(() =>
  [...sourceCompanies.value]
    .map((c) => ({ id: c.id, name: c.name || `شركة #${c.id}`, score: companyRevenue(c), growthLabel: growthLabel(c) }))
    .sort((a, b) => b.score - a.score)
    .slice(0, 4),
)
const topRisk = computed(() =>
  sourceCompanies.value
    .filter((c) => riskLabel(c) === 'خطر مرتفع')
    .slice(0, 4)
    .map((c) => ({ id: c.id, name: c.name || `شركة #${c.id}` })),
)
function followUpWhy(c: Company): string {
  const bits: string[] = []
  if (activityLabel(c) === 'نشاط منخفض') bits.push('نشاط تشغيلي منخفض في آخر تحديث مسجّل.')
  if (riskLabel(c) !== 'مستقر') bits.push(`تصنيف المخاطر: ${riskLabel(c)}.`)
  return bits.length > 0 ? bits.join(' ') : 'يتطلب متابعة وفق سياسة أولويات المنصة.'
}

const urgentFollowUps = computed(() =>
  sourceCompanies.value
    .filter((c) => riskLabel(c) !== 'مستقر' || activityLabel(c) === 'نشاط منخفض')
    .slice(0, 4)
    .map((c) => ({
      id: c.id,
      name: c.name || `شركة #${c.id}`,
      followWhy: followUpWhy(c),
    })),
)
const planOptions = computed(() => [...new Set(sourceCompanies.value.map((c) => String(c.plan_slug ?? '').trim()).filter(Boolean))].sort())
const planLabel = (slug: string) => rowPlanLabel({ plan_slug: slug } as Company)

function rowPlanLabel(c: Company): string {
  if (c.plan_name && String(c.plan_name).trim() !== '' && c.plan_name !== '—') {
    return String(c.plan_name)
  }
  const slug = c.plan_slug || '—'
  const fallback: Record<string, string> = {
    trial: 'تجريبي',
    basic: 'أساسي',
    professional: 'احترافي',
    enterprise: 'مؤسسي',
  }
  return fallback[slug] || slug
}
</script>
