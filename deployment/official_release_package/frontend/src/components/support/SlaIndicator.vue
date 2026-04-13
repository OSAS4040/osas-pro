<template>
  <div v-if="ticket.sla_due_at">
    <div class="flex items-center gap-1 text-xs" :class="textColor">
      <ClockIcon class="w-3.5 h-3.5 flex-shrink-0" />
      <span>{{ label }}</span>
    </div>
    <div class="mt-1 h-1.5 rounded-full bg-gray-200 dark:bg-gray-700 w-20 overflow-hidden">
      <div :class="barColor" class="h-full rounded-full transition-all" :style="{ width: barWidth }"></div>
    </div>
  </div>
  <span v-else class="text-gray-400 text-xs">—</span>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { ClockIcon } from '@heroicons/vue/24/outline'

const props = defineProps<{ ticket: any }>()

const pct  = computed(() => Math.min(100, props.ticket.sla_percentage ?? 0))
const mins = computed(() => props.ticket.sla_remaining_minutes ?? 0)

const isBreached = computed(() => props.ticket.sla_breached || props.ticket.is_overdue)

const label = computed(() => {
  if (isBreached.value) return 'تجاوز SLA'
  if (mins.value < 60) return `${mins.value} دقيقة`
  return `${Math.floor(mins.value / 60)} ساعة`
})

const textColor = computed(() =>
  isBreached.value ? 'text-red-600 dark:text-red-400' :
  pct.value > 80   ? 'text-orange-500' :
  'text-gray-500 dark:text-gray-400'
)

const barColor = computed(() =>
  isBreached.value ? 'bg-red-500' :
  pct.value > 80   ? 'bg-orange-400' :
  pct.value > 50   ? 'bg-yellow-400' :
  'bg-green-400'
)

const barWidth = computed(() => `${pct.value}%`)
</script>
