<template>
  <ul class="space-y-2">
    <li v-for="r in rows" :key="r.status" class="flex items-center gap-2 text-xs">
      <span class="w-28 shrink-0 truncate text-slate-600 dark:text-slate-300 font-medium" :title="r.status">{{ r.status }}</span>
      <div class="flex-1 h-2 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
        <div
          class="h-full rounded-full transition-all"
          :class="barClass"
          :style="{ width: pct(r.count) + '%' }"
        />
      </div>
      <span class="w-8 text-end tabular-nums text-slate-700 dark:text-slate-200">{{ r.count }}</span>
    </li>
  </ul>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { StatusBucketRow } from '@/types/customerPulseReport'

const props = withDefaults(
  defineProps<{
    rows: StatusBucketRow[]
    ar: boolean
    tone?: 'emerald' | 'violet' | 'amber'
  }>(),
  { tone: 'emerald' },
)

const max = computed(() => {
  const m = props.rows.reduce((a, b) => Math.max(a, b.count), 0)
  return m > 0 ? m : 1
})

function pct(n: number): number {
  return Math.min(100, Math.round((n / max.value) * 100))
}

const barClass = computed(() => {
  if (props.tone === 'violet') return 'bg-violet-500/90'
  if (props.tone === 'amber') return 'bg-amber-500/90'
  return 'bg-emerald-500/90'
})
</script>
