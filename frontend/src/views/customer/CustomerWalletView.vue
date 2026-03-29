<template>
  <div class="space-y-4">
    <div>
      <h2 class="text-lg font-bold text-gray-900 dark:text-white">المحفظة</h2>
      <p class="text-xs text-gray-500 mt-0.5">إدارة رصيدك وعمليات الشحن</p>
    </div>

    <!-- Wallet Cards -->
    <div v-if="loading" class="flex justify-center py-8"><div class="spinner"></div></div>
    <div v-else-if="wallets.length" class="space-y-3">
      <div v-for="w in wallets" :key="w.id"
        class="rounded-2xl p-5 text-white shadow-md"
        :class="walletGradient(walletKind(w))"
      >
        <div class="flex items-start justify-between mb-4">
          <div>
            <p class="text-xs text-white/70">{{ walletTypeLabel(walletKind(w)) }}</p>
            <p class="text-2xl font-bold mt-1">{{ fmt(w.balance) }}</p>
            <p class="text-xs text-white/70 mt-0.5">ر.س</p>
          </div>
          <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
            <CreditCardIcon class="w-5 h-5 text-white" />
          </div>
        </div>
        <div class="flex items-center justify-between text-xs text-white/70">
          <span>محفظة {{ walletKind(w) === 'fleet_vehicle' ? '#' + w.id : 'الشركة' }}</span>
          <span>آخر تحديث: {{ fmtDate(w.updated_at) }}</span>
        </div>
      </div>
    </div>
    <div v-else class="card p-8 text-center text-gray-500">
      <CreditCardIcon class="w-10 h-10 text-gray-300 mx-auto mb-2" />
      <p>لا توجد محافظ مرتبطة بحسابك</p>
    </div>

    <!-- Recent Transactions -->
    <div class="card p-4">
      <h3 class="font-semibold text-gray-800 dark:text-white mb-3">آخر المعاملات</h3>
      <div v-if="txLoading" class="flex justify-center py-4"><div class="spinner"></div></div>
      <div v-else-if="transactions.length" class="space-y-2">
        <div v-for="tx in transactions" :key="tx.id"
          class="flex items-center justify-between p-3 rounded-xl bg-gray-50 dark:bg-slate-700/40">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center"
              :class="txCredit(tx) ? 'bg-green-100 dark:bg-green-900/30' : 'bg-red-100 dark:bg-red-900/30'">
              <span class="text-sm">{{ txCredit(tx) ? '⬆️' : '⬇️' }}</span>
            </div>
            <div>
              <p class="text-sm font-medium text-gray-800 dark:text-white">{{ tx.description ?? tx.transaction_type ?? '—' }}</p>
              <p class="text-xs text-gray-400">{{ fmtDate(tx.created_at) }}</p>
            </div>
          </div>
          <span class="font-semibold text-sm" :class="txCredit(tx) ? 'text-green-600' : 'text-red-600'">
            {{ txCredit(tx) ? '+' : '-' }}{{ fmt(Math.abs(Number(tx.amount))) }}
          </span>
        </div>
      </div>
      <div v-else class="text-center text-gray-400 py-4 text-sm">لا توجد معاملات</div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { CreditCardIcon } from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const loading      = ref(false)
const txLoading    = ref(false)
const wallets      = ref<any[]>([])
const transactions = ref<any[]>([])

function walletGradient(type: string) {
  const map: Record<string, string> = {
    cash:          'bg-gradient-to-br from-blue-500 to-blue-700',
    promotional:   'bg-gradient-to-br from-purple-500 to-purple-700',
    reserved:      'bg-gradient-to-br from-gray-500 to-gray-700',
    fleet:         'bg-gradient-to-br from-teal-500 to-teal-700',
    fleet_vehicle: 'bg-gradient-to-br from-orange-500 to-amber-600',
  }
  return map[type] ?? 'bg-gradient-to-br from-gray-500 to-gray-700'
}

function walletKind(w: Record<string, unknown>): string {
  return String(w.wallet_type ?? w.type ?? 'cash')
}

function txCredit(tx: any): boolean {
  if (tx.direction === 'credit' || tx.type === 'credit') return true
  if (tx.direction === 'debit' || tx.type === 'debit') return false
  const typ = String(tx.transaction_type ?? '').toUpperCase()
  return !['TRANSFER_OUT', 'INVOICE_DEBIT', 'DEBIT', 'ADJUSTMENT_SUB'].some((x) => typ.includes(x))
}

function walletTypeLabel(type: string) {
  const map: Record<string, string> = {
    cash:          'المحفظة النقدية',
    promotional:   'المحفظة الترويجية',
    reserved:      'المبالغ المحجوزة',
    fleet:         'محفظة الأسطول',
    fleet_vehicle: 'محفظة المركبة',
  }
  return map[type] ?? type
}

const fmt = (n: any) =>
  new Intl.NumberFormat('ar-SA', { style: 'decimal', minimumFractionDigits: 2 }).format(parseFloat(n) || 0)

const fmtDate = (d: string) => new Date(d).toLocaleDateString('ar-SA')

async function loadWallets() {
  loading.value = true
  try {
    const { data } = await apiClient.get('/wallet')
    const w = data?.wallets
    wallets.value = Array.isArray(w?.data) ? w.data : (Array.isArray(w) ? w : [])
  } catch { wallets.value = [] } finally { loading.value = false }
}

async function loadTransactions() {
  txLoading.value = true
  try {
    const cid = auth.user?.customer_id
    if (cid == null || Number(cid) < 1) {
      transactions.value = []
      return
    }
    const { data } = await apiClient.get('/wallet/transactions', {
      params: { customer_id: cid, per_page: 20 },
    })
    const pag = data?.data
    transactions.value = Array.isArray(pag?.data) ? pag.data : []
  } catch { transactions.value = [] } finally { txLoading.value = false }
}

onMounted(() => {
  loadWallets()
  loadTransactions()
})
</script>
