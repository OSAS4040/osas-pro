<template>
  <div class="app-shell-page space-y-4">
    <div class="page-head">
      <div class="page-title-wrap">
        <h2 class="page-title-xl">المركبات</h2>
        <p class="page-subtitle">البحث في الأسطول وإدارة ملفات المركبات</p>
      </div>
      <div class="page-toolbar flex-wrap">
        <ExcelImport
          endpoint="/api/v1/vehicles/import"
          template-url="/templates/vehicles_template.csv"
          label="استيراد Excel"
          title="استيراد مركبات من Excel"
          @imported="load"
        />
        <button class="flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 transition-colors" @click="openCreate">
          <PlusIcon class="w-4 h-4" />
          إضافة مركبة
        </button>
      </div>
    </div>

    <div class="panel overflow-hidden p-0">
      <div class="panel-head border-b border-gray-100 dark:border-slate-700 flex gap-3">
        <input
          v-model="search"
          placeholder="رقم اللوحة، الشاسيه، الماركة، الموديل..."
          class="field flex-1 min-w-0"
          @keyup.enter="load"
        />
        <button type="button" class="btn btn-secondary shrink-0" @click="load">بحث</button>
      </div>

      <table class="data-table">
        <thead>
          <tr>
            <th class="text-right">رقم اللوحة</th>
            <th class="text-right">الماركة / الموديل</th>
            <th class="text-right">السنة</th>
            <th class="text-right">العميل</th>
            <th class="text-right">الوقود</th>
            <th class="text-right">الحالة</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="v in store.vehicles" :key="v.id" class="hover:bg-gray-50 dark:hover:bg-slate-700/30 cursor-pointer" @click="$router.push(`/vehicles/${v.id}`)">
            <td class="font-mono font-semibold text-gray-900 dark:text-slate-100 text-right">{{ v.plate_number }}</td>
            <td class="text-right">{{ v.make }} {{ v.model }}</td>
            <td class="text-right text-gray-500 dark:text-slate-400">{{ v.year ?? '—' }}</td>
            <td class="text-right text-gray-600 dark:text-slate-300">{{ v.customer?.name }}</td>
            <td class="text-right text-gray-500 dark:text-slate-400">{{ v.fuel_type ?? '—' }}</td>
            <td class="text-right">
              <span
                :class="v.is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-500 dark:bg-slate-700 dark:text-slate-400'"
                class="px-2 py-0.5 rounded-full badge-text"
              >
                {{ v.is_active ? 'نشطة' : 'غير نشطة' }}
              </span>
            </td>
            <td class="text-left" @click.stop>
              <RouterLink :to="`/vehicles/${v.id}`" class="text-primary-600 dark:text-primary-400 hover:underline text-xs font-medium">عرض</RouterLink>
            </td>
          </tr>
          <tr v-if="!store.vehicles.length">
            <td colspan="7" class="table-empty">
              <TruckIcon class="w-10 h-10 text-gray-200 dark:text-slate-600 mx-auto mb-2" />
              <p class="empty-state-title">لا توجد مركبات</p>
              <p class="empty-state-description">ابدأ بإضافة مركبة أو عدّل معايير البحث</p>
              <button type="button" class="mt-2 text-primary-600 dark:text-primary-400 text-xs font-medium hover:underline" @click="openCreate">أضف أول مركبة</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create Vehicle Modal -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="showCreate" :key="modalKey" class="fixed inset-0 bg-black/40 z-[100] flex items-center justify-center p-4" dir="rtl" @click.self="showCreate = false">
          <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-lg shadow-2xl border border-gray-100 dark:border-slate-700">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-slate-700">
              <h3 class="section-title">إضافة مركبة جديدة</h3>
              <button class="p-1.5 rounded-lg hover:bg-gray-100 transition-colors" @click="showCreate = false">
                <XMarkIcon class="w-5 h-5 text-gray-400" />
              </button>
            </div>

            <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
              <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2 space-y-2">
                  <label class="label">رقم اللوحة <span class="text-red-500">*</span></label>
                  <div class="flex flex-wrap items-center gap-2">
                    <CameraPlateScanner @plate="(p: string) => { form.plate_number = p }" />
                  </div>
                  <PlateInput v-model="form.plate_number" label="رقم اللوحة — تنسيق سعودي" />
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
              <button class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-100 transition-colors" @click="showCreate = false">إلغاء</button>
              <button :disabled="saving" class="px-5 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50 transition-colors" @click="submitCreate">
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
import { ref, reactive, onMounted, watch, nextTick } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { PlusIcon, XMarkIcon, TruckIcon } from '@heroicons/vue/24/outline'
import ExcelImport from '@/components/ExcelImport.vue'
import { useVehicleStore } from '@/stores/vehicle'
import apiClient from '@/lib/apiClient'
import { useToast } from '@/composables/useToast'
import PlateInput from '@/components/PlateInput.vue'
import CameraPlateScanner from '@/components/CameraPlateScanner.vue'

const store    = useVehicleStore()
const $router  = useRouter()
const route    = useRoute()
const toast    = useToast()
const search   = ref('')
const showCreate = ref(false)
const modalKey   = ref(0)
const saving   = ref(false)
const formError = ref('')
const customers = ref<any[]>([])

const form = reactive({
  plate_number: '', make: '', model: '', year: '',
  fuel_type: '', color: '', vin: '', customer_id: '', notes: '',
})

function openCreate() {
  modalKey.value += 1
  Object.assign(form, { plate_number: '', make: '', model: '', year: '', fuel_type: '', color: '', vin: '', customer_id: '', notes: '' })
  formError.value = ''
  showCreate.value = true
}

async function openCreateIfQuery() {
  const q = route.query.add
  if (q !== '1' && q !== 'true') return
  openCreate()
  await nextTick()
  await nextTick()
  window.setTimeout(() => {
    if (route.query.add) {
      $router.replace({ path: '/vehicles', query: {} })
    }
  }, 120)
}

async function load(): Promise<void> {
  const cid = route.query.customer_id
  const companyId = route.query.company_id
  const params: Record<string, unknown> = { search: search.value }
  if (cid !== undefined && cid !== null && String(cid).match(/^\d+$/)) {
    params.customer_id = Number(cid)
  }
  if (companyId !== undefined && companyId !== null && String(companyId).match(/^\d+$/)) {
    params.company_id = Number(companyId)
  }
  await store.fetchVehicles(params)
}

async function loadCustomers() {
  try {
    const r = await apiClient.get('/customers', { params: { per_page: 200 } })
    const d = r.data?.data
    customers.value = Array.isArray(d) ? d : d?.data ?? []
  } catch { /* silent */ }
}

async function submitCreate() {
  if (!form.plate_number || !form.make || !form.model) {
    formError.value = 'رقم اللوحة والماركة والموديل مطلوبة'
    return
  }
  const compact = form.plate_number.trim().toUpperCase().replace(/\s+/g, '')
  const m = compact.match(/^([A-Z]{3})(\d{3,4})$/)
  if (!m) {
    formError.value = 'اللوحة: 3 حروف إنجليزية ثم 3 أو 4 أرقام (مثل ABC1234).'
    return
  }
  const pn = `${m[1]} ${m[2]}`
  saving.value = true
  formError.value = ''
  try {
    await apiClient.post('/vehicles', {
      ...form,
      plate_number: pn,
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
  openCreateIfQuery()
})

watch(
  () => route.query.add,
  () => openCreateIfQuery(),
)

watch(
  () => route.query.customer_id,
  () => {
    void load()
  },
)

watch(
  () => route.query.company_id,
  () => {
    void load()
  },
)
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
