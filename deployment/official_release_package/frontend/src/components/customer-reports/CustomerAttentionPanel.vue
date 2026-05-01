<template>
  <section
    v-if="items.length"
    class="rounded-2xl border border-rose-200/70 dark:border-rose-900/40 bg-rose-50/50 dark:bg-rose-950/20 px-5 py-4"
  >
    <h2 class="text-sm font-semibold text-rose-900 dark:text-rose-100 mb-2 flex items-center gap-2">
      <span class="inline-block h-2 w-2 rounded-full bg-rose-500" aria-hidden="true" />
      {{ title }}
    </h2>
    <ul class="space-y-2">
      <li
        v-for="(it, i) in items"
        :key="i"
        class="text-sm flex items-start gap-2"
        :class="severityClass(it.severity)"
      >
        <span class="mt-0.5 text-xs font-bold tabular-nums">{{ i + 1 }}.</span>
        <span>{{ ar ? it.textAr : it.textEn }}</span>
      </li>
    </ul>
  </section>
</template>

<script setup lang="ts">
import type { AttentionItem } from '@/utils/customerPulseRules'

defineProps<{
  items: AttentionItem[]
  title: string
  ar: boolean
}>()

function severityClass(s: AttentionItem['severity']): string {
  if (s === 'high') return 'text-rose-900 dark:text-rose-100'
  if (s === 'medium') return 'text-rose-800/95 dark:text-rose-200/95'
  return 'text-rose-800/80 dark:text-rose-200/80'
}
</script>
