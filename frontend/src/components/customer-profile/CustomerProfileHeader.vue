<template>
  <header class="rounded-3xl border border-slate-200/80 dark:border-slate-700/60 bg-white/95 dark:bg-slate-900/45 px-6 py-5 shadow-sm">
    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
      <div>
        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ kicker }}</p>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-50 mt-1">{{ name }}</h1>
        <div class="flex flex-wrap items-center gap-2 mt-2">
          <span class="text-xs px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200">{{ typeLabel }}</span>
          <span v-if="createdLine" class="text-xs text-slate-500 dark:text-slate-400">{{ createdLine }}</span>
          <span v-if="lastActivity" class="text-xs text-slate-500 dark:text-slate-400">{{ lastActivity }}</span>
        </div>
      </div>
      <div class="flex flex-wrap gap-2 shrink-0">
        <RouterLink
          v-if="showPulse"
          :to="`/customers/${customerId}/reports`"
          class="btn text-sm border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800"
        >
          {{ pulseLabel }}
        </RouterLink>
        <RouterLink
          :to="`/work-orders?customer_id=${customerId}`"
          class="btn text-sm border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800"
        >
          {{ workOrdersLabel }}
        </RouterLink>
        <RouterLink
          v-if="showOps"
          to="/operations/global-feed"
          class="btn text-sm border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800"
        >
          {{ opsLabel }}
        </RouterLink>
        <RouterLink
          :to="`/vehicles?customer_id=${customerId}`"
          class="btn text-sm border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800"
        >
          {{ vehiclesLabel }}
        </RouterLink>
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { RouterLink } from 'vue-router'

defineProps<{
  kicker: string
  name: string
  typeLabel: string
  customerId: number
  createdLine: string
  lastActivity: string
  pulseLabel: string
  workOrdersLabel: string
  opsLabel: string
  vehiclesLabel: string
  showPulse: boolean
  showOps: boolean
}>()
</script>
