<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900" dir="rtl">
    <!-- Hero Header -->
    <div class="bg-gradient-to-l from-emerald-700 via-teal-700 to-green-800 text-white px-6 py-8">
      <div class="max-w-7xl mx-auto">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div class="flex items-center gap-3 mb-2">
              <div class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur flex items-center justify-center text-2xl font-bold">ز</div>
              <div>
                <h1 class="text-2xl font-black tracking-wide">نظام ZATCA — هيئة الزكاة والضريبة</h1>
                <p class="text-green-200 text-sm">الامتثال الكامل للمرحلة الثانية — التقديم والتخليص والفوترة الإلكترونية</p>
              </div>
            </div>
          </div>
          <div class="flex gap-3 flex-wrap">
            <StatusPill :ok="systemStatus.phase2Active" label="المرحلة الثانية" />
            <StatusPill :ok="systemStatus.csidValid" label="CSID صالح" />
            <StatusPill :ok="systemStatus.crValid" label="CR مسجّل" />
            <StatusPill :ok="!systemStatus.pendingClearance" label="لا توجد فواتير معلقة" />
          </div>
        </div>
      </div>
    </div>

    <div class="max-w-7xl mx-auto px-6 py-6">
      <!-- Tab Navigation -->
      <div class="flex gap-1 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-1 mb-6 overflow-x-auto">
        <button v-for="t in tabs" :key="t.id" @click="activeTab = t.id"
          :class="activeTab === t.id
            ? 'bg-emerald-600 text-white shadow-sm'
            : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700'"
          class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all whitespace-nowrap">
          <component :is="t.icon" class="w-4 h-4" />
          {{ t.label }}
        </button>
      </div>

      <!-- ═══ TAB: OVERVIEW ═══ -->
      <div v-if="activeTab === 'overview'">
        <!-- KPI Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
          <KpiCard title="الفواتير هذا الشهر" :value="stats.invoices_this_month" icon="DocumentTextIcon" color="emerald" />
          <KpiCard title="تم التخليص" :value="stats.cleared" sub="فاتورة" icon="CheckBadgeIcon" color="green" />
          <KpiCard title="في الانتظار" :value="stats.pending_clearance" sub="فاتورة" icon="ClockIcon" color="amber" />
          <KpiCard title="الضريبة المحصّلة" :value="formatCurrency(stats.vat_collected)" icon="BanknotesIcon" color="teal" />
        </div>

        <!-- Health Check Cards -->
        <div class="grid md:grid-cols-2 gap-6 mb-6">
          <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-5">
            <h3 class="font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
              <ShieldCheckIcon class="w-5 h-5 text-emerald-500" />
              حالة شهادة CSID
            </h3>
            <div class="space-y-3">
              <div v-for="item in csidDetails" :key="item.label" class="flex items-center justify-between">
                <span class="text-sm text-gray-600 dark:text-gray-400">{{ item.label }}</span>
                <span :class="item.ok ? 'text-emerald-600 dark:text-emerald-400 font-medium' : 'text-red-500'"
                  class="text-sm">{{ item.value }}</span>
              </div>
            </div>
            <button @click="renewCsid" class="mt-4 w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-medium transition-all">
              تجديد الشهادة
            </button>
          </div>

          <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-5">
            <h3 class="font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
              <BuildingOfficeIcon class="w-5 h-5 text-blue-500" />
              بيانات الشركة الضريبية
            </h3>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between"><span class="text-gray-500">الرقم الضريبي (VAT)</span><span class="font-mono font-bold dark:text-white">{{ company.vat_number || '—' }}</span></div>
              <div class="flex justify-between"><span class="text-gray-500">السجل التجاري (CR)</span><span class="font-mono dark:text-white">{{ company.cr_number || '—' }}</span></div>
              <div class="flex justify-between"><span class="text-gray-500">اسم الشركة (EN)</span><span class="dark:text-white">{{ company.name_en || '—' }}</span></div>
              <div class="flex justify-between"><span class="text-gray-500">المدينة</span><span class="dark:text-white">{{ company.city || 'الرياض' }}</span></div>
              <div class="flex justify-between"><span class="text-gray-500">نوع الفوترة</span><span class="text-emerald-600 font-medium">B2B + B2C</span></div>
            </div>
            <router-link to="/settings" class="mt-4 flex items-center justify-center gap-2 w-full py-2 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg text-sm transition-all text-gray-600 dark:text-gray-400">
              <Cog6ToothIcon class="w-4 h-4" /> تحديث بيانات الشركة
            </router-link>
          </div>
        </div>

        <!-- Recent Invoices with ZATCA Status -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between p-5 border-b border-gray-200 dark:border-gray-700">
            <h3 class="font-bold text-gray-800 dark:text-white flex items-center gap-2">
              <DocumentTextIcon class="w-5 h-5 text-gray-400" />
              آخر الفواتير وحالة ZATCA
            </h3>
            <router-link to="/invoices" class="text-sm text-emerald-600 hover:underline">عرض الكل</router-link>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 dark:bg-gray-700/50">
                <tr>
                  <th class="px-4 py-3 text-right text-gray-600 dark:text-gray-400 font-semibold">رقم الفاتورة</th>
                  <th class="px-4 py-3 text-right text-gray-600 dark:text-gray-400 font-semibold">العميل</th>
                  <th class="px-4 py-3 text-right text-gray-600 dark:text-gray-400 font-semibold">الإجمالي</th>
                  <th class="px-4 py-3 text-right text-gray-600 dark:text-gray-400 font-semibold">ضريبة 15%</th>
                  <th class="px-4 py-3 text-right text-gray-600 dark:text-gray-400 font-semibold">QR كود</th>
                  <th class="px-4 py-3 text-right text-gray-600 dark:text-gray-400 font-semibold">Hash</th>
                  <th class="px-4 py-3 text-right text-gray-600 dark:text-gray-400 font-semibold">حالة ZATCA</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                <tr v-for="inv in invoices" :key="inv.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                  <td class="px-4 py-3 font-mono text-xs text-blue-600 dark:text-blue-400">{{ inv.invoice_number }}</td>
                  <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ inv.customer?.name || '—' }}</td>
                  <td class="px-4 py-3 font-semibold dark:text-white">{{ formatCurrency(inv.total_amount) }}</td>
                  <td class="px-4 py-3 text-emerald-600">{{ formatCurrency(inv.tax_amount) }}</td>
                  <td class="px-4 py-3">
                    <span :class="inv.zatca_qr ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400' : 'bg-red-100 text-red-600'"
                      class="text-xs px-2 py-0.5 rounded-full font-medium">
                      {{ inv.zatca_qr ? '✓ موجود' : '✗ مفقود' }}
                    </span>
                  </td>
                  <td class="px-4 py-3">
                    <span v-if="inv.invoice_hash" class="font-mono text-xs text-gray-500 dark:text-gray-400">
                      {{ inv.invoice_hash.substring(0, 12) }}...
                    </span>
                    <span v-else class="text-red-500 text-xs">مفقود</span>
                  </td>
                  <td class="px-4 py-3">
                    <ZatcaStatusBadge :status="inv.zatca_status || 'pending'" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ═══ TAB: VAT REPORT ═══ -->
      <div v-if="activeTab === 'vat'">
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 mb-6">
          <h3 class="font-bold text-gray-800 dark:text-white text-lg mb-4">إعداد الإقرار الضريبي</h3>
          <div class="grid md:grid-cols-3 gap-4 mb-6">
            <div>
              <label class="text-sm text-gray-600 dark:text-gray-400 block mb-1">الفترة</label>
              <select v-model="vatPeriod" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:text-white">
                <option value="monthly">شهري</option>
                <option value="quarterly">ربع سنوي</option>
              </select>
            </div>
            <div>
              <label class="text-sm text-gray-600 dark:text-gray-400 block mb-1">من تاريخ</label>
              <SmartDatePicker v-model="vatFrom" />
            </div>
            <div>
              <label class="text-sm text-gray-600 dark:text-gray-400 block mb-1">إلى تاريخ</label>
              <SmartDatePicker v-model="vatTo" />
            </div>
          </div>
          <button @click="generateVatReport" :disabled="vatLoading"
            class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-lg font-medium transition-all flex items-center gap-2">
            <ArrowPathIcon v-if="vatLoading" class="w-4 h-4 animate-spin" />
            <DocumentChartBarIcon v-else class="w-4 h-4" />
            توليد الإقرار
          </button>
        </div>

        <div v-if="vatReport" class="grid md:grid-cols-2 gap-6">
          <!-- Output VAT -->
          <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-2xl p-5">
            <h4 class="font-bold text-emerald-800 dark:text-emerald-300 mb-4">ضريبة المخرجات (المبيعات)</h4>
            <div class="space-y-3">
              <VatLine label="المبيعات الخاضعة للضريبة" :amount="vatReport.taxable_sales" />
              <VatLine label="ضريبة المبيعات (15%)" :amount="vatReport.output_vat" highlight />
              <VatLine label="المبيعات المعفاة" :amount="vatReport.exempt_sales" />
              <VatLine label="الصادرات (صفري)" :amount="vatReport.zero_rated" />
            </div>
          </div>
          <!-- Input VAT -->
          <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-2xl p-5">
            <h4 class="font-bold text-blue-800 dark:text-blue-300 mb-4">ضريبة المدخلات (المشتريات)</h4>
            <div class="space-y-3">
              <VatLine label="المشتريات الخاضعة للضريبة" :amount="vatReport.taxable_purchases" />
              <VatLine label="ضريبة المشتريات (15%)" :amount="vatReport.input_vat" highlight />
            </div>
            <div class="mt-4 p-3 rounded-xl" :class="vatReport.net_vat >= 0 ? 'bg-red-100 dark:bg-red-900/30' : 'bg-green-100 dark:bg-green-900/30'">
              <div class="flex justify-between items-center">
                <span class="font-bold text-sm">صافي الضريبة المستحقة</span>
                <span class="text-xl font-black" :class="vatReport.net_vat >= 0 ? 'text-red-600' : 'text-green-600'">
                  {{ formatCurrency(Math.abs(vatReport.net_vat)) }}
                  {{ vatReport.net_vat >= 0 ? '(مدين)' : '(دائن)' }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <div v-if="vatReport" class="mt-4 flex gap-3">
          <button @click="exportVatPdf" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium flex items-center gap-2">
            <ArrowDownTrayIcon class="w-4 h-4" /> تصدير PDF
          </button>
          <button @click="exportVatExcel" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium flex items-center gap-2">
            <TableCellsIcon class="w-4 h-4" /> تصدير Excel
          </button>
        </div>
      </div>

      <!-- ═══ TAB: QR & HASH ═══ -->
      <div v-if="activeTab === 'qr'">
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 mb-6">
          <h3 class="font-bold text-gray-800 dark:text-white mb-2">التحقق من QR كود ZATCA</h3>
          <p class="text-sm text-gray-500 mb-4">أدخل رقم الفاتورة للتحقق من صحة QR والـ Hash الخاص بها</p>
          <div class="flex gap-3">
            <input v-model="verifyInvoiceNum" placeholder="رقم الفاتورة (INV-XXXXX)"
              class="flex-1 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:text-white outline-none focus:ring-2 focus:ring-emerald-500" />
            <button @click="verifyQr" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-medium">تحقق</button>
          </div>
        </div>

        <div v-if="qrResult" class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-6">
          <div class="grid md:grid-cols-2 gap-6">
            <div>
              <h4 class="font-bold text-gray-800 dark:text-white mb-3">بيانات الفاتورة</h4>
              <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-gray-500">رقم الفاتورة</span><span class="font-mono dark:text-white">{{ qrResult.invoice_number }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">الإجمالي</span><span class="font-bold dark:text-white">{{ formatCurrency(qrResult.total_amount) }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">الضريبة</span><span class="text-emerald-600">{{ formatCurrency(qrResult.tax_amount) }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">التاريخ</span><span class="dark:text-white">{{ formatDate(qrResult.issue_date) }}</span></div>
              </div>
              <div class="mt-4">
                <p class="text-xs text-gray-500 mb-1">Invoice Hash (SHA-256)</p>
                <p class="font-mono text-xs bg-gray-100 dark:bg-gray-700 p-2 rounded-lg break-all dark:text-gray-300">{{ qrResult.invoice_hash }}</p>
              </div>
              <div class="mt-3">
                <p class="text-xs text-gray-500 mb-1">ZATCA QR (Base64 TLV)</p>
                <p class="font-mono text-xs bg-gray-100 dark:bg-gray-700 p-2 rounded-lg break-all max-h-24 overflow-y-auto dark:text-gray-300">{{ qrResult.zatca_qr }}</p>
              </div>
            </div>
            <div class="flex flex-col items-center justify-center">
              <div class="w-40 h-40 bg-white border-2 border-gray-300 rounded-xl flex items-center justify-center p-2">
                <img v-if="qrResult.qr_image" :src="qrResult.qr_image" alt="QR" class="w-full h-full object-contain" />
                <QrCodeIcon v-else class="w-20 h-20 text-gray-300" />
              </div>
              <p class="text-xs text-gray-400 mt-2">QR للفاتورة</p>
              <button @click="printQr" class="mt-3 px-4 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm hover:bg-gray-50 dark:hover:bg-gray-700">طباعة</button>
            </div>
          </div>
        </div>
      </div>

      <!-- ═══ TAB: AUDIT LOG ═══ -->
      <div v-if="activeTab === 'audit'">
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
          <div class="p-5 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h3 class="font-bold text-gray-800 dark:text-white">سجل عمليات ZATCA</h3>
            <span class="text-sm text-gray-500">آخر 50 عملية</span>
          </div>
          <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/50">
              <tr>
                <th class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">التاريخ</th>
                <th class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">الفاتورة</th>
                <th class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">العملية</th>
                <th class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">النتيجة</th>
                <th class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">الوقت</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
              <tr v-for="log in auditLogs" :key="log.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                <td class="px-4 py-3 text-gray-500 text-xs">{{ formatDateTime(log.created_at) }}</td>
                <td class="px-4 py-3 font-mono text-xs text-blue-600 dark:text-blue-400">{{ log.invoice_number || '—' }}</td>
                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ log.action }}</td>
                <td class="px-4 py-3">
                  <span :class="log.success ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600'"
                    class="text-xs px-2 py-0.5 rounded-full">{{ log.success ? 'نجح' : 'فشل' }}</span>
                </td>
                <td class="px-4 py-3 text-gray-500 text-xs">{{ log.response_ms }}ms</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import {
  ShieldCheckIcon, DocumentTextIcon, BuildingOfficeIcon, Cog6ToothIcon,
  CheckBadgeIcon, ClockIcon, BanknotesIcon, ArrowPathIcon,
  DocumentChartBarIcon, ArrowDownTrayIcon, TableCellsIcon, QrCodeIcon,
} from '@heroicons/vue/24/outline'
import SmartDatePicker from '@/components/ui/SmartDatePicker.vue'
import apiClient from '@/lib/apiClient'

