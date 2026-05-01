<template>
  <section class="rounded-2xl border border-slate-200/80 dark:border-slate-700/70 bg-white dark:bg-slate-900/40 px-5 py-4 shadow-sm">
    <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100 mb-4">{{ title }}</h2>
    <div class="grid gap-6 md:grid-cols-2">
      <div>
        <h3 class="text-xs font-medium text-slate-500 dark:text-slate-400 mb-2">{{ woTitle }}</h3>
        <BreakdownBars :rows="workOrders" :ar="ar" />
      </div>
      <div v-if="financial && invoices.length">
        <h3 class="text-xs font-medium text-slate-500 dark:text-slate-400 mb-2">{{ invTitle }}</h3>
        <BreakdownBars :rows="invoices" :ar="ar" tone="violet" />
      </div>
      <div v-if="tickets.length" class="md:col-span-2">
        <h3 class="text-xs font-medium text-slate-500 dark:text-slate-400 mb-2">{{ tkTitle }}</h3>
        <BreakdownBars :rows="tickets" :ar="ar" tone="amber" />
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import type { StatusBucketRow } from '@/types/customerPulseReport'
import BreakdownBars from './CustomerBreakdownBars.vue'

defineProps<{
  title: string
  woTitle: string
  invTitle: string
  tkTitle: string
  workOrders: StatusBucketRow[]
  invoices: StatusBucketRow[]
  tickets: StatusBucketRow[]
  financial: boolean
  ar: boolean
}>()
</script>
