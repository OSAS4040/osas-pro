<template>
  <div class="space-y-4" dir="rtl">
    <div class="flex items-center gap-2">
      <RouterLink :to="`/purchases/${purchaseId}`" class="text-gray-400 hover:text-gray-700 text-sm">← تفاصيل أمر الشراء</RouterLink>
      <span class="text-gray-300">/</span>
      <h2 class="text-lg font-semibold text-gray-900">استلام البضاعة (GRN)</h2>
    </div>

    <div v-if="!purchase" class="text-gray-400 text-sm">جارٍ التحميل...</div>

    <template v-else>
      <div class="bg-white rounded-xl border border-gray-200 p-4 text-sm mb-2">
        <p><span class="text-gray-500">رقم أمر الشراء:</span> <strong>{{ purchase.reference_number }}</strong></p>
        <p><span class="text-gray-500">المورد:</span> {{ purchase.supplier?.name }}</p>
      </div>

      <form class="bg-white rounded-xl border border-gray-200 p-6 space-y-4" @submit.prevent="submit">
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">رقم مذكرة التسليم</label>
            <input v-model="form.delivery_note_number" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="اختياري" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">ملاحظات</label>
            <input v-model="form.notes" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="اختياري" />
          </div>
        </div>

        <table class="w-full text-sm border rounded-lg overflow-hidden">
          <thead class="bg-gray-50 text-xs text-gray-500">
            <tr>
              <th class="px-4 py-2 text-right">البند</th>
              <th class="px-4 py-2 text-right">الكمية المطلوبة</th>
              <th class="px-4 py-2 text-right">المستلم سابقاً</th>
              <th class="px-4 py-2 text-right">الكمية المستلمة الآن</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(line, idx) in receiveLines" :key="line.purchase_item_id" class="border-t">
              <td class="px-4 py-2 text-right">{{ line.name }}</td>
              <td class="px-4 py-2 text-right">{{ line.ordered }}</td>
              <td class="px-4 py-2 text-right text-gray-500">{{ line.alreadyReceived }}</td>
              <td class="px-4 py-2 text-right">
                <input
                  v-model.number="form.items[idx].received_quantity"
                  type="number"
                  :max="line.remaining"
                  min="0"
                  step="0.001"
                  class="w-28 border border-gray-300 rounded px-2 py-1 text-sm text-center"
                />
              </td>
            </tr>
          </tbody>
        </table>

        <div v-if="error" class="text-red-500 text-sm bg-red-50 rounded p-2">{{ error }}</div>
        <div class="flex justify-end gap-2">
          <RouterLink :to="`/purchases/${purchaseId}`" class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">إلغاء</RouterLink>
          <button type="submit" class="px-5 py-2 text-sm bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50" :disabled="saving">
            {{ saving ? 'جارٍ الترحيل...' : 'ترحيل الاستلام (GRN)' }}
          </button>
        </div>
      </form>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import apiClient from '@/lib/apiClient'

const route      = useRoute()
const router     = useRouter()
const purchaseId = computed(() => route.params.id as string)
const purchase   = ref<any>(null)
const saving     = ref(false)
const error      = ref('')

const form = ref<{
  delivery_note_number: string
  notes: string
  items: { purchase_item_id: number; received_quantity: number }[]
}>({ delivery_note_number: '', notes: '', items: [] })

const receiveLines = computed(() =>
  (purchase.value?.items ?? []).map((item: any) => ({
    purchase_item_id: item.id,
    name:             item.name,
    ordered:          item.quantity,
    alreadyReceived:  item.received_quantity,
    remaining:        Math.max(0, item.quantity - item.received_quantity),
  }))
)

onMounted(async () => {
  const { data } = await apiClient.get(`/purchases/${purchaseId.value}`)
  purchase.value = data.data
  form.value.items = purchase.value.items.map((item: any) => ({
    purchase_item_id:  item.id,
    received_quantity: Math.max(0, item.quantity - item.received_quantity),
  }))
})

async function submit() {
  saving.value = true
  error.value = ''
  try {
    const payload = {
      delivery_note_number: form.value.delivery_note_number || undefined,
      notes:                form.value.notes || undefined,
      items: form.value.items.filter(i => i.received_quantity > 0),
    }

    if (!payload.items.length) {
      error.value = 'لم يتم إدخال أي كميات للاستلام.'
      saving.value = false
      return
    }

    const { data } = await apiClient.post(`/purchases/${purchaseId.value}/receipts`, payload)
    router.push(`/goods-receipts/${data.data.id}`)
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'حدث خطأ أثناء ترحيل الاستلام.'
  } finally {
    saving.value = false
  }
}
</script>
