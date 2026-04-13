<script setup lang="ts">
/**
 * Phase 5 — Command Center: progressive loading, partial data, contextual navigation.
 * Rules: skeleton (full) only when loading && !hasAnyData; error when error && !hasAnyData; else partial/full UI.
 */
import { computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useIntelligenceCommandCenter, type AlertRow } from '@/composables/useIntelligenceCommandCenter'
import IntelligenceSummaryStrip from '@/components/intelligence/IntelligenceSummaryStrip.vue'
import CommandZoneCard from '@/components/intelligence/CommandZoneCard.vue'
import CommandZonesSkeleton from '@/components/intelligence/CommandZonesSkeleton.vue'
import SeverityBadge from '@/components/intelligence/SeverityBadge.vue'
import EmptySignalState from '@/components/intelligence/EmptySignalState.vue'
import IntelligenceCommandCenterSkeleton from '@/components/intelligence/IntelligenceCommandCenterSkeleton.vue'
import InsightsSparkline from '@/components/intelligence/InsightsSparkline.vue'
import { domainEventNameAr } from '@/utils/intelUiLabels'
import {
  ExclamationTriangleIcon,
  InformationCircleIcon,
  SparklesIcon,
  ChevronLeftIcon,
  ClockIcon,
} from '@heroicons/vue/24/outline'

const {
  loading,
  refreshing,
  sectionLoading,
  error,
  refreshedAt,
  traceId,
  overview,
  insights,
  alerts,
  commandCenter,
  hasAnyData,
  loadAll,
} = useIntelligenceCommandCenter()

onMounted(() => {
  loadAll()
})

/** Command-center summary if present; else safe defaults from insights_snapshot / insights / overview heuristics */
const summary = computed(() => {
  const cc = commandCenter.value
  if (cc?.summary) {
    return {
      total_now: cc.summary.total_now,
      total_next: cc.summary.total_next,
      total_watch: cc.summary.total_watch,
      low_signal: cc.summary.low_signal,
    }
  }

  const snap = cc?.insights_snapshot
  if (snap) {
    const low = (snap.total_events ?? 0) === 0
    return {
      total_now: 0,
      total_next: 0,
      total_watch: 0,
      low_signal: low,
    }
  }

  const ins = insights.value
  if (ins) {
    const ev = ins.totals?.events ?? 0
    return {
      total_now: 0,
      total_next: 0,
      total_watch: 0,
      low_signal: ev === 0,
    }
  }

  if (overview.value) {
    return {
      total_now: 0,
      total_next: 0,
      total_watch: 0,
      low_signal: true,
    }
  }

  return {
    total_now: 0,
    total_next: 0,
    total_watch: 0,
    low_signal: true,
  }
})

/** Zones appear only when command-center payload exists */
const zones = computed(() => ({
  now: commandCenter.value?.zones?.now ?? [],
  next: commandCenter.value?.zones?.next ?? [],
  watch: commandCenter.value?.zones?.watch ?? [],
}))

const zonesReady = computed(() => commandCenter.value !== null)
const zonesBootstrapping = computed(
  () => hasAnyData.value && !zonesReady.value && sectionLoading.commandCenter,
)

const meaningfulAlerts = computed(() => {
  const list = alerts.value ?? []
  const warnings = list.filter((a) => a.severity === 'warning')
  const rest = list.filter((a) => a.severity !== 'warning')
  return [...warnings, ...rest].slice(0, 8)
})

const insightCounts = computed(() => {
  const i = insights.value
  if (!i) return null
  return {
    events: i.totals?.events ?? 0,
    types: i.by_event_name?.length ?? 0,
    last: i.last_occurred_at,
    from: i.window?.from,
    to: i.window?.to,
  }
})

const topEventTypes = computed(() => {
  const rows = insights.value?.by_event_name ?? []
  return [...rows].sort((a, b) => (b.count ?? 0) - (a.count ?? 0)).slice(0, 5)
})

function alertTitle(a: AlertRow): string {
  return a.message?.slice(0, 160) ?? a.id
}

/** Read-only follow-up route per alert (heuristic; no API contract change) */
function alertFollowUp(a: AlertRow): { name: string } {
  const t = (a.type ?? '').toLowerCase()
  const m = (a.message ?? '').toLowerCase()
  if (t.includes('governance') || m.includes('حوكمة') || m.includes('governance')) {
    return { name: 'governance' }
  }
  if (t.includes('integration') || m.includes('تكامل') || m.includes('integration')) {
    return { name: 'settings.integrations' }
  }
  if (t.includes('wallet') || m.includes('محفظة') || m.includes('wallet')) {
    return { name: 'wallet' }
  }
  return { name: 'internal.intelligence' }
}

