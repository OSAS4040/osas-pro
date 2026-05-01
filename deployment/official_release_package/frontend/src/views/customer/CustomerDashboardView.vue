<template>
  <div class="space-y-5 max-w-[1600px] mx-auto">
    <div class="flex items-center justify-between gap-4 flex-wrap rounded-2xl border border-gray-200/80 dark:border-slate-700/80 bg-gradient-to-l from-primary-50/90 via-white to-violet-50/70 dark:from-slate-900 dark:via-slate-900 dark:to-primary-950/40 px-4 py-3 shadow-sm">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-slate-100 tracking-tight">لوحة التحكم</h1>
        <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">{{ dashGreeting }} — {{ today }}</p>
      </div>
      <div class="flex items-center gap-3 flex-wrap">
        <WeatherClock />
        <button class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200 border border-gray-200 dark:border-slate-600 rounded-lg px-3 py-1.5 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors" @click="loadData">
          <ArrowPathIcon class="w-3.5 h-3.5" :class="loading ? 'animate-spin' : ''" />
          تحديث
        </button>
      </div>
    </div>

    <MotivationalQuotes />
    <div
      v-if="demoMode"
      class="rounded-xl border border-amber-200 bg-amber-50/80 dark:border-amber-900/50 dark:bg-amber-950/25 px-4 py-2 text-xs text-amber-800 dark:text-amber-200"
    >
      وضع تجريبي مفعل لعرض عمليات وبيانات واقعية للتأكد من الواجهة.
    </div>

    <section
      v-if="showAnalyticsStrip"
      class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-xl border border-gray-200/80 dark:border-slate-700 bg-white/80 dark:bg-slate-800/80 px-4 py-3"
    >
      <p class="text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wide">تحليلات وتشغيل</p>
      <div class="flex flex-wrap gap-2">
        <RouterLink
          v-if="showBiShortcuts"
          to="/customer/business-intelligence"
          class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200/80 dark:border-indigo-800/50 bg-indigo-50/90 dark:bg-indigo-950/40 px-3 py-1.5 text-xs font-medium text-indigo-800 dark:text-indigo-200 hover:bg-indigo-100/90 dark:hover:bg-indigo-900/35 transition-colors"
        >
          <PresentationChartLineIcon class="w-4 h-4" />
          ذكاء الأعمال
        </RouterLink>
        <RouterLink
          v-if="showHeatmapShortcut"
          to="/customer/reports"
          class="inline-flex items-center gap-1.5 rounded-lg border border-orange-200/80 dark:border-orange-900/50 bg-orange-50/90 dark:bg-orange-950/35 px-3 py-1.5 text-xs font-medium text-orange-900 dark:text-orange-200 hover:bg-orange-100/90 dark:hover:bg-orange-900/30 transition-colors"
        >
          <FireIcon class="w-4 h-4" />
          تحليل التشغيل
        </RouterLink>
      </div>
    </section>

    <div v-if="loading" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <div
        v-for="i in 2"
        :key="'ch-' + i"
        class="h-[280px] rounded-xl border border-gray-200 dark:border-slate-700 bg-gray-100/80 dark:bg-slate-800/60 animate-pulse"
      />
    </div>
    <DashboardCharts
      v-else
      :revenue="chartRevenue"
      :work-orders="chartWo"
      revenue-title="قيمة الفواتير — آخر 7 أيام"
      revenue-subtitle="إجمالي مبالغ فواتير العميل يومياً (ليست إيراد المنشأة)"
    />

    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
      <template v-if="loading">
        <div v-for="i in 8" :key="i" class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-slate-700 p-5">
          <SkeletonBox height="2.5rem" width="2.5rem" class="rounded-xl mb-3" />
          <SkeletonBox height="1.5rem" width="60%" class="mb-2" />
          <SkeletonBox height="0.75rem" width="80%" />
        </div>
      </template>
      <template v-else>
        <KpiCard color="green" :icon="ChartBarIcon" :value="fmtMoney(kpi.totalRevenue)" label="إجمالي فواتيرك (الفترة)" sub="مجموع فواتير العميل خلال الفترة" />
        <KpiCard color="gray" :icon="DocumentTextIcon" :value="String(kpi.openInvoiceCount)" label="فواتير مفتوحة / قيد السداد" sub="عدد" />
        <KpiCard color="orange" :icon="ScaleIcon" :value="fmtMoney(kpi.totalOutstanding)" label="الذمم المدينة" sub="ر.س مستحقة" />
        <KpiCard color="purple" :icon="CurrencyDollarIcon" :value="fmtMoney(financeFigure)" :label="financeLabel" :sub="financeSubLabel" />
        <KpiCard color="blue" :icon="BanknotesIcon" :value="fmtMoney(kpi.totalCollected)" label="المسدد من فواتيرك (الفترة)" sub="مدفوعات العميل المكتملة ضمن الفترة" />
        <KpiCard color="indigo" :icon="ChartPieIcon" :value="`${kpi.collectionRate}%`" label="نسبة السداد" sub="نسبة المبلغ المسدد من إجمالي فواتير العميل" />
        <KpiCard color="purple" :icon="TruckIcon" :value="String(kpi.vehiclesCount)" label="عدد المركبات" sub="المركبات المسجلة للعميل" />
        <KpiCard color="gray" :icon="ReceiptPercentIcon" :value="fmtMoney(kpi.avgInvoiceValue)" label="متوسط قيمة الفاتورة" sub="للفواتير الصادرة (غير الملغاة/المسودة)" />
      </template>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-gray-800 dark:text-slate-100">أحدث الفواتير</h2>
          <RouterLink to="/customer/invoices" class="text-xs text-primary-600 dark:text-primary-400 hover:underline">عرض الكل ←</RouterLink>
        </div>
        <div v-if="loading">
          <SkeletonTable :rows="4" />
        </div>
        <div v-else-if="recentInvoices.length === 0" class="py-12 px-4 text-center rounded-b-xl bg-gray-50/50 dark:bg-slate-900/40">
          <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-600 mb-3 shadow-sm">
            <DocumentTextIcon class="w-7 h-7 text-gray-300 dark:text-slate-500" />
          </div>
          <p class="text-sm font-medium text-gray-600 dark:text-slate-300">لا توجد فواتير في القائمة</p>
          <p class="text-xs text-gray-400 dark:text-slate-500 mt-1">سيظهر أحدث النشاط المالي هنا</p>
        </div>
        <table v-else class="w-full text-sm">
          <tbody class="divide-y divide-gray-50 dark:divide-slate-700/80">
            <tr
              v-for="inv in recentInvoices"
              :key="inv.id"
              class="hover:bg-primary-50/40 dark:hover:bg-primary-950/20 transition-colors cursor-pointer"
              @click="$router.push(`/customer/invoices/${inv.id}`)"
            >
              <td class="px-5 py-2.5 font-medium text-gray-800 dark:text-slate-200 font-mono">{{ inv.invoice_number }}</td>
              <td class="px-5 py-2.5 text-gray-600 dark:text-slate-400 truncate max-w-[120px]">{{ inv.customer_name || auth.user?.name }}</td>
              <td class="px-5 py-2.5 text-primary-700 dark:text-primary-400 font-semibold">{{ fmtMoney(parseFloat(inv.total ?? 0)) }}</td>
              <td class="px-5 py-2.5">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="invoiceStatusClass(inv.status)">
                  {{ invoiceStatusLabel(inv.status) }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-gray-800 dark:text-slate-100 flex items-center gap-2">
            <span class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
            حالة أوامر العمل
          </h2>
          <RouterLink to="/customer/work-orders" class="text-xs text-primary-600 dark:text-primary-400 hover:underline">عرض الكل ←</RouterLink>
        </div>
        <div v-if="loading" class="p-4"><SkeletonTable :rows="3" /></div>
        <div v-else class="p-4 space-y-3">
          <div v-for="stat in woStats" :key="stat.label" class="flex items-center gap-3 p-3 rounded-xl" :class="stat.bg">
            <component :is="stat.icon" class="w-5 h-5 flex-shrink-0" :class="stat.color" />
            <div class="flex-1">
              <p class="text-sm font-medium text-gray-800 dark:text-slate-200">{{ stat.label }}</p>
              <p v-if="stat.hint" class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">{{ stat.hint }}</p>
            </div>
            <span class="text-2xl font-bold" :class="stat.color">{{ stat.value }}</span>
          </div>
          <div v-if="woStats.every(s => Number(s.value) === 0)" class="py-8 text-center text-gray-500 dark:text-slate-400">
            <CheckCircleIcon class="w-10 h-10 text-primary-200 dark:text-primary-900/60 mx-auto mb-2" />
            <p class="text-sm font-medium">لا أوامر عمل جديدة في الفترة الحالية</p>
            <p class="text-xs mt-1 text-gray-400">عند استقبال أوامر عمل جديدة سيظهر التوزيع هنا</p>
          </div>
        </div>
      </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 px-5 py-4">
      <div class="flex items-center justify-between gap-2 mb-3">
        <p class="text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wide">عمليات العميل المباشرة</p>
        <button class="text-xs text-primary-600 dark:text-primary-400 hover:underline" @click="loadData">تحديث العمليات</button>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
        <div class="rounded-xl border border-gray-200 dark:border-slate-700 p-3 space-y-2">
          <div class="flex items-center justify-between">
            <p class="text-sm font-bold text-gray-800 dark:text-slate-100">أوامر العمل</p>
            <RouterLink to="/customer/work-orders" class="text-xs text-primary-600 dark:text-primary-400 hover:underline">عرض</RouterLink>
          </div>
          <p v-if="recentWorkOrders.length === 0" class="text-xs text-gray-500">لا توجد أوامر حديثة.</p>
          <div v-else class="space-y-1">
            <button
              v-for="wo in recentWorkOrders"
              :key="wo.id"
              type="button"
              class="w-full text-right rounded-lg px-2 py-1.5 text-xs bg-gray-50 dark:bg-slate-900/50 hover:bg-primary-50 dark:hover:bg-primary-950/30"
              @click="$router.push(`/customer/work-orders`)"
            >
              <p class="font-semibold text-gray-700 dark:text-slate-200">{{ wo.order_number || `WO-${wo.id}` }}</p>
              <p class="text-gray-500 dark:text-slate-400">{{ wo.status || '—' }}</p>
            </button>
          </div>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-slate-700 p-3 space-y-2">
          <div class="flex items-center justify-between">
            <p class="text-sm font-bold text-gray-800 dark:text-slate-100">المركبات</p>
            <RouterLink to="/customer/vehicles" class="text-xs text-primary-600 dark:text-primary-400 hover:underline">عرض</RouterLink>
          </div>
          <p v-if="recentVehicles.length === 0" class="text-xs text-gray-500">لا توجد مركبات في القائمة.</p>
          <div v-else class="space-y-1">
            <button
              v-for="v in recentVehicles"
              :key="v.id"
              type="button"
              class="w-full text-right rounded-lg px-2 py-1.5 text-xs bg-gray-50 dark:bg-slate-900/50 hover:bg-primary-50 dark:hover:bg-primary-950/30"
              @click="$router.push(`/customer/vehicles/${v.id}`)"
            >
              <p class="font-semibold text-gray-700 dark:text-slate-200">{{ v.plate_number || `#${v.id}` }}</p>
              <p class="text-gray-500 dark:text-slate-400">{{ [v.make, v.model].filter(Boolean).join(' ') || 'مركبة' }}</p>
            </button>
          </div>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-slate-700 p-3 space-y-2">
          <div class="flex items-center justify-between">
            <p class="text-sm font-bold text-gray-800 dark:text-slate-100">الخدمات المتاحة</p>
            <RouterLink to="/customer/work-orders" class="text-xs text-primary-600 dark:text-primary-400 hover:underline">استخدمها</RouterLink>
          </div>
          <p v-if="availableServices.length === 0" class="text-xs text-gray-500">لا توجد خدمات متاحة حالياً.</p>
          <div v-else class="flex flex-wrap gap-1">
            <span
              v-for="svc in availableServices"
              :key="svc.id"
              class="px-2 py-1 rounded-full text-[11px] font-semibold bg-violet-100 text-violet-800 dark:bg-violet-900/35 dark:text-violet-200"
            >
              {{ svc.name }}
            </span>
          </div>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-slate-700 p-3 space-y-2">
          <div class="flex items-center justify-between">
            <p class="text-sm font-bold text-gray-800 dark:text-slate-100">الفواتير</p>
            <RouterLink to="/customer/invoices" class="text-xs text-primary-600 dark:text-primary-400 hover:underline">عرض</RouterLink>
          </div>
          <p v-if="recentInvoices.length === 0" class="text-xs text-gray-500">لا توجد فواتير حديثة.</p>
          <div v-else class="space-y-1">
            <button
              v-for="inv in recentInvoices.slice(0, 3)"
              :key="`mini-${inv.id}`"
              type="button"
              class="w-full text-right rounded-lg px-2 py-1.5 text-xs bg-gray-50 dark:bg-slate-900/50 hover:bg-primary-50 dark:hover:bg-primary-950/30"
              @click="$router.push(`/customer/invoices/${inv.id}`)"
            >
              <p class="font-semibold text-gray-700 dark:text-slate-200">{{ inv.invoice_number }}</p>
              <p class="text-gray-500 dark:text-slate-400">{{ fmtMoney(parseFloat(inv.total ?? 0)) }} ر.س</p>
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 px-5 py-4">
      <div class="flex items-center justify-between gap-2 mb-3">
        <p class="text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wide">عمليات واقعية للعميل</p>
        <RouterLink to="/customer/notifications" class="text-xs text-primary-600 dark:text-primary-400 hover:underline">متابعة التنفيذ ←</RouterLink>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
        <RouterLink
          v-for="op in operationCards"
          :key="op.title"
          :to="op.to"
          class="rounded-xl border border-gray-200 dark:border-slate-700 bg-gray-50/70 dark:bg-slate-900/40 p-3.5 hover:border-primary-300 dark:hover:border-primary-700 hover:bg-primary-50/40 dark:hover:bg-primary-950/20 transition-colors"
        >
          <div class="flex items-center justify-between gap-2">
            <div class="inline-flex items-center justify-center w-9 h-9 rounded-lg" :class="op.iconBg">
              <component :is="op.icon" class="w-4 h-4" :class="op.iconColor" />
            </div>
            <span class="text-[11px] px-2 py-0.5 rounded-full bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300">
              {{ op.metric }}
            </span>
          </div>
          <p class="text-sm font-bold text-gray-800 dark:text-slate-100 mt-2">{{ op.title }}</p>
          <p class="text-xs text-gray-500 dark:text-slate-400 mt-1 leading-relaxed">{{ op.description }}</p>
        </RouterLink>
      </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 px-5 py-4">
      <p class="text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wide mb-3">وصول سريع</p>
      <div class="flex flex-wrap gap-2">
        <QuickBtn :icon="DocumentTextIcon" label="فواتيري" to="/customer/invoices" color="blue" />
        <QuickBtn :icon="ClipboardDocumentIcon" label="أوامر العمل" to="/customer/work-orders" color="purple" />
        <QuickBtn :icon="TruckIcon" label="مركباتي" to="/customer/vehicles" color="green" />
        <QuickBtn :icon="ChartBarIcon" label="التقارير" to="/customer/reports" color="gray" />
        <QuickBtn :icon="PresentationChartLineIcon" label="ذكاء الأعمال" to="/customer/business-intelligence" color="indigo" />
        <QuickBtn
          v-if="isCreditCustomer"
          :icon="ScaleIcon"
          label="الملف الائتماني"
          to="/customer/invoices"
          color="orange"
        />
        <QuickBtn
          v-else
          :icon="CurrencyDollarIcon"
          label="رفع طلب شحن"
          to="/customer/wallet/top-up-requests"
          color="orange"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
/* eslint-disable vue/one-component-per-file */
import { ref, computed, defineComponent, h, onMounted } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import {
  CheckCircleIcon, CurrencyDollarIcon, DocumentTextIcon, ScaleIcon, ChartBarIcon,
  ClipboardDocumentIcon, ArrowPathIcon, ArrowTrendingUpIcon, ArrowTrendingDownIcon,
  PresentationChartLineIcon, TruckIcon, FireIcon, BanknotesIcon, ChartPieIcon, ReceiptPercentIcon, MapPinIcon, UserGroupIcon, LifebuoyIcon, CalendarDaysIcon, QueueListIcon,
} from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'
import SkeletonBox from '@/components/SkeletonBox.vue'
import SkeletonTable from '@/components/SkeletonTable.vue'
import WeatherClock from '@/components/WeatherClock.vue'
import MotivationalQuotes from '@/components/MotivationalQuotes.vue'
import DashboardCharts from '@/components/dashboard/DashboardCharts.vue'
import { invoiceStatusClass, invoiceStatusLabel } from '@/utils/financialLabels'
import { demoCustomerInvoices, demoCustomerVehicles, demoCustomerWorkOrders, demoCustomerServices } from '@/utils/customerDemoData'

const $router = useRouter()
const auth = useAuthStore()
const loading = ref(false)
const demoMode = ref(false)
const walletsTotal = ref(0)
const customerProfile = ref<any | null>(null)

const isCreditCustomer = computed(() => {
  const profile = String(customerProfile.value?.customer_pricing_profile ?? '').toLowerCase()
  if (profile === 'credit') return true
  const limit = Number(customerProfile.value?.credit_limit ?? 0)
  return Number.isFinite(limit) && limit > 0
})
const creditLimit = computed(() => Number(customerProfile.value?.credit_limit ?? 0))
const financeFigure = computed(() => (isCreditCustomer.value ? creditLimit.value : walletsTotal.value))
const financeLabel = computed(() => (isCreditCustomer.value ? 'الحد الائتماني' : 'أرصدة المحافظ'))
const financeSubLabel = computed(() => (isCreditCustomer.value ? 'السقف المتاح للعميل' : 'مجموع أنواع المحافظ'))
const showBiShortcuts = computed(() => true)
const showHeatmapShortcut = computed(() => true)
const showAnalyticsStrip = computed(() => showBiShortcuts.value || showHeatmapShortcut.value)

const today = computed(() => new Date().toLocaleDateString('ar-SA-u-ca-gregory', {
  weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
}))

const dashGreeting = computed(() => {
  const hour = new Date().getHours()
  if (hour >= 5 && hour < 12) return 'صباح الخير'
  if (hour >= 12 && hour < 18) return 'مساء الخير'
  if (hour >= 18 && hour < 24) return 'طابت مساءاتكم'
  return 'طابت ليلتكم'
})

const kpi = ref({
  totalRevenue: 0,
  openInvoiceCount: 0,
  totalOutstanding: 0,
  totalCollected: 0,
  collectionRate: 0,
  avgInvoiceValue: 0,
  vehiclesCount: 0,
  woCreated: 0,
  woCompleted: 0,
  woCompletionRate: 0,
})
const recentInvoices = ref<any[]>([])
const recentWorkOrders = ref<any[]>([])
const recentVehicles = ref<any[]>([])
const availableServices = ref<Array<{ id: number; name: string }>>([])
const chartRevenue = ref<{ date: string; revenue: number }[]>([])
const chartWo = ref<{ date: string; count: number }[]>([])

const woStats = computed(() => [
  {
    label: 'أوامر عمل جديدة',
    hint: 'ضمن فترة التقرير',
    value: kpi.value.woCreated,
    icon: ClipboardDocumentIcon,
    color: 'text-blue-600 dark:text-blue-400',
    bg: 'bg-blue-50 dark:bg-blue-950/35',
  },
  {
    label: 'مكتملة',
    hint: `${kpi.value.woCompletionRate}% منشأة في الفترة`,
    value: kpi.value.woCompleted,
    icon: CheckCircleIcon,
    color: 'text-primary-600 dark:text-primary-400',
    bg: 'bg-primary-50 dark:bg-primary-950/35',
  },
])

const operationCards = computed(() => {
  const financeCard = isCreditCustomer.value
    ? {
      title: 'مراجعة الملف الائتماني',
      description: 'متابعة حد الائتمان والذمم المفتوحة لضبط قرارات التشغيل.',
      to: '/customer/invoices',
      metric: `حد ${fmtMoney(creditLimit.value)}`,
      icon: ScaleIcon,
      iconBg: 'bg-orange-50 dark:bg-orange-950/30',
      iconColor: 'text-orange-600 dark:text-orange-300',
    }
    : {
      title: 'رفع طلب شحن الرصيد',
      description: 'إرسال طلب شحن للمحفظة الرئيسية مع المستندات للمراجعة.',
      to: '/customer/wallet/top-up-requests',
      metric: 'محفظة الشركة',
      icon: QueueListIcon,
      iconBg: 'bg-orange-50 dark:bg-orange-950/30',
      iconColor: 'text-orange-600 dark:text-orange-300',
    }
  return [
    {
      title: 'إرسال أمر عمل',
      description: 'إنشاء أمر جديد ومتابعة حالته حتى الإغلاق النهائي.',
      to: '/customer/work-orders',
      metric: `${kpi.value.woCreated} جديد`,
      icon: ClipboardDocumentIcon,
      iconBg: 'bg-blue-50 dark:bg-blue-950/30',
      iconColor: 'text-blue-600 dark:text-blue-300',
    },
    {
      title: 'حجز موعد خدمة',
      description: 'حجز موعد مناسب للمركبة حسب الخدمة المفعلة بالعقد.',
      to: '/customer/bookings',
      metric: `${kpi.value.vehiclesCount} مركبة`,
      icon: CalendarDaysIcon,
      iconBg: 'bg-violet-50 dark:bg-violet-950/30',
      iconColor: 'text-violet-600 dark:text-violet-300',
    },
    {
      title: 'متابعة الدعم الفني',
      description: 'فتح التذاكر والرد عليها وتتبع تحديثات الحالة فوريا.',
      to: '/customer/notifications',
      metric: `${kpi.value.openInvoiceCount} متابعة`,
      icon: LifebuoyIcon,
      iconBg: 'bg-rose-50 dark:bg-rose-950/30',
      iconColor: 'text-rose-600 dark:text-rose-300',
    },
    financeCard,
    {
      title: 'مواقع التغطية',
      description: 'استعراض مزودي الخدمة المعتمدين حسب الخدمات المتاحة لك.',
      to: '/customer/coverage-locations',
      metric: 'خرائط وتنقل',
      icon: MapPinIcon,
      iconBg: 'bg-emerald-50 dark:bg-emerald-950/30',
      iconColor: 'text-emerald-600 dark:text-emerald-300',
    },
    {
      title: 'إدارة فريق العميل',
      description: 'إضافة مستخدمين وتوزيع الصلاحيات على وحدات العمل.',
      to: '/customer/team-users',
      metric: 'صلاحيات',
      icon: UserGroupIcon,
      iconBg: 'bg-indigo-50 dark:bg-indigo-950/30',
      iconColor: 'text-indigo-600 dark:text-indigo-300',
    },
  ]
})

function fillChartsFromApi(d: Record<string, unknown> | null) {
  const revIn = Array.isArray(d?.charts && (d.charts as any).revenue_last_7_days) ? (d!.charts as any).revenue_last_7_days : []
  const woIn = Array.isArray(d?.charts && (d.charts as any).work_orders_last_7_days) ? (d!.charts as any).work_orders_last_7_days : []
  const rev: { date: string; revenue: number }[] = []
  const wo: { date: string; count: number }[] = []
  for (let i = 6; i >= 0; i--) {
    const x = new Date()
    x.setDate(x.getDate() - i)
    const ds = x.toISOString().slice(0, 10)
    const rRow = revIn.find((r: any) => r?.date === ds)
    const wRow = woIn.find((r: any) => r?.date === ds)
    rev.push({ date: ds, revenue: rRow != null ? Number(rRow.revenue) : 0 })
    wo.push({ date: ds, count: wRow != null ? Number(wRow.count) : 0 })
  }
  chartRevenue.value = rev
  chartWo.value = wo
}

function extractList(data: any): any[] {
  if (Array.isArray(data?.data?.data)) return data.data.data
  if (Array.isArray(data?.data)) return data.data
  if (Array.isArray(data)) return data
  return []
}

async function loadData() {
  loading.value = true
  demoMode.value = false
  try {
    const [sumRes, invRes, walletRes, vehiclesRes, customerRes, woRes, servicesRes] = await Promise.allSettled([
      apiClient.get('/dashboard/summary'),
      apiClient.get('/invoices', { params: { per_page: 5 } }),
      apiClient.get('/wallet'),
      apiClient.get('/vehicles', { params: { per_page: 5 } }),
      auth.user?.customer_id ? apiClient.get(`/customers/${auth.user.customer_id}`) : Promise.resolve({ data: null }),
      apiClient.get('/work-orders', { params: { per_page: 5 } }),
      apiClient.get('/fleet-portal/service-catalog', { params: { per_page: 12 } }),
    ])
    if (sumRes.status === 'fulfilled') {
      const d = sumRes.value.data?.data
      if (d) {
        kpi.value.totalRevenue = Number(d.sales?.total_revenue ?? 0)
        kpi.value.totalCollected = Number(d.sales?.total_collected ?? 0)
        kpi.value.collectionRate = Number(d.sales?.collection_rate ?? 0)
        kpi.value.avgInvoiceValue = Number(d.sales?.avg_invoice_value ?? 0)
        kpi.value.openInvoiceCount = Number(d.receivables?.open_invoice_count ?? 0)
        kpi.value.totalOutstanding = Number(d.receivables?.total_outstanding ?? 0)
        kpi.value.woCreated = Number(d.work_orders?.created_in_period ?? 0)
        kpi.value.woCompleted = Number(d.work_orders?.completed_in_period ?? 0)
        kpi.value.woCompletionRate = Number(d.work_orders?.completion_rate ?? 0)
        fillChartsFromApi(d as Record<string, unknown>)
      } else {
        fillChartsFromApi(null)
      }
    } else {
      fillChartsFromApi(null)
    }
    if (invRes.status === 'fulfilled') {
      const raw = invRes.value.data?.data
      const items = Array.isArray(raw) ? raw : raw?.data ?? []
      recentInvoices.value = items.slice(0, 5)
    }
    if (walletRes.status === 'fulfilled') {
      const body = walletRes.value.data
      const wallets = Array.isArray(body?.wallets?.data) ? body.wallets.data : Array.isArray(body?.wallets) ? body.wallets : extractList(body)
      walletsTotal.value = wallets.reduce((sum: number, w: any) => sum + Number(w?.balance ?? 0), 0)
    }
    if (vehiclesRes.status === 'fulfilled') {
      const body = vehiclesRes.value.data
      const count = Number(body?.meta?.total ?? body?.total ?? extractList(body).length ?? 0)
      kpi.value.vehiclesCount = Number.isFinite(count) ? count : 0
      recentVehicles.value = extractList(body).slice(0, 3)
    }
    if (woRes.status === 'fulfilled') {
      recentWorkOrders.value = extractList(woRes.value.data).slice(0, 3)
    }
    if (servicesRes.status === 'fulfilled') {
      const rows = extractList(servicesRes.value.data)
      availableServices.value = rows
        .map((row: any) => ({ id: Number(row?.id || 0), name: String(row?.name_ar || row?.name || '').trim() }))
        .filter((row: any) => row.id > 0 && row.name)
        .slice(0, 8)
    }
    if (customerRes.status === 'fulfilled') {
      const c = customerRes.value.data?.data ?? customerRes.value.data ?? null
      customerProfile.value = c && typeof c === 'object' ? c : null
    }
    if (!recentInvoices.value.length) {
      demoMode.value = true
      recentInvoices.value = demoCustomerInvoices
      kpi.value.openInvoiceCount = demoCustomerInvoices.filter((x) => String(x.status) !== 'paid').length
    }
    if (!recentVehicles.value.length) {
      demoMode.value = true
      recentVehicles.value = demoCustomerVehicles
      kpi.value.vehiclesCount = demoCustomerVehicles.length
    }
    if (!recentWorkOrders.value.length) {
      demoMode.value = true
      recentWorkOrders.value = demoCustomerWorkOrders
      kpi.value.woCreated = demoCustomerWorkOrders.length
    }
    if (!availableServices.value.length) {
      demoMode.value = true
      availableServices.value = demoCustomerServices
    }
  } finally {
    loading.value = false
  }
}

function fmtMoney(v: number) {
  return v.toLocaleString('ar-SA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const colorMap: Record<string, Record<string, string>> = {
  red: { bg: 'bg-red-50', icon: 'text-red-500' },
  green: { bg: 'bg-primary-50 dark:bg-primary-950/25', icon: 'text-primary-600 dark:text-primary-400' },
  blue: { bg: 'bg-blue-50', icon: 'text-blue-600' },
  purple: { bg: 'bg-primary-50 dark:bg-primary-950/25', icon: 'text-primary-600 dark:text-primary-400' },
  gray: { bg: 'bg-gray-50', icon: 'text-gray-500' },
  orange: { bg: 'bg-orange-50', icon: 'text-orange-500' },
  indigo: { bg: 'bg-indigo-50', icon: 'text-indigo-600' },
}

const KpiCard = defineComponent({
  props: { color: String, icon: Object, value: String, label: String, sub: String, trend: String },
  setup(p) {
    return () => {
      const c = colorMap[p.color ?? 'gray']
      const trendEl = p.trend
        ? h(p.trend === 'up' ? ArrowTrendingUpIcon : ArrowTrendingDownIcon, {
          class: `w-3.5 h-3.5 ${p.trend === 'up' ? 'text-primary-500' : 'text-red-400'}`,
        })
        : null
      return h('div', { class: 'bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-slate-700 p-5 flex items-start gap-4 hover:shadow-sm transition-shadow' }, [
        h('div', { class: `w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 ${c.bg}` }, [h(p.icon as any, { class: `w-5 h-5 ${c.icon}` })]),
        h('div', { class: 'flex-1 min-w-0' }, [
          h('div', { class: 'flex items-center gap-1.5' }, [
            h('p', { class: 'text-xl font-bold text-gray-900 dark:text-slate-100 leading-none tabular-nums' }, p.value),
            ...(trendEl ? [trendEl] : []),
          ]),
          h('p', { class: 'text-sm text-gray-600 dark:text-slate-300 mt-1 truncate' }, p.label),
          h('p', { class: 'text-xs text-gray-400 dark:text-slate-500 mt-0.5' }, p.sub),
        ]),
      ])
    }
  },
})

const QuickBtn = defineComponent({
  props: { icon: Object, label: String, to: { type: String, required: true as const }, color: String },
  setup(p) {
    const btnColor: Record<string, string> = {
      blue: 'bg-blue-600 hover:bg-blue-700',
      purple: 'bg-primary-600 hover:bg-primary-700',
      green: 'bg-primary-600 hover:bg-primary-700',
      gray: 'bg-gray-600 hover:bg-gray-700',
      orange: 'bg-orange-500 hover:bg-orange-600',
      indigo: 'bg-indigo-600 hover:bg-indigo-700',
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
