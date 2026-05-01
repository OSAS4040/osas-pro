<template>
  <div class="print-container space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-bold text-gray-900">فواتيري</h2>
        <p class="text-xs text-gray-400">سجل فواتيرك ومدفوعاتك</p>
      </div>
      <div class="no-print flex items-center gap-2">
        <button class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-gray-200 text-gray-700 hover:bg-gray-50" @click="exportJSON">JSON</button>
        <button class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-gray-200 text-gray-700 hover:bg-gray-50" @click="exportCSV">CSV</button>
        <button class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-gray-200 text-gray-700 hover:bg-gray-50" @click="printInvoices">PDF</button>
        <button class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-primary-600 text-white hover:bg-primary-700" @click="load">تحديث</button>
      </div>
    </div>

    <div class="no-print grid grid-cols-2 md:grid-cols-4 gap-2">
      <RouterLink to="/customer/work-orders" class="px-3 py-2 rounded-xl border border-gray-200 text-xs font-semibold text-gray-700 hover:bg-primary-50">عملية: أوامر العمل</RouterLink>
      <RouterLink to="/customer/vehicles" class="px-3 py-2 rounded-xl border border-gray-200 text-xs font-semibold text-gray-700 hover:bg-primary-50">عملية: المركبات</RouterLink>
      <RouterLink to="/customer/wallet" class="px-3 py-2 rounded-xl border border-gray-200 text-xs font-semibold text-gray-700 hover:bg-primary-50">عملية: المحفظة والمدفوعات</RouterLink>
      <RouterLink to="/customer/reports" class="px-3 py-2 rounded-xl border border-gray-200 text-xs font-semibold text-gray-700 hover:bg-primary-50">عملية: تقارير الفواتير</RouterLink>
    </div>
    <div class="no-print rounded-xl border border-gray-200 bg-white px-3 py-2 flex flex-wrap items-end gap-2">
      <div class="min-w-[12rem] flex-1">
        <label class="block text-[10px] text-gray-500 mb-1">بحث برقم الفاتورة أو الملاحظات</label>
        <input v-model.trim="searchText" type="search" class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs" placeholder="بحث..." autocomplete="off" />
      </div>
      <div class="min-w-[9rem]">
        <label class="block text-[10px] text-gray-500 mb-1">الحالة</label>
        <select v-model="quickStatus" class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs">
          <option value="">الكل{{ portalLabelAll }}</option>
          <option value="paid">مدفوعة{{ portalLabelPaid }}</option>
          <option value="pending">معلقة{{ portalLabelPending }}</option>
          <option value="overdue">متأخرة{{ portalLabelOverdue }}</option>
        </select>
      </div>
      <div class="min-w-[9rem]">
        <label class="block text-[10px] text-gray-500 mb-1">من تاريخ</label>
        <input v-model="fromDate" type="date" class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs" />
      </div>
      <div class="min-w-[9rem]">
        <label class="block text-[10px] text-gray-500 mb-1">إلى تاريخ</label>
        <input v-model="toDate" type="date" class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs" />
      </div>
      <button
        type="button"
        class="px-2.5 py-1.5 rounded text-xs border border-gray-200 text-gray-700 hover:bg-gray-50"
        @click="clearQuickFilters"
      >
        مسح الفلتر
      </button>
    </div>
    <div
      v-if="demoMode"
      class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800"
    >
      تم تفعيل بيانات الفواتير التجريبية لتمكينك من التحقق السريع.
    </div>
    <div
      v-if="listTotalCount > 0"
      class="no-print rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 flex flex-wrap items-center gap-2"
    >
      <span class="text-xs text-gray-600">المحدد: {{ selectedInvoiceIds.length }}</span>
      <button
        type="button"
        class="px-2.5 py-1 rounded text-xs border border-emerald-200 text-emerald-700 hover:bg-emerald-50 disabled:opacity-50"
        :disabled="!selectedInvoiceIds.length"
        @click="printSelectedInvoices"
      >
        طباعة المحدد
      </button>
      <button
        type="button"
        class="px-2.5 py-1 rounded text-xs border border-primary-200 text-primary-700 hover:bg-primary-50 disabled:opacity-50"
        :disabled="!selectedInvoiceIds.length || batchDownloading"
        @click="downloadSelectedInvoicesPdf"
      >
        {{ batchDownloading ? 'جاري تنزيل المحدد...' : 'تنزيل PDF للمحدد' }}
      </button>
      <button
        type="button"
        class="px-2.5 py-1 rounded text-xs border border-gray-200 text-gray-700 hover:bg-white disabled:opacity-50"
        :disabled="!selectedInvoiceIds.length"
        @click="clearSelection"
      >
        إلغاء التحديد
      </button>
    </div>
    <div v-if="listTotalCount > 0" class="no-print flex items-center justify-between rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs text-gray-500">
      <span>عرض {{ pageStart }} - {{ pageEnd }} من {{ listTotalCount }}</span>
      <div class="flex items-center gap-2">
        <button type="button" class="px-2 py-1 rounded border border-gray-200 disabled:opacity-50" :disabled="currentPage <= 1" @click="goPage(currentPage - 1)">السابق</button>
        <span>صفحة {{ currentPage }} / {{ totalPages }}</span>
        <button type="button" class="px-2 py-1 rounded border border-gray-200 disabled:opacity-50" :disabled="currentPage >= totalPages" @click="goPage(currentPage + 1)">التالي</button>
      </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
      <div v-if="loading" class="py-10 text-center text-gray-400 text-sm">جارٍ التحميل...</div>
      <div v-else-if="!listTotalCount" class="py-10 text-center text-gray-400 text-sm space-y-2">
        <p>لا توجد فواتير بعد</p>
        <div class="no-print flex justify-center gap-2">
          <RouterLink to="/customer/work-orders" class="text-xs text-primary-600 hover:underline">إنشاء أمر عمل يولد فاتورة لاحقًا</RouterLink>
          <span class="text-gray-300">|</span>
          <button class="text-xs text-primary-600 hover:underline" @click="load">تحديث القائمة</button>
        </div>
      </div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-right text-xs text-gray-500">
            <tr>
              <th class="px-4 py-3 font-medium w-12">
                <input
                  type="checkbox"
                  class="rounded border-gray-300"
                  :checked="allSelected"
                  @change="toggleSelectAll"
                />
              </th>
              <th class="px-4 py-3 font-medium">رقم الفاتورة</th>
              <th class="px-4 py-3 font-medium">التاريخ</th>
              <th class="px-4 py-3 font-medium">الإجمالي</th>
              <th class="px-4 py-3 font-medium">الحالة</th>
              <th class="px-4 py-3 font-medium">إجراءات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="inv in tableRows" :key="inv.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-4 py-3">
                <input
                  type="checkbox"
                  class="rounded border-gray-300"
                  :checked="isSelected(inv)"
                  @change="toggleSelection(inv)"
                />
              </td>
              <td class="px-4 py-3 font-semibold text-violet-600">{{ inv.invoice_number }}</td>
              <td class="px-4 py-3 text-gray-500">{{ fmtDate(inv.issue_date || inv.issued_at) }}</td>
              <td class="px-4 py-3 font-semibold text-gray-800">{{ fmt(inv.total) }} ر.س</td>
              <td class="px-4 py-3">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                      :class="inv.status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300'"
                >
                  {{ inv.status === 'paid' ? 'مدفوعة' : 'معلقة' }}
                </span>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <button
                    type="button"
                    class="px-2 py-1 rounded text-xs bg-primary-50 text-primary-700 hover:bg-primary-100"
                    @click="openInvoice(inv)"
                  >
                    عرض الفاتورة
                  </button>
                  <button
                    type="button"
                    class="px-2 py-1 rounded text-xs border border-gray-200 text-gray-700 hover:bg-gray-50"
                    @click="downloadInvoicePdf(inv)"
                  >
                    حفظ PDF
                  </button>
                  <button
                    type="button"
                    class="px-2 py-1 rounded text-xs border border-violet-200 text-violet-700 hover:bg-violet-50"
                    @click="shareInvoice(inv)"
                  >
                    مشاركة
                  </button>
                  <button
                    type="button"
                    class="px-2 py-1 rounded text-xs border border-emerald-200 text-emerald-700 hover:bg-emerald-50"
                    @click="printInvoiceDirect(inv)"
                  >
                    طباعة
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useRouter } from 'vue-router'
import { watchDebounced } from '@vueuse/core'
import apiClient from '@/lib/apiClient'
import { useToast } from '@/composables/useToast'
import { printDocument } from '@/composables/useAppPrint'
import { demoCustomerInvoices } from '@/utils/customerDemoData'
import { useAuthStore } from '@/stores/auth'