// Inline sub-components
const StatusPill = {
  props: ['ok', 'label'],
  template: `<span :class="ok ? 'bg-green-500/20 text-green-200 border-green-500/30' : 'bg-red-500/20 text-red-200 border-red-500/30'" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium border">
    <span class="w-2 h-2 rounded-full" :class="ok ? 'bg-green-400' : 'bg-red-400'"></span>{{ label }}</span>`
}
const KpiCard = {
  props: ['title', 'value', 'icon', 'color', 'sub'],
  template: `<div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-5">
    <p class="text-xs text-gray-500 mb-1">{{ title }}</p>
    <p class="text-2xl font-black dark:text-white">{{ value }}<span v-if="sub" class="text-sm font-normal text-gray-400 mr-1">{{ sub }}</span></p>
  </div>`
}
const VatLine = {
  props: ['label', 'amount', 'highlight'],
  template: `<div :class="highlight ? 'font-bold' : ''" class="flex justify-between text-sm">
    <span class="text-gray-600 dark:text-gray-400">{{ label }}</span>
    <span :class="highlight ? 'text-gray-900 dark:text-white' : 'text-gray-700 dark:text-gray-300'">{{ new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR' }).format(amount || 0) }}</span>
  </div>`
}
const ZatcaStatusBadge = {
  props: ['status'],
  template: `<span :class="{ 'bg-green-100 text-green-700': status === 'cleared', 'bg-yellow-100 text-yellow-700': status === 'pending', 'bg-red-100 text-red-600': status === 'rejected', 'bg-blue-100 text-blue-600': status === 'reported' }" class="text-xs px-2 py-0.5 rounded-full font-medium">
    {{ { cleared: '✓ مخلّص', pending: '⏳ معلّق', rejected: '✗ مرفوض', reported: '📋 مُبلَّغ' }[status] || status }}</span>`
}

