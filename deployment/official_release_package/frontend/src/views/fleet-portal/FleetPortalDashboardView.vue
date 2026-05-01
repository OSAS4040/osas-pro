<template>
  <div class="space-y-5">
    <!-- Title Bar -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">لوحة التحكم — إصدار 2026</h1>
        <p class="text-xs text-gray-400 mt-0.5">نظرة ذكية على أداء الأسطول • إنشارات مبكرة • قرارات سريعة</p>
      </div>
      <div class="flex gap-2">
        <RouterLink to="/fleet-portal/top-up"
                    class="flex items-center gap-1.5 px-3 py-2 bg-teal-600 text-white text-sm font-medium rounded-lg hover:bg-teal-700 transition-colors"
        >
          <CreditCardIcon class="w-4 h-4" /> شحن رصيد
        </RouterLink>
        <RouterLink to="/fleet-portal/new-order"
                    class="flex items-center gap-1.5 px-3 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
        >
          <PlusCircleIcon class="w-4 h-4" /> طلب خدمة
        </RouterLink>
      </div>
    </div>

    <!-- Quick Access Chips -->
    <div class="flex flex-wrap gap-2">
      <RouterLink v-for="chip in chips" :key="chip.label" :to="chip.to"
                  class="flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-200 rounded-full text-xs font-medium text-gray-700 hover:bg-gray-50 hover:border-gray-300 transition-colors shadow-sm"
      >
        <component :is="chip.icon" class="w-3.5 h-3.5 text-gray-500" />
        {{ chip.label }}
      </RouterLink>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-white rounded-xl border border-gray-100 p-5">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-2xl font-bold text-blue-700">{{ kpi.totalOrders }}</p>
            <p class="text-sm text-gray-600 mt-1">إجمالي الطلبات</p>
            <p class="text-xs text-gray-400">لقطة مباشرة</p>
          </div>
          <div class="w-9 h-9 bg-blue-50 rounded-xl flex items-center justify-center">
            <ChartBarIcon class="w-5 h-5 text-blue-600" />
          </div>
        </div>
      </div>
      <div class="bg-white rounded-xl border border-gray-100 p-5">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-2xl font-bold text-orange-600">{{ kpi.needMaintenance }}</p>
            <p class="text-sm text-gray-600 mt-1">تحتاج صيانة</p>
            <p class="text-xs text-gray-400">نسبة {{ kpi.maintenancePct }}%</p>
          </div>
          <div class="w-9 h-9 bg-orange-50 rounded-xl flex items-center justify-center">
            <WrenchScrewdriverIcon class="w-5 h-5 text-orange-600" />
          </div>
        </div>
      </div>
      <div class="bg-white rounded-xl border border-gray-100 p-5">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-2xl font-bold text-green-700">{{ kpi.totalVehicles }}</p>
            <p class="text-sm text-gray-600 mt-1">إجمالي المركبات</p>
            <p class="text-xs text-gray-400">مركبات في الأسطول</p>
          </div>
          <div class="w-9 h-9 bg-green-50 rounded-xl flex items-center justify-center">
            <TruckIcon class="w-5 h-5 text-green-600" />
          </div>
        </div>
      </div>
      <RouterLink to="/fleet-portal/top-up"
                  class="block bg-white rounded-xl border border-gray-100 p-5 hover:border-primary-200 hover:shadow-md transition-all cursor-pointer group"
                  title="شحن رصيد المحفظة"
      >
        <div class="flex items-start justify-between">
          <div>
            <p class="text-2xl font-bold text-primary-700 group-hover:text-primary-800">{{ fmtMoney(kpi.walletBalance) }}</p>
            <p class="text-sm text-gray-600 mt-1">رصيد المحفظة</p>
            <p class="text-xs text-gray-400">ريال سعودي — اضغط للشحن</p>
          </div>
          <div class="w-9 h-9 bg-primary-50 rounded-xl flex items-center justify-center group-hover:bg-primary-100">
            <CreditCardIcon class="w-5 h-5 text-primary-600" />
          </div>
        </div>
      </RouterLink>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <!-- Orders Trend (line-like bar chart) -->
      <div class="bg-white rounded-xl border border-gray-100 p-5 lg:col-span-2">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-sm font-semibold text-gray-800">حركة الطلبات (أسبوع)</h3>
          <div class="flex gap-2 text-xs text-gray-500">
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 bg-teal-500 rounded-sm inline-block"></span>إجمالي</span>
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 bg-blue-400 rounded-sm inline-block"></span>متوسط</span>
          </div>
        </div>
        <div class="flex items-end gap-2 h-28">
          <div v-for="(day, i) in weekDays" :key="i" class="flex-1 flex flex-col items-center gap-1">
            <div class="w-full rounded-t-sm transition-all duration-500"
                 :class="day.val > 0 ? 'bg-teal-500' : 'bg-gray-100'"
                 :style="{ height: `${Math.max(day.val * 20, 4)}px` }"
            >
            </div>
            <span class="text-[10px] text-gray-400">{{ day.label }}</span>
          </div>
        </div>
      </div>

      <!-- Maintenance Status (donut) -->
      <div class="bg-white rounded-xl border border-gray-100 p-5">
        <h3 class="text-sm font-semibold text-gray-800 mb-4">حالة الصيانة</h3>
        <div class="flex items-center justify-center">
          <div class="relative w-28 h-28">
            <svg viewBox="0 0 36 36" class="w-28 h-28 -rotate-90">
              <circle cx="18" cy="18" r="15.9" fill="none" stroke="#e5e7eb" stroke-width="3" />
              <circle cx="18" cy="18" r="15.9" fill="none" stroke="#10b981" stroke-width="3"
                      :stroke-dasharray="`${kpi.maintenancePct === 0 ? 100 : (100 - kpi.maintenancePct)} 100`"
                      stroke-dashoffset="0" stroke-linecap="round"
              />
              <circle cx="18" cy="18" r="15.9" fill="none" stroke="#f97316" stroke-width="3"
                      :stroke-dasharray="`${kpi.maintenancePct} 100`"
                      :stroke-dashoffset="`-${100 - kpi.maintenancePct}`" stroke-linecap="round"
              />
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
              <span class="text-lg font-bold text-gray-900">{{ kpi.maintenancePct }}%</span>
              <span class="text-[10px] text-gray-400">صيانة</span>
            </div>
          </div>
        </div>
        <div class="mt-3 space-y-1.5">
          <div class="flex items-center justify-between text-xs">
            <span class="flex items-center gap-1.5"><span class="w-2 h-2 bg-green-500 rounded-full"></span>سليمة</span>
            <span class="font-medium text-gray-700">{{ kpi.totalVehicles - kpi.needMaintenance }}</span>
          </div>
          <div class="flex items-center justify-between text-xs">
            <span class="flex items-center gap-1.5"><span class="w-2 h-2 bg-orange-500 rounded-full"></span>تحتاج صيانة</span>
            <span class="font-medium text-gray-700">{{ kpi.needMaintenance }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Pending Approvals -->
    <div v-if="isManager && pendingOrders.length" class="bg-orange-50 border border-orange-200 rounded-xl p-5">
      <h2 class="font-semibold text-orange-800 mb-3 flex items-center gap-2 text-sm">
        <span class="w-2 h-2 bg-orange-500 rounded-full animate-pulse"></span>
        طلبات تنتظر اعتماد الائتمان ({{ pendingOrders.length }})
      </h2>
      <div class="space-y-2">
        <div v-for="wo in pendingOrders" :key="wo.id"
             class="bg-white rounded-lg px-4 py-3 flex items-center justify-between shadow-xs"
        >
          <div class="text-sm">
            <span class="font-medium text-gray-800">WO-{{ wo.id }}</span>
            <span class="mx-2 text-gray-300">|</span>
            <span class="text-gray-600">{{ wo.vehicle?.plate_number }}</span>
          </div>
          <div class="flex gap-2">
            <button class="px-3 py-1 bg-green-600 text-white text-xs rounded-lg hover:bg-green-700" @click="approveCredit(wo.id)">اعتماد</button>
            <button class="px-3 py-1 bg-red-100 text-red-700 text-xs rounded-lg hover:bg-red-200" @click="rejectCredit(wo.id)">رفض</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent Orders Table -->
    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
      <div class="px-5 py-3.5 border-b border-gray-50 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-800">آخر طلبات الخدمة</h2>
        <RouterLink to="/fleet-portal/orders" class="text-xs text-teal-600 hover:underline">عرض الكل ←</RouterLink>
      </div>
      <div v-if="!recentOrders.length" class="py-10 text-center text-gray-400 text-sm">
        لا توجد طلبات خدمة بعد
      </div>
      <table v-else class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr class="text-right text-xs text-gray-500">
            <th class="px-5 py-3 font-medium">رقم الطلب</th>
            <th class="px-5 py-3 font-medium">المركبة</th>
            <th class="px-5 py-3 font-medium">الحالة</th>
            <th class="px-5 py-3 font-medium">الائتمان</th>
            <th class="px-5 py-3 font-medium">التاريخ</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="wo in recentOrders" :key="wo.id" class="hover:bg-gray-50 transition-colors">
            <td class="px-5 py-3 font-medium text-teal-700">{{ wo.order_number }}</td>
            <td class="px-5 py-3 text-gray-700">{{ wo.vehicle?.plate_number }}</td>
            <td class="px-5 py-3">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="workOrderStatusBadgeClass(wo.status)">
                {{ workOrderStatusLabel(wo.status) }}
              </span>
            </td>
            <td class="px-5 py-3">
              <span v-if="wo.credit_authorized" class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs">معتمد</span>
              <span v-else-if="wo.approval_status === 'pending'" class="px-2 py-0.5 bg-orange-100 text-orange-700 rounded text-xs">بانتظار</span>
              <span v-else class="px-2 py-0.5 bg-gray-100 text-gray-500 rounded text-xs">—</span>
            </td>
            <td class="px-5 py-3 text-gray-400 text-xs">{{ fmtDate(wo.created_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-4 text-sm">{{ error }}</div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import {
  CreditCardIcon, PlusCircleIcon, ChartBarIcon, WrenchScrewdriverIcon,
  TruckIcon, DocumentTextIcon, ClipboardDocumentListIcon, Cog6ToothIcon,
} from '@heroicons/vue/24/outline'
import { workOrderStatusLabel, workOrderStatusBadgeClass } from '@/utils/workOrderStatusLabels'

const BASE  = '/api/v1'
const token = () => localStorage.getItem('auth_token') ?? ''
const userStr = () => { try { return JSON.parse(localStorage.getItem('user') ?? '{}') } catch { return {} } }

const loading       = ref(true)
const error         = ref('')
const wallets       = ref<any[]>([])
const recentOrders  = ref<any[]>([])
const pendingOrders = ref<any[]>([])
const allVehicles   = ref<any[]>([])

const isManager = computed(() => {
  const u = userStr()
  return u.role === 'fleet_manager'
})

const kpi = computed(() => {
  const totalOrders     = recentOrders.value.length
  const totalVehicles   = allVehicles.value.length
  const needMaintenance = allVehicles.value.filter((v: any) => v.maintenance_status === 'needs_service').length
  const maintenancePct  = totalVehicles > 0 ? Math.round((needMaintenance / totalVehicles) * 100) : 0
  const walletBalance   = wallets.value.reduce((s: number, w: any) => {
    return w.wallet_type === 'fleet_main' ? s + parseFloat(w.balance ?? 0) : s
  }, 0)
  return { totalOrders, totalVehicles, needMaintenance, maintenancePct, walletBalance }
})

// Simulated week chart data (replace with real API later)
const weekDays = ref([
  { label: 'السبت', val: 0 }, { label: 'الأحد', val: 0 }, { label: 'الاثنين', val: 0 },
  { label: 'الثلاثاء', val: 0 }, { label: 'الأربعاء', val: 0 }, { label: 'الخميس', val: 0 }, { label: 'الجمعة', val: 0 },
])

const chips = [
  { label: 'الخدمات والمنتجات', to: '/fleet-portal',      icon: Cog6ToothIcon },
  { label: 'الطلبات',           to: '/fleet-portal/orders', icon: ClipboardDocumentListIcon },
  { label: 'المحفظة',           to: '/fleet-portal/top-up', icon: CreditCardIcon },
  { label: 'الفواتير',          to: '/fleet-portal',        icon: DocumentTextIcon },
  { label: 'إنشاء طلب',         to: '/fleet-portal/new-order', icon: PlusCircleIcon },
]

async function api(path: string, opts: RequestInit = {}) {
  const r = await fetch(`${BASE}${path}`, {
    headers: { Authorization: `Bearer ${token()}`, 'Content-Type': 'application/json', Accept: 'application/json' },
    ...opts,
  })
  const json = await r.json()
  if (!r.ok) throw new Error(json.message ?? `HTTP ${r.status}`)
  return json
}

async function loadDashboard() {
  loading.value = true
  try {
    const [dash, veh] = await Promise.allSettled([
      api('/fleet-portal/dashboard'),
      api('/vehicles?per_page=100'),
    ])
    if (dash.status === 'fulfilled') {
      wallets.value      = dash.value.data?.wallets ?? []
      recentOrders.value = dash.value.data?.recent_orders ?? []
      // Build week chart from orders
      const dayMap: Record<number, number> = { 0: 6, 1: 0, 2: 1, 3: 2, 4: 3, 5: 4, 6: 5 }
      recentOrders.value.forEach((o: any) => {
        const d = new Date(o.created_at).getDay()
        const idx = dayMap[d] ?? 0
        weekDays.value[idx].val++
      })
    }
    if (veh.status === 'fulfilled') {
      allVehicles.value = veh.value.data ?? []
    }
    if (isManager.value) {
      try {
        const pend = await api('/fleet-portal/work-orders/pending-approval')
        pendingOrders.value = pend.data ?? []
      } catch { /* ok */ }
    }
  } catch (e: any) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function approveCredit(id: number) {
  try { await api(`/fleet-portal/work-orders/${id}/approve-credit`, { method: 'POST', body: '{}' }); await loadDashboard() }
  catch (e: any) { error.value = e.message }
}
async function rejectCredit(id: number) {
  try { await api(`/fleet-portal/work-orders/${id}/reject-credit`, { method: 'POST', body: '{}' }); await loadDashboard() }
  catch (e: any) { error.value = e.message }
}

function fmtMoney(v: number) { return v.toLocaleString('ar-SA', { minimumFractionDigits: 2 }) }
function fmtDate(d: string)  { return d ? new Date(d).toLocaleDateString('ar-SA') : '—' }

onMounted(loadDashboard)
</script>
