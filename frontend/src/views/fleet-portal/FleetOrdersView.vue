<template>
  <div class="space-y-5">
    <div>
      <h2 class="text-lg font-bold text-gray-900">طلباتي</h2>
      <p class="text-xs text-gray-400">جميع طلبات الخدمة لأسطولك</p>
    </div>

    <!-- Filters -->
    <div class="flex gap-2 flex-wrap">
      <button v-for="tab in tabs" :key="tab.value" @click="activeTab = tab.value"
        class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
        :class="activeTab === tab.value ? 'bg-teal-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'">
        {{ tab.label }}
        <span v-if="tab.count > 0" class="mr-1 bg-white/20 text-white px-1.5 py-0.5 rounded-full text-[10px]">{{ tab.count }}</span>
      </button>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
      <div v-if="loading" class="py-10 text-center text-gray-400 text-sm">جارٍ التحميل...</div>
      <div v-else-if="!filtered.length" class="py-10 text-center text-gray-400 text-sm">لا توجد طلبات</div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-right text-xs text-gray-500">
            <tr>
              <th class="px-4 py-3 font-medium">رقم الطلب</th>
              <th class="px-4 py-3 font-medium">المركبة</th>
              <th class="px-4 py-3 font-medium">الحالة</th>
              <th class="px-4 py-3 font-medium">نوع الدفع</th>
              <th class="px-4 py-3 font-medium">اعتماد الائتمان</th>
              <th class="px-4 py-3 font-medium">التاريخ</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="wo in filtered" :key="wo.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-4 py-3 font-semibold text-teal-700">{{ wo.order_number }}</td>
              <td class="px-4 py-3 text-gray-700">{{ wo.vehicle?.plate_number ?? '—' }}</td>
              <td class="px-4 py-3">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="statusClass(wo.status)">
                  {{ statusLabel(wo.status) }}
                </span>
              </td>
              <td class="px-4 py-3 text-gray-600">{{ wo.payment_method === 'credit' ? 'ائتمان' : wo.payment_method === 'wallet' ? 'محفظة' : '—' }}</td>
              <td class="px-4 py-3">
                <span v-if="wo.credit_authorized" class="text-green-600 text-xs font-medium">معتمد ✓</span>
                <span v-else-if="wo.approval_status === 'pending'" class="text-orange-500 text-xs">بانتظار الاعتماد</span>
                <span v-else class="text-gray-400 text-xs">—</span>
              </td>
              <td class="px-4 py-3 text-gray-400 text-xs">{{ fmtDate(wo.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Approval Panel (for fleet_manager) -->
    <div v-if="isManager && pendingApproval.length" class="bg-orange-50 border border-orange-200 rounded-xl p-5">
      <h3 class="font-semibold text-orange-800 text-sm mb-3">طلبات تنتظر اعتمادك ({{ pendingApproval.length }})</h3>
      <div class="space-y-2">
        <div v-for="wo in pendingApproval" :key="wo.id"
          class="bg-white rounded-lg p-4 flex items-center justify-between shadow-xs">
          <div>
            <p class="text-sm font-semibold text-gray-800">{{ wo.order_number }}</p>
            <p class="text-xs text-gray-500">{{ wo.vehicle?.plate_number }} • {{ wo.description }}</p>
          </div>
          <div class="flex gap-2">
            <button @click="approve(wo.id)" class="px-3 py-1.5 bg-green-600 text-white text-xs rounded-lg hover:bg-green-700">اعتماد</button>
            <button @click="reject(wo.id)" class="px-3 py-1.5 bg-red-100 text-red-700 text-xs rounded-lg hover:bg-red-200">رفض</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'

const auth    = useAuthStore()
const loading = ref(true)
const orders  = ref<any[]>([])
const activeTab = ref('all')

const isManager = computed(() => auth.user?.role === 'fleet_manager')

const pendingApproval = computed(() =>
  orders.value.filter(o => o.approval_status === 'pending'))

const tabs = computed(() => [
  { label: 'جميع الطلبات', value: 'all', count: 0 },
  { label: 'بانتظار الاعتماد', value: 'pending_approval', count: pendingApproval.value.length },
  { label: 'قيد التنفيذ', value: 'in_progress', count: orders.value.filter(o => o.status === 'in_progress').length },
  { label: 'مكتملة', value: 'completed', count: orders.value.filter(o => o.status === 'completed').length },
])

const filtered = computed(() => {
  if (activeTab.value === 'all') return orders.value
  if (activeTab.value === 'pending_approval') return pendingApproval.value
  return orders.value.filter(o => o.status === activeTab.value)
})

async function load() {
  loading.value = true
  try {
    const endpoint = isManager.value ? '/fleet-portal/work-orders' : '/fleet-portal/work-orders'
    const { data } = await apiClient.get(endpoint)
    orders.value = data.data ?? []
  } catch { /* silent */ } finally { loading.value = false }
}

async function approve(id: number) {
  try {
    await apiClient.post(`/fleet-portal/work-orders/${id}/approve-credit`)
    await load()
  } catch (e: any) { alert(e.response?.data?.message ?? 'فشل الاعتماد') }
}

async function reject(id: number) {
  try {
    await apiClient.post(`/fleet-portal/work-orders/${id}/reject-credit`)
    await load()
  } catch (e: any) { alert(e.response?.data?.message ?? 'فشل الرفض') }
}

function statusLabel(s: string) {
  const m: Record<string, string> = { pending: 'معلق', assigned: 'مُسنَد', in_progress: 'جاري', completed: 'مكتمل', invoiced: 'مُفوتر', cancelled: 'ملغي' }
  return m[s] ?? s
}
function statusClass(s: string) {
  const m: Record<string, string> = {
    pending: 'bg-gray-100 text-gray-600', assigned: 'bg-blue-100 text-blue-700',
    in_progress: 'bg-yellow-100 text-yellow-700', completed: 'bg-green-100 text-green-700',
    invoiced: 'bg-purple-100 text-purple-700', cancelled: 'bg-red-100 text-red-700',
  }
  return m[s] ?? 'bg-gray-100 text-gray-600'
}
function fmtDate(d: string) { return d ? new Date(d).toLocaleDateString('ar-SA') : '—' }

onMounted(load)
</script>
