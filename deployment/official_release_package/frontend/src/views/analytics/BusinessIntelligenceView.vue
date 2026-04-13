<template>
  <div class="space-y-8 max-w-[1600px] mx-auto pb-12" dir="rtl">
    <!-- رأس -->
    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 text-primary-600 dark:text-primary-400 text-xs font-semibold uppercase tracking-wide">
          <PresentationChartLineIcon class="w-4 h-4" />
          تحليلات متقدمة
        </div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-1">لوحة ذكاء الأعمال</h1>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-1 max-w-2xl leading-relaxed">
          رؤية موحّدة للإيرادات، التحصيل، العمليات، وطرق الدفع — مبنية على بياناتكم الفعلية مع مؤشرات قابلة للمقارنة ومخططات تفاعلية.
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <button
          type="button"
          class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-gray-200 dark:border-slate-600 text-sm text-gray-700 dark:text-slate-200 hover:bg-gray-50 dark:hover:bg-slate-800"
          @click="loadAll"
        >
          <ArrowPathIcon class="w-4 h-4" :class="{ 'animate-spin': loading }" />
          تحديث البيانات
        </button>
        <RouterLink
          to="/reports"
          class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-primary-600 text-white text-sm font-medium hover:bg-primary-700"
        >
          التقارير التفصيلية
          <ChevronLeftIcon class="w-4 h-4 rtl:rotate-180" />
        </RouterLink>
      </div>
    </div>

    <!-- فترة زمنية -->
    <div class="card p-4 space-y-3">
      <p class="text-xs font-medium text-gray-500 dark:text-slate-400">نطاق التحليل</p>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="pr in presets"
          :key="pr.key"
          type="button"
          class="px-3 py-1.5 text-xs rounded-lg border transition-colors"
          :class="preset === pr.key ? 'border-primary-500 bg-primary-50 dark:bg-primary-950/40 text-primary-800 dark:text-primary-200' : 'border-gray-200 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-800'"
          @click="applyPreset(pr.key)"
        >
          {{ pr.label }}
        </button>
      </div>
      <div class="flex flex-wrap gap-3 items-end">
        <div class="min-w-[260px]">
          <label class="block text-xs text-gray-500 mb-1">نطاق التاريخ</label>
          <SmartDatePicker
            mode="range"
            :from-value="from"
            :to-value="to"
            @change="onDateRangeChange"
          />
        </div>
        <button type="button" class="btn btn-primary text-sm" @click="applyRange">تطبيق</button>
      </div>
      <p class="text-[11px] text-gray-400 leading-relaxed">
        الفواتير حسب <strong>تاريخ الإصدار</strong>؛ المدفوعات حسب <strong>تاريخ التسجيل</strong>. لنتائج أدق وسّع الفترة أو طابقها مع تقاريركم المحاسبية.
      </p>
    </div>

    <!-- ميزات المنصة -->
    <section class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
      <div
        v-for="f in featureHighlights"
        :key="f.title"
        class="rounded-xl border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800/50 p-4 flex gap-3"
      >
        <div class="w-10 h-10 rounded-lg bg-primary-100 dark:bg-primary-900/40 flex items-center justify-center shrink-0">
          <component :is="f.icon" class="w-5 h-5 text-primary-600 dark:text-primary-400" />
        </div>
        <div>
          <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ f.title }}</p>
          <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5 leading-snug">{{ f.desc }}</p>
        </div>
      </div>
    </section>

    <!-- تنبيه صلاحية -->
    <div
      v-if="reportsDenied"
      class="rounded-xl border border-amber-200 bg-amber-50 dark:bg-amber-950/30 dark:border-amber-900/50 px-4 py-3 text-sm text-amber-900 dark:text-amber-100"
    >
      بعض واجهات التحليل (KPI، المبيعات، المالية) تتطلب صلاحية عرض التقارير. ما زال بإمكانكم رؤية ملخص لوحة التحكم أدناه.
    </div>

    <!-- KPIs تنفيذية -->
    <section>
      <h2 class="text-sm font-bold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
        <span class="w-1 h-4 bg-primary-500 rounded-full" />
        مؤشرات الأداء الرئيسية
      </h2>
      <div v-if="loading && !summary" class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div v-for="i in 8" :key="i" class="h-24 rounded-xl bg-gray-100 dark:bg-slate-700 animate-pulse" />
      </div>
      <div v-else class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <KpiCard title="إجمالي الإيراد (فواتير)" :value="fmt(salesKpi.total_sales)" icon="💰" color="blue" />
        <KpiCard title="المتحصل نقدياً" :value="fmt(salesKpi.total_collected)" icon="✅" color="green" />
        <KpiCard title="معدل التحصيل" :value="`${collectionRate}٪`" icon="📈" color="teal" />
        <KpiCard title="متوسط قيمة الفاتورة" :value="fmt(salesKpi.avg_invoice_value)" icon="📊" color="indigo" />
        <KpiCard title="الضريبة في الفترة" :value="fmt(salesKpi.total_vat)" icon="🏛" color="purple" />
        <KpiCard title="الذمم المفتوحة" :value="fmt(summary?.receivables?.total_outstanding)" icon="⚠️" color="orange" />
        <KpiCard title="عملاء جدد" :value="String(summary?.customers?.new_in_period ?? 0)" icon="👥" color="green" />
        <KpiCard title="إكمال أوامر العمل" :value="`${summary?.work_orders?.completion_rate ?? 0}٪`" icon="🔧" color="orange" />
      </div>
    </section>

    <!-- تحليل مالي وتشغيلي (API business-analytics) -->
    <section v-if="!reportsDenied" class="space-y-4">
      <h2 class="text-sm font-bold text-gray-800 dark:text-white flex items-center gap-2">
        <span class="w-1 h-4 bg-violet-500 rounded-full" />
        التحليل المالي والتشغيلي
      </h2>
      <p class="text-xs text-gray-500 dark:text-slate-400 -mt-2 max-w-3xl">
        تفصيل حالات الفواتير، الخصومات، التحصيل، المتأخرات، أوامر العمل، الحجوزات، والمشتريات ضمن نفس الفترة المعروضة أعلاه.
      </p>

      <div v-if="loading && !bi" class="h-32 rounded-xl bg-gray-100 dark:bg-slate-700 animate-pulse" />
      <p v-else-if="!loading && !bi" class="text-sm text-amber-700 dark:text-amber-300">
        تعذر تحميل التحليل المالي والتشغيلي. تحققوا من الاتصال أو حاولوا «تحديث البيانات».
      </p>

      <template v-else-if="bi">
        <div class="grid grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-3">
          <div class="rounded-xl border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800/50 p-3">
            <p class="text-[10px] text-gray-500 uppercase tracking-wide">إجمالي الخصومات</p>
            <p class="text-lg font-bold text-gray-900 dark:text-white font-mono">{{ fmt(bi.financial.discount_sum) }}</p>
          </div>
          <div class="rounded-xl border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800/50 p-3">
            <p class="text-[10px] text-gray-500 uppercase tracking-wide">نسبة الخصم</p>
            <p class="text-lg font-bold text-primary-600 dark:text-primary-400 font-mono">{{ bi.financial.discount_ratio_pct }}٪</p>
          </div>
          <div class="rounded-xl border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800/50 p-3">
            <p class="text-[10px] text-gray-500 uppercase tracking-wide">متوسط قيمة الدفعة</p>
            <p class="text-lg font-bold text-gray-900 dark:text-white font-mono">{{ fmt(bi.financial.avg_payment) }}</p>
          </div>
          <div class="rounded-xl border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800/50 p-3">
            <p class="text-[10px] text-gray-500 uppercase tracking-wide">فواتير متأخرة (الآن)</p>
            <p class="text-lg font-bold text-amber-700 dark:text-amber-300 font-mono">{{ bi.financial.overdue_count }}</p>
            <p class="text-[11px] text-gray-500">{{ fmt(bi.financial.overdue_amount) }}</p>
          </div>
          <div class="rounded-xl border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800/50 p-3">
            <p class="text-[10px] text-gray-500 uppercase tracking-wide">مشتريات الفترة</p>
            <p class="text-lg font-bold text-gray-900 dark:text-white font-mono">{{ fmt(bi.operational.purchases.total) }}</p>
            <p class="text-[11px] text-gray-500">{{ bi.operational.purchases.count }} أمر</p>
          </div>
          <div class="rounded-xl border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800/50 p-3">
            <p class="text-[10px] text-gray-500 uppercase tracking-wide">تنبيه مخزون منخفض</p>
            <p class="text-lg font-bold text-rose-700 dark:text-rose-300 font-mono">{{ bi.operational.low_stock_row_count }}</p>
            <p class="text-[11px] text-gray-500">صف بمستودع/فرع</p>
          </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-4">
          <div class="card p-4">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">توزيع الفواتير حسب الحالة (الفترة)</h3>
            <div v-if="invoiceStatusLabels.length" class="h-72">
              <Bar :data="invoiceStatusBarData" :options="invoiceStatusBarOpts" />
            </div>
            <p v-else class="text-sm text-gray-400 py-12 text-center">لا فواتير في الفترة</p>
          </div>
          <div class="card p-4">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">أوامر العمل حسب الحالة (منشأة في الفترة)</h3>
            <div v-if="woStatusLabels.length" class="h-72">
              <Bar :data="woStatusBarData" :options="woStatusBarOpts" />
            </div>
            <p v-else class="text-sm text-gray-400 py-12 text-center">لا أوامر عمل في الفترة</p>
          </div>
        </div>

        <div v-if="bookingStatusLabels.length" class="card p-4">
          <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">الحجوزات حسب الحالة (مُنشأة في الفترة)</h3>
          <div class="h-56 max-w-xl">
            <Bar :data="bookingStatusBarData" :options="bookingStatusBarOpts" />
          </div>
        </div>

        <div class="grid md:grid-cols-2 gap-4 text-sm">
          <div class="rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800/40 p-4">
            <h4 class="font-semibold text-gray-800 dark:text-white mb-2">ملخص مالي سريع</h4>
            <ul class="space-y-1.5 text-gray-600 dark:text-slate-300">
              <li class="flex justify-between gap-2"><span>المجموع قبل الضريبة (مجمع subtotal)</span><span class="font-mono">{{ fmt(bi.financial.subtotal_sum) }}</span></li>
              <li class="flex justify-between gap-2"><span>ضريبة مُحصَّلة في الفواتير</span><span class="font-mono">{{ fmt(bi.financial.tax_sum) }}</span></li>
              <li class="flex justify-between gap-2"><span>عدد المدفوعات المكتملة</span><span class="font-mono">{{ bi.financial.payment_count }}</span></li>
              <li class="flex justify-between gap-2"><span>مجموع المدفوعات</span><span class="font-mono">{{ fmt(bi.financial.payment_total) }}</span></li>
            </ul>
          </div>
          <div class="rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800/40 p-4">
            <h4 class="font-semibold text-gray-800 dark:text-white mb-2">ملخص تشغيلي</h4>
            <ul class="space-y-1.5 text-gray-600 dark:text-slate-300">
              <li class="flex justify-between gap-2"><span>أوامر عمل منشأة في الفترة</span><span class="font-mono">{{ bi.operational.work_orders_created_total }}</span></li>
              <li class="flex justify-between gap-2"><span>حجوزات منشأة في الفترة</span><span class="font-mono">{{ bi.operational.bookings_created_total }}</span></li>
              <li class="flex justify-between gap-2"><span>أوامر شراء (عدد)</span><span class="font-mono">{{ bi.operational.purchases.count }}</span></li>
            </ul>
          </div>
        </div>
      </template>
    </section>

    <!-- مخططات -->
    <section class="space-y-4">
      <h2 class="text-sm font-bold text-gray-800 dark:text-white flex items-center gap-2">
        <span class="w-1 h-4 bg-emerald-500 rounded-full" />
        اتجاهات وتوزيع
      </h2>
      <div class="grid lg:grid-cols-2 gap-4">
        <div class="card p-4">
          <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">الإيراد اليومي</h3>
          <div v-if="lineLabels.length" class="h-64">
            <Line :data="lineData" :options="lineOpts" />
          </div>
          <p v-else class="text-sm text-gray-400 py-12 text-center">لا بيانات يومية في هذه الفترة</p>
        </div>
        <div class="card p-4">
          <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">المبيعات حسب الفرع</h3>
          <div v-if="branchLabels.length" class="h-64">
            <Bar :data="barData" :options="barOpts" />
          </div>
          <p v-else class="text-sm text-gray-400 py-12 text-center">لا توزيع فروع</p>
        </div>
      </div>
      <div class="grid lg:grid-cols-3 gap-4">
        <div class="card p-4 lg:col-span-1">
          <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">المدفوعات حسب الطريقة</h3>
          <div v-if="payLabels.length" class="h-56 flex items-center justify-center">
            <Doughnut :data="doughnutData" :options="doughnutOpts" />
          </div>
          <p v-else class="text-sm text-gray-400 py-8 text-center">لا بيانات</p>
        </div>
        <div class="card p-4 lg:col-span-2">
          <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">ملخص سريع</h3>
          <ul class="text-sm text-gray-600 dark:text-slate-300 space-y-2">
            <li class="flex justify-between border-b border-gray-100 dark:border-slate-700 pb-2">
              <span>فواتير قيد التحصيل (عدد)</span>
              <span class="font-mono font-semibold">{{ summary?.receivables?.open_invoice_count ?? '—' }}</span>
            </li>
            <li class="flex justify-between border-b border-gray-100 dark:border-slate-700 pb-2">
              <span>أوامر عمل منشأة في الفترة</span>
              <span class="font-mono font-semibold">{{ summary?.work_orders?.created_in_period ?? '—' }}</span>
            </li>
            <li class="flex justify-between border-b border-gray-100 dark:border-slate-700 pb-2">
              <span>أوامر مكتملة في الفترة</span>
              <span class="font-mono font-semibold">{{ summary?.work_orders?.completed_in_period ?? '—' }}</span>
            </li>
            <li class="flex justify-between">
              <span>أرصدة المحافظ (إجمالي تقريبي)</span>
              <span class="font-mono font-semibold">{{ fmt(walletTotal) }}</span>
            </li>
          </ul>
        </div>
      </div>
    </section>

    <!-- روابط ذكية -->
    <section class="rounded-2xl border border-dashed border-gray-300 dark:border-slate-600 p-6 bg-gray-50/50 dark:bg-slate-900/40">
      <h3 class="text-sm font-bold text-gray-800 dark:text-white mb-4">متابعة التحليل</h3>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3">
        <RouterLink
          v-for="l in smartLinks"
          :key="l.to"
          :to="l.to"
          class="flex items-center gap-3 p-4 rounded-xl bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 hover:border-primary-400 transition-colors"
        >
          <component :is="l.icon" class="w-8 h-8 text-primary-600 dark:text-primary-400 shrink-0" />
          <div>
            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ l.label }}</p>
            <p class="text-[11px] text-gray-500">{{ l.hint }}</p>
          </div>
        </RouterLink>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import {
  ArrowPathIcon,
  ChevronLeftIcon,
  PresentationChartLineIcon,
  ChartBarSquareIcon,
  ArrowTrendingUpIcon,
  FunnelIcon,
  DocumentArrowDownIcon,
  BoltIcon,
  LinkIcon,
  TableCellsIcon,
  SignalIcon,
  FireIcon,
} from '@heroicons/vue/24/outline'
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  LineElement,
  BarElement,
  ArcElement,
  CategoryScale,
  LinearScale,
  PointElement,
  Filler,
} from 'chart.js'
import { Line, Bar, Doughnut } from 'vue-chartjs'
import apiClient from '@/lib/apiClient'
import KpiCard from '@/components/KpiCard.vue'
import SmartDatePicker from '@/components/ui/SmartDatePicker.vue'

