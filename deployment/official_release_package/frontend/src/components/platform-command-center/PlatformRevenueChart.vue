<template>
  <section class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">الإيرادات عبر الزمن</h3>
    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">قراءة تنفيذية مبسطة للفترة الحالية.</p>
    <div class="mt-3 h-[220px]">
      <Line v-if="!empty" :data="chartData" :options="chartOptions" />
      <div v-else class="flex h-full items-center justify-center rounded-xl border border-dashed border-slate-300 text-xs text-slate-500 dark:border-slate-700 dark:text-slate-400">
        لا توجد بيانات كافية لعرض الرسم حاليًا.
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Line } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Tooltip,
  Legend,
  Filler,
} from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Legend, Filler)

const props = withDefaults(defineProps<{ points: Array<{ label: string; height: number }>; empty?: boolean }>(), {
  empty: false,
})

const chartData = computed(() => ({
  labels: props.points.map((p) => p.label),
  datasets: [
    {
      label: 'مؤشر الإيراد',
      data: props.points.map((p) => p.height),
      borderColor: 'rgb(124,58,237)',
      backgroundColor: 'rgba(124,58,237,0.15)',
      fill: true,
      tension: 0.35,
      pointRadius: 3,
    },
  ],
}))

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
  },
  scales: {
    x: { grid: { display: false } },
    y: { beginAtZero: true, max: 100, ticks: { callback: (v: any) => `${v}%` } },
  },
}
</script>
