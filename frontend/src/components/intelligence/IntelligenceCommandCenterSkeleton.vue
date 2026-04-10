<script setup lang="ts">
/** Phase 5+ — fast perceived load: shimmer layout before first API slice arrives */
</script>

<template>
  <div class="space-y-8 animate-pulse" aria-hidden="true">
    <div class="space-y-2">
      <div class="h-7 w-56 rounded-lg bg-gray-200 dark:bg-slate-700" />
      <div class="h-4 max-w-xl rounded bg-gray-100 dark:bg-slate-800" />
    </div>

    <div
      class="h-24 rounded-2xl bg-gradient-to-l from-gray-200 to-gray-300 dark:from-slate-700 dark:to-slate-600"
    />

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
      <div v-for="i in 3" :key="i" class="rounded-2xl border border-gray-100 dark:border-slate-700 overflow-hidden">
        <div class="h-14 bg-gray-100 dark:bg-slate-800" />
        <div class="p-4 space-y-3 bg-gray-50/80 dark:bg-slate-900/40">
          <div v-for="j in 2" :key="j" class="h-28 rounded-xl bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700" />
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="h-40 rounded-2xl bg-gray-100 dark:bg-slate-800 border border-gray-100 dark:border-slate-700" />
      <div class="h-40 rounded-2xl bg-gray-100 dark:bg-slate-800 border border-gray-100 dark:border-slate-700" />
    </div>
  </div>
</template>
