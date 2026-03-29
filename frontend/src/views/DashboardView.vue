<template>
  <div class="space-y-5 max-w-[1600px] mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between gap-4 flex-wrap">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-slate-100">لوحة التحكم</h1>
        <p class="text-xs text-gray-400 dark:text-slate-500 mt-0.5">{{ dashGreeting }} — {{ today }}</p>
      </div>
      <div class="flex items-center gap-3 flex-wrap">
        <WeatherClock />
        <button @click="loadData" class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200 border border-gray-200 dark:border-slate-600 rounded-lg px-3 py-1.5 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
          <ArrowPathIcon class="w-3.5 h-3.5" :class="loading ? 'animate-spin' : ''" />
          تحديث
        </button>
      </div>
    </div>

    <!-- Motivational Quote -->
    <MotivationalQuotes />

    <!-- KPI — من GET /dashboard/summary -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <template v-if="loading">
        <div v-for="i in 4" :key="i" class="bg-white rounded-xl border border-gray-100 p-5">
          <SkeletonBox height="2.5rem" width="2.5rem" class="rounded-xl mb-3" />
          <SkeletonBox height="1.5rem" width="60%" class="mb-2" />
          <SkeletonBox height="0.75rem" width="80%" />
        </div>
      </template>
      <template v-else>
        <KpiCard color="green"  :icon="ChartBarIcon"         :value="fmtMoney(kpi.totalRevenue)"    label="حجم الفواتير (الفترة)" sub="مجموع إجمالي الفواتير بحسب الإصدار — ليس المتحصّل النقدي" />
        <KpiCard color="gray"   :icon="DocumentTextIcon"     :value="String(kpi.openInvoiceCount)" label="فواتير مفتوحة / قيد التحصيل" sub="عدد" />
        <KpiCard color="orange" :icon="ScaleIcon"            :value="fmtMoney(kpi.totalOutstanding)" label="الذمم المدينة" sub="ر.س مستحقة" />
        <KpiCard color="teal"   :icon="CurrencyDollarIcon"   :value="fmtMoney(kpi.walletBalanceTotal)" label="أرصدة المحافظ" sub="مجموع الأنواع" />
      </template>
    </div>

    <!-- Middle Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

      <!-- Recent Invoices -->
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-gray-800">أحدث الفواتير</h2>
          <RouterLink to="/invoices" class="text-xs text-primary-600 hover:underline">عرض الكل ←</RouterLink>
        </div>
        <div v-if="loading">
          <SkeletonTable :rows="4" />
        </div>
        <div v-else-if="recentInvoices.length === 0" class="py-12 px-4 text-center rounded-b-xl bg-gray-50/50 dark:bg-slate-800/30">
          <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-600 mb-3 shadow-sm">
            <DocumentTextIcon class="w-7 h-7 text-gray-300 dark:text-slate-500" />
          </div>
          <p class="text-sm font-medium text-gray-600 dark:text-slate-300">لا توجد فواتير في القائمة</p>
          <p class="text-xs text-gray-400 dark:text-slate-500 mt-1">ابدأ بإصدار فاتورة أو استيراد من أمر عمل</p>
          <RouterLink to="/invoices/create" class="mt-4 inline-flex items-center gap-1.5 text-xs font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400">
            + إنشاء فاتورة
          </RouterLink>
        </div>
        <table v-else class="w-full text-sm">
          <tbody class="divide-y divide-gray-50">
            <tr
              v-for="inv in recentInvoices"
              :key="inv.id"
              class="hover:bg-primary-50/40 transition-colors cursor-pointer"
              @click="$router.push(`/invoices/${inv.id}`)"
            >
              <td class="px-5 py-2.5 font-medium text-gray-800 font-mono">{{ inv.invoice_number }}</td>
              <td class="px-5 py-2.5 text-gray-600 truncate max-w-[120px]">{{ inv.customer_name }}</td>
              <td class="px-5 py-2.5 text-green-700 font-semibold">{{ fmtMoney(parseFloat(inv.total ?? 0)) }}</td>
              <td class="px-5 py-2.5">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                  :class="invoiceStatusClass(inv.status)">
                  {{ invoiceStatusLabel(inv.status) }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Work Order Stats + Urgent -->
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
            <span class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
            حالة أوامر العمل
          </h2>
          <RouterLink to="/work-orders" class="text-xs text-primary-600 hover:underline">عرض الكل ←</RouterLink>
        </div>
        <div v-if="loading" class="p-4"><SkeletonTable :rows="3" /></div>
          <div v-else class="p-4 space-y-3">
          <div v-for="stat in woStats" :key="stat.label"
            class="flex items-center gap-3 p-3 rounded-xl"
            :class="stat.bg"
          >
            <component :is="stat.icon" class="w-5 h-5 flex-shrink-0" :class="stat.color" />
            <div class="flex-1">
              <p class="text-sm font-medium text-gray-800">{{ stat.label }}</p>
              <p v-if="stat.hint" class="text-xs text-gray-500 mt-0.5">{{ stat.hint }}</p>
            </div>
            <span class="text-2xl font-bold" :class="stat.color">{{ stat.value }}</span>
          </div>
          <div v-if="woStats.every(s => Number(s.value) === 0)" class="py-8 text-center text-gray-500 dark:text-slate-400">
            <CheckCircleIcon class="w-10 h-10 text-emerald-200 dark:text-emerald-800 mx-auto mb-2" />
            <p class="text-sm font-medium">لا أوامر عمل جديدة في الفترة الحالية</p>
            <p class="text-xs mt-1 text-gray-400">سيظهر هنا عدد الأوامر عند تفعيل ورشة العمل</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Actions Bar -->
    <div class="bg-white rounded-xl border border-gray-200 px-5 py-4">
      <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">وصول سريع</p>
      <div class="flex flex-wrap gap-2">
        <QuickBtn :icon="DocumentTextIcon"    label="فاتورة جديدة" to="/invoices/create"     color="blue" />
        <QuickBtn :icon="ClipboardDocumentIcon" label="أمر عمل"    to="/work-orders/new"     color="purple" />
        <QuickBtn :icon="ShoppingCartIcon"    label="نقطة البيع"   to="/pos"                 color="green" />
        <QuickBtn :icon="UsersIcon"           label="عميل جديد"    to="/customers"           color="teal" />
        <QuickBtn :icon="ChartBarIcon"        label="التقارير"     to="/reports"             color="gray" />
        <QuickBtn :icon="ScaleIcon"            label="ZATCA"        to="/zatca"                 color="orange" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, defineComponent, h, onMounted } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import {
  CheckCircleIcon, CurrencyDollarIcon, UsersIcon,
  DocumentTextIcon, ScaleIcon, ChartBarIcon,
  ClipboardDocumentIcon, ShoppingCartIcon, ArrowPathIcon,
  ArrowTrendingUpIcon, ArrowTrendingDownIcon,
} from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import SkeletonBox from '@/components/SkeletonBox.vue'
import SkeletonTable from '@/components/SkeletonTable.vue'
import WeatherClock from '@/components/WeatherClock.vue'
import MotivationalQuotes from '@/components/MotivationalQuotes.vue'
import { invoiceStatusClass, invoiceStatusLabel } from '@/utils/financialLabels'

