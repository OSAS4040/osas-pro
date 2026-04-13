<template>
  <div class="space-y-4" dir="rtl">
    <div class="flex items-center gap-2">
      <RouterLink :to="`/purchases/${receipt?.purchase_id}`" class="text-gray-400 hover:text-gray-700 text-sm">← أمر الشراء</RouterLink>
      <span class="text-gray-300">/</span>
      <h2 class="text-lg font-semibold text-gray-900">{{ receipt?.grn_number }}</h2>
    </div>

    <div v-if="!receipt" class="text-gray-400 text-sm">جارٍ التحميل...</div>

    <template v-else>
      <div class="grid grid-cols-2 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-1 text-sm">
          <p><span class="text-gray-500">رقم GRN:</span> <strong>{{ receipt.grn_number }}</strong></p>
          <p><span class="text-gray-500">رقم أمر الشراء:</span> {{ receipt.purchase?.reference_number }}</p>
          <p><span class="text-gray-500">المورد:</span> {{ receipt.supplier?.name }}</p>
          <p><span class="text-gray-500">الفرع:</span> {{ receipt.branch?.name ?? '—' }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-1 text-sm">
          <p>
            <span class="text-gray-500">الحالة:</span>
            <span class="mr-1 px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700">مكتمل</span>
          </p>
          <p><span class="text-gray-500">تاريخ الاستلام:</span> {{ receipt.received_at?.slice(0, 16).replace('T', ' ') }}</p>
          <p><span class="text-gray-500">مذكرة التسليم:</span> {{ receipt.delivery_note_number ?? '—' }}</p>
          <p v-if="receipt.notes" class="text-gray-500 italic">{{ receipt.notes }}</p>
        </div>
      </div>

      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-4 py-3 border-b text-sm font-semibold">بنود الاستلام</div>
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-xs text-gray-500">
            <tr>
              <th class="px-4 py-2 text-right">المنتج</th>
              <th class="px-4 py-2 text-right">الكمية المتوقعة</th>
              <th class="px-4 py-2 text-right">المستلم</th>
              <th class="px-4 py-2 text-right">سعر الوحدة</th>
              <th class="px-4 py-2 text-right">حركة المخزون</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="item in receipt.items" :key="item.id">
              <td class="px-4 py-2 text-right">
                <span class="font-medium">{{ item.product?.name ?? item.purchase_item?.name ?? '—' }}</span>
                <span v-if="item.product?.sku" class="text-gray-400 text-xs mr-1">({{ item.product.sku }})</span>
              </td>
              <td class="px-4 py-2 text-right">{{ item.expected_quantity }}</td>
              <td class="px-4 py-2 text-right font-semibold text-green-700">{{ item.received_quantity }}</td>
              <td class="px-4 py-2 text-right">{{ Number(item.unit_cost).toFixed(2) }} ر.س</td>
              <td class="px-4 py-2 text-xs text-gray-500 text-right">
                {{ item.stock_movement_id ? `#${item.stock_movement_id}` : '—' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import apiClient from '@/lib/apiClient'

const route   = useRoute()
const receipt = ref<any>(null)

onMounted(async () => {
  const { data } = await apiClient.get(`/goods-receipts/${route.params.id}`)
  receipt.value = data.data
})
</script>
