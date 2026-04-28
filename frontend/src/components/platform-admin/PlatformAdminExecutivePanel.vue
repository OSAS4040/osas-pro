<template>
  <section v-if="auth.isPlatform" class="mb-6 rounded-2xl border border-primary-200/90 bg-gradient-to-br from-white via-primary-50/40 to-slate-50/90 p-4 shadow-sm dark:border-primary-900/50 dark:from-slate-900 dark:via-primary-900/20 dark:to-slate-950" dir="rtl">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-primary-100/80 pb-3 dark:border-primary-900/40">
      <div>
        <h2 data-testid="platform-executive-dashboard-title" class="text-base font-semibold text-slate-900 dark:text-white sm:text-lg">
          لوحة قيادة منصة أسس برو
        </h2>
        <p class="mt-1 text-[12px] text-slate-600 dark:text-slate-400">مركز قيادة أسس برو لإدارة المنصة — قراءة فقط</p>
        <p v-if="payload" class="mt-1 font-mono text-[10px] text-primary-700 dark:text-primary-400" dir="ltr">{{ payload.generated_at }}</p>
      </div>
      <PlatformQuickActions @refresh="reload" />
    </div>

    <p v-if="error" class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100">{{ error }}</p>
    <div v-if="loading && !payload" class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4"><div v-for="s in 8" :key="s" class="h-20 animate-pulse rounded-xl bg-white/80 dark:bg-slate-800/60" /></div>

    <template v-else-if="payload">
      <div class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-5">
        <PlatformKpiCard label="إجمالي إيراد المنصة (سنوي تقديري)" :value="formatSar(totalPlatformRevenue)">
          <RouterLink to="/platform/finance" class="mt-1 inline-block text-[10px] font-bold text-primary-700 underline dark:text-primary-400">عرض التفاصيل</RouterLink>
        </PlatformKpiCard>
        <PlatformKpiCard label="التقدير الشهري المتكرر (كتالوج)" :value="formatSar(payload.kpis.estimated_mrr)">
          <RouterLink to="/platform/finance" class="mt-1 inline-block text-[10px] font-bold text-primary-700 underline dark:text-primary-400">عرض التفاصيل</RouterLink>
        </PlatformKpiCard>
        <PlatformKpiCard label="عدد الشركات النشطة" :value="payload.kpis.active_companies.toLocaleString('ar-SA')" tone="success">
          <RouterLink to="/platform/companies?status=active" class="mt-1 inline-block text-[10px] font-bold text-emerald-800 underline dark:text-emerald-200">فتح القائمة</RouterLink>
        </PlatformKpiCard>
        <PlatformKpiCard label="الشركات عالية المخاطر" :value="highRiskCompanies.toLocaleString('ar-SA')" tone="warning">
          <RouterLink to="/platform/companies?risk=high" class="mt-1 inline-block text-[10px] font-bold text-amber-800 underline dark:text-amber-200">فتح القائمة</RouterLink>
        </PlatformKpiCard>
        <PlatformKpiCard label="التحصيل مقابل المتأخر (مؤشر)" :value="collectionVsOverdue" tone="danger">
          <RouterLink to="/platform/companies?risk=high&status=struggling" class="mt-1 inline-block text-[10px] font-bold text-rose-800 underline dark:text-rose-200">القفز للحالات المتأخرة</RouterLink>
        </PlatformKpiCard>
      </div>

      <div v-if="intelligenceLine" class="mt-3">
        <PlatformInsightCard
          eyebrow="قراءة تنفيذية فورية"
          :title="intelligenceLineTitle"
          badge="تركيز اليوم"
          :tone="intelligenceLineTone"
          :why="intelligenceLine"
          meaning="هذا المؤشر يحدد أولوية التدخل قبل أن تتسع المشكلة تشغيلياً أو مالياً."
          recommendation="ابدأ من أقرب مسار إجراء مرتبط بالمؤشر، ثم ارجع للملخص للتحقق من الأثر."
          cta-label="الانتقال للتشغيل والمالية"
          cta-to="/platform/ops"
        />
      </div>

      <div class="mt-4 rounded-xl border border-primary-200/70 bg-white/80 p-3 dark:border-primary-900/50 dark:bg-slate-900/40">
        <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">سياسة المنصة لإظهار الأقسام</h3>
        <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">تتحكم بما يمكن للشركات إظهاره لاحقًا لمستخدميها.</p>
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
          <label v-for="k in sectionKeys" :key="`sec-${k}`" class="flex items-center gap-2 text-xs">
            <input v-model="platformNav.sections[k]" type="checkbox" class="rounded" />
            <span>{{ sectionLabels[k] }}</span>
          </label>
        </div>
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
          <label v-for="k in groupKeys" :key="`grp-${k}`" class="flex items-center gap-2 text-xs">
            <input v-model="platformNav.groups[k]" type="checkbox" class="rounded" />
            <span>{{ groupLabels[k] }}</span>
          </label>
        </div>
        <div class="mt-3 flex justify-end">
          <button
            type="button"
            class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-700 disabled:opacity-60"
            :disabled="savingPolicy"
            @click="savePlatformPolicy"
          >
            {{ savingPolicy ? 'جاري الحفظ...' : 'حفظ سياسة المنصة' }}
          </button>
        </div>
      </div>

      <h3 class="mt-5 border-b border-slate-200/80 pb-2 text-sm font-semibold text-slate-900 dark:border-slate-700 dark:text-white">الصحة التشغيلية</h3>
      <PlatformHealth :health="payload.health" />

      <h3 class="mt-6 border-b border-slate-200/80 pb-2 text-sm font-semibold text-slate-900 dark:border-slate-700 dark:text-white">مؤشرات المنصة الرئيسية</h3>
      <PlatformKpis :kpis="payload.kpis" :definitions="payload.definitions" />

      <div class="mt-6 rounded-xl border border-emerald-200/80 bg-emerald-50/50 p-3 dark:border-emerald-900/40 dark:bg-emerald-950/20">
        <h3 class="text-sm font-semibold text-emerald-950 dark:text-emerald-100">المالية والاشتراكات (داخل المنصة)</h3>
        <p class="mt-1 text-[11px] leading-relaxed text-emerald-900/90 dark:text-emerald-200/90">
          قرارات النموذج المالي والاعتمادات تُدار من قسم النموذج المالي؛ الأرقام أدناه تقديرية من كتالوج الباقات وليست إيراداً محصّلاً.
        </p>
        <p
          v-if="financeWhyEngineTeaser"
          class="mt-2 rounded-lg border border-emerald-300/60 bg-white/60 px-2.5 py-1.5 text-[10px] font-semibold leading-relaxed text-emerald-950 dark:border-emerald-800/50 dark:bg-emerald-950/25 dark:text-emerald-100"
        >
          {{ financeWhyEngineTeaser }}
        </p>
        <div class="mt-2 flex flex-wrap gap-2">
          <RouterLink
            to="/platform/finance"
            class="inline-flex items-center rounded-lg bg-emerald-700 px-3 py-1.5 text-[11px] font-bold text-white hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500"
          >
            عرض المالية
          </RouterLink>
          <RouterLink
            to="/platform/plans"
            class="inline-flex items-center rounded-lg border border-emerald-600/50 bg-white px-3 py-1.5 text-[11px] font-bold text-emerald-900 hover:bg-emerald-50 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-100 dark:hover:bg-emerald-900/30"
          >
            الاشتراكات والباقات
          </RouterLink>
          <PlatformOperationsExitLink
            to="/invoices"
            aria-name="الفواتير والتحصيل في فريق العمل"
            dense
            class="shrink-0 self-center"
          >
            الفواتير (فريق العمل)
          </PlatformOperationsExitLink>
        </div>
      </div>

      <h3 class="mt-6 border-b border-slate-200/80 pb-2 text-sm font-semibold text-slate-900 dark:border-slate-700 dark:text-white">النمو والاتجاهات والتوزيع</h3>
      <PlatformCharts :trends="payload.trends" :distribution="payload.distribution" />

      <h3 class="mt-6 border-b border-slate-200/80 pb-2 text-sm font-semibold text-slate-900 dark:border-slate-700 dark:text-white">ذكاء النشاط والتنبيهات</h3>
      <PlatformActivityIntel :activity="payload.activity" />
      <PlatformAlerts :alerts="payload.alerts" />
      <PlatformInsights :insights="payload.insights" />

      <PlatformSignalsPanel :refresh-tick="refreshTick" />
      <PlatformIncidentCandidatesPanel :refresh-tick="refreshTick" />

      <h3 class="mt-6 border-b border-slate-200/80 pb-2 text-sm font-semibold text-slate-900 dark:border-slate-700 dark:text-white">لقطات قرار سريعة</h3>
      <p class="mt-2 text-[11px] text-slate-500 dark:text-slate-400">
        ثلاث زوايا تنفيذية — نمو، مخاطر، إيراد — بنفس بطاقة التفسير والانتقال.
      </p>
      <div class="mt-4 grid gap-5 lg:grid-cols-3 lg:items-start">
        <div class="space-y-3">
          <p class="text-[10px] font-medium uppercase tracking-wide text-emerald-800 dark:text-emerald-300">نمو ونشاط</p>
          <PlatformInsightCard
            v-for="row in growthInsightCards"
            :key="'grow-' + row.company_id"
            eyebrow="زخم نشاط"
            :title="row.title"
            :badge="row.badge"
            tone="positive"
            :why="row.why"
            :meaning="row.meaning"
            :recommendation="row.recommendation"
            :cta-label="row.ctaLabel"
            :cta-to="row.ctaTo"
          />
          <PlatformInsightCard
            v-if="growthInsightCards.length === 0"
            eyebrow="زخم نشاط"
            title="لا توجد عيّنة نمو واضحة بعد"
            badge="—"
            tone="default"
            why="البيانات الحالية لا تكفي لعزل أعلى ثلاث شركات نشاطاً في هذه اللحظة."
            meaning="انتظر تراكم أحداث أو حدّث الملخص لاحقاً."
            recommendation="راجع جدول المشتركين مع ترتيب حسب المخاطر أو النشاط."
            cta-label="عرض المشتركين"
            cta-to="/platform/companies"
          />
        </div>

        <div class="space-y-3">
          <p class="text-[10px] font-medium uppercase tracking-wide text-amber-900 dark:text-amber-200">مخاطر ومتابعة</p>
          <PlatformInsightCard
            v-for="row in riskInsightCards"
            :key="'risk-' + row.company_id"
            eyebrow="مخاطر تشغيلية"
            :title="row.title"
            badge="مخاطر"
            tone="warning"
            :why="row.why"
            :meaning="row.meaning"
            :recommendation="row.recommendation"
            :cta-label="row.ctaLabel"
            :cta-to="row.ctaTo"
          />
          <PlatformInsightCard
            v-if="riskInsightCards.length === 0"
            eyebrow="مخاطر تشغيلية"
            title="لا توجد حالات في أعلى المخاطر الآن"
            badge="مستقر"
            tone="positive"
            why="لم يُرصد مستأجر في قمة قائمة المتابعة ضمن العيّنة الحالية."
            meaning="يمكنك إعادة ترتيب الأولويات من شاشة المشتركين عند ظهور إشارات جديدة."
            recommendation="فعّل مراقبة أسبوعية للاشتراكات والتحصيل."
            cta-label="تصفية المخاطر"
            cta-to="/platform/companies?risk=high"
          />
        </div>

        <div class="space-y-3">
          <p class="text-[10px] font-medium uppercase tracking-wide text-primary-800 dark:text-primary-300">إيراد وتوسعة</p>
          <PlatformInsightCard
            eyebrow="إيراد وتوسعة"
            title="التحليل المالي على مستوى المنصة"
            badge="كتالوج"
            tone="default"
            why="أرقام الإيراد المعروضة في الملخص مرجعية من كتالوج الباقات وليست تحصيلاً فعلياً."
            meaning="القرار التجاري يحتاج ربطاً بين الاشتراك، الفوترة، والتحصيل داخل كل مستأجر."
            recommendation="ابدأ من مالية المنصة ثم انزل لملف الشركة عند الحاجة."
            cta-label="الانتقال للتحليل المالي"
            cta-to="/platform/finance"
          />
        </div>
      </div>

      <h3 class="mt-8 border-b border-slate-200/80 pb-2 text-sm font-semibold text-slate-900 dark:border-slate-700 dark:text-white">شركات تحتاج متابعة</h3>
      <p class="mt-2 text-[11px] text-slate-500 dark:text-slate-400">
        بطاقات قرار من الخادم — لكل شركة: السياق، الدلالة، والخطوة التالية.
      </p>
      <div class="mt-4 space-y-3">
        <p
          v-if="!payload.companies_requiring_attention?.length"
          class="rounded-xl border border-dashed border-slate-200/90 bg-slate-50/50 px-4 py-6 text-center text-[11px] text-slate-600 dark:border-slate-700 dark:bg-slate-900/35 dark:text-slate-400"
        >
          لا توجد شركات في قائمة المتابعة حالياً — الحالة ضمن الحدود أو لم تُحدَّث الإشارات بعد.
        </p>
        <PlatformInsightCard
          v-for="c in payload.companies_requiring_attention"
          :key="'attn-' + c.company_id"
          eyebrow="متابعة مطلوبة"
          :title="c.name"
          badge="أولوية"
          :tone="attentionTone(c)"
          :why="attentionWhy(c)"
          :meaning="attentionMeaning(c)"
          :recommendation="attentionRecommendation(c)"
          :cta-label="attentionCtaLabel(c)"
          :cta-to="c.action_path"
        />
      </div>
    </template>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'
