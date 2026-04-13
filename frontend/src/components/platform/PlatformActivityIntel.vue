<template>
  <div class="mt-4 overflow-hidden rounded-xl border border-slate-200/90 bg-white/90 shadow-sm dark:border-slate-700/80 dark:bg-slate-900/50">
    <div class="border-b border-slate-100 bg-slate-50/90 px-3 py-2 dark:border-slate-800 dark:bg-slate-900/80">
      <h3 class="text-xs font-bold text-slate-900 dark:text-white">ذكاء النشاط (7 أيام)</h3>
      <p class="mt-0.5 text-[10px] text-slate-600 dark:text-slate-400">
        متوسط درجة النشاط عبر الشركات:
        <span class="font-mono font-bold text-violet-700 dark:text-violet-400">{{ avgLabel }}</span>
      </p>
    </div>
    <div class="grid gap-0 md:grid-cols-2 md:divide-x md:divide-slate-100 dark:md:divide-slate-800">
      <div class="p-3">
        <p class="mb-2 text-[10px] font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-400">أكثر الشركات نشاطاً</p>
        <ul v-if="most.length" class="space-y-1.5">
          <li
            v-for="row in most"
            :key="'m-' + row.company_id"
            class="flex flex-wrap items-baseline justify-between gap-2 rounded-lg border border-emerald-100/80 bg-emerald-50/40 px-2 py-1.5 text-[11px] dark:border-emerald-900/40 dark:bg-emerald-950/20"
          >
            <span class="font-semibold text-slate-900 dark:text-white">{{ row.company_name }}</span>
            <span class="font-mono text-emerald-800 dark:text-emerald-300" dir="ltr">{{ row.activity_score }}</span>
            <span class="w-full text-[10px] text-slate-600 dark:text-slate-400">آخر نشاط: {{ daysLabel(row.last_activity_days_ago) }}</span>
          </li>
        </ul>
        <p v-else class="text-[11px] text-slate-500 dark:text-slate-400">لا بيانات.</p>
      </div>
      <div class="p-3">
        <p class="mb-2 text-[10px] font-bold uppercase tracking-wide text-amber-800 dark:text-amber-400">أقل الشركات نشاطاً</p>
        <ul v-if="least.length" class="space-y-1.5">
          <li
            v-for="row in least"
            :key="'l-' + row.company_id"
            class="flex flex-wrap items-baseline justify-between gap-2 rounded-lg border border-amber-100/80 bg-amber-50/35 px-2 py-1.5 text-[11px] dark:border-amber-900/40 dark:bg-amber-950/20"
          >
            <span class="font-semibold text-slate-900 dark:text-white">{{ row.company_name }}</span>
            <span class="font-mono text-amber-900 dark:text-amber-200" dir="ltr">{{ row.activity_score }}</span>
            <span class="w-full text-[10px] text-slate-600 dark:text-slate-400">آخر نشاط: {{ daysLabel(row.last_activity_days_ago) }}</span>
          </li>
        </ul>
        <p v-else class="text-[11px] text-slate-500 dark:text-slate-400">لا بيانات.</p>
      </div>
    </div>
    <div class="border-t border-slate-100 px-3 py-2 text-[10px] text-slate-500 dark:border-slate-800 dark:text-slate-400">
      <RouterLink to="/admin#admin-section-tenants" class="font-semibold text-violet-700 underline dark:text-violet-400">عرض جدول المشتركين</RouterLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { PlatformAdminOverviewPayload } from '@/types/platformAdminOverview'

const props = defineProps<{
  activity: PlatformAdminOverviewPayload['activity']
}>()

const most = computed(() => props.activity.most_active_companies ?? [])
const least = computed(() => props.activity.least_active_companies ?? [])
const avgLabel = computed(() => {
  const n = props.activity.avg_activity_score
  return typeof n === 'number' && !Number.isNaN(n) ? n.toLocaleString('ar-SA', { maximumFractionDigits: 2 }) : '—'
})

function daysLabel(days: number): string {
  if (days >= 999) return 'لا يوجد'
  return `${days.toLocaleString('ar-SA')} يوماً`
}
</script>
