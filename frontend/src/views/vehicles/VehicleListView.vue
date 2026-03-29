<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
      <h2 class="text-lg font-semibold text-gray-900">المركبات</h2>
      <div class="flex items-center gap-2 flex-wrap">
        <ExcelImport
          endpoint="/api/v1/vehicles/import"
          template-url="/templates/vehicles_template.csv"
          label="استيراد Excel"
          title="استيراد مركبات من Excel"
          @imported="load"
        />
        <button @click="openCreate" class="flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 transition-colors">
          <PlusIcon class="w-4 h-4" />
          إضافة مركبة
        </button>
      </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200">
      <div class="p-4 border-b border-gray-100 flex gap-3">
        <input v-model="search" @keyup.enter="load" placeholder="رقم اللوحة، الشاسيه، الماركة، الموديل..." class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm" />
        <button @click="load" class="px-4 py-2 bg-gray-100 rounded-lg text-sm hover:bg-gray-200">بحث</button>
      </div>

      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
          <tr>
            <th class="px-4 py-3 text-right">رقم اللوحة</th>
            <th class="px-4 py-3 text-right">الماركة / الموديل</th>
            <th class="px-4 py-3 text-right">السنة</th>
            <th class="px-4 py-3 text-right">العميل</th>
            <th class="px-4 py-3 text-right">الوقود</th>
            <th class="px-4 py-3 text-right">الحالة</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="v in store.vehicles" :key="v.id" class="hover:bg-gray-50 cursor-pointer" @click="$router.push(`/vehicles/${v.id}`)">
            <td class="px-4 py-3 font-mono font-semibold text-gray-900 text-right">{{ v.plate_number }}</td>
            <td class="px-4 py-3 text-right">{{ v.make }} {{ v.model }}</td>
            <td class="px-4 py-3 text-gray-500 text-right">{{ v.year ?? '—' }}</td>
            <td class="px-4 py-3 text-gray-600 text-right">{{ v.customer?.name }}</td>
            <td class="px-4 py-3 text-gray-500 text-right">{{ v.fuel_type ?? '—' }}</td>
            <td class="px-4 py-3 text-right">
              <span :class="v.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'" class="px-2 py-0.5 rounded-full text-xs">
                {{ v.is_active ? 'نشطة' : 'غير نشطة' }}
              </span>
            </td>
            <td class="px-4 py-3 text-left" @click.stop>
              <RouterLink :to="`/vehicles/${v.id}`" class="text-primary-600 hover:underline text-xs">عرض</RouterLink>
            </td>
          </tr>
          <tr v-if="!store.vehicles.length">
            <td colspan="7" class="px-4 py-12 text-center">
              <TruckIcon class="w-10 h-10 text-gray-200 mx-auto mb-2" />
              <p class="text-gray-400 text-sm">لا توجد مركبات</p>
              <button @click="openCreate" class="mt-2 text-primary-600 text-xs hover:underline">أضف أول مركبة</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create Vehicle Modal -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="showCreate" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" dir="rtl" @click.self="showCreate = false">
          <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
              <h3 class="text-base font-bold text-gray-900">إضافة مركبة جديدة</h3>
              <button @click="showCreate = false" class="p-1.5 rounded-lg hover:bg-gray-100 transition-colors">
                <XMarkIcon class="w-5 h-5 text-gray-400" />
              </button>
            </div>

            <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
              <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                  <label class="block text-xs font-medium text-gray-600 mb-1">رقم اللوحة <span class="text-red-500">*</span></label>
                  <PlateInput v-model="form.plate_number" placeholder="ABC 1234" />
                </div>
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
                  <input v-model="form.year" type="number" min="1990" :max="new Date().getFullYear()+1" class="field" placeholder="2023" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">نوع الوقود</label>
                  <select v-model="form.fuel_type" class="field bg-white">
                    <option value="">اختر</option>
                    <option value="gasoline">بنزين</option>
                    <option value="diesel">ديزل</option>
                    <option value="hybrid">هجين</option>
                    <option value="electric">كهرباء</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">اللون</label>
                  <input v-model="form.color" class="field" placeholder="أبيض" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">رقم الشاسيه (VIN)</label>
                  <input v-model="form.vin" class="field font-mono" placeholder="1HGBH41JXMN109186" />
                </div>
                <div class="col-span-2">
                  <label class="block text-xs font-medium text-gray-600 mb-1">العميل</label>
                  <select v-model="form.customer_id" class="field bg-white">
                    <option value="">بدون عميل</option>
                    <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                  </select>
                </div>
                <div class="col-span-2">
                  <label class="block text-xs font-medium text-gray-600 mb-1">ملاحظات</label>
                  <textarea v-model="form.notes" rows="2" class="field resize-none" placeholder="أي ملاحظات إضافية..."></textarea>
                </div>
              </div>

              <div v-if="formError" class="text-sm text-red-600 bg-red-50 rounded-lg p-3">{{ formError }}</div>
            </div>

            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
              <button @click="showCreate = false" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-100 transition-colors">إلغاء</button>
              <button @click="submitCreate" :disabled="saving" class="px-5 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50 transition-colors">
                {{ saving ? 'جارٍ الحفظ...' : 'إضافة المركبة' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { PlusIcon, XMarkIcon, TruckIcon } from '@heroicons/vue/24/outline'
import ExcelImport from '@/components/ExcelImport.vue'
import { useVehicleStore } from '@/stores/vehicle'
import apiClient from '@/lib/apiClient'
import { useToast } from '@/composables/useToast'
import PlateInput from '@/components/PlateInput.vue'

const store    = useVehicleStore()
const $router  = useRouter()
const toast    = useToast()
const search   = ref('')
const showCreate = ref(false)
const saving   = ref(false)
const formError = ref('')
const customers = ref<any[]>([])

const form = reactive({
  plate_number: '', make: '', model: '', year: '',
  fuel_type: '', color: '', vin: '', customer_id: '', notes: '',
})

function openCreate() {
  Object.assign(form, { plate_number: '', make: '', model: '', year: '', fuel_type: '', color: '', vin: '', customer_id: '', notes: '' })
  formError.value = ''
  showCreate.value = true
}

async function load(): Promise<void> {
  await store.fetchVehicles({ search: search.value })
}

async function loadCustomers() {
  try {
    const r = await apiClient.get('/customers', { params: { per_page: 200 } })
    customers.value = r.data?.data ?? []
  } catch { /* silent */ }
}

async function submitCreate() {
  if (!form.plate_number || !form.make || !form.model) {
    formError.value = 'رقم اللوحة والماركة والموديل مطلوبة'
    return
  }
  saving.value = true
  formError.value = ''
  try {
    await apiClient.post('/vehicles', {
      ...form,
      year: form.year ? Number(form.year) : null,
      customer_id: form.customer_id || null,
    })
    toast.success('تم إضافة المركبة بنجاح')
    showCreate.value = false
    await load()
  } catch (e: any) {
    formError.value = e?.response?.data?.message ?? 'حدث خطأ، تحقق من البيانات'
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  await load()
  await loadCustomers()
})
</script>

<style scoped>
.field {
  @apply w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent;
}
.modal-fade-enter-active { transition: all 0.2s ease-out; }
.modal-fade-leave-active { transition: all 0.15s ease-in; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }
.modal-fade-enter-from .bg-white { transform: scale(0.97) translateY(-8px); }
</style>
