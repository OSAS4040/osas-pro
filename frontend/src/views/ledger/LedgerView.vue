<template>
  <div class="p-6 space-y-4">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-800">دفتر الأستاذ العام</h1>
      <div class="flex gap-2">
        <input v-model="search" type="text" placeholder="بحث..." class="border rounded-lg px-3 py-2 text-sm" />
        <select v-model="typeFilter" class="border rounded-lg px-3 py-2 text-sm">
          <option value="">جميع الأنواع</option>
          <option value="sale">مبيعات</option>
          <option value="purchase">مشتريات</option>
          <option value="payment">دفعات</option>
          <option value="reversal">إلغاء</option>
          <option value="adjustment">تسوية</option>
        </select>
        <input v-model="fromDate" type="date" class="border rounded-lg px-3 py-2 text-sm" />
        <input v-model="toDate"   type="date" class="border rounded-lg px-3 py-2 text-sm" />
      </div>
    </div>

    <div v-if="loading" class="text-center py-12 text-gray-400">جارٍ التحميل...</div>

    <div v-else class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
          <tr>
            <th class="px-4 py-3 text-right">رقم القيد</th>
            <th class="px-4 py-3 text-right">التاريخ</th>
            <th class="px-4 py-3 text-right">النوع</th>
            <th class="px-4 py-3 text-right">الوصف</th>
            <th class="px-4 py-3 text-right">مدين</th>
            <th class="px-4 py-3 text-right">دائن</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="entry in entries" :key="entry.id" class="hover:bg-gray-50 transition">
            <td class="px-4 py-3 font-mono text-blue-600">{{ entry.entry_number }}</td>
            <td class="px-4 py-3 text-gray-600">{{ formatDate(entry.entry_date) }}</td>
            <td class="px-4 py-3">
              <span :class="typeBadge(entry.type)" class="px-2 py-0.5 rounded-full text-xs font-medium">
                {{ typeLabel(entry.type) }}
              </span>
            </td>
            <td class="px-4 py-3 text-gray-700 max-w-xs truncate">{{ entry.description }}</td>
            <td class="px-4 py-3 text-green-700 font-medium">{{ formatMoney(entry.total_debit) }}</td>
            <td class="px-4 py-3 text-red-600 font-medium">{{ formatMoney(entry.total_credit) }}</td>
            <td class="px-4 py-3">
              <RouterLink :to="{ name: 'ledger.show', params: { id: entry.id } }" class="text-blue-500 hover:underline text-xs">
                تفاصيل
              </RouterLink>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="flex justify-between items-center text-sm text-gray-500">
      <span>إجمالي {{ meta?.total ?? 0 }} قيد</span>
      <div class="flex gap-2">
        <button :disabled="page <= 1"       @click="page--" class="px-3 py-1 border rounded disabled:opacity-40">السابق</button>
        <button :disabled="page >= lastPage" @click="page++" class="px-3 py-1 border rounded disabled:opacity-40">التالي</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/services/api'

const entries   = ref<any[]>([])
const meta      = ref<any>(null)
const loading   = ref(false)
const search    = ref('')
const typeFilter = ref('')
const fromDate  = ref('')
const toDate    = ref('')
const page      = ref(1)
const lastPage  = ref(1)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/ledger', {
      params: {
        search:    search.value || undefined,
        type:      typeFilter.value || undefined,
        from_date: fromDate.value || undefined,
        to_date:   toDate.value   || undefined,
        page:      page.value,
        per_page:  25,
      },
    })
    entries.value  = data.data.data ?? data.data
    meta.value     = data.data.meta ?? data.data
    lastPage.value = data.data.last_page ?? 1
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch([search, typeFilter, fromDate, toDate], () => { page.value = 1; load() })
watch(page, load)

function formatDate(d: string) { return d ? new Date(d).toLocaleDateString('ar-SA') : '—' }
function formatMoney(n: number) { return n ? Number(n).toLocaleString('ar-SA', { style: 'currency', currency: 'SAR' }) : '—' }

function typeLabel(t: string) {
  const map: Record<string, string> = {
    sale: 'مبيعات', purchase: 'مشتريات', payment: 'دفعة',
    reversal: 'إلغاء', adjustment: 'تسوية', wallet_top_up: 'شحن محفظة',
    wallet_debit: 'خصم محفظة', vat_output: 'ضريبة', refund: 'استرجاع',
  }
  return map[t] ?? t
}

function typeBadge(t: string) {
  const map: Record<string, string> = {
    sale: 'bg-green-100 text-green-800',
    purchase: 'bg-blue-100 text-blue-800',
    payment: 'bg-purple-100 text-purple-800',
    reversal: 'bg-red-100 text-red-800',
    adjustment: 'bg-yellow-100 text-yellow-800',
  }
  return map[t] ?? 'bg-gray-100 text-gray-600'
}
</script>
