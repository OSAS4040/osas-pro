<template>
  <section class="app-shell-page space-y-6" dir="rtl">
    <nav class="flex flex-wrap items-center gap-1 text-[11px] text-slate-500 dark:text-slate-400" aria-label="breadcrumb">
      <RouterLink to="/platform/overview" class="font-semibold text-primary-700 hover:underline dark:text-primary-400">إدارة المنصة</RouterLink>
      <span>/</span>
      <RouterLink to="/platform/companies" class="font-semibold text-primary-700 hover:underline dark:text-primary-400">الشركات</RouterLink>
      <span>/</span>
      <span class="font-semibold text-slate-700 dark:text-slate-200">{{ companyName }}</span>
      <span>/</span>
      <span class="font-semibold text-slate-700 dark:text-slate-200">{{ activeTabLabel }}</span>
    </nav>

    <CompanyHeader
      :name="companyName"
      :status-label="statusLabel"
      :status-class="statusClass"
      :plan-label="planLabel"
      :risk-label="riskLabel"
      :risk-class="riskClass"
      :quick-indicator="quickIndicator"
    />

    <CompanyKpiStrip :items="kpiStripItems" />

    <CompanyTabs :tabs="tabs" :active-tab="activeTab" @update:active-tab="activeTab = $event" />

    <PlatformAdminInPageNav
      v-if="company && !loading && !error && detailInPageNavItems.length > 0"
      aria-label="فهرس أقسام تبويب الشركة"
      section-hint="انتقال سريع داخل التبويب الحالي"
      :items="detailInPageNavItems"
    />

    <div v-if="loading" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <div v-for="sk in 4" :key="'co-detail-sk-'+sk" class="h-20 animate-pulse rounded-xl bg-slate-200/80 dark:bg-slate-700/70" />
    </div>

    <div v-else-if="error" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">
      {{ error }}
    </div>

    <template v-else-if="company">
      <div v-if="activeTab === 'overview'" class="grid gap-4 lg:grid-cols-2">
        <CompanySection
          title="Overview"
          subtitle="ملخص سريع + Why Engine + التنبيهات"
          section-id="platform-company-overview-summary"
        >
          <div class="space-y-2 text-sm">
            <p class="rounded-lg bg-slate-50 p-2 text-slate-700 dark:bg-slate-800/60 dark:text-slate-200">
              {{ whyInsights.primary }}
            </p>
            <ul class="space-y-1 text-xs text-slate-600 dark:text-slate-300">
              <li v-for="(signal, idx) in whyInsights.signals" :key="`sig-${idx}`">- {{ signal }}</li>
            </ul>
          </div>
        </CompanySection>
        <CompanySection title="Alerts" subtitle="إشارات فورية تتطلب متابعة" section-id="platform-company-overview-alerts">
          <ul class="space-y-2 text-xs">
            <li v-for="(alert, idx) in alerts" :key="`alert-${idx}`" class="rounded-lg border border-amber-200 bg-amber-50 px-2 py-1.5 text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200">
              {{ alert }}
            </li>
          </ul>
        </CompanySection>
        <CompanySection
          title="نشاط الشركة"
          subtitle="آخر تحديثات التشغيل والاستخدام"
          section-id="platform-company-overview-activity"
        >
          <div class="space-y-2 text-xs text-slate-700 dark:text-slate-300">
            <p>حالة النشاط: <span class="font-bold">{{ activityLabel }}</span></p>
            <p>آخر تحديث: <span class="font-bold">{{ lastUpdatedLabel }}</span></p>
            <p>الحالة التشغيلية: <span class="font-bold">{{ statusLabel }}</span></p>
          </div>
        </CompanySection>
      </div>

      <div v-else-if="activeTab === 'finance'" class="space-y-4">
        <CompanySection
          title="Finance"
          subtitle="مؤشرات الفواتير والمدفوع والمتأخر (بدون Ledger خام)"
          section-id="platform-company-finance-kpis"
        >
          <CompanyKpiStrip :items="financeKpis" />
        </CompanySection>
        <CompanySection
          title="جدول الفواتير"
          subtitle="قراءة تشغيلية للفواتير المرتبطة بالشركة"
          section-id="platform-company-finance-table"
        >
          <CompanyDataTable :columns="invoiceColumns" :rows="invoiceRows" :loading="financeLoading" empty-label="لا توجد فواتير متاحة لهذا المستوى." />
        </CompanySection>
      </div>

      <div v-else-if="activeTab === 'customers'" class="space-y-4">
        <CompanySection
          title="Customers"
          subtitle="قائمة العملاء ونشاطهم داخل الشركة"
          section-id="platform-company-customers-table"
        >
          <CompanyDataTable :columns="customerColumns" :rows="customerRows" :loading="customersLoading" empty-label="لا يوجد عملاء للعرض." />
        </CompanySection>
      </div>

      <div v-else-if="activeTab === 'vehicles'" class="space-y-4">
        <CompanySection
          title="Vehicles"
          subtitle="حالة المركبات والارتباط التشغيلي"
          section-id="platform-company-vehicles-table"
        >
          <CompanyDataTable :columns="vehicleColumns" :rows="vehicleRows" :loading="vehiclesLoading" empty-label="لا توجد مركبات متاحة أو لا توجد صلاحية لهذا المصدر." />
        </CompanySection>
      </div>

      <div v-else-if="activeTab === 'invoices'" class="space-y-4">
        <CompanySection
          title="Invoices"
          subtitle="جدول الفواتير بالحالة والقيمة والتاريخ"
          section-id="platform-company-invoices-table"
        >
          <CompanyDataTable :columns="invoiceColumns" :rows="invoiceRows" :loading="financeLoading" empty-label="لا توجد فواتير متاحة." />
        </CompanySection>
      </div>

      <div v-else>
        <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-300">
          التبويب غير معروف.
        </div>
      </div>
    </template>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import apiClient from '@/lib/apiClient'
