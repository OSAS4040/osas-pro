<template>
  <div class="max-w-4xl space-y-5 pb-8" dir="rtl">
    <div class="flex items-center gap-3">
      <RouterLink to="/invoices" class="text-gray-400 hover:text-gray-600 text-sm">← الفواتير</RouterLink>
      <h2 class="text-lg font-semibold text-gray-900">فاتورة جديدة</h2>
    </div>

    <form @submit.prevent="submit" class="space-y-5">
      <!-- العميل والمعلومات -->
      <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
        <h3 class="text-sm font-semibold text-gray-700">بيانات الفاتورة</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div class="sm:col-span-2">
            <label class="block text-xs text-gray-500 mb-1">العميل <span class="text-red-500">*</span></label>
            <select v-model="form.customer_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
              <option value="">اختر عميلاً</option>
              <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">تاريخ الإصدار</label>
            <input v-model="form.issued_at" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">تاريخ الاستحقاق</label>
            <input v-model="form.due_at" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">العملة</label>
            <select v-model="form.currency" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
              <option value="SAR">ريال سعودي (SAR)</option>
              <option value="USD">دولار أمريكي (USD)</option>
              <option value="AED">درهم إماراتي (AED)</option>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">ملاحظات</label>
          <textarea v-model="form.notes" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="ملاحظات للعميل..."></textarea>
        </div>
      </div>

      <!-- البنود -->
      <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-semibold text-gray-700">البنود والخدمات</h3>
          <button type="button" @click="addItem" class="text-sm text-primary-600 hover:underline flex items-center gap-1">
            <span class="text-base leading-none">+</span> إضافة بند
          </button>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-xs text-gray-500 bg-gray-50">
              <tr>
                <th class="px-3 py-2 text-right">اسم المنتج / الخدمة</th>
                <th class="px-3 py-2 text-right w-20">الكمية</th>
                <th class="px-3 py-2 text-right w-28">سعر الوحدة</th>
                <th class="px-3 py-2 text-right w-20">الضريبة %</th>
                <th class="px-3 py-2 text-right w-28">الإجمالي</th>
                <th class="px-3 py-2 w-8"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, i) in form.items" :key="i" class="border-t border-gray-100">
                <td class="px-3 py-2">
                  <input v-model="item.name" required placeholder="الاسم" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm" />
                </td>
                <td class="px-3 py-2">
                  <input v-model.number="item.quantity" type="number" min="0.001" step="0.001" required class="w-20 px-2 py-1.5 border border-gray-300 rounded text-sm text-center" />
                </td>
                <td class="px-3 py-2">
                  <input v-model.number="item.unit_price" type="number" min="0" step="0.01" required class="w-28 px-2 py-1.5 border border-gray-300 rounded text-sm text-center" />
                </td>
                <td class="px-3 py-2">
                  <input v-model.number="item.tax_rate" type="number" min="0" max="100" class="w-20 px-2 py-1.5 border border-gray-300 rounded text-sm text-center" />
                </td>
                <td class="px-3 py-2 font-medium text-right">
                  {{ lineTotal(item).toFixed(2) }}
                </td>
                <td class="px-3 py-2">
                  <button type="button" @click="removeItem(i)" class="text-red-400 hover:text-red-600 text-lg">×</button>
                </td>
              </tr>
            </tbody>
            <tfoot v-if="form.items.length">
              <tr class="border-t-2 border-gray-200 bg-gray-50">
                <td colspan="4" class="px-3 py-2 text-right font-semibold text-gray-700">الإجمالي</td>
                <td class="px-3 py-2 text-right font-bold text-gray-900">{{ grandTotal.toFixed(2) }} {{ form.currency }}</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <!-- الدفع -->
      <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
        <h3 class="text-sm font-semibold text-gray-700">معلومات الدفع</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="block text-xs text-gray-500 mb-1">طريقة الدفع</label>
            <select v-model="form.payment.method" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
              <option value="cash">نقداً</option>
              <option value="card">بطاقة</option>
              <option value="wallet">محفظة</option>
              <option value="bank_transfer">تحويل بنكي</option>
              <option value="credit">ائتمان</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">المبلغ المدفوع</label>
            <input v-model.number="form.payment.amount" type="number" min="0" step="0.01" :placeholder="grandTotal.toFixed(2)" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
          </div>
          <div class="flex items-end pb-1">
            <span class="text-sm text-gray-500">
              المتبقي: <strong class="text-red-600">{{ remaining.toFixed(2) }} {{ form.currency }}</strong>
            </span>
          </div>
        </div>
      </div>

      <div v-if="error" class="text-red-600 text-sm bg-red-50 rounded-lg p-3">{{ error }}</div>

      <div class="flex justify-end gap-3">
        <RouterLink to="/invoices" class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">إلغاء</RouterLink>
        <button type="submit" :disabled="saving || !form.items.length" class="px-6 py-2 text-sm bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50">
          {{ saving ? 'جارٍ الحفظ...' : 'إنشاء الفاتورة' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import apiClient, { withIdempotency } from '@/lib/apiClient'

const router = useRouter()
const customers = ref<any[]>([])
const saving  = ref(false)
const error   = ref('')

const today = new Date().toISOString().split('T')[0]
const dueDate = new Date(Date.now() + 30 * 86400000).toISOString().split('T')[0]

const form = ref({
  customer_id: '',
  issued_at: today,
  due_at: dueDate,
  currency: 'SAR',
  notes: '',
  items: [{ name: '', quantity: 1, unit_price: 0, tax_rate: 15 }] as any[],
  payment: { method: 'cash', amount: 0 },
})

function addItem() {
  form.value.items.push({ name: '', quantity: 1, unit_price: 0, tax_rate: 15 })
}

function removeItem(i: number) {
  form.value.items.splice(i, 1)
}

function lineTotal(item: any): number {
  const base = item.quantity * item.unit_price
  return base + base * ((item.tax_rate ?? 0) / 100)
}

const grandTotal = computed(() => form.value.items.reduce((s, i) => s + lineTotal(i), 0))

const remaining = computed(() => {
  const paid = Number(form.value.payment.amount) || grandTotal.value
  return grandTotal.value - paid
})

async function submit() {
  saving.value = true
  error.value = ''
  try {
    const payload = {
      customer_id: Number(form.value.customer_id),
      issued_at:   form.value.issued_at,
      due_at:      form.value.due_at,
      currency:    form.value.currency,
      notes:       form.value.notes || undefined,
      items: form.value.items,
      payment: {
        method: form.value.payment.method,
        amount: form.value.payment.amount > 0 ? form.value.payment.amount : grandTotal.value,
      },
    }
    const { data } = await apiClient.post('/invoices', payload, withIdempotency())
    router.push(`/invoices/${data.data.id}`)
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'حدث خطأ أثناء إنشاء الفاتورة.'
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  const { data } = await apiClient.get('/customers', { params: { per_page: 500 } })
  customers.value = data.data.data ?? data.data
})
</script>
