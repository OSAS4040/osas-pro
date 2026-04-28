<template>
  <section class="rounded-2xl border border-slate-200/80 dark:border-slate-700/70 bg-white dark:bg-slate-900/40 p-4 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
      <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ heading }}</h2>
      <div v-if="financial" class="flex rounded-lg border border-slate-200 dark:border-slate-600 p-0.5 text-xs">
        <button
          type="button"
          class="px-2.5 py-1 rounded-md transition-colors"
          :class="tab === 'ops' ? 'bg-primary-600 text-white' : 'text-slate-600 dark:text-slate-300'"
          @click="tab = 'ops'"
        >
          {{ tabOps }}
        </button>
        <button
          type="button"
          class="px-2.5 py-1 rounded-md transition-colors"
          :class="tab === 'fin' ? 'bg-primary-600 text-white' : 'text-slate-600 dark:text-slate-300'"
          @click="tab = 'fin'"
        >
          {{ tabFin }}
        </button>
      </div>
    </div>
    <p class="text-xs text-slate-500 dark:text-slate-400 mb-2">{{ caption }}</p>
    <div class="h-[240px] min-h-[220px]">
      <Line v-if="chartData" :key="chartKey" :data="chartData" :options="chartOptions" />
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Line } from 'vue-chartjs'
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
import type { WeeklyBucketRow } from '@/types/customerPulseReport'
import { useDarkMode } from '@/composables/useDarkMode'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler)

const props = defineProps<{
  workOrders: WeeklyBucketRow[]
  invoices: WeeklyBucketRow[]
  financial: boolean
  heading: string
  tabOps: string
  tabFin: string
  caption: string
  ar: boolean
}>()

const tab = ref<'ops' | 'fin'>('ops')

const activeSeries = computed(() => {
  if (props.financial && tab.value === 'fin') return props.invoices
  return props.workOrders
})

const labels = computed(() =>
  activeSeries.value.map((r) => {
    const raw = r.period_start || ''
    try {
      return new Date(raw).toLocaleDateString(props.ar ? 'ar-SA' : 'en-GB', { month: 'short', day: 'numeric' })
    } catch {
      return raw.slice(5, 10)
    }
  }),
)

const chartData = computed(() => ({
  labels: labels.value,
  datasets: [
    {
      label: props.financial && tab.value === 'fin' ? (props.ar ? 'فواتير' : 'Invoices') : props.ar ? 'أوامر عمل' : 'Work orders',
      data: activeSeries.value.map((r) => r.count),
      borderColor: props.financial && tab.value === 'fin' ? 'rgb(99, 102, 241)' : 'rgb(16, 185, 129)',
      backgroundColor: props.financial && tab.value === 'fin' ? 'rgba(99, 102, 241, 0.12)' : 'rgba(16, 185, 129, 0.1)',
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
  animation: { duration: 400, easing: 'easeOutQuart' as const },
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: {
        label(tooltipItem: TooltipItem<'line'>) {
          const y = tooltipItem.parsed.y ?? 0
          return `${y}`
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

const chartKey = computed(() => `${tab.value}-${isDark.value ? 'd' : 'l'}-${activeSeries.value.length}`)
</script>
