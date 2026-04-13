<template>
  <aside v-if="items.length" class="rounded-2xl border border-slate-200/80 dark:border-slate-700/50 bg-white dark:bg-slate-900/40 p-4">
    <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-2">{{ title }}</h2>
    <ul class="space-y-2">
      <li
        v-for="(it, i) in items"
        :key="i"
        class="text-sm rounded-lg px-3 py-2 border"
        :class="severityClass(it.severity)"
      >
        {{ labelFor(it) }}
      </li>
    </ul>
  </aside>
</template>

<script setup lang="ts">
import type { OperationalAttentionItem } from '@/types/operationalIntelligence'

const props = defineProps<{
  title: string
  items: OperationalAttentionItem[]
  labelFor: (it: OperationalAttentionItem) => string
}>()

function severityClass(s: string): string {
  if (s === 'high') return 'border-rose-200 bg-rose-50/90 text-rose-900 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-100'
  if (s === 'medium') return 'border-orange-200 bg-orange-50/90 text-orange-950 dark:border-orange-900/40 dark:bg-orange-950/25 dark:text-orange-100'
  return 'border-amber-200 bg-amber-50/80 text-amber-950 dark:border-amber-900/35 dark:bg-amber-950/20 dark:text-amber-100'
}
</script>
