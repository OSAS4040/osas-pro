<script setup lang="ts">
defineProps<{
  label?: string
  text: string
}>()
</script>

<template>
  <div class="rounded-lg bg-white/60 dark:bg-slate-800/60 border border-gray-100 dark:border-slate-600 p-3">
    <p class="text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1">{{ label ?? 'إجراء مقترح' }}</p>
    <p class="text-sm text-gray-800 dark:text-slate-200 leading-relaxed whitespace-pre-wrap">{{ text }}</p>
  </div>
</template>
