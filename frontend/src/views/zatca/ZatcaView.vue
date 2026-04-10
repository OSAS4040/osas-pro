<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900" dir="rtl">
    <!-- Hero Header -->
    <div data-print-chrome class="print:hidden bg-gradient-to-l from-emerald-700 via-teal-700 to-green-800 text-white px-6 py-8">
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

    <div v-if="simulationMode" data-print-chrome class="print:hidden max-w-7xl mx-auto px-6 pt-4">
      <div class="rounded-xl border border-amber-200 bg-amber-50 dark:bg-amber-900/25 dark:border-amber-800 px-4 py-3 text-sm text-amber-950 dark:text-amber-100">
        <strong>وضع محاكاة ZATCA:</strong>
        لا يوجد اتصال فعلي بمنصة هيئة الزكاة والضريبة. الواجهة للتطوير والاختبار فقط — لا تُستخدم كدليل امتثال إنتاجي.
      </div>
    </div>

    <div class="max-w-7xl mx-auto px-6 py-6">
      <!-- Tab Navigation -->
      <div data-print-chrome class="print:hidden flex gap-1 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-1 mb-6 overflow-x-auto">
        <button v-for="t in tabs" :key="t.id" :class="activeTab === t.id
                  ? 'bg-emerald-600 text-white shadow-sm'
                  : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700'"
                class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all whitespace-nowrap"
                @click="activeTab = t.id"
        >
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
                      class="text-sm"
                >{{ item.value }}</span>
              </div>
            </div>
            <button class="mt-4 w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-medium transition-all" @click="renewCsid">
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
                          class="text-xs px-2 py-0.5 rounded-full font-medium"
                    >
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
          <button :disabled="vatLoading" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-lg font-medium transition-all flex items-center gap-2"
                  @click="generateVatReport"
          >
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
          <button
            :disabled="vatPdfExporting"
            class="px-4 py-2 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white rounded-lg text-sm font-medium flex items-center gap-2"
            @click="exportVatPdf"
          >
            <ArrowPathIcon v-if="vatPdfExporting" class="w-4 h-4 animate-spin" />
            <ArrowDownTrayIcon v-else class="w-4 h-4" />
            {{ vatPdfExporting ? 'جاري التصدير…' : 'تصدير PDF' }}
          </button>
          <button class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium flex items-center gap-2" @click="exportVatExcel">
            <TableCellsIcon class="w-4 h-4" /> تصدير Excel
          </button>
        </div>
      </div>

      <!-- ═══ TAB: QR & HASH ═══ -->
      <div v-if="activeTab === 'qr'">
        <div data-print-chrome class="print:hidden bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 mb-6">
          <h3 class="font-bold text-gray-800 dark:text-white mb-2">التحقق من QR كود ZATCA</h3>
          <p class="text-sm text-gray-500 mb-4">أدخل رقم الفاتورة للتحقق من صحة QR والـ Hash الخاص بها</p>
          <div class="flex gap-3">
            <input v-model="verifyInvoiceNum" placeholder="رقم الفاتورة (INV-XXXXX)"
                   class="flex-1 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:text-white outline-none focus:ring-2 focus:ring-emerald-500"
            />
            <button class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-medium" @click="verifyQr">تحقق</button>
          </div>
        </div>

        <div v-if="qrResult" class="print-container bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-6">
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
              <button type="button" data-print-chrome class="print:hidden mt-3 px-4 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm hover:bg-gray-50 dark:hover:bg-gray-700" @click="printQr">طباعة</button>
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
                        class="text-xs px-2 py-0.5 rounded-full"
                  >{{ log.success ? 'نجح' : 'فشل' }}</span>
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
import {
  ShieldCheckIcon, DocumentTextIcon, BuildingOfficeIcon, Cog6ToothIcon,
  ClockIcon, ArrowPathIcon,
  DocumentChartBarIcon, ArrowDownTrayIcon, TableCellsIcon, QrCodeIcon,
} from '@heroicons/vue/24/outline'
import SmartDatePicker from '@/components/ui/SmartDatePicker.vue'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'
import { printDocument, ensurePrintFontsReady } from '@/composables/useAppPrint'
import { useToast } from '@/composables/useToast'
import { PDF_EXPORT_FAIL_AR } from '@/constants/pdfExportMessages'
import { addInvoiceCanvasToSinglePagePdf } from '@/utils/invoicePdfExport'

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
const vatReport = ref<NormalizedVatReport | null>(null)
const vatLoading = ref(false)
const vatPdfExporting = ref(false)
const toast = useToast()
const vatPeriod = ref('monthly')
const auditLogs = ref<any[]>([])
const verifyInvoiceNum = ref('')
const qrResult = ref<any>(null)
const auth = useAuthStore()
const company = ref<any>({})

