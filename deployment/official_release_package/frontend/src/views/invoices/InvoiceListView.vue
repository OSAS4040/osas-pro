<template>
  <div class="app-shell-page">
    <div class="page-head">
      <div class="page-title-wrap">
        <h2 class="page-title-xl">{{ locale.t('nav.invoices') }}</h2>
        <p class="page-subtitle">{{ l('إدارة الفواتير والتحصيل والحالات المالية', 'Manage invoices, collections, and financial statuses') }}</p>
      </div>
      <div class="page-toolbar">
        <div
          v-if="selectedIds.length"
          class="flex items-center gap-1.5 flex-wrap text-xs border border-primary-200 dark:border-primary-800 bg-primary-50/80 dark:bg-primary-950/40 rounded-xl px-2 py-1.5"
        >
          <span class="text-primary-800 dark:text-primary-200 font-semibold tabular-nums">{{ selectedIds.length }} {{ l('محدد', 'selected') }}</span>
          <button type="button" class="px-2 py-1 rounded-lg bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 hover:bg-gray-50" @click="printSelected">{{ l('طباعة', 'Print') }}</button>
          <button type="button" class="px-2 py-1 rounded-lg bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 hover:bg-gray-50" @click="exportPdfSelected">PDF</button>
          <button type="button" class="px-2 py-1 rounded-lg bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 hover:bg-gray-50" @click="shareSelected">{{ l('مشاركة', 'Share') }}</button>
          <button type="button" class="px-2 py-1 rounded-lg bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 hover:bg-gray-50" @click="saveSelectedExcel">Excel</button>
          <button type="button" class="px-2 py-1 text-red-600 hover:underline" @click="clearSelection">{{ l('إلغاء', 'Clear') }}</button>
        </div>
        <button
          type="button"
          class="flex items-center gap-1.5 px-3 py-2 text-sm bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 text-gray-700 dark:text-slate-200 rounded-xl hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors"
          :title="l('حفظ المرشحات الحالية في المتصفح', 'Save current filters in browser')"
          @click="persistFilters"
        >
          {{ l('حفظ المرشحات', 'Save filters') }}
        </button>
        <button
          type="button"
          :disabled="loading || !invoices.length"
          class="flex items-center gap-1.5 px-3 py-2 text-sm bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 text-gray-700 dark:text-slate-200 rounded-xl hover:bg-gray-50 dark:hover:bg-slate-700 disabled:opacity-40 transition-colors"
          @click="printCurrentPage"
        >
          {{ l('طباعة القائمة', 'Print list') }}
        </button>
        <button
          type="button"
          :disabled="loading || !invoices.length"
          class="flex items-center gap-1.5 px-3 py-2 text-sm bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 text-gray-700 dark:text-slate-200 rounded-xl hover:bg-gray-50 dark:hover:bg-slate-700 disabled:opacity-40 transition-colors"
          @click="exportExcel"
        >
          {{ l('تصدير Excel', 'Export Excel') }}
        </button>
        <RouterLink
          to="/invoices/create"
          class="flex items-center gap-1.5 px-4 py-2 text-sm font-semibold bg-primary-600 text-white rounded-xl hover:bg-primary-700 shadow-sm transition-colors"
        >
          {{ l('+ فاتورة جديدة', '+ New Invoice') }}
        </RouterLink>
      </div>
    </div>

    <div class="table-toolbar">
      <div class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[180px]">
          <label class="filter-field-label">{{ locale.t('common.search') }}</label>
          <input
            v-model="filters.search"
            type="text"
            :placeholder="l('رقم الفاتورة أو العميل...', 'Invoice number or customer...')"
            class="w-full border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            @input="debouncedLoad"
          />
        </div>
        <div>
          <label class="filter-field-label">{{ l('الحالة', 'Status') }}</label>
          <select
            v-model="filters.status"
            class="border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl px-3 py-2 text-sm min-w-[140px] focus:ring-2 focus:ring-primary-500"
            @change="load(true)"
          >
            <option value="">{{ l('كل الحالات', 'All statuses') }}</option>
            <option value="draft">مسودة</option>
            <option value="pending">معلقة</option>
            <option value="paid">مدفوعة</option>
            <option value="partial_paid">مدفوعة جزئياً</option>
            <option value="cancelled">ملغية</option>
            <option value="refunded">مستردة</option>
          </select>
        </div>
        <div>
          <label class="filter-field-label">{{ l('نطاق التاريخ', 'Date range') }}</label>
          <SmartDatePicker
            mode="range"
            :from-value="filters.from"
            :to-value="filters.to"
            @change="onDateFilterChange"
          />
        </div>
      </div>
    </div>

    <div class="table-shell">
      <div class="panel-head">
        <span class="panel-title">{{ l('قائمة الفواتير', 'Invoices list') }}</span>
        <span v-if="!loading && pagination" class="panel-muted">{{ pagination.total }} {{ l('فاتورة', 'invoice(s)') }}</span>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th class="px-2 py-3 w-10 text-center">
              <input type="checkbox" class="rounded border-gray-300" :checked="allSelected" aria-label="تحديد الكل" @change="toggleAll" />
            </th>
            <th class="px-4 py-3 text-right font-semibold">رقم الفاتورة</th>
            <th class="px-4 py-3 text-right font-semibold">العميل</th>
            <th class="px-4 py-3 text-right font-semibold">الفرع</th>
            <th class="px-4 py-3 text-right font-semibold">التاريخ</th>
            <th class="px-4 py-3 text-right font-semibold">الإجمالي</th>
            <th class="px-4 py-3 text-right font-semibold">المدفوع</th>
            <th class="px-4 py-3 text-right font-semibold">المتبقي</th>
            <th class="px-4 py-3 text-right font-semibold">الحالة</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody>
          <template v-if="loading">
            <tr v-for="n in 6" :key="n">
              <td v-for="c in 9" :key="c" class="px-4 py-3">
                <div class="h-4 bg-gray-100 dark:bg-slate-700 rounded animate-pulse" :style="{ width: c === 1 ? '70%' : c === 9 ? '40%' : '85%' }" />
              </td>
            </tr>
          </template>
          <tr v-for="inv in invoices" v-else :key="inv.id" class="hover:bg-gray-50/80 dark:hover:bg-slate-700/40 transition-colors">
            <td class="px-2 py-3 text-center" @click.stop>
              <input type="checkbox" class="rounded border-gray-300" :checked="selectedIds.includes(inv.id)" @change="toggleOne(inv.id)" />
            </td>
            <td class="px-4 py-3 font-mono font-medium text-sm text-right text-gray-900 dark:text-slate-100">{{ inv.invoice_number }}</td>
            <td class="px-4 py-3 text-gray-600 dark:text-slate-300 text-right">{{ inv.customer?.name ?? '—' }}</td>
            <td class="px-4 py-3 text-gray-500 text-xs text-right">{{ inv.branch?.name ?? '—' }}</td>
            <td class="px-4 py-3 text-gray-500 text-xs text-right tabular-nums">{{ formatDate(inv.issued_at) }}</td>
            <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ Number(inv.total).toFixed(2) }}</td>
            <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400 tabular-nums">{{ Number(inv.paid_amount).toFixed(2) }}</td>
            <td class="px-4 py-3 text-right tabular-nums" :class="Number(inv.due_amount) > 0 ? 'text-amber-700 dark:text-amber-400 font-semibold' : 'text-gray-400'">
              {{ Number(inv.due_amount).toFixed(2) }}
            </td>
            <td class="px-4 py-3 text-right">
              <span :class="invoiceStatusClass(inv.status)" class="text-xs px-2 py-0.5 rounded-full font-medium">{{ invoiceStatusLabel(inv.status) }}</span>
            </td>
            <td class="px-4 py-3 text-left">
              <RouterLink :to="`/invoices/${inv.id}`" class="text-primary-600 dark:text-primary-400 hover:underline text-xs font-medium">{{ l('عرض', 'View') }}</RouterLink>
            </td>
          </tr>
          <tr v-if="!loading && !invoices.length">
            <td colspan="10" class="px-4 py-16 text-center">
              <div class="inline-flex flex-col items-center max-w-sm mx-auto">
                <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mb-3 text-gray-400">
                  <DocumentTextIcon class="w-8 h-8" />
                </div>
                <p class="text-sm font-medium text-gray-600 dark:text-slate-300">{{ l('لا توجد فواتير مطابقة', 'No matching invoices') }}</p>
                <p class="text-xs text-gray-400 mt-1">{{ l('جرّب تغيير المرشحات أو إنشاء فاتورة جديدة', 'Try changing filters or create a new invoice') }}</p>
                <RouterLink to="/invoices/create" class="mt-4 text-xs font-semibold text-primary-600 hover:underline">{{ l('+ إنشاء فاتورة', '+ Create invoice') }}</RouterLink>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="pagination" class="table-pagination">
      <span>{{ pagination.total }} {{ l('فاتورة', 'invoice(s)') }} — {{ l('صفحة', 'Page') }} {{ pagination.current_page }} {{ l('من', 'of') }} {{ pagination.last_page }}</span>
      <div class="flex gap-2">
        <button :disabled="pagination.current_page <= 1" class="px-3 py-1 border rounded disabled:opacity-40" @click="page--; load(false)">{{ l('السابق', 'Previous') }}</button>
        <button :disabled="pagination.current_page >= pagination.last_page" class="px-3 py-1 border rounded disabled:opacity-40" @click="page++; load(false)">{{ l('التالي', 'Next') }}</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { DocumentTextIcon } from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import { invoiceStatusClass, invoiceStatusLabel } from '@/utils/financialLabels'
