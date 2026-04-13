<script setup lang="ts">
import { computed } from 'vue'
import type { Severity } from '@/composables/useIntelligenceCommandCenter'

const props = defineProps<{
  severity: Severity
}>()

const styles: Record<string, string> = {
  critical: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
  warning: 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
  info: 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
}

const label: Record<string, string> = {
  critical: 'حرج',
  warning: 'تنبيه',
  info: 'معلومة',
}

const badgeClass = computed(() => styles[props.severity] ?? styles.info)
const badgeText = computed(() => label[props.severity] ?? props.severity)
</script>

<template>
  <span
    class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold uppercase tracking-wide"
    :class="badgeClass"
  >
    {{ badgeText }}
  </span>
</template>