import PlatformAdminInPageNav from '@/components/platform-admin/PlatformAdminInPageNav.vue'
import CompanyHeader from '@/components/platform-admin/company-detail/CompanyHeader.vue'
import CompanyKpiStrip from '@/components/platform-admin/company-detail/CompanyKpiStrip.vue'
import CompanyTabs from '@/components/platform-admin/company-detail/CompanyTabs.vue'
import CompanySection from '@/components/platform-admin/company-detail/CompanySection.vue'
import CompanyDataTable from '@/components/platform-admin/company-detail/CompanyDataTable.vue'
import {
  platformCompanyDetailInPageNavByTab,
  type PlatformCompanyDetailTabId,
} from '@/config/platformCompanyDetailInPageNav'

const route = useRoute()
const loading = ref(false)
const error = ref('')
const company = ref<any | null>(null)
const activeTab = ref('overview')
const customersLoading = ref(false)
const vehiclesLoading = ref(false)
const financeLoading = ref(false)
const customerRows = ref<Record<string, unknown>[]>([])
const vehicleRows = ref<Record<string, unknown>[]>([])
const invoiceRows = ref<Record<string, unknown>[]>([])

const tabs = [
  { id: 'overview', label: 'Overview' },
  { id: 'finance', label: 'Finance' },
  { id: 'customers', label: 'Customers' },
  { id: 'vehicles', label: 'Vehicles' },
  { id: 'invoices', label: 'Invoices' },
]
const activeTabLabel = computed(() => tabs.find((t) => t.id === activeTab.value)?.label ?? 'Overview')

const detailInPageNavItems = computed(() => {
  const tab = activeTab.value as PlatformCompanyDetailTabId
  return platformCompanyDetailInPageNavByTab[tab] ?? []
})

const companyName = computed(() => company.value?.name || `شركة #${String(route.params.id ?? '')}`)
const planLabel = computed(() => String(company.value?.subscription?.plan ?? company.value?.plan_catalog_match?.slug ?? company.value?.plan_slug ?? '—'))
const monthlyRevenue = computed(() => formatCurrency(Number(company.value?.monthly_revenue) || 0))
const statusLabel = computed(() => {
  if (company.value?.company_status === 'suspended') return 'موقوفة'
  if (company.value?.is_active === false) return 'غير مفعّلة'
  return 'نشطة'
})
const statusClass = computed(() => {
  if (company.value?.company_status === 'suspended') return 'bg-rose-100 text-rose-900 dark:bg-rose-950/40 dark:text-rose-200'
  if (company.value?.is_active === false) return 'bg-amber-100 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200'
  return 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200'
})
const activityLabel = computed(() => {
  const d = company.value?.updated_at ? new Date(company.value.updated_at) : null
  if (!d || Number.isNaN(d.getTime())) return 'غير متاح'
  const days = Math.floor((Date.now() - d.getTime()) / 86400000)
  if (days <= 3) return 'نشاط مرتفع'
  if (days <= 10) return 'نشاط متوسط'
  return 'نشاط منخفض'
})
const lastUpdatedLabel = computed(() => formatDate(company.value?.updated_at))
const riskLabel = computed(() => {
  if (company.value?.company_status === 'suspended') return 'عالي'
  if (activityLabel.value === 'نشاط منخفض') return 'متوسط'
  return 'منخفض'
})
const riskClass = computed(() => {
  if (riskLabel.value === 'عالي') return 'bg-rose-100 text-rose-900 dark:bg-rose-950/40 dark:text-rose-200'
  if (riskLabel.value === 'متوسط') return 'bg-amber-100 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200'
  return 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200'
})
const quickIndicator = computed(() => `${monthlyRevenue.value} • ${activityLabel.value}`)