ChartJS.register(
  Title,
  Tooltip,
  Legend,
  LineElement,
  BarElement,
  ArcElement,
  CategoryScale,
  LinearScale,
  PointElement,
  Filler,
)

const loading = ref(true)
const reportsDenied = ref(false)
const preset = ref<string>('month')
const to = ref(new Date().toISOString().split('T')[0])
const from = ref('')
{
  const d = new Date()
  d.setDate(1)
  from.value = d.toISOString().split('T')[0]
}

const summary = ref<any>(null)
const kpi = ref<any>(null)
const sales = ref<any>({ summary: {}, byBranch: [] })
const financial = ref<{ payments_by_method?: any[] }>({})

/** استجابة GET /reports/business-analytics */
const bi = ref<{
  financial: {
    invoice_by_status: { status: string; count: number; total_amount: number }[]
    subtotal_sum: number
    discount_sum: number
    tax_sum: number
    discount_ratio_pct: number
    payment_count: number
    payment_total: number
    avg_payment: number
    overdue_count: number
    overdue_amount: number
  }
  operational: {
    work_orders_by_status: { status: string; count: number }[]
    work_orders_created_total: number
    bookings_by_status: { status: string; count: number }[]
    bookings_created_total: number
    purchases: { count: number; total: number }
    low_stock_row_count: number
  }
  period: { from: string; to: string }
} | null>(null)

