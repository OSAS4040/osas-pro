<template>
  <div class="app-shell-page space-y-5" dir="rtl">
    <!-- Header -->
    <div class="page-head no-print">
      <div class="page-title-wrap">
        <h2 class="page-title-xl flex items-center gap-2">
          <CalendarDaysIcon class="w-6 h-6 text-primary-600 dark:text-primary-400" />
          {{ l('مواعيد وحجوزات', 'Bookings & Appointments') }}
        </h2>
        <p v-if="bookingSmartHint" class="page-subtitle text-primary-700 dark:text-primary-300">{{ bookingSmartHint }}</p>
      </div>
      <div class="page-toolbar">
        <button class="flex items-center gap-1.5 px-3 py-2 border border-indigo-300 rounded-lg text-sm text-indigo-600 hover:bg-indigo-50 transition-colors"
                @click="showPublicLink = true"
        >
          <LinkIcon class="w-4 h-4" /> {{ l('الرابط العام', 'Public link') }}
        </button>
        <button class="flex items-center gap-1.5 px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors"
                @click="exportBookings"
        >
          <ArrowDownTrayIcon class="w-4 h-4" /> {{ l('تصدير', 'Export') }}
        </button>
        <button class="flex items-center gap-1.5 px-3 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 transition-colors"
                @click="showModal = true"
        >
          <PlusIcon class="w-4 h-4" /> {{ l('إضافة موعد', 'Add booking') }}
        </button>
      </div>
    </div>

    <!-- Tabs -->
    <div class="no-print flex items-center justify-between flex-wrap gap-3">
      <div class="flex gap-0.5 bg-gray-100 dark:bg-slate-800 p-1 rounded-xl">
        <button v-for="tab in tabs" :key="tab.key" class="px-4 py-1.5 rounded-lg text-sm font-medium transition-colors"
                :class="activeTab === tab.key ? 'bg-white dark:bg-slate-700 text-primary-700 dark:text-primary-300 shadow-sm' : 'text-gray-600 dark:text-slate-400 hover:text-gray-800 dark:hover:text-slate-200'"
                @click="activeTab = tab.key"
        >
          {{ tab.label }}
          <span v-if="tab.count > 0"
                class="mr-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                :class="activeTab === tab.key ? 'bg-primary-100 text-primary-700' : 'bg-gray-200 text-gray-600'"
          >
            {{ tab.count }}
          </span>
        </button>
      </div>

      <!-- View Toggle: قائمة / تقويم -->
      <div class="flex gap-1 bg-gray-100 dark:bg-slate-800 p-1 rounded-lg">
        <button class="p-1.5 rounded-md transition-colors"
                :class="viewMode === 'list' ? 'bg-white dark:bg-slate-700 shadow-sm text-primary-600 dark:text-primary-400' : 'text-gray-500 dark:text-slate-400 hover:text-gray-700'"
                @click="viewMode = 'list'"
        >
          <Bars3Icon class="w-4 h-4" />
        </button>
        <button class="p-1.5 rounded-md transition-colors"
                :class="viewMode === 'calendar' ? 'bg-white dark:bg-slate-700 shadow-sm text-primary-600 dark:text-primary-400' : 'text-gray-500 dark:text-slate-400 hover:text-gray-700'"
                @click="viewMode = 'calendar'"
        >
          <CalendarDaysIcon class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="no-print bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-slate-700 p-4 space-y-3 transition-colors">
      <div class="flex flex-wrap gap-2">
        <button type="button" class="px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors"
                :class="isTodayFilter ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/30 text-primary-800 dark:text-primary-200' : 'border-gray-200 dark:border-slate-600 text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700'"
                @click="setFilterOffset(0)"
        >
          {{ l('اليوم', 'Today') }}
        </button>
        <button type="button" class="px-3 py-1.5 rounded-lg text-xs font-medium border border-gray-200 dark:border-slate-600 text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700"
                @click="setFilterOffset(1)"
        >
          {{ l('غداً', 'Tomorrow') }}
        </button>
        <button type="button" class="px-3 py-1.5 rounded-lg text-xs font-medium border border-gray-200 dark:border-slate-600 text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700"
                @click="setFilterOffset(7)"
        >
          {{ l('بعد أسبوع', 'In one week') }}
        </button>
      </div>
      <div class="flex gap-3 flex-wrap items-end">
        <div>
          <label class="block text-xs font-medium text-gray-500 dark:text-slate-400 mb-1">{{ l('منطقة العمل', 'Work area') }}</label>
          <select v-model="filterBay" class="border border-gray-200 dark:border-slate-600 dark:bg-slate-900 rounded-lg px-3 py-2 text-sm text-gray-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-primary-400">
            <option value="">{{ l('جميع مناطق العمل', 'All work areas') }}</option>
            <option v-for="b in bays" :key="b.id" :value="b.id">{{ b.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-500 dark:text-slate-400 mb-1">{{ l('التاريخ', 'Date') }}</label>
          <SmartDatePicker
            :model-value="filterDate"
            mode="single"
            @change="onBookingDateChange"
          />
        </div>
      </div>
      <!-- جدول ساعات العمل (من إعدادات الفرع) -->
      <div
        v-if="activeTab === 'bookings'"
        class="rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50/80 dark:bg-slate-900/40 px-4 py-3 text-xs text-slate-700 dark:text-slate-300"
      >
        <p class="font-semibold text-slate-800 dark:text-slate-100 mb-1">{{ branchHoursIntro }}</p>
        <ul v-if="branchHoursLines.length" class="list-disc pr-4 space-y-0.5">
          <li v-for="(line, idx) in branchHoursLines" :key="idx">{{ line }}</li>
        </ul>
        <p v-else class="text-slate-500 dark:text-slate-400 leading-relaxed">
          {{
            l(
              'لم يُضبط جدول ساعات في بيانات الفرع بعد — يُسمح بالحجز في أي وقت طالما توجد منطقة عمل متاحة. من «إدارة الفروع» → تعديل الفرع → قسم «ساعات العمل والحجوزات».',
              'No branch opening hours yet — bookings are allowed whenever a work area is free. Set them under Branches → edit branch → Business hours.',
            )
          }}
        </p>
      </div>
    </div>

    <div class="print-container space-y-5">
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
            <p class="text-sm">{{ l('لا توجد طلبات حجز حالياً', 'No booking requests right now') }}</p>
            <button class="no-print mt-3 flex items-center gap-1.5 mx-auto text-sm text-gray-500 hover:text-gray-700" @click="load">
              <ArrowPathIcon class="w-4 h-4" /> {{ l('تحديث', 'Refresh') }}
            </button>
          </div>
          <div v-else class="space-y-3">
            <div v-for="b in pendingBookings" :key="b.id"
                 class="bg-white rounded-xl p-4 flex items-center justify-between shadow-sm border border-blue-100"
            >
              <div>
                <p class="font-semibold text-gray-800 text-sm">{{ b.service_type }}</p>
                <p class="text-xs text-gray-500 mt-0.5">{{ formatDt(b.starts_at) }} • {{ b.bay?.name }}</p>
              </div>
              <div class="flex gap-2 no-print">
                <button class="px-3 py-1.5 bg-green-600 text-white text-xs rounded-lg hover:bg-green-700" @click="confirmBooking(b.id)">{{ l('تأكيد', 'Confirm') }}</button>
                <button class="px-3 py-1.5 bg-red-100 text-red-700 text-xs rounded-lg hover:bg-red-200" @click="cancelBooking(b.id)">{{ l('رفض', 'Reject') }}</button>
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- Availability Check -->
      <template v-if="activeTab === 'bookings'">
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
          <h3 class="font-semibold text-blue-900 mb-3 text-sm no-print">{{ l('فحص التوفر', 'Availability check') }}</h3>
          <div class="no-print flex gap-3 flex-wrap items-end">
            <div>
              <label class="block text-xs font-medium text-blue-800 mb-1">{{ l('التاريخ والوقت', 'Date & time') }}</label>
              <input v-model="avail.starts_at" type="datetime-local" class="border border-blue-300 rounded-lg px-3 py-2 text-sm focus:outline-none" />
            </div>
            <div>
              <label class="block text-xs font-medium text-blue-800 mb-1">{{ l('المدة (دقيقة)', 'Duration (min)') }}</label>
              <input v-model.number="avail.duration_minutes" type="number" min="15" step="15"
                     class="border border-blue-300 rounded-lg px-3 py-2 text-sm focus:outline-none w-24"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-blue-800 mb-1">{{ l('الخدمة (اختياري)', 'Service (optional)') }}</label>
              <input v-model="avail.capability" placeholder="oil_change" class="border border-blue-300 rounded-lg px-3 py-2 text-sm focus:outline-none" />
            </div>
            <button :disabled="checkingAvail" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50"
                    @click="checkAvailability"
            >
              {{ checkingAvail ? l('جارٍ الفحص...', 'Checking...') : l('فحص', 'Check') }}
            </button>
          </div>
          <div v-if="availResult !== null" class="mt-3 p-3 rounded-lg text-sm"
               :class="availResult.available ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
          >
            {{ availabilityMessage }}
          </div>
        </div>

        <!-- LIST VIEW -->
        <div v-if="viewMode === 'list'" class="table-shell transition-colors">
          <div v-if="loading" class="state-loading">
            <div class="animate-spin rounded-full h-7 w-7 border-b-2 border-primary-600 mx-auto"></div>
          </div>
          <div v-else class="overflow-x-auto">
            <table class="data-table">
              <thead>
                <tr>
                  <th class="px-4 py-3 font-medium">{{ l('منطقة العمل', 'Work area') }}</th>
                  <th class="px-4 py-3 font-medium">{{ l('وقت البداية', 'Start') }}</th>
                  <th class="px-4 py-3 font-medium">{{ l('وقت الانتهاء', 'End') }}</th>
                  <th class="px-4 py-3 font-medium">{{ l('الخدمة', 'Service') }}</th>
                  <th class="px-4 py-3 font-medium">{{ l('المصدر', 'Source') }}</th>
                  <th class="px-4 py-3 font-medium">{{ l('الحالة', 'Status') }}</th>
                  <th class="px-4 py-3 font-medium no-print">{{ l('إجراءات', 'Actions') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="b in filteredBookings" :key="b.id">
                  <td class="px-4 py-3 font-semibold text-gray-800">{{ b.bay?.name ?? `${l('منطقة', 'Area')} ${b.bay_id}` }}</td>
                  <td class="px-4 py-3 text-gray-600 text-xs">{{ formatDt(b.starts_at) }}</td>
                  <td class="px-4 py-3 text-gray-600 text-xs">{{ formatDt(b.ends_at) }}</td>
                  <td class="px-4 py-3 text-gray-700">{{ b.service_type }}</td>
                  <td class="px-4 py-3">
                    <span class="px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full text-xs">{{ b.source ?? l('يدوي', 'manual') }}</span>
                  </td>
                  <td class="px-4 py-3">
                    <span :class="statusBadge(b.status)" class="px-2 py-0.5 rounded-full text-xs font-medium">
                      {{ statusLabel(b.status) }}
                    </span>
                  </td>
                  <td class="px-4 py-3 no-print">
                    <button v-if="b.status === 'pending'" class="text-xs text-green-700 hover:underline ml-2"
                            @click="confirmBooking(b.id)"
                    >
                      {{ l('تأكيد', 'Confirm') }}
                    </button>
                    <button v-if="['pending','confirmed'].includes(b.status)" class="text-xs text-red-600 hover:underline"
                            @click="cancelBooking(b.id)"
                    >
                      {{ l('إلغاء', 'Cancel') }}
                    </button>
                  </td>
                </tr>
                <tr v-if="!filteredBookings.length">
                  <td colspan="7" class="table-empty">{{ l('لا توجد مواعيد', 'No bookings') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- CALENDAR VIEW -->
        <div v-else class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-slate-700 p-5 transition-colors">
          <div class="flex items-center justify-between mb-4">
            <button type="button" class="no-print p-1.5 hover:bg-gray-100 rounded-lg transition-colors" @click="prevDay">
              <ChevronRightIcon class="w-5 h-5 text-gray-600" />
            </button>
            <h3 class="font-semibold text-gray-800">{{ calendarTitle }}</h3>
            <button type="button" class="no-print p-1.5 hover:bg-gray-100 rounded-lg transition-colors" @click="nextDay">
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
                     :class="statusBadge(b.status)"
                >
                  {{ b.service_type }} — {{ b.bay?.name }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- Previous / Cancelled tabs -->
      <template v-if="activeTab === 'past' || activeTab === 'cancelled'">
        <div class="table-shell">
          <table class="data-table">
            <thead>
              <tr>
                <th class="px-4 py-3 font-medium">{{ l('منطقة العمل', 'Work area') }}</th>
                <th class="px-4 py-3 font-medium">{{ l('التاريخ', 'Date') }}</th>
                <th class="px-4 py-3 font-medium">{{ l('الخدمة', 'Service') }}</th>
                <th class="px-4 py-3 font-medium">{{ l('الحالة', 'Status') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="b in tabBookings" :key="b.id">
                <td class="px-4 py-3 font-medium text-gray-800">{{ b.bay?.name ?? '—' }}</td>
                <td class="px-4 py-3 text-gray-500 text-xs">{{ formatDt(b.starts_at) }}</td>
                <td class="px-4 py-3 text-gray-700">{{ b.service_type }}</td>
                <td class="px-4 py-3"><span :class="statusBadge(b.status)" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ statusLabel(b.status) }}</span></td>
              </tr>
              <tr v-if="!tabBookings.length"><td colspan="4" class="table-empty">{{ l('لا توجد مواعيد', 'No bookings') }}</td></tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>

    <!-- New Booking Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal-box max-w-md">
        <div class="flex items-center justify-between px-6 py-4 border-b">
          <h3 class="font-bold text-lg">{{ l('إضافة موعد جديد', 'New booking') }}</h3>
          <button class="btn btn-ghost btn-sm" @click="showModal = false"><XMarkIcon class="w-5 h-5 text-gray-400" /></button>
        </div>
        <form class="form-shell" @submit.prevent="save">
          <div class="form-section">
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ l('منطقة العمل *', 'Work area *') }}</label>
            <select v-model="form.bay_id" required class="field">
              <option value="">{{ l('اختر منطقة عمل', 'Select work area') }}</option>
              <option v-for="b in bays" :key="b.id" :value="b.id" :disabled="b.status !== 'available'">
                {{ b.name }} {{ b.status !== 'available' ? l('(غير متاحة)', '(unavailable)') : '' }}
              </option>
            </select>
          </div>
          <div class="form-section">
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ l('وقت البداية *', 'Start time *') }}</label>
            <input v-model="form.starts_at" type="datetime-local" required class="field" />
          </div>
          <div class="form-grid-2">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">{{ l('المدة (دقيقة)', 'Duration (minutes)') }}</label>
              <input v-model.number="form.duration_minutes" type="number" min="15" step="15" class="field" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">{{ l('نوع الخدمة', 'Service type') }}</label>
              <input v-model="form.service_type" :placeholder="l('تغيير زيت…', 'Oil change…')" class="field" />
            </div>
          </div>
          <p v-if="modalError" class="text-red-600 text-sm bg-red-50 rounded-xl p-3">{{ modalError }}</p>
          <div class="form-actions">
            <button type="button" class="btn btn-outline" @click="showModal = false">{{ l('إلغاء', 'Cancel') }}</button>
            <button type="submit" :disabled="saving" class="btn btn-primary disabled:opacity-50">
              {{ saving ? l('جارٍ الحفظ…', 'Saving…') : l('حفظ الموعد', 'Save booking') }}
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
          {{ l('الرابط العام للحجوزات', 'Public booking link') }}
        </h3>
        <button @click="showPublicLink = false"><XMarkIcon class="w-5 h-5 text-gray-400" /></button>
      </div>
      <p class="text-sm text-gray-500">{{ l('شارك هذا الرابط مع عملائك ليتمكنوا من حجز المواعيد مباشرة', 'Share this link with your customers so they can book directly') }}</p>
      <div class="flex gap-2">
        <input :value="publicBookingUrl" readonly
               class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 bg-gray-50 text-gray-700 select-all"
        />
        <button class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                :class="publicLinkCopied ? 'bg-green-500 text-white' : 'bg-indigo-600 text-white hover:bg-indigo-700'"
                @click="copyPublicLink"
        >
          {{ publicLinkCopied ? l('تم النسخ ✓', 'Copied ✓') : l('نسخ', 'Copy') }}
        </button>
      </div>
      <div class="flex gap-2 pt-2">
        <a :href="`https://wa.me/?text=${encodeURIComponent('احجز موعدك: '+publicBookingUrl)}`" target="_blank"
           class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-green-500 text-white rounded-lg text-sm hover:bg-green-600 transition-colors"
        >
          {{ l('مشاركة واتساب', 'Share WhatsApp') }}
        </a>
        <a :href="`mailto:?subject=حجز موعد&body=${encodeURIComponent('احجز موعدك عبر الرابط: '+publicBookingUrl)}`"
           class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-blue-500 text-white rounded-lg text-sm hover:bg-blue-600 transition-colors"
        >
          {{ l('مشاركة بريد', 'Share Email') }}
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
import SmartDatePicker from '@/components/ui/SmartDatePicker.vue'
import { useLocale } from '@/composables/useLocale'
import { printDocument } from '@/composables/useAppPrint'
import { useAuthStore } from '@/stores/auth'
import {
  BRANCH_DAY_KEYS,
  BRANCH_DAY_LABEL_AR,
  BRANCH_DAY_LABEL_EN,
  normalizeOpeningHoursForDisplay,
  scheduleHasIntervals,
} from '@/utils/branchOpeningHours'

const auth = useAuthStore()
const showPublicLink = ref(false)
const branchContext = ref<{ opening_hours?: Record<string, [string, string][]> | null; name?: string } | null>(null)
const locale = useLocale()
const l = (ar: string, en: string) => (locale.lang.value === 'ar' ? ar : en)
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

function setFilterOffset(days: number) {
  const d = new Date()
  d.setDate(d.getDate() + days)
  filterDate.value = d.toISOString().slice(0, 10)
  load()
}

function onBookingDateChange(val: { from: string; to: string }) {
  filterDate.value = val.from || val.to
  load()
}

const isTodayFilter = computed(() => filterDate.value === new Date().toISOString().slice(0, 10))
const showModal     = ref(false)
const saving        = ref(false)
const modalError    = ref('')
const checkingAvail = ref(false)
const availResult   = ref<any>(null)
const activeTab     = ref<'bookings'|'requests'|'past'|'cancelled'>('bookings')
const viewMode      = ref<'list'|'calendar'>('list')

const bookingSmartHint = computed(() => {
  if (activeTab.value !== 'bookings' || loading.value) return ''
  const conf = bookings.value.filter((b) => b.status === 'confirmed').length
  const pend = bookings.value.filter((b) => b.status === 'pending').length
  return l(
    `${conf} مؤكد · ${pend} بانتظار المراجعة — حسب التاريخ المختار`,
    `${conf} confirmed · ${pend} pending review — for the selected date`,
  )
})

const branchHoursLines = computed(() => {
  const oh = normalizeOpeningHoursForDisplay(branchContext.value?.opening_hours)
  if (!oh || !scheduleHasIntervals(oh as Record<string, unknown>)) return []
  const lines: string[] = []
  for (const k of BRANCH_DAY_KEYS) {
    const slots = oh[k]
    if (!Array.isArray(slots) || !slots.length) continue
    const label = l(BRANCH_DAY_LABEL_AR[k], BRANCH_DAY_LABEL_EN[k])
    const ranges = slots.map(([a, b]) => `${a}–${b}`).join('، ')
    lines.push(`${label}: ${ranges}`)
  }
  return lines
})

const branchHoursIntro = computed(() =>
  l(
    'ساعات عمل الفرع (للمرجعية — الحجز يُقيَّد بها عند تعريفها في إعدادات الفرع)',
    'Branch business hours (bookings are limited to these windows when configured under branch settings)',
  ),
)

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
  { key: 'bookings',  label: l('مواعيد', 'Appointments'), count: filteredBookings.value.length },
  { key: 'requests',  label: l('طلبات', 'Requests'), count: pendingBookings.value.length },
  { key: 'past',      label: l('مواعيد سابقة', 'Past'), count: bookings.value.filter(b => b.status === 'completed').length },
  { key: 'cancelled', label: l('مواعيد ملغية', 'Cancelled'), count: bookings.value.filter(b => b.status === 'cancelled').length },
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

const availabilityMessage = computed(() => {
  const r = availResult.value
  if (!r) return ''
  if (r.available) {
    const name = r.bay?.name ?? l('غير محدد', 'n/a')
    return l(`✅ متاح — منطقة عمل: ${name}`, `✅ Available — work area: ${name}`)
  }
  if (r.reason === 'outside_hours') {
    return l('❌ الوقت المحدد خارج ساعات عمل الفرع.', '❌ Outside branch business hours.')
  }
  if (r.reason === 'branch_not_found' || r.reason === 'no_branch') {
    return l('❌ لم يُحدد فرع صالح للمستخدم.', '❌ No valid branch for this user.')
  }
  if (r.reason === 'missing_datetime') {
    return l('❌ اختر التاريخ والوقت أولاً.', '❌ Pick date and time first.')
  }
  return l('❌ لا توجد منطقة عمل متاحة في هذا الوقت.', '❌ No work area is free for this slot.')
})

async function checkAvailability() {
  if (!avail.value.starts_at?.trim()) {
    availResult.value = { available: false, reason: 'missing_datetime' }
    return
  }
  checkingAvail.value = true
  availResult.value = null
  const bid = auth.user?.branch_id
  if (bid == null) {
    availResult.value = { available: false, reason: 'no_branch' }
    checkingAvail.value = false
    return
  }
  try {
    const r = await apiClient.post('/bookings/availability', { branch_id: bid, ...avail.value })
    availResult.value = r.data
  } catch {
    availResult.value = { available: false, reason: 'no_work_area' }
  } finally {
    checkingAvail.value = false
  }
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

async function exportBookings() {
  await printDocument()
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
  const bid = auth.user?.branch_id
  const bayUrl = bid != null ? `/bays?branch_id=${bid}` : '/bays'
  await load()
  try {
    const b = await apiClient.get(bayUrl)
    bays.value = b.data?.data ?? []
  } catch {
    bays.value = []
  }
  if (bid != null) {
    try {
      const br = await apiClient.get(`/branches/${bid}`)
      branchContext.value = br.data?.data ?? null
    } catch {
      branchContext.value = null
    }
  }
})
</script>