import { useToast } from '@/composables/useToast'
import { logActivity } from '@/composables/useActivityLog'
import SmartDatePicker from '@/components/ui/SmartDatePicker.vue'
import { useLocale } from '@/composables/useLocale'
import { PRINT_HTML_FONT_LINKS, PRINT_HTML_FONT_FAMILY } from '@/design/printHtml'

const FILTERS_KEY = 'invoice_list_filters_v1'
const toast = useToast()
const locale = useLocale()
const l = (ar: string, en: string) => (locale.lang.value === 'ar' ? ar : en)

const invoices   = ref<any[]>([])
const selectedIds = ref<number[]>([])
const loading    = ref(false)
const page       = ref(1)
const pagination = ref<any>(null)
const filters    = ref({ search: '', status: '', from: '', to: '' })

let debounceTimer: ReturnType<typeof setTimeout>
function debouncedLoad() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => { page.value = 1; load(true) }, 350)
}

const allSelected = computed(
  () => invoices.value.length > 0 && invoices.value.every((inv) => selectedIds.value.includes(inv.id)),
)

function toggleAll(e: Event) {
  const on = (e.target as HTMLInputElement).checked
  if (on) selectedIds.value = invoices.value.map((inv) => inv.id)
  else selectedIds.value = []
}

function toggleOne(id: number) {
  const i = selectedIds.value.indexOf(id)
  if (i >= 0) selectedIds.value = selectedIds.value.filter((x) => x !== id)
  else selectedIds.value = [...selectedIds.value, id]
}