const INVOICE_STATUS_AR: Record<string, string> = {
  paid: 'مدفوعة',
  pending: 'معلقة',
  partial_paid: 'مدفوعة جزئياً',
  draft: 'مسودة',
  cancelled: 'ملغاة',
  refunded: 'مُستردة',
}

const WO_STATUS_AR: Record<string, string> = {
  pending: 'معلقة',
  in_progress: 'قيد التنفيذ',
  completed: 'مكتملة',
  cancelled: 'ملغاة',
  on_hold: 'معلّقة',
  scheduled: 'مجدولة',
}

const BOOKING_STATUS_AR: Record<string, string> = {
  pending: 'معلقة',
  confirmed: 'مؤكدة',
  in_progress: 'جارية',
  completed: 'مكتملة',
  cancelled: 'ملغاة',
  no_show: 'لم يحضر',
}

function invoiceStatusLabel(s: string) {
  return INVOICE_STATUS_AR[s] ?? s.replace(/_/g, ' ')
}

function woStatusLabel(s: string) {
  return WO_STATUS_AR[s] ?? s.replace(/_/g, ' ')
}

function bookingStatusLabel(s: string) {
  return BOOKING_STATUS_AR[s] ?? s.replace(/_/g, ' ')
}

const invoiceRows = computed(() => bi.value?.financial?.invoice_by_status ?? [])
const invoiceStatusLabels = computed(() => invoiceRows.value.map((r) => invoiceStatusLabel(r.status)))

