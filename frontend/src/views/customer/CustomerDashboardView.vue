<template>
  <div class="space-y-5">
    <!-- Title -->
    <div>
      <h2 class="text-lg font-bold text-gray-900">لوحة التحكم — إصدار 2026</h2>
      <p class="text-xs text-gray-500 mt-0.5">نظرة ذكية على الأسطول والطلبات والمحفظة</p>
    </div>

    <!-- Quick Access Chips -->
    <div class="flex flex-wrap gap-2">
      <RouterLink v-for="q in quickLinks" :key="q.label" :to="q.to"
                  class="flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-200 rounded-full text-xs font-medium text-gray-700 hover:bg-orange-50 hover:border-orange-300 transition-colors shadow-sm"
      >
        <component :is="q.icon" class="w-3.5 h-3.5 text-gray-500" />
        {{ q.label }}
      </RouterLink>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-3 gap-3">
      <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
        <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center mx-auto mb-2">
          <TruckIcon class="w-5 h-5 text-purple-600" />
        </div>
        <p class="text-2xl font-bold text-gray-900">{{ kpi.totalVehicles }}</p>
        <p class="text-xs text-gray-500 mt-0.5">إجمالي المركبات</p>
        <p class="text-[11px] text-gray-400">مركبات في الأسطول</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
        <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center mx-auto mb-2">
          <CreditCardIcon class="w-5 h-5 text-green-600" />
        </div>
        <p class="text-lg font-bold text-gray-900">{{ fmtMoney(kpi.walletBalance) }}</p>
        <p class="text-xs text-gray-500 mt-0.5">رصيد المحفظة</p>
        <p class="text-[11px] text-gray-400">ريال سعودي</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
        <div class="w-10 h-10 bg-yellow-100 rounded-xl flex items-center justify-center mx-auto mb-2">
          <StarIcon class="w-5 h-5 text-yellow-500" />
        </div>
        <p class="text-2xl font-bold text-gray-900">—</p>
        <p class="text-xs text-gray-500 mt-0.5">تقييم المزودين</p>
        <p class="text-[11px] text-gray-400">متوسط عام</p>
      </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 gap-4">
      <!-- Vehicles by Type (donut) -->
      <div class="bg-white rounded-xl border border-gray-100 p-5">
        <h3 class="text-sm font-semibold text-gray-800 mb-4">توزيع المركبات حسب النوع</h3>
        <div class="flex items-center gap-6">
          <div class="relative w-24 h-24 flex-shrink-0">
            <svg viewBox="0 0 36 36" class="w-24 h-24 -rotate-90">
              <circle cx="18" cy="18" r="15.9" fill="none" stroke="#e5e7eb" stroke-width="3" />
              <circle v-for="(seg, i) in donutSegments" :key="i"
                      cx="18" cy="18" r="15.9" fill="none" :stroke="seg.color" stroke-width="3"
                      :stroke-dasharray="`${seg.dash} 100`"
                      :stroke-dashoffset="`-${seg.offset}`" stroke-linecap="round"
              />
            </svg>
            <div class="absolute inset-0 flex items-center justify-center">
              <p class="text-sm font-bold text-gray-900">{{ kpi.totalVehicles }}</p>
            </div>
          </div>
          <div class="space-y-2 flex-1">
            <div v-for="(t, i) in vehicleTypes" :key="i" class="flex items-center justify-between text-xs">
              <span class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full" :style="{ background: typeColors[i % typeColors.length] }"></span>
                {{ t.label }}
              </span>
              <span class="font-semibold text-gray-700">{{ t.count }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Orders Trend (requests trend line chart) -->
    <div class="bg-white rounded-xl border border-gray-100 p-5">
      <h3 class="text-sm font-semibold text-gray-800 mb-3">اتجاه الطلبات</h3>
      <div class="flex items-end gap-1.5 h-20">
        <div v-for="(d, i) in weekDays" :key="i" class="flex-1 flex flex-col items-center gap-1">
          <div class="w-full rounded-sm bg-purple-400 transition-all"
               :style="{ height: `${Math.max(d * 14, 3)}px`, opacity: d === 0 ? 0.3 : 1 }"
          ></div>
          <span class="text-[10px] text-gray-400">{{ weekLabels[i] }}</span>
        </div>
      </div>
    </div>

    <!-- 4 Nav Cards -->
    <div class="grid grid-cols-2 gap-3">
      <RouterLink to="/customer/bookings"
                  class="bg-white rounded-2xl p-4 border border-gray-100 flex flex-col items-center gap-2 hover:border-orange-300 hover:shadow-md transition-all"
      >
        <div class="w-11 h-11 bg-orange-100 rounded-xl flex items-center justify-center">
          <CalendarDaysIcon class="w-6 h-6 text-orange-600" />
        </div>
        <span class="text-xs font-semibold text-gray-800">حجز موعد</span>
      </RouterLink>
      <RouterLink to="/customer/vehicles"
                  class="bg-white rounded-2xl p-4 border border-gray-100 flex flex-col items-center gap-2 hover:border-orange-300 hover:shadow-md transition-all"
      >
        <div class="w-11 h-11 bg-purple-100 rounded-xl flex items-center justify-center">
          <TruckIcon class="w-6 h-6 text-purple-600" />
        </div>
        <span class="text-xs font-semibold text-gray-800">مركباتي</span>
      </RouterLink>
      <RouterLink to="/customer/invoices"
                  class="bg-white rounded-2xl p-4 border border-gray-100 flex flex-col items-center gap-2 hover:border-orange-300 hover:shadow-md transition-all"
      >
        <div class="w-11 h-11 bg-green-100 rounded-xl flex items-center justify-center">
          <DocumentTextIcon class="w-6 h-6 text-green-600" />
        </div>
        <span class="text-xs font-semibold text-gray-800">فواتيري</span>
      </RouterLink>
      <RouterLink to="/customer"
                  class="bg-white rounded-2xl p-4 border border-gray-100 flex flex-col items-center gap-2 hover:border-orange-300 hover:shadow-md transition-all"
      >
        <div class="w-11 h-11 bg-blue-100 rounded-xl flex items-center justify-center">
          <ChatBubbleLeftRightIcon class="w-6 h-6 text-blue-600" />
        </div>
        <span class="text-xs font-semibold text-gray-800">الدعم الفني</span>
      </RouterLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import {
  TruckIcon, CreditCardIcon, StarIcon, CalendarDaysIcon,
  DocumentTextIcon, ChatBubbleLeftRightIcon, ClipboardDocumentListIcon,
  WrenchScrewdriverIcon,
} from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'

const vehicles    = ref<any[]>([])
const walletBal   = ref(0)
const weekDays    = ref([0, 0, 0, 0, 0, 0, 0])
const weekLabels  = ['السبت', 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة']
const typeColors  = ['#7c3aed', '#3b82f6', '#10b981', '#f97316', '#ec4899']

const quickLinks = [
  { label: 'المركبات',   to: '/customer/vehicles',   icon: TruckIcon },
  { label: 'الطلبات',    to: '/customer/bookings',   icon: ClipboardDocumentListIcon },
  { label: 'الصيانة',    to: '/customer/bookings',   icon: WrenchScrewdriverIcon },
  { label: 'المحفظة',    to: '/customer/invoices',   icon: CreditCardIcon },
  { label: 'الفواتير',   to: '/customer/invoices',   icon: DocumentTextIcon },
  { label: 'إنشاء طلب', to: '/customer/bookings',   icon: CalendarDaysIcon },
]

const kpi = computed(() => ({
  totalVehicles: vehicles.value.length,
  walletBalance: walletBal.value,
}))

const vehicleTypes = computed(() => {
  const map: Record<string, number> = {}
  vehicles.value.forEach(v => {
    const k = v.vehicle_type ?? v.type ?? 'أخرى'
    map[k] = (map[k] ?? 0) + 1
  })
  return Object.entries(map).map(([label, count]) => ({ label, count }))
})

const donutSegments = computed(() => {
  const total = kpi.value.totalVehicles || 1
  let offset  = 0
  return vehicleTypes.value.map((t, i) => {
    const dash = (t.count / total) * 100
    const seg  = { color: typeColors[i % typeColors.length], dash, offset }
    offset += dash
    return seg
  })
})

async function load() {
  try {
    const { data } = await apiClient.get('/vehicles', { params: { per_page: 100 } })
    vehicles.value = data.data ?? []
  } catch { /* silent */ }
}

function fmtMoney(v: number) {
  return v.toLocaleString('ar-SA', { minimumFractionDigits: 2 })
}

onMounted(load)
</script>
