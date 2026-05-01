<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  plan?: string
  label?: string
}>()

const displayText = computed(() => {
  const lbl = props.label
  if (lbl && String(lbl).trim() !== '') return String(lbl)
  const m: Record<string, string> = {
    trial: 'تجريبي',
    basic: 'أساسي',
    professional: 'احترافي',
    enterprise: 'مؤسسي',
  }
  const p = props.plan ?? ''

  return m[p] || p || '—'
})

const badgeClass = computed(() => {
  const p = props.plan ?? ''
  const map: Record<string, string> = {
    trial: 'bg-amber-500/15 text-amber-800 ring-1 ring-amber-500/25 dark:text-amber-300',
    basic: 'bg-sky-500/15 text-sky-800 ring-1 ring-sky-500/25 dark:text-sky-300',
    professional: 'bg-primary-500/15 text-primary-800 ring-1 ring-primary-500/25 dark:text-primary-300',
    enterprise: 'bg-emerald-500/15 text-emerald-800 ring-1 ring-emerald-500/25 dark:text-emerald-300',
  }

  return map[p] || 'bg-slate-500/10 text-slate-700 ring-1 ring-slate-500/20 dark:text-slate-300'
})
</script>

<template>
  <span :class="[badgeClass, 'inline-flex max-w-full items-center truncate rounded-full px-2 py-0.5 text-xs font-semibold']">{{ displayText }}</span>
</template>
