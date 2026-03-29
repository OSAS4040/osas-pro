<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-gray-900">المخزون</h2>
      <div class="flex gap-2">
        <RouterLink to="/inventory/units" class="px-3 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">
          وحدات القياس
        </RouterLink>
        <RouterLink to="/inventory/reservations" class="px-3 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">
          الحجوزات
        </RouterLink>
      </div>
    </div>

    <div class="flex gap-3">
      <label class="flex items-center gap-2 text-sm">
        <input v-model="filters.low_stock" type="checkbox" class="rounded" @change="load" />
        المخزون المنخفض فقط
      </label>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
          <tr>
            <th class="px-4 py-3 text-right">المنتج</th>
            <th class="px-4 py-3 text-right">الفرع</th>
            <th class="px-4 py-3 text-right">الوحدة</th>
            <th class="px-4 py-3 text-right">الكمية</th>
            <th class="px-4 py-3 text-right">محجوز</th>
            <th class="px-4 py-3 text-right">المتاح</th>
            <th class="px-4 py-3 text-right">إعادة الطلب</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-if="loading">
            <td colspan="7" class="px-4 py-8 text-center text-gray-400">جارٍ التحميل...</td>
          </tr>
          <tr v-for="inv in inventory" :key="inv.id" :class="{ 'bg-red-50': isLow(inv) }">
            <td class="px-4 py-3 font-medium text-right">{{ inv.product?.name }}</td>
            <td class="px-4 py-3 text-gray-500 text-right">{{ inv.branch?.name }}</td>
            <td class="px-4 py-3 text-gray-500 text-right">{{ inv.product?.unit?.symbol }}</td>
            <td class="px-4 py-3 text-right">{{ inv.quantity }}</td>
            <td class="px-4 py-3 text-right text-yellow-600">{{ inv.reserved_quantity }}</td>
            <td class="px-4 py-3 text-right font-semibold" :class="isLow(inv) ? 'text-red-600' : 'text-green-600'">
              {{ (inv.quantity - inv.reserved_quantity).toFixed(2) }}
            </td>
            <td class="px-4 py-3 text-right text-gray-400">{{ inv.reorder_point }}</td>
          </tr>
          <tr v-if="!loading && !inventory.length">
            <td colspan="7" class="px-4 py-8 text-center text-gray-400">لا توجد بيانات مخزون.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import apiClient from '@/lib/apiClient'

const inventory = ref<any[]>([])
const loading = ref(false)
const filters = ref({ low_stock: false })

async function load() {
  loading.value = true
  try {
    const params: Record<string, any> = {}
    if (filters.value.low_stock) params.low_stock = 1
    const { data } = await apiClient.get('/inventory', { params })
    inventory.value = data.data.data ?? data.data
  } finally {
    loading.value = false
  }
}

onMounted(load)

function isLow(inv: any): boolean {
  return (inv.quantity - inv.reserved_quantity) <= inv.reorder_point
}
</script>
