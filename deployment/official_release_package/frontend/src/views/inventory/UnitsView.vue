<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-gray-900">وحدات القياس</h2>
      <button class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700" @click="openCreate">
        + إضافة وحدة
      </button>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
          <tr>
            <th class="px-4 py-3 text-right">الاسم</th>
            <th class="px-4 py-3 text-right">الرمز</th>
            <th class="px-4 py-3 text-right">النوع</th>
            <th class="px-4 py-3 text-right">النظام</th>
            <th class="px-4 py-3 text-right">الحالة</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-if="loading">
            <td colspan="6" class="px-4 py-8 text-center text-gray-400">جارٍ التحميل...</td>
          </tr>
          <tr v-for="unit in units" :key="unit.id">
            <td class="px-4 py-3 font-medium text-right">{{ unit.name }}</td>
            <td class="px-4 py-3 text-gray-500 text-right">{{ unit.symbol }}</td>
            <td class="px-4 py-3 text-gray-500 text-right">{{ unit.type }}</td>
            <td class="px-4 py-3 text-right">
              <span v-if="unit.is_system" class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">نظام</span>
              <span v-else class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">مخصص</span>
            </td>
            <td class="px-4 py-3 text-right">
              <span :class="unit.is_active ? 'text-green-600' : 'text-gray-400'">
                {{ unit.is_active ? 'نشط' : 'غير نشط' }}
              </span>
            </td>
            <td class="px-4 py-3 text-left">
              <button v-if="!unit.is_system" class="text-red-500 hover:text-red-700 text-xs" @click="deleteUnit(unit)">حذف</button>
            </td>
          </tr>
          <tr v-if="!loading && !units.length">
            <td colspan="6" class="px-4 py-8 text-center text-gray-400">لا توجد وحدات.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="showModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md">
        <h3 class="font-semibold text-gray-900 mb-4">إنشاء وحدة جديدة</h3>
        <form class="space-y-4" @submit.prevent="submitCreate">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الاسم</label>
            <input v-model="form.name" type="text" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الرمز</label>
            <input v-model="form.symbol" type="text" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">النوع</label>
            <select v-model="form.type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
              <option value="quantity">كمية</option>
              <option value="weight">وزن</option>
              <option value="volume">حجم</option>
              <option value="length">طول</option>
              <option value="time">وقت</option>
            </select>
          </div>
          <p v-if="error" class="text-red-600 text-sm">{{ error }}</p>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" class="px-4 py-2 text-sm border border-gray-300 rounded-lg" @click="showModal = false">إلغاء</button>
            <button type="submit" :disabled="saving" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
              {{ saving ? 'جارٍ الحفظ...' : 'إنشاء' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import apiClient from '@/lib/apiClient'
import { appConfirm } from '@/services/appConfirmDialog'
import { useToast } from '@/composables/useToast'

const toast = useToast()

interface Unit {
  id: number
  name: string
  symbol: string
  type: string
  is_system: boolean
  is_active: boolean
}

const units = ref<Unit[]>([])
const loading = ref(false)
const showModal = ref(false)
const saving = ref(false)
const error = ref('')
const form = ref({ name: '', symbol: '', type: 'quantity' })

async function load() {
  loading.value = true
  try {
    const { data } = await apiClient.get('/units')
    units.value = data.data
  } finally {
    loading.value = false
  }
}

function openCreate() {
  form.value = { name: '', symbol: '', type: 'quantity' }
  error.value = ''
  showModal.value = true
}

async function submitCreate() {
  saving.value = true
  error.value = ''
  try {
    await apiClient.post('/units', form.value)
    showModal.value = false
    await load()
  } catch (e: any) {
    error.value = e.response?.data?.message ?? 'فشل إنشاء الوحدة.'
  } finally {
    saving.value = false
  }
}

async function deleteUnit(unit: Unit) {
  const ok = await appConfirm({
    title: 'حذف الوحدة',
    message: `هل تريد حذف الوحدة «${unit.name}»؟`,
    variant: 'danger',
    confirmLabel: 'حذف',
  })
  if (!ok) return
  try {
    await apiClient.delete(`/units/${unit.id}`)
    await load()
  } catch (e: any) {
    toast.error('تعذّر الحذف', e.response?.data?.message ?? 'فشل حذف الوحدة.')
  }
}

onMounted(load)
</script>