const loading = ref(true)
const invoices = ref<any[]>([])
const invoicesTotal = ref(0)
const invoicesLastPage = ref(1)
const portalCounts = ref<Record<string, number>>({})
const demoMode = ref(false)
const toast = useToast()
const router = useRouter()
const auth = useAuthStore()
const selectedInvoiceIds = ref<number[]>([])
const batchDownloading = ref(false)
const quickStatus = ref('')
const fromDate = ref('')
const toDate = ref('')
const searchText = ref('')
const pageSize = 20
const currentPage = ref(1)

function filterDemoList(): typeof demoCustomerInvoices {
  const q = searchText.value.trim().toLowerCase()
  return demoCustomerInvoices.filter((inv) => {
    if (q) {
      const hay = `${inv?.invoice_number ?? ''} ${(inv as any)?.notes ?? ''} ${(inv as any)?.customer_name ?? ''}`.toLowerCase()
      if (!hay.includes(q)) return false
    }
    const statusRaw = String(inv?.status || '').toLowerCase()
    const isPaid = statusRaw === 'paid'
    const isOverdue = statusRaw === 'overdue'
    const isPending = !isPaid && !isOverdue
    if (quickStatus.value === 'paid' && !isPaid) return false
    if (quickStatus.value === 'overdue' && !isOverdue) return false
    if (quickStatus.value === 'pending' && !isPending) return false
    const rawDate = String(inv?.issue_date || (inv as any)?.issued_at || '')
    const invDate = rawDate ? new Date(rawDate) : null
    if (fromDate.value && invDate) {
      const from = new Date(`${fromDate.value}T00:00:00`)
      if (invDate < from) return false
    }
    if (toDate.value && invDate) {
      const to = new Date(`${toDate.value}T23:59:59`)
      if (invDate > to) return false
    }
    return true
  })
}

