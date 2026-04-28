<template>
  <article class="rounded-xl border p-3 shadow-sm transition-colors" :class="toneClass">
    <p class="text-[10px] font-bold" :class="labelClass">{{ label }}</p>
    <p class="mt-1 text-lg font-semibold tabular-nums" :class="valueClass">{{ value }}</p>
    <p v-if="hint" class="mt-1 text-[10px]" :class="hintClass">{{ hint }}</p>
    <slot />
  </article>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    label: string
    value: string
    hint?: string
    tone?: 'default' | 'success' | 'warning' | 'danger' | 'brand'
  }>(),
  {
    hint: '',
    tone: 'default',
  },
)

const toneClass = computed(() => {
  if (props.tone === 'success') return 'border-emerald-200/80 bg-emerald-50/70 dark:border-emerald-900/40 dark:bg-emerald-950/25'
  if (props.tone === 'warning') return 'border-amber-200/80 bg-amber-50/70 dark:border-amber-900/40 dark:bg-amber-950/25'
  if (props.tone === 'danger') return 'border-rose-200/80 bg-rose-50/70 dark:border-rose-900/40 dark:bg-rose-950/25'
  if (props.tone === 'brand') return 'border-primary-200/80 bg-primary-50/70 dark:border-primary-900/40 dark:bg-primary-900/25'
  return 'border-slate-200/90 bg-white dark:border-slate-700 dark:bg-slate-900/70'
})

const labelClass = computed(() => {
  if (props.tone === 'success') return 'text-emerald-900 dark:text-emerald-200'
  if (props.tone === 'warning') return 'text-amber-900 dark:text-amber-200'
  if (props.tone === 'danger') return 'text-rose-900 dark:text-rose-200'
  if (props.tone === 'brand') return 'text-primary-900 dark:text-primary-200'
  return 'text-slate-500 dark:text-slate-400'
})

const valueClass = computed(() => {
  if (props.tone === 'success') return 'text-emerald-950 dark:text-emerald-100'
  if (props.tone === 'warning') return 'text-amber-950 dark:text-amber-100'
  if (props.tone === 'danger') return 'text-rose-950 dark:text-rose-100'
  if (props.tone === 'brand') return 'text-primary-900 dark:text-primary-100'
  return 'text-slate-900 dark:text-white'
})

const hintClass = computed(() => (props.tone === 'default' ? 'text-slate-500 dark:text-slate-400' : labelClass.value))
</script>
