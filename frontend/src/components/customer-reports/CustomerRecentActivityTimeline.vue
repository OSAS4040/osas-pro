<template>
  <section class="rounded-2xl border border-slate-200/80 dark:border-slate-700/70 bg-white dark:bg-slate-900/40 px-5 py-4 shadow-sm">
    <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100 mb-1">{{ title }}</h2>
    <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">{{ subtitle }}</p>
    <div v-if="!lines.length" class="text-sm text-slate-500 dark:text-slate-400 py-6 text-center rounded-xl bg-slate-50 dark:bg-slate-800/50">
      {{ empty }}
    </div>
    <ul v-else class="space-y-3 border-slate-100 dark:border-slate-800/80">
      <li
        v-for="(row, i) in lines"
        :key="i"
        class="flex gap-3 text-sm"
      >
        <div class="shrink-0 w-24 text-xs text-slate-500 dark:text-slate-400 tabular-nums">
          {{ formatAt(row.at) }}
        </div>
        <div class="min-w-0 flex-1 border-s-slate-200 dark:border-slate-700 ps-3 border-s-2">
          <p class="font-medium text-slate-800 dark:text-slate-100">{{ ar ? row.labelAr : row.labelEn }}</p>
          <p v-if="row.hintAr || row.hintEn" class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            {{ ar ? row.hintAr : row.hintEn }}
          </p>
        </div>
      </li>
    </ul>
  </section>
</template>

<script setup lang="ts">
import type { DerivedActivityLine } from '@/utils/customerPulseRules'

const props = defineProps<{
  lines: DerivedActivityLine[]
  title: string
  subtitle: string
  empty: string
  ar: boolean
}>()

function formatAt(iso: string): string {
  try {
    const d = new Date(iso)
    return d.toLocaleString(props.ar ? 'ar-SA' : 'en-GB', {
      day: '2-digit',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return iso.slice(0, 16)
  }
}
</script>
