<template>
  <div class="space-y-6" dir="rtl">
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h2 class="text-2xl font-bold text-gray-900">الحضور والانصراف</h2>
        <p class="text-xs text-gray-400 mt-0.5">
          طريقة التسجيل:
          <span class="font-medium" :class="geoEnabled ? 'text-green-600' : 'text-blue-600'">
            {{ geoEnabled ? '📍 جيومكاني (GPS)' : '🖥️ دخول النظام' }}
          </span>
          <span v-if="!canUseGeo" class="text-gray-300"> · (يتطلب باقة Professional)</span>
        </p>
      </div>
      <div class="flex gap-2">
        <button @click="checkAction('check-in')"
          class="flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-medium">
          <ArrowRightEndOnRectangleIcon class="w-4 h-4" />
          تسجيل حضور
        </button>
        <button @click="checkAction('check-out')"
          class="flex items-center gap-2 px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 text-sm font-medium">
          <ArrowLeftStartOnRectangleIcon class="w-4 h-4" />
          تسجيل انصراف
        </button>
      </div>
    </div>

    <!-- Attendance Method Selector (managers only, based on plan) -->
    <div class="bg-white rounded-xl border border-gray-200 p-5">
      <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
        <Cog6ToothIcon class="w-4 h-4 text-gray-500" />
        طريقة تسجيل الحضور
      </h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <!-- Option 1: System Login -->
        <button
          @click="setAttendanceMethod('system')"
          class="flex items-start gap-4 p-4 rounded-xl border-2 transition-all text-right"
          :class="attendanceMethod === 'system' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300 bg-white'"
        >
          <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" :class="attendanceMethod === 'system' ? 'bg-blue-100' : 'bg-gray-100'">
            <ComputerDesktopIcon class="w-5 h-5" :class="attendanceMethod === 'system' ? 'text-blue-600' : 'text-gray-400'" />
          </div>
          <div>
            <p class="text-sm font-semibold text-gray-900">دخول النظام</p>
            <p class="text-xs text-gray-500 mt-0.5">يُسجَّل الحضور عبر النظام دون تحقق من الموقع</p>
            <span class="inline-block mt-1.5 text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">متاح لجميع الباقات</span>
          </div>
          <CheckCircleIcon v-if="attendanceMethod === 'system'" class="w-5 h-5 text-blue-500 mr-auto flex-shrink-0 mt-0.5" />
        </button>

        <!-- Option 2: GPS Geo -->
        <button
          @click="setAttendanceMethod('geo')"
          class="flex items-start gap-4 p-4 rounded-xl border-2 transition-all text-right"
          :class="[
            !canUseGeo ? 'opacity-60 cursor-not-allowed' : '',
            attendanceMethod === 'geo' ? 'border-green-500 bg-green-50' : 'border-gray-200 hover:border-gray-300 bg-white'
          ]"
          :disabled="!canUseGeo"
        >
          <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" :class="attendanceMethod === 'geo' ? 'bg-green-100' : 'bg-gray-100'">
            <MapPinIcon class="w-5 h-5" :class="attendanceMethod === 'geo' ? 'text-green-600' : 'text-gray-400'" />
          </div>
          <div>
            <p class="text-sm font-semibold text-gray-900 flex items-center gap-2">
              جيومكاني (GPS + Geofence)
              <LockClosedIcon v-if="!canUseGeo" class="w-3.5 h-3.5 text-gray-400" />
            </p>
            <p class="text-xs text-gray-500 mt-0.5">يُتحقق من موقع الموظف تلقائياً عند التسجيل</p>
            <span class="inline-block mt-1.5 text-xs px-2 py-0.5 rounded-full" :class="canUseGeo ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-400'">
              {{ canUseGeo ? 'مفعّل' : 'يتطلب Professional أو أعلى' }}
            </span>
          </div>
          <CheckCircleIcon v-if="attendanceMethod === 'geo'" class="w-5 h-5 text-green-500 mr-auto flex-shrink-0 mt-0.5" />
        </button>
      </div>

      <!-- Geofence Config (visible when geo selected) -->
      <div v-if="attendanceMethod === 'geo' && canUseGeo" class="mt-4 p-4 bg-green-50 rounded-xl space-y-3 border border-green-200">
        <p class="text-sm font-medium text-green-800">إعدادات النطاق الجغرافي</p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div class="md:col-span-2">
            <label class="block text-xs text-gray-600 mb-1">إحداثيات الفرع (lat, lng)</label>
            <div class="flex gap-2">
              <input v-model="geoConfig.lat" type="number" step="0.0001" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono" placeholder="24.7136" />
              <input v-model="geoConfig.lng" type="number" step="0.0001" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono" placeholder="46.6753" />
            </div>
          </div>
          <div>
            <label class="block text-xs text-gray-600 mb-1">نطاق السماح (متر)</label>
            <input v-model="geoConfig.radius" type="number" min="50" max="5000" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="200" />
          </div>
        </div>
        <button @click="getMyLocation" class="flex items-center gap-1.5 text-xs text-green-700 hover:underline">
          <MapPinIcon class="w-3.5 h-3.5" />
          استخدام موقعي الحالي كإحداثيات الفرع
        </button>
      </div>
    </div>

    <!-- Today Summary -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div v-for="s in todayStats" :key="s.label" class="bg-white rounded-xl p-4 border border-gray-200 text-center">
        <p class="text-2xl font-bold" :class="s.color">{{ s.value }}</p>
        <p class="text-xs text-gray-500 mt-1">{{ s.label }}</p>
      </div>
    </div>

    <!-- Employee selector & date -->
    <div class="bg-white rounded-xl border border-gray-200 p-4 flex gap-3 flex-wrap items-end">
      <div class="flex-1 min-w-[200px]">
        <label class="block text-xs font-medium text-gray-600 mb-1">الموظف</label>
        <select v-model="selectedEmployee" @change="loadMonth" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none">
          <option value="">كل الموظفين</option>
          <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.full_name }}</option>
        </select>
      </div>
      <div class="flex-1 min-w-[160px]">
        <label class="block text-xs font-medium text-gray-600 mb-1">الشهر</label>
        <input v-model="selectedMonth" type="month" @change="loadMonth"
          class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none" />
      </div>
    </div>

    <!-- Attendance Log -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div class="px-4 py-3 border-b bg-gray-50">
        <h3 class="font-semibold text-sm text-gray-700">سجل الحضور</h3>
      </div>
      <div v-if="loading" class="flex justify-center py-8">
        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-primary-600"></div>
      </div>
      <table v-else class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="px-4 py-3 text-right font-semibold text-gray-700">الموظف</th>
            <th class="px-4 py-3 text-right font-semibold text-gray-700">النوع</th>
            <th class="px-4 py-3 text-right font-semibold text-gray-700">الوقت</th>
            <th class="px-4 py-3 text-right font-semibold text-gray-700">الطريقة</th>
            <th class="px-4 py-3 text-right font-semibold text-gray-700">الموقع</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="log in logs" :key="log.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-medium text-gray-900">{{ log.employee_name ?? log.employee_id }}</td>
            <td class="px-4 py-3">
              <span :class="log.type === 'check_in' ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700'"
                class="px-2 py-0.5 rounded-full text-xs font-medium">
                {{ log.type === 'check_in' ? 'حضور' : 'انصراف' }}
              </span>
            </td>
            <td class="px-4 py-3 text-gray-600 text-xs">{{ formatDate(log.logged_at) }}</td>
            <td class="px-4 py-3 text-xs">
              <span v-if="log.latitude" class="flex items-center gap-1 text-green-600">
                <MapPinIcon class="w-3 h-3" /> GPS
              </span>
              <span v-else class="text-gray-400">نظام</span>
            </td>
            <td class="px-4 py-3 text-gray-500 text-xs">
              <a v-if="log.latitude"
                :href="`https://www.google.com/maps?q=${log.latitude},${log.longitude}`"
                target="_blank"
                class="text-blue-600 hover:underline font-mono">
                {{ Number(log.latitude).toFixed(4) }}, {{ Number(log.longitude).toFixed(4) }}
              </a>
              <span v-else>—</span>
            </td>
          </tr>
          <tr v-if="!logs.length">
            <td colspan="5" class="text-center py-10 text-gray-400">لا توجد سجلات</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Check Modal -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="checkModal" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" dir="rtl" @click.self="checkModal = null">
          <div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl">
            <div class="flex items-center justify-between px-6 py-4 border-b">
              <div>
                <h3 class="font-bold">{{ checkModal === 'check-in' ? 'تسجيل حضور' : 'تسجيل انصراف' }}</h3>
                <p class="text-xs text-gray-400 mt-0.5">
                  {{ geoEnabled ? '📍 سيتم التحقق من موقعك تلقائياً' : '🖥️ تسجيل عبر النظام' }}
                </p>
              </div>
              <button @click="checkModal = null"><XMarkIcon class="w-5 h-5 text-gray-400" /></button>
            </div>
            <div class="p-6 space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الموظف *</label>
                <select v-model="checkForm.employee_id" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                  <option value="">اختر موظفاً</option>
                  <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.full_name }}</option>
                </select>
              </div>

              <!-- GPS Status (geo mode) -->
              <div v-if="geoEnabled" class="rounded-xl p-3 text-sm border"
                :class="geoStatus === 'ok' ? 'bg-green-50 border-green-200 text-green-700' : geoStatus === 'error' ? 'bg-red-50 border-red-200 text-red-600' : 'bg-blue-50 border-blue-200 text-blue-600'"
              >
                <div class="flex items-center gap-2">
                  <MapPinIcon class="w-4 h-4 flex-shrink-0" />
                  <span>{{ geoMessage }}</span>
                </div>
                <p v-if="geoCoords" class="text-xs mt-1 font-mono opacity-70">{{ geoCoords.lat.toFixed(5) }}, {{ geoCoords.lng.toFixed(5) }}</p>
              </div>

              <div v-if="checkError" class="text-red-600 text-sm bg-red-50 rounded-lg p-3">{{ checkError }}</div>
              <div class="flex gap-3 justify-end">
                <button @click="checkModal = null" class="px-4 py-2 border rounded-lg text-sm text-gray-700">إلغاء</button>
                <button @click="submitCheck" :disabled="checkSaving || (geoEnabled && geoStatus === 'fetching')"
                  :class="checkModal === 'check-in' ? 'bg-green-600 hover:bg-green-700' : 'bg-orange-500 hover:bg-orange-600'"
                  class="px-4 py-2 text-white rounded-lg text-sm font-medium disabled:opacity-50">
                  {{ checkSaving ? 'جاري...' : (checkModal === 'check-in' ? 'تسجيل الحضور' : 'تسجيل الانصراف') }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import {
  ArrowRightEndOnRectangleIcon, ArrowLeftStartOnRectangleIcon, XMarkIcon,
  MapPinIcon, ComputerDesktopIcon, Cog6ToothIcon, LockClosedIcon, CheckCircleIcon,
} from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import { useSubscriptionStore } from '@/stores/subscription'
import { useToast } from '@/composables/useToast'

const sub   = useSubscriptionStore()
const toast = useToast()

const canUseGeo     = computed(() => sub.hasFeature('work_orders'))
const attendanceMethod = ref<'system' | 'geo'>(
  localStorage.getItem('attendance_method') as 'system' | 'geo' || 'system'
)
const geoEnabled = computed(() => attendanceMethod.value === 'geo' && canUseGeo.value)

const geoConfig = ref({ lat: '', lng: '', radius: 200 })

const employees      = ref<any[]>([])
const logs           = ref<any[]>([])
const loading        = ref(false)
const selectedEmployee = ref('')
const selectedMonth  = ref(new Date().toISOString().slice(0, 7))
const checkModal     = ref<string | null>(null)
const checkSaving    = ref(false)
const checkError     = ref('')
const checkForm      = ref({ employee_id: '' })

type GeoStatus = 'idle' | 'fetching' | 'ok' | 'error'
const geoStatus  = ref<GeoStatus>('idle')
const geoMessage = ref('سيتم جلب موقعك تلقائياً')
const geoCoords  = ref<{ lat: number; lng: number } | null>(null)

const todayStats = computed(() => {
  const today = new Date().toDateString()
  const todayLogs = logs.value.filter(l => new Date(l.logged_at).toDateString() === today)
  return [
    { label: 'حاضرون اليوم',    value: new Set(todayLogs.filter(l => l.type === 'check_in').map(l => l.employee_id)).size,  color: 'text-green-600' },
    { label: 'منصرفون',          value: new Set(todayLogs.filter(l => l.type === 'check_out').map(l => l.employee_id)).size, color: 'text-orange-500' },
    { label: 'إجمالي الموظفين', value: employees.value.length,  color: 'text-primary-600' },
    { label: 'سجلات الشهر',     value: logs.value.length,       color: 'text-blue-600' },
  ]
})

function formatDate(d: string) {
  return new Date(d).toLocaleString('ar-SA', { dateStyle: 'short', timeStyle: 'short' })
}

function setAttendanceMethod(m: 'system' | 'geo') {
  if (m === 'geo' && !canUseGeo.value) {
    toast.warning('يتطلب ترقية الباقة', 'الحضور الجيومكاني متاح في باقة Professional أو أعلى')
    return
  }
  attendanceMethod.value = m
  localStorage.setItem('attendance_method', m)
}

function getMyLocation() {
  if (!navigator.geolocation) return
  navigator.geolocation.getCurrentPosition(p => {
    geoConfig.value.lat = String(p.coords.latitude)
    geoConfig.value.lng = String(p.coords.longitude)
    toast.success('تم تحديد موقعك كمركز للنطاق')
  })
}

async function fetchGeoLocation(): Promise<{ lat: number; lng: number } | null> {
  return new Promise(resolve => {
    if (!navigator.geolocation) { resolve(null); return }
    geoStatus.value = 'fetching'
    geoMessage.value = 'جارٍ تحديد موقعك...'
    navigator.geolocation.getCurrentPosition(
      p => {
        const coords = { lat: p.coords.latitude, lng: p.coords.longitude }
        geoCoords.value = coords
        if (geoConfig.value.lat && geoConfig.value.lng) {
          const dist = getDistanceMeters(coords.lat, coords.lng, Number(geoConfig.value.lat), Number(geoConfig.value.lng))
          if (dist > Number(geoConfig.value.radius || 200)) {
            geoStatus.value = 'error'
            geoMessage.value = `أنت خارج النطاق المسموح (${Math.round(dist)}م من الفرع)`
            resolve(null); return
          }
        }
        geoStatus.value = 'ok'
        geoMessage.value = 'تم تأكيد موقعك بنجاح ✓'
        resolve(coords)
      },
      () => {
        geoStatus.value = 'error'
        geoMessage.value = 'تعذّر تحديد الموقع — تأكد من إذن الموقع في المتصفح'
        resolve(null)
      },
      { timeout: 8000, enableHighAccuracy: true }
    )
  })
}

function getDistanceMeters(lat1: number, lng1: number, lat2: number, lng2: number) {
  const R = 6371000
  const dLat = (lat2 - lat1) * Math.PI / 180
  const dLng = (lng2 - lng1) * Math.PI / 180
  const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180) * Math.cos(lat2*Math.PI/180) * Math.sin(dLng/2)**2
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a))
}