function clearSelection() {
  selectedIds.value = []
}

function onDateFilterChange(payload: { from: string; to: string }) {
  filters.value.from = payload.from
  filters.value.to = payload.to
  load(true)
}

function rowsForSelected() {
  return invoices.value.filter((inv) => selectedIds.value.includes(inv.id))
}

function printSelected() {
  const rows = rowsForSelected()
  if (!rows.length) return
  const w = window.open('', '_blank')
  if (!w) {
    toast.error('السماح بالنوافذ المنبثقة لطباعة الفواتير')
    return
  }
  const body = rows
    .map(
      (inv) =>
        `<tr><td>${inv.invoice_number}</td><td>${inv.customer?.name ?? '—'}</td><td>${Number(inv.total).toFixed(2)}</td><td>${invoiceStatusLabel(inv.status)}</td><td>${(inv.issued_at ?? '').slice(0, 10)}</td></tr>`,
    )
    .join('')
  w.document.write(
    `<!DOCTYPE html><html dir="${locale.lang.value === 'ar' ? 'rtl' : 'ltr'}" lang="${locale.lang.value === 'ar' ? 'ar' : 'en'}"><head><meta charset="utf-8"/><title>${l('فواتير', 'Invoices')}</title>${PRINT_HTML_FONT_LINKS}
    <style>body{font-family:${PRINT_HTML_FONT_FAMILY};padding:16px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:8px;text-align:right}th{background:#f3f4f6}</style></head><body>
    <h2>${l('فواتير محددة', 'Selected invoices')} (${rows.length})</h2><table><thead><tr><th>${l('رقم', '#')}</th><th>${l('العميل', 'Customer')}</th><th>${l('الإجمالي', 'Total')}</th><th>${l('الحالة', 'Status')}</th><th>${l('التاريخ', 'Date')}</th></tr></thead><tbody>${body}</tbody></table>
    </body></html>`,
  )
  w.document.close()
  w.focus()
  void (async () => {
    try {
      await w.document.fonts?.ready
    } catch {
      /* ignore */
    }
    w.print()
  })()
  logActivity('طباعة فواتير', `${rows.length} فاتورة`)
}

