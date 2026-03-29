<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useIntelligenceCommandCenter, type AlertRow } from '@/composables/useIntelligenceCommandCenter'
import IntelligenceSummaryStrip from '@/components/intelligence/IntelligenceSummaryStrip.vue'
import CommandZoneCard from '@/components/intelligence/CommandZoneCard.vue'
import SeverityBadge from '@/components/intelligence/SeverityBadge.vue'
import EmptySignalState from '@/components/intelligence/EmptySignalState.vue'
import { ExclamationTriangleIcon, InformationCircleIcon } from '@heroicons/vue/24/outline'

const {
  loading,
  error,
  refreshedAt,
  traceId,
  overview,
  insights,
  alerts,
  commandCenter,
  loadAll,
} = useIntelligenceCommandCenter()

onMounted(() => {
  loadAll()
})

const summary = computed(() => {
  const s = commandCenter.value?.summary
  return {
    total_now: s?.total_now ?? 0,
    total_next: s?.total_next ?? 0,
    total_watch: s?.total_watch ?? 0,
    low_signal: s?.low_signal ?? true,
  }
})

const zones = computed(() => ({
  now: commandCenter.value?.zones?.now ?? [],
  next: commandCenter.value?.zones?.next ?? [],
  watch: commandCenter.value?.zones?.watch ?? [],
}))

const meaningfulAlerts = computed(() => {
  const list = alerts.value ?? []
  const warnings = list.filter((a) => a.severity === 'warning')
  const rest = list.filter((a) => a.severity !== 'warning')
  return [...warnings, ...rest].slice(0, 5)
})

const insightCounts = computed(() => {
  const i = insights.value
  if (!i) return null
  return {
    events: i.totals?.events ?? 0,
    types: i.by_event_name?.length ?? 0,
    last: i.last_occurred_at,
  }
})

function alertTitle(a: AlertRow): string {
  return a.message?.slice(0, 120) ?? a.id
}
</script>

<template>
  <div class="max-w-7xl mx-auto space-y-8 pb-12">
    <header class="space-y-2">
      <h2 class="text-xl font-bold text-gray-900 dark:text-slate-100">مركز العمليات الذكي</h2>
      <p class="text-sm text-gray-600 dark:text-slate-400 max-w-2xl leading-relaxed">
        لوحة داخلية للقراءة فقط — تلخّص إشارات التشغيل من طبقة الذكاء دون أي تنفيذ أو تعديل على البيانات.
      </p>
    </header>

    <div
      v-if="loading && !commandCenter && !insights"
      class="rounded-2xl border border-gray-200 dark:border-slate-600 p-12 text-center text-gray-500 dark:text-slate-400"
    >
      جاري تحميل الإشارات…
    </div>

    <div
      v-else-if="error && !commandCenter && !insights"
      class="rounded-2xl border border-red-200 dark:border-red-900/50 bg-red-50/80 dark:bg-red-950/30 p-6 flex gap-3 items-start"
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
      <IntelligenceSummaryStrip
        :total-now="summary.total_now"
        :total-next="summary.total_next"
        :total-watch="summary.total_watch"
        :refreshed-at="refreshedAt"
        :trace-id="traceId"
        :loading="loading"
        @refresh="loadAll"
      />

      <div
        v-if="summary.low_signal && commandCenter"
        class="rounded-xl border border-amber-200 dark:border-amber-900/40 bg-amber-50/90 dark:bg-amber-950/20 px-4 py-3 flex gap-2 items-start"
      >
        <InformationCircleIcon class="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" />
        <div class="text-sm text-amber-900 dark:text-amber-100">
          <p class="font-medium">إشارات تشغيل منخفضة في النافذة الحالية</p>
          <p class="text-amber-800/90 dark:text-amber-200/90 mt-1 text-xs leading-relaxed">
            عند زيادة حركة الأحداث المسجّلة (Phase 1) واتساع نافذة الزمن ستظهر عناصر أوضح في «الآن» و«التالي».
            هذه اللوحة لا تنفّذ أي إجراء — للمراقبة فقط.
          </p>
        </div>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
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
        <section
          class="rounded-2xl border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800/60 p-5 shadow-sm"
        >
          <h3 class="text-sm font-bold text-gray-900 dark:text-slate-100 mb-4">لمحة الرؤى</h3>
          <template v-if="insightCounts">
            <dl class="grid grid-cols-2 gap-3 text-sm">
              <div class="rounded-lg bg-gray-50 dark:bg-slate-900/50 p-3">
                <dt class="text-xs text-gray-500 dark:text-slate-400">أحداث في النافذة</dt>
                <dd class="text-xl font-bold text-gray-900 dark:text-slate-100 tabular-nums">{{ insightCounts.events }}</dd>
              </div>
              <div class="rounded-lg bg-gray-50 dark:bg-slate-900/50 p-3">
                <dt class="text-xs text-gray-500 dark:text-slate-400">أنواع الأحداث</dt>
                <dd class="text-xl font-bold text-gray-900 dark:text-slate-100 tabular-nums">{{ insightCounts.types }}</dd>
              </div>
            </dl>
            <p v-if="insightCounts.last" class="text-xs text-gray-500 dark:text-slate-500 mt-3">
              آخر حدث: {{ insightCounts.last }}
            </p>
          </template>
          <EmptySignalState
            v-else
            title="لا تتوفر رؤى"
            hint="تعذّر تحميل واجهة الرؤى — تحقق من إعدادات الخادم."
          />
        </section>

        <section
          class="rounded-2xl border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800/60 p-5 shadow-sm"
        >
          <h3 class="text-sm font-bold text-gray-900 dark:text-slate-100 mb-4">تنبيهات تشغيلية</h3>
          <ul v-if="meaningfulAlerts.length" class="space-y-3">
            <li
              v-for="a in meaningfulAlerts"
              :key="a.id"
              class="rounded-lg border border-gray-100 dark:border-slate-600 p-3 flex gap-3 items-start"
            >
              <SeverityBadge :severity="a.severity" />
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-gray-900 dark:text-slate-100">{{ alertTitle(a) }}</p>
                <p v-if="a.basis" class="text-xs text-gray-500 dark:text-slate-500 mt-1 font-mono break-all">
                  {{ a.basis }}
                </p>
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
