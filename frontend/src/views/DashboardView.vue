<template>
  <div class="space-y-5 max-w-[1600px] mx-auto">
    <NavigationSourceHint />
    <SetupWelcomeModal
      :open="auth.isManager && showWelcomeModal"
      @start="onSetupWelcomeStart"
      @later="onSetupWelcomeLater"
    />
    <SetupChecklistCard
      v-if="auth.isManager && shouldShowChecklist"
      id="setup-checklist-anchor"
      :status="setupStatus"
      :loading="setupLoading"
      :load-error="setupLoadError"
      :steps-done="setupStepsDone"
      :total-steps="setupTotalSteps"
      :progress-percent="setupProgressPercent"
      @dismiss="dismissChecklistForLater"
    />
    <!-- Header -->
    <div class="flex items-center justify-between gap-4 flex-wrap rounded-2xl border border-gray-200/80 dark:border-slate-700/80 bg-gradient-to-l from-primary-50/90 via-white to-violet-50/70 dark:from-slate-900 dark:via-slate-900 dark:to-primary-950/40 px-4 py-3 shadow-sm">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-slate-100 tracking-tight">
          {{ platformExecutionPartner ? 'لوحة المزوّد' : 'لوحة التحكم' }}
        </h1>
        <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">
          {{ dashGreeting }} — {{ today }}
          <span v-if="platformExecutionPartner" class="block mt-1 text-[11px] text-gray-400 dark:text-slate-500">
            عرض العمليات والمؤشرات التشغيلية فقط — إصدار الفواتير وإدارة العملاء من نطاق العميل/المنشأة المالكة.
          </span>
        </p>
      </div>
      <div class="flex items-center gap-3 flex-wrap">
        <RouterLink
          v-if="auth.user?.company_id"
          :to="{ name: 'companies.profile', params: { companyId: String(auth.user.company_id) } }"
          class="hidden sm:inline-flex text-xs font-medium text-primary-600 hover:underline px-2"
        >
          مركز الشركة
        </RouterLink>
        <WeatherClock />
        <button class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200 border border-gray-200 dark:border-slate-600 rounded-lg px-3 py-1.5 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors" @click="loadData({ refresh: true })">
          <ArrowPathIcon class="w-3.5 h-3.5" :class="loading ? 'animate-spin' : ''" />
          تحديث
        </button>
      </div>
    </div>

    <!-- Motivational Quote -->
    <MotivationalQuotes />

    <!-- روابط تحليلات سريعة — تظهر فقط عند تفعيل الميزة وملف النشاط -->
    <section
      v-if="showAnalyticsStrip"
      class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-xl border border-gray-200/80 dark:border-slate-700 bg-white/80 dark:bg-slate-800/80 px-4 py-3"
    >
      <p class="text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wide">تحليلات وتشغيل</p>
      <div class="flex flex-wrap gap-2">
        <RouterLink
          v-if="showBiShortcuts"
          to="/business-intelligence"
          class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200/80 dark:border-indigo-800/50 bg-indigo-50/90 dark:bg-indigo-950/40 px-3 py-1.5 text-xs font-medium text-indigo-800 dark:text-indigo-200 hover:bg-indigo-100/90 dark:hover:bg-indigo-900/35 transition-colors"
        >
          <PresentationChartLineIcon class="w-4 h-4" />
          ذكاء الأعمال
        </RouterLink>
        <RouterLink
          v-if="showHeatmapShortcut"
          to="/bays/heatmap"
          class="inline-flex items-center gap-1.5 rounded-lg border border-orange-200/80 dark:border-orange-900/50 bg-orange-50/90 dark:bg-orange-950/35 px-3 py-1.5 text-xs font-medium text-orange-900 dark:text-orange-200 hover:bg-orange-100/90 dark:hover:bg-orange-900/30 transition-colors"
        >
          <FireIcon class="w-4 h-4" />
          الخريطة الحرارية
        </RouterLink>
      </div>
    </section>

    <div v-if="loading && showChartsSection" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <div
        v-for="i in chartSkeletonCount"
        :key="'ch-' + i"
        class="h-[280px] rounded-xl border border-gray-200 dark:border-slate-700 bg-gray-100/80 dark:bg-slate-800/60 animate-pulse"
      />
    </div>
    <DashboardCharts
      v-else-if="showChartsSection"
      :revenue="chartRevenue"
      :work-orders="chartWo"
      :show-revenue-chart="tenantOpen('finance') && !platformExecutionPartner"
      :show-work-order-chart="tenantOpen('operations')"
    />

    <!-- KPI — من GET /dashboard/summary؛ الترتيب حسب نوع النشاط ثم الميزات المفعّلة -->
    <div v-if="visibleKpiItems.length || loading" class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
      <template v-if="loading">
        <div v-for="i in kpiSkeletonCount" :key="i" class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-slate-700 p-5">
          <SkeletonBox height="2.5rem" width="2.5rem" class="rounded-xl mb-3" />
          <SkeletonBox height="1.5rem" width="60%" class="mb-2" />
          <SkeletonBox height="0.75rem" width="80%" />
        </div>
      </template>
      <template v-else>
        <KpiCard
          v-for="row in visibleKpiItems"
          :key="row.id"
          :color="row.color"
          :icon="row.icon"
          :value="row.value"
          :label="row.label"
          :sub="row.sub"
        />
      </template>
    </div>

    <!-- Middle Row -->
    <div v-if="showRecentInvoicesPanel || showWorkOrdersPanel" :class="middleRowGridClass">
      <!-- Recent Invoices -->
      <div v-if="showRecentInvoicesPanel" class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-gray-800 dark:text-slate-100">أحدث الفواتير</h2>
          <RouterLink to="/invoices" class="text-xs text-primary-600 dark:text-primary-400 hover:underline">عرض الكل ←</RouterLink>
        </div>
        <div v-if="loading">
          <SkeletonTable :rows="4" />
        </div>
        <div v-else-if="recentInvoices.length === 0" class="py-12 px-4 text-center rounded-b-xl bg-gray-50/50 dark:bg-slate-900/40">
          <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-600 mb-3 shadow-sm">
            <DocumentTextIcon class="w-7 h-7 text-gray-300 dark:text-slate-500" />
          </div>
          <p class="text-sm font-medium text-gray-600 dark:text-slate-300">لا توجد فواتير في القائمة</p>
          <p class="text-xs text-gray-400 dark:text-slate-500 mt-1">ابدأ بإصدار فاتورة أو استيراد من أمر عمل</p>
          <RouterLink
            v-if="!platformExecutionPartner"
            to="/invoices/create"
            class="mt-4 inline-flex items-center gap-1.5 text-xs font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400"
          >
            + إنشاء فاتورة
          </RouterLink>
        </div>
        <table v-else class="w-full text-sm">
          <tbody class="divide-y divide-gray-50 dark:divide-slate-700/80">
            <tr
              v-for="inv in recentInvoices"
              :key="inv.id"
              class="hover:bg-primary-50/40 dark:hover:bg-primary-950/20 transition-colors cursor-pointer"
              @click="$router.push(`/invoices/${inv.id}`)"
            >
              <td class="px-5 py-2.5 font-medium text-gray-800 dark:text-slate-200 font-mono">{{ inv.invoice_number }}</td>
              <td class="px-5 py-2.5 text-gray-600 dark:text-slate-400 truncate max-w-[120px]">{{ inv.customer_name }}</td>
              <td class="px-5 py-2.5 text-primary-700 dark:text-primary-400 font-semibold">{{ fmtMoney(parseFloat(inv.total ?? 0)) }}</td>
              <td class="px-5 py-2.5">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                      :class="invoiceStatusClass(inv.status)"
                >
                  {{ invoiceStatusLabel(inv.status) }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Work Order Stats + Urgent -->
      <div v-if="showWorkOrdersPanel" class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-gray-800 dark:text-slate-100 flex items-center gap-2">
            <span class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
            حالة العمليات
          </h2>
          <RouterLink to="/work-orders" class="text-xs text-primary-600 dark:text-primary-400 hover:underline">عرض الكل ←</RouterLink>
        </div>
        <div v-if="loading" class="p-4"><SkeletonTable :rows="3" /></div>
        <div v-else class="p-4 space-y-3">
          <div v-for="stat in woStats" :key="stat.label"
               class="flex items-center gap-3 p-3 rounded-xl"
               :class="stat.bg"
          >
            <component :is="stat.icon" class="w-5 h-5 flex-shrink-0" :class="stat.color" />
            <div class="flex-1">
              <p class="text-sm font-medium text-gray-800 dark:text-slate-200">{{ stat.label }}</p>
              <p v-if="stat.hint" class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">{{ stat.hint }}</p>
            </div>
            <span class="text-2xl font-bold" :class="stat.color">{{ stat.value }}</span>
          </div>
          <div v-if="woStats.every(s => Number(s.value) === 0)" class="py-8 text-center text-gray-500 dark:text-slate-400">
            <CheckCircleIcon class="w-10 h-10 text-primary-200 dark:text-primary-900/60 mx-auto mb-2" />
            <p class="text-sm font-medium">لا عمليات جديدة في الفترة الحالية</p>
            <p class="text-xs mt-1 text-gray-400">عند تسجيل عمليات جديدة سيظهر التوزيع هنا (مركز خدمة أو منفذ بيع)</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Actions Bar -->
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 px-5 py-4">
      <p class="text-xs font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wide mb-3">وصول سريع</p>
      <div class="flex flex-wrap gap-2">
        <QuickBtn v-if="quickInvoiceCreate" :icon="DocumentTextIcon" label="فاتورة جديدة" to="/invoices/create" color="blue" />
        <QuickBtn :icon="MagnifyingGlassCircleIcon" label="تنفيذ العمليات" to="/execution-hub" color="blue" />
        <QuickBtn v-if="quickWorkOrders" :icon="ClipboardDocumentIcon" label="العمليات" to="/work-orders" color="purple" />
        <QuickBtn v-if="quickNewCustomer" :icon="UsersIcon" label="عميل جديد" to="/customers" color="green" />
        <RouterLink
          v-if="auth.isPlatform"
          :to="{ name: 'vehicles', query: { add: '1' } }"
          class="flex items-center gap-1.5 px-4 py-2 text-white text-sm font-medium rounded-lg transition-all shadow-sm hover:shadow active:scale-[0.97] bg-primary-600 hover:bg-primary-700"
        >
          <TruckIcon class="w-4 h-4" />
          <span>مركبة جديدة</span>
        </RouterLink>
        <QuickBtn
          v-if="showBiShortcuts"
          :icon="PresentationChartLineIcon"
          label="ذكاء الأعمال"
          to="/business-intelligence"
          color="indigo"
        />
        <QuickBtn
          v-if="showHeatmapShortcut"
          :icon="FireIcon"
          label="الخريطة الحرارية"
          to="/bays/heatmap"
          color="orange"
        />
        <QuickBtn v-if="quickReports" :icon="ChartBarIcon" label="التقارير" to="/reports" color="gray" />
        <QuickBtn v-if="quickZatca" :icon="ScaleIcon" label="ZATCA" to="/zatca" color="orange" />
        <QuickBtn v-if="auth.isStaff" :icon="MapPinIcon" label="خريطة الفروع" to="/branches/map" color="indigo" />
        <QuickBtn v-if="auth.isManager" :icon="BuildingLibraryIcon" label="الفروع" to="/branches" color="green" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
/* eslint-disable vue/one-component-per-file -- بطاقات KPI وأزرار سريعة داخلية للوحة */
import { ref, computed, defineComponent, h, onMounted, nextTick } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import {
  CheckCircleIcon, CurrencyDollarIcon, UsersIcon,
  DocumentTextIcon, ScaleIcon, ChartBarIcon,
  ClipboardDocumentIcon, ArrowPathIcon,
  ArrowTrendingUpIcon, ArrowTrendingDownIcon, PresentationChartLineIcon, TruckIcon,
  BuildingLibraryIcon, MapPinIcon, FireIcon, BanknotesIcon, UserPlusIcon, ChartPieIcon,
  ReceiptPercentIcon,
  MagnifyingGlassCircleIcon,
} from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'
import SkeletonBox from '@/components/SkeletonBox.vue'
import SkeletonTable from '@/components/SkeletonTable.vue'
import WeatherClock from '@/components/WeatherClock.vue'
import MotivationalQuotes from '@/components/MotivationalQuotes.vue'
import NavigationSourceHint from '@/components/NavigationSourceHint.vue'
import DashboardCharts from '@/components/dashboard/DashboardCharts.vue'
import SetupWelcomeModal from '@/components/onboarding/SetupWelcomeModal.vue'
import SetupChecklistCard from '@/components/onboarding/SetupChecklistCard.vue'
import { useSetupOnboarding } from '@/composables/useSetupOnboarding'
import { invoiceStatusClass, invoiceStatusLabel } from '@/utils/financialLabels'
import { featureFlags } from '@/config/featureFlags'
import { useBusinessProfileStore } from '@/stores/businessProfile'
import { canAccessStaffBusinessIntelligence, tenantSectionOpen } from '@/config/staffFeatureGate'
import { usePlatformExecutionPartner } from '@/composables/usePlatformExecutionPartner'

const $router = useRouter()
const auth = useAuthStore()
const biz = useBusinessProfileStore()
const { active: platformExecutionPartner } = usePlatformExecutionPartner()

const showBiShortcuts = computed(() => {
  void biz.loaded
  void biz.businessType
  void biz.effectiveFeatureMatrix
  if (platformExecutionPartner.value) return false
  return canAccessStaffBusinessIntelligence({
    buildFlagOn: featureFlags.intelligenceCommandCenter,
    isOwner: auth.isOwner,
    isEnabled: (k) => biz.isEnabled(k),
  })
})
const showHeatmapShortcut = computed(() => {
  void biz.loaded
  void biz.businessType
  void biz.effectiveFeatureMatrix
  return tenantSectionOpen(auth.isOwner, (k) => biz.isEnabled(k), 'operations')
})
const showAnalyticsStrip = computed(() => showBiShortcuts.value || showHeatmapShortcut.value)

function tenantOpen(key: string): boolean {
  void biz.loaded
  void biz.effectiveFeatureMatrix
  return tenantSectionOpen(auth.isOwner, (k) => biz.isEnabled(k), key)
}

/** ترتيب محاور لوحة التحكم حسب نوع النشاط */
const pillarPriority = computed((): Array<'operations' | 'finance' | 'crm' | 'accounting'> => {
  void biz.businessType
  switch (biz.businessType) {
    case 'retail':
      return ['finance', 'operations', 'crm', 'accounting']
    case 'fleet_operator':
      return ['operations', 'finance', 'crm', 'accounting']
    default:
      return ['operations', 'finance', 'crm', 'accounting']
  }
})

const {
  loading: setupLoading,
  loadError: setupLoadError,
  status: setupStatus,
  fetchStatus,
  welcomeSeen,
  showWelcomeModal,
  markWelcomeSeen,
  dismissChecklistForLater,
  stepsDone: setupStepsDone,
  totalSteps: setupTotalSteps,
  progressPercent: setupProgressPercent,
  shouldShowChecklist,
} = useSetupOnboarding()
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
  totalCollected: 0,
  collectionRate: 0,
  newCustomersInPeriod: 0,
  avgInvoiceValue: 0,
  woCreated: 0,
  woCompleted: 0,
  woCompletionRate: 0,
})
const recentInvoices = ref<any[]>([])
const chartRevenue = ref<{ date: string; revenue: number }[]>([])
const chartWo = ref<{ date: string; count: number }[]>([])