const invoiceStatusBarData = computed(() => ({
  labels: invoiceRows.value.map((r) => invoiceStatusLabel(r.status)),
  datasets: [
    {
      label: 'إجمالي المبلغ (ر.س.)',
      data: invoiceRows.value.map((r) => Number(r.total_amount) || 0),
      backgroundColor: invoiceRows.value.map((_, i) => `hsla(${222 - i * 28}, 70%, 52%, 0.72)`),
      borderColor: invoiceRows.value.map((_, i) => `hsl(${222 - i * 28}, 70%, 42%)`),
      borderWidth: 1,
    },
  ],
}))

const invoiceStatusBarOpts = {
  indexAxis: 'y' as const,
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: { x: { beginAtZero: true } },
}

const woRows = computed(() => bi.value?.operational?.work_orders_by_status ?? [])
const woStatusLabels = computed(() => woRows.value.map((r) => woStatusLabel(r.status)))

const woStatusBarData = computed(() => ({
  labels: woRows.value.map((r) => woStatusLabel(r.status)),
  datasets: [
    {
      label: 'عدد الأوامر',
      data: woRows.value.map((r) => r.count),
      backgroundColor: 'rgba(124, 58, 237, 0.68)',
      borderColor: 'rgb(109, 40, 217)',
      borderWidth: 1,
    },
  ],
}))