const $router = useRouter()
const loading = ref(false)
const today   = computed(() => new Date().toLocaleDateString('ar-SA-u-ca-gregory', {
  weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
}))

const dashGreeting = computed(() => {
  const h = new Date().getHours()
  if (h >= 5  && h < 12) return 'صباح الخير'
  if (h >= 12 && h < 18) return 'مساء الخير'
  if (h >= 18 && h < 24) return 'طابت مساءاتكم'
  return 'طابت ليلتكم'
})

const kpi = ref({
  totalRevenue: 0,
  openInvoiceCount: 0,
  totalOutstanding: 0,
  walletBalanceTotal: 0,
  woCreated: 0,
  woCompleted: 0,
  woCompletionRate: 0,
})
const recentInvoices = ref<any[]>([])

const woStats = computed(() => [
  {
    label: 'أوامر عمل جديدة',
    hint: 'ضمن فترة التقرير',
    value: kpi.value.woCreated,
    icon: ClipboardDocumentIcon,
    color: 'text-blue-600',
    bg: 'bg-blue-50',
  },
  {
    label: 'مكتملة',
    hint: `${kpi.value.woCompletionRate}% منشأة في الفترة`,
    value: kpi.value.woCompleted,
    icon: CheckCircleIcon,
    color: 'text-green-600',
    bg: 'bg-green-50',
  },
])

