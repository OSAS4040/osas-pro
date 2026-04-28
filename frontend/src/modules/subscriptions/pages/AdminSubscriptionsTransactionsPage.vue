<template>
  <div class="space-y-4">
    <h1 class="text-xl font-bold">مراقبة المعاملات البنكية</h1>
    <div class="rounded-xl border bg-white dark:bg-slate-900 overflow-auto">
      <table class="w-full min-w-[1200px] text-sm">
        <thead class="bg-slate-50 dark:bg-slate-800">
          <tr>
            <th class="p-2 text-start">ID</th>
            <th class="p-2 text-start">المبلغ</th>
            <th class="p-2 text-start">العملة</th>
            <th class="p-2 text-start">التاريخ</th>
            <th class="p-2 text-start">اسم المحوّل</th>
            <th class="p-2 text-start">مرجع بنكي</th>
            <th class="p-2 text-start">وصف</th>
            <th class="p-2 text-start">مرجع مستخرج</th>
            <th class="p-2 text-start">مطابقة</th>
            <th class="p-2 text-start">مطابقات</th>
            <th class="p-2 text-start">سجل</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="tx in rows" :key="tx.id" class="border-t border-slate-100 dark:border-slate-800">
            <td class="p-2">{{ tx.id }}</td>
            <td class="p-2" dir="ltr">{{ tx.amount }}</td>
            <td class="p-2">{{ tx.currency }}</td>
            <td class="p-2 text-xs" dir="ltr">{{ tx.transaction_date }}</td>
            <td class="p-2 text-xs">{{ tx.sender_name }}</td>
            <td class="p-2 text-xs font-mono" dir="ltr">{{ tx.bank_reference }}</td>
            <td class="p-2 text-xs max-w-[200px] truncate" :title="tx.description">{{ tx.description }}</td>
            <td class="p-2 text-xs font-mono" dir="ltr">{{ tx.reference_extracted }}</td>
            <td class="p-2">{{ tx.is_matched ? 'matched' : 'unmatched' }}</td>
            <td class="p-2 text-xs">
              <span v-for="m in tx.reconciliation_matches || []" :key="m.id" class="me-1 block">
                PO#{{ m.payment_order_id }} ({{ m.status }})
              </span>
              <span v-if="!(tx.reconciliation_matches || []).length">—</span>
            </td>
            <td class="p-2">
              <RouterLink
                class="text-primary-700 hover:underline dark:text-primary-400"
                :to="{ name: 'admin-subscriptions-bank-tx', params: { id: tx.id } }"
              >
                تفاصيل
              </RouterLink>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <AdminSubscriptionsFullPayload v-if="raw" :payload="raw" />
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { subscriptionsApi } from '../api'
import AdminSubscriptionsFullPayload from '../components/AdminSubscriptionsFullPayload.vue'

const rows = ref<any[]>([])
const raw = ref<unknown>(null)
onMounted(async () => {
  const res = await subscriptionsApi.adminTransactions()
  const p = res.data?.data
  raw.value = p ?? null
  rows.value = Array.isArray(p?.data) ? p.data : Array.isArray(p) ? p : []
})
</script>
