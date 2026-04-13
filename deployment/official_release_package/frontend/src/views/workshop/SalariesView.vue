<template>
  <div class="space-y-6 print-container" dir="rtl">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3 no-print">
      <h2 class="text-2xl font-bold text-gray-900 dark:text-slate-100">مسير الرواتب</h2>
      <div class="flex items-center flex-wrap gap-2">
        <button type="button" class="flex items-center gap-1.5 px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-lg text-sm text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors"
                @click="saveSnapshot"
        >
          <BookmarkIcon class="w-4 h-4" /> حفظ لقطة
        </button>
        <button type="button" class="flex items-center gap-1.5 px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-lg text-sm text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors"
                @click="sharePayroll"
        >
          <ShareIcon class="w-4 h-4" /> مشاركة
        </button>
        <button class="flex items-center gap-1.5 px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-lg text-sm text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors"
                @click="exportExcel"
        >
          <TableCellsIcon class="w-4 h-4" /> Excel
        </button>
        <button class="flex items-center gap-1.5 px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-lg text-sm text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors"
                @click="exportPdf"
        >
          <DocumentArrowDownIcon class="w-4 h-4" /> طباعة / PDF
        </button>
        <button class="flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm font-medium"
                @click="openGenerateModal"
        >
          <PlusIcon class="w-4 h-4" />
          إنشاء مسير شهري
        </button>
      </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 no-print">
      <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-200 dark:border-slate-700 text-center transition-colors">
        <p class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ formatNum(totalNetSalaries) }}</p>
        <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">إجمالي الرواتب (ر.س)</p>
      </div>
      <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-200 dark:border-slate-700 text-center transition-colors">
        <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ payroll.length }}</p>
        <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">عدد الموظفين</p>
      </div>
      <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-200 dark:border-slate-700 text-center transition-colors">
        <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ disbursementRate }}%</p>
        <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">نسبة الصرف</p>
      </div>
    </div>

    <!-- Month / Year Filter -->
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4 flex gap-3 flex-wrap items-end no-print transition-colors">
      <div>
        <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">الشهر</label>
        <select v-model="filterMonth" class="border border-gray-300 dark:border-slate-600 dark:bg-slate-900 rounded-lg px-3 py-2 text-sm focus:outline-none text-gray-900 dark:text-slate-100" @change="load">
          <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">السنة</label>
        <select v-model="filterYear" class="border border-gray-300 dark:border-slate-600 dark:bg-slate-900 rounded-lg px-3 py-2 text-sm focus:outline-none text-gray-900 dark:text-slate-100" @change="load">
          <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="no-print flex justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
    </div>

    <!-- Table -->
    <div v-else class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden transition-colors">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-slate-900/50 border-b border-gray-200 dark:border-slate-700">
            <tr>
              <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-slate-300">الموظف</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-slate-300">الراتب الأساسي</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-slate-300">العلاوات</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-slate-300">الخصومات</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-slate-300">العمولات</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-slate-300">صافي الراتب</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-slate-300">الحالة</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-slate-300 no-print">طباعة</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="row in payroll" :key="row.id" class="hover:bg-gray-50 dark:hover:bg-slate-900/40 transition-colors">
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/50 flex items-center justify-center text-primary-700 dark:text-primary-300 font-bold text-xs flex-shrink-0">
                    {{ row.employee_name?.charAt(0) ?? '؟' }}
                  </div>
                  <div>
                    <p class="font-medium text-gray-900 dark:text-slate-100">{{ row.employee_name ?? `#${row.employee_id}` }}</p>
                    <p v-if="row.employee_code" class="text-xs text-gray-400 dark:text-slate-500">{{ row.employee_code }}</p>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3 text-gray-700 dark:text-slate-300">{{ formatNum(row.base_salary) }} <span class="text-gray-400 text-xs">ر.س</span></td>
              <td class="px-4 py-3 text-green-700 dark:text-green-400 font-medium">{{ formatNum(row.allowances ?? 0) }} <span class="text-gray-400 text-xs">ر.س</span></td>
              <td class="px-4 py-3 text-red-600 dark:text-red-400 font-medium">{{ formatNum(row.deductions ?? 0) }} <span class="text-gray-400 text-xs">ر.س</span></td>
              <td class="px-4 py-3 text-blue-700 dark:text-blue-400 font-medium">{{ formatNum(row.commissions ?? 0) }} <span class="text-gray-400 text-xs">ر.س</span></td>
              <td class="px-4 py-3 font-bold text-primary-700 dark:text-primary-400">{{ formatNum(row.net_salary ?? calcNet(row)) }} <span class="text-gray-400 text-xs font-normal">ر.س</span></td>
              <td class="px-4 py-3">
                <span :class="payrollStatusBadge(row.status)" class="px-2 py-0.5 rounded-full text-xs font-medium">
                  {{ payrollStatusLabel(row.status) }}
                </span>
              </td>
              <td class="px-4 py-3 no-print">
                <button class="p-1.5 text-gray-500 dark:text-slate-400 hover:text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/30 rounded-lg transition-colors"
                        @click="printRow(row)"
                >
                  <PrinterIcon class="w-4 h-4" />
                </button>
              </td>
            </tr>
            <tr v-if="!payroll.length">
              <td colspan="8" class="text-center py-10 text-gray-400">لا توجد بيانات مسير رواتب لهذا الشهر</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal: إنشاء مسير شهري -->
    <div v-if="showModal" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4 no-print" @click.self="showModal = false">
      <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-md shadow-xl border border-gray-200 dark:border-slate-700">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-slate-700">
          <h3 class="font-bold text-lg text-gray-900 dark:text-slate-100">إنشاء مسير رواتب شهري</h3>
          <button class="text-gray-400 hover:text-gray-700" @click="showModal = false"><XMarkIcon class="w-5 h-5" /></button>
        </div>
        <div class="p-6 space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">الشهر *</label>
              <select v-model="genForm.month" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">السنة *</label>
              <select v-model="genForm.year" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
              </select>
            </div>
          </div>
          <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-sm text-blue-800">
            <p class="font-semibold mb-1">تأكيد إنشاء المسير</p>
            <p class="text-xs text-blue-600">
              سيتم إنشاء مسير الرواتب لجميع الموظفين النشطين لشهر
              <strong>{{ months.find(m => m.value === genForm.month)?.label }} {{ genForm.year }}</strong>.
              تأكد من مراجعة الحضور والعمولات قبل المتابعة.
            </p>
          </div>
          <div v-if="genError" class="text-red-600 text-sm bg-red-50 rounded-lg p-3">{{ genError }}</div>
          <div class="flex gap-3 justify-end pt-1">
            <button type="button" class="px-4 py-2 border rounded-lg text-sm text-gray-700 hover:bg-gray-50" @click="showModal = false">إلغاء</button>
            <button :disabled="generating" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50"
                    @click="generate"
            >
              {{ generating ? 'جاري الإنشاء...' : 'إنشاء المسير' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import {
  PlusIcon,
  XMarkIcon,
  PrinterIcon,
  TableCellsIcon,
  DocumentArrowDownIcon,
  BookmarkIcon,
  ShareIcon,
} from '@heroicons/vue/24/outline'
import { useApi } from '@/composables/useApi'
import { PRINT_HTML_FONT_LINKS, PRINT_HTML_FONT_FAMILY } from '@/design/printHtml'
import { printDocument } from '@/composables/useAppPrint'

const { get, post } = useApi()

const payroll = ref<any[]>([])
const loading = ref(true)
const showModal = ref(false)
const generating = ref(false)
const genError = ref('')

const now = new Date()
const filterMonth = ref(now.getMonth() + 1)
const filterYear = ref(now.getFullYear())

const genForm = ref({ month: now.getMonth() + 1, year: now.getFullYear() })

const months = [
  { value: 1, label: 'يناير' },
  { value: 2, label: 'فبراير' },
  { value: 3, label: 'مارس' },
  { value: 4, label: 'أبريل' },
  { value: 5, label: 'مايو' },
  { value: 6, label: 'يونيو' },
  { value: 7, label: 'يوليو' },
  { value: 8, label: 'أغسطس' },
  { value: 9, label: 'سبتمبر' },
  { value: 10, label: 'أكتوبر' },
  { value: 11, label: 'نوفمبر' },
  { value: 12, label: 'ديسمبر' },
]

const years = Array.from({ length: 5 }, (_, i) => now.getFullYear() - i)

const totalNetSalaries = computed(() =>
  payroll.value.reduce((sum, r) => sum + Number(r.net_salary ?? calcNet(r)), 0)
)

const disbursementRate = computed(() => {
  if (!payroll.value.length) return 0
  const paid = payroll.value.filter(r => r.status === 'paid').length
  return Math.round((paid / payroll.value.length) * 100)
})

function calcNet(row: any): number {
  return (Number(row.base_salary) || 0) + (Number(row.allowances) || 0) + (Number(row.commissions) || 0) - (Number(row.deductions) || 0)
}

function formatNum(n: number | string) {
  return Number(n || 0).toLocaleString('ar-SA')
}

function payrollStatusLabel(s: string) {
  return { paid: 'مصروف', pending: 'معلق', draft: 'مسودة' }[s] ?? s ?? 'مسودة'
}

function payrollStatusBadge(s: string) {
  return {
    paid: 'bg-green-100 text-green-700',
    pending: 'bg-yellow-100 text-yellow-700',
    draft: 'bg-gray-100 text-gray-600',
  }[s] ?? 'bg-gray-100 text-gray-600'
}

function openGenerateModal() {
  genForm.value = { month: filterMonth.value, year: filterYear.value }
  genError.value = ''
  showModal.value = true
}

async function load() {
  loading.value = true
  try {
    const res = await get('/governance/salaries', { month: filterMonth.value, year: filterYear.value })
    payroll.value = res?.data ?? res ?? []
  } finally {
    loading.value = false
  }
}

async function generate() {
  generating.value = true
  genError.value = ''
  try {
    await post('/governance/salaries', { month: genForm.value.month, year: genForm.value.year })
    filterMonth.value = genForm.value.month
    filterYear.value = genForm.value.year
    showModal.value = false
    await load()
  } catch (e: any) {
    genError.value = e?.response?.data?.message ?? 'حدث خطأ أثناء إنشاء المسير'
  } finally {
    generating.value = false
  }
}

function printRow(row: any) {
  const content = `
    <!DOCTYPE html><html dir="rtl"><head><meta charset="utf-8"/><title>مسير راتب</title>${PRINT_HTML_FONT_LINKS}
    <style>body{margin:0;padding:20px;font-family:${PRINT_HTML_FONT_FAMILY}}</style></head><body>
    <h2>مسير راتب - ${row.employee_name ?? ''}</h2>
    <p>الشهر: ${months.find(m => m.value === filterMonth.value)?.label} ${filterYear.value}</p>
    <table border="1" cellpadding="8" style="border-collapse:collapse;width:100%">
      <tr><th>الراتب الأساسي</th><td>${formatNum(row.base_salary)} ر.س</td></tr>
      <tr><th>العلاوات</th><td>${formatNum(row.allowances ?? 0)} ر.س</td></tr>
      <tr><th>العمولات</th><td>${formatNum(row.commissions ?? 0)} ر.س</td></tr>
      <tr><th>الخصومات</th><td>${formatNum(row.deductions ?? 0)} ر.س</td></tr>
      <tr><th>صافي الراتب</th><td><strong>${formatNum(row.net_salary ?? calcNet(row))} ر.س</strong></td></tr>
    </table>
    </body></html>
  `
  const w = window.open('', '_blank')
  if (!w) return
  w.document.write(content)
  w.document.close()
  void (async () => {
    try {
      await w.document.fonts?.ready
    } catch {
      /* ignore */
    }
    w.print()
  })()
}

async function exportExcel() {
  const rows = payroll.value.map((r) => ({
    الموظف: r.employee_name ?? r.employee_id,
    'الراتب الأساسي': Number(r.base_salary ?? 0),
    العلاوات: Number(r.allowances ?? 0),
    الخصومات: Number(r.deductions ?? 0),
    العمولات: Number(r.commissions ?? 0),
    'صافي الراتب': Number(r.net_salary ?? calcNet(r)),
    الحالة: payrollStatusLabel(r.status),
  }))
  const { downloadExcelFromRows } = await import('@/utils/exportExcel')
  await downloadExcelFromRows(rows, 'مسير الرواتب', `payroll-${filterYear.value}-${filterMonth.value}.xlsx`)
}

async function exportPdf() {
  await printDocument()
}

const SNAP_PREFIX = 'payroll_snapshot_'

function saveSnapshot() {
  const key = `${SNAP_PREFIX}${filterYear.value}_${filterMonth.value}`
  const payload = {
    savedAt: new Date().toISOString(),
    year: filterYear.value,
    month: filterMonth.value,
    rows: payroll.value,
  }
  localStorage.setItem(key, JSON.stringify(payload))
}

async function sharePayroll() {
  const title = `مسير رواتب ${filterMonth.value}/${filterYear.value}`
  const text = `إجمالي صافي: ${formatNum(totalNetSalaries.value)} ر.س — ${payroll.value.length} موظفاً`
  const url = typeof window !== 'undefined' ? window.location.href : ''
  if (navigator.share) {
    try {
      await navigator.share({ title, text, url })
      return
    } catch {
      /* user cancelled or unsupported */
    }
  }
  try {
    await navigator.clipboard.writeText(`${title}\n${text}\n${url}`)
  } catch {
    /* ignore */
  }
}

onMounted(load)
</script>
