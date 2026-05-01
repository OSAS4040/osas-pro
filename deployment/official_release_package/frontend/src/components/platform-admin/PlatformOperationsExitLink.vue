<template>
  <RouterLink
    :to="to"
    :title="PLATFORM_OPERATIONS_EXIT_TOOLTIP"
    :aria-label="platformOperationsExitAriaLabel(ariaName)"
    :class="linkClass"
  >
    <span class="flex flex-col gap-0.5">
      <span class="flex items-center gap-1.5">
        <ArrowTopRightOnSquareIcon
          class="h-3.5 w-3.5 shrink-0 text-amber-700 dark:text-amber-400"
          aria-hidden="true"
        />
        <component :is="icon" v-if="icon" class="h-4 w-4 shrink-0 text-emerald-700 dark:text-emerald-400" aria-hidden="true" />
        <span :class="titleClass">
          <slot />
        </span>
      </span>
      <span
        class="block text-[9px] font-semibold leading-snug text-amber-950 dark:text-amber-100/95"
        :class="subtitleClass"
      >
        {{ PLATFORM_OPERATIONS_EXIT_VISIBLE }}
      </span>
    </span>
  </RouterLink>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { Component } from 'vue'
import type { RouteLocationRaw } from 'vue-router'
import { RouterLink } from 'vue-router'
import { ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/outline'
import {
  PLATFORM_OPERATIONS_EXIT_TOOLTIP,
  PLATFORM_OPERATIONS_EXIT_VISIBLE,
  platformOperationsExitAriaLabel,
} from '@/config/platformOperationsHandoff'

const props = withDefaults(
  defineProps<{
    to: RouteLocationRaw | string
    /** اسم قصير يُذكر في aria-label (مثال: الفواتير) */
    ariaName: string
    icon?: Component
    dense?: boolean
    /** card: شريط جانبي — inline: داخل فقرة — toolbar: شريط علوي مضغوط */
    variant?: 'card' | 'inline' | 'toolbar'
  }>(),
  { variant: 'card' },
)

const linkClass = computed(() => {
  const base =
    'group text-right transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900'
  if (props.variant === 'inline') {
    return `${base} my-0.5 inline-flex max-w-[15rem] flex-col items-start rounded-lg border border-amber-200/80 bg-amber-50/50 px-2 py-1 align-baseline hover:border-amber-400 hover:bg-amber-50 dark:border-amber-900/40 dark:bg-amber-950/20 dark:hover:bg-amber-950/35`
  }
  if (props.variant === 'toolbar') {
    return `${base} shrink-0 inline-flex max-w-[11rem] flex-col items-start rounded-lg border border-amber-200/85 bg-amber-50/60 px-2 py-1.5 align-middle shadow-sm hover:border-amber-400 hover:bg-amber-50 dark:border-amber-900/45 dark:bg-amber-950/30 dark:hover:bg-amber-950/45`
  }
  return `${base} flex min-w-0 flex-col rounded-xl border border-amber-200/90 bg-white/95 px-2.5 py-2 shadow-sm hover:border-amber-400 hover:bg-amber-50/40 dark:border-amber-900/50 dark:bg-slate-900/80 dark:hover:border-amber-700 dark:hover:bg-amber-950/25 ${props.dense ? 'py-1.5' : ''}`
})

const titleClass = computed(() => {
  if (props.variant === 'inline') {
    return 'text-[11px] font-bold text-primary-800 underline-offset-2 group-hover:underline dark:text-primary-300'
  }
  if (props.variant === 'toolbar') {
    return 'text-[10px] font-semibold leading-tight text-slate-900 dark:text-slate-100'
  }
  return 'text-xs font-bold text-slate-900 dark:text-slate-100'
})

const subtitleClass = computed(() => {
  if (props.variant === 'inline') return 'max-w-[14rem]'
  if (props.variant === 'toolbar') return 'max-w-[10rem]'
  return 'mr-5'
})
</script>
