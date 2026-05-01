<template>
  <section class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">توزيع الشركات حسب الباقة</h3>
    <div class="mt-3 h-[220px]">
      <Doughnut v-if="!empty" :data="chartData" :options="chartOptions" />
      <div v-else class="flex h-full items-center justify-center rounded-xl border border-dashed border-slate-300 text-xs text-slate-500 dark:border-slate-700 dark:text-slate-400">
        لا توجد بيانات كافية لعرض التوزيع.
      </div>
    </div>
    <ul class="mt-3 space-y-2">
      <li v-for="row in rows" :key="row.label" class="space-y-1">
        <div class="flex items-center justify-between text-xs">
          <span class="text-slate-700 dark:text-slate-200">{{ row.label }}</span>
          <span class="font-bold text-slate-900 dark:text-slate-100">{{ row.count }}</span>
        </div>
        <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-800">
          <div class="h-2 rounded-full bg-emerald-500" :style="{ width: `${row.percent}%` }" />
        </div>
      </li>
      <li v-if="rows.length === 0" class="text-xs text-slate-500">لا بيانات متاحة.</li>
    </ul>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Doughnut } from 'vue-chartjs'
import { ArcElement, Chart as ChartJS, Legend, Tooltip } from 'chart.js'

ChartJS.register(ArcElement, Tooltip, Legend)

const props = withDefaults(defineProps<{ rows: Array<{ label: string; count: number; percent: number }>; empty?: boolean }>(), {
  empty: false,
})

const chartData = computed(() => ({
  labels: props.rows.map((r) => r.label),
  datasets: [
    {
      data: props.rows.map((r) => r.count),
      backgroundColor: ['#7c3aed', '#10b981', '#f59e0b', '#ef4444', '#3b82f6', '#334155'],
      borderWidth: 0,
    },
  ],
}))

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { position: 'bottom' as const } },
}
</script>
