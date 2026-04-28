<template>
  <article
    class="overflow-hidden rounded-xl border-2 bg-white shadow-sm dark:bg-slate-900/90"
    :class="severityBorderClass"
  >
    <div class="flex flex-wrap items-start justify-between gap-2 border-b border-slate-100/90 px-3 py-2.5 dark:border-slate-800">
      <h4 class="text-sm font-semibold leading-snug text-slate-900 dark:text-white">{{ insight.title }}</h4>
      <span
        class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold"
        :class="severityPillClass"
      >{{ severityLabel }}</span>
    </div>
    <div class="px-3 py-2.5">
      <p v-if="!detailOpen && firstReason" class="text-[11px] leading-relaxed text-slate-600 dark:text-slate-400">
        {{ firstReason }}
      </p>
      <div v-if="detailOpen" class="space-y-3 text-[11px] leading-relaxed">
        <div>
          <p class="mb-1 font-bold text-slate-800 dark:text-slate-200">لماذا؟</p>
          <ul class="list-disc pr-4 text-slate-600 dark:text-slate-400">
            <li v-for="(r, i) in insight.reasons" :key="'r-'+insight.id+'-'+i">{{ r }}</li>
          </ul>
        </div>
        <div>
          <p class="mb-1 font-bold text-slate-800 dark:text-slate-200">الإشارات</p>
          <ul class="space-y-0.5 text-[10px] font-semibold text-primary-800 dark:text-primary-300">
            <li v-for="(s, i) in insight.signals" :key="'s-'+insight.id+'-'+i">{{ s }}</li>
          </ul>
        </div>
        <div>
          <p class="mb-1 font-bold text-slate-800 dark:text-slate-200">التوصية</p>
          <ul class="list-disc pr-4 text-slate-600 dark:text-slate-400">
            <li v-for="(rec, i) in insight.recommendations" :key="'rec-'+insight.id+'-'+i">{{ rec }}</li>
          </ul>
        </div>
      </div>
      <div class="mt-3">
        <div class="mb-1 flex items-center justify-between gap-2">
          <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400">مستوى الثقة</span>
          <span class="tabular-nums text-[10px] font-bold text-slate-700 dark:text-slate-300">{{ confidencePct }}٪</span>
        </div>
        <div class="h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700" role="progressbar" :aria-valuenow="confidencePct" aria-valuemin="0" aria-valuemax="100">
          <div
            class="h-full rounded-full transition-all duration-300"
            :class="confidenceBarClass"
            :style="{ width: confidencePct + '%' }"
          />
        </div>
      </div>
      <button
        type="button"
        class="mt-3 text-xs font-bold text-primary-700 underline-offset-2 hover:underline dark:text-primary-400"
        @click="detailOpen = !detailOpen"
      >
        {{ detailOpen ? 'إخفاء التفاصيل' : 'عرض التفاصيل' }}
      </button>
    </div>
  </article>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import type { PlatformFinanceInsight } from './platformInsightTypes'

const props = defineProps<{
  insight: PlatformFinanceInsight
}>()

const detailOpen = ref(false)

const firstReason = computed(() => props.insight.reasons[0] ?? '')

const confidencePct = computed(() => {
  const n = Number(props.insight.confidence)
  if (Number.isNaN(n)) return 0
  return Math.max(0, Math.min(100, Math.round(n)))
})

const severityLabel = computed(() => {
  if (props.insight.severity === 'risk') return 'مخاطرة'
  if (props.insight.severity === 'warning') return 'تنبيه'
  return 'معلومة'
})

const severityBorderClass = computed(() => {
  if (props.insight.severity === 'risk') {
    return 'border-rose-400/90 dark:border-rose-700/80'
  }
  if (props.insight.severity === 'warning') {
    return 'border-amber-400/90 dark:border-amber-700/70'
  }
  return 'border-primary-400/80 dark:border-primary-700/70'
})

const severityPillClass = computed(() => {
  if (props.insight.severity === 'risk') {
    return 'bg-rose-100 text-rose-900 dark:bg-rose-950/50 dark:text-rose-100'
  }
  if (props.insight.severity === 'warning') {
    return 'bg-amber-100 text-amber-950 dark:bg-amber-950/40 dark:text-amber-100'
  }
  return 'bg-primary-100 text-primary-900 dark:bg-primary-900/40 dark:text-primary-100'
})

const confidenceBarClass = computed(() => {
  if (props.insight.severity === 'risk') return 'bg-rose-500 dark:bg-rose-600'
  if (props.insight.severity === 'warning') return 'bg-amber-500 dark:bg-amber-600'
  return 'bg-primary-500 dark:bg-primary-600'
})
</script>
