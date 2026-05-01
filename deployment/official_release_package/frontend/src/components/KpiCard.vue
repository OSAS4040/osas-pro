<template>
  <div class="card p-4 flex items-center gap-4" :class="bgClass">
    <div class="text-3xl">{{ icon }}</div>
    <div class="min-w-0 flex-1">
      <div class="text-xs text-gray-500 dark:text-slate-400 truncate">{{ title }}</div>
      <div class="text-xl font-bold mt-0.5" :class="textClass">{{ value }}</div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  title: string
  value: string | number
  icon?: string
  color?: 'blue' | 'green' | 'orange' | 'purple' | 'teal' | 'indigo' | 'rose' | 'red'
}>()

const colorMap: Record<string, { bg: string; text: string }> = {
  blue:   { bg: 'border-l-4 border-blue-500',   text: 'text-blue-700 dark:text-blue-400' },
  green:  { bg: 'border-l-4 border-primary-500',  text: 'text-primary-700 dark:text-primary-400' },
  orange: { bg: 'border-l-4 border-orange-500', text: 'text-orange-700 dark:text-orange-400' },
  purple: { bg: 'border-l-4 border-primary-500', text: 'text-primary-700 dark:text-primary-400' },
  teal:   { bg: 'border-l-4 border-primary-500', text: 'text-primary-700 dark:text-primary-400' },
  indigo: { bg: 'border-l-4 border-indigo-500', text: 'text-indigo-700 dark:text-indigo-400' },
  rose:   { bg: 'border-l-4 border-rose-500',   text: 'text-rose-700 dark:text-rose-400' },
  red:    { bg: 'border-l-4 border-red-500',     text: 'text-red-700 dark:text-red-400' },
}

const bgClass   = computed(() => colorMap[props.color ?? 'blue']?.bg ?? '')
const textClass = computed(() => colorMap[props.color ?? 'blue']?.text ?? 'text-gray-800 dark:text-white')
</script>