import type { PlatformAdminOverviewPayload } from '@/types/platformAdminOverview'
import PlatformKpis from '@/components/platform/PlatformKpis.vue'
import PlatformCharts from '@/components/platform/PlatformCharts.vue'
import PlatformAlerts from '@/components/platform/PlatformAlerts.vue'
import PlatformInsights from '@/components/platform/PlatformInsights.vue'
import PlatformHealth from '@/components/platform/PlatformHealth.vue'
import PlatformActivityIntel from '@/components/platform/PlatformActivityIntel.vue'
import PlatformQuickActions from '@/components/platform/PlatformQuickActions.vue'
import {
  DEFAULT_NAV_VISIBILITY,
  NAV_GROUP_LABELS,
  NAV_SECTION_LABELS,
  type NavVisibilityPolicy,
} from '@/config/navigationVisibility'
import PlatformOperationsExitLink from '@/components/platform-admin/PlatformOperationsExitLink.vue'
import PlatformKpiCard from '@/components/platform-admin/ui/PlatformKpiCard.vue'
import PlatformInsightCard from '@/components/platform-admin/ui/PlatformInsightCard.vue'
import PlatformSignalsPanel from '@/components/platform-admin/intelligence/PlatformSignalsPanel.vue'
import PlatformIncidentCandidatesPanel from '@/components/platform-admin/intelligence/PlatformIncidentCandidatesPanel.vue'
import type { PlatformAdminAttentionCompany } from '@/types/platformAdminOverview'