async function shareSelected() {
  const urls = selectedIds.value.map((id) => `${window.location.origin}/invoices/${id}`)
  const text = urls.join('\n')
  try {
    if (navigator.share) {
      await navigator.share({ title: l('فواتير محددة', 'Selected invoices'), text })
    } else {
      await navigator.clipboard.writeText(text)
      toast.success('تم نسخ روابط الفواتير')
    }
    logActivity('مشاركة فواتير', `${selectedIds.value.length} رابط`)
  } catch {
    await navigator.clipboard.writeText(text).catch(() => {})
    toast.info('تم نسخ الروابط')
  }
}

async function exportPdfSelected() {
  const rows = rowsForSelected()
  if (!rows.length) return
  const { jsPDF } = await import('jspdf')
  const autoTable = (await import('jspdf-autotable')).default
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' })
  doc.setFontSize(14)
  doc.text(`${l('فواتير', 'Invoices')} (${rows.length})`, 14, 16)
  autoTable(doc, {
    startY: 22,
    head: [[l('رقم الفاتورة', 'Invoice #'), l('العميل', 'Customer'), l('الإجمالي', 'Total'), l('المدفوع', 'Paid'), l('المتبقي', 'Due'), l('الحالة', 'Status'), l('التاريخ', 'Date')]],
    body: rows.map((inv) => [
      inv.invoice_number,
      String(inv.customer?.name ?? '—').slice(0, 40),
      Number(inv.total).toFixed(2),
      Number(inv.paid_amount).toFixed(2),
      Number(inv.due_amount).toFixed(2),
      invoiceStatusLabel(inv.status),
      (inv.issued_at ?? '').slice(0, 10),
    ]),
    styles: { fontSize: 8 },
    headStyles: { fillColor: [79, 70, 229] },
  })
  doc.save(`${locale.lang.value === 'ar' ? 'invoices_selected' : 'invoices_selected'}_${new Date().toISOString().slice(0, 10)}.pdf`)
  logActivity('تصدير PDF فواتير', `${rows.length}`)
  toast.success('تم حفظ ملف PDF')
}

