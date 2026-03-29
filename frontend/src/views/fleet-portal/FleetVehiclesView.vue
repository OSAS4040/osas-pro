<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-bold text-gray-900">مركبات الأسطول</h2>
        <p class="text-xs text-gray-400">إدارة وتتبع مركبات شركتك</p>
      </div>
      <button @click="showAddModal = true"
        class="flex items-center gap-1.5 px-4 py-2 bg-teal-600 text-white text-sm font-medium rounded-lg hover:bg-teal-700 transition-colors">
        <PlusIcon class="w-4 h-4" /> إضافة مركبة
      </button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-3 gap-3">
      <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
        <p class="text-2xl font-bold text-teal-700">{{ vehicles.length }}</p>
        <p class="text-xs text-gray-500 mt-1">إجمالي المركبات</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
        <p class="text-2xl font-bold text-green-600">{{ vehicles.filter(v => v.maintenance_status === 'good').length }}</p>
        <p class="text-xs text-gray-500 mt-1">سليمة</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
        <p class="text-2xl font-bold text-orange-600">{{ vehicles.filter(v => v.maintenance_status === 'needs_service').length }}</p>
        <p class="text-xs text-gray-500 mt-1">تحتاج صيانة</p>
      </div>
    </div>

    <!-- Search -->
    <div class="bg-white rounded-xl border border-gray-100 p-4">
      <input v-model="search" placeholder="بحث برقم اللوحة أو الموديل..." class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-teal-400 focus:outline-none" />
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
      <div v-if="loading" class="py-10 text-center text-gray-400 text-sm">جارٍ التحميل...</div>
      <div v-else-if="!filtered.length" class="py-10 text-center text-gray-400 text-sm">لا توجد مركبات</div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-right text-xs text-gray-500">
            <tr>
              <th class="px-4 py-3 font-medium">رقم اللوحة</th>
              <th class="px-4 py-3 font-medium">الموديل</th>
              <th class="px-4 py-3 font-medium">السنة</th>
              <th class="px-4 py-3 font-medium">الحالة</th>
              <th class="px-4 py-3 font-medium">العداد</th>
              <th class="px-4 py-3 font-medium">الرصيد</th>
              <th class="px-4 py-3 font-medium">الإجراءات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="v in filtered" :key="v.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-4 py-3 font-bold text-teal-700">{{ v.plate_number }}</td>
              <td class="px-4 py-3 text-gray-700">{{ v.make }} {{ v.model }}</td>
              <td class="px-4 py-3 text-gray-500">{{ v.year }}</td>
              <td class="px-4 py-3">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                  :class="v.maintenance_status === 'good' ? 'bg-green-100 text-green-700' : v.maintenance_status === 'needs_service' ? 'bg-orange-100 text-orange-700' : 'bg-blue-100 text-blue-700'">
                  {{ v.maintenance_status === 'good' ? 'سليمة' : v.maintenance_status === 'needs_service' ? 'تحتاج صيانة' : 'في الصيانة' }}
                </span>
              </td>
              <td class="px-4 py-3 text-gray-500">{{ v.mileage ? v.mileage.toLocaleString() + ' كم' : '—' }}</td>
              <td class="px-4 py-3 font-semibold text-green-700">
                {{ v.wallet_balance != null ? Number(v.wallet_balance).toLocaleString('ar-SA', {minimumFractionDigits:2}) + ' ر.س' : '—' }}
              </td>
              <td class="px-4 py-3">
                <button @click="requestService(v)"
                  class="px-3 py-1.5 bg-teal-600 text-white text-xs rounded-lg hover:bg-teal-700 transition-colors">
                  طلب خدمة
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { PlusIcon } from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'

const router       = useRouter()
const loading      = ref(true)
const vehicles     = ref<any[]>([])
const search       = ref('')
const showAddModal = ref(false)

const filtered = computed(() =>
  vehicles.value.filter(v => !search.value || v.plate_number?.toLowerCase().includes(search.value.toLowerCase())
    || v.make?.toLowerCase().includes(search.value.toLowerCase())))

async function load() {
  loading.value = true
  try {
    const { data } = await apiClient.get('/vehicles', { params: { per_page: 100 } })
    vehicles.value = data.data ?? []
  } catch { /* silent */ } finally { loading.value = false }
}

function requestService(v: any) {
  router.push({ path: '/fleet-portal/new-order', query: { vehicle_id: v.id, plate: v.plate_number } })
}

onMounted(load)
</script>