const props = defineProps<{
  refreshTick?: number
  /** تلميح من محرك «لماذا؟» للمالية (قائمة المشتركين + لقطة جلسة) */
  financeWhyEngineTeaser?: string
}>()

const auth = useAuthStore()
const loading = ref(false)
const error = ref('')
const payload = ref<PlatformAdminOverviewPayload | null>(null)
const sectionLabels = NAV_SECTION_LABELS
const groupLabels = NAV_GROUP_LABELS
const sectionKeys = Object.keys(NAV_SECTION_LABELS)
const groupKeys = Object.keys(NAV_GROUP_LABELS)
const platformNav = ref<NavVisibilityPolicy>(JSON.parse(JSON.stringify(DEFAULT_NAV_VISIBILITY)))
const savingPolicy = ref(false)
const totalPlatformRevenue = computed(() => (payload.value?.kpis.estimated_mrr ?? 0) * 12)
const highRiskCompanies = computed(() => {
  const base = payload.value?.kpis.churn_risk_companies ?? 0
  const attn = payload.value?.companies_requiring_attention?.length ?? 0
  return Math.max(base, attn)
})
const overdueSignals = computed(() =>
  (payload.value?.alerts ?? []).filter((a) => /فاتورة|تحصيل|متأخر|overdue/i.test(String(a.message))).length,
)
const collectionVsOverdue = computed(() => {
  const collected = payload.value?.kpis.subscriptions_active ?? 0
  const overdue = overdueSignals.value
  return `${collected.toLocaleString('ar-SA')} / ${overdue.toLocaleString('ar-SA')}`
})
const topGrowth = computed(() => (payload.value?.activity.most_active_companies ?? []).slice(0, 3))
const topRisk = computed(() => (payload.value?.companies_requiring_attention ?? []).slice(0, 3))