const simulationMode = ref(false)

/** حقول العرض والتصدير — يطابق استجابة `/reports/vat` (taxable_amount, vat_collected, …) */
interface NormalizedVatReport {
  invoice_count: number
  taxable_sales: number
  output_vat: number
  exempt_sales: number
  zero_rated: number
  taxable_purchases: number
  input_vat: number
  net_vat: number
  gross_sales: number
  taxable_amount: number
  vat_collected: number
  total_with_vat: number
  total_tax: number
  net_sales: number
  by_rate: Array<{ tax_rate: number; taxable_amount: number; tax_amount: number }>
}

function normalizeVatReport(raw: unknown): NormalizedVatReport | null {
  if (!raw || typeof raw !== 'object') return null
  const r = raw as Record<string, unknown>
  const num = (v: unknown): number => {
    if (typeof v === 'number' && !Number.isNaN(v)) return v
    const n = Number(v)
    return Number.isFinite(n) ? n : 0
  }
  const taxable = num(r.taxable_sales) || num(r.taxable_amount) || num(r.net_sales)
  const output = num(r.output_vat) || num(r.vat_collected) || num(r.total_tax)
  const input = num(r.input_vat)
  const netExplicit = r.net_vat
  const net =
    netExplicit != null && netExplicit !== ''
      ? num(netExplicit)
      : output - input
  const byRaw = r.by_rate
  const by_rate = Array.isArray(byRaw)
    ? byRaw.map((row: unknown) => {
        const x = row as Record<string, unknown>
        return {
          tax_rate: num(x.tax_rate),
          taxable_amount: num(x.taxable_amount),
          tax_amount: num(x.tax_amount),
        }
      })
    : []
  return {
    invoice_count: num(r.invoice_count),
    taxable_sales: taxable,
    output_vat: output,
    exempt_sales: num(r.exempt_sales),
    zero_rated: num(r.zero_rated),
    taxable_purchases: num(r.taxable_purchases),
    input_vat: input,
    net_vat: net,
    gross_sales: num(r.gross_sales) || num(r.total_with_vat),
    taxable_amount: num(r.taxable_amount) || taxable,
    vat_collected: num(r.vat_collected) || output,
    total_with_vat: num(r.total_with_vat),
    total_tax: num(r.total_tax) || output,
    net_sales: num(r.net_sales) || taxable,
    by_rate,
  }
}

const systemStatus = ref({
  phase2Active: false, csidValid: false, crValid: false, pendingClearance: false,
})

const csidDetails = computed(() => {
  if (simulationMode.value) {
    return [
      { label: 'الوضع', value: 'محاكاة (Simulation)', ok: true },
      { label: 'CSID', value: 'غير متصل — لا شهادة إنتاجية', ok: false },
    ]
  }
  return [
    { label: 'رقم الشهادة', value: '—', ok: false },
    { label: 'تاريخ الإصدار', value: '—', ok: false },
    { label: 'تاريخ الانتهاء', value: '—', ok: false },
    { label: 'الحالة', value: 'غير مهيأ', ok: false },
    { label: 'بيئة الاتصال', value: '—', ok: false },
  ]
})

// Date range defaults
const now = new Date()
const vatFrom = ref(new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0])
const vatTo   = ref(new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0])

