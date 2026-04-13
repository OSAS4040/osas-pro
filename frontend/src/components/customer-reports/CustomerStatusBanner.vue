<template>
  <div
    class="rounded-2xl px-4 py-3 flex items-start gap-3 border text-sm leading-relaxed"
    :class="wrapClass"
    role="status"
  >
    <span class="text-lg leading-none shrink-0 select-none" aria-hidden="true">{{ icon }}</span>
    <p class="text-slate-800 dark:text-slate-100">{{ message }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  tone: 'success' | 'warning' | 'danger' | 'neutral'
  message: string
}>()

const icon = computed(() => {
  switch (props.tone) {
    case 'success':
      return '🟢'
    case 'warning':
      return '🟠'
    case 'danger':
      return '🔴'
    default:
      return '⚪'
  }
})

const wrapClass = computed(() => {
  switch (props.tone) {
    case 'success':
      return 'border-emerald-200/90 bg-emerald-50/80 dark:border-emerald-900/50 dark:bg-emerald-950/25'
    case 'warning':
      return 'border-amber-200/90 bg-amber-50/85 dark:border-amber-900/45 dark:bg-amber-950/25'
    case 'danger':
      return 'border-rose-200/90 bg-rose-50/85 dark:border-rose-900/45 dark:bg-rose-950/25'
    default:
      return 'border-slate-200/90 bg-slate-50/90 dark:border-slate-700 dark:bg-slate-800/50'
  }
})
</script>