const growthInsightCards = computed(() =>
  topGrowth.value.map((row) => ({
    company_id: row.company_id,
    title: row.company_name,
    badge: `درجة ${row.activity_score.toLocaleString('ar-SA')}`,
    why: 'ضمن نافذة النشاط الأخيرة تظهر هذه الشركة في أعلى العيّنة — مؤشر على تبنٍ أو حملة تشغيلية نشطة.',
    meaning: 'يمكن ربط الزخم بفرصة إيراد عبر الترقية أو التحصيل المبكر.',
    recommendation: 'راجع خطط الباقة والفوترة لهذا المستأجر قبل نهاية دورة الفوترة.',
    ctaLabel: 'فتح ملف الشركة',
    ctaTo: `/platform/companies/${row.company_id}`,
  })),
)

const riskInsightCards = computed(() =>
  topRisk.value.map((c) => ({
    company_id: c.company_id,
    title: c.name,
    why: `${c.reason_ar || c.reason}${c.action_hint ? ` — ${c.action_hint}` : ''}`,
    meaning: 'الظهور في قائمة المتابعة يعني أن الخادم صنّف هذه الحالة ضمن أولوية التدخل.',
    recommendation: 'تأكد من حالة الاشتراك والتحصيل، ثم سجّل قراراً واضحاً في ملف الشركة.',
    ctaLabel: 'فتح مسار المتابعة',
    ctaTo: c.action_path || `/platform/companies/${c.company_id}`,
  })),
)

