<template>
  <div class="space-y-6" dir="rtl">
    <div class="flex items-center justify-between">
      <h2 class="text-2xl font-bold text-gray-900">العمولات</h2>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="bg-white rounded-xl p-4 border border-gray-200 text-center">
        <p class="text-2xl font-bold text-primary-600">{{ formatNum(totalPending) }}</p>
        <p class="text-xs text-gray-500 mt-1">مستحقة (ر.س)</p>
      </div>
      <div class="bg-white rounded-xl p-4 border border-gray-200 text-center">
        <p class="text-2xl font-bold text-green-600">{{ formatNum(totalPaid) }}</p>
        <p class="text-xs text-gray-500 mt-1">مدفوعة (ر.س)</p>
      </div>
      <div class="bg-white rounded-xl p-4 border border-gray-200 text-center">
        <p class="text-2xl font-bold text-blue-600">{{ commissions.filter(c => c.status === 'pending').length }}</p>
        <p class="text-xs text-gray-500 mt-1">معلقة</p>
      </div>
      <div class="bg-white rounded-xl p-4 border border-gray-200 text-center">
        <p class="text-2xl font-bold text-gray-700">{{ commissions.length }}</p>
        <p class="text-xs text-gray-500 mt-1">إجمالي</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl border border-gray-200 p-4 flex gap-3 flex-wrap">
      <select v-model="filterEmployee" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none">
        <option value="">كل الموظفين</option>
        <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.full_name }}</option>
      </select>
      <select v-model="filterStatus" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none">
        <option value="">كل الحالات</option>
        <option value="pending">معلقة</option>
        <option value="paid">مدفوعة</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
    </div>

    <!-- Table -->
    <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="px-4 py-3 text-right font-semibold text-gray-700">الموظف</th>
            <th class="px-4 py-3 text-right font-semibold text-gray-700">المصدر</th>
            <th class="px-4 py-3 text-right font-semibold text-gray-700">المبلغ</th>
            <th class="px-4 py-3 text-right font-semibold text-gray-700">الحالة</th>
            <th class="px-4 py-3 text-right font-semibold text-gray-700">التاريخ</th>
            <th class="px-4 py-3 text-right font-semibold text-gray-700">إجراء</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="c in filtered" :key="c.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-medium text-gray-900">{{ c.employee_name ?? c.employee_id }}</td>
            <td class="px-4 py-3 text-gray-600 text-xs">{{ c.source_type }} #{{ c.source_id }}</td>
            <td class="px-4 py-3 font-semibold text-primary-700">{{ formatNum(c.amount) }} ر.س</td>
            <td class="px-4 py-3">
              <span :class="c.status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'"
                class="px-2 py-0.5 rounded-full text-xs font-medium">
                {{ c.status === 'paid' ? 'مدفوعة' : 'معلقة' }}
              </span>
            </td>
            <td class="px-4 py-3 text-gray-500 text-xs">{{ formatDate(c.created_at) }}</td>
            <td class="px-4 py-3">
              <button v-if="c.status === 'pending'" @click="pay(c)"
                class="text-xs bg-green-600 text-white px-3 py-1 rounded-lg hover:bg-green-700 disabled:opacity-50"
                :disabled="payingId === c.id">
                {{ payingId === c.id ? '...' : 'صرف' }}
              </button>
            </td>
          </tr>
          <tr v-if="!filtered.length">
            <td colspan="6" class="text-center py-10 text-gray-400">لا توجد عمولات</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import apiClient from '@/lib/apiClient'


const commissions = ref<any[]>([])
const employees = ref<any[]>([])
const loading = ref(true)
const filterEmployee = ref('')
const filterStatus = ref('')
const payingId = ref<number | null>(null)

const filtered = computed(() =>
  commissions.value.filter(c =>
    (!filterEmployee.value || c.employee_id == filterEmployee.value) &&
    (!filterStatus.value || c.status === filterStatus.value)
  )
)

const totalPending = computed(() => commissions.value.filter(c => c.status === 'pending').reduce((a, c) => a + Number(c.amount), 0))
const totalPaid = computed(() => commissions.value.filter(c => c.status === 'paid').reduce((a, c) => a + Number(c.amount), 0))

function formatNum(n: number) { return Number(n || 0).toLocaleString('ar-SA') }
function formatDate(d: string) { return new Date(d).toLocaleDateString('ar-SA') }

async function load() {
  loading.value = true
  try {
    const [c, e] = await Promise.all([apiClient.get('/workshop/commissions'), apiClient.get('/workshop/employees')])
    commissions.value = c.data?.data ?? []
    employees.value = e.data?.data ?? []
  } finally { loading.value = false }
}

async function pay(c: any) {
  payingId.value = c.id
  try {
    await apiClient.post(`/workshop/commissions/${c.id}/pay`, {})
    c.status = 'paid'
  } finally { payingId.value = null }
}

onMounted(load)
</script>