const woStatusBarOpts = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: { y: { beginAtZero: true }, x: { ticks: { maxRotation: 40 } } },
}

const bookingRows = computed(() => bi.value?.operational?.bookings_by_status ?? [])
const bookingStatusLabels = computed(() => bookingRows.value.map((r) => bookingStatusLabel(r.status)))

const bookingStatusBarData = computed(() => ({
  labels: bookingRows.value.map((r) => bookingStatusLabel(r.status)),
  datasets: [
    {
      label: 'عدد الحجوزات',
      data: bookingRows.value.map((r) => r.count),
      backgroundColor: 'rgba(14, 165, 233, 0.68)',
      borderColor: 'rgb(2, 132, 199)',
      borderWidth: 1,
    },
  ],
}))

const bookingStatusBarOpts = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: { y: { beginAtZero: true }, x: { ticks: { maxRotation: 40 } } },
}

const presets = [
  { key: 'month', label: 'هذا الشهر' },
  { key: '90', label: 'آخر 90 يوماً' },
  { key: 'year', label: 'هذه السنة' },
  { key: '12m', label: 'آخر 12 شهراً' },
]

const featureHighlights = [
  { title: 'مؤشرات لحظية', desc: 'إيراد، تحصيل، ذمم، ومعدلات تشغيل في نطاق واحد.', icon: BoltIcon },
  { title: 'اتجاهات زمنية', desc: 'منحنى يومي لرصد الذروات والهدوء.', icon: ArrowTrendingUpIcon },
  { title: 'تفصيل جغرافي/فرعي', desc: 'مقارنة فروع لاكتشاف الفرص.', icon: ChartBarSquareIcon },
  { title: 'مزيج التحصيل', desc: 'توزيع طرق الدفع لتحسين نقاط البيع.', icon: FunnelIcon },
  { title: 'تصدير ومتابعة', desc: 'انتقلوا للتقارير أو دفتر الأستاذ بنقرة.', icon: DocumentArrowDownIcon },
  { title: 'ربط مركز الذكاء', desc: 'إشارات تشغيلية من طبقة الأحداث.', icon: SignalIcon },
  { title: 'جداول محاسبية', desc: 'ربط مع القيود والحسابات عند التفعيل.', icon: TableCellsIcon },
  { title: 'روابط تشغيلية', desc: 'من التحليل إلى الفاتورة أو أمر العمل.', icon: LinkIcon },
]

