<template>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4 h-[280px] flex flex-col">
      <h3 class="text-sm font-semibold text-gray-800 dark:text-slate-100 mb-1">إيراد الفواتير — آخر 7 أيام</h3>
      <p class="text-xs text-gray-500 dark:text-slate-400 mb-2">مجموع إجمالي الفواتير الصادرة يومياً</p>
      <div class="flex-1 min-h-0">
        <Line v-if="revenueData" :key="'rev-' + (isDark ? '1' : '0')" :data="revenueData" :options="chartOptions" />
      </div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4 h-[280px] flex flex-col">
      <h3 class="text-sm font-semibold text-gray-800 dark:text-slate-100 mb-1">أوامر عمل جديدة — آخر 7 أيام</h3>
      <p class="text-xs text-gray-500 dark:text-slate-400 mb-2">عدد الأوامر المُنشأة يومياً</p>
      <div class="flex-1 min-h-0">
        <Line v-if="woData" :key="'wo-' + (isDark ? '1' : '0')" :data="woData" :options="chartOptionsWo" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Line } from 'vue-chartjs'
import { useDarkMode } from '@/composables/useDarkMode'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler,
} from 'chart.js'
import type { TooltipItem } from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler)

const props = defineProps<{
  revenue: { date: string; revenue: number }[]
  workOrders: { date: string; count: number }[]
}>()

const labels = computed(() =>
  (props.revenue?.length ? props.revenue : props.workOrders).map((r) => r.date.slice(5).replace('-', '/')),
)

const revenueData = computed(() => ({
  labels: labels.value,
  datasets: [
    {
      label: 'ر.س',
      data: props.revenue.map((r) => r.revenue),
      borderColor: 'rgb(99, 102, 241)',
      backgroundColor: 'rgba(99, 102, 241, 0.12)',
      fill: true,
      tension: 0.35,
      pointRadius: 3,
      pointHoverRadius: 5,
    },
  ],
}))

const woData = computed(() => ({
  labels: labels.value,
  datasets: [
    {
      label: 'عدد الأوامر',
      data: props.workOrders.map((r) => r.count),
      borderColor: 'rgb(16, 185, 129)',
      backgroundColor: 'rgba(16, 185, 129, 0.08)',
      fill: true,
      tension: 0.35,
      pointRadius: 3,
      pointHoverRadius: 5,
    },
  ],
}))

const { isDark } = useDarkMode()
const tickColor = computed(() => (isDark.value ? '#94a3b8' : '#64748b'))
const gridY = computed(() => (isDark.value ? 'rgba(148,163,184,0.14)' : 'rgba(100,116,139,0.1)'))

const chartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  animation: { duration: 450, easing: 'easeOutQuart' as const },
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: {
        label(tooltipItem: TooltipItem<'line'>) {
          const y = tooltipItem.parsed.y ?? 0
          return `${Number(y).toLocaleString('ar-SA')} ر.س`
        },
      },
    },
  },
  scales: {
    x: {
      grid: { display: false },
      ticks: { maxRotation: 0, font: { size: 10 }, color: tickColor.value },
    },
    y: {
      beginAtZero: true,
      ticks: { font: { size: 10 }, color: tickColor.value },
      grid: { color: gridY.value },
    },
  },
}))

const chartOptionsWo = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  animation: { duration: 450, easing: 'easeOutQuart' as const },
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: {
        label(tooltipItem: TooltipItem<'line'>) {
          const y = tooltipItem.parsed.y ?? 0
          return `${y} أمر`
        },
      },
    },
  },
  scales: {
    x: {
      grid: { display: false },
      ticks: { maxRotation: 0, font: { size: 10 }, color: tickColor.value },
    },
    y: {
      beginAtZero: true,
      ticks: { stepSize: 1, font: { size: 10 }, color: tickColor.value },
      grid: { color: gridY.value },
    },
  },
}))
</script>
