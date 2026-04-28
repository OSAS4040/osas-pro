<template>
  <div class="space-y-4">
    <h1 class="text-xl font-bold">فواتير الاشتراك</h1>
    <div class="rounded-xl border bg-white dark:bg-slate-900 overflow-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-800">
          <tr>
            <th class="p-2 text-start">رقم الفاتورة</th>
            <th class="p-2 text-start">الحالة</th>
            <th class="p-2 text-start">الإجمالي</th>
            <th class="p-2 text-start">التاريخ</th>
            <th class="p-2 text-start">PDF</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="inv in invoices" :key="inv.id" class="border-t">
            <td class="p-2">{{ inv.invoice_number }}</td>
            <td class="p-2">{{ inv.status }}</td>
            <td class="p-2">{{ money(Number(inv.total || 0)) }}</td>
            <td class="p-2">{{ formatDate(inv.issued_at) }}</td>
            <td class="p-2">
              <a :href="`/api/v1/invoices/${inv.id}/pdf`" target="_blank" class="text-primary-600">تحميل</a>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { subscriptionsApi } from '../api'

const invoices = ref<any[]>([])
const money = (v: number) => new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR' }).format(v || 0)
const formatDate = (v?: string) => (v ? new Date(v).toLocaleDateString('ar-SA') : '—')

onMounted(async () => {
  const res = await subscriptionsApi.getInvoices()
  invoices.value = res.data?.data ?? []
})
</script>

