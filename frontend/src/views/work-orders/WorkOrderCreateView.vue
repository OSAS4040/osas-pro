<template>
  <div class="space-y-6" dir="rtl">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-gray-900">أمر عمل جديد</h2>
      <RouterLink to="/work-orders" class="text-sm text-primary-600 hover:underline">← أوامر العمل</RouterLink>
    </div>

    <p v-if="bootError" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ bootError }}</p>

    <form class="space-y-6" @submit.prevent="submit">
      <!-- العميل والمركبة -->
      <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
        <h3 class="font-medium text-gray-800">بيانات العميل والمركبة</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">العميل <span class="text-red-500">*</span></label>
            <select v-model="form.customer_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" @change="loadVehicles">
              <option value="">اختر عميلاً</option>
              <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">المركبة <span class="text-red-500">*</span></label>
            <select v-model="form.vehicle_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
              <option value="">اختر مركبة</option>
              <option v-for="v in filteredVehicles" :key="v.id" :value="v.id">{{ v.plate_number }} — {{ v.make }} {{ v.model }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">اسم السائق</label>
            <input v-model="form.driver_name" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="اختياري" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">هاتف السائق</label>
            <input v-model="form.driver_phone" type="tel" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="05xxxxxxxx" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">قراءة العداد (كم)</label>
            <input v-model="form.odometer_reading" type="number" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="مثال: 45000" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الأولوية</label>
            <select v-model="form.priority" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
              <option value="low">منخفضة</option>
              <option value="normal">عادية</option>
              <option value="high">عالية</option>
              <option value="urgent">عاجلة</option>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">وصف الطلب</label>
          <textarea v-model="form.customer_complaint" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="وصف العمل المطلوب أو الملاحظات…"></textarea>
        </div>
      </div>

      <!-- بنود أمر العمل -->
      <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
        <div class="flex justify-between items-center">
          <h3 class="font-medium text-gray-800">البنود والخدمات</h3>
          <button type="button" class="text-sm text-primary-600 hover:underline flex items-center gap-1" @click="addItem">
            <span class="text-lg leading-none">+</span> إضافة بند
          </button>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-xs text-gray-500 bg-gray-50">
              <tr>
                <th class="px-2 py-2 text-right">النوع</th>
                <th class="px-2 py-2 text-right">الاسم / الوصف</th>
                <th class="px-2 py-2 text-right">الكمية</th>
                <th class="px-2 py-2 text-right">سعر الوحدة</th>
                <th class="px-2 py-2 text-right">الضريبة %</th>
                <th class="px-2 py-2 text-right">الإجمالي</th>
                <th class="px-2 py-2"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, i) in form.items" :key="i" class="border-t border-gray-100">
                <td class="px-2 py-2">
                  <select v-model="item.item_type" class="border border-gray-300 rounded px-2 py-1 text-xs">
                    <option value="part">قطعة</option>
                    <option value="labor">عمالة</option>
                    <option value="service">خدمة</option>
                    <option value="other">أخرى</option>
                  </select>
                </td>
                <td class="px-2 py-2">
                  <input v-model="item.name" type="text" required placeholder="اسم البند" class="w-full border border-gray-300 rounded px-2 py-1 text-sm" />
                </td>
                <td class="px-2 py-2 w-20">
                  <input v-model="item.quantity" type="number" min="0.001" step="0.001" class="w-full border border-gray-300 rounded px-2 py-1 text-sm text-center" />
                </td>
                <td class="px-2 py-2 w-28">
                  <input v-model="item.unit_price" type="number" min="0" step="0.01" class="w-full border border-gray-300 rounded px-2 py-1 text-sm text-center" />
                </td>
                <td class="px-2 py-2 w-20">
                  <input v-model="item.tax_rate" type="number" min="0" max="100" step="0.01" class="w-full border border-gray-300 rounded px-2 py-1 text-sm text-center" />
                </td>
                <td class="px-2 py-2 text-center font-medium text-gray-700 w-28">{{ lineTotal(item) }} ر.س</td>
                <td class="px-2 py-2">
                  <button type="button" class="text-red-400 hover:text-red-600 text-lg" @click="form.items.splice(i, 1)">✕</button>
                </td>
              </tr>
            </tbody>
            <tfoot v-if="form.items.length">
              <tr class="border-t-2 border-gray-200 bg-gray-50">
                <td colspan="5" class="px-2 py-2 text-right font-semibold text-gray-700">الإجمالي</td>
                <td class="px-2 py-2 text-center font-bold text-gray-900 text-base">{{ totalAmount }} ر.س</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <p v-if="!form.items.length" class="text-center text-amber-800 bg-amber-50 border border-amber-200 rounded-lg py-3 px-2 text-sm">
          يجب إضافة بند خدمة أو منتج واحد على الأقل قبل إنشاء أمر العمل.
        </p>
      </div>

      <p v-if="error" class="text-red-600 text-sm bg-red-50 rounded-lg p-3">{{ error }}</p>

      <div class="flex justify-end gap-3">
        <RouterLink to="/work-orders" class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">إلغاء</RouterLink>
        <button type="submit" :disabled="saving || !canSubmitWorkOrder" class="px-6 py-2 text-sm bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50">
          {{ saving ? 'جارٍ الحفظ...' : 'إنشاء أمر العمل' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import apiClient from '@/lib/apiClient'
import { summarizeAxiosError } from '@/utils/apiErrorSummary'

const router = useRouter()

const customers = ref<any[]>([])
const vehicles  = ref<any[]>([])
const saving    = ref(false)
const error     = ref('')
const bootError   = ref('')

const form = ref({
  customer_id: '',
  vehicle_id: '',
  driver_name: '',
  driver_phone: '',
  odometer_reading: null as number | null,
  priority: 'normal',
  customer_complaint: '',
  items: [] as Array<{
    item_type: string
    name: string
    quantity: number
    unit_price: number
    tax_rate: number
    product_id: number | null
  }>,
})

const filteredVehicles = computed(() => {
  if (!form.value.customer_id) return vehicles.value
  return vehicles.value.filter((v: any) => String(v.customer_id) === String(form.value.customer_id))
})

function loadVehicles() {
  form.value.vehicle_id = ''
}

function addItem() {
  form.value.items.push({ item_type: 'service', name: '', quantity: 1, unit_price: 0, tax_rate: 15, product_id: null })
}

function lineTotal(item: typeof form.value.items[0]): string {
  const sub  = item.quantity * item.unit_price
  const tax  = sub * (item.tax_rate / 100)
  return (sub + tax).toFixed(2)
}

const totalAmount = computed(() =>
  form.value.items.reduce((acc, item) => {
    const sub = item.quantity * item.unit_price
    return acc + sub + sub * (item.tax_rate / 100)
  }, 0).toFixed(2)
)

/** بند صالح: اسم غير فارغ وكمية وحد سعر رقمية مقبولة */
const canSubmitWorkOrder = computed(() => {
  const rows = form.value.items
  if (!rows.length) return false
  return rows.every((item) => {
    const nameOk = typeof item.name === 'string' && item.name.trim().length > 0
    const q = Number(item.quantity)
    const p = Number(item.unit_price)
    const tax = Number(item.tax_rate)
    return nameOk && Number.isFinite(q) && q > 0 && Number.isFinite(p) && p >= 0 && Number.isFinite(tax) && tax >= 0 && tax <= 100
  })
})

async function submit() {
  if (saving.value) return
  if (!canSubmitWorkOrder.value) {
    error.value = 'أضف بنداً واحداً على الأقل مع اسم البند وكمية صحيحة.'
    return
  }
  saving.value = true
  error.value = ''
  try {
    const { data } = await apiClient.post(
      '/work-orders',
      {
        ...form.value,
        customer_id: Number(form.value.customer_id),
        vehicle_id: Number(form.value.vehicle_id),
        items: form.value.items.map((i) => ({
          ...i,
          quantity: Number(i.quantity),
          unit_price: Number(i.unit_price),
          tax_rate: Number(i.tax_rate),
          product_id: i.product_id,
        })),
      },
      { skipGlobalErrorToast: true },
    )
    router.push(`/work-orders/${data.data.id}`)
  } catch (e: unknown) {
    error.value = summarizeAxiosError(e)
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  bootError.value = ''
  try {
    const [c, v] = await Promise.all([
      apiClient.get('/customers', { params: { per_page: 500 }, skipGlobalErrorToast: true }),
      apiClient.get('/vehicles', { params: { per_page: 500 }, skipGlobalErrorToast: true }),
    ])
    customers.value = c.data.data.data ?? c.data.data
    vehicles.value = v.data.data.data ?? v.data.data
  } catch (e: unknown) {
    bootError.value = summarizeAxiosError(e)
  }
})
</script>