const listTotalCount = computed(() => (demoMode.value ? filterDemoList().length : invoicesTotal.value))
const totalPages = computed(() => {
  if (demoMode.value) return Math.max(1, Math.ceil(filterDemoList().length / pageSize))
  return Math.max(1, invoicesLastPage.value)
})
const pageStart = computed(() => (listTotalCount.value ? (currentPage.value - 1) * pageSize + 1 : 0))
const pageEnd = computed(() => {
  if (demoMode.value) return Math.min(currentPage.value * pageSize, filterDemoList().length)
  return Math.min((currentPage.value - 1) * pageSize + invoices.value.length, invoicesTotal.value)
})
const tableRows = computed(() => {
  if (demoMode.value) {
    const all = filterDemoList()
    const start = (currentPage.value - 1) * pageSize
    return all.slice(start, start + pageSize)
  }
  return invoices.value
})
const portalLabelAll = computed(() => (!demoMode.value && portalCounts.value.all != null ? ` (${portalCounts.value.all})` : ''))
const portalLabelPaid = computed(() => (!demoMode.value && portalCounts.value.paid != null ? ` (${portalCounts.value.paid})` : ''))
const portalLabelPending = computed(() => (!demoMode.value && portalCounts.value.pending != null ? ` (${portalCounts.value.pending})` : ''))
const portalLabelOverdue = computed(() => (!demoMode.value && portalCounts.value.overdue != null ? ` (${portalCounts.value.overdue})` : ''))