const intelligenceLineTitle = computed(() => {
  const p = payload.value
  if (!p?.health) return 'ملخص تشغيلي'
  const h = p.health
  if (typeof h.queue_pending_count === 'number' && h.queue_pending_count > 100) return 'ضغط على الطابور'
  if (h.failed_jobs != null && h.failed_jobs > 0) return 'مهام فاشلة متراكمة في الطابور'
  if (h.redis_ok === false) return 'انقطاع ذاكرة التخزين المؤقت'
  if (p.kpis?.inactive_companies && p.kpis.inactive_companies > 0) return 'شركات بلا نشاط ملحوظ'
  if (p.kpis?.low_activity_companies && p.kpis.low_activity_companies > 0) return 'نشاط منخفض لدى جزء من المشتركين'
  if (h.trend === 'degraded') return 'صحة تشغيلية تحتاج مراجعة'
  return 'قراءة تنفيذية'
})

const intelligenceLineTone = computed((): 'default' | 'positive' | 'warning' | 'action' => {
  const p = payload.value
  if (!p?.health) return 'default'
  const h = p.health
  if (h.redis_ok === false || (h.failed_jobs != null && h.failed_jobs > 0)) return 'action'
  if (typeof h.queue_pending_count === 'number' && h.queue_pending_count > 100) return 'warning'
  if (p.kpis?.inactive_companies && p.kpis.inactive_companies > 0) return 'warning'
  if (h.trend === 'degraded') return 'warning'
  return 'positive'
})

function attentionWhy(c: PlatformAdminAttentionCompany): string {
  const base = c.reason_ar || c.reason || 'الخادم يطلب متابعة تشغيلية لهذا المستأجر.'
  return c.action_hint ? `${base} — ${c.action_hint}` : base
}

function attentionMeaning(_c: PlatformAdminAttentionCompany): string {
  return 'البقاء في قائمة المتابعة دون إجراء يزيد احتمال تصاعد المخاطر المالية أو التشغيلية.'
}

