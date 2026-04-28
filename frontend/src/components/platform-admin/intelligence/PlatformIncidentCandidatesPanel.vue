<script setup lang="ts">
import { onMounted, watch } from 'vue'
import { usePlatformIntelligenceCandidates } from '@/composables/platform-admin/intelligence/usePlatformIntelligenceCandidates'
import { PLATFORM_INTELLIGENCE_SEVERITY } from '@/types/platform-admin/platformIntelligenceEnums'

const props = defineProps<{
  refreshTick?: number
}>()

const { canLoad, filtered, loading, error, severityFilter, fetchCandidates } = usePlatformIntelligenceCandidates()

onMounted(() => {
  void fetchCandidates()
})

watch(
  () => props.refreshTick,
  () => {
    void fetchCandidates()
  },
)

function severityBadgeClass(sev: string): string {
  if (sev === 'critical' || sev === 'high') return 'bg-rose-100 text-rose-900 dark:bg-rose-950/50 dark:text-rose-100'
  if (sev === 'medium') return 'bg-amber-100 text-amber-950 dark:bg-amber-950/40 dark:text-amber-100'
  if (sev === 'low') return 'bg-slate-200 text-slate-900 dark:bg-slate-700 dark:text-slate-100'
  return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'
}
</script>

<template>
  <section v-if="canLoad" class="mt-6 rounded-2xl border border-slate-200/90 bg-white/90 p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/60" dir="rtl">
    <div class="flex flex-wrap items-end justify-between gap-3 border-b border-slate-200/80 pb-3 dark:border-slate-700">
      <div>
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">مرشّحات حوادث تشغيلية (طبقة مرشّحات فقط)</h3>
        <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
          مخرجات رسمية من عقد المرشّح — بدون مركز حوادث، بدون دورة حياة، بدون أوامر تنفيذ
        </p>
      </div>
      <label class="flex flex-col gap-1 text-[11px] text-slate-600 dark:text-slate-300">
        <span class="font-medium">تصفية الشدة</span>
        <select
          v-model="severityFilter"
          class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs text-slate-900 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
        >
          <option value="">الكل</option>
          <option v-for="s in PLATFORM_INTELLIGENCE_SEVERITY" :key="s" :value="s">{{ s }}</option>
        </select>
      </label>
    </div>

    <p v-if="error" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100" data-testid="platform-candidates-error">
      تعذر تحميل المرشّحات — {{ error }}
    </p>

    <div v-else-if="loading" class="mt-4 space-y-2">
      <div v-for="n in 3" :key="n" class="h-16 animate-pulse rounded-xl bg-slate-100 dark:bg-slate-800/80" />
    </div>

    <p
      v-else-if="filtered.length === 0"
      class="mt-4 rounded-xl border border-dashed border-slate-200 px-4 py-6 text-center text-[11px] text-slate-600 dark:border-slate-600 dark:text-slate-400"
      data-testid="platform-candidates-empty"
    >
      لا توجد مرشّحات مطابقة — قد تكون الإشارات مُقمعّة ضمن قواعد الأهلية/القمع أو لا توجد إشارات مؤهّلة.
    </p>

    <ul v-else class="mt-4 space-y-3" data-testid="platform-candidates-list">
      <li
        v-for="c in filtered"
        :key="c.incident_key"
        class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-3 text-start dark:border-slate-700 dark:bg-slate-950/40"
      >
        <div class="flex flex-wrap items-center gap-2">
          <span :class="['rounded-full px-2 py-0.5 text-[10px] font-bold uppercase', severityBadgeClass(c.severity)]">{{ c.severity }}</span>
          <span class="text-[10px] font-mono text-slate-500 dark:text-slate-400" dir="ltr">{{ c.incident_type }}</span>
          <span class="text-[10px] text-slate-500 dark:text-slate-400">ثقة {{ (c.confidence * 100).toFixed(0) }}٪</span>
          <span class="text-[10px] text-slate-500 dark:text-slate-400">إشارات: {{ c.source_signals.length }}</span>
          <span class="text-[10px] text-slate-500 dark:text-slate-400">شركات: {{ c.affected_companies?.length ?? 0 }}</span>
        </div>
        <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-white">{{ c.title }}</p>
        <p class="mt-1 text-[11px] text-slate-600 dark:text-slate-300">{{ c.grouping_reason }}</p>
        <p class="mt-2 whitespace-pre-line text-[11px] leading-relaxed text-slate-700 dark:text-slate-200">{{ c.why_summary }}</p>
        <ul v-if="c.recommended_actions?.length" class="mt-2 list-disc ps-4 text-[11px] text-primary-900 dark:text-primary-200">
          <li v-for="(a, i) in c.recommended_actions" :key="i">{{ a }}</li>
        </ul>
      </li>
    </ul>
  </section>
</template>