const smartLinks = [
  { to: '/reports', label: 'التقارير', hint: 'VAT، منتجات، عملاء', icon: ChartBarSquareIcon },
  { to: '/bays/heatmap', label: 'الخريطة الحرارية', hint: 'إشغال المناطق بالساعة', icon: FireIcon },
  { to: '/ledger', label: 'دفتر الأستاذ', hint: 'قيود وحركة حسابية', icon: TableCellsIcon },
  { to: '/internal/intelligence', label: 'مركز العمليات الذكي', hint: 'إشارات الآن/التالي', icon: SignalIcon },
  { to: '/wallet', label: 'المحافظ', hint: 'أرصدة العملاء', icon: BoltIcon },
]

const salesKpi = computed(() => {
  const k = kpi.value ?? {}
  const s = summary.value?.sales ?? {}
  return {
    total_sales: k.total_sales ?? k.total_revenue ?? s.total_revenue ?? 0,
    total_collected: k.total_collected ?? k.total_paid ?? s.total_collected ?? 0,
    total_vat: k.total_vat ?? 0,
    avg_invoice_value: k.avg_invoice_value ?? s.avg_invoice_value ?? 0,
  }
})

const collectionRate = computed(() => {
  const k = kpi.value
  if (k?.collection_rate != null) return Number(k.collection_rate).toFixed(1)
  const tr = Number(summary.value?.sales?.total_revenue ?? 0)
  const tc = Number(summary.value?.sales?.total_collected ?? 0)
  if (tr <= 0) return '0.0'
  return ((tc / tr) * 100).toFixed(1)
})

const walletTotal = computed(() => {
  const b = summary.value?.wallets?.balance_by_type ?? {}
  return Object.values(b).reduce((a: number, v: any) => a + Number(v ?? 0), 0)
})

const lineLabels = computed(() => (kpi.value?.daily_revenue ?? []).map((x: any) => x.day))
const lineData = computed(() => ({
  labels: lineLabels.value,
  datasets: [
    {
      label: 'إيراد يومي (ر.س.)',
      data: (kpi.value?.daily_revenue ?? []).map((x: any) => x.total),
      borderColor: 'rgb(59, 130, 246)',
      backgroundColor: 'rgba(59, 130, 246, 0.12)',
      fill: true,
      tension: 0.35,
    },
  ],
}))
const lineOpts = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: true, position: 'bottom' as const } },
  scales: { y: { beginAtZero: true } },
}

