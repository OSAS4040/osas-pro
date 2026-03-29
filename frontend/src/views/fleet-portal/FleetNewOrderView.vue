<template>
  <div class="p-6 max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
      <button @click="$router.back()" class="text-gray-400 hover:text-gray-600">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      </button>
      <h1 class="text-xl font-bold text-gray-900">طلب خدمة جديد</h1>
    </div>

    <form @submit.prevent="submit" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">

      <!-- المركبة -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">المركبة <span class="text-red-500">*</span></label>
        <select v-model="form.vehicle_id" required
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
          <option value="">— اختر المركبة —</option>
          <option v-for="v in vehicles" :key="v.id" :value="v.id">
            {{ v.plate_number }} — {{ v.make }} {{ v.model }} ({{ v.year }})
          </option>
        </select>
      </div>

      <!-- شكوى العميل -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">وصف المشكلة / الخدمة المطلوبة <span class="text-red-500">*</span></label>
        <textarea v-model="form.customer_complaint" required rows="3"
          placeholder="صِف الخلل أو الخدمة المطلوبة بالتفصيل..."
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"></textarea>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <!-- قراءة العداد -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">قراءة العداد (كم)</label>
          <input v-model.number="form.mileage" type="number" min="0" placeholder="مثال: 75000"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500" />
        </div>
        <!-- اسم السائق -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">اسم السائق</label>
          <input v-model="form.driver_name" type="text" placeholder="اسم السائق"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>

      <!-- هاتف السائق -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">هاتف السائق</label>
        <input v-model="form.driver_phone" type="tel" placeholder="05xxxxxxxx"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500" />
      </div>

      <!-- طريقة الدفع -->
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <p class="text-sm font-medium text-blue-800 mb-3">طريقة الدفع</p>
        <div class="space-y-2">
          <label class="flex items-center gap-3 cursor-pointer">
            <input type="radio" v-model="paymentMode" value="wallet" class="text-blue-600" />
            <div>
              <span class="text-sm font-medium text-gray-800">خصم من المحفظة</span>
              <span class="text-xs text-gray-500 block">يُخصم مباشرة عند إتمام الخدمة</span>
            </div>
          </label>
          <label class="flex items-center gap-3 cursor-pointer">
            <input type="radio" v-model="paymentMode" value="credit" class="text-blue-600" />
            <div>
              <span class="text-sm font-medium text-gray-800">ائتمان (يتطلب موافقة المدير)</span>
              <span class="text-xs text-gray-500 block">سيُرسل الطلب لمدير الأسطول للاعتماد قبل الخدمة</span>
            </div>
          </label>
        </div>
      </div>

      <!-- ملاحظات -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">ملاحظات إضافية</label>
        <textarea v-model="form.notes" rows="2"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
      </div>

      <!-- Error -->
      <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">{{ error }}</div>

      <!-- Success -->
      <div v-if="success" class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-4 text-sm">
        <p class="font-medium">✅ تم إنشاء طلب الخدمة بنجاح</p>
        <p class="mt-1">{{ successMsg }}</p>
        <button @click="$router.push('/fleet-portal')" class="mt-3 px-4 py-1.5 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700">
          العودة للوحة التحكم
        </button>
      </div>

      <div v-if="!success" class="flex gap-3 pt-2">
        <button type="submit" :disabled="submitting"
          class="flex-1 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm disabled:opacity-60">
          {{ submitting ? 'جارٍ الإرسال...' : 'إرسال طلب الخدمة' }}
        </button>
        <button type="button" @click="$router.back()"
          class="px-4 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm">
          إلغاء
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'

const BASE = '/api/v1'
const token = () => localStorage.getItem('auth_token') ?? ''

const vehicles  = ref<any[]>([])
const error     = ref('')
const success   = ref(false)
const successMsg = ref('')
const submitting = ref(false)
const paymentMode = ref('wallet')

const form = ref({
  vehicle_id: '',
  customer_complaint: '',
  mileage: null as number | null,
  driver_name: '',
  driver_phone: '',
  notes: '',
})

async function api(path: string, opts: RequestInit = {}) {
  const r = await fetch(`${BASE}${path}`, {
    headers: { 'Authorization': `Bearer ${token()}`, 'Content-Type': 'application/json', 'Accept': 'application/json' },
    ...opts,
  })
  const json = await r.json()
  if (!r.ok) throw new Error(json.message ?? `HTTP ${r.status}`)
  return json
}

async function loadVehicles() {
  try {
    const res = await api('/fleet-portal/vehicles')
    vehicles.value = res.data?.data ?? res.data ?? []
  } catch (e: any) {
    error.value = 'تعذّر تحميل قائمة المركبات: ' + e.message
  }
}

async function submit() {
  if (!form.value.vehicle_id || !form.value.customer_complaint.trim()) {
    error.value = 'يرجى اختيار المركبة وإدخال وصف المشكلة.'; return
  }
  submitting.value = true; error.value = ''
  try {
    const payload: any = {
      vehicle_id:          Number(form.value.vehicle_id),
      customer_complaint:  form.value.customer_complaint,
      use_credit:          paymentMode.value === 'credit',
    }
    if (form.value.mileage)      payload.mileage      = form.value.mileage
    if (form.value.driver_name)  payload.driver_name  = form.value.driver_name
    if (form.value.driver_phone) payload.driver_phone = form.value.driver_phone
    if (form.value.notes)        payload.notes        = form.value.notes

    const res = await api('/fleet-portal/work-orders', { method: 'POST', body: JSON.stringify(payload) })
    success.value   = true
    successMsg.value = res.message ?? 'تم إنشاء الطلب بنجاح.'
  } catch (e: any) {
    error.value = e.message
  } finally {
    submitting.value = false
  }
}

onMounted(loadVehicles)
</script>
