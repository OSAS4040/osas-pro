<template>
  <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-5">
    <PlatformKpiCard
      v-for="card in cards"
      :key="card.key"
      :label="card.label"
      :value="card.value"
      :hint="card.hint || ''"
      :tone="card.tone ?? 'default'"
    />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { PlatformAdminOverviewKpis } from '@/types/platformAdminOverview'
import PlatformKpiCard from '@/components/platform-admin/ui/PlatformKpiCard.vue'

const props = defineProps<{
  kpis: PlatformAdminOverviewKpis
  definitions: Record<string, string>
}>()

const cards = computed(() => {
  const k = props.kpis
  const d = props.definitions
  return [
    { key: 'total', label: 'إجمالي الشركات', value: k.total_companies.toLocaleString('ar-SA'), tone: 'default' as const },
    {
      key: 'active',
      label: 'شركات نشطة (7 أيام)',
      value: k.active_companies.toLocaleString('ar-SA'),
      hint: d.active_company,
      tone: 'success' as const,
    },
    {
      key: 'low',
      label: 'منخفضة النشاط',
      value: k.low_activity_companies.toLocaleString('ar-SA'),
      hint: d.low_activity_company,
      tone: 'warning' as const,
    },
    { key: 'trial', label: 'شركات تجريبية', value: k.trial_companies.toLocaleString('ar-SA'), tone: 'default' as const },
    {
      key: 'churn',
      label: 'مخاطر انقطاع',
      value: k.churn_risk_companies.toLocaleString('ar-SA'),
      tone: k.churn_risk_companies > 0 ? ('danger' as const) : ('default' as const),
    },
    { key: 'users', label: 'إجمالي المستخدمين', value: k.total_users.toLocaleString('ar-SA'), tone: 'default' as const },
    { key: 'subs', label: 'اشتراكات فعّالة', value: k.subscriptions_active.toLocaleString('ar-SA'), tone: 'brand' as const },
    {
      key: 'mrr',
      label: 'التقدير الشهري المتكرر — كتالوج',
      value: `${k.estimated_mrr.toLocaleString('ar-SA')} ر.س`,
      hint: d.catalog_mrr_estimate,
      tone: 'brand' as const,
    },
    { key: 'n7', label: 'شركات جديدة (7 أيام)', value: k.companies_new_7d.toLocaleString('ar-SA'), tone: 'success' as const },
    { key: 'n30', label: 'شركات جديدة (30 يوماً)', value: k.companies_new_30d.toLocaleString('ar-SA'), tone: 'default' as const },
  ]
})
</script>
