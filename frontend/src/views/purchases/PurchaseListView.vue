<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-3">
      <h2 class="text-lg font-semibold text-gray-900">أوامر الشراء</h2>
      <div class="flex items-center gap-2 flex-wrap">
        <PurchaseInvoiceScanner @saved="onScannedSaved" />
        <RouterLink to="/purchases/new" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">+ أمر شراء جديد</RouterLink>
      </div>
    </div>

    <div class="flex gap-3">
      <select v-model="filterStatus" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-48" @change="load">
        <option value="">كل الحالات</option>
        <option value="pending">معلق</option>
        <option value="ordered">مطلوب</option>
        <option value="partial">مستلم جزئياً</option>
        <option value="received">مستلم</option>
        <option value="cancelled">ملغي</option>
      </select>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
          <tr>
            <th class="px-4 py-3 text-right">المرجع</th>
            <th class="px-4 py-3 text-right">المورد</th>
            <th class="px-4 py-3 text-right">الإجمالي</th>
            <th class="px-4 py-3 text-right">الحالة</th>
            <th class="px-4 py-3 text-right">التاريخ المتوقع</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="p in purchases" :key="p.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-mono font-semibold text-sm text-right">{{ p.reference_number }}</td>
            <td class="px-4 py-3 text-gray-600 text-right">{{ p.supplier?.name ?? '—' }}</td>
            <td class="px-4 py-3 text-right font-medium">{{ Number(p.total).toFixed(2) }} ر.س</td>
            <td class="px-4 py-3 text-right">
              <span :class="statusClass(p.status)" class="px-2 py-0.5 rounded-full text-xs">{{ statusLabel(p.status) }}</span>
            </td>
            <td class="px-4 py-3 text-gray-400 text-xs text-right">{{ p.expected_at?.slice(0, 10) ?? '—' }}</td>
            <td class="px-4 py-3 text-left">
              <RouterLink :to="`/purchases/${p.id}`" class="text-primary-600 hover:underline text-xs">عرض</RouterLink>
            </td>
          </tr>
          <tr v-if="!purchases.length">
            <td colspan="6" class="px-4 py-8 text-center text-gray-400">لا توجد أوامر شراء.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta" class="flex justify-end gap-2 text-sm">
      <button :disabled="meta.current_page <= 1" class="px-3 py-1 border rounded disabled:opacity-40" @click="changePage(meta.current_page - 1)">السابق</button>
      <span class="py-1 px-2 text-gray-500">{{ meta.current_page }} / {{ meta.last_page }}</span>
      <button :disabled="meta.current_page >= meta.last_page" class="px-3 py-1 border rounded disabled:opacity-40" @click="changePage(meta.current_page + 1)">التالي</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import apiClient from '@/lib/apiClient'
import PurchaseInvoiceScanner from '@/components/PurchaseInvoiceScanner.vue'

const route        = useRoute()
const purchases    = ref<any[]>([])
const filterStatus = ref('')

function onScannedSaved() { load() }
const meta         = ref<any>(null)
const page         = ref(1)

async function load() {
  const params: Record<string, any> = { page: page.value, per_page: 25 }
  if (filterStatus.value) params.status = filterStatus.value
  if (route.query.supplier_id) params.supplier_id = route.query.supplier_id
  const { data } = await apiClient.get('/purchases', { params })
  purchases.value = data.data.data ?? data.data
  meta.value = data.data.meta ?? null
}

function changePage(p: number) { page.value = p; load() }

function statusClass(s: string): string {
  const m: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-700', ordered: 'bg-blue-100 text-blue-700',
    partial: 'bg-orange-100 text-orange-700', received: 'bg-green-100 text-green-700',
    cancelled: 'bg-red-100 text-red-600',
  }
  return m[s] ?? 'bg-gray-100 text-gray-500'
}

function statusLabel(s: string): string {
  const m: Record<string, string> = {
    pending: 'معلق', ordered: 'مطلوب', partial: 'مستلم جزئياً', received: 'مستلم', cancelled: 'ملغي',
  }
  return m[s] ?? s
}

onMounted(load)
</script>
