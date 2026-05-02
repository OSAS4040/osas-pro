<template>
  <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/70">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="space-y-2">
        <p class="text-[11px] font-bold tracking-wide text-primary-700 dark:text-primary-400">إدارة المنصة — مركز تحكم الشركة</p>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ name }}</h1>
        <div class="flex flex-wrap items-center gap-2 text-xs">
          <span class="rounded-full px-2 py-0.5 font-bold" :class="statusClass">{{ statusLabel }}</span>
          <span class="rounded-full bg-slate-100 px-2 py-0.5 font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-200">الخطة: {{ planLabel }}</span>
          <span class="rounded-full px-2 py-0.5 font-bold" :class="riskClass">المخاطر: {{ riskLabel }}</span>
          <span class="rounded-full bg-primary-100 px-2 py-0.5 font-bold text-primary-900 dark:bg-primary-900/40 dark:text-primary-200">{{ quickIndicator }}</span>
        </div>
      </div>

      <div class="flex flex-wrap gap-2">
        <RouterLink to="/platform/companies" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800/60">العودة إلى الشركات</RouterLink>
        <RouterLink
          v-if="companyId !== ''"
          :to="{ name: 'platform-company-detail', params: { id: companyId }, query: { tab: 'finance' } }"
          :class="shortcutClass('finance')"
        >
          المالية
        </RouterLink>
        <RouterLink
          v-if="companyId !== ''"
          :to="{ name: 'platform-company-detail', params: { id: companyId }, query: { tab: 'customers' } }"
          :class="shortcutClass('customers')"
        >
          العملاء
        </RouterLink>
        <RouterLink
          v-if="companyId !== ''"
          :to="{ name: 'platform-company-detail', params: { id: companyId }, query: { tab: 'vehicles' } }"
          :class="shortcutClass('vehicles')"
        >
          المركبات
        </RouterLink>
        <RouterLink
          v-if="companyId !== ''"
          :to="{ name: 'platform-company-detail', params: { id: companyId }, query: { tab: 'invoices' } }"
          :class="shortcutClass('invoices')"
        >
          الفواتير
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { RouterLink } from 'vue-router'

const props = withDefaults(
  defineProps<{
    name: string
    statusLabel: string
    statusClass: string
    planLabel: string
    riskLabel: string
    riskClass: string
    quickIndicator: string
    /** معرف الشركة في مسار `/platform/companies/:id` — لربط اختصارات التبويب بنفس الصفحة */
    companyId?: string
    /** تبويب الشركة النشط (overview | finance | …) لمظهر الاختصار */
    activeTab?: string
  }>(),
  { companyId: '', activeTab: 'overview' },
)

function shortcutClass(tab: string): string[] {
  const base =
    'rounded-lg border px-3 py-1.5 text-xs font-bold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900'
  const inactive =
    'border-primary-300 text-primary-700 hover:bg-primary-50 dark:border-primary-700 dark:text-primary-300 dark:hover:bg-primary-900/30'
  const active =
    'border-primary-600 bg-primary-50 text-primary-900 shadow-sm ring-1 ring-primary-500/25 dark:border-primary-500 dark:bg-primary-950/50 dark:text-primary-100 dark:ring-primary-400/20'
  return [base, props.activeTab === tab ? active : inactive]
}
</script>
