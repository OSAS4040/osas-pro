<script setup lang="ts">
import { onMounted, watch } from 'vue'
import { usePlatformIntelligenceSignals } from '@/composables/platform-admin/intelligence/usePlatformIntelligenceSignals'
import { PLATFORM_INTELLIGENCE_SEVERITY } from '@/types/platform-admin/platformIntelligenceEnums'

const props = defineProps<{
  /** Bump from parent to refetch after overview refresh */
  refreshTick?: number
}>()

const { canLoad, filtered, loading, error, severityFilter, fetchSignals } = usePlatformIntelligenceSignals()

onMounted(() => {
  void fetchSignals()
})

watch(
  () => props.refreshTick,
  () => {
    void fetchSignals()
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
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">إشارات التشغيل (محرك المنصة)</h3>
        <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">قراءة فقط — بدون حوادث أو قرارات أو أوامر تنفيذ</p>
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

    <p v-if="error" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100" data-testid="platform-signals-error">
      تعذر تحميل الإشارات — {{ error }}
    </p>

    <div v-else-if="loading" class="mt-4 space-y-2">
      <div v-for="n in 3" :key="n" class="h-16 animate-pulse rounded-xl bg-slate-100 dark:bg-slate-800/80" />
    </div>

    <p
      v-else-if="filtered.length === 0"
      class="mt-4 rounded-xl border border-dashed border-slate-200 px-4 py-6 text-center text-[11px] text-slate-600 dark:border-slate-600 dark:text-slate-400"
      data-testid="platform-signals-empty"
    >
      لا توجد إشارات مطابقة للتصفية الحالية — قد يعني ذلك استقراراً في لقطة الملخص أو حاجة لتحديث البيانات.
    </p>

    <ul v-else class="mt-4 space-y-3" data-testid="platform-signals-list">
      <li
        v-for="sig in filtered"
        :key="sig.signal_key"
        class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-3 text-start dark:border-slate-700 dark:bg-slate-950/40"
      >
        <div class="flex flex-wrap items-center gap-2">
          <span :class="['rounded-full px-2 py-0.5 text-[10px] font-bold uppercase', severityBadgeClass(sig.severity)]">{{ sig.severity }}</span>
          <span class="text-[10px] font-mono text-slate-500 dark:text-slate-400" dir="ltr">{{ sig.signal_key }}</span>
          <span class="text-[10px] text-slate-500 dark:text-slate-400">ثقة {{ (sig.confidence * 100).toFixed(0) }}٪</span>
          <span class="text-[10px] text-slate-500 dark:text-slate-400">مصدر: {{ sig.source }}</span>
        </div>
        <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-white">{{ sig.title }}</p>
        <p class="mt-1 text-[12px] leading-relaxed text-slate-700 dark:text-slate-200">{{ sig.summary }}</p>
        <p class="mt-2 whitespace-pre-line text-[11px] leading-relaxed text-slate-600 dark:text-slate-300">{{ sig.why_summary }}</p>
        <p v-if="sig.affected_companies?.length" class="mt-1 text-[10px] text-slate-500 dark:text-slate-400" dir="ltr">
          شركات: {{ sig.affected_companies.join(', ') }}
        </p>
        <p class="mt-2 rounded-lg bg-white/70 px-2 py-1.5 text-[11px] text-primary-900 dark:bg-slate-900/60 dark:text-primary-200">
          {{ sig.recommended_next_step }}
        </p>
      </li>
    </ul>
  </section>
</template>
