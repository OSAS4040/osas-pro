<template>
  <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3">
    <div
      v-for="c in cards"
      :key="c.key"
      class="rounded-2xl border border-slate-200/80 dark:border-slate-700/50 bg-white dark:bg-slate-900/40 px-4 py-3 shadow-sm"
    >
      <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400">{{ c.label }}</p>
      <p class="text-xl font-semibold tabular-nums text-slate-900 dark:text-slate-50 mt-1">{{ c.display }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  cards: { key: string; label: string; display: string | number }[]
}>()
</script>