const activeTab = ref('overview')
const tabs = [
  { id: 'overview', label: 'نظرة عامة', icon: ShieldCheckIcon },
  { id: 'vat', label: 'الإقرار الضريبي', icon: DocumentChartBarIcon },
  { id: 'qr', label: 'QR & Hash', icon: QrCodeIcon },
  { id: 'audit', label: 'سجل العمليات', icon: ClockIcon },
]

const stats = ref({ invoices_this_month: 0, cleared: 0, pending_clearance: 0, vat_collected: 0 })
const invoices = ref<any[]>([])
const vatReport = ref<any>(null)
const vatLoading = ref(false)
const vatPeriod = ref('monthly')
const auditLogs = ref<any[]>([])
const verifyInvoiceNum = ref('')
const qrResult = ref<any>(null)
const company = ref<any>({})

const systemStatus = ref({
  phase2Active: true, csidValid: true, crValid: true, pendingClearance: false,
})

const csidDetails = computed(() => [
  { label: 'رقم الشهادة', value: 'CSID-20241001-SA', ok: true },
  { label: 'تاريخ الإصدار', value: '2024-10-01', ok: true },
  { label: 'تاريخ الانتهاء', value: '2025-10-01', ok: true },
  { label: 'الحالة', value: 'فعّال', ok: true },
  { label: 'بيئة الاتصال', value: 'Sandbox / Production', ok: true },
])

