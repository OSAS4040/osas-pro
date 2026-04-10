<template>
  <div class="space-y-5" dir="rtl">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-bold text-gray-900 dark:text-white">مركباتي</h2>
        <p class="text-xs text-gray-400">جميع مركباتك المسجلة</p>
      </div>
      <button class="flex items-center gap-1.5 bg-orange-500 hover:bg-orange-600 text-white text-sm px-4 py-2 rounded-xl transition-colors shadow-sm"
              @click="openAdd"
      >
        <PlusIcon class="w-4 h-4" />
        إضافة مركبة
      </button>
    </div>

    <!-- Vehicle Cards -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="w-8 h-8 border-4 border-orange-400 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <div v-else-if="!vehicles.length" class="text-center py-14">
      <TruckIcon class="w-14 h-14 text-gray-200 mx-auto mb-3" />
      <p class="text-gray-400 text-sm">لا توجد مركبات مسجلة</p>
      <button class="mt-3 text-orange-500 text-sm hover:underline" @click="openAdd">أضف مركبتك الأولى</button>
    </div>

    <div v-else class="space-y-3">
      <div v-for="v in vehicles" :key="v.id"
           class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 p-4 shadow-sm hover:shadow-md transition-shadow"
      >
        <div class="flex items-start justify-between">
          <div class="flex items-center gap-3">
            <div class="w-11 h-11 bg-orange-50 dark:bg-orange-900/30 rounded-xl flex items-center justify-center">
              <TruckIcon class="w-6 h-6 text-orange-500" />
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
      </div>
    </div>

    <!-- Add Vehicle Modal -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="showAdd" class="fixed inset-0 bg-black/40 z-50 flex items-end sm:items-center justify-center p-4" dir="rtl"
             @click.self="showAdd = false"
        >
          <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-md shadow-2xl">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
              <h3 class="font-bold text-gray-900 dark:text-white">إضافة مركبة جديدة</h3>
              <button class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700" @click="showAdd = false">
                <XMarkIcon class="w-5 h-5 text-gray-400" />
              </button>
            </div>
            <div class="p-5 space-y-4 max-h-[70vh] overflow-y-auto">
              <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-slate-300 mb-1">رقم اللوحة <span class="text-red-500">*</span></label>
                <input v-model="form.plate_number" class="field" placeholder="ABC 1234" />
              </div>
              <div class="grid grid-cols-2 gap-3">
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
                  <label class="block text-xs font-medium text-gray-600 dark:text-slate-300 mb-1">رقم الشاسيه</label>
                  <input v-model="form.vin" class="field font-mono" placeholder="VIN..." />
                </div>
              </div>
              <div v-if="formErr" class="text-sm text-red-600 bg-red-50 rounded-lg p-3">{{ formErr }}</div>
            </div>
            <div class="flex gap-2 px-5 py-4 border-t border-gray-100 dark:border-slate-700">
              <button class="flex-1 py-2.5 border border-gray-300 dark:border-slate-600 rounded-xl text-sm text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700" @click="showAdd = false">إلغاء</button>
              <button :disabled="saving" class="flex-1 py-2.5 bg-orange-500 hover:bg-orange-600 disabled:opacity-50 text-white rounded-xl text-sm font-semibold transition-colors"
                      @click="submit"
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
import { ref, reactive, onMounted } from 'vue'
import { PlusIcon, XMarkIcon, TruckIcon } from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import { useToast } from '@/composables/useToast'

const toast   = useToast()
const loading = ref(true)
const saving  = ref(false)
const showAdd = ref(false)
const formErr = ref('')
const vehicles = ref<any[]>([])

const form = reactive({ plate_number: '', make: '', model: '', year: '', color: '', fuel_type: '', vin: '' })

const fuelMap: Record<string, string> = { gasoline: 'بنزين', diesel: 'ديزل', hybrid: 'هجين', electric: 'كهرباء' }
function fuelLabel(t: string) { return fuelMap[t] || '—' }

function openAdd() {
  Object.assign(form, { plate_number: '', make: '', model: '', year: '', color: '', fuel_type: '', vin: '' })
  formErr.value = ''
  showAdd.value = true
}

async function load() {
  loading.value = true
  try {
    const { data } = await apiClient.get('/vehicles', { params: { per_page: 100 } })
    vehicles.value = data.data?.data ?? data.data ?? []
  } catch { /* silent */ } finally { loading.value = false }
}

async function submit() {
  if (!form.plate_number || !form.make || !form.model) {
    formErr.value = 'رقم اللوحة والماركة والموديل مطلوبة'
    return
  }
  saving.value = true
  formErr.value = ''
  try {
    await apiClient.post('/vehicles', {
      ...form,
      year: form.year ? Number(form.year) : null,
    })
    toast.success('تم إضافة المركبة')
    showAdd.value = false
    await load()
  } catch (e: any) {
    formErr.value = e?.response?.data?.message ?? 'حدث خطأ'
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.field {
  @apply w-full px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-xl text-sm focus:ring-2 focus:ring-orange-400 focus:border-transparent dark:bg-slate-700 dark:text-white;
}
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
