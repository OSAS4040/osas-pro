<template>
  <div :class="colorClasses" class="rounded-xl p-4 flex flex-col gap-1">
    <span class="text-xs font-medium opacity-75">{{ label }}</span>
    <span class="text-2xl font-bold">{{ value }}</span>
  </div>
</template>
<script setup lang="ts">
const props = defineProps<{ label: string; value: string | number; color: string; icon?: string }>()
const palette: Record<string, string> = {
  blue:   'bg-blue-50   dark:bg-blue-900/30  text-blue-700   dark:text-blue-300',
  yellow: 'bg-yellow-50 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300',
  indigo: 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300',
  green:  'bg-green-50  dark:bg-green-900/30  text-green-700  dark:text-green-300',
  red:    'bg-red-50    dark:bg-red-900/30    text-red-700    dark:text-red-300',
  purple: 'bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300',
  amber:  'bg-amber-50  dark:bg-amber-900/30  text-amber-700  dark:text-amber-300',
}
const colorClasses = palette[props.color] ?? palette.blue
</script>