function followUpLabel(name: string): string {
  const map: Record<string, string> = {
    governance: 'فتح الحوكمة',
    'settings.integrations': 'فتح التكاملات',
    wallet: 'فتح المحافظ',
    'internal.intelligence': 'مركز الذكاء',
  }
  return map[name] ?? 'متابعة'
}
</script>

<template>
  <div class="max-w-7xl mx-auto space-y-8 pb-12">
    <!-- Header: دائمًا مرئي — يثبّت السياق حتى أثناء أول تحميل -->
    <header
      class="space-y-3 motion-safe:animate-[intelHdr_0.5s_ease-out_both]"
    >
      <div class="flex flex-wrap items-center gap-2 text-primary-600 dark:text-primary-400">
        <SparklesIcon class="w-6 h-6 motion-safe:opacity-90" aria-hidden="true" />
        <span class="text-xs font-semibold tracking-wide text-primary-700 dark:text-primary-300">لوحة داخلية — تنقّل سياقي آمن</span>
      </div>
      <div>
        <h1 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-slate-100 tracking-tight">
          مركز العمليات الذكي
        </h1>
        <p class="text-sm text-gray-600 dark:text-slate-400 max-w-2xl mt-1.5 leading-relaxed">
          لوحة داخلية <strong class="font-semibold text-gray-700 dark:text-slate-300">للقراءة فقط</strong> —
          تُحمَّل الوحدات بالتوازي وتُعرض فور توفر أول استجابة، دون انتظار جميع الخدمات.
        </p>
        <p class="text-xs text-gray-500 dark:text-slate-500 mt-2 flex flex-wrap items-center gap-x-2 gap-y-1">
          <ClockIcon class="w-3.5 h-3.5 opacity-70 shrink-0" aria-hidden="true" />
          <span>نصيحة: استخدم «سياق تشغيلي» في البطاقات للانتقال إلى الفاتورة أو أمر العمل — دون أي تنفيذ تلقائي.</span>
        </p>
      </div>
    </header>

    <IntelligenceCommandCenterSkeleton v-if="loading && !hasAnyData" />

    <div
      v-else-if="error && !hasAnyData"
      class="rounded-2xl border border-red-200 dark:border-red-900/50 bg-red-50/80 dark:bg-red-950/30 p-6 flex gap-3 items-start"
      role="alert"
    >
      <ExclamationTriangleIcon class="w-6 h-6 text-red-600 dark:text-red-400 flex-shrink-0" />
      <div>
        <p class="font-semibold text-red-800 dark:text-red-200">تعذّر تحميل لوحة العمليات</p>
        <p class="text-sm text-red-700 dark:text-red-300 mt-1">{{ error }}</p>
        <p class="text-xs text-red-600/80 dark:text-red-400/80 mt-2">
          تأكد من تفعيل <code class="font-mono bg-red-100 dark:bg-red-900/40 px-1 rounded">INTELLIGENT_*</code> في الخادم
          وصلاحية المالك/المدير.
        </p>
        <button
          type="button"
          class="mt-4 text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline"
          @click="loadAll"
        >
          إعادة المحاولة
        </button>
      </div>
    </div>

    <template v-else>
      <!-- Summary: أي بيانات جزئية تكفي لإظهار الشريط مع أرقام آمنة -->
      <div class="space-y-2">
        <IntelligenceSummaryStrip
          v-if="hasAnyData"
          :total-now="summary.total_now"
          :total-next="summary.total_next"
          :total-watch="summary.total_watch"
          :refreshed-at="refreshedAt"
          :trace-id="traceId"
          :loading="loading"
          :refreshing="refreshing"
          :summary-degraded="hasAnyData && !commandCenter"
          @refresh="loadAll"
        />

        <div
          v-if="summary.low_signal && hasAnyData && (commandCenter || insights)"
          class="rounded-xl border border-sky-200 dark:border-sky-900/40 bg-sky-50/90 dark:bg-sky-950/25 px-4 py-3 flex gap-2 items-start"
          role="status"
        >
          <InformationCircleIcon class="w-5 h-5 text-sky-600 dark:text-sky-400 flex-shrink-0 mt-0.5" />
          <div class="text-sm text-sky-950 dark:text-sky-100">
            <p class="font-medium">إشارات تشغيل خفيفة في نافذة الزمن الحالية</p>
            <p class="text-sky-900/90 dark:text-sky-200/90 mt-1 text-xs leading-relaxed">
              هذا <strong class="font-semibold">أمر متوقع</strong> عندما يكون عدد أحداث النطاق قليلاً أو البيانات حديثة — وليس خللاً في النظام.
              عند ازدحام الأحداث أو توسيع النافذة تظهر عناصر أوضح في «الآن» و«التالي». اللوحة للمراقبة فقط ولا تنفّذ إجراءات تلقائياً.
            </p>
          </div>
        </div>
      </div>

      <CommandZonesSkeleton v-if="zonesBootstrapping" />

      <!-- dir=rtl يوحّد ترتيب الأعمدة مع الشريط العلوي: الآن → التالي → المراقبة من اليمين -->
      <div v-else dir="rtl" class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <CommandZoneCard
          title="الآن"
          subtitle="يحتاج انتباهك مباشرة"
          accent-class="bg-red-50/90 dark:bg-red-950/30"
          :items="zones.now"
          empty-title="لا عناصر عاجلة في هذه النافذة"
          empty-hint="الإشارات الحرجة والتنبيهات تظهر هنا عند توفرها."
        />
        <CommandZoneCard
          title="التالي"
          subtitle="خطط له قريباً"
          accent-class="bg-amber-50/90 dark:bg-amber-950/25"
          :items="zones.next"
          empty-title="لا توجد مهام تالية مميزة"
          empty-hint="التوصيات غير العاجلة تُعرض هنا عند ظهور أنماط في البيانات."
        />
        <CommandZoneCard
          title="المراقبة"
          subtitle="راقب دون تدخل فوري"
          accent-class="bg-slate-100/90 dark:bg-slate-800/60"
          :items="zones.watch"
          empty-title="لا عناصر مراقبة إضافية"
          empty-hint="الإشارات المعلوماتية وانخفاض التغطية يظهران هنا."
        />
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Insights -->
        <section
          class="rounded-2xl border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800/60 p-5 shadow-sm relative overflow-hidden ring-1 ring-gray-100/80 dark:ring-slate-700/50"
        >
          <div
            class="absolute start-0 top-0 bottom-0 w-1 rounded-s-2xl bg-gradient-to-b from-primary-500 to-primary-600 opacity-90"
            aria-hidden="true"
          />
          <div class="ps-1">
            <div class="flex items-baseline justify-between gap-2 mb-4 flex-wrap">
              <h2 class="text-sm font-bold text-gray-900 dark:text-slate-100">لمحة الرؤى</h2>
              <p
                v-if="insightCounts?.from && insightCounts?.to"
                class="text-[11px] text-gray-500 dark:text-slate-500 font-mono tabular-nums"
              >
                {{ insightCounts.from }} → {{ insightCounts.to }}
              </p>
            </div>

            <div
              v-if="sectionLoading.insights && !insights"
              class="space-y-3 animate-pulse"
              aria-hidden="true"
            >
              <div class="grid grid-cols-2 gap-3">
                <div class="h-20 rounded-xl bg-gray-100 dark:bg-slate-700" />
                <div class="h-20 rounded-xl bg-gray-100 dark:bg-slate-700" />
              </div>
              <div class="h-14 rounded-xl bg-gray-50 dark:bg-slate-800" />
            </div>

            <template v-else-if="insightCounts">
              <dl class="grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-xl bg-gradient-to-br from-gray-50 to-white dark:from-slate-900/60 dark:to-slate-800/40 border border-gray-100 dark:border-slate-600 p-3.5">
                  <dt class="text-xs font-medium text-gray-500 dark:text-slate-400">أحداث في النافذة</dt>
                  <dd class="text-2xl font-bold text-gray-900 dark:text-slate-100 tabular-nums mt-1">
                    {{ insightCounts.events }}
                  </dd>
                </div>
                <div class="rounded-xl bg-gradient-to-br from-gray-50 to-white dark:from-slate-900/60 dark:to-slate-800/40 border border-gray-100 dark:border-slate-600 p-3.5">
                  <dt class="text-xs font-medium text-gray-500 dark:text-slate-400">أنواع الأحداث</dt>
                  <dd class="text-2xl font-bold text-gray-900 dark:text-slate-100 tabular-nums mt-1">
                    {{ insightCounts.types }}
                  </dd>
                </div>
              </dl>

              <InsightsSparkline :daily-counts="insights?.daily_counts ?? null" />

              <div v-if="topEventTypes.length" class="mt-4 pt-3 border-t border-gray-100 dark:border-slate-700">
                <p class="text-[11px] font-semibold text-gray-500 dark:text-slate-400 mb-2">أكثر الأحداث تكراراً</p>
                <ul class="space-y-1.5">
                  <li
                    v-for="row in topEventTypes"
                    :key="row.event_name"
                    class="flex items-center justify-between gap-2 text-xs"
                  >
                    <span class="text-gray-700 dark:text-slate-300 truncate" :title="row.event_name">{{
                      domainEventNameAr(row.event_name)
                    }}</span>
                    <span class="tabular-nums font-semibold text-primary-700 dark:text-primary-300 shrink-0">{{
                      row.count
                    }}</span>
                  </li>
                </ul>
              </div>

              <p v-if="insightCounts.last" class="text-xs text-gray-500 dark:text-slate-500 mt-3 flex items-center gap-1">
                <ClockIcon class="w-3.5 h-3.5 opacity-60" aria-hidden="true" />
                آخر حدث: {{ insightCounts.last }}
              </p>
            </template>
            <EmptySignalState
              v-else
              title="لا تتوفر رؤى بعد"
              hint="تحقق من تفعيل واجهة الرؤى في الخادم أو أعد المحاولة لاحقاً."
            />
          </div>
        </section>

        <!-- Alerts -->
        <section
          class="rounded-2xl border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800/60 p-5 shadow-sm ring-1 ring-gray-100/80 dark:ring-slate-700/50"
        >
          <h2 class="text-sm font-bold text-gray-900 dark:text-slate-100 mb-4">تنبيهات تشغيلية</h2>

          <ul v-if="sectionLoading.alerts && !alerts" class="space-y-3 animate-pulse" aria-hidden="true">
            <li v-for="i in 4" :key="i" class="h-[4.5rem] rounded-xl bg-gray-100 dark:bg-slate-700" />
          </ul>

          <ul v-else-if="meaningfulAlerts.length" class="space-y-3">
            <li
              v-for="a in meaningfulAlerts"
              :key="a.id"
              class="group rounded-xl border border-gray-200/90 dark:border-slate-600 bg-gray-50/50 dark:bg-slate-900/30 p-3.5 flex flex-col gap-2 transition-all hover:border-primary-200 dark:hover:border-primary-800/50 hover:bg-white dark:hover:bg-slate-800/50 hover:shadow-sm"
            >
              <div class="flex gap-3 items-start">
                <SeverityBadge :severity="a.severity" />
                <div class="min-w-0 flex-1">
                  <p class="text-sm font-semibold text-gray-900 dark:text-slate-100 leading-snug">
                    {{ alertTitle(a) }}
                  </p>
                  <p v-if="a.detected_at" class="text-[11px] text-gray-500 dark:text-slate-500 mt-1 tabular-nums">
                    {{ a.detected_at }}
                  </p>
                  <p
                    v-if="a.basis"
                    class="text-[11px] text-gray-500 dark:text-slate-500 mt-2 font-mono leading-relaxed break-words bg-white/60 dark:bg-slate-800/60 rounded-lg px-2 py-1.5 border border-gray-100 dark:border-slate-600"
                  >
                    {{ a.basis }}
                  </p>
                </div>
              </div>
              <div class="flex justify-end pt-1 border-t border-gray-100/80 dark:border-slate-700/80">
                <RouterLink
                  :to="alertFollowUp(a)"
                  class="inline-flex items-center gap-1 text-xs font-semibold text-primary-700 dark:text-primary-300 hover:text-primary-900 dark:hover:text-primary-100 transition-colors"
                >
                  {{ followUpLabel(alertFollowUp(a).name) }}
                  <ChevronLeftIcon class="w-3.5 h-3.5 rtl:rotate-180" aria-hidden="true" />
                </RouterLink>
              </div>
            </li>
          </ul>
          <EmptySignalState
            v-else
            title="لا توجد تنبيهات في النافذة"
            hint="التنبيهات تعتمد على أحجام الأحداث وسجلات الفشل — للقراءة فقط."
          />
        </section>
      </div>

      <footer
        v-if="overview?.feature_flags"
        class="text-xs text-gray-400 dark:text-slate-600 border-t border-gray-200 dark:border-slate-700 pt-4"
      >
        <span class="font-medium text-gray-500 dark:text-slate-500">مفاتيح الخادم (قراءة):</span>
        <span class="font-mono ms-2">
          {{ Object.entries(overview.feature_flags).filter(([, v]) => v).map(([k]) => k).join(', ') || '—' }}
        </span>
      </footer>
    </template>
  </div>
</template>

<style scoped>
@keyframes intelHdr {
  from {
    opacity: 0;
    transform: translateY(6px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