const invoiceTotals = computed(() => {
  const total = invoiceRows.value.length
  const paid = invoiceRows.value.filter((r) => String(r.status ?? '').includes('مدفوع')).length
  const overdue = invoiceRows.value.filter((r) => String(r.status ?? '').includes('متأخر')).length
  return { total, paid, overdue }
})
const kpiStripItems = computed(() => [
  { key: 'invoices-total', label: 'إجمالي الفواتير', value: invoiceTotals.value.total.toLocaleString('ar-SA') },
  { key: 'invoices-paid', label: 'المدفوع', value: invoiceTotals.value.paid.toLocaleString('ar-SA') },
  { key: 'invoices-overdue', label: 'المتأخر', value: invoiceTotals.value.overdue.toLocaleString('ar-SA') },
  { key: 'customers', label: 'عدد العملاء', value: customerRows.value.length.toLocaleString('ar-SA') },
  { key: 'vehicles', label: 'عدد المركبات', value: vehicleRows.value.length.toLocaleString('ar-SA') },
  { key: 'activity', label: 'النشاط', value: activityLabel.value },
])
const financeKpis = computed(() => [
  { key: 'mrr', label: 'الإيراد الشهري', value: monthlyRevenue.value },
  { key: 'inv-total', label: 'إجمالي الفواتير', value: invoiceTotals.value.total.toLocaleString('ar-SA') },
  { key: 'inv-paid', label: 'المدفوع', value: invoiceTotals.value.paid.toLocaleString('ar-SA') },
  { key: 'inv-overdue', label: 'المتأخر', value: invoiceTotals.value.overdue.toLocaleString('ar-SA') },
])

const whyInsights = computed(() => {
  const signals: string[] = []
  if (riskLabel.value === 'عالي') signals.push('الحالة التشغيلية تشير إلى مستوى خطر مرتفع.')
  if (activityLabel.value === 'نشاط منخفض') signals.push('انخفاض النشاط الحديث يزيد احتمالية التعثر.')
  if (invoiceTotals.value.overdue > 0) signals.push(`وجود ${invoiceTotals.value.overdue.toLocaleString('ar-SA')} فاتورة متأخرة.`)
  if (signals.length === 0) signals.push('لا توجد إشارات حرجة حالياً، الشركة ضمن نطاق مستقر.')
  return {
    primary: signals[0],
    signals,
  }
})

const alerts = computed(() => {
  const list: string[] = []
  if (invoiceTotals.value.overdue > 0) list.push('يوجد فواتير متأخرة تحتاج متابعة.')
  if (activityLabel.value === 'نشاط منخفض') list.push('النشاط منخفض مقارنة بالحد المتوقع.')
  if (riskLabel.value === 'عالي') list.push('الشركة ضمن شريحة المخاطر العالية.')
  if (list.length === 0) list.push('لا توجد تنبيهات حرجة حالياً.')
  return list
})

const customerColumns = [
  { key: 'name', label: 'العميل' },
  { key: 'status', label: 'الحالة' },
  { key: 'invoices_count', label: 'عدد الفواتير' },
  { key: 'last_activity', label: 'آخر نشاط' },
]
const vehicleColumns = [
  { key: 'vehicle', label: 'المركبة' },
  { key: 'status', label: 'الحالة' },
  { key: 'invoice_link', label: 'الارتباط بالفواتير' },
  { key: 'updated_at', label: 'آخر تحديث' },
]
const invoiceColumns = [
  { key: 'invoice_no', label: 'رقم الفاتورة' },
  { key: 'status', label: 'الحالة' },
  { key: 'amount', label: 'القيمة' },
  { key: 'issued_at', label: 'التاريخ' },
]