const branchLabels = computed(() => (sales.value.byBranch ?? []).map((b: any) => b.branch?.name ?? 'فرع'))
const barData = computed(() => ({
  labels: branchLabels.value,
  datasets: [
    {
      label: 'مبيعات',
      data: (sales.value.byBranch ?? []).map((b: any) => Number(b.total_sales) || 0),
      backgroundColor: 'rgba(99, 102, 241, 0.65)',
      borderColor: 'rgb(79, 70, 229)',
      borderWidth: 1,
    },
  ],
}))
const barOpts = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: { y: { beginAtZero: true }, x: { ticks: { maxRotation: 45 } } },
}

const payLabels = computed(() => (financial.value.payments_by_method ?? []).map((r: any) => r.method ?? '—'))
const payAmounts = computed(() => (financial.value.payments_by_method ?? []).map((r: any) => Number(r.total) || 0))
const doughnutData = computed(() => ({
  labels: payLabels.value,
  datasets: [
    {
      data: payAmounts.value,
      backgroundColor: [
        'rgba(59, 130, 246, 0.75)',
        'rgba(16, 185, 129, 0.75)',
        'rgba(245, 158, 11, 0.75)',
        'rgba(239, 68, 68, 0.75)',
        'rgba(139, 92, 246, 0.75)',
        'rgba(100, 116, 139, 0.75)',
      ],
    },
  ],
}))
const doughnutOpts = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { position: 'bottom' as const } },
}

function fmt(v: any) {
  const n = parseFloat(v) || 0
  return n.toLocaleString('ar-SA', { style: 'currency', currency: 'SAR', maximumFractionDigits: 0 })
}

function params() {
  return { from: from.value, to: to.value }
}

function applyPreset(key: string) {
  preset.value = key
  const end = new Date()
  const toStr = end.toISOString().split('T')[0]
  if (key === 'month') {
    const s = new Date(end.getFullYear(), end.getMonth(), 1)
    from.value = s.toISOString().split('T')[0]
  } else if (key === '90') {
    const s = new Date()
    s.setDate(s.getDate() - 90)
    from.value = s.toISOString().split('T')[0]
  } else if (key === 'year') {
    from.value = `${end.getFullYear()}-01-01`
  } else if (key === '12m') {
    const s = new Date()
    s.setFullYear(s.getFullYear() - 1)
    from.value = s.toISOString().split('T')[0]
  }
  to.value = toStr
  loadAll()
}

function applyRange() {
  loadAll()
}

function onDateRangeChange(val: { from: string; to: string }) {
  from.value = val.from
  to.value = val.to
}

async function loadAll() {
  loading.value = true
  reportsDenied.value = false
  const p = params()
  try {
    const s = await apiClient.get('/dashboard/summary', { params: p })
    summary.value = s.data?.data ?? null
  } catch {
    summary.value = null
  }
  try {
    const k = await apiClient.get('/reports/kpi', { params: p })
    kpi.value = k.data?.data ?? null
  } catch {
    kpi.value = null
    reportsDenied.value = true
  }
  try {
    const sl = await apiClient.get('/reports/sales', { params: p })
    sales.value = sl.data?.data ?? { summary: {}, byBranch: [] }
  } catch {
    sales.value = { summary: {}, byBranch: [] }
    reportsDenied.value = true
  }
  try {
    const f = await apiClient.get('/reports/financial', { params: p })
    financial.value = f.data?.data ?? {}
  } catch {
    financial.value = {}
    reportsDenied.value = true
  }
  try {
    const bar = await apiClient.get('/reports/business-analytics', { params: p })
    bi.value = bar.data?.data ?? null
  } catch (err: unknown) {
    bi.value = null
    const st = (err as { response?: { status?: number } })?.response?.status
    if (st === 403) {
      reportsDenied.value = true
    }
  }
  loading.value = false
}

onMounted(loadAll)
</script>
