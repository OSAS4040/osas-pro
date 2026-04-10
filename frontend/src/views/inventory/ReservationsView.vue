<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-gray-900">حجوزات المخزون</h2>
      <RouterLink to="/inventory" class="text-sm text-blue-600 hover:underline">← العودة للمخزون</RouterLink>
    </div>

    <div class="flex gap-3 flex-wrap">
      <select v-model="filters.status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" @change="load">
        <option value="">كل الحالات</option>
        <option value="pending">معلق</option>
        <option value="consumed">مستهلك</option>
        <option value="released">محرر</option>
        <option value="canceled">ملغي</option>
        <option value="expired">منتهي</option>
      </select>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
          <tr>
            <th class="px-4 py-3 text-right">المنتج</th>
            <th class="px-4 py-3 text-right">الفرع</th>
            <th class="px-4 py-3 text-right">الكمية</th>
            <th class="px-4 py-3 text-right">الحالة</th>
            <th class="px-4 py-3 text-right">المرجع</th>
            <th class="px-4 py-3 text-right">ينتهي في</th>
            <th class="px-4 py-3 text-right">إجراءات</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-if="loading">
            <td colspan="7" class="px-4 py-8 text-center text-gray-400">جارٍ التحميل...</td>
          </tr>
          <tr v-for="res in reservations" :key="res.id">
            <td class="px-4 py-3 font-medium text-right">{{ res.product?.name }}</td>
            <td class="px-4 py-3 text-gray-500 text-right">{{ res.branch?.name ?? '—' }}</td>
            <td class="px-4 py-3 text-right">{{ res.quantity }}</td>
            <td class="px-4 py-3 text-right">
              <span :class="statusClass(res.status)" class="text-xs px-2 py-0.5 rounded-full">
                {{ statusLabel(res.status) }}
              </span>
            </td>
            <td class="px-4 py-3 text-gray-500 text-xs text-right">{{ res.reference_type }} #{{ res.reference_id }}</td>
            <td class="px-4 py-3 text-gray-500 text-xs text-right">{{ res.expires_at ? formatDate(res.expires_at) : '—' }}</td>
            <td class="px-4 py-3 text-left">
              <template v-if="res.status === 'pending'">
                <button class="text-green-600 hover:text-green-800 text-xs ml-2" @click="action(res.id, 'consume')">استهلاك</button>
                <button class="text-yellow-600 hover:text-yellow-800 text-xs ml-2" @click="action(res.id, 'release')">تحرير</button>
                <button class="text-red-500 hover:text-red-700 text-xs" @click="action(res.id, 'cancel')">إلغاء</button>
              </template>
              <span v-else class="text-gray-400 text-xs">—</span>
            </td>
          </tr>
          <tr v-if="!loading && !reservations.length">
            <td colspan="7" class="px-4 py-8 text-center text-gray-400">لا توجد حجوزات.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="pagination" class="flex justify-between items-center text-sm text-gray-500">
      <span>صفحة {{ pagination.current_page }} من {{ pagination.last_page }}</span>
      <div class="flex gap-2">
        <button :disabled="pagination.current_page <= 1" class="px-3 py-1 border rounded disabled:opacity-40" @click="page--; load()">السابق</button>
        <button :disabled="pagination.current_page >= pagination.last_page" class="px-3 py-1 border rounded disabled:opacity-40" @click="page++; load()">التالي</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import apiClient from '@/lib/apiClient'
import { useToast } from '@/composables/useToast'

const toast = useToast()

interface Reservation {
  id: number
  product?: { name: string }
  branch?: { name: string }
  quantity: number
  status: string
  reference_type: string
  reference_id: number
  expires_at: string | null
}

const reservations = ref<Reservation[]>([])
const loading = ref(false)
const page = ref(1)
const pagination = ref<any>(null)
const filters = ref({ status: '' })

async function load() {
  loading.value = true
  try {
    const params: Record<string, any> = { page: page.value }
    if (filters.value.status) params.status = filters.value.status
    const { data } = await apiClient.get('/inventory/reservations', { params })
    const res = data.data
    reservations.value = res.data ?? res
    if (res.current_page) pagination.value = res
  } finally {
    loading.value = false
  }
}

async function action(id: number, type: 'consume' | 'release' | 'cancel') {
  try {
    await apiClient.patch(`/inventory/reservations/${id}/${type}`)
    await load()
  } catch (e: any) {
    toast.error('تعذّر تنفيذ الإجراء', e.response?.data?.message ?? 'فشل تنفيذ الإجراء.')
  }
}

function statusClass(status: string): string {
  const map: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-700',
    consumed: 'bg-green-100 text-green-700',
    released: 'bg-blue-100 text-blue-700',
    canceled: 'bg-gray-100 text-gray-600',
    expired: 'bg-red-100 text-red-600',
  }
  return map[status] ?? 'bg-gray-100 text-gray-600'
}

function statusLabel(status: string): string {
  const map: Record<string, string> = {
    pending: 'معلق', consumed: 'مستهلك', released: 'محرر', canceled: 'ملغي', expired: 'منتهي',
  }
  return map[status] ?? status
}

function formatDate(dt: string): string {
  return new Date(dt).toLocaleDateString('ar-SA')
}

onMounted(load)
</script>
