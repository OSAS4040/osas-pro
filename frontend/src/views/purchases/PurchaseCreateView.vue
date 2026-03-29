<template>
  <div class="space-y-4" dir="rtl">
    <div class="flex items-center gap-2">
      <RouterLink to="/purchases" class="text-gray-400 hover:text-gray-700 text-sm">← أوامر الشراء</RouterLink>
      <span class="text-gray-300">/</span>
      <h2 class="text-lg font-semibold text-gray-900">أمر شراء جديد</h2>
    </div>

    <form class="bg-white rounded-xl border border-gray-200 p-6 space-y-5" @submit.prevent="submit">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">المورد <span class="text-red-500">*</span></label>
          <select v-model="form.supplier_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
            <option value="">اختر موردًا...</option>
            <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">تاريخ التسليم المتوقع</label>
          <input v-model="form.expected_at" type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
        </div>
        <div class="col-span-2">
          <label class="block text-xs font-medium text-gray-600 mb-1">ملاحظات</label>
          <textarea v-model="form.notes" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" rows="2" />
        </div>
      </div>

      <div>
        <div class="flex items-center justify-between mb-2">
          <h3 class="text-sm font-semibold text-gray-700">البنود</h3>
          <button type="button" class="text-sm text-primary-600 hover:underline" @click="addItem">+ إضافة بند</button>
        </div>
        <table class="w-full text-sm border rounded-lg overflow-hidden">
          <thead class="bg-gray-50 text-xs text-gray-500">
            <tr>
              <th class="px-3 py-2 text-right">الاسم</th>
              <th class="px-3 py-2 text-right">الرمز (SKU)</th>
              <th class="px-3 py-2 text-right">الكمية</th>
              <th class="px-3 py-2 text-right">سعر الوحدة</th>
              <th class="px-3 py-2 text-right">الضريبة %</th>
              <th class="px-3 py-2 text-right">الإجمالي</th>
              <th class="px-3 py-2"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, idx) in form.items" :key="idx" class="border-t">
              <td class="px-3 py-2">
                <input v-model="item.name" type="text" class="w-full border border-gray-300 rounded px-2 py-1 text-sm" required placeholder="اسم البند" />
              </td>
              <td class="px-3 py-2">
                <input v-model="item.sku" type="text" class="w-28 border border-gray-300 rounded px-2 py-1 text-sm" placeholder="اختياري" />
              </td>
              <td class="px-3 py-2">
                <input v-model.number="item.quantity" type="number" min="0.001" step="0.001" class="w-24 border border-gray-300 rounded px-2 py-1 text-sm text-center" required />
              </td>
              <td class="px-3 py-2">
                <input v-model.number="item.unit_cost" type="number" min="0" step="0.01" class="w-28 border border-gray-300 rounded px-2 py-1 text-sm text-center" required />
              </td>
              <td class="px-3 py-2">
                <input v-model.number="item.tax_rate" type="number" min="0" max="100" class="w-20 border border-gray-300 rounded px-2 py-1 text-sm text-center" />
              </td>
              <td class="px-3 py-2 text-center font-medium">
                {{ lineTotal(item).toFixed(2) }} ر.س
              </td>
              <td class="px-3 py-2">
                <button type="button" class="text-red-400 hover:text-red-600" @click="removeItem(idx)">✕</button>
              </td>
            </tr>
          </tbody>
          <tfoot class="bg-gray-50 border-t">
            <tr>
              <td colspan="5" class="px-3 py-2 text-right text-sm font-semibold">الإجمالي</td>
              <td class="px-3 py-2 text-center font-bold">{{ grandTotal.toFixed(2) }} ر.س</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div v-if="error" class="text-red-500 text-sm bg-red-50 rounded p-2">{{ error }}</div>
      <div class="flex justify-end gap-2">
        <RouterLink to="/purchases" class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">إلغاء</RouterLink>
        <button type="submit" class="px-5 py-2 text-sm bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50" :disabled="saving || !form.items.length">
          {{ saving ? 'جارٍ الحفظ...' : 'إنشاء أمر الشراء' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import apiClient from '@/lib/apiClient'

const router   = useRouter()
const suppliers = ref<any[]>([])
const saving   = ref(false)
const error    = ref('')

const form = ref({
  supplier_id: '',
  expected_at: '',
  notes: '',
  items: [] as any[],
})

onMounted(async () => {
  const { data } = await apiClient.get('/suppliers', { params: { is_active: true, per_page: 200 } })
  suppliers.value = data.data.data ?? data.data
  addItem()
})

function addItem() {
  form.value.items.push({ name: '', sku: '', quantity: 1, unit_cost: 0, tax_rate: 15 })
}

function removeItem(idx: number) {
  form.value.items.splice(idx, 1)
}

function lineTotal(item: any): number {
  const base = item.quantity * item.unit_cost
  return base + base * ((item.tax_rate ?? 15) / 100)
}

const grandTotal = computed(() => form.value.items.reduce((s, i) => s + lineTotal(i), 0))

async function submit() {
  saving.value = true
  error.value = ''
  try {
    const { data } = await apiClient.post('/purchases', form.value)
    router.push(`/purchases/${data.data.id}`)
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'حدث خطأ أثناء إنشاء أمر الشراء.'
  } finally {
    saving.value = false
  }
}
</script>
