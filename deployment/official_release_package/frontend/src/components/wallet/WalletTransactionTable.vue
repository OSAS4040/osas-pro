<template>
  <div class="table-wrapper">
    <table v-if="transactions.length" class="data-table">
      <thead>
        <tr>
          <th>Type</th>
          <th>Amount</th>
          <th>Balance Before</th>
          <th>Balance After</th>
          <th>Reference</th>
          <th>Trace ID</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="txn in transactions" :key="txn.id">
          <td>
            <span :class="['badge', txnBadgeClass(txn.type)]">{{ txn.type }}</span>
          </td>
          <td :class="isDebit(txn.type) ? 'text-danger' : 'text-success'">
            {{ isDebit(txn.type) ? '-' : '+' }}{{ formatCurrency(txn.amount) }}
          </td>
          <td>{{ formatCurrency(txn.balance_before) }}</td>
          <td>{{ formatCurrency(txn.balance_after) }}</td>
          <td>{{ txn.reference_type ? `${txn.reference_type}#${txn.reference_id}` : '-' }}</td>
          <td class="trace-id">{{ txn.trace_id ?? '-' }}</td>
          <td>{{ formatDate(txn.created_at) }}</td>
        </tr>
      </tbody>
    </table>
    <div v-else class="empty-state">No transactions found.</div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  transactions: any[]
}>()

const DEBIT_TYPES = ['INVOICE_DEBIT', 'ADJUSTMENT_SUB', 'REVERSAL']

function isDebit(type: string): boolean {
  return DEBIT_TYPES.includes(type)
}

function txnBadgeClass(type: string): string {
  if (type === 'TOP_UP') return 'badge--success'
  if (type === 'REFUND') return 'badge--info'
  if (type === 'REVERSAL') return 'badge--warning'
  if (isDebit(type)) return 'badge--danger'
  return 'badge--default'
}

function formatCurrency(value: number | string): string {
  return Number(value).toLocaleString('en-SA', { style: 'currency', currency: 'SAR' })
}

function formatDate(value: string): string {
  return new Date(value).toLocaleString()
}
</script>