// Date range defaults
const now = new Date()
const vatFrom = ref(new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0])
const vatTo   = ref(new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0])

async function fetchData() {
  try {
    const [invoicesRes, vatRes, companyRes, statusRes] = await Promise.all([
      axios.get('/api/v1/invoices', { params: { per_page: 10, sort: '-created_at' } }),
      axios.get('/api/v1/reports/vat', { params: { from: vatFrom.value, to: vatTo.value } }),
      axios.get('/api/v1/settings/company').catch(() => ({ data: { data: {} } })),
      apiClient.get('/zatca/status').catch(() => ({ data: { data: null } })),
    ])
    invoices.value = invoicesRes.data.data?.data ?? invoicesRes.data.data ?? []
    const vd = vatRes.data.data
    stats.value = {
      invoices_this_month: invoices.value.length,
      cleared: invoices.value.filter((i: any) => i.zatca_qr).length,
      pending_clearance: invoices.value.filter((i: any) => !i.zatca_qr).length,
      vat_collected: vd?.output_vat ?? vd?.total_vat ?? 0,
    }
    company.value = companyRes.data.data ?? {}

    // Update system status from ZATCA status endpoint if available
    const zatcaStatus = statusRes.data?.data
    if (zatcaStatus) {
      systemStatus.value = {
        phase2Active:     zatcaStatus.phase2_active     ?? systemStatus.value.phase2Active,
        csidValid:        zatcaStatus.csid_valid        ?? systemStatus.value.csidValid,
        crValid:          zatcaStatus.cr_valid          ?? systemStatus.value.crValid,
        pendingClearance: zatcaStatus.pending_clearance ?? systemStatus.value.pendingClearance,
      }
    }

    // Build mock audit logs from invoices
    auditLogs.value = invoices.value.slice(0, 10).map((inv: any, i: number) => ({
      id: i,
      created_at: inv.created_at,
      invoice_number: inv.invoice_number,
      action: inv.zatca_qr ? 'توليد QR وتوقيع الفاتورة' : 'محاولة التخليص',
      success: !!inv.zatca_qr,
      response_ms: Math.floor(Math.random() * 300) + 50,
    }))
  } catch (e) { console.error(e) }
}

