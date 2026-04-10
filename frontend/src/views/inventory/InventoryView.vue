<template>
  <div class="app-shell-page space-y-6">
    <div class="page-head">
      <div class="page-title-wrap">
        <h2 class="page-title-xl">المخزون</h2>
        <p class="page-subtitle">عرض الكميات والحجوزات وحدود إعادة الطلب لكل فرع</p>
      </div>
      <div class="page-toolbar">
        <RouterLink
          to="/inventory/units"
          class="btn btn-outline border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
        >
          وحدات القياس
        </RouterLink>
        <RouterLink
          to="/inventory/reservations"
          class="btn btn-outline border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
        >
          الحجوزات
        </RouterLink>
      </div>
    </div>

    <div class="flex gap-3">
      <label class="flex items-center gap-2 body-text">
        <input v-model="filters.low_stock" type="checkbox" class="rounded border-gray-300 dark:border-slate-600" @change="load" />
        المخزون المنخفض فقط
      </label>
    </div>

    <div class="panel overflow-hidden p-0">
      <table class="data-table">
        <thead>
          <tr>
            <th class="text-right">المنتج</th>
            <th class="text-right">الفرع</th>
            <th class="text-right">الوحدة</th>
            <th class="text-right">الكمية</th>
            <th class="text-right">محجوز</th>
            <th class="text-right">المتاح</th>
            <th class="text-right">إعادة الطلب</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="7" class="table-empty py-10">
              <p class="empty-state-description">جارٍ التحميل...</p>
            </td>
          </tr>
          <tr v-for="inv in inventory" :key="inv.id" :class="{ 'bg-red-50 dark:bg-red-950/20': isLow(inv) }">
            <td class="font-medium text-right text-gray-900 dark:text-slate-100">{{ inv.product?.name }}</td>
            <td class="text-right text-sm text-gray-500 dark:text-slate-400">{{ inv.branch?.name }}</td>
            <td class="text-right text-sm text-gray-500 dark:text-slate-400">{{ inv.product?.unit?.symbol }}</td>
            <td class="text-right">{{ inv.quantity }}</td>
            <td class="text-right text-yellow-600 dark:text-yellow-400">{{ inv.reserved_quantity }}</td>
            <td class="text-right font-semibold" :class="isLow(inv) ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'">
              {{ (inv.quantity - inv.reserved_quantity).toFixed(2) }}
            </td>
            <td class="text-right text-gray-400 dark:text-slate-500">{{ inv.reorder_point }}</td>
          </tr>
          <tr v-if="!loading && !inventory.length">
            <td colspan="7" class="table-empty">
              <p class="empty-state-title">لا توجد بيانات مخزون</p>
              <p class="empty-state-description">جرّب إلغاء تصفية المخزون المنخفض أو راجع الفروع</p>
            </td>
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