function formatCurrency(v: number): string {
  return new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR', maximumFractionDigits: 0 }).format(v || 0)
}

function formatDate(value: unknown): string {
  if (!value) return '—'
  const d = new Date(String(value))
  if (Number.isNaN(d.getTime())) return '—'
  return d.toLocaleDateString('ar-SA', { dateStyle: 'medium' })
}

async function loadCompany(): Promise<void> {
  const id = String(route.params.id ?? '').trim()
  if (!id) return
  loading.value = true
  error.value = ''
  try {
    const { data } = await apiClient.get(`/platform/companies/${id}`, { skipGlobalErrorToast: true })
    const payload = data?.data ?? {}
    const baseCompany = payload?.company ?? payload ?? {}
    company.value = {
      ...baseCompany,
      subscription: payload?.subscription ?? null,
      plan_catalog_match: payload?.plan_catalog_match ?? null,
    }
  } catch {
    company.value = null
    error.value = 'تعذر تحميل ملف الشركة ضمن سياق المنصة.'
  } finally {
    loading.value = false
  }
}

async function loadCustomers(): Promise<void> {
  const id = Number(route.params.id)
  if (!Number.isFinite(id)) return
  customersLoading.value = true
  try {
    const { data } = await apiClient.get('/platform/customers', { params: { company_id: id, per_page: 10 }, skipGlobalErrorToast: true })
    const rows = Array.isArray(data?.data) ? data.data : []
    customerRows.value = rows.map((r: any) => ({
      name: { label: r.name ?? r.name_ar ?? `عميل #${r.id ?? ''}`, to: '/customers' },
      status: r.is_active === false ? 'غير نشط' : 'نشط',
      invoices_count: r.invoices_count ?? 0,
      last_activity: formatDate(r.updated_at),
    }))
  } catch {
    customerRows.value = []
  } finally {
    customersLoading.value = false
  }
}

async function loadInvoices(): Promise<void> {
  const id = Number(route.params.id)
  if (!Number.isFinite(id)) return
  financeLoading.value = true
  try {
    const { data } = await apiClient.get('/invoices', { params: { company_id: id, per_page: 10 }, skipGlobalErrorToast: true })
    const rows = Array.isArray(data?.data) ? data.data : []
    invoiceRows.value = rows.map((r: any) => ({
      invoice_no: { label: r.invoice_no ?? r.code ?? `INV-${r.id ?? ''}`, to: r.id ? `/invoices/${r.id}` : '/invoices', external: true },
      status: String(r.status ?? '').toLowerCase() === 'paid' ? 'مدفوع' : String(r.status ?? '').toLowerCase() === 'overdue' ? 'متأخر' : 'قيد المعالجة',
      amount: formatCurrency(Number(r.total ?? r.amount ?? 0)),
      issued_at: formatDate(r.issued_at ?? r.created_at),
    }))
  } catch {
    invoiceRows.value = []
  } finally {
    financeLoading.value = false
  }
}

async function loadVehicles(): Promise<void> {
  const id = Number(route.params.id)
  if (!Number.isFinite(id)) return
  vehiclesLoading.value = true
  try {
    const { data } = await apiClient.get('/vehicles', { params: { company_id: id, per_page: 10 }, skipGlobalErrorToast: true })
    const rows = Array.isArray(data?.data) ? data.data : []
    vehicleRows.value = rows.map((r: any) => ({
      vehicle: { label: r.plate_no ?? r.name ?? `مركبة #${r.id ?? ''}`, to: r.id ? `/vehicles/${r.id}` : '/vehicles', external: true },
      status: r.status ?? 'نشطة',
      invoice_link: r.latest_invoice_id ? { label: `فاتورة #${r.latest_invoice_id}`, to: `/invoices/${r.latest_invoice_id}`, external: true } : '—',
      updated_at: formatDate(r.updated_at),
    }))
  } catch {
    vehicleRows.value = []
  } finally {
    vehiclesLoading.value = false
  }
}

async function loadAll(): Promise<void> {
  await loadCompany()
  await Promise.all([loadCustomers(), loadInvoices(), loadVehicles()])
}

onMounted(() => { void loadAll() })
watch(() => route.params.id, () => { activeTab.value = 'overview'; void loadAll() })
</script>