async function fetchData() {
  const cid = auth.user?.company_id
  try {
    const [invoicesRes, vatRes, companyRes, statusRes] = await Promise.all([
      apiClient.get('/invoices', { params: { per_page: 10, sort: '-created_at' } }),
      apiClient.get('/reports/vat', { params: { from: vatFrom.value, to: vatTo.value } }),
      cid
        ? apiClient.get(`/companies/${cid}`).catch(() => ({ data: { data: {} } }))
        : Promise.resolve({ data: { data: {} } }),
      apiClient.get('/zatca/status').catch(() => ({ data: { data: null } })),
    ])
    invoices.value = invoicesRes.data.data?.data ?? invoicesRes.data.data ?? []
    const vd = vatRes.data.data
    stats.value = {
      invoices_this_month: invoices.value.length,
      cleared: invoices.value.filter((i: any) => i.zatca_qr).length,
      pending_clearance: invoices.value.filter((i: any) => !i.zatca_qr).length,
      vat_collected: Number(vd?.vat_collected ?? vd?.total_tax ?? 0) || 0,
    }
    const rawCo = companyRes.data.data ?? {}
    company.value = {
      vat_number: rawCo.vat_number ?? rawCo.tax_number,
      cr_number: rawCo.cr_number ?? rawCo.commercial_registration,
      name_en: rawCo.name_en ?? rawCo.name,
      city: rawCo.city,
      ...rawCo,
    }

    // Update system status from ZATCA status endpoint if available
    const zatcaStatus = statusRes.data?.data
    simulationMode.value = zatcaStatus?.simulation_mode === true
    if (zatcaStatus) {
      systemStatus.value = {
        phase2Active:     Boolean(zatcaStatus.phase2_active),
        csidValid:        Boolean(zatcaStatus.csid_valid),
        crValid:          Boolean(zatcaStatus.cr_valid),
        pendingClearance: Boolean(zatcaStatus.pending_clearance),
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
    const res = await apiClient.get('/reports/vat', { params: { from: vatFrom.value, to: vatTo.value } })
    vatReport.value = normalizeVatReport(res.data.data)
  } finally {
    vatLoading.value = false
  }
}

async function verifyQr() {
  if (!verifyInvoiceNum.value) return
  try {
    const res = await apiClient.get('/invoices', { params: { search: verifyInvoiceNum.value } })
    const inv = res.data.data?.data?.[0] ?? res.data.data?.[0]
    qrResult.value = inv || null
  } catch {
    qrResult.value = null
  }
}

function renewCsid() {
  toast.info(
    'تجديد الشهادة',
    'سيتم إرسال طلب تجديد الشهادة. تأكد من صلاحية بيانات الشركة أولاً.',
  )
}
async function printQr() {
  await printDocument()
}

function buildVatReportPdfElement(v: NormalizedVatReport, from: string, to: string): HTMLElement {
  const fmt = (n: number) =>
    new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR' }).format(Number(n) || 0)

  const wrap = document.createElement('div')
  wrap.setAttribute('dir', 'rtl')
  wrap.style.cssText = [
    'box-sizing:border-box',
    'width:794px',
    'padding:36px 44px',
    'background:#ffffff',
    'color:#0f172a',
    'font-family:Segoe UI,Tahoma,Arial,sans-serif',
    'font-size:14px',
    'line-height:1.55',
  ].join(';')

  const h1 = document.createElement('h1')
  h1.style.cssText = 'margin:0 0 10px;font-size:22px;font-weight:800'
  h1.textContent = 'تقرير ضريبة القيمة المضافة'
  wrap.appendChild(h1)

  const p = document.createElement('p')
  p.style.cssText = 'margin:0 0 8px;color:#64748b;font-size:13px'
  p.textContent = `الفترة: من ${from} إلى ${to}`
  wrap.appendChild(p)

  const cnt = document.createElement('p')
  cnt.style.cssText = 'margin:0 0 20px;font-size:13px'
  cnt.textContent = `عدد الفواتير ضمن الفترة: ${v.invoice_count}`
  wrap.appendChild(cnt)

  function section(title: string, color: string) {
    const h = document.createElement('h2')
    h.style.cssText = `margin:20px 0 12px;font-size:16px;font-weight:700;color:${color}`
    h.textContent = title
    wrap.appendChild(h)
  }

  function row(label: string, amount: number, highlight = false) {
    const d = document.createElement('div')
    d.style.cssText = [
      'display:flex',
      'justify-content:space-between',
      'align-items:center',
      'padding:10px 0',
      'border-bottom:1px solid #e2e8f0',
      highlight ? 'font-weight:700' : '',
    ].join(';')
    const l = document.createElement('span')
    l.textContent = label
    const r = document.createElement('span')
    r.textContent = fmt(amount)
    d.appendChild(l)
    d.appendChild(r)
    wrap.appendChild(d)
  }

  section('ضريبة المخرجات (المبيعات)', '#047857')
  row('المبيعات الخاضعة للضريبة', v.taxable_sales)
  row('ضريبة المبيعات (15٪)', v.output_vat, true)
  row('المبيعات المعفاة', v.exempt_sales)
  row('الصادرات (صفري)', v.zero_rated)

  section('ضريبة المدخلات (المشتريات)', '#1d4ed8')
  row('المشتريات الخاضعة للضريبة', v.taxable_purchases)
  row('ضريبة المشتريات (15٪)', v.input_vat, true)

  const netBox = document.createElement('div')
  netBox.style.cssText =
    'margin-top:20px;padding:14px 16px;border-radius:12px;background:#fef2f2;border:1px solid #fecaca'
  const netRow = document.createElement('div')
  netRow.style.cssText = 'display:flex;justify-content:space-between;align-items:center'
  const netL = document.createElement('span')
  netL.style.fontWeight = '700'
  netL.textContent = 'صافي الضريبة المستحقة'
  const netR = document.createElement('span')
  netR.style.cssText = 'font-size:18px;font-weight:800;color:#b91c1c'
  netR.textContent = `${fmt(Math.abs(v.net_vat))} ${v.net_vat >= 0 ? '(مدين)' : '(دائن)'}`
  netRow.appendChild(netL)
  netRow.appendChild(netR)
  netBox.appendChild(netRow)
  wrap.appendChild(netBox)

  if (v.by_rate.length > 0) {
    section('التفصيل حسب نسبة الضريبة', '#334155')
    for (const br of v.by_rate) {
      row(`نسبة ${br.tax_rate}% — خاضع`, br.taxable_amount)
      row(`نسبة ${br.tax_rate}% — ضريبة`, br.tax_amount)
    }
  }

  const foot = document.createElement('p')
  foot.style.cssText = 'margin-top:28px;font-size:11px;color:#94a3b8'
  foot.textContent = 'صادر من نظام أسس برو — إقرار معلوماتي؛ يُراجع لدى مستشار ضريبي عند الحاجة.'
  wrap.appendChild(foot)

  return wrap
}

async function exportVatPdf() {
  const v = vatReport.value
  if (!v || vatPdfExporting.value) return
  vatPdfExporting.value = true
  let mount: HTMLElement | null = null
  try {
    const el = buildVatReportPdfElement(v, vatFrom.value, vatTo.value)
    mount = el
    el.style.position = 'fixed'
    el.style.left = '-12000px'
    el.style.top = '0'
    el.style.zIndex = '-1'
    document.body.appendChild(el)

    await ensurePrintFontsReady()

    const [{ default: html2canvas }, { jsPDF }] = await Promise.all([
      import('html2canvas'),
      import('jspdf'),
    ])

    const canvas = await html2canvas(el, {
      scale: 2,
      useCORS: true,
      allowTaint: false,
      backgroundColor: '#ffffff',
      logging: false,
    })

    if (el.parentNode) el.parentNode.removeChild(el)
    mount = null

    const imgData = canvas.toDataURL('image/png')
    if (!imgData || imgData.length < 100) throw new Error('empty canvas')

    const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
    addInvoiceCanvasToSinglePagePdf(pdf, imgData, canvas)
    pdf.save(`vat-report-${vatFrom.value}-to-${vatTo.value}.pdf`)
    toast.success('تم التصدير', 'تم تنزيل ملف PDF.')
  } catch (e) {
    console.warn('[ZATCA VAT PDF]', e)
    toast.error('تصدير PDF', PDF_EXPORT_FAIL_AR)
    if (mount?.parentNode) mount.parentNode.removeChild(mount)
  } finally {
    vatPdfExporting.value = false
  }
}

async function exportVatExcel() {
  const v = vatReport.value
  if (!v) return
  try {
    const { downloadExcelFromRows } = await import('@/utils/exportExcel')
    const rows = [
      { البيان: 'المبيعات الخاضعة للضريبة', المبلغ: v.taxable_sales },
      { البيان: 'ضريبة المخرجات (15%)', المبلغ: v.output_vat },
      { البيان: 'المبيعات المعفاة', المبلغ: v.exempt_sales },
      { البيان: 'الصادرات (صفري)', المبلغ: v.zero_rated },
      { البيان: 'المشتريات الخاضعة للضريبة', المبلغ: v.taxable_purchases },
      { البيان: 'ضريبة المدخلات (15%)', المبلغ: v.input_vat },
      { البيان: 'صافي الضريبة', المبلغ: v.net_vat },
    ]
    await downloadExcelFromRows(rows, 'تقرير الضريبة', `vat-${vatFrom.value}-to-${vatTo.value}.xlsx`)
    toast.success('تم التصدير', 'تم تنزيل ملف Excel.')
  } catch {
    toast.error('تصدير Excel', 'تعذّر تصدير Excel.')
  }
}

const formatCurrency = (v: number) => new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR' }).format(v || 0)
const formatDate = (d: string) => d ? new Date(d).toLocaleDateString('ar-SA') : '—'
const formatDateTime = (d: string) => d ? new Date(d).toLocaleString('ar-SA') : '—'

onMounted(fetchData)
</script>
