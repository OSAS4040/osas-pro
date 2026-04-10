<template>
  <div class="p-6 max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
      <button class="text-gray-400 hover:text-gray-600" @click="$router.back()">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
      </button>
      <h1 class="text-xl font-bold text-gray-900">طلب خدمة جديد</h1>
    </div>

    <form class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5" @submit.prevent="submit">
      <!-- المركبة -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">المركبة <span class="text-red-500">*</span></label>
        <select v-model="form.vehicle_id" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                @change="onVehicleChange"
        >
          <option value="">— اختر المركبة —</option>
          <option v-for="v in vehicles" :key="v.id" :value="v.id">
            {{ v.plate_number }} — {{ v.make }} {{ v.model }} ({{ v.year }})
          </option>
        </select>
      </div>

      <!-- الخدمة (التسعير من الخادم فقط) -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">الخدمة <span class="text-red-500">*</span></label>
        <select
          v-model="form.service_id"
          required
          :disabled="!form.vehicle_id"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:opacity-60"
          @change="loadPricingPreview"
        >
          <option value="">— اختر الخدمة —</option>
          <option v-for="s in services" :key="s.id" :value="s.id">
            {{ s.name_ar || s.name }} <span v-if="s.code">({{ s.code }})</span>
          </option>
        </select>
      </div>

      <!-- تسعير الخدمة (قراءة فقط) -->
      <div class="bg-slate-50 border border-slate-200 rounded-lg p-4">
        <p class="text-sm font-medium text-slate-800 mb-2">التسعير المعتمد</p>
        <div v-if="pricingLoading" class="text-xs text-slate-500">جارٍ جلب السعر المعتمد...</div>
        <template v-else>
          <div class="grid grid-cols-2 gap-3 text-sm">
            <div>
              <div class="text-slate-500 text-xs mb-1">سعر الوحدة</div>
              <div class="font-semibold text-slate-900">{{ pricingPreview ? formatSar(pricingPreview.unit_price) : '—' }}</div>
            </div>
            <div>
              <div class="text-slate-500 text-xs mb-1">ضريبة القيمة المضافة</div>
              <div class="font-semibold text-slate-900">{{ pricingPreview ? `${pricingPreview.tax_rate}%` : '—' }}</div>
            </div>
          </div>
          <div class="mt-3 text-xs text-slate-600">
            <span class="font-medium">مصدر السعر:</span>
            <span>{{ pricingPreview?.pricing_source_label_ar || '—' }}</span>
          </div>
          <div class="mt-1 text-[11px] text-slate-500">
            تم تسعير هذا البند تلقائيًا حسب العقد أو سياسة التسعير المعتمدة للشركة. لا يمكن تعديل السعر من بوابة العميل.
          </div>
          <p v-if="!form.vehicle_id" class="mt-2 text-[11px] text-amber-700">
            اختر المركبة أولاً لعرض الخدمات المشمولة حسب العقد ونطاق الأصول.
          </p>
          <div v-if="pricingError" class="mt-2 text-xs text-red-600">{{ pricingError }}</div>
        </template>
      </div>

      <!-- وصف الطلب -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">وصف الطلب</label>
        <textarea v-model="form.customer_complaint" rows="3"
                  placeholder="صف العمل أو الخدمة المطلوبة…"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
        ></textarea>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <!-- قراءة العداد -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">قراءة العداد (كم)</label>
          <input v-model.number="form.mileage" type="number" min="0" placeholder="مثال: 75000"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
          />
        </div>
        <!-- اسم السائق -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">اسم السائق</label>
          <input v-model="form.driver_name" type="text" placeholder="اسم السائق"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
          />
        </div>
      </div>

      <!-- هاتف السائق -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">هاتف السائق</label>
        <input v-model="form.driver_phone" type="tel" placeholder="05xxxxxxxx"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
        />
      </div>

      <!-- طريقة الدفع -->
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <p class="text-sm font-medium text-blue-800 mb-3">طريقة الدفع</p>
        <div class="space-y-2">
          <label class="flex items-center gap-3 cursor-pointer">
            <input v-model="paymentMode" type="radio" value="wallet" class="text-blue-600" />
            <div>
              <span class="text-sm font-medium text-gray-800">خصم من المحفظة</span>
              <span class="text-xs text-gray-500 block">يُخصم مباشرة عند إتمام الخدمة</span>
            </div>
          </label>
          <label class="flex items-center gap-3 cursor-pointer">
            <input v-model="paymentMode" type="radio" value="credit" class="text-blue-600" />
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
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 resize-none"
        ></textarea>
      </div>

      <!-- Error -->
      <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">{{ error }}</div>

      <!-- Success -->
      <div v-if="success" class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-4 text-sm">
        <p class="font-medium">✅ تم إنشاء طلب الخدمة بنجاح</p>
        <p class="mt-1">{{ successMsg }}</p>
        <button class="mt-3 px-4 py-1.5 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700" @click="$router.push('/fleet-portal')">
          العودة للوحة التحكم
        </button>
      </div>

      <div v-if="!success" class="flex gap-3 pt-2">
        <button type="submit" :disabled="submitting"
                class="flex-1 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm disabled:opacity-60"
        >
          {{ submitting ? 'جارٍ الإرسال...' : 'إرسال طلب الخدمة' }}
        </button>
        <button type="button" class="px-4 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm"
                @click="$router.back()"
        >
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
const services = ref<any[]>([])
const error     = ref('')
const success   = ref(false)
const successMsg = ref('')
const submitting = ref(false)
const paymentMode = ref('wallet')
const pricingLoading = ref(false)
const pricingError = ref('')
const pricingPreview = ref<null | {
  unit_price: number
  tax_rate: number
  pricing_source_label_ar: string
}>(null)