function printCurrentPage() {
  const rows = invoices.value
  if (!rows.length) return
  const w = window.open('', '_blank')
  if (!w) {
    toast.error('السماح بالنوافذ المنبثقة للطباعة')
    return
  }
  const body = rows
    .map(
      (inv) =>
        `<tr><td>${inv.invoice_number}</td><td>${inv.customer?.name ?? '—'}</td><td>${Number(inv.total).toFixed(2)}</td><td>${invoiceStatusLabel(inv.status)}</td></tr>`,
    )
    .join('')
  w.document.write(
    `<!DOCTYPE html><html dir="rtl" lang="ar"><head><meta charset="utf-8"/><title>قائمة الفواتير</title>${PRINT_HTML_FONT_LINKS}
    <style>body{font-family:${PRINT_HTML_FONT_FAMILY};padding:16px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:8px;text-align:right}</style></head><body>
    <h2>قائمة الفواتير — الصفحة الحالية</h2><table><thead><tr><th>رقم</th><th>العميل</th><th>الإجمالي</th><th>الحالة</th></tr></thead><tbody>${body}</tbody></table>
    </body></html>`,
  )
  w.document.close()
  w.focus()
  void (async () => {
    try {
      await w.document.fonts?.ready
    } catch {
      /* ignore */
    }
    w.print()
  })()
  logActivity('طباعة قائمة فواتير', `صفحة ${pagination.value?.current_page ?? 1}`)
}

function persistFilters() {
  try {
    localStorage.setItem(FILTERS_KEY, JSON.stringify(filters.value))
    toast.success('تم حفظ المرشحات')
    logActivity('حفظ مرشحات الفواتير')
  } catch {
    toast.error('تعذر الحفظ')
  }
}

function restoreFilters() {
  try {
    const raw = localStorage.getItem(FILTERS_KEY)
    if (!raw) return
    const j = JSON.parse(raw)
    if (j && typeof j === 'object') {
      filters.value = { ...filters.value, ...j }
    }
  } catch {
    /* */
  }
}

async function saveSelectedExcel() {
  const rows = rowsForSelected()
  if (!rows.length) return
  const sheetData = rows.map((inv) => ({
    'رقم الفاتورة': inv.invoice_number,
    'العميل': inv.customer?.name ?? '—',
    'الإجمالي': inv.total,
    'المدفوع': inv.paid_amount,
    'الحالة': invoiceStatusLabel(inv.status),
    'تاريخ الإصدار': inv.issued_at?.slice(0, 10),
  }))
  const { downloadExcelFromRows } = await import('@/utils/exportExcel')
  await downloadExcelFromRows(sheetData, 'محدد', `invoices_selected_${new Date().toISOString().slice(0, 10)}.xlsx`)
  logActivity('تصدير Excel فواتير محددة', `${rows.length}`)
  toast.success('تم حفظ الملف')
}

async function load(resetSelection = true) {
  loading.value = true
  try {
    const params: Record<string, any> = { page: page.value }
    if (filters.value.search) params.search = filters.value.search
    if (filters.value.status) params.status = filters.value.status
    if (filters.value.from)   params.from   = filters.value.from
    if (filters.value.to)     params.to     = filters.value.to
    const { data } = await apiClient.get('/invoices', { params })
    const res = data.data
    invoices.value = res.data ?? res
    if (res.current_page) pagination.value = res
    if (resetSelection) clearSelection()
  } finally {
    loading.value = false
  }
}

function formatDate(dt: string): string {
  if (!dt) return '—'
  return new Date(dt).toLocaleDateString('ar-SA')
}

async function exportExcel() {
  const rows = invoices.value.map((inv) => ({
    'رقم الفاتورة': inv.invoice_number,
    'العميل': inv.customer?.name ?? '—',
    'الإجمالي': inv.total,
    'المدفوع': inv.paid_amount,
    'الحالة': invoiceStatusLabel(inv.status),
    'تاريخ الإصدار': inv.issued_at?.slice(0, 10),
    'تاريخ الاستحقاق': inv.due_at?.slice(0, 10),
  }))
  const { downloadExcelFromRows } = await import('@/utils/exportExcel')
  await downloadExcelFromRows(rows, 'الفواتير', `invoices_${new Date().toISOString().slice(0, 10)}.xlsx`)
  logActivity('تصدير Excel صفحة فواتير', `${rows.length}`)
  toast.success('تم تصدير الصفحة الحالية')
}

onMounted(() => {
  restoreFilters()
  load(true)
})
</script>
