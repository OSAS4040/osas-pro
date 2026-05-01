<template>
  <div class="rounded-2xl border border-dashed border-slate-300 dark:border-slate-600 bg-slate-50/80 dark:bg-slate-800/40 px-8 py-14 text-center">
    <p class="text-slate-700 dark:text-slate-200 font-medium">{{ title }}</p>
    <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">{{ hint }}</p>
  </div>
</template>

<script setup lang="ts">
defineProps<{ title: string; hint: string }>()
</script>