function attentionRecommendation(c: PlatformAdminAttentionCompany): string {
  if (c.financial_model_status && String(c.financial_model_status).length > 0) {
    return 'راجع النموذج المالي والاشتراك مع فريق التحصيل قبل اتخاذ قرار التعليق أو الترقية.'
  }
  return 'حدّد موعد متابعة قصيراً، وثبّت القرار في سجل المنصة بعد المراجعة.'
}

function attentionCtaLabel(c: PlatformAdminAttentionCompany): string {
  return c.action_hint && c.action_hint.length > 0 ? 'تنفيذ المسار المقترح' : 'فتح ملف الشركة'
}

function attentionTone(c: PlatformAdminAttentionCompany): 'default' | 'positive' | 'warning' | 'action' {
  const s = String(c.company_status ?? '').toLowerCase()
  if (s === 'suspended') return 'action'
  const r = String(c.reason ?? '').toLowerCase()
  if (r.includes('خطر') || r.includes('risk') || r.includes('تأخر')) return 'warning'
  return 'warning'
}

const intelligenceLine = computed(() => {
  const p = payload.value
  if (!p?.kpis || !p.health) return ''
  const k = p.kpis
  const h = p.health
  const qp = h.queue_pending_count
  if (typeof qp === 'number' && qp > 100) {
    return `ضغط على الطابور: يوجد ${qp.toLocaleString('ar-SA')} مهمة في انتظار التنفيذ — راجع عمال الطابور وقسم التشغيل العام.`
  }
  if (h.failed_jobs != null && h.failed_jobs > 0) {
    return `يوجد ${Number(h.failed_jobs).toLocaleString('ar-SA')} مهمة فاشلة متراكمة في الطابور — تحقّق من السبب الجذري أولاً ثم نفّذ إعادة المحاولة بشكل موجّه عند الحاجة.`
  }
  if (h.redis_ok === false) {
    return 'ذاكرة التخزين المؤقت غير متاحة — قد تتأثر الجلسات والكاش؛ راجع خدمة التخزين المؤقت على الخادم.'
  }
  if (k.inactive_companies > 0) {
    return `انخفاض النشاط: ${k.inactive_companies.toLocaleString('ar-SA')} شركة بلا نشاط يذكر منذ 14 يوماً فأكثر (من إجمالي ${k.total_companies.toLocaleString('ar-SA')} مشترك).`
  }
  if (k.low_activity_companies > 0) {
    return `${k.low_activity_companies.toLocaleString('ar-SA')} شركة بأداء منخفض (نشاط بين 8 و14 يوماً).`
  }
  if (h.trend === 'degraded') {
    return 'الصحة التشغيلية تحتاج مراجعة (قاعدة البيانات أو الطابور أو ذاكرة التخزين المؤقت).'
  }
  return ''
})

async function reload(): Promise<void> {
  if (!auth.isPlatform) return
  loading.value = true
  error.value = ''
  try {
    const { data: body } = await apiClient.get<{ data: PlatformAdminOverviewPayload }>('/admin/overview', {
      skipGlobalErrorToast: true,
    })
    payload.value = body.data
    const { data: navRes } = await apiClient.get('/platform/navigation-visibility', { skipGlobalErrorToast: true })
    platformNav.value = navRes?.data ?? JSON.parse(JSON.stringify(DEFAULT_NAV_VISIBILITY))
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    error.value = typeof msg === 'string' && msg.length > 0 ? msg : 'تعذّر تحميل لوحة قيادة المنصة. تحقّق من صلاحية قراءة شركات المنصة لدى حسابك.'
    payload.value = null
  } finally {
    loading.value = false
  }
}

async function savePlatformPolicy(): Promise<void> {
  savingPolicy.value = true
  try {
    const { data } = await apiClient.patch('/platform/navigation-visibility', platformNav.value, {
      skipGlobalErrorToast: true,
    })
    platformNav.value = data?.data ?? platformNav.value
  } finally {
    savingPolicy.value = false
  }
}

function formatSar(v: number): string {
  return new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR', maximumFractionDigits: 0 }).format(v || 0)
}

onMounted(() => {
  if (auth.isPlatform) void reload()
})

watch(
  () => props.refreshTick,
  () => {
    if (auth.isPlatform) void reload()
  },
)

watch(
  () => auth.isPlatform,
  (v) => {
    if (v) void reload()
  },
)
</script>
