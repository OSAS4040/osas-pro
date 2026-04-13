<template>
  <div v-if="insights.length" class="mt-4 rounded-xl border border-slate-200/90 bg-white/70 p-3 dark:border-slate-700 dark:bg-slate-900/50">
    <h3 class="mb-2 text-xs font-bold text-slate-800 dark:text-slate-200">Insights</h3>
    <ul class="space-y-1.5 text-[12px] leading-relaxed text-slate-700 dark:text-slate-300">
      <li v-for="(ins, i) in insights" :key="i" class="flex gap-2">
        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full" :class="dotClass(ins.tone)" />
        <span>{{ ins.text }}</span>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import type { PlatformAdminInsight } from '@/types/platformAdminOverview'

defineProps<{ insights: PlatformAdminInsight[] }>()

function dotClass(tone: string): string {
  if (tone === 'positive') return 'bg-emerald-500'
  if (tone === 'warning' || tone === 'action') return 'bg-amber-500'
  return 'bg-slate-400'
}
</script>
