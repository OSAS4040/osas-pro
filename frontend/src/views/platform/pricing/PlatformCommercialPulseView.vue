<template>
  <div class="mx-auto max-w-[1200px] space-y-6 pb-12" dir="rtl">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">تقارير المنصة — نبض تشغيلي</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
          ملخص من مسار التقارير المعتمد للمشغّلين (صلاحية التقارير).
        </p>
        <p v-if="periodLabel" class="mt-2 text-xs font-medium text-slate-600 dark:text-slate-300">
          الفترة:
          <span class="font-mono text-[11px] text-primary-700 dark:text-primary-400" dir="ltr">{{ periodLabel }}</span>
          <span v-if="generatedLabel" class="mr-3 text-slate-500">
            · أُنشئ في
            <span class="font-mono text-[11px]" dir="ltr">{{ generatedLabel }}</span>
          </span>
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <button
          type="button"
          class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
          :disabled="loading || !auth.hasPermission('platform.reporting.read')"
          @click="load"
        >
          {{ loading ? 'جارٍ التحديث…' : 'تحديث' }}
        </button>
        <RouterLink :to="{ name: 'platform-overview' }" class="text-sm font-semibold text-primary-700 hover:underline dark:text-primary-400">
          ← الملخص
        </RouterLink>
      </div>
    </div>

    <div v-if="!auth.hasPermission('platform.reporting.read')" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
      لا تملك صلاحية قراءة تقارير المنصة.
    </div>

    <template v-else>
      <div v-if="loading" class="rounded-xl border border-slate-200 bg-white p-10 text-center text-slate-500 dark:border-slate-700 dark:bg-slate-900/40">
        جارٍ التحميل…
      </div>
      <div v-else-if="errorMsg" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-100">
        {{ errorMsg }}
      </div>

      <template v-else-if="envelope">
        <section v-if="exportHint" class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600 dark:border-slate-600 dark:bg-slate-800/50 dark:text-slate-300">
          {{ exportHint }}
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
          <h2 class="mb-3 text-sm font-bold text-slate-800 dark:text-slate-100">ملخص المنصة</h2>
          <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <div
              v-for="item in summaryCards"
              :key="item.key"
              class="rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-3 dark:border-slate-600 dark:bg-slate-800/60"
            >
              <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">{{ item.label }}</p>
              <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900 dark:text-white" dir="ltr">
                {{ fmt(item.value) }}
              </p>
            </div>
          </div>
        </section>

        <section
          v-if="activityRow.length"
          class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/40"
        >
          <h2 class="mb-3 text-sm font-bold text-slate-800 dark:text-slate-100">نشاط داخل الفترة</h2>
          <div class="grid gap-3 sm:grid-cols-2">
            <div
              v-for="row in activityRow"
              :key="row.key"
              class="rounded-xl border border-slate-100 bg-emerald-50/60 px-3 py-3 dark:border-emerald-900/40 dark:bg-emerald-950/20"
            >
              <p class="text-[11px] font-semibold text-emerald-900 dark:text-emerald-200">{{ row.label }}</p>
              <p class="mt-1 text-xl font-bold tabular-nums text-emerald-950 dark:text-emerald-50" dir="ltr">{{ fmt(row.value) }}</p>
            </div>
          </div>
        </section>

        <div class="grid gap-4 lg:grid-cols-3">
          <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
            <h3 class="mb-2 text-sm font-bold text-slate-800 dark:text-slate-100">الشركات حسب الحالة</h3>
            <p v-if="!statusCompanies.length" class="text-xs text-slate-500 dark:text-slate-400">لا بيانات حالة للشركات.</p>
            <div v-else class="overflow-x-auto rounded-lg border border-slate-100 dark:border-slate-600">
              <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/80">
                  <tr>
                    <th class="px-3 py-2 text-right font-semibold">الحالة</th>
                    <th class="px-3 py-2 text-end font-semibold tabular-nums">العدد</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                  <tr v-for="r in statusCompanies" :key="'c-' + r.status">
                    <td class="px-3 py-2 font-mono text-xs" dir="ltr">{{ r.status || '—' }}</td>
                    <td class="px-3 py-2 text-end tabular-nums" dir="ltr">{{ fmt(r.count) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

          <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
            <h3 class="mb-2 text-sm font-bold text-slate-800 dark:text-slate-100">الاشتراكات حسب الحالة</h3>
            <p v-if="!statusSubscriptions.length" class="text-xs text-slate-500 dark:text-slate-400">لا بيانات اشتراكات.</p>
            <div v-else class="overflow-x-auto rounded-lg border border-slate-100 dark:border-slate-600">
              <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/80">
                  <tr>
                    <th class="px-3 py-2 text-right font-semibold">الحالة</th>
                    <th class="px-3 py-2 text-end font-semibold tabular-nums">العدد</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                  <tr v-for="r in statusSubscriptions" :key="'s-' + r.status">
                    <td class="px-3 py-2 font-mono text-xs" dir="ltr">{{ r.status || '—' }}</td>
                    <td class="px-3 py-2 text-end tabular-nums" dir="ltr">{{ fmt(r.count) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

          <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
            <h3 class="mb-2 text-sm font-bold text-slate-800 dark:text-slate-100">تذاكر الدعم حسب الحالة</h3>
            <p v-if="!statusTickets.length" class="text-xs text-slate-500 dark:text-slate-400">لا تذاكر أو الجدول غير متوفر.</p>
            <div v-else class="overflow-x-auto rounded-lg border border-slate-100 dark:border-slate-600">
              <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/80">
                  <tr>
                    <th class="px-3 py-2 text-right font-semibold">الحالة</th>
                    <th class="px-3 py-2 text-end font-semibold tabular-nums">العدد</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                  <tr v-for="r in statusTickets" :key="'t-' + r.status">
                    <td class="px-3 py-2 font-mono text-xs" dir="ltr">{{ r.status || '—' }}</td>
                    <td class="px-3 py-2 text-end tabular-nums" dir="ltr">{{ fmt(r.count) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </div>

        <div v-if="weeklySectionVisible" class="grid gap-4 lg:grid-cols-2">
          <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
            <h3 class="mb-2 text-sm font-bold text-slate-800 dark:text-slate-100">أوامر العمل — أسبوعياً</h3>
            <p v-if="!weeklyWorkOrders.length" class="text-xs text-slate-500">لا بيانات في الفترة.</p>
            <div v-else class="max-h-64 overflow-auto rounded-lg border border-slate-100 dark:border-slate-600">
              <table class="w-full text-xs">
                <thead class="sticky top-0 bg-slate-50 dark:bg-slate-800">
                  <tr>
                    <th class="px-2 py-2 text-right">بداية الأسبوع</th>
                    <th class="px-2 py-2 text-end tabular-nums">العدد</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                  <tr v-for="(r, i) in weeklyWorkOrders" :key="'wo-' + i">
                    <td class="px-2 py-1.5 font-mono text-[11px]" dir="ltr">{{ formatBucketDate(r.period_start) }}</td>
                    <td class="px-2 py-1.5 text-end tabular-nums" dir="ltr">{{ fmt(r.count) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

          <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
            <h3 class="mb-2 text-sm font-bold text-slate-800 dark:text-slate-100">تسجيل شركات — أسبوعياً</h3>
            <p v-if="!weeklyCompanies.length" class="text-xs text-slate-500">لا بيانات في الفترة.</p>
            <div v-else class="max-h-64 overflow-auto rounded-lg border border-slate-100 dark:border-slate-600">
              <table class="w-full text-xs">
                <thead class="sticky top-0 bg-slate-50 dark:bg-slate-800">
                  <tr>
                    <th class="px-2 py-2 text-right">بداية الأسبوع</th>
                    <th class="px-2 py-2 text-end tabular-nums">العدد</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                  <tr v-for="(r, i) in weeklyCompanies" :key="'co-' + i">
                    <td class="px-2 py-1.5 font-mono text-[11px]" dir="ltr">{{ formatBucketDate(r.period_start) }}</td>
                    <td class="px-2 py-1.5 text-end tabular-nums" dir="ltr">{{ fmt(r.count) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </div>

        <details class="rounded-xl border border-dashed border-slate-300 bg-slate-50/50 dark:border-slate-600 dark:bg-slate-900/30">
          <summary class="cursor-pointer select-none px-4 py-3 text-sm font-semibold text-slate-600 dark:text-slate-300">
            عرض JSON الخام (للمطورين)
          </summary>
          <pre
            class="overflow-x-auto border-t border-slate-200 p-4 text-[11px] leading-relaxed dark:border-slate-600 dark:text-slate-200"
            dir="ltr"
          >{{ JSON.stringify(envelope, null, 2) }}</pre>
        </details>
      </template>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const loading = ref(false)
const envelope = ref<Record<string, unknown> | null>(null)
const errorMsg = ref<string | null>(null)

const SUMMARY_LABELS: Record<string, string> = {
  companies_total: 'إجمالي الشركات',
  companies_operational: 'شركات نشطة تشغيلياً',
  companies_suspended: 'شركات موقوفة',
  companies_other: 'شركات (حالات أخرى)',
  users_total: 'المستخدمون',
  customers_total: 'العملاء',
  branches_total: 'الفروع',
  subscriptions_total: 'الاشتراكات',
  tickets_open: 'تذاكر مفتوحة',
  tickets_overdue: 'تذاكر متأخرة',
  work_orders_in_period: 'أوامر عمل في الفترة',
}

const SUMMARY_ORDER = [
  'companies_total',
  'companies_operational',
  'companies_suspended',
  'companies_other',
  'users_total',
  'customers_total',
  'branches_total',
  'subscriptions_total',
  'tickets_open',
  'tickets_overdue',
  'work_orders_in_period',
]

function fmt(n: number): string {
  return Number(n).toLocaleString('ar-SA')
}

function asRecord(v: unknown): Record<string, unknown> | null {
  return v !== null && typeof v === 'object' && !Array.isArray(v) ? (v as Record<string, unknown>) : null
}

const report = computed(() => asRecord(envelope.value?.report))
const pulseData = computed(() => asRecord(envelope.value?.data))
const summary = computed(() => asRecord(pulseData.value?.summary))

const periodLabel = computed(() => {
  const p = asRecord(report.value?.period)
  if (!p?.from || !p?.to) return ''
  return `${String(p.from)} → ${String(p.to)}`
})

const generatedLabel = computed(() => {
  const g = report.value?.generated_at
  return typeof g === 'string' ? g : ''
})

const exportHint = computed(() => {
  const ex = asRecord(report.value?.export)
  if (!ex) return ''
  const supported = ex.supported === true
  const rawFormats = ex.formats ?? ex.formats_supported
  const formats = Array.isArray(rawFormats) ? rawFormats.map(String).join(', ') : ''
  if (supported && formats) return `التصدير متاح بالصيغ: ${formats}.`
  return 'التصدير غير مفعّل لهذا التقرير حالياً — يمكن ربطه لاحقاً من مسار التصدير المعتمد.'
})

const summaryCards = computed(() => {
  const s = summary.value
  if (!s) return []
  const out: { key: string; label: string; value: number }[] = []
  for (const key of SUMMARY_ORDER) {
    const raw = s[key]
    const n = typeof raw === 'number' ? raw : Number(raw)
    if (!Number.isFinite(n)) continue
    out.push({ key, label: SUMMARY_LABELS[key] ?? key, value: n })
  }
  return out
})

const breakdown = computed(() => asRecord(pulseData.value?.breakdown))
const byStatus = computed(() => asRecord(breakdown.value?.by_status))

function statusRows(key: string): { status: string; count: number }[] {
  const v = byStatus.value?.[key]
  if (!Array.isArray(v)) return []
  return v
    .map((row) => {
      const o = asRecord(row)
      if (!o) return null
      const st = o.status != null ? String(o.status) : ''
      const c = typeof o.count === 'number' ? o.count : Number(o.count)
      if (!Number.isFinite(c)) return null
      return { status: st, count: c }
    })
    .filter((x): x is { status: string; count: number } => x !== null)
}

const statusCompanies = computed(() => statusRows('companies'))
const statusSubscriptions = computed(() => statusRows('subscriptions'))
const statusTickets = computed(() => statusRows('support_tickets'))

const byActivity = computed(() => asRecord(breakdown.value?.by_activity))

const activityRow = computed(() => {
  const a = byActivity.value
  if (!a) return []
  const rows: { key: string; label: string; value: number }[] = []
  const reg = a.companies_registered_in_period
  const wo = a.work_orders_created_in_period
  if (typeof reg === 'number' && Number.isFinite(reg)) {
    rows.push({ key: 'reg', label: 'شركات سُجّلت في الفترة', value: reg })
  }
  if (typeof wo === 'number' && Number.isFinite(wo)) {
    rows.push({ key: 'wo', label: 'أوامر عمل أُنشئت في الفترة', value: wo })
  }
  return rows
})

const byTime = computed(() => asRecord(breakdown.value?.by_time_period))

const weeklyWorkOrders = computed(() => parseWeekly(byTime.value?.work_orders))
const weeklyCompanies = computed(() => parseWeekly(byTime.value?.companies))

const weeklySectionVisible = computed(() => weeklyWorkOrders.value.length > 0 || weeklyCompanies.value.length > 0)

function parseWeekly(v: unknown): { period_start: string; count: number }[] {
  if (!Array.isArray(v)) return []
  return v
    .map((row) => {
      const o = asRecord(row)
      if (!o) return null
      const ps = o.period_start != null ? String(o.period_start) : ''
      const c = typeof o.count === 'number' ? o.count : Number(o.count)
      if (!Number.isFinite(c)) return null
      return { period_start: ps, count: c }
    })
    .filter((x): x is { period_start: string; count: number } => x !== null)
}

function formatBucketDate(iso: string): string {
  if (!iso) return '—'
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return iso
  return d.toLocaleDateString('ar-SA', { year: 'numeric', month: 'short', day: 'numeric' })
}

async function load(): Promise<void> {
  if (!auth.hasPermission('platform.reporting.read')) return
  loading.value = true
  errorMsg.value = null
  envelope.value = null
  try {
    const end = new Date()
    const start = new Date()
    start.setDate(start.getDate() - 30)
    const from = start.toISOString().slice(0, 10)
    const to = end.toISOString().slice(0, 10)
    const { data } = await apiClient.get<Record<string, unknown>>('/reporting/v1/platform/pulse-summary', {
      params: { from, to },
    })
    envelope.value = data ?? null
  } catch {
    errorMsg.value = 'تعذّر تحميل التقرير — تحقق من الصلاحيات أو الشبكة.'
  } finally {
    loading.value = false
  }
}

onMounted(() => void load())
</script>
