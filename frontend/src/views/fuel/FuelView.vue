<template>
  <div class="space-y-6" dir="rtl">
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <svg class="w-7 h-7 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h2l1 9h12l1-9h2M7 10V7a5 5 0 0110 0v3M12 14v3"/></svg>
          إدارة الوقود
        </h1>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">تتبع استهلاك الوقود وتكاليف التشغيل</p>
      </div>
      <button @click="showForm = true" class="flex items-center gap-2 px-4 py-2 bg-orange-500 text-white rounded-xl text-sm font-semibold hover:bg-orange-600 transition-colors shadow-sm">
        <PlusIcon class="w-5 h-5" />
        تسجيل تعبئة
      </button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div v-for="s in statCards" :key="s.label" class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 p-5 shadow-sm">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs text-gray-400 dark:text-slate-500">{{ s.label }}</span>
          <div class="w-8 h-8 rounded-lg flex items-center justify-center" :class="s.bg">
            <component :is="s.icon" class="w-4 h-4" :class="s.color" />
          </div>
        </div>
        <p class="text-xl font-bold text-gray-900 dark:text-white">{{ s.value }}</p>
        <p class="text-xs text-gray-400 mt-0.5">{{ s.sub }}</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 p-4 shadow-sm flex flex-wrap gap-3">
      <select v-model="filters.vehicle_id" @change="load" class="px-3 py-2 border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl text-sm focus:ring-2 focus:ring-orange-400">
        <option value="">كل المركبات</option>
        <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.plate_number }} — {{ v.make }} {{ v.model }}</option>
      </select>
      <select v-model="filters.fuel_type" @change="load" class="px-3 py-2 border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl text-sm focus:ring-2 focus:ring-orange-400">
        <option value="">كل الأنواع</option>
        <option value="91">بنزين 91</option>
        <option value="95">بنزين 95</option>
        <option value="98">بنزين 98</option>
        <option value="diesel">ديزل</option>
      </select>
      <input type="date" v-model="filters.from" @change="load" class="px-3 py-2 border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl text-sm focus:ring-2 focus:ring-orange-400" />
      <input type="date" v-model="filters.to" @change="load" class="px-3 py-2 border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl text-sm focus:ring-2 focus:ring-orange-400" />
      <button @click="exportCSV" class="px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-xl text-sm text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 flex items-center gap-2">
        <ArrowDownTrayIcon class="w-4 h-4" /> تصدير
      </button>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm overflow-hidden">
      <div v-if="loading" class="flex justify-center py-16">
        <div class="w-8 h-8 border-4 border-orange-200 border-t-orange-500 rounded-full animate-spin"></div>
      </div>
      <div v-else-if="!logs.length" class="py-16 text-center">
        <p class="text-gray-400 text-sm">لا توجد سجلات وقود</p>
        <button @click="showForm = true" class="mt-3 text-orange-500 text-sm hover:underline">ابدأ بتسجيل أول تعبئة</button>
      </div>
      <table v-else class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-slate-700/50 text-xs text-gray-500 dark:text-slate-400">
          <tr>
            <th class="px-5 py-3 text-right font-semibold">التاريخ</th>
            <th class="px-4 py-3 text-right font-semibold">المركبة</th>
            <th class="px-4 py-3 text-right font-semibold">النوع</th>
            <th class="px-4 py-3 text-right font-semibold">اللترات</th>
            <th class="px-4 py-3 text-right font-semibold">سعر/لتر</th>
            <th class="px-4 py-3 text-right font-semibold">الإجمالي</th>
            <th class="px-4 py-3 text-right font-semibold">كفاءة (ك/ل)</th>
            <th class="px-4 py-3 text-right font-semibold">المحطة</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
          <tr v-for="log in logs" :key="log.id" class="hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors">
            <td class="px-5 py-3.5 text-gray-700 dark:text-slate-300">{{ fmtDate(log.log_date) }}</td>
            <td class="px-4 py-3.5">
              <span class="font-medium text-gray-900 dark:text-white">{{ log.vehicle?.plate_number }}</span>
              <span class="text-xs text-gray-400 mr-1">{{ log.vehicle?.make }}</span>
            </td>
            <td class="px-4 py-3.5">
              <span class="px-2 py-0.5 rounded-full text-xs font-semibold" :class="fuelTypeClass(log.fuel_type)">{{ log.fuel_type }}</span>
            </td>
            <td class="px-4 py-3.5 font-medium text-gray-900 dark:text-white">{{ log.liters?.toFixed(2) }} ل</td>
            <td class="px-4 py-3.5 text-gray-600 dark:text-slate-400">{{ fmt(log.price_per_liter) }}</td>
            <td class="px-4 py-3.5 font-bold text-orange-600 dark:text-orange-400">{{ fmt(log.total_cost) }}</td>
            <td class="px-4 py-3.5">
              <span v-if="log.fuel_efficiency" class="text-green-600 dark:text-green-400 font-medium">{{ log.fuel_efficiency?.toFixed(2) }}</span>
              <span v-else class="text-gray-300">—</span>
            </td>
            <td class="px-4 py-3.5 text-gray-500 dark:text-slate-400 text-xs">{{ log.station_name ?? '—' }}</td>
            <td class="px-4 py-3.5">
              <button @click="deleteLog(log.id)" class="p-1.5 rounded-lg hover:bg-red-50 text-gray-300 hover:text-red-500 transition-colors">
                <TrashIcon class="w-4 h-4" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add Fuel Modal -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="showForm" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showForm = false" dir="rtl">
          <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-lg shadow-2xl">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-slate-700">
              <h3 class="font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h2l1 9h12l1-9h2"/></svg>
                تسجيل تعبئة وقود
              </h3>
              <button @click="showForm = false" class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700"><XMarkIcon class="w-5 h-5 text-gray-400" /></button>
            </div>
            <div class="p-6 space-y-4">
              <div class="grid grid-cols-2 gap-3">
                <div class="col-span-2">
                  <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">المركبة *</label>
                  <select v-model="form.vehicle_id" class="w-full px-3 py-2.5 border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl text-sm focus:ring-2 focus:ring-orange-400">
                    <option value="">اختر مركبة</option>
                    <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.plate_number }} — {{ v.make }} {{ v.model }}</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">التاريخ *</label>
                  <input type="date" v-model="form.log_date" class="w-full px-3 py-2.5 border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl text-sm focus:ring-2 focus:ring-orange-400" />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">نوع الوقود</label>
                  <select v-model="form.fuel_type" class="w-full px-3 py-2.5 border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl text-sm focus:ring-2 focus:ring-orange-400">
                    <option value="91">بنزين 91</option>
                    <option value="95">بنزين 95</option>
                    <option value="98">بنزين 98</option>
                    <option value="diesel">ديزل</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">الكمية (لتر) *</label>
                  <input type="number" v-model.number="form.liters" min="0.1" step="0.001" placeholder="0.000"
                    class="w-full px-3 py-2.5 border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl text-sm focus:ring-2 focus:ring-orange-400" />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">السعر/لتر (ريال) *</label>
                  <input type="number" v-model.number="form.price_per_liter" min="0" step="0.001" placeholder="0.000"
                    class="w-full px-3 py-2.5 border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl text-sm focus:ring-2 focus:ring-orange-400" />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">عداد المسافة قبل</label>
                  <input type="number" v-model.number="form.odometer_before" placeholder="كم"
                    class="w-full px-3 py-2.5 border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl text-sm focus:ring-2 focus:ring-orange-400" />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">عداد المسافة بعد</label>
                  <input type="number" v-model.number="form.odometer_after" placeholder="كم"
                    class="w-full px-3 py-2.5 border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl text-sm focus:ring-2 focus:ring-orange-400" />
                </div>
                <div class="col-span-2">
                  <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">اسم المحطة</label>
                  <input type="text" v-model="form.station_name" placeholder="مثال: أرامكو — الطريق الدائري"
                    class="w-full px-3 py-2.5 border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl text-sm focus:ring-2 focus:ring-orange-400" />
                </div>
              </div>
              <!-- Cost preview -->
              <div v-if="form.liters && form.price_per_liter" class="bg-orange-50 dark:bg-orange-900/20 rounded-xl p-3 flex items-center justify-between">
                <span class="text-sm text-orange-700 dark:text-orange-300">إجمالي التكلفة:</span>
                <span class="font-bold text-lg text-orange-600 dark:text-orange-400">{{ fmt(form.liters * form.price_per_liter) }}</span>
              </div>
              <div v-if="efficiency" class="bg-green-50 dark:bg-green-900/20 rounded-xl p-3 flex items-center justify-between">
                <span class="text-sm text-green-700 dark:text-green-300">الكفاءة المتوقعة:</span>
                <span class="font-bold text-lg text-green-600 dark:text-green-400">{{ efficiency }} كم/لتر</span>
              </div>
              <div v-if="formError" class="text-sm text-red-600 bg-red-50 dark:bg-red-900/30 rounded-xl p-3">{{ formError }}</div>
            </div>
            <div class="flex gap-3 px-6 py-4 border-t border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-700/50 rounded-b-2xl">
              <button @click="showForm = false" class="flex-1 px-4 py-2.5 border border-gray-200 dark:border-slate-600 rounded-xl text-sm font-medium text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors">إلغاء</button>
              <button @click="submit" :disabled="submitting" class="flex-1 px-4 py-2.5 bg-orange-500 text-white rounded-xl text-sm font-semibold hover:bg-orange-600 disabled:opacity-50 transition-colors">
                {{ submitting ? 'جارٍ الحفظ...' : 'تسجيل التعبئة' }}
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
import { PlusIcon, TrashIcon, ArrowDownTrayIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import { useToast } from '@/composables/useToast'

const toast = useToast()
const loading = ref(false)
const submitting = ref(false)
const showForm = ref(false)
const formError = ref('')
const logs = ref<any[]>([])
const vehicles = ref<any[]>([])
const statsData = ref<any>({})

const filters = reactive({ vehicle_id: '', fuel_type: '', from: '', to: '' })
const form = reactive({
  vehicle_id: '', log_date: new Date().toISOString().slice(0, 10),
  liters: null as number | null, price_per_liter: null as number | null,
  odometer_before: null as number | null, odometer_after: null as number | null,
  fuel_type: '95', station_name: '',
})

const fmt = (n: any) => new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR', minimumFractionDigits: 2 }).format(parseFloat(n) || 0)
const fmtDate = (d: string) => new Date(d).toLocaleDateString('ar-SA', { day: 'numeric', month: 'short', year: 'numeric' })

const efficiency = computed(() => {
  if (form.odometer_before && form.odometer_after && form.liters) {
    const km = form.odometer_after - form.odometer_before
    if (km > 0) return (km / form.liters).toFixed(2)
  }
  return null
})

const statCards = computed(() => {
  const t = statsData.value?.totals ?? {}
  return [
    { label: 'إجمالي التكلفة', value: fmt(t.total_cost ?? 0), sub: 'هذا الشهر', bg: 'bg-orange-100 dark:bg-orange-900/30', color: 'text-orange-600', icon: { render: () => null } },
    { label: 'إجمالي اللترات', value: `${(t.total_liters ?? 0).toFixed(1)} ل`, sub: 'مجموع التعبئات', bg: 'bg-blue-100 dark:bg-blue-900/30', color: 'text-blue-600', icon: { render: () => null } },
    { label: 'متوسط الكفاءة', value: `${(t.avg_efficiency ?? 0).toFixed(2)} ك/ل`, sub: 'كم لكل لتر', bg: 'bg-green-100 dark:bg-green-900/30', color: 'text-green-600', icon: { render: () => null } },
    { label: 'عدد التعبئات', value: t.total_logs ?? 0, sub: 'عملية تعبئة', bg: 'bg-purple-100 dark:bg-purple-900/30', color: 'text-purple-600', icon: { render: () => null } },
  ]
})

function fuelTypeClass(t: string) {
  return { '91': 'bg-yellow-100 text-yellow-700', '95': 'bg-orange-100 text-orange-700', '98': 'bg-red-100 text-red-700', diesel: 'bg-gray-100 text-gray-700' }[t] ?? 'bg-gray-100 text-gray-700'
}

async function load() {
  loading.value = true
  try {
    const [logsR, statsR] = await Promise.all([
      apiClient.get('/fuel', { params: filters }),
      apiClient.get('/fuel/stats', { params: { from: filters.from || undefined, to: filters.to || undefined, vehicle_id: filters.vehicle_id || undefined } }),
    ])
    logs.value = logsR.data?.data ?? logsR.data ?? []
    statsData.value = statsR.data
  } catch { logs.value = [] }
  finally { loading.value = false }
}

async function loadVehicles() {
  try {
    const r = await apiClient.get('/vehicles', { params: { per_page: 300 } })
    vehicles.value = r.data?.data ?? []
  } catch { /* silent */ }
}

async function submit() {
  if (!form.vehicle_id || !form.liters || !form.price_per_liter) {
    formError.value = 'يرجى تعبئة الحقول المطلوبة'
    return
  }
  submitting.value = true; formError.value = ''
  try {
    await apiClient.post('/fuel', form)
    toast.success('تم تسجيل التعبئة بنجاح')
    showForm.value = false
    Object.assign(form, { vehicle_id: '', liters: null, price_per_liter: null, odometer_before: null, odometer_after: null, station_name: '' })
    await load()
  } catch (e: any) { formError.value = e?.response?.data?.message ?? 'حدث خطأ' }
  finally { submitting.value = false }
}

async function deleteLog(id: number) {
  if (!confirm('هل أنت متأكد من الحذف؟')) return
  try {
    await apiClient.delete(`/fuel/${id}`)
    toast.success('تم الحذف')
    logs.value = logs.value.filter(l => l.id !== id)
  } catch { toast.error('فشل الحذف') }
}

function exportCSV() {
  const rows = [['التاريخ', 'المركبة', 'النوع', 'اللترات', 'السعر/لتر', 'الإجمالي', 'الكفاءة', 'المحطة']]
  logs.value.forEach(l => rows.push([l.log_date, l.vehicle?.plate_number, l.fuel_type, l.liters, l.price_per_liter, l.total_cost, l.fuel_efficiency ?? '', l.station_name ?? '']))
  const csv = '\uFEFF' + rows.map(r => r.join(',')).join('\n')
  const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' })); a.download = 'fuel_logs.csv'; a.click()
}

onMounted(async () => { await Promise.all([load(), loadVehicles()]) })
</script>
