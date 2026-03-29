<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">التقارير</h1>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">تحليل شامل لأداء الأعمال</p>
      </div>
      <div class="flex gap-2 flex-wrap">
        <button @click="exportCSV"   class="px-3 py-2 text-sm border border-gray-300 dark:border-slate-600 dark:text-white rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700">⬇ CSV</button>
        <button @click="exportExcel" class="px-3 py-2 text-sm border border-emerald-500 text-emerald-700 dark:text-emerald-400 rounded-lg hover:bg-emerald-50">📊 Excel</button>
        <button @click="exportPDF"   class="px-3 py-2 text-sm border border-red-400 text-red-600 rounded-lg hover:bg-red-50">📄 PDF</button>
        <button @click="loadAll" :disabled="loading" class="btn btn-primary">
          <span v-if="loading">جاري...</span>
          <span v-else>تحديث</span>
        </button>
      </div>
    </div>

    <!-- Date Filter -->
    <div class="card p-4">
      <div class="flex flex-wrap gap-3 items-end">
        <div>
          <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">من تاريخ</label>
          <input type="date" v-model="from" class="px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm dark:bg-slate-800 dark:text-white" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">إلى تاريخ</label>
          <input type="date" v-model="to" class="px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm dark:bg-slate-800 dark:text-white" />
        </div>
        <button @click="loadAll" class="btn btn-primary text-sm">تطبيق</button>
      </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200 dark:border-slate-700">
      <nav class="flex gap-1 overflow-x-auto">
        <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
          :class="['px-4 py-2.5 text-sm font-medium rounded-t-lg transition whitespace-nowrap',
            activeTab === tab.key
              ? 'border-b-2 border-primary-500 text-primary-600 dark:text-primary-400'
              : 'text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-300']">
          {{ tab.label }}
        </button>
      </nav>
    </div>

    <!-- KPI Tab -->
    <div v-if="activeTab === 'kpi'">
      <div v-if="kpiLoading" class="flex justify-center py-12">
        <div class="w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
      </div>
      <div v-else-if="kpi" class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <KpiCard title="إجمالي المبيعات"     :value="fmt(kpi.total_sales)"        icon="💰" color="blue" />
        <KpiCard title="عدد الفواتير"         :value="String(kpi.invoice_count||0)" icon="📄" color="indigo" />
        <KpiCard title="أوامر العمل"          :value="String(kpi.work_order_count||0)" icon="🔧" color="orange" />
        <KpiCard title="متوسط الفاتورة"       :value="fmt(kpi.avg_invoice_value)"  icon="📊" color="green" />
        <KpiCard title="إجمالي الضريبة"       :value="fmt(kpi.total_vat)"          icon="🏛" color="purple" />
        <KpiCard title="المدفوعات المستلمة"   :value="fmt(kpi.total_paid)"         icon="✅" color="green" />
        <KpiCard title="المبالغ المستحقة"     :value="fmt(kpi.total_due)"          icon="⚠️" color="red" />
        <KpiCard title="عملاء جدد"            :value="String(kpi.new_customers||0)" icon="👥" color="teal" />
      </div>
      <div v-else class="text-center py-12 text-gray-400">لا توجد بيانات</div>
    </div>

    <!-- Sales Tab -->
    <div v-if="activeTab === 'sales'" class="space-y-4">
      <div v-if="salesLoading" class="flex justify-center py-12">
        <div class="w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
      </div>
      <template v-else>
        <!-- Bar Chart -->
        <div v-if="sales.byBranch && sales.byBranch.length" class="card p-4">
          <h3 class="font-semibold text-gray-800 dark:text-white mb-4">المبيعات حسب الفرع</h3>
          <div class="space-y-3 mb-4">
            <div v-for="b in sales.byBranch" :key="b.branch_id" class="flex items-center gap-3">
              <div class="w-24 text-xs text-gray-600 dark:text-slate-400 text-right truncate">{{ b.branch?.name ?? 'رئيسي' }}</div>
              <div class="flex-1 h-5 bg-gray-100 dark:bg-slate-700 rounded-full overflow-hidden">
                <div class="h-full bg-primary-500 rounded-full transition-all duration-700"
                  :style="{ width: maxSales ? (Number(b.total_sales)/maxSales*100)+'%' : '0%' }"></div>
              </div>
              <div class="w-28 text-xs font-semibold text-gray-700 dark:text-slate-300 text-left">{{ fmt(b.total_sales) }}</div>
            </div>
          </div>
          <table class="w-full text-sm">
            <thead>
              <tr class="text-right border-b dark:border-slate-700">
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">الفرع</th>
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">الفواتير</th>
                <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">المبيعات</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="b in sales.byBranch" :key="b.branch_id" class="border-b dark:border-slate-700/50">
                <td class="py-2">{{ b.branch?.name ?? 'رئيسي' }}</td>
                <td class="py-2">{{ b.invoice_count }}</td>
                <td class="py-2 font-medium text-primary-600">{{ fmt(b.total_sales) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="sales.summary" class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <KpiCard title="إجمالي المبيعات" :value="fmt(sales.summary.total_sales)"   icon="💰" color="blue" />
          <KpiCard title="إجمالي الضريبة"  :value="fmt(sales.summary.total_vat)"     icon="🏛" color="purple" />
          <KpiCard title="الخصومات"        :value="fmt(sales.summary.total_discount)" icon="🏷" color="orange" />
          <KpiCard title="عدد الفواتير"    :value="String(sales.summary.count||0)"   icon="📄" color="indigo" />
        </div>
      </template>
    </div>

    <!-- By Customer Tab -->
    <div v-if="activeTab === 'by_customer'" class="card p-4">
      <div v-if="custLoading" class="flex justify-center py-8">
        <div class="w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
      </div>
      <template v-else>
        <h3 class="font-semibold text-gray-800 dark:text-white mb-4">مبيعات حسب العميل</h3>
        <!-- Bar chart -->
        <div class="space-y-2 mb-4">
          <div v-for="r in byCustomer.slice(0,8)" :key="r.customer_id" class="flex items-center gap-3">
            <div class="w-32 text-xs truncate text-gray-600 dark:text-slate-400">{{ r.customer?.name ?? 'غير محدد' }}</div>
            <div class="flex-1 h-4 bg-gray-100 dark:bg-slate-700 rounded-full overflow-hidden">
              <div class="h-full bg-emerald-500 rounded-full"
                :style="{ width: maxCustomerSales ? (Number(r.total_sales)/maxCustomerSales*100)+'%' : '0%' }"></div>
            </div>
            <div class="w-24 text-xs font-semibold text-left">{{ fmt(r.total_sales) }}</div>
          </div>
        </div>
        <table class="w-full text-sm">
          <thead>
            <tr class="text-right border-b dark:border-slate-700">
              <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">العميل</th>
              <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">المبيعات</th>
              <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">الفواتير</th>
              <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">المستحق</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in byCustomer" :key="r.customer_id" class="border-b dark:border-slate-700/50">
              <td class="py-2">{{ r.customer?.name ?? 'غير محدد' }}</td>
              <td class="py-2 font-medium text-primary-600">{{ fmt(r.total_sales) }}</td>
              <td class="py-2">{{ r.invoice_count }}</td>
              <td class="py-2 text-red-500">{{ fmt(r.total_due) }}</td>
            </tr>
          </tbody>
        </table>
      </template>
    </div>

    <!-- By Product Tab -->
    <div v-if="activeTab === 'by_product'" class="card p-4">
      <div v-if="prodLoading" class="flex justify-center py-8">
        <div class="w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
      </div>
      <template v-else>
        <h3 class="font-semibold text-gray-800 dark:text-white mb-4">مبيعات حسب المنتج / الخدمة</h3>
        <div class="space-y-2 mb-4">
          <div v-for="r in byProduct.slice(0,8)" :key="r.product_name" class="flex items-center gap-3">
            <div class="w-32 text-xs truncate text-gray-600 dark:text-slate-400">{{ r.product_name }}</div>
            <div class="flex-1 h-4 bg-gray-100 dark:bg-slate-700 rounded-full overflow-hidden">
              <div class="h-full bg-amber-500 rounded-full"
                :style="{ width: maxProductSales ? (Number(r.total_revenue)/maxProductSales*100)+'%' : '0%' }"></div>
            </div>
            <div class="w-24 text-xs font-semibold text-left">{{ fmt(r.total_revenue) }}</div>
          </div>
        </div>
        <table class="w-full text-sm">
          <thead>
            <tr class="text-right border-b dark:border-slate-700">
              <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">المنتج/الخدمة</th>
              <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">الكمية</th>
              <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">الإيراد</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in byProduct" :key="r.product_name" class="border-b dark:border-slate-700/50">
              <td class="py-2">{{ r.product_name }}</td>
              <td class="py-2">{{ r.total_quantity }}</td>
              <td class="py-2 font-medium text-primary-600">{{ fmt(r.total_revenue) }}</td>
            </tr>
          </tbody>
        </table>
      </template>
    </div>

    <!-- Overdue Tab -->
    <div v-if="activeTab === 'overdue'" class="card p-4">
      <div v-if="overdueLoading" class="flex justify-center py-8">
        <div class="w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
      </div>
      <template v-else>
        <h3 class="font-semibold text-gray-800 dark:text-white mb-4">الفواتير المتأخرة</h3>
        <table class="w-full text-sm">
          <thead>
            <tr class="text-right border-b dark:border-slate-700">
              <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">الفاتورة</th>
              <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">العميل</th>
              <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">المبلغ</th>
              <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">التأخر</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in overdue" :key="r.id" class="border-b dark:border-slate-700/50">
              <td class="py-2 font-mono text-xs">{{ r.invoice_number }}</td>
              <td class="py-2">{{ r.customer?.name ?? '-' }}</td>
              <td class="py-2 font-medium text-red-600">{{ fmt(r.due_amount) }}</td>
              <td class="py-2">
                <span :class="['px-2 py-0.5 rounded-full text-xs', Number(r.days_overdue) > 30 ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700']">
                  {{ r.days_overdue }} يوم
                </span>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-if="!overdue.length" class="text-center text-gray-400 py-6">لا توجد فواتير متأخرة</p>
      </template>
    </div>

    <!-- Inventory Tab -->
    <div v-if="activeTab === 'inventory'" class="card p-4">
      <div v-if="invLoading" class="flex justify-center py-8">
        <div class="w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
      </div>
      <template v-else>
        <h3 class="font-semibold text-gray-800 dark:text-white mb-4">تقرير المخزون</h3>
        <table class="w-full text-sm">
          <thead>
            <tr class="text-right border-b dark:border-slate-700">
              <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">المنتج</th>
              <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">الكمية</th>
              <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">نقطة الطلب</th>
              <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">الحالة</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in inventory" :key="r.id" class="border-b dark:border-slate-700/50">
              <td class="py-2">{{ r.product?.name ?? r.product_id }}</td>
              <td class="py-2">{{ r.quantity }}</td>
              <td class="py-2">{{ r.reorder_point }}</td>
              <td class="py-2">
                <span :class="['px-2 py-0.5 rounded-full text-xs', Number(r.quantity) <= Number(r.reorder_point) ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700']">
                  {{ Number(r.quantity) <= Number(r.reorder_point) ? 'منخفض' : 'جيد' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-if="!inventory.length" class="text-center text-gray-400 py-6">لا توجد بيانات</p>
      </template>
    </div>

    <!-- VAT Tab -->
    <div v-if="activeTab === 'vat'" class="card p-4">
      <div v-if="vatLoading" class="flex justify-center py-8">
        <div class="w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
      </div>
      <template v-else>
        <h3 class="font-semibold text-gray-800 dark:text-white mb-4">تقرير ضريبة القيمة المضافة</h3>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
          <KpiCard title="إجمالي الضريبة المحصلة" :value="fmt(vat.total_tax)"         icon="🏛" color="purple" />
          <KpiCard title="صافي المبيعات"           :value="fmt(vat.net_sales)"         icon="💰" color="blue" />
          <KpiCard title="إجمالي المبيعات شامل"    :value="fmt(vat.gross_sales)"       icon="📊" color="indigo" />
        </div>
        <table v-if="vat.by_rate && vat.by_rate.length" class="w-full text-sm">
          <thead>
            <tr class="text-right border-b dark:border-slate-700">
              <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">نسبة الضريبة</th>
              <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">الوعاء</th>
              <th class="pb-2 font-medium text-gray-600 dark:text-slate-400">الضريبة</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in vat.by_rate" :key="r.tax_rate" class="border-b dark:border-slate-700/50">
              <td class="py-2">{{ r.tax_rate }}%</td>
              <td class="py-2">{{ fmt(r.taxable_amount) }}</td>
              <td class="py-2 font-medium text-purple-600">{{ fmt(r.tax_amount) }}</td>
            </tr>
          </tbody>
        </table>
      </template>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useApi } from '@/composables/useApi'
import KpiCard from '@/components/KpiCard.vue'

const api = useApi()

const from = ref(new Date(new Date().setDate(1)).toISOString().split('T')[0])
const to   = ref(new Date().toISOString().split('T')[0])
const activeTab = ref('kpi')

const tabs = [
  { key: 'kpi',         label: 'المؤشرات الرئيسية' },
  { key: 'sales',       label: 'المبيعات' },
  { key: 'by_customer', label: 'حسب العميل' },
  { key: 'by_product',  label: 'حسب المنتج' },
  { key: 'vat',         label: 'الضريبة' },
  { key: 'overdue',     label: 'المتأخرات' },
  { key: 'inventory',   label: 'المخزون' },
]

const loading        = ref(false)
const kpiLoading     = ref(false)
const salesLoading   = ref(false)
const custLoading    = ref(false)
const prodLoading    = ref(false)
const overdueLoading = ref(false)
const invLoading     = ref(false)
const vatLoading     = ref(false)

const kpi        = ref<any>(null)
const sales      = ref<any>({})
const byCustomer = ref<any[]>([])
const byProduct  = ref<any[]>([])
const overdue    = ref<any[]>([])
const inventory  = ref<any[]>([])
const vat        = ref<any>({})

const maxSales         = computed(() => Math.max(1, ...((sales.value.byBranch ?? []).map((b: any) => Number(b.total_sales) || 0))))
const maxCustomerSales = computed(() => Math.max(1, ...(byCustomer.value.map((r: any) => Number(r.total_sales) || 0))))
const maxProductSales  = computed(() => Math.max(1, ...(byProduct.value.map((r: any) => Number(r.total_revenue) || 0))))

const fmt = (v: any) => {
  const n = parseFloat(v) || 0
  return n.toLocaleString('ar-SA', { style: 'currency', currency: 'SAR', maximumFractionDigits: 0 })
}

const params = () => ({ from: from.value, to: to.value })

async function loadAll() {
  loading.value = true
  await Promise.allSettled([loadKpi(), loadSales(), loadCustomer(), loadProduct(), loadOverdue(), loadInventory(), loadVat()])
  loading.value = false
}

async function loadKpi() {
  kpiLoading.value = true
  try { const r = await api.get('/reports/kpi', params()); kpi.value = r.data?.data ?? r.data ?? null }
  catch { kpi.value = null }
  finally { kpiLoading.value = false }
}

async function loadSales() {
  salesLoading.value = true
  try { const r = await api.get('/reports/sales', params()); sales.value = r.data?.data ?? r.data ?? {} }
  catch { sales.value = {} }
  finally { salesLoading.value = false }
}

async function loadCustomer() {
  custLoading.value = true
  try { const r = await api.get('/reports/sales-by-customer', params()); byCustomer.value = r.data?.data ?? r.data ?? [] }
  catch { byCustomer.value = [] }
  finally { custLoading.value = false }
}

async function loadProduct() {
  prodLoading.value = true
  try { const r = await api.get('/reports/sales-by-product', params()); byProduct.value = r.data?.data ?? r.data ?? [] }
  catch { byProduct.value = [] }
  finally { prodLoading.value = false }
}

async function loadOverdue() {
  overdueLoading.value = true
  try { const r = await api.get('/reports/overdue-receivables', params()); overdue.value = r.data?.data?.data ?? r.data?.data ?? r.data ?? [] }
  catch { overdue.value = [] }
  finally { overdueLoading.value = false }
}

async function loadInventory() {
  invLoading.value = true
  try { const r = await api.get('/reports/inventory', params()); inventory.value = r.data?.data?.data ?? r.data?.data ?? r.data ?? [] }
  catch { inventory.value = [] }
  finally { invLoading.value = false }
}

async function loadVat() {
  vatLoading.value = true
  try { const r = await api.get('/reports/vat', params()); vat.value = r.data?.data ?? r.data ?? {} }
  catch { vat.value = {} }
  finally { vatLoading.value = false }
}

function exportCSV() {
  const map: Record<string, any[]> = {
    kpi: kpi.value ? [kpi.value] : [],
    sales: sales.value.byBranch ?? [],
    by_customer: byCustomer.value,
    by_product: byProduct.value,
    overdue: overdue.value,
    inventory: inventory.value,
    vat: vat.value.by_rate ?? [],
  }
  const rows = map[activeTab.value] ?? []
  if (!rows.length) { alert('لا توجد بيانات'); return }
  const keys = Object.keys(rows[0])
  const csv = [keys.join(','), ...rows.map((r: any) => keys.map(k => `"${r[k] ?? ''}"`).join(','))].join('\n')
  const a = document.createElement('a')
  a.href = URL.createObjectURL(new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' }))
  a.download = `report_${activeTab.value}_${from.value}.csv`
  a.click()
}

async function exportExcel() {
  try {
    const { utils, writeFile } = await import('xlsx')
    const map: Record<string, any[]> = {
      kpi: kpi.value ? [kpi.value] : [],
      sales: sales.value.byBranch ?? [],
      by_customer: byCustomer.value,
      by_product: byProduct.value,
      overdue: overdue.value,
      inventory: inventory.value,
      vat: vat.value.by_rate ?? [],
    }
    const rows = map[activeTab.value] ?? []
    if (!rows.length) { alert('لا توجد بيانات'); return }
    const ws = utils.json_to_sheet(rows)
    const wb = utils.book_new()
    utils.book_append_sheet(wb, ws, 'تقرير')
    writeFile(wb, `report_${activeTab.value}_${from.value}.xlsx`)
  } catch { alert('مكتبة التصدير غير متوفرة') }
}

async function exportPDF() {
  try {
    const { jsPDF } = await import('jspdf')
    const at = await import('jspdf-autotable')
    const autoTable = at.default ?? at
    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
    doc.setFontSize(14)
    doc.text('تقرير: ' + (tabs.find(t => t.key === activeTab.value)?.label ?? ''), 105, 15, { align: 'center' })
    doc.setFontSize(9)
    doc.text(`${from.value} — ${to.value}`, 105, 22, { align: 'center' })
    const map: Record<string, any[]> = {
      kpi: kpi.value ? [kpi.value] : [],
      sales: sales.value.byBranch ?? [],
      by_customer: byCustomer.value,
      by_product: byProduct.value,
      overdue: overdue.value,
      inventory: inventory.value,
      vat: vat.value.by_rate ?? [],
    }
    const rows = map[activeTab.value] ?? []
    if (rows.length) {
      const keys = Object.keys(rows[0])
      autoTable(doc, { head: [keys], body: rows.map((r: any) => keys.map(k => String(r[k] ?? ''))), startY: 28, styles: { fontSize: 8 }, headStyles: { fillColor: [59, 130, 246] } })
    }
    doc.save(`report_${activeTab.value}_${from.value}.pdf`)
  } catch { window.print() }
}

onMounted(loadAll)
</script>
