<template>
  <div class="space-y-5" dir="rtl">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <button @click="$router.back()" class="p-2 rounded-xl hover:bg-gray-100 dark:hover:bg-slate-700">
          <ChevronRightIcon class="w-5 h-5 text-gray-400" />
        </button>
        <div>
          <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
            <span class="font-mono text-blue-600 dark:text-blue-400">{{ vehicle?.plate_number }}</span>
            <span class="text-gray-400 text-base font-normal">{{ vehicle?.make }} {{ vehicle?.model }}</span>
          </h2>
          <p class="text-xs text-gray-400">جواز المركبة الرقمي</p>
        </div>
      </div>
      <button @click="exportPDF" class="btn btn-outline text-xs py-1.5 px-3 flex items-center gap-1">
        <ArrowDownTrayIcon class="w-4 h-4" /> تصدير PDF
      </button>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="w-8 h-8 border-4 border-blue-400 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <div v-else-if="vehicle" class="space-y-4">

      <!-- Vehicle Info Card -->
      <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 p-5">
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
          <div v-for="f in vehicleFields" :key="f.label">
            <p class="text-xs text-gray-400 mb-0.5">{{ f.label }}</p>
            <p class="text-sm font-semibold text-gray-800 dark:text-white">{{ f.value || '—' }}</p>
          </div>
        </div>
      </div>

      <!-- Oil Change Tracker -->
      <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 p-5">
        <h3 class="font-bold text-sm text-gray-800 dark:text-white mb-4 flex items-center gap-2">
          <span class="text-lg">🔧</span> متابعة تغيير الزيت
        </h3>
        <div class="flex items-center gap-3 mb-2">
          <span class="text-xs text-gray-500">آخر تغيير: {{ lastOilChange || 'غير مسجل' }}</span>
          <span class="mx-auto"></span>
          <span class="text-xs text-gray-500">الكيلومتر التالي: {{ nextOilKm || '—' }}</span>
        </div>
        <div class="w-full bg-gray-100 dark:bg-slate-700 rounded-full h-3">
          <div class="h-3 rounded-full transition-all duration-500"
            :class="oilProgress > 80 ? 'bg-red-500' : oilProgress > 60 ? 'bg-amber-500' : 'bg-green-500'"
            :style="`width:${oilProgress}%`"></div>
        </div>
        <p class="text-xs text-gray-400 mt-1 text-left">{{ oilProgress }}% مكتمل</p>
      </div>

      <!-- Service History Timeline -->
      <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 p-5">
        <h3 class="font-bold text-sm text-gray-800 dark:text-white mb-4 flex items-center gap-2">
          <ClipboardDocumentListIcon class="w-4 h-4 text-blue-500" /> سجل الخدمات
        </h3>
        <div v-if="!workOrders.length" class="text-center py-6 text-gray-400 text-xs">لا توجد سجلات خدمة</div>
        <div v-else class="relative">
          <div class="absolute right-4 top-0 bottom-0 w-0.5 bg-gray-100 dark:bg-slate-700"></div>
          <div v-for="wo in workOrders" :key="wo.id" class="flex items-start gap-3 pb-4 relative pr-10">
            <div class="absolute right-2.5 w-3 h-3 rounded-full border-2 border-white dark:border-slate-800 mt-1"
              :class="wo.status === 'completed' ? 'bg-green-500' : wo.status === 'in_progress' ? 'bg-blue-500' : 'bg-gray-300'">
            </div>
            <div class="flex-1 bg-gray-50 dark:bg-slate-900 rounded-xl p-3">
              <div class="flex justify-between items-start">
                <p class="text-xs font-semibold text-gray-800 dark:text-white">{{ wo.title || 'أمر عمل #' + wo.id }}</p>
                <span class="text-xs text-gray-400">{{ formatDate(wo.created_at) }}</span>
              </div>
              <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">{{ wo.description || '' }}</p>
              <div class="flex items-center gap-2 mt-1.5">
                <span class="text-xs px-2 py-0.5 rounded-full"
                  :class="wo.status === 'completed' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'">
                  {{ statusLabel(wo.status) }}
                </span>
                <span v-if="wo.total_amount" class="text-xs text-gray-500">{{ fmt(wo.total_amount) }} ر.س</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Invoice History -->
      <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 p-5">
        <h3 class="font-bold text-sm text-gray-800 dark:text-white mb-4 flex items-center gap-2">
          <DocumentTextIcon class="w-4 h-4 text-purple-500" /> سجل الفواتير
        </h3>
        <div v-if="!invoices.length" class="text-center py-4 text-gray-400 text-xs">لا توجد فواتير</div>
        <div v-else class="space-y-2">
          <div v-for="inv in invoices" :key="inv.id"
            class="flex items-center justify-between p-3 bg-gray-50 dark:bg-slate-900 rounded-xl">
            <div>
              <p class="text-xs font-mono font-semibold text-gray-800 dark:text-white">#{{ inv.invoice_number }}</p>
              <p class="text-xs text-gray-400">{{ formatDate(inv.issued_at) }}</p>
            </div>
            <div class="text-right">
              <p class="text-sm font-bold text-gray-800 dark:text-white">{{ fmt(inv.total) }} ر.س</p>
              <span class="text-xs px-2 py-0.5 rounded-full"
                :class="inv.status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'">
                {{ inv.status === 'paid' ? 'مدفوعة' : 'غير مدفوعة' }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Vehicle Settings -->
      <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 p-5">
        <h3 class="font-bold text-sm text-gray-800 dark:text-white mb-4 flex items-center gap-2">
          <CogIcon class="w-4 h-4 text-gray-500" /> إعدادات المركبة
        </h3>
        <div class="grid grid-cols-2 gap-3" v-if="!editingSettings">
          <div v-for="s in settingsFields" :key="s.key">
            <p class="text-xs text-gray-400">{{ s.label }}</p>
            <p class="text-sm font-medium text-gray-800 dark:text-white mt-0.5">{{ settings[s.key] || '—' }}</p>
          </div>
          <div class="col-span-2">
            <button @click="editingSettings = true" class="text-xs text-blue-600 hover:underline">تعديل الإعدادات</button>
          </div>
        </div>
        <div v-else class="space-y-3">
          <div class="grid grid-cols-2 gap-3">
            <div v-for="s in settingsFields" :key="s.key">
              <label class="block text-xs text-gray-400 mb-1">{{ s.label }}</label>
              <input v-model="settings[s.key]" class="field text-sm" :placeholder="s.placeholder" />
            </div>
          </div>
          <div class="flex gap-2">
            <button @click="saveSettings" class="btn btn-primary text-xs py-1.5 px-3">حفظ</button>
            <button @click="editingSettings = false" class="btn btn-outline text-xs py-1.5 px-3">إلغاء</button>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, reactive } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ChevronRightIcon, ArrowDownTrayIcon, ClipboardDocumentListIcon, DocumentTextIcon, CogIcon } from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'