async function loadData() {
  loading.value = true
  try {
    const [sumRes, invRes] = await Promise.allSettled([
      apiClient.get('/dashboard/summary'),
      apiClient.get('/invoices', { params: { per_page: 5 } }),
    ])
    if (sumRes.status === 'fulfilled') {
      const d = sumRes.value.data?.data
      if (d) {
        kpi.value.totalRevenue = Number(d.sales?.total_revenue ?? 0)
        kpi.value.openInvoiceCount = Number(d.receivables?.open_invoice_count ?? 0)
        kpi.value.totalOutstanding = Number(d.receivables?.total_outstanding ?? 0)
        const bal = d.wallets?.balance_by_type ?? {}
        kpi.value.walletBalanceTotal = Object.values(bal).reduce((s: number, v) => s + Number(v ?? 0), 0)
        kpi.value.woCreated = Number(d.work_orders?.created_in_period ?? 0)
        kpi.value.woCompleted = Number(d.work_orders?.completed_in_period ?? 0)
        kpi.value.woCompletionRate = Number(d.work_orders?.completion_rate ?? 0)
      }
    }
    if (invRes.status === 'fulfilled') {
      const items = invRes.value.data.data ?? []
      recentInvoices.value = items.slice(0, 5)
    }
  } catch { /* silent */ } finally {
    loading.value = false
  }
}

function fmtMoney(v: number) {
  return v.toLocaleString('ar-SA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const colorMap: Record<string, Record<string, string>> = {
  red:    { bg: 'bg-red-50',    icon: 'text-red-500',    val: 'text-red-700' },
  green:  { bg: 'bg-green-50',  icon: 'text-green-600',  val: 'text-green-700' },
  blue:   { bg: 'bg-blue-50',   icon: 'text-blue-600',   val: 'text-blue-700' },
  purple: { bg: 'bg-purple-50', icon: 'text-purple-600', val: 'text-purple-700' },
  gray:   { bg: 'bg-gray-50',   icon: 'text-gray-500',   val: 'text-gray-700' },
  orange: { bg: 'bg-orange-50', icon: 'text-orange-500', val: 'text-orange-700' },
  teal:   { bg: 'bg-teal-50',   icon: 'text-teal-600',   val: 'text-teal-700' },
}

const KpiCard = defineComponent({
  props: { color: String, icon: Object, value: String, label: String, sub: String, trend: String },
  setup(p) {
    return () => {
      const c = colorMap[p.color ?? 'gray']
      const trendEl = p.trend
        ? h(p.trend === 'up' ? ArrowTrendingUpIcon : ArrowTrendingDownIcon, {
            class: `w-3.5 h-3.5 ${p.trend === 'up' ? 'text-green-500' : 'text-red-400'}`
          })
        : null
      return h('div', { class: 'bg-white rounded-xl border border-gray-100 p-5 flex items-start gap-4 hover:shadow-sm transition-shadow' }, [
        h('div', { class: `w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 ${c.bg}` },
          [h(p.icon as any, { class: `w-5 h-5 ${c.icon}` })]),
        h('div', { class: 'flex-1 min-w-0' }, [
          h('div', { class: 'flex items-center gap-1.5' }, [
            h('p', { class: `text-xl font-bold text-gray-900 leading-none tabular-nums` }, p.value),
            ...(trendEl ? [trendEl] : []),
          ]),
          h('p', { class: 'text-sm text-gray-600 mt-1 truncate' }, p.label),
          h('p', { class: 'text-xs text-gray-400 mt-0.5' }, p.sub),
        ]),
      ])
    }
  },
})

const QuickBtn = defineComponent({
  props: { icon: Object, label: String, to: { type: String, required: true as const }, color: String },
  setup(p) {
    const btnColor: Record<string, string> = {
      blue:   'bg-blue-600 hover:bg-blue-700',   purple: 'bg-purple-600 hover:bg-purple-700',
      green:  'bg-green-600 hover:bg-green-700', teal:   'bg-teal-600 hover:bg-teal-700',
      gray:   'bg-gray-600 hover:bg-gray-700',   orange: 'bg-orange-500 hover:bg-orange-600',
    }
    return () => h(RouterLink, {
      to: p.to!,
      class: `flex items-center gap-1.5 px-4 py-2 text-white text-sm font-medium rounded-lg transition-all shadow-sm hover:shadow active:scale-[0.97] ${btnColor[p.color ?? 'gray']}`,
    }, () => [
      h(p.icon as any, { class: 'w-4 h-4' }),
      h('span', {}, p.label),
    ])
  },
})

onMounted(loadData)
</script>
