<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  dailyCounts?: { date: string; count: number }[] | null
}>()

const series = computed(() => {
  const raw = props.dailyCounts ?? []
  if (!raw.length) return []
  return raw.slice(-14).map((d) => Math.max(0, Number(d.count) || 0))
})

const pathD = computed(() => {
  const s = series.value
  if (s.length < 2) return ''
  const w = 280
  const h = 48
  const pad = 4
  const max = Math.max(...s, 1)
  const min = 0
  const range = max - min || 1
  const step = (w - pad * 2) / (s.length - 1)
  return s
    .map((v, i) => {
      const x = pad + i * step
      const y = h - pad - ((v - min) / range) * (h - pad * 2)
      return `${i === 0 ? 'M' : 'L'} ${x.toFixed(1)} ${y.toFixed(1)}`
    })
    .join(' ')
})
</script>

<template>
  <div v-if="series.length >= 2" class="mt-3">
    <p class="text-[11px] font-medium text-gray-500 dark:text-slate-400 mb-2">نشاط الأحداث (آخر {{ series.length }} يوماً)</p>
    <svg
      class="w-full max-w-[280px] h-12 text-primary-600 dark:text-primary-400"
      viewBox="0 0 280 48"
      preserveAspectRatio="none"
      aria-hidden="true"
    >
      <path
        v-if="pathD"
        :d="pathD"
        fill="none"
        stroke="currentColor"
        stroke-width="2.25"
        stroke-linecap="round"
        stroke-linejoin="round"
      />
    </svg>
  </div>
</template>
