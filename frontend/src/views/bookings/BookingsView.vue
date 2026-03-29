<template>
  <div class="space-y-5" dir="rtl">
    <!-- Header - مطابق لاحجزني -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
        <CalendarDaysIcon class="w-6 h-6 text-primary-600" />
        مواعيد
      </h2>
      <div class="flex items-center gap-2">
        <button @click="showPublicLink = true"
          class="flex items-center gap-1.5 px-3 py-2 border border-indigo-300 rounded-lg text-sm text-indigo-600 hover:bg-indigo-50 transition-colors">
          <LinkIcon class="w-4 h-4" /> الرابط العام
        </button>
        <button @click="exportBookings"
          class="flex items-center gap-1.5 px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
          <ArrowDownTrayIcon class="w-4 h-4" /> تصدير
        </button>
        <button @click="showModal = true"
          class="flex items-center gap-1.5 px-3 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 transition-colors">
          <PlusIcon class="w-4 h-4" /> إضافة موعد
        </button>
      </div>
    </div>

    <!-- Tabs: مواعيد / طلبات / مواعيد سابقة / ملغية -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div class="flex gap-0.5 bg-gray-100 p-1 rounded-xl">
        <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
          class="px-4 py-1.5 rounded-lg text-sm font-medium transition-colors"
          :class="activeTab === tab.key ? 'bg-white text-primary-700 shadow-sm' : 'text-gray-600 hover:text-gray-800'">
          {{ tab.label }}
          <span v-if="tab.count > 0"
            class="mr-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold"
            :class="activeTab === tab.key ? 'bg-primary-100 text-primary-700' : 'bg-gray-200 text-gray-600'">
            {{ tab.count }}
          </span>
        </button>
      </div>

      <!-- View Toggle: قائمة / تقويم -->
      <div class="flex gap-1 bg-gray-100 p-1 rounded-lg">
        <button @click="viewMode = 'list'"
          class="p-1.5 rounded-md transition-colors"
          :class="viewMode === 'list' ? 'bg-white shadow-sm text-primary-600' : 'text-gray-500 hover:text-gray-700'">
          <Bars3Icon class="w-4 h-4" />
        </button>
        <button @click="viewMode = 'calendar'"
          class="p-1.5 rounded-md transition-colors"
          :class="viewMode === 'calendar' ? 'bg-white shadow-sm text-primary-600' : 'text-gray-500 hover:text-gray-700'">
          <CalendarDaysIcon class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl border border-gray-100 p-4 flex gap-3 flex-wrap items-end">
      <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">عرض المواعيد</label>
        <select v-model="filterBay" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-primary-400">
          <option value="">جميع الرافعات</option>
          <option v-for="b in bays" :key="b.id" :value="b.id">{{ b.name }}</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">التاريخ</label>
        <input v-model="filterDate" type="date" @change="load"
          class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-primary-400" />
      </div>
    </div>

    <!-- Requests Panel (طلبات) -->
    <template v-if="activeTab === 'requests'">
      <div class="bg-blue-50 border border-blue-200 rounded-xl p-5">
        <p class="text-sm text-blue-700 mb-4">
          فوراً ما أن يقوم أحدهم بالحجز لديك من خلال
          <span class="font-semibold text-blue-800">الموقع الإلكتروني</span>،
          سيظهر طلب الحجز هنا لتقوم بتأكيده أو رفضه.
        </p>
        <div v-if="!pendingBookings.length" class="text-center py-6 text-gray-400">
          <CalendarDaysIcon class="w-10 h-10 mx-auto mb-2 text-gray-300" />
          <p class="text-sm">لا توجد طلبات حجز حالياً</p>
          <button @click="load" class="mt-3 flex items-center gap-1.5 mx-auto text-sm text-gray-500 hover:text-gray-700">
            <ArrowPathIcon class="w-4 h-4" /> تحديث
          </button>
        </div>
        <div v-else class="space-y-3">
          <div v-for="b in pendingBookings" :key="b.id"
            class="bg-white rounded-xl p-4 flex items-center justify-between shadow-sm border border-blue-100">
            <div>
              <p class="font-semibold text-gray-800 text-sm">{{ b.service_type }}</p>
              <p class="text-xs text-gray-500 mt-0.5">{{ formatDt(b.starts_at) }} • {{ b.bay?.name }}</p>
            </div>
            <div class="flex gap-2">
              <button @click="confirmBooking(b.id)" class="px-3 py-1.5 bg-green-600 text-white text-xs rounded-lg hover:bg-green-700">تأكيد</button>
              <button @click="cancelBooking(b.id)" class="px-3 py-1.5 bg-red-100 text-red-700 text-xs rounded-lg hover:bg-red-200">رفض</button>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Availability Check -->
    <template v-if="activeTab === 'bookings'">
      <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
        <h3 class="font-semibold text-blue-900 mb-3 text-sm">فحص التوفر</h3>
        <div class="flex gap-3 flex-wrap items-end">
          <div>
            <label class="block text-xs font-medium text-blue-800 mb-1">التاريخ والوقت</label>
            <input v-model="avail.starts_at" type="datetime-local" class="border border-blue-300 rounded-lg px-3 py-2 text-sm focus:outline-none" />
          </div>
          <div>
            <label class="block text-xs font-medium text-blue-800 mb-1">المدة (دقيقة)</label>
            <input v-model.number="avail.duration_minutes" type="number" min="15" step="15"
              class="border border-blue-300 rounded-lg px-3 py-2 text-sm focus:outline-none w-24" />
          </div>
          <div>
            <label class="block text-xs font-medium text-blue-800 mb-1">الخدمة</label>
            <input v-model="avail.capability" placeholder="oil_change" class="border border-blue-300 rounded-lg px-3 py-2 text-sm focus:outline-none" />
          </div>
          <button @click="checkAvailability" :disabled="checkingAvail"
            class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
            {{ checkingAvail ? 'جارٍ الفحص...' : 'فحص' }}
          </button>
        </div>
        <div v-if="availResult !== null" class="mt-3 p-3 rounded-lg text-sm"
          :class="availResult.available ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
          {{ availResult.available
            ? `✅ متاح — رافعة: ${availResult.bay?.name ?? 'غير محدد'}`
            : '❌ لا توجد رافعة متاحة في هذا الوقت' }}
        </div>
      </div>

      <!-- LIST VIEW -->
      <div v-if="viewMode === 'list'" class="bg-white rounded-xl border border-gray-100 overflow-hidden">
        <div v-if="loading" class="py-10 text-center">
          <div class="animate-spin rounded-full h-7 w-7 border-b-2 border-primary-600 mx-auto"></div>
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 text-right">
              <tr>
                <th class="px-4 py-3 font-medium">الرافعة</th>
                <th class="px-4 py-3 font-medium">وقت البداية</th>
                <th class="px-4 py-3 font-medium">وقت الانتهاء</th>
                <th class="px-4 py-3 font-medium">الخدمة</th>
                <th class="px-4 py-3 font-medium">المصدر</th>
                <th class="px-4 py-3 font-medium">الحالة</th>
                <th class="px-4 py-3 font-medium">إجراءات</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="b in filteredBookings" :key="b.id" class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 font-semibold text-gray-800">{{ b.bay?.name ?? `Bay ${b.bay_id}` }}</td>
                <td class="px-4 py-3 text-gray-600 text-xs">{{ formatDt(b.starts_at) }}</td>
                <td class="px-4 py-3 text-gray-600 text-xs">{{ formatDt(b.ends_at) }}</td>
                <td class="px-4 py-3 text-gray-700">{{ b.service_type }}</td>
                <td class="px-4 py-3">
                  <span class="px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full text-xs">{{ b.source ?? 'manual' }}</span>
                </td>
                <td class="px-4 py-3">
                  <span :class="statusBadge(b.status)" class="px-2 py-0.5 rounded-full text-xs font-medium">
                    {{ statusLabel(b.status) }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <button v-if="b.status === 'pending'" @click="confirmBooking(b.id)"
                    class="text-xs text-green-700 hover:underline ml-2">تأكيد</button>
                  <button v-if="['pending','confirmed'].includes(b.status)" @click="cancelBooking(b.id)"
                    class="text-xs text-red-600 hover:underline">إلغاء</button>
                </td>
              </tr>
              <tr v-if="!filteredBookings.length">
                <td colspan="7" class="text-center py-10 text-gray-400">لا توجد مواعيد</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- CALENDAR VIEW -->
      <div v-else class="bg-white rounded-xl border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-4">
          <button @click="prevDay" class="p-1.5 hover:bg-gray-100 rounded-lg transition-colors">
            <ChevronRightIcon class="w-5 h-5 text-gray-600" />
          </button>
          <h3 class="font-semibold text-gray-800">{{ calendarTitle }}</h3>
          <button @click="nextDay" class="p-1.5 hover:bg-gray-100 rounded-lg transition-colors">
            <ChevronLeftIcon class="w-5 h-5 text-gray-600" />
          </button>
        </div>
        <!-- Hourly Slots -->
        <div class="space-y-1 max-h-96 overflow-y-auto">
          <div v-for="hour in hours" :key="hour" class="flex items-stretch gap-3">
            <div class="w-14 text-xs text-gray-400 py-2 text-left flex-shrink-0">{{ hour }}:00</div>
            <div class="flex-1 min-h-[40px] rounded-lg border border-gray-100 relative">
              <div v-for="b in bookingsByHour(hour)" :key="b.id"
                class="absolute inset-x-0 top-0 px-2 py-1 rounded-lg text-xs font-medium"
                :class="statusBadge(b.status)">
                {{ b.service_type }} — {{ b.bay?.name }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Previous / Cancelled tabs -->
    <template v-if="activeTab === 'past' || activeTab === 'cancelled'">
      <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-xs text-gray-500 text-right">
            <tr>
              <th class="px-4 py-3 font-medium">الرافعة</th>
              <th class="px-4 py-3 font-medium">التاريخ</th>
              <th class="px-4 py-3 font-medium">الخدمة</th>
              <th class="px-4 py-3 font-medium">الحالة</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="b in tabBookings" :key="b.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-4 py-3 font-medium text-gray-800">{{ b.bay?.name ?? '—' }}</td>
              <td class="px-4 py-3 text-gray-500 text-xs">{{ formatDt(b.starts_at) }}</td>
              <td class="px-4 py-3 text-gray-700">{{ b.service_type }}</td>
              <td class="px-4 py-3"><span :class="statusBadge(b.status)" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ statusLabel(b.status) }}</span></td>
            </tr>
            <tr v-if="!tabBookings.length"><td colspan="4" class="text-center py-10 text-gray-400">لا توجد مواعيد</td></tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- New Booking Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="showModal = false">
      <div class="bg-white rounded-2xl w-full max-w-md shadow-xl">
        <div class="flex items-center justify-between px-6 py-4 border-b">
          <h3 class="font-bold text-lg">إضافة موعد جديد</h3>
          <button @click="showModal = false"><XMarkIcon class="w-5 h-5 text-gray-400" /></button>
        </div>
        <form @submit.prevent="save" class="p-6 space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الرافعة *</label>
            <select v-model="form.bay_id" required class="w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400">
              <option value="">اختر رافعة</option>
              <option v-for="b in bays" :key="b.id" :value="b.id" :disabled="b.status !== 'available'">
                {{ b.name }} {{ b.status !== 'available' ? '(غير متاحة)' : '' }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">وقت البداية *</label>
            <input v-model="form.starts_at" type="datetime-local" required class="w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">المدة (دقيقة)</label>
              <input v-model.number="form.duration_minutes" type="number" min="15" step="15" class="w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">نوع الخدمة</label>
              <input v-model="form.service_type" placeholder="تغيير زيت..." class="w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400" />
            </div>
          </div>
          <p v-if="modalError" class="text-red-600 text-sm bg-red-50 rounded-xl p-3">{{ modalError }}</p>
          <div class="flex gap-3 justify-end pt-1">
            <button type="button" @click="showModal = false" class="px-4 py-2 border rounded-xl text-sm text-gray-700 hover:bg-gray-50">إلغاء</button>
            <button type="submit" :disabled="saving" class="px-4 py-2 bg-primary-600 text-white rounded-xl text-sm font-medium disabled:opacity-50 hover:bg-primary-700">
              {{ saving ? 'جارٍ الحفظ...' : 'حفظ الموعد' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Public Link Modal -->
  <div v-if="showPublicLink" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-md shadow-xl p-6 space-y-4" dir="rtl">
      <div class="flex items-center justify-between">
        <h3 class="font-bold text-lg text-gray-900 flex items-center gap-2">
          <LinkIcon class="w-5 h-5 text-indigo-600" />
          الرابط العام للحجوزات
        </h3>
        <button @click="showPublicLink = false"><XMarkIcon class="w-5 h-5 text-gray-400" /></button>
      </div>
      <p class="text-sm text-gray-500">شارك هذا الرابط مع عملائك ليتمكنوا من حجز المواعيد مباشرة</p>
      <div class="flex gap-2">
        <input :value="publicBookingUrl" readonly
          class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 bg-gray-50 text-gray-700 select-all" />
        <button @click="copyPublicLink"
          class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
          :class="publicLinkCopied ? 'bg-green-500 text-white' : 'bg-indigo-600 text-white hover:bg-indigo-700'">
          {{ publicLinkCopied ? 'تم النسخ ✓' : 'نسخ' }}
        </button>
      </div>
      <div class="flex gap-2 pt-2">
        <a :href="`https://wa.me/?text=${encodeURIComponent('احجز موعدك: '+publicBookingUrl)}`" target="_blank"
          class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-green-500 text-white rounded-lg text-sm hover:bg-green-600 transition-colors">
          مشاركة واتساب
        </a>
        <a :href="`mailto:?subject=حجز موعد&body=${encodeURIComponent('احجز موعدك عبر الرابط: '+publicBookingUrl)}`"
          class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-blue-500 text-white rounded-lg text-sm hover:bg-blue-600 transition-colors">
          مشاركة بريد
        </a>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import {
  PlusIcon, XMarkIcon, CalendarDaysIcon, ArrowDownTrayIcon,
  Bars3Icon, ChevronLeftIcon, ChevronRightIcon, ArrowPathIcon, LinkIcon,
} from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'

const showPublicLink = ref(false)
const publicLinkCopied = ref(false)
const publicBookingUrl = computed(() => `${window.location.origin}/book/${window.location.hostname.split('.')[0] ?? 'demo'}`)
function copyPublicLink() {
  navigator.clipboard.writeText(publicBookingUrl.value).then(() => {
    publicLinkCopied.value = true
    setTimeout(() => { publicLinkCopied.value = false }, 2000)
  })
}

const bookings      = ref<any[]>([])
const bays          = ref<any[]>([])
const loading       = ref(true)
const filterDate    = ref(new Date().toISOString().slice(0, 10))
const filterBay     = ref('')
const showModal     = ref(false)
const saving        = ref(false)
const modalError    = ref('')
const checkingAvail = ref(false)
const availResult   = ref<any>(null)
const activeTab     = ref<'bookings'|'requests'|'past'|'cancelled'>('bookings')
const viewMode      = ref<'list'|'calendar'>('list')

const avail = ref({ starts_at: '', duration_minutes: 60, capability: '' })
const form  = ref({ bay_id: '', starts_at: '', duration_minutes: 60, service_type: '' })

const hours = Array.from({ length: 14 }, (_, i) => i + 7) // 7am - 9pm

const pendingBookings = computed(() => bookings.value.filter(b => b.status === 'pending'))

const filteredBookings = computed(() =>
  bookings.value.filter(b => {
    if (filterBay.value && b.bay_id != filterBay.value) return false
    return !['completed','cancelled'].includes(b.status)
  })
)

const tabBookings = computed(() => {
  if (activeTab.value === 'past')      return bookings.value.filter(b => b.status === 'completed')
  if (activeTab.value === 'cancelled') return bookings.value.filter(b => b.status === 'cancelled')
  return []
})

const tabs = computed((): { key: 'bookings'|'requests'|'past'|'cancelled'; label: string; count: number }[] => [
  { key: 'bookings',  label: 'مواعيد',        count: filteredBookings.value.length },
  { key: 'requests',  label: 'طلبات',         count: pendingBookings.value.length },
  { key: 'past',      label: 'مواعيد سابقة',  count: bookings.value.filter(b => b.status === 'completed').length },
  { key: 'cancelled', label: 'مواعيد ملغية',  count: bookings.value.filter(b => b.status === 'cancelled').length },
])

const calendarTitle = computed(() => {
  return new Date(filterDate.value).toLocaleDateString('ar-SA-u-ca-gregory', {
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
  })
})

function bookingsByHour(hour: number) {
  return filteredBookings.value.filter(b => {
    const h = new Date(b.starts_at).getHours()
    return h === hour
  })
}

function prevDay() {
  const d = new Date(filterDate.value)
  d.setDate(d.getDate() - 1)
  filterDate.value = d.toISOString().slice(0, 10)
  load()
}
function nextDay() {
  const d = new Date(filterDate.value)
  d.setDate(d.getDate() + 1)
  filterDate.value = d.toISOString().slice(0, 10)
  load()
}

function formatDt(d: string) {
  return new Date(d).toLocaleString('ar-SA', { dateStyle: 'short', timeStyle: 'short' })
}
function statusLabel(s: string) {
  return { pending: 'معلق', confirmed: 'مؤكد', in_progress: 'جارٍ', completed: 'مكتمل', cancelled: 'ملغي' }[s] ?? s
}
function statusBadge(s: string) {
  return { confirmed: 'bg-green-100 text-green-700', pending: 'bg-yellow-100 text-yellow-700', in_progress: 'bg-blue-100 text-blue-700', completed: 'bg-gray-100 text-gray-600', cancelled: 'bg-red-100 text-red-700' }[s] ?? 'bg-gray-100 text-gray-600'
}

async function checkAvailability() {
  checkingAvail.value = true; availResult.value = null
  try {
    const r = await apiClient.post('/bookings/availability', { branch_id: 1, ...avail.value })
    availResult.value = r.data
  } catch { availResult.value = { available: false } }
  finally { checkingAvail.value = false }
}

async function load() {
  loading.value = true
  try {
    const r = await apiClient.get(`/bookings?date=${filterDate.value}`)
    bookings.value = r.data?.data ?? []
  } finally { loading.value = false }
}

async function confirmBooking(id: number) {
  try {
    await apiClient.patch(`/bookings/${id}`, { status: 'confirmed' })
    await load()
  } catch { /* silent */ }
}

async function cancelBooking(id: number) {
  try {
    await apiClient.patch(`/bookings/${id}`, { status: 'cancelled' })
    await load()
  } catch { /* silent */ }
}

function exportBookings() {
  window.print()
}

async function save() {
  saving.value = true; modalError.value = ''
  try {
    await apiClient.post('/bookings', form.value)
    await load()
    showModal.value = false
    form.value = { bay_id: '', starts_at: '', duration_minutes: 60, service_type: '' }
  } catch (e: any) {
    modalError.value = e?.response?.data?.message ?? 'حدث خطأ'
  } finally { saving.value = false }
}

onMounted(async () => {
  const [, b] = await Promise.all([load(), apiClient.get('/bays')])
  bays.value = b.data?.data ?? []
})
</script>
