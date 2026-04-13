<template>
  <header class="rounded-3xl border border-slate-200/80 dark:border-slate-700/70 bg-white/95 dark:bg-slate-900/50 px-6 py-5 shadow-sm">
    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
      <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ kicker }}</p>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-50 mt-1">{{ title }}</h1>
        <p class="text-sm text-slate-600 dark:text-slate-300 mt-1 max-w-2xl">{{ subtitle }}</p>
        <div class="flex flex-wrap gap-3 mt-3 text-xs text-slate-500 dark:text-slate-400">
          <span v-if="lastUpdated">{{ lastUpdated }}</span>
          <span v-if="periodText">{{ periodText }}</span>
          <span v-if="resultCount !== null" class="font-semibold text-slate-700 dark:text-slate-200">{{ resultCount }}</span>
        </div>
        <div v-if="chips.length" class="flex flex-wrap gap-1.5 mt-3">
          <span
            v-for="c in chips"
            :key="c"
            class="inline-flex items-center rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200/80 dark:border-slate-600 px-2 py-0.5 text-[11px] text-slate-700 dark:text-slate-200"
          >{{ c }}</span>
        </div>
      </div>
      <div class="flex flex-wrap gap-2 shrink-0">
        <slot name="actions" />
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
defineProps<{
  kicker: string
  title: string
  subtitle: string
  lastUpdated: string
  periodText: string
  resultCount: string | null
  chips: string[]
}>()
</script>