/** يوم تقويمي بالتوقيت المحلي — يطابق مفاتيح التاريخ من الخادم (مثل Asia/Riyadh) ولا يستخدم UTC من toISOString */
function localDateYmd(d: Date): string {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

function fmtMoney(v: number) {
  const n = Number.isFinite(v) ? v : 0
  return n.toLocaleString('ar-SA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

type DashPillar = 'operations' | 'finance' | 'crm' | 'accounting'

const visibleKpiItems = computed(() => {
  void biz.loaded
  void biz.effectiveFeatureMatrix
  void biz.businessType
  const defs: Array<{
    id: string
    pillar: DashPillar
    feature: string
    sortKey: number
    color: string
    icon: typeof ChartBarIcon
    value: string
    label: string
    sub: string
  }> = [
    {
      id: 'woNew7d',
      pillar: 'operations',
      feature: 'operations',
      sortKey: 2,
      color: 'blue',
      icon: ClipboardDocumentIcon,
      value: String(kpi.value.woCreated),
      label: 'أوامر عمل جديدة (آخر 7 أيام)',
      sub: 'عدد التسجيلات — يطابق عمود الرسم البياني',
    },
    {
      id: 'woDone7d',
      pillar: 'operations',
      feature: 'operations',
      sortKey: 3,
      color: 'teal',
      icon: CheckCircleIcon,
      value: String(kpi.value.woCompleted),
      label: 'أوامر مكتملة (آخر 7 أيام)',
      sub: 'حسب تاريخ إكمال الحالة',
    },
    {
      id: 'totalRevenue',
      pillar: 'finance',
      feature: 'finance',
      sortKey: 10,
      color: 'green',
      icon: ChartBarIcon,
      value: fmtMoney(kpi.value.totalRevenue),
      label: 'حجم الفواتير (الفترة)',
      sub: 'مجموع إجمالي الفواتير بحسب الإصدار — ليس المتحصّل النقدي',
    },
    {
      id: 'openInvoiceCount',
      pillar: 'finance',
      feature: 'finance',
      sortKey: 20,
      color: 'gray',
      icon: DocumentTextIcon,
      value: String(kpi.value.openInvoiceCount),
      label: 'فواتير مفتوحة / قيد التحصيل',
      sub: 'عدد',
    },
    {
      id: 'totalOutstanding',
      pillar: 'finance',
      feature: 'finance',
      sortKey: 30,
      color: 'orange',
      icon: ScaleIcon,
      value: fmtMoney(kpi.value.totalOutstanding),
      label: 'الذمم المدينة',
      sub: 'ر.س مستحقة',
    },
    {
      id: 'walletBalanceTotal',
      pillar: 'finance',
      feature: 'finance',
      sortKey: 40,
      color: 'purple',
      icon: CurrencyDollarIcon,
      value: fmtMoney(kpi.value.walletBalanceTotal),
      label: 'أرصدة المحافظ',
      sub: 'مجموع الأنواع',
    },
    {
      id: 'totalCollected',
      pillar: 'finance',
      feature: 'finance',
      sortKey: 50,
      color: 'blue',
      icon: BanknotesIcon,
      value: fmtMoney(kpi.value.totalCollected),
      label: 'المتحصّل (الفترة)',
      sub: 'مدفوعات مكتملة ضمن نطاق التقرير',
    },
    {
      id: 'collectionRate',
      pillar: 'finance',
      feature: 'finance',
      sortKey: 60,
      color: 'indigo',
      icon: ChartPieIcon,
      value: `${kpi.value.collectionRate}%`,
      label: 'معدل التحصيل',
      sub: 'نسبة المتحصّل إلى حجم الفواتير في الفترة',
    },
    {
      id: 'avgInvoiceValue',
      pillar: 'finance',
      feature: 'finance',
      sortKey: 70,
      color: 'gray',
      icon: ReceiptPercentIcon,
      value: fmtMoney(kpi.value.avgInvoiceValue),
      label: 'متوسط قيمة الفاتورة',
      sub: 'للفواتير الصادرة (غير الملغاة/المسودة)',
    },
    {
      id: 'newCustomersInPeriod',
      pillar: 'crm',
      feature: 'crm',
      sortKey: 15,
      color: 'purple',
      icon: UserPlusIcon,
      value: String(kpi.value.newCustomersInPeriod),
      label: 'عملاء جدد',
      sub: 'خلال نفس فترة التقرير',
    },
  ]

  const pri = pillarPriority.value
  return defs
    .filter((d) => {
      if ((d.id === 'woNew7d' || d.id === 'woDone7d') && !platformExecutionPartner.value) {
        return false
      }
      if (platformExecutionPartner.value && (d.pillar === 'finance' || d.pillar === 'crm')) {
        return false
      }
      return tenantOpen(d.feature)
    })
    .sort((a, b) => {
      const ia = pri.indexOf(a.pillar)
      const ib = pri.indexOf(b.pillar)
      if (ia !== ib) return ia - ib
      return a.sortKey - b.sortKey
    })
})

const showChartsSection = computed(() => {
  if (platformExecutionPartner.value) {
    return tenantOpen('operations')
  }
  return tenantOpen('finance') || tenantOpen('operations')
})

const chartSkeletonCount = computed(() => {
  let n = 0
  if (tenantOpen('finance') && !platformExecutionPartner.value) n++
  if (tenantOpen('operations')) n++
  return Math.max(n, 1)
})

const kpiSkeletonCount = computed(() => Math.min(8, Math.max(visibleKpiItems.value.length || 4, 4)))

const showRecentInvoicesPanel = computed(() => tenantOpen('finance') && !platformExecutionPartner.value)
const showWorkOrdersPanel = computed(() => tenantOpen('operations'))

const middleRowGridClass = computed(() => {
  if (showRecentInvoicesPanel.value && showWorkOrdersPanel.value) {
    return 'grid grid-cols-1 lg:grid-cols-2 gap-4'
  }
  return 'grid grid-cols-1 gap-4'
})

const quickInvoiceCreate = computed(() => tenantOpen('finance') && !platformExecutionPartner.value)
const quickWorkOrders = computed(() => tenantOpen('operations'))
const quickNewCustomer = computed(() => tenantOpen('crm') && !platformExecutionPartner.value)
const quickReports = computed(() => tenantOpen('reports') && !platformExecutionPartner.value)
const quickZatca = computed(() => tenantOpen('accounting') && !platformExecutionPartner.value)

const woStats = computed(() => [
  {
    label: 'أوامر عمل جديدة',
    hint: 'آخر 7 أيام (نفس نطاق الرسم البياني)',
    value: kpi.value.woCreated,
    icon: ClipboardDocumentIcon,
    color: 'text-blue-600 dark:text-blue-400',
    bg: 'bg-blue-50 dark:bg-blue-950/35',
  },
  {
    label: 'مكتملة',
    hint: `${kpi.value.woCompletionRate}% من أوامر آخر 7 أيام`,
    value: kpi.value.woCompleted,
    icon: CheckCircleIcon,
    color: 'text-primary-600 dark:text-primary-400',
    bg: 'bg-primary-50 dark:bg-primary-950/35',
  },
])

function fillChartsFromApi(d: Record<string, unknown> | null) {
  const revIn = Array.isArray(d?.charts && (d.charts as any).revenue_last_7_days)
    ? (d!.charts as any).revenue_last_7_days
    : []
  const woIn = Array.isArray(d?.charts && (d.charts as any).work_orders_last_7_days)
    ? (d!.charts as any).work_orders_last_7_days
    : []
  const rev: { date: string; revenue: number }[] = []
  const wo: { date: string; count: number }[] = []
  for (let i = 6; i >= 0; i--) {
    const x = new Date()
    x.setDate(x.getDate() - i)
    const ds = localDateYmd(x)
    const rRow = revIn.find((r: any) => r?.date === ds)
    const wRow = woIn.find((r: any) => r?.date === ds)
    rev.push({ date: ds, revenue: rRow != null ? Number(rRow.revenue) : 0 })
    wo.push({ date: ds, count: wRow != null ? Number(wRow.count) : 0 })
  }
  chartRevenue.value = rev
  chartWo.value = wo
}

async function loadData(opts?: { refresh?: boolean }) {
  loading.value = true
  try {
    const summaryParams: Record<string, string> = {}
    if (opts?.refresh) {
      summaryParams.refresh = '1'
    }
    const summaryReq = apiClient.get('/dashboard/summary', { params: summaryParams })
    const invReq = platformExecutionPartner.value
      ? Promise.resolve({ data: {} } as Awaited<ReturnType<typeof apiClient.get>>)
      : apiClient.get('/invoices', { params: { per_page: 5 } })
    const [sumRes, invRes] = await Promise.allSettled([summaryReq, invReq])
    if (sumRes.status === 'fulfilled') {
      const d = sumRes.value.data?.data
      if (d) {
        kpi.value.totalRevenue = Number(d.sales?.total_revenue ?? 0)
        kpi.value.totalCollected = Number(d.sales?.total_collected ?? 0)
        kpi.value.collectionRate = Number(d.sales?.collection_rate ?? 0)
        kpi.value.newCustomersInPeriod = Number(d.customers?.new_in_period ?? 0)
        kpi.value.avgInvoiceValue = Number(d.sales?.avg_invoice_value ?? 0)
        kpi.value.openInvoiceCount = Number(d.receivables?.open_invoice_count ?? 0)
        kpi.value.totalOutstanding = Number(d.receivables?.total_outstanding ?? 0)
        const bal = d.wallets?.balance_by_type ?? {}
        kpi.value.walletBalanceTotal = Object.values(bal).reduce((s: number, v) => s + Number(v ?? 0), 0)
        kpi.value.woCreated = Number(
          d.work_orders?.created_last_7_days ?? d.work_orders?.created_in_period ?? 0,
        )
        kpi.value.woCompleted = Number(
          d.work_orders?.completed_last_7_days ?? d.work_orders?.completed_in_period ?? 0,
        )
        kpi.value.woCompletionRate = Number(
          d.work_orders?.completion_rate_last_7_days ?? d.work_orders?.completion_rate ?? 0,
        )
        fillChartsFromApi(d as Record<string, unknown>)
      } else {
        fillChartsFromApi(null)
      }
    } else {
      fillChartsFromApi(null)
    }
    if (!platformExecutionPartner.value && invRes.status === 'fulfilled') {
      const raw = invRes.value.data?.data
      const items = Array.isArray(raw) ? raw : raw?.data ?? []
      recentInvoices.value = items.slice(0, 5)
    } else if (platformExecutionPartner.value) {
      recentInvoices.value = []
    }
  } catch {
    fillChartsFromApi(null)
  } finally {
    loading.value = false
    if (auth.isManager && auth.user?.company_id) {
      fetchStatus().catch(() => {})
    }
  }
}

const colorMap: Record<string, Record<string, string>> = {
  red:    { bg: 'bg-red-50',    icon: 'text-red-500',    val: 'text-red-700' },
  green:  { bg: 'bg-primary-50 dark:bg-primary-950/25', icon: 'text-primary-600 dark:text-primary-400', val: 'text-primary-700 dark:text-primary-300' },
  blue:   { bg: 'bg-blue-50',   icon: 'text-blue-600',   val: 'text-blue-700' },
  purple: { bg: 'bg-primary-50 dark:bg-primary-950/25', icon: 'text-primary-600 dark:text-primary-400', val: 'text-primary-700 dark:text-primary-300' },
  gray:   { bg: 'bg-gray-50',   icon: 'text-gray-500',   val: 'text-gray-700' },
  orange: { bg: 'bg-orange-50', icon: 'text-orange-500', val: 'text-orange-700' },
  teal:   { bg: 'bg-primary-50 dark:bg-primary-950/25', icon: 'text-primary-600 dark:text-primary-400', val: 'text-primary-700 dark:text-primary-300' },
  indigo: { bg: 'bg-indigo-50', icon: 'text-indigo-600', val: 'text-indigo-700' },
}

const KpiCard = defineComponent({
  props: { color: String, icon: Object, value: String, label: String, sub: String, trend: String },
  setup(p) {
    return () => {
      const c = colorMap[p.color ?? 'gray']
      const trendEl = p.trend
        ? h(p.trend === 'up' ? ArrowTrendingUpIcon : ArrowTrendingDownIcon, {
            class: `w-3.5 h-3.5 ${p.trend === 'up' ? 'text-primary-500' : 'text-red-400'}`
          })
        : null
      return h('div', { class: 'bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-slate-700 p-5 flex items-start gap-4 hover:shadow-sm transition-shadow' }, [
        h('div', { class: `w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 ${c.bg}` },
          [h(p.icon as any, { class: `w-5 h-5 ${c.icon}` })]),
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
      blue:   'bg-blue-600 hover:bg-blue-700',   purple: 'bg-primary-600 hover:bg-primary-700',
      green:  'bg-primary-600 hover:bg-primary-700',
      teal:   'bg-primary-600 hover:bg-primary-700',
      gray:   'bg-gray-600 hover:bg-gray-700',   orange: 'bg-orange-500 hover:bg-orange-600',
      indigo: 'bg-indigo-600 hover:bg-indigo-700',
      cyan:   'bg-primary-600 hover:bg-primary-700',
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

function onSetupWelcomeStart() {
  markWelcomeSeen()
  nextTick(() => {
    document.getElementById('setup-checklist-anchor')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  })
}

function onSetupWelcomeLater() {
  markWelcomeSeen()
}

onMounted(async () => {
  if (auth.isStaff && auth.user?.company_id) {
    biz.load().catch(() => {})
  }
  await loadData()
  if (auth.isManager && auth.user?.company_id && !welcomeSeen.value) {
    showWelcomeModal.value = true
  }
})
</script>
