<script setup lang="ts">
import { SignalIcon } from '@heroicons/vue/24/outline'

defineProps<{
  title: string
  hint: string
}>()
</script>

<template>
  <div
    class="flex flex-col items-center justify-center text-center py-10 px-4 rounded-xl border border-dashed border-gray-200 dark:border-slate-600 bg-white/50 dark:bg-slate-800/30"
  >
    <SignalIcon class="w-10 h-10 text-gray-300 dark:text-slate-600 mb-3" aria-hidden="true" />
    <p class="text-sm font-medium text-gray-700 dark:text-slate-300">{{ title }}</p>
    <p class="text-xs text-gray-500 dark:text-slate-500 mt-2 max-w-sm leading-relaxed">{{ hint }}</p>
  </div>
</template>