const route  = useRoute()
const loading = ref(true)
const vehicle = ref<any>(null)
const workOrders = ref<any[]>([])
const invoices   = ref<any[]>([])
const editingSettings = ref(false)

const settings = reactive<Record<string, string>>({
  oil_type: '', oil_capacity: '', battery_capacity: '', tire_size_front: '',
  tire_size_rear: '', last_odometer: '', next_oil_change_km: '',
  air_filter_interval: '', coolant_type: '',
})

const settingsFields = [
  { key: 'oil_type',          label: 'نوع الزيت',          placeholder: '5W-30' },
  { key: 'oil_capacity',      label: 'سعة الزيت (لتر)',     placeholder: '4.5' },
  { key: 'battery_capacity',  label: 'سعة البطارية (Ah)',   placeholder: '70' },
  { key: 'tire_size_front',   label: 'حجم الكفر الأمامي',  placeholder: '225/60R17' },
  { key: 'tire_size_rear',    label: 'حجم الكفر الخلفي',   placeholder: '225/60R17' },
  { key: 'last_odometer',     label: 'آخر قراءة عداد (كم)', placeholder: '50000' },
  { key: 'next_oil_change_km',label: 'تغيير الزيت القادم', placeholder: '55000' },
  { key: 'coolant_type',      label: 'نوع سائل التبريد',   placeholder: 'OAT' },
]

