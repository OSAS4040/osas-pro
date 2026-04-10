<script setup lang="ts">
/** Skeleton for Now / Next / Watch columns while command-center payload is in flight */
</script>

<template>
  <div class="grid grid-cols-1 xl:grid-cols-3 gap-6" aria-hidden="true">
    <div
      v-for="i in 3"
      :key="i"
      class="rounded-2xl border border-gray-200 dark:border-slate-600 overflow-hidden bg-gray-50/80 dark:bg-slate-900/50 animate-pulse"
    >
      <div class="h-14 bg-gray-200/90 dark:bg-slate-700/80" />
      <div class="p-4 space-y-3">
        <div class="h-24 rounded-xl bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700" />
        <div class="h-24 rounded-xl bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700" />
      </div>
    </div>
  </div>
</template>
