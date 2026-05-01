<template>
  <article
    class="rounded-xl border px-3.5 py-3 text-[11px] leading-relaxed shadow-sm transition-colors duration-200 dark:shadow-none"
    :class="severityClass"
  >
    <h4 class="text-sm font-semibold text-slate-900 dark:text-white">{{ title }}</h4>
    <p v-if="description" class="mt-1.5 text-slate-600 dark:text-slate-300">{{ description }}</p>
    <slot />
  </article>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    title: string
    description?: string
    severity?: 'low' | 'medium' | 'high'
  }>(),
  {
    description: '',
    severity: 'low',
  },
)

const severityClass = computed(() => {
  if (props.severity === 'high') {
    return 'border-rose-200/85 bg-rose-50/75 text-rose-950 dark:border-rose-900/50 dark:bg-rose-950/25 dark:text-rose-100'
  }
  if (props.severity === 'medium') {
    return 'border-amber-200/85 bg-amber-50/70 text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/25 dark:text-amber-100'
  }
  return 'border-slate-200/85 bg-slate-50/80 text-slate-800 dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-100'
})
</script>
