<template>
  <div class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-5">
    <div v-for="card in cards" :key="card.key" class="rounded-xl border border-white/80 bg-white/90 p-3 shadow-sm ring-1 ring-violet-100/60 dark:border-slate-700 dark:bg-slate-900/70 dark:ring-violet-900/30">
      <div class="text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ card.label }}</div>
      <div class="mt-1 text-xl font-black tabular-nums text-slate-900 dark:text-white">{{ card.value }}</div>
      <div v-if="card.hint" class="mt-1 text-[10px] leading-snug text-slate-500 dark:text-slate-500">{{ card.hint }}</div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { PlatformAdminOverviewKpis } from '@/types/platformAdminOverview'

const props = defineProps<{
  kpis: PlatformAdminOverviewKpis
  definitions: Record<string, string>
}>()

const cards = computed(() => [
  { key: 'total', label: 'Total Companies', value: props.kpis.total_companies.toLocaleString('ar-SA') },
  { key: 'active', label: 'Active Companies', value: props.kpis.active_companies.toLocaleString('ar-SA'), hint: props.definitions.active_company },
  { key: 'low', label: 'Low Activity', value: props.kpis.low_activity_companies.toLocaleString('ar-SA'), hint: props.definitions.low_activity_company },
  { key: 'trial', label: 'Trial Companies', value: props.kpis.trial_companies.toLocaleString('ar-SA') },
  { key: 'churn', label: 'Churn Risk', value: props.kpis.churn_risk_companies.toLocaleString('ar-SA') },
  { key: 'users', label: 'Total Users', value: props.kpis.total_users.toLocaleString('ar-SA') },
  { key: 'subs', label: 'Active Subscriptions', value: props.kpis.subscriptions_active.toLocaleString('ar-SA') },
  { key: 'mrr', label: 'Estimated MRR', value: `${props.kpis.estimated_mrr.toLocaleString('ar-SA')} ر.س`, hint: props.definitions.catalog_mrr_estimate },
  { key: 'n7', label: 'New 7d', value: props.kpis.companies_new_7d.toLocaleString('ar-SA') },
  { key: 'n30', label: 'New 30d', value: props.kpis.companies_new_30d.toLocaleString('ar-SA') },
])
</script>
