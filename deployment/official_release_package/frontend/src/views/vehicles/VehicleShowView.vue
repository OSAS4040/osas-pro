<template>
  <div class="space-y-5" dir="rtl">
    <NavigationSourceHint />
    <!-- Back + Title -->
    <div class="flex items-center gap-3 flex-wrap">
      <RouterLink to="/vehicles" class="text-gray-400 dark:text-slate-500 hover:text-primary-600 dark:hover:text-primary-400 text-sm flex items-center gap-1 transition-colors">
        ← المركبات
      </RouterLink>
      <span class="text-gray-300 dark:text-slate-600">/</span>
      <h1 class="text-xl font-bold text-gray-900 dark:text-white">
        {{ vehicle ? `${vehicle.make} ${vehicle.model}` : '...' }}
      </h1>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-16">
      <div class="w-8 h-8 border-4 border-primary-200 border-t-primary-500 rounded-full animate-spin"></div>
    </div>

    <template v-else-if="vehicle">
      <!-- Action buttons -->
      <div class="flex flex-wrap gap-2 items-center justify-between">
        <div class="flex gap-2 flex-wrap">
          <RouterLink :to="`/vehicles/${vehicle.id}/card`"
                      class="flex items-center gap-2 px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-xl hover:bg-primary-700 transition-colors"
          >
            <CreditCardIcon class="w-4 h-4" />
            البطاقة الرقمية
          </RouterLink>
          <RouterLink :to="`/vehicles/${vehicle.id}/passport`"
                      class="flex items-center gap-2 px-4 py-2 border border-gray-200 dark:border-slate-600 text-gray-700 dark:text-slate-300 bg-white dark:bg-slate-800 text-sm font-medium rounded-xl hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors"
          >
            <DocumentTextIcon class="w-4 h-4" />
            جواز المركبة
          </RouterLink>
        </div>
        <span class="text-xs text-gray-400 dark:text-slate-500">#{{ vehicle.id }}</span>
      </div>
      <p class="text-[11px] leading-relaxed text-gray-500 dark:text-slate-400 max-w-2xl">
        من «البطاقة الرقمية» تُنشأ بطاقة بمظهر محفظة مع رمز مسح عام (OSAS Pro) للتحقق من المركبة دون كشف التفاصيل الكاملة؛ يُدار الرابط من نفس صفحة البطاقة (تدوير / إبطال / إصدار جديد).
        في الإنتاج اضبط <code class="rounded bg-gray-100 px-1 py-0.5 text-[10px] dark:bg-slate-700">APP_PUBLIC_URL</code> ليطابق نطاق الواجهة التي يفتحها من يمسح الرمز.
      </p>

      <!-- Tabs -->
      <div
        class="flex gap-1 bg-gray-100 dark:bg-slate-700/50 rounded-xl p-1 flex-wrap"
        role="tablist"
        aria-label="أقسام ملف المركبة"
      >
        <button
          v-for="t in tabs"
          :id="`vehicle-tab-${t.id}`"
          :key="t.id"
          type="button"
          role="tab"
          :aria-selected="activeTab === t.id"
          :aria-controls="`vehicle-panel-${t.id}`"
          :tabindex="activeTab === t.id ? 0 : -1"
          :class="activeTab === t.id
            ? 'bg-white dark:bg-slate-800 text-primary-700 dark:text-primary-400 shadow-sm'
            : 'text-gray-600 dark:text-slate-400 hover:text-gray-800 dark:hover:text-slate-200'"
          class="px-4 py-2 rounded-lg text-sm font-medium transition-all"
          @click="selectTab(t.id)"
          @keydown="onTabKeydown($event, t.id)"
        >
          {{ t.label }}
        </button>
      </div>

      <!-- ── Tab: معلومات ── -->
      <div
        v-if="activeTab === 'info'"
        id="vehicle-panel-info"
        role="tabpanel"
        aria-labelledby="vehicle-tab-info"
        tabindex="0"
        class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 shadow-sm p-6 outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
      >
        <h2 class="text-base font-bold text-gray-800 dark:text-white mb-5 flex items-center gap-2">
          <TruckIcon class="w-5 h-5 text-primary-500" />
          بيانات المركبة
        </h2>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-5 text-sm">
          <div>
            <p class="text-xs text-gray-400 dark:text-slate-500 mb-0.5">رقم اللوحة</p>
            <p class="font-bold text-lg font-mono text-gray-900 dark:text-white">{{ vehicle.plate_number || '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-400 dark:text-slate-500 mb-0.5">الشركة المصنّعة</p>
            <p class="font-semibold text-gray-800 dark:text-slate-200">{{ vehicle.make || '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-400 dark:text-slate-500 mb-0.5">الموديل</p>
            <p class="font-semibold text-gray-800 dark:text-slate-200">{{ vehicle.model || '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-400 dark:text-slate-500 mb-0.5">سنة الصنع</p>
            <p class="font-semibold text-gray-800 dark:text-slate-200">{{ vehicle.year || '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-400 dark:text-slate-500 mb-0.5">اللون</p>
            <p class="font-semibold text-gray-800 dark:text-slate-200">{{ vehicle.color || '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-400 dark:text-slate-500 mb-0.5">نوع الوقود</p>
            <p class="font-semibold text-gray-800 dark:text-slate-200 capitalize">{{ vehicle.fuel_type || '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-400 dark:text-slate-500 mb-0.5">ناقل الحركة</p>
            <p class="font-semibold text-gray-800 dark:text-slate-200 capitalize">{{ vehicle.transmission || '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-400 dark:text-slate-500 mb-0.5">رقم الهيكل (VIN)</p>
            <p class="font-mono text-sm text-gray-800 dark:text-slate-200">{{ vehicle.vin || '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-400 dark:text-slate-500 mb-0.5">العداد عند الدخول</p>
            <p class="font-semibold text-gray-800 dark:text-slate-200">{{ vehicle.mileage_in != null ? vehicle.mileage_in.toLocaleString('ar-SA') + ' كم' : '—' }}</p>
          </div>
        </div>
        <div v-if="vehicle.customer" class="mt-5 pt-5 border-t border-gray-100 dark:border-slate-700">
          <p class="text-xs text-gray-400 dark:text-slate-500 mb-1">المالك</p>
          <RouterLink
            :to="vehicle.customer.id ? `/customers/${vehicle.customer.id}` : '/customers'"
            class="font-semibold text-primary-600 dark:text-primary-400 hover:underline text-sm"
          >
            {{ vehicle.customer.name }}
          </RouterLink>
        </div>
      </div>

      <!-- ── Tab: تاريخ الخدمة ── -->
      <div
        v-else-if="activeTab === 'history'"
        id="vehicle-panel-history"
        role="tabpanel"
        aria-labelledby="vehicle-tab-history"
        tabindex="0"
        class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 shadow-sm overflow-hidden outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
      >
        <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
          <h2 class="font-bold text-gray-800 dark:text-white flex items-center gap-2">
            <ClipboardDocumentIcon class="w-5 h-5 text-blue-500" />
            أوامر العمل
          </h2>
          <RouterLink to="/work-orders/new" class="text-xs text-primary-600 dark:text-primary-400 hover:underline">+ أمر عمل جديد</RouterLink>
        </div>
        <div v-if="loadingWO" class="flex justify-center py-10">
          <div class="w-6 h-6 border-4 border-blue-200 border-t-blue-500 rounded-full animate-spin"></div>
        </div>
        <div v-else-if="workOrders.length" class="divide-y divide-gray-100 dark:divide-slate-700">
          <div v-for="wo in workOrders" :key="wo.id"
               class="flex items-center justify-between px-5 py-3.5 hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors"
          >
            <div>
              <RouterLink :to="`/work-orders/${wo.id}`"
                          class="font-medium text-primary-600 dark:text-primary-400 hover:underline text-sm"
              >
                {{ wo.order_number }}
              </RouterLink>
              <p class="text-xs text-gray-400 dark:text-slate-500 mt-0.5">{{ fmtDate(wo.created_at) }}</p>
            </div>
            <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                  :class="workOrderStatusBadgeClass(wo.status)"
            >{{ workOrderStatusLabel(wo.status) }}</span>
          </div>
        </div>
        <div v-else class="py-12 text-center text-gray-400 dark:text-slate-500 text-sm">لا توجد أوامر عمل لهذه المركبة</div>
      </div>

      <!-- ── Tab: المستندات ── -->
      <div
        v-else-if="activeTab === 'docs'"
        id="vehicle-panel-docs"
        role="tabpanel"
        aria-labelledby="vehicle-tab-docs"
        tabindex="0"
        class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 shadow-sm p-6 outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
      >
        <h2 class="font-bold text-gray-800 dark:text-white mb-5 flex items-center gap-2">
          <DocumentTextIcon class="w-5 h-5 text-purple-500" />
          المستندات
        </h2>
        <IntelligentVehicleDocumentPanel v-if="vehicle" :vehicle-id="vehicle.id" class="mb-6" />
        <div class="grid sm:grid-cols-2 gap-4">
          <RouterLink :to="`/vehicles/${vehicle.id}/card`"
                      class="flex items-center gap-3 p-4 border border-primary-200 dark:border-primary-800/40 bg-primary-50 dark:bg-primary-900/20 rounded-xl hover:bg-primary-100 dark:hover:bg-primary-900/30 transition-colors"
          >
            <CreditCardIcon class="w-6 h-6 text-primary-600 dark:text-primary-400 flex-shrink-0" />
            <div>
              <p class="font-semibold text-gray-800 dark:text-white text-sm">البطاقة الرقمية</p>
              <p class="text-xs text-gray-500 dark:text-slate-400">عرض وطباعة بطاقة المركبة</p>
            </div>
          </RouterLink>
          <RouterLink :to="`/vehicles/${vehicle.id}/passport`"
                      class="flex items-center gap-3 p-4 border border-purple-200 dark:border-purple-800/40 bg-purple-50 dark:bg-purple-900/20 rounded-xl hover:bg-purple-100 dark:hover:bg-purple-900/30 transition-colors"
          >
            <DocumentTextIcon class="w-6 h-6 text-purple-600 dark:text-purple-400 flex-shrink-0" />
            <div>
              <p class="font-semibold text-gray-800 dark:text-white text-sm">جواز المركبة</p>
              <p class="text-xs text-gray-500 dark:text-slate-400">السجل الكامل والتاريخ</p>
            </div>
          </RouterLink>
        </div>
      </div>

      <!-- ── Tab: الإعدادات ── -->
      <div
        v-else-if="activeTab === 'settings'"
        id="vehicle-panel-settings"
        role="tabpanel"
        aria-labelledby="vehicle-tab-settings"
        tabindex="0"
        class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 shadow-sm p-6 outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
      >
        <h2 class="font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
          <Cog6ToothIcon class="w-5 h-5 text-gray-500" />
          إعدادات المركبة
        </h2>
        <div class="flex items-center gap-3">
          <div class="w-2.5 h-2.5 rounded-full" :class="vehicle.is_active ? 'bg-green-500' : 'bg-red-400'"></div>
          <span class="text-sm text-gray-700 dark:text-slate-300 font-medium">
            {{ vehicle.is_active ? 'المركبة نشطة' : 'المركبة غير نشطة' }}
          </span>
        </div>
        <p class="mt-4 text-xs text-gray-400 dark:text-slate-500">لتعديل بيانات المركبة يرجى التواصل مع الدعم الفني أو التعديل من قائمة المركبات.</p>
      </div>

      <!-- ── Tab: الوصول السريع (رمز الاستجابة في البطاقة الرقمية) ── -->
      <div
        v-else-if="activeTab === 'quick_access'"
        id="vehicle-panel-quick_access"
        role="tabpanel"
        aria-labelledby="vehicle-tab-quick_access"
        tabindex="0"
        class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 shadow-sm p-6 text-center outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
      >
        <h2 class="font-bold text-gray-800 dark:text-white mb-2 flex items-center justify-center gap-2">
          <QrCodeIcon class="w-5 h-5 text-teal-500" />
          الوصول السريع
        </h2>
        <p class="text-xs text-gray-500 dark:text-slate-400 mb-6 max-w-sm mx-auto leading-relaxed">
          رمز الاستجابة السريعة (QR) يُعرض داخل <strong class="text-gray-700 dark:text-slate-300">البطاقة الرقمية</strong> مع الرصيد والزيارات — وليس هنا كي لا نكرر محتوى مضلّل.
        </p>
        <div class="inline-flex flex-col items-center gap-4">
          <p class="text-sm text-gray-600 dark:text-slate-400">
            اللوحة:
            <strong class="font-mono text-gray-900 dark:text-white" dir="ltr">{{ vehicle.plate_number }}</strong>
          </p>
          <RouterLink
            :to="`/vehicles/${vehicle.id}/card`"
            class="px-5 py-2.5 bg-teal-600 text-white text-sm font-medium rounded-xl hover:bg-teal-700 transition-colors min-h-[44px] inline-flex items-center"
          >
            فتح البطاقة الرقمية ورمز QR
          </RouterLink>
        </div>
      </div>
    </template>

    <!-- Error / not found -->
    <div v-else class="text-center py-16 text-gray-500 dark:text-slate-400" role="alert">
      <TruckIcon class="w-12 h-12 mx-auto mb-3 opacity-30 text-gray-400" />
      <p class="text-sm font-medium text-gray-700 dark:text-slate-300">{{ vehicleLoadError }}</p>
      <button
        type="button"
        class="mt-4 inline-flex items-center justify-center rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-700 min-h-[44px]"
        @click="fetchVehicle"
      >
        إعادة المحاولة
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, nextTick } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import {
  CreditCardIcon, DocumentTextIcon, TruckIcon, ClipboardDocumentIcon,
  Cog6ToothIcon, QrCodeIcon,
} from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import { workOrderStatusLabel, workOrderStatusBadgeClass } from '@/utils/workOrderStatusLabels'
import NavigationSourceHint from '@/components/NavigationSourceHint.vue'
import IntelligentVehicleDocumentPanel from '@/components/intelligence/IntelligentVehicleDocumentPanel.vue'

interface Vehicle {
  id: number
  plate_number: string
  make: string
  model: string
  year: number | null
  color: string | null
  fuel_type: string | null
  transmission: string | null
  mileage_in: number | null
  vin: string | null
  is_active: boolean
  customer?: { id: number; name: string }
}

const route = useRoute()
const vehicle = ref<Vehicle | null>(null)
const workOrders = ref<any[]>([])
const loading = ref(false)
const loadingWO = ref(false)
const activeTab = ref('info')
const vehicleLoadError = ref('تعذّر تحميل بيانات المركبة.')

const tabs = [
  { id: 'info',         label: 'معلومات' },
  { id: 'history',      label: 'تاريخ الخدمة' },
  { id: 'docs',         label: 'المستندات' },
  { id: 'settings',     label: 'الإعدادات' },
  { id: 'quick_access', label: 'الوصول السريع' },
]

function focusTabButton(tabId: string) {
  void nextTick(() => {
    document.getElementById(`vehicle-tab-${tabId}`)?.focus()
  })
}

function selectTab(tabId: string) {
  activeTab.value = tabId
  focusTabButton(tabId)
}

/** تنقل أفقي للتبويبات — يحترم اتجاه RTL */
function onTabKeydown(e: KeyboardEvent, currentId: string) {
  const idx = tabs.findIndex((t) => t.id === currentId)
  if (idx < 0) return

  const rtl = typeof document !== 'undefined' && document.documentElement.dir === 'rtl'

  if (e.key === 'Home') {
    e.preventDefault()
    selectTab(tabs[0].id)
    return
  }
  if (e.key === 'End') {
    e.preventDefault()
    selectTab(tabs[tabs.length - 1].id)
    return
  }

  let delta = 0
  if (e.key === 'ArrowRight') delta = rtl ? -1 : 1
  else if (e.key === 'ArrowLeft') delta = rtl ? 1 : -1
  else return

  e.preventDefault()
  const next = (idx + delta + tabs.length) % tabs.length
  selectTab(tabs[next].id)
}

async function fetchVehicle() {
  loading.value = true
  vehicle.value = null
  try {
    const { data } = await apiClient.get(`/vehicles/${route.params.id}`)
    vehicle.value = data.data
    if (data.data) {
      vehicleLoadError.value = 'تعذّر تحميل بيانات المركبة.'
    } else {
      vehicleLoadError.value = 'لا توجد مركبة بهذا المعرّف.'
    }
  } catch (err: unknown) {
    vehicle.value = null
    const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
    vehicleLoadError.value =
      typeof msg === 'string' && msg.trim() !== '' ? msg : 'تعذّر تحميل بيانات المركبة. تحقق من الاتصال أو الصلاحيات.'
  } finally {
    loading.value = false
  }
}

async function fetchWorkOrders() {
  loadingWO.value = true
  try {
    const { data } = await apiClient.get('/work-orders', { params: { vehicle_id: route.params.id } })
    workOrders.value = data.data?.data ?? data.data ?? []
  } catch {
    workOrders.value = []
  } finally {
    loadingWO.value = false
  }
}

function fmtDate(d: string) {
  return d ? new Date(d).toLocaleDateString('ar-SA', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'
}

onMounted(() => {
  fetchVehicle()
  fetchWorkOrders()
})
</script>
