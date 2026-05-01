<template>
  <div
    class="rounded-2xl border px-4 py-3 text-sm font-medium"
    :class="bannerClass"
  >
    {{ message }}
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  activityStatus: string
  message: string
}>()

const bannerClass = computed(() => {
  switch (props.activityStatus) {
    case 'healthy':
      return 'border-emerald-200/80 bg-emerald-50/80 text-emerald-950 dark:border-emerald-900/40 dark:bg-emerald-950/25 dark:text-emerald-100'
    case 'watch':
      return 'border-amber-200/80 bg-amber-50/80 text-amber-950 dark:border-amber-900/40 dark:bg-amber-950/25 dark:text-amber-100'
    case 'at_risk':
      return 'border-rose-200/80 bg-rose-50/85 text-rose-950 dark:border-rose-900/45 dark:bg-rose-950/30 dark:text-rose-100'
    case 'inactive':
      return 'border-slate-300/90 bg-slate-100/90 text-slate-800 dark:border-slate-600 dark:bg-slate-800/60 dark:text-slate-100'
    default:
      return 'border-slate-200 bg-slate-50 text-slate-800 dark:border-slate-700 dark:bg-slate-800/50'
  }
})
</script>
