<template>
  <div
    v-if="summary && summary.attention_count > 0"
    class="rounded-2xl border border-amber-200/80 dark:border-amber-900/40 bg-amber-50/70 dark:bg-amber-950/25 px-4 py-3 flex flex-wrap gap-3 items-center text-sm"
  >
    <span class="font-semibold text-amber-950 dark:text-amber-100">{{ title }}</span>
    <button
      type="button"
      class="rounded-lg bg-white/90 dark:bg-slate-900/60 border border-amber-200 dark:border-amber-800 px-2 py-1 text-xs font-medium text-amber-900 dark:text-amber-100 hover:bg-amber-100/80"
      @click="$emit('filter-attention', 'important')"
    >
      {{ filterLabel }} ({{ summary.attention_count }})
    </button>
  </div>
</template>

<script setup lang="ts">
import type { GlobalFeedSummary } from '@/types/globalOperationsFeed'

defineProps<{
  summary: GlobalFeedSummary | null
  title: string
  filterLabel: string
}>()

defineEmits<{ 'filter-attention': [level: string] }>()
</script>
