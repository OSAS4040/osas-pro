<template>
  <div class="mt-4 grid gap-3 lg:grid-cols-2">
    <div class="rounded-xl border border-slate-200/90 bg-white/80 p-3 dark:border-slate-700 dark:bg-slate-900/60">
      <h3 class="text-xs font-bold text-slate-900 dark:text-white">Companies Growth (30d)</h3>
      <div class="mt-2 max-h-40 overflow-y-auto font-mono text-[10px]" dir="ltr">
        <div v-for="row in trends.companies_growth" :key="row.date" class="flex justify-between border-b border-slate-100 py-0.5 dark:border-slate-800">
          <span>{{ row.date }}</span>
          <span class="font-bold">{{ row.count ?? 0 }}</span>
        </div>
      </div>
    </div>
    <div class="rounded-xl border border-slate-200/90 bg-white/80 p-3 dark:border-slate-700 dark:bg-slate-900/60">
      <h3 class="text-xs font-bold text-slate-900 dark:text-white">Activity Trend (score)</h3>
      <div class="mt-2 max-h-40 overflow-y-auto font-mono text-[10px]" dir="ltr">
        <div v-for="row in trends.activity_trend" :key="`a-${row.date}`" class="flex justify-between border-b border-slate-100 py-0.5 dark:border-slate-800">
          <span>{{ row.date }}</span>
          <span class="font-bold">{{ row.activity_score ?? 0 }}</span>
        </div>
      </div>
    </div>
    <div class="rounded-xl border border-slate-200/90 bg-white/80 p-3 dark:border-slate-700 dark:bg-slate-900/60 lg:col-span-2">
      <h3 class="text-xs font-bold text-slate-900 dark:text-white">Distribution</h3>
      <div class="mt-2 grid gap-2 sm:grid-cols-2">
        <div>
          <p class="mb-1 text-[11px] text-slate-500">By Plan</p>
          <div v-for="(v, k) in distribution.by_plan" :key="`p-${k}`" class="flex justify-between text-[11px]"><span>{{ k }}</span><span class="font-bold">{{ v }}</span></div>
        </div>
        <div>
          <p class="mb-1 text-[11px] text-slate-500">By Status</p>
          <div v-for="(v, k) in distribution.by_status" :key="`s-${k}`" class="flex justify-between text-[11px]"><span>{{ k }}</span><span class="font-bold">{{ v }}</span></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { PlatformAdminOverviewPayload } from '@/types/platformAdminOverview'

defineProps<{
  trends: PlatformAdminOverviewPayload['trends']
  distribution: PlatformAdminOverviewPayload['distribution']
}>()
</script>
