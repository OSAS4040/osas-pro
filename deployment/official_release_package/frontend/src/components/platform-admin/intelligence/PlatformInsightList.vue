<template>
  <div>
    <div v-if="loading" class="flex flex-wrap gap-3">
      <div v-for="s in 2" :key="'sk-'+s" class="h-28 flex-1 min-w-[200px] animate-pulse rounded-xl bg-slate-200/80 dark:bg-slate-800/80" />
    </div>
    <div v-else-if="insights.length === 0" class="rounded-xl border border-dashed border-slate-300 bg-slate-50/80 px-4 py-6 text-center text-sm text-slate-600 dark:border-slate-600 dark:bg-slate-900/40 dark:text-slate-400">
      لا توجد إشارات مالية حالياً
    </div>
    <div v-else class="grid gap-3 sm:grid-cols-1 lg:grid-cols-2">
      <PlatformInsightCard v-for="ins in insights" :key="ins.id" :insight="ins" />
    </div>
  </div>
</template>

<script setup lang="ts">
import PlatformInsightCard from './PlatformInsightCard.vue'
import type { PlatformFinanceInsight } from './platformInsightTypes'

defineProps<{
  insights: PlatformFinanceInsight[]
  loading?: boolean
}>()
</script>
