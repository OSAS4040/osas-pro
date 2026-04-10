<template>
  <div class="space-y-6">
    <div class="flex items-center gap-3">
      <RouterLink to="/fleet/wallet" class="text-sm text-primary-600 hover:underline">← المحافظ</RouterLink>
      <span class="text-gray-300">/</span>
      <h2 class="text-lg font-bold text-gray-900">سجل المعاملات</h2>
      <span v-if="walletId" class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">#{{ walletId }}</span>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="w-8 h-8 border-2 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 text-sm">{{ error }}</div>

    <!-- Transactions Table -->
    <div v-else class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
          <tr>
            <th class="text-right text-xs font-semibold text-gray-500 px-4 py-3">التاريخ</th>
            <th class="text-right text-xs font-semibold text-gray-500 px-4 py-3">النوع</th>
            <th class="text-right text-xs font-semibold text-gray-500 px-4 py-3">المبلغ</th>
            <th class="text-right text-xs font-semibold text-gray-500 px-4 py-3">الرصيد قبل</th>
            <th class="text-right text-xs font-semibold text-gray-500 px-4 py-3">الرصيد بعد</th>
            <th class="text-right text-xs font-semibold text-gray-500 px-4 py-3">طريقة الدفع</th>
            <th class="text-right text-xs font-semibold text-gray-500 px-4 py-3">ملاحظات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!transactions.length">
            <td colspan="7" class="text-center py-10 text-gray-400 text-sm">لا توجد معاملات بعد.</td>
          </tr>
          <tr
            v-for="txn in transactions"
            :key="txn.id"
            class="border-b border-gray-50 hover:bg-gray-50 transition-colors"
          >
            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ formatDate(txn.created_at) }}</td>
            <td class="px-4 py-3">
              <span class="text-xs px-2 py-1 rounded-full font-medium" :class="txnTypeClass(txn.transaction_type)">
                {{ txnTypeLabel(txn.transaction_type) }}
              </span>
            </td>
            <td class="px-4 py-3 font-semibold" :class="isCredit(txn.transaction_type) ? 'text-green-600' : 'text-red-500'">
              {{ isCredit(txn.transaction_type) ? '+' : '-' }}{{ formatAmount(txn.amount) }}
            </td>
            <td class="px-4 py-3 text-gray-500">{{ formatAmount(txn.balance_before) }}</td>
            <td class="px-4 py-3 text-gray-700 font-medium">{{ formatAmount(txn.balance_after) }}</td>
            <td class="px-4 py-3">
              <span v-if="txn.payment_mode" class="text-xs px-2 py-0.5 rounded-full"
                    :class="txn.payment_mode === 'credit' ? 'bg-amber-100 text-amber-700' : 'bg-blue-50 text-blue-600'"
              >
                {{ txn.payment_mode === 'credit' ? 'ائتمان' : 'مسبق' }}
              </span>
            </td>
            <td class="px-4 py-3 text-gray-400 text-xs max-w-40 truncate">{{ txn.notes }}</td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="meta?.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
        <button
          :disabled="meta.current_page <= 1"
          class="text-xs text-primary-600 disabled:text-gray-300 hover:underline"
          @click="loadPage(meta.current_page - 1)"
        >
          السابق
        </button>
        <span class="text-xs text-gray-400">صفحة {{ meta.current_page }} من {{ meta.last_page }}</span>
        <button
          :disabled="meta.current_page >= meta.last_page"
          class="text-xs text-primary-600 disabled:text-gray-300 hover:underline"
          @click="loadPage(meta.current_page + 1)"
        >
          التالي
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

const API   = '/api/v1'
const token = () => localStorage.getItem('auth_token') ?? ''
const route = useRoute()

const walletId    = route.params.walletId as string
const loading     = ref(false)
const error       = ref('')
const transactions = ref<any[]>([])
const meta        = ref<any>(null)

const LABELS: Record<string, string> = {
  TOP_UP: 'شحن رصيد',
  TRANSFER_OUT: 'تحويل صادر',
  TRANSFER_IN: 'تحويل وارد',
  INVOICE_DEBIT: 'خصم فاتورة',
  REFUND: 'استرداد',
  ADJUSTMENT_ADD: 'تسوية +',
  ADJUSTMENT_SUB: 'تسوية -',
  REVERSAL: 'عكس عملية',
}

const CREDIT_TYPES = new Set(['TOP_UP', 'TRANSFER_IN', 'REFUND', 'ADJUSTMENT_ADD'])

function txnTypeLabel(t: string): string { return LABELS[t] ?? t }

function txnTypeClass(t: string): string {
  if (CREDIT_TYPES.has(t)) return 'bg-green-100 text-green-700'
  if (t === 'REVERSAL') return 'bg-gray-100 text-gray-600'
  return 'bg-red-100 text-red-600'
}

function isCredit(t: string): boolean { return CREDIT_TYPES.has(t) }

function formatAmount(v: number): string {
  return (v ?? 0).toLocaleString('ar-SA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function formatDate(d: string): string {
  return new Date(d).toLocaleString('ar-SA', { dateStyle: 'short', timeStyle: 'short' })
}

async function loadPage(page = 1) {
  loading.value = true
  error.value   = ''
  try {
    const q = new URLSearchParams({ wallet_id: String(walletId), page: String(page), per_page: '50' })
    const res = await fetch(`${API}/wallet/transactions?${q}`, {
      headers: { Authorization: `Bearer ${token()}` },
    })
    if (!res.ok) throw new Error('فشل تحميل المعاملات')
    const json = await res.json()
    const pag = json.data
    transactions.value = Array.isArray(pag?.data) ? pag.data : []
    meta.value = pag
      ? { current_page: pag.current_page, last_page: pag.last_page }
      : null
  } catch (e: any) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

onMounted(() => loadPage())
</script>