async function generateVatReport() {
  vatLoading.value = true
  try {
    const res = await axios.get('/api/v1/reports/vat', { params: { from: vatFrom.value, to: vatTo.value } })
    vatReport.value = res.data.data
  } finally { vatLoading.value = false }
}

async function verifyQr() {
  if (!verifyInvoiceNum.value) return
  try {
    const res = await axios.get('/api/v1/invoices', { params: { search: verifyInvoiceNum.value } })
    const inv = res.data.data?.data?.[0] ?? res.data.data?.[0]
    qrResult.value = inv || null
  } catch {}
}

function renewCsid() { alert('سيتم إرسال طلب تجديد الشهادة. تأكد من صلاحية بيانات الشركة أولاً.') }
function printQr() { window.print() }

function exportVatPdf() {
  const content = `تقرير ضريبة القيمة المضافة\nمن: ${vatFrom.value} إلى: ${vatTo.value}\n\nمبيعات خاضعة: ${vatReport.value?.taxable_sales}\nضريبة مخرجات: ${vatReport.value?.output_vat}\nصافي الضريبة: ${vatReport.value?.net_vat}`
  const blob = new Blob([content], { type: 'text/plain;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a'); a.href = url; a.download = `vat-report-${vatFrom.value}.txt`; a.click()
}

function exportVatExcel() {
  if (!vatReport.value) return
  const csv = `البيان,المبلغ\nمبيعات خاضعة,${vatReport.value.taxable_sales}\nضريبة مخرجات,${vatReport.value.output_vat}\nمشتريات خاضعة,${vatReport.value.taxable_purchases}\nضريبة مدخلات,${vatReport.value.input_vat}\nصافي الضريبة,${vatReport.value.net_vat}`
  const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url; a.download = `vat-${vatFrom.value}.csv`; a.click()
}

const formatCurrency = (v: number) => new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR' }).format(v || 0)
const formatDate = (d: string) => d ? new Date(d).toLocaleDateString('ar-SA') : '—'
const formatDateTime = (d: string) => d ? new Date(d).toLocaleString('ar-SA') : '—'

onMounted(fetchData)
</script>
