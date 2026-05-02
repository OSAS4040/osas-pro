<template>
  <div class="space-y-5" dir="rtl">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-bold text-gray-900 dark:text-white">مركباتي</h2>
        <p class="text-xs text-gray-400">جميع مركباتك المسجلة</p>
      </div>
      <div class="flex items-center gap-2">
        <ExcelImport
          endpoint="/api/v1/vehicles/import"
          template-url="/templates/vehicles_template.xlsx"
          template-file-name="vehicles_template.xlsx"
          :template-columns="['plate_number', 'make', 'model', 'year', 'color', 'vin']"
          label="استيراد Excel"
          title="استيراد مركبات"
          @imported="() => void load()"
        />
        <button class="flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 transition-colors"
                @click="openAdd"
        >
          <PlusIcon class="w-4 h-4" />
          إضافة مركبة
        </button>
      </div>
    </div>
    <div
      v-if="demoMode"
      class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800"
    >
      تم تفعيل بيانات المركبات التجريبية لتمكينك من التحقق.
    </div>

    <div class="rounded-xl border border-gray-200 bg-white dark:bg-slate-800 dark:border-slate-700 p-3 space-y-3">
      <div class="grid gap-3 md:grid-cols-3">
        <input
          v-model.trim="filters.search"
          type="text"
          class="field"
          placeholder="بحث باللوحة أو الماركة أو الموديل أو الشاسيه"
        />
        <select v-model="filters.status" class="field bg-white dark:bg-slate-700">
          <option value="all">كل الحالات</option>
          <option value="active">نشطة</option>
          <option value="inactive">غير نشطة</option>
        </select>
        <div class="flex items-center justify-between rounded-lg border border-gray-200 dark:border-slate-600 px-3 py-2 text-xs text-gray-600 dark:text-slate-300">
          <span>الإجمالي بعد الفلترة</span>
          <span class="font-bold text-violet-700 dark:text-violet-300">{{ listTotalCount }}</span>
        </div>
      </div>
      <div class="flex items-center justify-between text-xs text-gray-500">
        <p>عرض {{ pageStart }} - {{ pageEnd }} من {{ listTotalCount }} مركبة</p>
        <div class="flex items-center gap-2">
          <button type="button" class="px-3 py-1 rounded-md border border-gray-200 hover:bg-gray-50 disabled:opacity-50" :disabled="currentPage <= 1" @click="goPage(currentPage - 1)">السابق</button>
          <span>صفحة {{ currentPage }} / {{ totalPages }}</span>
          <button type="button" class="px-3 py-1 rounded-md border border-gray-200 hover:bg-gray-50 disabled:opacity-50" :disabled="currentPage >= totalPages" @click="goPage(currentPage + 1)">التالي</button>
        </div>
      </div>
    </div>

    <!-- Vehicle Cards -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="w-8 h-8 border-4 border-violet-400 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <div v-else-if="!listTotalCount" class="text-center py-14">
      <TruckIcon class="w-14 h-14 text-gray-200 mx-auto mb-3" />
      <p class="text-gray-400 text-sm">لا توجد نتائج مطابقة للبحث/الفلترة</p>
      <button class="mt-3 text-primary-600 text-sm hover:underline" @click="resetFilters">إعادة تعيين الفلاتر</button>
    </div>

    <div v-else class="space-y-3">
      <div v-for="v in tableRows" :key="v.id"
           class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 p-4 shadow-sm hover:shadow-md transition-shadow"
      >
        <div class="flex items-start justify-between">
          <div class="flex items-center gap-3">
            <div class="w-11 h-11 bg-violet-50 dark:bg-violet-900/30 rounded-xl flex items-center justify-center">
              <TruckIcon class="w-6 h-6 text-violet-600" />
            </div>
            <div>
              <p class="font-bold text-gray-900 dark:text-white text-sm font-mono">{{ v.plate_number }}</p>
              <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">{{ v.make }} {{ v.model }} {{ v.year ? `(${v.year})` : '' }}</p>
            </div>
          </div>
          <span :class="v.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                class="text-xs px-2 py-0.5 rounded-full font-medium"
          >
            {{ v.is_active ? 'نشطة' : 'غير نشطة' }}
          </span>
        </div>
        <div class="grid grid-cols-3 gap-2 mt-3 pt-3 border-t border-gray-50 dark:border-slate-700">
          <div class="text-center">
            <p class="text-xs text-gray-400">اللون</p>
            <p class="text-xs font-medium text-gray-700 dark:text-white mt-0.5">{{ v.color || '—' }}</p>
          </div>
          <div class="text-center">
            <p class="text-xs text-gray-400">الوقود</p>
            <p class="text-xs font-medium text-gray-700 dark:text-white mt-0.5">{{ fuelLabel(v.fuel_type) }}</p>
          </div>
          <div class="text-center">
            <p class="text-xs text-gray-400">الشاسيه</p>
            <p class="text-xs font-medium text-gray-700 dark:text-white mt-0.5 font-mono truncate">{{ v.vin || '—' }}</p>
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-2 mt-3 pt-3 border-t border-gray-50 dark:border-slate-700">
          <RouterLink :to="`/customer/vehicles/${v.id}`" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-primary-600 text-white hover:bg-primary-700 transition-colors">
            ملف المركبة
          </RouterLink>
          <RouterLink :to="`/customer/vehicles/${v.id}/card`" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-teal-100 text-teal-700 hover:bg-teal-200 transition-colors">
            البطاقة الرقمية + QR
          </RouterLink>
          <RouterLink :to="`/customer/vehicles/${v.id}/passport`" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-violet-100 text-violet-700 hover:bg-violet-200 transition-colors">
            سجل المركبة
          </RouterLink>
          <button
            type="button"
            class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200 transition-colors"
            @click="openEdit(v)"
          >
            تعديل
          </button>
          <button
            type="button"
            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors"
            :class="v.is_active ? 'bg-amber-100 text-amber-800 hover:bg-amber-200' : 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200'"
            @click="toggleVehicleState(v)"
          >
            {{ v.is_active ? 'تعطيل' : 'تنشيط' }}
          </button>
          <button
            type="button"
            class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-red-100 text-red-700 hover:bg-red-200 transition-colors"
            @click="deleteVehicle(v)"
          >
            حذف
          </button>
        </div>
      </div>
    </div>

    <!-- Add Vehicle Modal (same design as provider) -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="showAdd" :key="modalKey" class="fixed inset-0 bg-black/40 z-[100] flex items-center justify-center p-4" dir="rtl" @click.self="showAdd = false">
          <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-lg shadow-2xl border border-gray-100 dark:border-slate-700">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
              <h3 class="section-title">{{ editingVehicleId ? 'تعديل بيانات المركبة' : 'إضافة مركبة جديدة' }}</h3>
              <button class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700" @click="showAdd = false">
                <XMarkIcon class="w-5 h-5 text-gray-400" />
              </button>
            </div>
            <div class="p-5 space-y-4 max-h-[70vh] overflow-y-auto">
              <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2 space-y-2">
                  <label class="label">رقم اللوحة <span class="text-red-500">*</span></label>
                  <div class="flex flex-wrap items-center gap-2">
                    <CameraPlateScanner @plate="(p: string) => { form.plate_number = p }" />
                  </div>
                  <PlateInput v-model="form.plate_number" label="رقم اللوحة — تنسيق سعودي" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 dark:text-slate-300 mb-1">الماركة <span class="text-red-500">*</span></label>
                  <input v-model="form.make" class="field" placeholder="Toyota" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 dark:text-slate-300 mb-1">الموديل <span class="text-red-500">*</span></label>
                  <input v-model="form.model" class="field" placeholder="Camry" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 dark:text-slate-300 mb-1">سنة الصنع</label>
                  <input v-model="form.year" type="number" min="1990" :max="new Date().getFullYear()+1" class="field" placeholder="2023" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 dark:text-slate-300 mb-1">اللون</label>
                  <input v-model="form.color" class="field" placeholder="أبيض" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 dark:text-slate-300 mb-1">نوع الوقود</label>
                  <select v-model="form.fuel_type" class="field bg-white dark:bg-slate-700">
                    <option value="">اختر</option>
                    <option value="gasoline">بنزين</option>
                    <option value="diesel">ديزل</option>
                    <option value="hybrid">هجين</option>
                    <option value="electric">كهرباء</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 dark:text-slate-300 mb-1">رقم الشاسيه (VIN)</label>
                  <input v-model="form.vin" class="field font-mono" placeholder="1HGBH41JXMN109186" />
                </div>
                <div class="col-span-2">
                  <label class="block text-xs font-medium text-gray-600 dark:text-slate-300 mb-1">ملاحظات</label>
                  <textarea v-model="form.notes" rows="2" class="field resize-none" placeholder="أي ملاحظات إضافية..."></textarea>
                </div>
              </div>
              <div v-if="formErr" class="text-sm text-red-600 bg-red-50 rounded-lg p-3">{{ formErr }}</div>
            </div>
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
              <button class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-100 transition-colors" @click="showAdd = false">إلغاء</button>
              <button :disabled="saving" class="px-5 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50 transition-colors" @click="submit">
                {{ saving ? 'جارٍ الحفظ...' : (editingVehicleId ? 'حفظ التعديلات' : 'إضافة المركبة') }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { PlusIcon, XMarkIcon, TruckIcon } from '@heroicons/vue/24/outline'
import { watchDebounced } from '@vueuse/core'
import apiClient from '@/lib/apiClient'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { demoCustomerVehicles } from '@/utils/customerDemoData'
import ExcelImport from '@/components/ExcelImport.vue'
import PlateInput from '@/components/PlateInput.vue'
import CameraPlateScanner from '@/components/CameraPlateScanner.vue'

const toast = useToast()
const auth = useAuthStore()
const loading = ref(true)
const saving  = ref(false)
const showAdd = ref(false)
const modalKey = ref(0)
const formErr = ref('')
const editingVehicleId = ref<number | null>(null)
const vehicles = ref<any[]>([])
const vehiclesTotal = ref(0)
const vehiclesLastPage = ref(1)
const vehiclesListDemo = ref(false)
const demoMode = ref(false)
const pageSize = 20
const currentPage = ref(1)
const filters = reactive({
  search: '',
  status: 'all',
})

const form = reactive({ plate_number: '', make: '', model: '', year: '', color: '', fuel_type: '', vin: '', notes: '' })

const fuelMap: Record<string, string> = { gasoline: 'بنزين', diesel: 'ديزل', hybrid: 'هجين', electric: 'كهرباء' }
function fuelLabel(t: string) { return fuelMap[t] || '—' }
function filterDemoVehicles(): typeof demoCustomerVehicles {
  const q = String(filters.search || '').trim().toLowerCase()
  return demoCustomerVehicles.filter((v) => {
    if (filters.status === 'active' && !v?.is_active) return false
    if (filters.status === 'inactive' && v?.is_active) return false
    if (!q) return true
    const hay = `${v?.plate_number ?? ''} ${v?.make ?? ''} ${v?.model ?? ''} ${v?.vin ?? ''}`.toLowerCase()
    return hay.includes(q)
  })
}
const listTotalCount = computed(() => (vehiclesListDemo.value ? filterDemoVehicles().length : vehiclesTotal.value))
const totalPages = computed(() => {
  if (vehiclesListDemo.value) return Math.max(1, Math.ceil(filterDemoVehicles().length / pageSize))
  return Math.max(1, vehiclesLastPage.value)
})
const pageStart = computed(() => (listTotalCount.value ? (currentPage.value - 1) * pageSize + 1 : 0))
const pageEnd = computed(() => {
  if (vehiclesListDemo.value) return Math.min(currentPage.value * pageSize, filterDemoVehicles().length)
  return Math.min((currentPage.value - 1) * pageSize + vehicles.value.length, vehiclesTotal.value)
})
const tableRows = computed(() => {
  if (vehiclesListDemo.value) {
    const all = filterDemoVehicles()
    const start = (currentPage.value - 1) * pageSize
    return all.slice(start, start + pageSize)
  }
  return vehicles.value
})
function goPage(p: number): void {
  const next = Math.max(1, Math.min(p, totalPages.value))
  if (next === currentPage.value) return
  currentPage.value = next
  if (!vehiclesListDemo.value) void load()
}
function resetFilters(): void {
  filters.search = ''
  filters.status = 'all'
  currentPage.value = 1
  if (!vehiclesListDemo.value) void load()
}
watchDebounced(
  () => filters.search,
  () => {
    currentPage.value = 1
    if (!vehiclesListDemo.value) void load()
  },
  { debounce: 350 },
)
watch(
  () => filters.status,
  () => {
    currentPage.value = 1
    if (!vehiclesListDemo.value) void load()
  },
)
watch(totalPages, (next) => {
  if (currentPage.value > next) currentPage.value = next
})

function openAdd() {
  editingVehicleId.value = null
  modalKey.value += 1
  Object.assign(form, { plate_number: '', make: '', model: '', year: '', color: '', fuel_type: '', vin: '', notes: '' })
  formErr.value = ''
  showAdd.value = true
}
function openEdit(v: any): void {
  editingVehicleId.value = Number(v?.id || 0) || null
  modalKey.value += 1
  Object.assign(form, {
    plate_number: String(v?.plate_number || '').replace(/\s+/g, ''),
    make: String(v?.make || ''),
    model: String(v?.model || ''),
    year: v?.year ? String(v.year) : '',
    color: String(v?.color || ''),
    fuel_type: String(v?.fuel_type || ''),
    vin: String(v?.vin || ''),
    notes: String(v?.notes || ''),
  })
  formErr.value = ''
  showAdd.value = true
}

async function load(options?: { clampOnly?: boolean }): Promise<void> {
  loading.value = true
  demoMode.value = false
  vehiclesListDemo.value = false
  try {
    const params: Record<string, unknown> = { per_page: pageSize, page: currentPage.value }
    if (auth.user?.customer_id != null) params.customer_id = auth.user.customer_id
    const s = filters.search.trim()
    if (s) params.search = s
    if (filters.status === 'active') params.is_active = true
    if (filters.status === 'inactive') params.is_active = false
    const { data } = await apiClient.get('/vehicles', { params })
    const paginator = data?.data
    vehicles.value = Array.isArray(paginator?.data) ? paginator.data : []
    vehiclesTotal.value = Number(paginator?.total ?? vehicles.value.length)
    vehiclesLastPage.value = Math.max(1, Number(paginator?.last_page ?? 1))
    if (!options?.clampOnly && vehiclesLastPage.value >= 1 && currentPage.value > vehiclesLastPage.value) {
      currentPage.value = vehiclesLastPage.value
      await load({ clampOnly: true })
      return
    }
  } catch {
    vehicles.value = []
    vehiclesListDemo.value = true
    demoMode.value = true
    vehiclesTotal.value = filterDemoVehicles().length
    vehiclesLastPage.value = Math.max(1, Math.ceil(vehiclesTotal.value / pageSize))
  } finally {
    loading.value = false
  }
}

async function submit() {
  if (!form.plate_number || !form.make || !form.model) {
    formErr.value = 'رقم اللوحة والماركة والموديل مطلوبة'
    return
  }
  const compact = form.plate_number.trim().toUpperCase().replace(/\s+/g, '')
  const m = compact.match(/^([A-Z]{3})(\d{3,4})$/)
  if (!m) {
    formErr.value = 'اللوحة: 3 حروف إنجليزية ثم 3 أو 4 أرقام (مثل ABC1234).'
    return
  }
  const pn = `${m[1]} ${m[2]}`
  saving.value = true
  formErr.value = ''
  try {
    const payload = {
      ...form,
      plate_number: pn,
      year: form.year ? Number(form.year) : null,
    }
    if (editingVehicleId.value) {
      await apiClient.put(`/vehicles/${editingVehicleId.value}`, payload)
      toast.success('تم التحديث', 'تم تعديل بيانات المركبة بنجاح.')
    } else {
      await apiClient.post('/vehicles', payload)
      toast.success('تم إضافة المركبة بنجاح')
    }
    showAdd.value = false
    editingVehicleId.value = null
    await load()
  } catch (e: any) {
    formErr.value = e?.response?.data?.message ?? 'حدث خطأ'
  } finally {
    saving.value = false
  }
}
async function toggleVehicleState(v: any): Promise<void> {
  const id = Number(v?.id || 0)
  if (!id) return
  try {
    await apiClient.put(`/vehicles/${id}`, {
      plate_number: v?.plate_number,
      make: v?.make,
      model: v?.model,
      year: v?.year ?? null,
      color: v?.color ?? null,
      fuel_type: v?.fuel_type ?? null,
      vin: v?.vin ?? null,
      notes: v?.notes ?? null,
      is_active: !v?.is_active,
    })
    toast.success('تم التحديث', `تم ${v?.is_active ? 'تعطيل' : 'تنشيط'} المركبة بنجاح.`)
    await load()
  } catch (e: any) {
    toast.error('تعذر التحديث', e?.response?.data?.message || 'تعذر تحديث حالة المركبة.')
  }
}
async function deleteVehicle(v: any): Promise<void> {
  const id = Number(v?.id || 0)
  if (!id) return
  const ok = window.confirm(`هل تريد حذف المركبة ${v?.plate_number || ''}؟ لا يمكن التراجع عن العملية.`)
  if (!ok) return
  try {
    await apiClient.delete(`/vehicles/${id}`)
    toast.success('تم الحذف', 'تم حذف المركبة بنجاح.')
    await load()
  } catch (e: any) {
    toast.error('تعذر الحذف', e?.response?.data?.message || 'تعذر حذف المركبة.')
  }
}

onMounted(load)
</script>

<style scoped>
.field {
  @apply w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent dark:bg-slate-700 dark:text-white;
}
.modal-fade-enter-active { transition: all 0.2s ease-out; }
.modal-fade-leave-active { transition: all 0.15s ease-in; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }
.modal-fade-enter-from .bg-white { transform: scale(0.97) translateY(-8px); }
</style>