function checkAction(type: string) {
  checkForm.value.employee_id = ''
  checkError.value = ''
  geoCoords.value = null
  geoStatus.value = 'idle'
  geoMessage.value = 'سيتم جلب موقعك تلقائياً'
  checkModal.value = type
  if (geoEnabled.value) {
    fetchGeoLocation()
  }
}

async function loadEmployees() {
  const r = await apiClient.get('/workshop/employees')
  employees.value = r.data?.data ?? []
}

async function loadMonth() {
  if (!selectedEmployee.value) { logs.value = []; return }
  loading.value = true
  try {
    const [y, m] = selectedMonth.value.split('-')
    const r = await apiClient.get(`/workshop/attendance/${selectedEmployee.value}/month?year=${y}&month=${m}`)
    logs.value = r.data?.data ?? []
  } finally { loading.value = false }
}

async function submitCheck() {
  if (!checkForm.value.employee_id) { checkError.value = 'اختر موظفاً'; return }
  if (geoEnabled.value && geoStatus.value === 'error') { checkError.value = 'لا يمكن التسجيل — تحقق من موقعك'; return }

  checkSaving.value = true
  checkError.value = ''
  try {
    const payload: any = { employee_id: checkForm.value.employee_id }
    if (geoEnabled.value && geoCoords.value) {
      payload.latitude  = geoCoords.value.lat
      payload.longitude = geoCoords.value.lng
    }
    const endpoint = checkModal.value === 'check-in' ? '/workshop/attendance/check-in' : '/workshop/attendance/check-out'
    await apiClient.post(endpoint, payload)
    toast.success(checkModal.value === 'check-in' ? 'تم تسجيل الحضور بنجاح' : 'تم تسجيل الانصراف بنجاح')
    checkModal.value = null
    if (selectedEmployee.value) loadMonth()
  } catch (e: any) {
    checkError.value = e?.response?.data?.message ?? 'حدث خطأ'
  } finally { checkSaving.value = false }
}

onMounted(async () => {
  await loadEmployees()
  if (employees.value.length) {
    selectedEmployee.value = employees.value[0].id
    await loadMonth()
  }
})
</script>

<style scoped>
.modal-fade-enter-active { transition: all 0.2s ease-out; }
.modal-fade-leave-active { transition: all 0.15s ease-in; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }
</style>