const allSelected = computed(() => {
  if (!tableRows.value.length) return false
  const ids = tableRows.value.map((inv) => invoiceId(inv)).filter((x) => x > 0)
  return ids.length > 0 && ids.every((id) => selectedInvoiceIds.value.includes(id))
})

function goPage(p: number): void {
  const next = Math.max(1, Math.min(p, totalPages.value))
  if (next === currentPage.value) return
  currentPage.value = next
  if (!demoMode.value) void load()
}

async function load(options?: { clampOnly?: boolean }): Promise<void> {
  loading.value = true
  demoMode.value = false
  try {
    const params: Record<string, unknown> = {
      per_page: pageSize,
      page: currentPage.value,
      include_portal_counts: 1,
    }
    if (auth.user?.customer_id != null) params.customer_id = auth.user.customer_id
    const s = searchText.value.trim()
    if (s) params.search = s
    if (fromDate.value) params.from = fromDate.value
    if (toDate.value) params.to = toDate.value
    if (quickStatus.value) params.portal_quick_status = quickStatus.value
    const { data } = await apiClient.get('/invoices', { params })
    const paginator = data?.data
    invoices.value = Array.isArray(paginator?.data) ? paginator.data : []
    invoicesTotal.value = Number(paginator?.total ?? invoices.value.length)
    invoicesLastPage.value = Math.max(1, Number(paginator?.last_page ?? 1))
    if (data?.portal_counts && typeof data.portal_counts === 'object') {
      portalCounts.value = data.portal_counts as Record<string, number>
    }
    if (!options?.clampOnly && !demoMode.value && invoicesLastPage.value >= 1 && currentPage.value > invoicesLastPage.value) {
      currentPage.value = invoicesLastPage.value
      await load({ clampOnly: true })
      return
    }
  } catch {
    invoices.value = demoCustomerInvoices as any[]
    demoMode.value = true
    invoicesTotal.value = demoCustomerInvoices.length
    invoicesLastPage.value = Math.max(1, Math.ceil(demoCustomerInvoices.length / pageSize))
    portalCounts.value = {}
  } finally {
    loading.value = false
  }
}

function fmt(v: any) { return Number(v ?? 0).toLocaleString('ar-SA', { minimumFractionDigits: 2 }) }
function fmtDate(d: string) { return d ? new Date(d).toLocaleDateString('ar-SA') : '—' }

async function fetchAllInvoicesForExport(): Promise<any[]> {
  if (demoMode.value) return filterDemoList()
  const out: any[] = []
  let page = 1
  const perPage = 100
  // حد عملي لتفادي while(true) في قواعد ESLint
  while (page <= 10000) {
    const params: Record<string, unknown> = { per_page: perPage, page, include_portal_counts: 0 }
    if (auth.user?.customer_id != null) params.customer_id = auth.user.customer_id
    if (searchText.value.trim()) params.search = searchText.value.trim()
    if (fromDate.value) params.from = fromDate.value
    if (toDate.value) params.to = toDate.value
    if (quickStatus.value) params.portal_quick_status = quickStatus.value
    const { data } = await apiClient.get('/invoices', { params })
    const paginator = data?.data
    const rows = Array.isArray(paginator?.data) ? paginator.data : []
    out.push(...rows)
    const last = Math.max(1, Number(paginator?.last_page ?? 1))
    if (page >= last || rows.length === 0) break
    page += 1
  }
  return out
}