const form = ref({
  vehicle_id: '',
  service_id: '',
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

function onVehicleChange() {
  form.value.service_id = ''
  pricingPreview.value = null
  pricingError.value = ''
  loadServices()
}

async function loadServices() {
  services.value = []
  if (!form.value.vehicle_id) return
  try {
    const q = `?vehicle_id=${encodeURIComponent(String(form.value.vehicle_id))}`
    const res = await api(`/fleet-portal/service-catalog${q}`)
    services.value = res.data ?? []
  } catch (e: any) {
    error.value = 'تعذّر تحميل قائمة الخدمات: ' + e.message
  }
}

function formatSar(v: number): string {
  return new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR' }).format(Number(v) || 0)
}

async function loadPricingPreview() {
  pricingError.value = ''
  pricingPreview.value = null
  if (!form.value.service_id || !form.value.vehicle_id) return
  pricingLoading.value = true
  try {
    const res = await api('/fleet-portal/work-orders/pricing-preview', {
      method: 'POST',
      body: JSON.stringify({
        service_id: Number(form.value.service_id),
        vehicle_id: Number(form.value.vehicle_id),
      }),
    })
    pricingPreview.value = res.data ?? null
  } catch (e: any) {
    pricingError.value = e.message || 'لا يوجد سعر معتمد لهذه الخدمة.'
  } finally {
    pricingLoading.value = false
  }
}

async function submit() {
  if (!form.value.vehicle_id || !form.value.service_id) {
    error.value = 'يرجى اختيار المركبة والخدمة.'
    return
  }
  if (!pricingPreview.value) {
    error.value = pricingError.value || 'لا يوجد سعر معتمد لهذه الخدمة ضمن سياسة التسعير الحالية.'
    return
  }
  submitting.value = true; error.value = ''
  try {
    const payload: any = {
      vehicle_id:          Number(form.value.vehicle_id),
      service_id:          Number(form.value.service_id),
      customer_complaint:  form.value.customer_complaint || undefined,
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

onMounted(async () => {
  await loadVehicles()
})
</script>