const vehicleFields = computed(() => [
  { label: 'رقم اللوحة',    value: vehicle.value?.plate_number },
  { label: 'الماركة',       value: vehicle.value?.make },
  { label: 'الموديل',       value: vehicle.value?.model },
  { label: 'سنة الصنع',     value: vehicle.value?.year },
  { label: 'اللون',         value: vehicle.value?.color },
  { label: 'الوقود',        value: fuelLabel(vehicle.value?.fuel_type) },
  { label: 'رقم الشاسيه',  value: vehicle.value?.vin },
  { label: 'الكيلومتر',     value: settings.last_odometer ? settings.last_odometer + ' كم' : '—' },
])

const lastOilChange = computed(() => {
  const wo = [...workOrders.value].reverse().find(w => w.title?.includes('زيت') || w.description?.includes('زيت'))
  return wo ? formatDate(wo.created_at) : null
})
const nextOilKm  = computed(() => settings.next_oil_change_km)
const oilProgress = computed(() => {
  if (!settings.last_odometer || !settings.next_oil_change_km) return 0
  const interval = 5000
  const lastChange = Number(settings.next_oil_change_km) - interval
  const driven = Number(settings.last_odometer) - lastChange
  return Math.min(100, Math.round((driven / interval) * 100))
})

const fmt = (v: number) => Number(v || 0).toLocaleString('ar-SA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const formatDate = (d: string) => d ? new Date(d).toLocaleDateString('ar-SA') : '—'
const statusLabel = (s: string) => ({'pending':'انتظار','assigned':'مُعيَّن','in_progress':'جاري','completed':'مكتمل','invoiced':'مُفوتَر','cancelled':'ملغى'}[s] ?? s)
const fuelLabel = (f: string) => ({'gasoline':'بنزين','diesel':'ديزل','hybrid':'هجين','electric':'كهرباء'}[f] ?? f)

async function saveSettings() {
  try {
    await apiClient.patch(`/vehicles/${route.params.id}/settings`, settings)
    editingSettings.value = false
  } catch { editingSettings.value = false }
}

async function exportPDF() {
  try {
    const { default: jsPDF } = await import('jspdf')
    const doc = new jsPDF({ orientation: 'portrait', format: 'a4' })
    doc.text(`Vehicle Passport: ${vehicle.value?.plate_number}`, 20, 20)
    doc.text(`${vehicle.value?.make} ${vehicle.value?.model} (${vehicle.value?.year})`, 20, 30)
    doc.save(`vehicle-passport-${vehicle.value?.plate_number}.pdf`)
  } catch { alert('جارٍ تطوير ميزة التصدير') }
}

async function load() {
  loading.value = true
  try {
    const { data } = await apiClient.get(`/vehicles/${route.params.id}`)
    vehicle.value = data.data ?? data
    const vSettings = vehicle.value?.settings ?? {}
    Object.assign(settings, vSettings)
    try {
      const wo = await apiClient.get(`/work-orders?vehicle_id=${route.params.id}&per_page=20`)
      workOrders.value = wo.data?.data?.data ?? []
    } catch {}
    try {
      const inv = await apiClient.get(`/invoices?vehicle_id=${route.params.id}&per_page=20`)
      invoices.value = inv.data?.data?.data ?? []
    } catch {}
  } finally { loading.value = false }
}

onMounted(load)
</script>

<style scoped>
.field { @apply w-full px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-xl text-sm focus:ring-2 focus:ring-blue-400 focus:border-transparent dark:bg-slate-700 dark:text-white; }
.btn { @apply inline-flex items-center gap-1 rounded-xl font-medium transition-colors cursor-pointer; }
.btn-primary { @apply bg-blue-600 hover:bg-blue-700 text-white px-3 py-2; }
.btn-outline { @apply border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 px-3 py-2; }
</style>
