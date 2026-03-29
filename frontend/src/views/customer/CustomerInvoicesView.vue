<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-bold text-gray-900">فواتيري</h2>
        <p class="text-xs text-gray-400">سجل فواتيرك ومدفوعاتك</p>
      </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
      <div v-if="loading" class="py-10 text-center text-gray-400 text-sm">جارٍ التحميل...</div>
      <div v-else-if="!invoices.length" class="py-10 text-center text-gray-400 text-sm">لا توجد فواتير بعد</div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-right text-xs text-gray-500">
            <tr>
              <th class="px-4 py-3 font-medium">رقم الفاتورة</th>
              <th class="px-4 py-3 font-medium">التاريخ</th>
              <th class="px-4 py-3 font-medium">الإجمالي</th>
              <th class="px-4 py-3 font-medium">الحالة</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="inv in invoices" :key="inv.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-4 py-3 font-semibold text-orange-600">{{ inv.invoice_number }}</td>
              <td class="px-4 py-3 text-gray-500">{{ fmtDate(inv.issue_date) }}</td>
              <td class="px-4 py-3 font-semibold text-gray-800">{{ fmt(inv.total) }} ر.س</td>
              <td class="px-4 py-3">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                  :class="inv.status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700'">
                  {{ inv.status === 'paid' ? 'مدفوعة' : 'معلقة' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import apiClient from '@/lib/apiClient'

const loading  = ref(true)
const invoices = ref<any[]>([])

async function load() {
  loading.value = true
  try {
    const { data } = await apiClient.get('/invoices', { params: { per_page: 50 } })
    invoices.value = data.data ?? []
  } catch { /* silent */ } finally { loading.value = false }
}

function fmt(v: any) { return Number(v ?? 0).toLocaleString('ar-SA', { minimumFractionDigits: 2 }) }
function fmtDate(d: string) { return d ? new Date(d).toLocaleDateString('ar-SA') : '—' }

onMounted(load)
</script>
