<template>
  <div class="mt-4 flex flex-wrap items-center gap-2">
    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-bold" :class="chipClass">
      <span class="h-2 w-2 rounded-full" :class="dotClass" />
      {{ label }}
    </span>
    <span class="text-[11px] text-slate-500 dark:text-slate-400">API: {{ health.api }} · Queue: {{ health.queue }}</span>
    <span v-if="health.failed_jobs != null" class="text-[11px] text-slate-500 dark:text-slate-400">Failed jobs: {{ health.failed_jobs }}</span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { PlatformAdminOverviewHealth } from '@/types/platformAdminOverview'

const props = defineProps<{ health: PlatformAdminOverviewHealth }>()

const label = computed(() => (props.health.trend === 'degraded' ? 'Operational attention required' : 'Operational status stable'))
const chipClass = computed(() =>
  props.health.trend === 'degraded'
    ? 'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100'
    : 'border-emerald-200 bg-emerald-50 text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100',
)
const dotClass = computed(() => (props.health.trend === 'degraded' ? 'bg-amber-500' : 'bg-emerald-500'))
</script>
