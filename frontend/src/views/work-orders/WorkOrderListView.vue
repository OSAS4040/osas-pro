<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-gray-900">أوامر العمل</h2>
      <RouterLink to="/work-orders/new" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
        + أمر عمل جديد
      </RouterLink>
    </div>

    <div class="flex gap-2 flex-wrap">
      <button v-for="s in statuses" :key="s.value"
        @click="filterStatus = filterStatus === s.value ? '' : s.value; load()"
        :class="filterStatus === s.value ? 'bg-primary-600 text-white' : 'bg-white text-gray-600 border border-gray-300'"
        class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
        {{ s.label }}
      </button>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div v-if="store.loading" class="p-8 text-center text-gray-400 text-sm">جارٍ التحميل...</div>
      <table v-else class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
          <tr>
            <th class="px-4 py-3 text-right">رقم الأمر</th>
            <th class="px-4 py-3 text-right">العميل</th>
            <th class="px-4 py-3 text-right">المركبة</th>
            <th class="px-4 py-3 text-right">الفني</th>
            <th class="px-4 py-3 text-right">الحالة</th>
            <th class="px-4 py-3 text-right">الأولوية</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="wo in store.orders" :key="wo.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-medium text-gray-900 text-right">{{ wo.order_number }}</td>
            <td class="px-4 py-3 text-gray-600 text-right">{{ wo.customer?.name }}</td>
            <td class="px-4 py-3 font-mono text-xs text-gray-500 text-right">{{ wo.vehicle?.plate_number }}</td>
            <td class="px-4 py-3 text-gray-600 text-right">{{ wo.assigned_technician?.name ?? '—' }}</td>
            <td class="px-4 py-3 text-right">
              <span :class="statusClass(wo.status)" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ statusLabel(wo.status) }}</span>
            </td>
            <td class="px-4 py-3 text-right">
              <span :class="priorityClass(wo.priority)" class="px-2 py-0.5 rounded-full text-xs">{{ priorityLabel(wo.priority) }}</span>
            </td>
            <td class="px-4 py-3 text-left">
              <RouterLink :to="`/work-orders/${wo.id}`" class="text-primary-600 hover:underline text-xs">عرض</RouterLink>
            </td>
          </tr>
          <tr v-if="!store.orders.length">
            <td colspan="7" class="px-4 py-8 text-center text-gray-400">لا توجد أوامر عمل.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useWorkOrderStore } from '@/stores/workOrder'

const store        = useWorkOrderStore()
const filterStatus = ref('')

const statuses = [
  { value: 'pending',     label: 'معلق' },
  { value: 'in_progress', label: 'قيد التنفيذ' },
  { value: 'on_hold',     label: 'موقوف' },
  { value: 'completed',   label: 'مكتمل' },
  { value: 'delivered',   label: 'مسلّم' },
]

async function load(): Promise<void> {
  await store.fetchOrders({ status: filterStatus.value || undefined })
}

onMounted(load)

function statusClass(s: string): string {
  const m: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-500', pending: 'bg-yellow-100 text-yellow-700',
    in_progress: 'bg-blue-100 text-blue-700', on_hold: 'bg-orange-100 text-orange-700',
    completed: 'bg-green-100 text-green-700', delivered: 'bg-teal-100 text-teal-700',
    cancelled: 'bg-red-100 text-red-600',
  }
  return m[s] ?? 'bg-gray-100 text-gray-600'
}

function statusLabel(s: string): string {
  const m: Record<string, string> = {
    draft: 'مسودة', pending: 'معلق', in_progress: 'قيد التنفيذ',
    on_hold: 'موقوف', completed: 'مكتمل', delivered: 'مسلّم', cancelled: 'ملغي',
  }
  return m[s] ?? s
}

function priorityClass(p: string): string {
  const m: Record<string, string> = {
    low: 'bg-gray-100 text-gray-500', normal: 'bg-blue-50 text-blue-600',
    high: 'bg-orange-100 text-orange-600', urgent: 'bg-red-100 text-red-700',
  }
  return m[p] ?? ''
}

function priorityLabel(p: string): string {
  const m: Record<string, string> = { low: 'منخفضة', normal: 'عادية', high: 'عالية', urgent: 'عاجلة' }
  return m[p] ?? p
}
</script>
