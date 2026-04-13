<template>
  <div class="space-y-6" dir="rtl">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-gray-900">أمر عمل جديد</h2>
      <RouterLink to="/work-orders" class="text-sm text-primary-600 hover:underline">← أوامر العمل</RouterLink>
    </div>

    <p v-if="bootError" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ bootError }}</p>

    <form class="space-y-6" @submit.prevent="submit">
      <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
        <h3 class="font-medium text-gray-800">بيانات العميل والمركبة</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">العميل <span class="text-red-500">*</span></label>
            <select v-model="form.customer_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" @change="onCustomerOrVehicleChange">
              <option value="">اختر عميلاً</option>
              <option v-for="c in customers" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">المركبة <span class="text-red-500">*</span></label>
            <select v-model="form.vehicle_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" @change="onCustomerOrVehicleChange">
              <option value="">اختر مركبة</option>
              <option v-for="v in filteredVehicles" :key="v.id" :value="String(v.id)">{{ v.plate_number }} — {{ v.make }} {{ v.model }}</option>
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

      <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
        <div class="flex justify-between items-center">
          <h3 class="font-medium text-gray-800">البنود والخدمات</h3>
        </div>
        <WorkOrderLinesEditor
          ref="linesEditorRef"
          v-model="form.items"
          :customer-id="form.customer_id"
          :vehicle-id="form.vehicle_id"
          :services="services"
        />
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
import WorkOrderLinesEditor from '@/components/work-orders/WorkOrderLinesEditor.vue'
import {
  type CatalogLineItem,
  emptyCatalogLine,
  canSubmitCatalogLines,
  buildItemsApiPayload,
} from '@/composables/useWorkOrderCatalogLines'

const router = useRouter()
const linesEditorRef = ref<InstanceType<typeof WorkOrderLinesEditor> | null>(null)

const customers = ref<any[]>([])
const vehicles = ref<any[]>([])
const services = ref<Array<{ id: number; name?: string; name_ar?: string }>>([])
const saving = ref(false)
const error = ref('')
const bootError = ref('')

const form = ref({
  customer_id: '',
  vehicle_id: '',
  driver_name: '',
  driver_phone: '',
  odometer_reading: null as number | null,
  priority: 'normal',
  customer_complaint: '',
  items: [] as CatalogLineItem[],
})

const filteredVehicles = computed(() => {
  if (!form.value.customer_id) return vehicles.value
  return vehicles.value.filter((v: any) => String(v.customer_id) === String(form.value.customer_id))
})

async function onCustomerOrVehicleChange() {
  if (!form.value.customer_id) {
    form.value.vehicle_id = ''
  }
  await linesEditorRef.value?.refreshAllCatalogPricing?.()
}

const canSubmitWorkOrder = computed(() =>
  form.value.items.length > 0 &&
  canSubmitCatalogLines(form.value.items, form.value.customer_id, form.value.vehicle_id),
)

async function submit() {
  if (saving.value) return
  if (!canSubmitWorkOrder.value) {
    error.value = 'أكمل البنود: للبند المربوط بخدمة اختر العميل والمركبة وانتظر السعر المعتمد من الخادم. للبند اليدوي أدخل الاسم والسعر.'
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
        items: buildItemsApiPayload(form.value.items),
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
    const [c, v, s] = await Promise.all([
      apiClient.get('/customers', { params: { per_page: 500 }, skipGlobalErrorToast: true }),
      apiClient.get('/vehicles', { params: { per_page: 500 }, skipGlobalErrorToast: true }),
      apiClient.get('/services', { params: { per_page: 500, is_active: true }, skipGlobalErrorToast: true }),
    ])
    customers.value = c.data.data.data ?? c.data.data
    vehicles.value = v.data.data.data ?? v.data.data
    const svcPayload = s.data.data
    const raw = svcPayload?.data ?? svcPayload ?? []
    services.value = Array.isArray(raw) ? raw : []
    form.value.items = [emptyCatalogLine()]
  } catch (e: unknown) {
    bootError.value = summarizeAxiosError(e)
  }
})
</script>
