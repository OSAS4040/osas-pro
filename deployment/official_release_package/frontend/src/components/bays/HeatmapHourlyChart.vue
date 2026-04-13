<template>
  <div class="rounded-2xl border border-slate-200/80 dark:border-slate-700 bg-white/90 dark:bg-slate-800/90 p-4 h-[240px] flex flex-col">
    <div class="flex items-center justify-between gap-2 mb-2">
      <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100 flex items-center gap-2">
        <ChartBarIcon class="w-4 h-4 text-orange-500" />
        طلب الإشغال بالساعة (مجموع الحجوزات المتقاطعة)
      </h3>
      <span class="text-[11px] text-slate-500 dark:text-slate-400">يُحسب من شبكة اليوم</span>
    </div>
    <div class="flex-1 min-h-0">
      <Bar v-if="barData" :key="'hm-bar-' + (isDark ? '1' : '0')" :data="barData" :options="opts" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Bar } from 'vue-chartjs'
import { ChartBarIcon } from '@heroicons/vue/24/outline'
import { useDarkMode } from '@/composables/useDarkMode'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend,
} from 'chart.js'
import type { TooltipItem } from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend)

const props = defineProps<{
  hours: number[]
  /** مجموع عدد الحجوزات النشطة في كل ساعة عبر كل المناطق */
  totals: number[]
}>()

const { isDark } = useDarkMode()

const barData = computed(() => ({
  labels: props.hours.map((h) => `${h}:00`),
  datasets: [
    {
      label: 'حجوزات',
      data: props.totals,
      backgroundColor: props.totals.map((t) =>
        t === 0
          ? isDark.value
            ? 'rgba(71, 85, 105, 0.45)'
            : 'rgba(148, 163, 184, 0.35)'
          : t < 3
            ? 'rgba(52, 211, 153, 0.75)'
            : t < 6
              ? 'rgba(251, 191, 36, 0.85)'
              : 'rgba(248, 113, 113, 0.9)',
      ),
      borderRadius: 6,
      borderSkipped: false,
    },
  ],
}))

const opts = computed(() => {
  const tc = isDark.value ? '#94a3b8' : '#64748b'
  const gy = isDark.value ? 'rgba(148,163,184,0.14)' : 'rgba(100,116,139,0.1)'
  return {
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 400, easing: 'easeOutQuart' as const },
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: {
          label(tooltipItem: TooltipItem<'bar'>) {
            const y = tooltipItem.parsed.y ?? 0
            return `${y} حجز متقاطع`
          },
        },
      },
    },
    scales: {
      x: { grid: { display: false }, ticks: { font: { size: 9 }, color: tc } },
      y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 10 }, color: tc }, grid: { color: gy } },
    },
  }
})
</script>
