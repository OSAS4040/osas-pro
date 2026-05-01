<template>
  <header class="rounded-3xl border border-slate-200/80 dark:border-slate-700/70 bg-gradient-to-br from-white via-slate-50/80 to-primary-50/30 dark:from-slate-900 dark:via-slate-900 dark:to-primary-950/20 px-6 py-5 shadow-sm">
    <nav class="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-3">
      <RouterLink to="/customers" class="hover:text-primary-600 dark:hover:text-primary-400">{{ breadcrumbParent }}</RouterLink>
      <span aria-hidden="true">/</span>
      <span class="text-slate-700 dark:text-slate-200 font-medium">{{ breadcrumbCurrent }}</span>
    </nav>
    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
      <div class="space-y-2 min-w-0">
        <p class="text-xs font-semibold uppercase tracking-wide text-primary-700/90 dark:text-primary-300/90">
          {{ pulseLabel }}
        </p>
        <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-slate-50 truncate">
          {{ customerName }}
        </h1>
        <div class="flex flex-wrap gap-2 text-sm text-slate-600 dark:text-slate-300">
          <span class="inline-flex items-center rounded-full bg-white/80 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-600 px-2.5 py-0.5 text-xs font-medium">
            {{ typeLabel }}
          </span>
          <span v-if="branchLabel" class="inline-flex items-center rounded-full bg-white/80 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-600 px-2.5 py-0.5 text-xs">
            {{ branchLabel }}
          </span>
          <span
            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
            :class="active ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
          >
            {{ active ? activeLabel : inactiveLabel }}
          </span>
        </div>
        <p class="text-sm text-slate-600 dark:text-slate-400">
          {{ lastActivityText }}
        </p>
        <p class="text-sm font-medium text-slate-700 dark:text-slate-200">
          {{ periodText }}
        </p>
        <div class="flex flex-wrap gap-1.5 pt-1">
          <span
            v-for="b in badges"
            :key="b.key"
            class="inline-flex items-center rounded-lg px-2 py-0.5 text-[11px] font-medium border"
            :class="b.class"
          >{{ b.text }}</span>
        </div>
      </div>
      <div class="flex flex-col sm:flex-row flex-wrap gap-2 shrink-0">
        <slot name="actions" />
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  customerName: string
  customerType: string
  branchName?: string | null
  isActive: boolean
  lastActivityAt: string | null
  periodFrom: string
  periodTo: string
  badges: { key: string; text: string; class: string }[]
  ar: boolean
}>()

const pulseLabel = computed(() => (props.ar ? 'نبض العميل' : 'Customer pulse'))
const breadcrumbParent = computed(() => (props.ar ? 'العملاء' : 'Customers'))
const breadcrumbCurrent = computed(() => (props.ar ? 'لوحة الفهم' : 'Insight board'))
const typeLabel = computed(() => {
  if (props.customerType === 'b2b') return props.ar ? 'شركة (B2B)' : 'Company (B2B)'
  return props.ar ? 'فرد (B2C)' : 'Individual (B2C)'
})
const branchLabel = computed(() => {
  if (!props.branchName) return ''
  return props.ar ? `الفرع: ${props.branchName}` : `Branch: ${props.branchName}`
})
const activeLabel = computed(() => (props.ar ? 'نشط' : 'Active'))
const inactiveLabel = computed(() => (props.ar ? 'غير نشط' : 'Inactive'))
const active = computed(() => props.isActive)

const lastActivityText = computed(() => {
  if (!props.lastActivityAt) return props.ar ? 'آخر نشاط: لا يوجد في النطاق' : 'Last activity: none in scope'
  const d = new Date(props.lastActivityAt)
  const rel = formatRelative(d, props.ar)
  return props.ar ? `آخر نشاط: ${rel}` : `Last activity: ${rel}`
})

const periodText = computed(() =>
  props.ar
    ? `الفترة: ${props.periodFrom} → ${props.periodTo}`
    : `Period: ${props.periodFrom} → ${props.periodTo}`,
)

function formatRelative(d: Date, ar: boolean): string {
  const diff = Date.now() - d.getTime()
  const days = Math.floor(diff / 86400000)
  if (days < 0) return ar ? 'في المستقبل' : 'in the future'
  if (days === 0) return ar ? 'اليوم' : 'today'
  if (days === 1) return ar ? 'أمس' : 'yesterday'
  if (days < 14) return ar ? `قبل ${days} يوماً` : `${days} days ago`
  return d.toLocaleDateString(ar ? 'ar-SA' : 'en-GB')
}
</script>
