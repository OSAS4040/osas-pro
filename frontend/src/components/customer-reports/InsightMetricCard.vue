<template>
  <button
    v-if="clickable"
    type="button"
    class="text-right w-full rounded-2xl border border-slate-200/90 dark:border-slate-700/80 bg-white/90 dark:bg-slate-900/60 shadow-sm hover:shadow-md transition-shadow px-5 py-4 flex flex-col gap-1 min-h-[128px] cursor-pointer hover:border-primary-300/80 dark:hover:border-primary-700/50"
    @click="$emit('click')"
  >
    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ label }}</p>
    <p class="text-2xl font-semibold tabular-nums text-slate-900 dark:text-slate-50 tracking-tight">{{ displayValue }}</p>
    <div v-if="deltaText" class="flex items-center gap-1.5 text-xs">
      <span
        class="inline-flex items-center rounded-md px-1.5 py-0.5 font-medium"
        :class="deltaClass"
      >{{ deltaText }}</span>
      <span v-if="hint" class="text-slate-500 dark:text-slate-400">{{ hint }}</span>
    </div>
    <p v-else-if="hint" class="text-xs text-slate-500 dark:text-slate-400">{{ hint }}</p>
  </button>
  <div
    v-else
    class="text-right w-full rounded-2xl border border-slate-200/90 dark:border-slate-700/80 bg-white/90 dark:bg-slate-900/60 shadow-sm px-5 py-4 flex flex-col gap-1 min-h-[128px]"
  >
    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ label }}</p>
    <p class="text-2xl font-semibold tabular-nums text-slate-900 dark:text-slate-50 tracking-tight">{{ displayValue }}</p>
    <div v-if="deltaText" class="flex items-center gap-1.5 text-xs">
      <span
        class="inline-flex items-center rounded-md px-1.5 py-0.5 font-medium"
        :class="deltaClass"
      >{{ deltaText }}</span>
      <span v-if="hint" class="text-slate-500 dark:text-slate-400">{{ hint }}</span>
    </div>
    <p v-else-if="hint" class="text-xs text-slate-500 dark:text-slate-400">{{ hint }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    label: string
    value: string | number
    deltaPercent?: number | null
    invertDelta?: boolean
    hint?: string
    clickable?: boolean
  }>(),
  { invertDelta: false, clickable: false },
)

defineEmits<{ click: [] }>()

const displayValue = computed(() =>
  typeof props.value === 'number' ? props.value.toLocaleString('ar-SA') : props.value,
)

const deltaText = computed(() => {
  if (props.deltaPercent === null || props.deltaPercent === undefined) return ''
  const sign = props.deltaPercent > 0 ? '+' : ''
  return `${sign}${props.deltaPercent}%`
})

const deltaClass = computed(() => {
  if (props.deltaPercent === null || props.deltaPercent === undefined) return ''
  const up = props.deltaPercent > 0
  const good = props.invertDelta ? !up : up
  if (props.deltaPercent === 0 || Math.abs(props.deltaPercent) < 0.05) {
    return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
  }
  return good
    ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200'
    : 'bg-amber-50 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200'
})
</script>
