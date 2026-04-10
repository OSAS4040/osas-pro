<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-bold text-gray-900">مركبات الأسطول</h2>
        <p class="text-xs text-gray-400">إدارة وتتبع مركبات شركتك</p>
      </div>
      <button
        type="button"
        class="flex items-center gap-1.5 px-4 py-2 bg-teal-600 text-white text-sm font-medium rounded-lg hover:bg-teal-700 transition-colors"
        @click="openAdd"
      >
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
                      :class="v.maintenance_status === 'good' ? 'bg-green-100 text-green-700' : v.maintenance_status === 'needs_service' ? 'bg-orange-100 text-orange-700' : 'bg-blue-100 text-blue-700'"
                >
                  {{ v.maintenance_status === 'good' ? 'سليمة' : v.maintenance_status === 'needs_service' ? 'تحتاج صيانة' : 'في الصيانة' }}
                </span>
              </td>
              <td class="px-4 py-3 text-gray-500">{{ v.mileage ? v.mileage.toLocaleString() + ' كم' : '—' }}</td>
              <td class="px-4 py-3 font-semibold text-green-700">
                {{ v.wallet_balance != null ? Number(v.wallet_balance).toLocaleString('ar-SA', {minimumFractionDigits:2}) + ' ر.س' : '—' }}
              </td>
              <td class="px-4 py-3">
                <button class="px-3 py-1.5 bg-teal-600 text-white text-xs rounded-lg hover:bg-teal-700 transition-colors"
                        @click="requestService(v)"
                >
                  طلب خدمة
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- إضافة مركبة (كان الزر يضبط showAddModal دون واجهة — هذا يفعّل التدفق فعلياً) -->
    <Teleport to="body">
      <Transition name="fade">
        <div
          v-if="showAddModal"
          class="fixed inset-0 bg-black/40 z-[100] flex items-end sm:items-center justify-center p-4"
          dir="rtl"
          @click.self="showAddModal = false"
        >
          <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl max-h-[90vh] flex flex-col">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 shrink-0">
              <h3 class="font-bold text-gray-900">إضافة مركبة للأسطول</h3>
              <button type="button" class="p-1.5 rounded-lg hover:bg-gray-100" @click="showAddModal = false">
                <XMarkIcon class="w-5 h-5 text-gray-400" />
              </button>
            </div>
            <div class="p-5 space-y-4 overflow-y-auto">
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">رقم اللوحة <span class="text-red-500">*</span></label>
                <input v-model="form.plate_number" class="field" placeholder="مثل ABC 1234" />
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">الماركة <span class="text-red-500">*</span></label>
                  <input v-model="form.make" class="field" placeholder="Toyota" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">الموديل <span class="text-red-500">*</span></label>
                  <input v-model="form.model" class="field" placeholder="Camry" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">سنة الصنع</label>
                  <input v-model="form.year" type="number" min="1990" :max="new Date().getFullYear() + 1" class="field" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">اللون</label>
                  <input v-model="form.color" class="field" placeholder="أبيض" />
                </div>
              </div>
              <div v-if="formErr" class="text-sm text-red-600 bg-red-50 rounded-lg p-3">{{ formErr }}</div>
            </div>
            <div class="flex gap-2 px-5 py-4 border-t border-gray-100 shrink-0">
              <button type="button" class="flex-1 py-2.5 border border-gray-300 rounded-xl text-sm" @click="showAddModal = false">
                إلغاء
              </button>
              <button
                type="button"
                :disabled="saving"
                class="flex-1 py-2.5 bg-teal-600 text-white rounded-xl text-sm font-semibold disabled:opacity-50"
                @click="submitAdd"
              >
                {{ saving ? 'جارٍ الحفظ...' : 'إضافة' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import { useToast } from '@/composables/useToast'

const router = useRouter()
const toast = useToast()
const loading = ref(true)
const vehicles = ref<any[]>([])
const search = ref('')
const showAddModal = ref(false)
const saving = ref(false)
const formErr = ref('')
const form = reactive({
  plate_number: '',
  make: '',
  model: '',
  year: '' as string | number | '',
  color: '',
})

function openAdd() {
  Object.assign(form, { plate_number: '', make: '', model: '', year: '', color: '' })
  formErr.value = ''
  showAddModal.value = true
}

async function submitAdd() {
  if (!form.plate_number?.trim() || !form.make?.trim() || !form.model?.trim()) {
    formErr.value = 'اللوحة والماركة والموديل مطلوبة'
    return
  }
  const compact = form.plate_number.trim().toUpperCase().replace(/\s+/g, '')
  const m = compact.match(/^([A-Z]{3})(\d{3,4})$/)
  if (!m) {
    formErr.value = 'اللوحة: 3 حروف لاتينية ثم 3 أو 4 أرقام (مثل ABC123 أو ABC1234).'
    return
  }
  const plate_number = `${m[1]} ${m[2]}`
  saving.value = true
  formErr.value = ''
  try {
    await apiClient.post('/vehicles', {
      plate_number,
      make: form.make.trim(),
      model: form.model.trim(),
      year: form.year === '' ? null : Number(form.year),
      color: form.color?.trim() || null,
    })
    toast.success('تم إضافة المركبة')
    showAddModal.value = false
    await load()
  } catch (e: any) {
    formErr.value = e?.response?.data?.message ?? 'تعذّر الحفظ'
  } finally {
    saving.value = false
  }
}

const filtered = computed(() =>
  vehicles.value.filter(v => !search.value || v.plate_number?.toLowerCase().includes(search.value.toLowerCase())
    || v.make?.toLowerCase().includes(search.value.toLowerCase())))

async function load() {
  loading.value = true
  try {
    const { data } = await apiClient.get('/vehicles', { params: { per_page: 100 } })
    const raw = data.data
    vehicles.value = Array.isArray(raw) ? raw : raw?.data ?? []
  } catch { /* silent */ } finally { loading.value = false }
}

function requestService(v: any) {
  router.push({ path: '/fleet-portal/new-order', query: { vehicle_id: v.id, plate: v.plate_number } })
}

onMounted(load)
</script>

<style scoped>
.field {
  @apply w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-teal-500 focus:border-transparent;
}
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