async function exportCSV(): Promise<void> {
  const allRows = await fetchAllInvoicesForExport()
  if (!allRows.length) {
    toast.warning('لا توجد بيانات', 'لا توجد فواتير للتصدير.')
    return
  }
  const rows = allRows.map((inv) => ({
    invoice_number: inv.invoice_number || inv.id,
    issue_date: fmtDate(inv.issue_date || inv.issued_at),
    total: Number(inv.total ?? 0),
    status: inv.status === 'paid' ? 'مدفوعة' : 'معلقة',
  }))
  const keys = Object.keys(rows[0])
  const csv = [keys.join(','), ...rows.map((r) => keys.map((k) => `"${String((r as any)[k] ?? '').replace(/"/g, '""')}"`).join(','))].join('\n')
  const url = URL.createObjectURL(new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' }))
  const a = document.createElement('a')
  a.href = url
  a.download = 'customer_invoices.csv'
  a.click()
  URL.revokeObjectURL(url)
  toast.success('تم التصدير', 'تم تنزيل ملف CSV.')
}

async function exportJSON(): Promise<void> {
  const allRows = await fetchAllInvoicesForExport()
  if (!allRows.length) {
    toast.warning('لا توجد بيانات', 'لا توجد فواتير للتصدير.')
    return
  }
  const url = URL.createObjectURL(new Blob([JSON.stringify(allRows, null, 2)], { type: 'application/json;charset=utf-8' }))
  const a = document.createElement('a')
  a.href = url
  a.download = 'customer_invoices.json'
  a.click()
  URL.revokeObjectURL(url)
  toast.success('تم التصدير', 'تم تنزيل ملف JSON.')
}

function printInvoices(): void {
  void printDocument({ rootSelector: '.print-container' })
}

function clearQuickFilters(): void {
  quickStatus.value = ''
  fromDate.value = ''
  toDate.value = ''
  searchText.value = ''
  currentPage.value = 1
  if (!demoMode.value) void load()
}
watchDebounced(
  searchText,
  () => {
    currentPage.value = 1
    if (!demoMode.value) void load()
  },
  { debounce: 400 },
)
watch([quickStatus, fromDate, toDate], () => {
  currentPage.value = 1
  if (!demoMode.value) void load()
})
watch(totalPages, (next) => {
  if (currentPage.value > next) currentPage.value = next
})

function openInvoice(inv: any): void {
  const id = Number(inv?.id ?? 0)
  if (!id) {
    toast.warning('تعذّر الفتح', 'معرّف الفاتورة غير صالح.')
    return
  }
  router.push(`/customer/invoices/${id}`)
}

async function downloadInvoicePdf(inv: any, silent = false): Promise<boolean> {
  const id = Number(inv?.id ?? 0)
  if (!id) {
    if (!silent) toast.warning('تعذّر التنزيل', 'معرّف الفاتورة غير صالح.')
    return false
  }
  try {
    const res = await apiClient.get<Blob>(`/invoices/${id}/pdf`, {
      responseType: 'blob',
      skipGlobalErrorToast: true,
      headers: { Accept: 'application/pdf' },
    } as Parameters<typeof apiClient.get>[1])
    const url = URL.createObjectURL(res.data)
    const a = document.createElement('a')
    const fileBase = inv?.invoice_number || `invoice-${id}`
    a.href = url
    a.download = `${String(fileBase).replace(/[^\w.-]+/g, '_')}.pdf`
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(url)
    if (!silent) toast.success('تم التصدير', 'تم تنزيل الفاتورة PDF بنجاح.')
    return true
  } catch {
    if (!silent) toast.error('تعذّر تنزيل الفاتورة', 'يمكنك فتح الفاتورة ثم الطباعة والحفظ PDF.')
    return false
  }
}

async function shareInvoice(inv: any): Promise<void> {
  const id = Number(inv?.id ?? 0)
  if (!id) {
    toast.warning('تعذّرت المشاركة', 'معرّف الفاتورة غير صالح.')
    return
  }
  const invoiceNumber = String(inv?.invoice_number || `invoice-${id}`)
  const shareUrl = `${window.location.origin}/customer/invoices/${id}`
  try {
    if (navigator.share) {
      await navigator.share({
        title: `فاتورة ${invoiceNumber}`,
        text: `رابط الفاتورة رقم ${invoiceNumber}`,
        url: shareUrl,
      })
      toast.success('تمت المشاركة', 'تم إرسال رابط الفاتورة بنجاح.')
      return
    }
    await navigator.clipboard.writeText(shareUrl)
    toast.success('تم النسخ', 'تم نسخ رابط الفاتورة للمشاركة.')
  } catch {
    toast.warning('تعذّرت المشاركة', 'انسخ الرابط يدوياً من صفحة الفاتورة.')
  }
}

function printInvoiceDirect(inv: any): void {
  const id = Number(inv?.id ?? 0)
  if (!id) {
    toast.warning('تعذّرت الطباعة', 'معرّف الفاتورة غير صالح.')
    return
  }
  const printUrl = `${window.location.origin}/customer/invoices/${id}?autoprint=1`
  const win = window.open(printUrl, '_blank', 'noopener,noreferrer')
  if (!win) {
    toast.warning('تعذّر فتح نافذة الطباعة', 'اسمح بالنوافذ المنبثقة ثم أعد المحاولة.')
  }
}

function invoiceId(inv: any): number {
  return Number(inv?.id ?? 0)
}

function isSelected(inv: any): boolean {
  const id = invoiceId(inv)
  return id > 0 && selectedInvoiceIds.value.includes(id)
}

function toggleSelection(inv: any): void {
  const id = invoiceId(inv)
  if (!id) return
  if (selectedInvoiceIds.value.includes(id)) {
    selectedInvoiceIds.value = selectedInvoiceIds.value.filter((x) => x !== id)
    return
  }
  selectedInvoiceIds.value = [...selectedInvoiceIds.value, id]
}

function toggleSelectAll(): void {
  if (allSelected.value) {
    const visibleIds = new Set(tableRows.value.map((inv) => invoiceId(inv)).filter((id) => id > 0))
    selectedInvoiceIds.value = selectedInvoiceIds.value.filter((id) => !visibleIds.has(id))
    return
  }
  const visibleIds = tableRows.value.map((inv) => invoiceId(inv)).filter((id) => id > 0)
  selectedInvoiceIds.value = Array.from(new Set([...selectedInvoiceIds.value, ...visibleIds]))
}

function clearSelection(): void {
  selectedInvoiceIds.value = []
}

function selectedInvoices(): any[] {
  return selectedInvoiceIds.value.map((id) => {
    const row = invoices.value.find((i) => invoiceId(i) === id)
    if (row) return row
    if (demoMode.value) return filterDemoList().find((i) => invoiceId(i) === id) ?? { id }
    return { id }
  })
}

function printSelectedInvoices(): void {
  const list = selectedInvoices()
  if (!list.length) {
    toast.warning('لا يوجد تحديد', 'اختر فاتورة واحدة على الأقل.')
    return
  }
  for (const inv of list) printInvoiceDirect(inv)
}

async function downloadSelectedInvoicesPdf(): Promise<void> {
  const list = selectedInvoices()
  if (!list.length) {
    toast.warning('لا يوجد تحديد', 'اختر فاتورة واحدة على الأقل.')
    return
  }
  batchDownloading.value = true
  let okCount = 0
  for (const inv of list) {
    const ok = await downloadInvoicePdf(inv, true)
    if (ok) okCount += 1
  }
  batchDownloading.value = false
  if (okCount > 0) {
    toast.success('تم تنزيل المحدد', `تم تنزيل ${okCount} ملف PDF.`)
  } else {
    toast.error('فشل تنزيل المحدد', 'تعذر تنزيل الفواتير المحددة.')
  }
}

onMounted(load)
</script>
