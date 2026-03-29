<template>
  <div class="space-y-6 max-w-[1600px] mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-slate-100">الفواتير</h2>
        <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">إدارة الفواتير والتحصيل والحالات المالية</p>
      </div>
      <div class="flex items-center gap-2 flex-wrap">
        <button
          type="button"
          :disabled="loading || !invoices.length"
          @click="exportExcel"
          class="flex items-center gap-1.5 px-3 py-2 text-sm bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 text-gray-700 dark:text-slate-200 rounded-xl hover:bg-gray-50 dark:hover:bg-slate-700 disabled:opacity-40 transition-colors"
        >
          تصدير Excel
        </button>
        <RouterLink
          to="/invoices/create"
          class="flex items-center gap-1.5 px-4 py-2 text-sm font-semibold bg-primary-600 text-white rounded-xl hover:bg-primary-700 shadow-sm transition-colors"
        >
          + فاتورة جديدة
        </RouterLink>
      </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 p-4 shadow-sm">
      <div class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[180px]">
          <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-1">بحث</label>
          <input
            v-model="filters.search"
            type="text"
            placeholder="رقم الفاتورة أو العميل..."
            class="w-full border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            @input="debouncedLoad"
          />
        </div>
        <div>
          <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-1">الحالة</label>
          <select
            v-model="filters.status"
            class="border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl px-3 py-2 text-sm min-w-[140px] focus:ring-2 focus:ring-primary-500"
            @change="load"
          >
            <option value="">كل الحالات</option>
            <option value="draft">مسودة</option>
            <option value="pending">معلقة</option>
            <option value="paid">مدفوعة</option>
            <option value="partial_paid">مدفوعة جزئياً</option>
            <option value="cancelled">ملغية</option>
            <option value="refunded">مستردة</option>
          </select>
        </div>
        <div>
          <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-1">من</label>
          <input v-model="filters.from" type="date" class="border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl px-3 py-2 text-sm" @change="load" />
        </div>
        <div>
          <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-1">إلى</label>
          <input v-model="filters.to" type="date" class="border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl px-3 py-2 text-sm" @change="load" />
        </div>
      </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 overflow-hidden shadow-sm">
      <div class="px-4 py-3 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
        <span class="text-sm font-semibold text-gray-800 dark:text-slate-100">قائمة الفواتير</span>
        <span v-if="!loading && pagination" class="text-xs text-gray-400">{{ pagination.total }} فاتورة</span>
      </div>
      <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-slate-700/50 text-xs text-gray-500 dark:text-slate-400">
          <tr>
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
        <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
          <template v-if="loading">
            <tr v-for="n in 6" :key="n">
              <td v-for="c in 9" :key="c" class="px-4 py-3">
                <div class="h-4 bg-gray-100 dark:bg-slate-700 rounded animate-pulse" :style="{ width: c === 1 ? '70%' : c === 9 ? '40%' : '85%' }" />
              </td>
            </tr>
          </template>
          <tr v-for="inv in invoices" v-else :key="inv.id" class="hover:bg-gray-50/80 dark:hover:bg-slate-700/40 transition-colors">
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
              <RouterLink :to="`/invoices/${inv.id}`" class="text-primary-600 dark:text-primary-400 hover:underline text-xs font-medium">عرض</RouterLink>
            </td>
          </tr>
          <tr v-if="!loading && !invoices.length">
            <td colspan="9" class="px-4 py-16 text-center">
              <div class="inline-flex flex-col items-center max-w-sm mx-auto">
                <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mb-3 text-gray-400">
                  <DocumentTextIcon class="w-8 h-8" />
                </div>
                <p class="text-sm font-medium text-gray-600 dark:text-slate-300">لا توجد فواتير مطابقة</p>
                <p class="text-xs text-gray-400 mt-1">جرّب تغيير المرشحات أو إنشاء فاتورة جديدة</p>
                <RouterLink to="/invoices/create" class="mt-4 text-xs font-semibold text-primary-600 hover:underline">+ إنشاء فاتورة</RouterLink>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="pagination" class="flex flex-col sm:flex-row justify-between items-center gap-3 text-sm text-gray-500 dark:text-slate-400">
      <span>{{ pagination.total }} فاتورة — صفحة {{ pagination.current_page }} من {{ pagination.last_page }}</span>
      <div class="flex gap-2">
        <button :disabled="pagination.current_page <= 1" class="px-3 py-1 border rounded disabled:opacity-40" @click="page--; load()">السابق</button>
        <button :disabled="pagination.current_page >= pagination.last_page" class="px-3 py-1 border rounded disabled:opacity-40" @click="page++; load()">التالي</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { DocumentTextIcon } from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import { invoiceStatusClass, invoiceStatusLabel } from '@/utils/financialLabels'

const invoices   = ref<any[]>([])
const loading    = ref(false)
const page       = ref(1)
const pagination = ref<any>(null)
const filters    = ref({ search: '', status: '', from: '', to: '' })

let debounceTimer: ReturnType<typeof setTimeout>
function debouncedLoad() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => { page.value = 1; load() }, 350)
}

async function load() {
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
  } finally {
    loading.value = false
  }
}

function formatDate(dt: string): string {
  if (!dt) return '—'
  return new Date(dt).toLocaleDateString('ar-SA')
}

async function exportExcel() {
  const XLSX = await import('xlsx')
  const rows = invoices.value.map(inv => ({
    'رقم الفاتورة': inv.invoice_number,
    'العميل': inv.customer?.name ?? '—',
    'الإجمالي': inv.total,
    'المدفوع': inv.paid_amount,
    'الحالة': invoiceStatusLabel(inv.status),
    'تاريخ الإصدار': inv.issued_at?.slice(0, 10),
    'تاريخ الاستحقاق': inv.due_at?.slice(0, 10),
  }))
  const ws = XLSX.utils.json_to_sheet(rows)
  const wb = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(wb, ws, 'الفواتير')
  XLSX.writeFile(wb, `invoices_${new Date().toISOString().slice(0, 10)}.xlsx`)
}

onMounted(load)
</script>
