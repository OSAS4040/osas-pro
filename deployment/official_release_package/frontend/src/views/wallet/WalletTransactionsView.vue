<template>
  <div class="wallet-transactions">
    <div class="page-header">
      <button class="btn-back" @click="$router.back()">← Back</button>
      <h1>Wallet Transactions</h1>
    </div>

    <div class="filters">
      <select v-model="filterType" @change="loadTransactions">
        <option value="">All Types</option>
        <option v-for="t in transactionTypes" :key="t" :value="t">{{ t }}</option>
      </select>
    </div>

    <div v-if="loading" class="loading">Loading...</div>

    <table v-else class="txn-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Type</th>
          <th>Amount</th>
          <th>Balance After</th>
          <th>Payment Mode</th>
          <th>Vehicle</th>
          <th>Reference</th>
          <th>Notes</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="txn in transactions" :key="txn.id">
          <td>{{ formatDate(txn.created_at) }}</td>
          <td>
            <span class="type-badge" :class="`type--${txn.transaction_type.toLowerCase()}`">
              {{ txn.transaction_type }}
            </span>
          </td>
          <td :class="isDebit(txn.transaction_type) ? 'amount--debit' : 'amount--credit'">
            {{ isDebit(txn.transaction_type) ? '-' : '+' }}{{ formatAmount(txn.amount) }}
          </td>
          <td>{{ formatAmount(txn.balance_after) }}</td>
          <td>{{ txn.payment_mode ?? '—' }}</td>
          <td>{{ txn.vehicle_id ?? '—' }}</td>
          <td>{{ txn.reference_type ? `${txn.reference_type} #${txn.reference_id}` : '—' }}</td>
          <td>{{ txn.notes ?? '—' }}</td>
        </tr>
      </tbody>
    </table>

    <div v-if="meta" class="pagination">
      <button :disabled="meta.current_page <= 1" @click="page--; loadTransactions()">Prev</button>
      <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
      <button :disabled="meta.current_page >= meta.last_page" @click="page++; loadTransactions()">Next</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { http } from '@/api/http'

interface Transaction {
  id: number
  transaction_type: string
  amount: string
  balance_after: string
  payment_mode: string | null
  vehicle_id: number | null
  reference_type: string | null
  reference_id: number | null
  notes: string | null
  created_at: string
}

interface Meta {
  current_page: number
  last_page: number
}

const route  = useRoute()
const router = useRouter()
const walletId = Number(route.params.walletId)

const loading = ref(false)
const transactions = ref<Transaction[]>([])
const meta = ref<Meta | null>(null)
const page = ref(1)
const filterType = ref('')

const transactionTypes = [
  'TOP_UP', 'TRANSFER_OUT', 'TRANSFER_IN', 'INVOICE_DEBIT',
  'REFUND', 'ADJUSTMENT_ADD', 'ADJUSTMENT_SUB', 'REVERSAL',
]

const debitTypes = new Set(['TRANSFER_OUT', 'INVOICE_DEBIT', 'ADJUSTMENT_SUB'])

function isDebit(type: string): boolean {
  return debitTypes.has(type)
}

function formatDate(dt: string): string {
  return new Date(dt).toLocaleString('en-SA')
}

function formatAmount(amount: string): string {
  return parseFloat(amount).toLocaleString('en-SA', { minimumFractionDigits: 2 })
}

async function loadTransactions(): Promise<void> {
  loading.value = true
  try {
    const params: Record<string, unknown> = { page: page.value }
    if (filterType.value) params.type = filterType.value

    const resp = await http.get('/wallet/transactions', { params: { ...params, wallet_id: walletId } })
    const pag = resp.data?.data
    transactions.value = pag?.data ?? []
    meta.value = pag
      ? { current_page: pag.current_page, last_page: pag.last_page }
      : null
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  if (!Number.isFinite(walletId) || walletId < 1) {
    router.replace({ name: 'wallet' })
    return
  }
  loadTransactions()
})
</script>

<style scoped>
.wallet-transactions { padding: 1.5rem; }
.page-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; }
.btn-back { background: none; border: none; cursor: pointer; color: #3b82f6; font-size: 0.9rem; }
.filters { margin-bottom: 1rem; }
.filters select { padding: 0.375rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; }
.txn-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.txn-table th, .txn-table td { padding: 0.75rem; border-bottom: 1px solid #e5e7eb; text-align: left; }
.txn-table th { background: #f9fafb; font-weight: 600; color: #374151; }
.type-badge { padding: 0.125rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
.type--top_up { background: #d1fae5; color: #065f46; }
.type--invoice_debit { background: #fee2e2; color: #991b1b; }
.type--transfer_out { background: #fef3c7; color: #92400e; }
.type--transfer_in { background: #dbeafe; color: #1e40af; }
.type--reversal { background: #f3e8ff; color: #6b21a8; }
.amount--debit { color: #dc2626; font-weight: 600; }
.amount--credit { color: #16a34a; font-weight: 600; }
.pagination { display: flex; align-items: center; gap: 1rem; margin-top: 1rem; }
.pagination button { padding: 0.375rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; cursor: pointer; }
.pagination button:disabled { opacity: 0.5; cursor: not-allowed; }
.loading { text-align: center; color: #6b7280; padding: 2rem; }
</style>
