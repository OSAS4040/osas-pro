<template>
  <div class="app-shell-page space-y-5" dir="rtl">
    <!-- Header -->
    <div class="page-head no-print">
      <div class="page-title-wrap">
        <h2 class="page-title-xl">إدارة الإجازات</h2>
        <p class="page-subtitle">تدفق واضح للطلب والمراجعة والاعتماد مع فلترة سريعة وتقليل التشتت</p>
      </div>
      <button class="btn btn-primary"
              @click="openNew"
      >
        <PlusIcon class="w-4 h-4" />
        طلب إجازة جديدة
      </button>
    </div>

    <!-- Stats -->
    <div class="no-print grid grid-cols-2 md:grid-cols-4 gap-3">
      <div class="panel p-4 text-center">
        <p class="text-2xl font-bold text-yellow-600">{{ stats.pending }}</p>
        <p class="text-xs text-gray-500 mt-1">معلقة</p>
      </div>
      <div class="panel p-4 text-center">
        <p class="text-2xl font-bold text-green-600">{{ stats.approved }}</p>
        <p class="text-xs text-gray-500 mt-1">مقبولة</p>
      </div>
      <div class="panel p-4 text-center">
        <p class="text-2xl font-bold text-red-600">{{ stats.rejected }}</p>
        <p class="text-xs text-gray-500 mt-1">مرفوضة</p>
      </div>
      <div class="panel p-4 text-center">
        <p class="text-2xl font-bold text-primary-600">{{ stats.totalDays }}</p>
        <p class="text-xs text-gray-500 mt-1">إجمالي أيام</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="no-print panel p-4 flex gap-3 flex-wrap items-end">
      <div class="flex gap-2 flex-wrap">
        <button type="button" class="px-3 py-1.5 rounded-lg text-xs font-medium border"
                :class="quickTab==='all' ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-gray-200 text-gray-500'"
                @click="quickTab='all'"
        >
          الكل
        </button>
        <button type="button" class="px-3 py-1.5 rounded-lg text-xs font-medium border"
                :class="quickTab==='pending' ? 'border-yellow-500 bg-yellow-50 text-yellow-700' : 'border-gray-200 text-gray-500'"
                @click="quickTab='pending'"
        >
          المعلقة
        </button>
        <button type="button" class="px-3 py-1.5 rounded-lg text-xs font-medium border"
                :class="quickTab==='approved' ? 'border-green-500 bg-green-50 text-green-700' : 'border-gray-200 text-gray-500'"
                @click="quickTab='approved'"
        >
          المقبولة
        </button>
      </div>
      <select v-model="filterEmployee" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none">
        <option value="">كل الموظفين</option>
        <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.full_name || e.name }}</option>
      </select>
      <select v-model="filterStatus" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none">
        <option value="">كل الحالات</option>
        <option value="pending">معلقة</option>
        <option value="approved">مقبولة</option>
        <option value="rejected">مرفوضة</option>
      </select>
      <div class="mr-auto flex gap-2">
        <button class="btn btn-outline btn-sm" @click="printPage">طباعة</button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="no-print flex justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
    </div>

    <!-- Table -->
    <div v-else class="table-shell print-container">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
              <th class="px-4 py-3 text-right font-semibold text-gray-700">الموظف</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-700">نوع الإجازة</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-700">من تاريخ</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-700">إلى تاريخ</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-700">عدد الأيام</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-700">الحالة</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-700 no-print">إجراءات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="leave in filtered" :key="leave.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-bold text-xs flex-shrink-0">
                    {{ employeeName(leave.employee_id)?.charAt(0) ?? '؟' }}
                  </div>
                  <span class="font-medium text-gray-900">{{ employeeName(leave.employee_id) ?? `#${leave.employee_id}` }}</span>
                </div>
              </td>
              <td class="px-4 py-3">
                <span :class="leaveTypeBadge(leave.leave_type)" class="px-2 py-0.5 rounded-full text-xs font-medium">
                  {{ leaveTypeLabel(leave.leave_type) }}
                </span>
              </td>
              <td class="px-4 py-3 text-gray-600 text-xs">{{ formatDate(leave.start_date) }}</td>
              <td class="px-4 py-3 text-gray-600 text-xs">{{ formatDate(leave.end_date) }}</td>
              <td class="px-4 py-3 text-center font-semibold text-gray-700">{{ leave.days_count ?? calcDays(leave.start_date, leave.end_date) }}</td>
              <td class="px-4 py-3">
                <span :class="statusBadge(leave.status)" class="px-2 py-0.5 rounded-full text-xs font-medium">
                  {{ statusLabel(leave.status) }}
                </span>
              </td>
              <td class="px-4 py-3 no-print">
                <div v-if="leave.status === 'pending'" class="flex items-center gap-2">
                  <button :disabled="actionId === leave.id"
                          class="text-xs bg-green-600 text-white px-3 py-1 rounded-lg hover:bg-green-700 disabled:opacity-50"
                          data-smart-tip="اعتماد الإجازة وتحديث الرصيد التشغيلي"
                          @click="updateStatus(leave, 'approved')"
                  >
                    قبول
                  </button>
                  <button :disabled="actionId === leave.id"
                          class="text-xs bg-red-100 text-red-700 px-3 py-1 rounded-lg hover:bg-red-200 disabled:opacity-50"
                          data-smart-tip="رفض الطلب مع إشعار الموظف"
                          @click="updateStatus(leave, 'rejected')"
                  >
                    رفض
                  </button>
                </div>
                <span v-else class="text-gray-300 text-xs">—</span>
              </td>
            </tr>
            <tr v-if="!filtered.length">
              <td colspan="7" class="text-center py-10 text-gray-400">لا توجد طلبات إجازة</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal: طلب إجازة جديدة -->
    <div v-if="showModal" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="closeModal">
      <div class="bg-white rounded-2xl w-full max-w-lg shadow-xl">
        <div class="flex items-center justify-between px-6 py-4 border-b">
          <h3 class="font-bold text-lg">طلب إجازة جديدة</h3>
          <button class="text-gray-400 hover:text-gray-700" @click="closeModal"><XMarkIcon class="w-5 h-5" /></button>
        </div>
        <form class="p-6 space-y-4" @submit.prevent="save">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الموظف *</label>
            <select v-model="form.employee_id" required class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
              <option value="">اختر موظفاً</option>
              <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.full_name || e.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">نوع الإجازة *</label>
            <select v-model="form.leave_type" required class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
              <option value="">اختر النوع</option>
              <option value="annual">سنوية</option>
              <option value="sick">مرضية</option>
              <option value="emergency">طارئة</option>
              <option value="unpaid">بدون راتب</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">نطاق الإجازة *</label>
              <SmartDatePicker
                mode="range"
                :from-value="form.start_date"
                :to-value="form.end_date"
                @change="onLeaveRangeChange"
              />
            </div>
          </div>
          <div v-if="form.start_date && form.end_date" class="flex items-center gap-2 text-sm text-gray-600 bg-gray-50 rounded-lg px-3 py-2">
            <CalendarDaysIcon class="w-4 h-4 text-primary-500" />
            <span>عدد الأيام: <strong class="text-primary-700">{{ calcDays(form.start_date, form.end_date) }}</strong></span>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">سبب الإجازة</label>
            <textarea v-model="form.reason" rows="3" placeholder="اكتب سبب الإجازة..."
                      class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none"
            ></textarea>
          </div>
          <div v-if="modalError" class="text-red-600 text-sm bg-red-50 rounded-lg p-3">{{ modalError }}</div>
          <div class="flex gap-3 justify-end pt-1">
            <button type="button" class="px-4 py-2 border rounded-lg text-sm text-gray-700 hover:bg-gray-50" @click="closeModal">إلغاء</button>
            <button type="submit" :disabled="saving"
                    class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50"
            >
              {{ saving ? 'جاري الحفظ...' : 'إرسال الطلب' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { PlusIcon, XMarkIcon, CalendarDaysIcon } from '@heroicons/vue/24/outline'
import { useApi } from '@/composables/useApi'
import SmartDatePicker from '@/components/ui/SmartDatePicker.vue'
import { printDocument } from '@/composables/useAppPrint'

const { get, post } = useApi()

const leaves = ref<any[]>([])
const employees = ref<any[]>([])
const loading = ref(true)
const filterEmployee = ref('')
const filterStatus = ref('')
const quickTab = ref<'all'|'pending'|'approved'>('all')
const showModal = ref(false)
const saving = ref(false)
const modalError = ref('')
const actionId = ref<number | null>(null)

const form = ref({ employee_id: '', leave_type: '', start_date: '', end_date: '', reason: '' })

const filtered = computed(() =>
  leaves.value.filter(l =>
    (quickTab.value === 'all' || l.status === quickTab.value) &&
    (!filterEmployee.value || l.employee_id == filterEmployee.value) &&
    (!filterStatus.value || l.status === filterStatus.value)
  )
)

const stats = computed(() => ({
  pending: leaves.value.filter(l => l.status === 'pending').length,
  approved: leaves.value.filter(l => l.status === 'approved').length,
  rejected: leaves.value.filter(l => l.status === 'rejected').length,
  totalDays: leaves.value.reduce((sum, l) => sum + (l.days_count ?? calcDays(l.start_date, l.end_date)), 0),
}))

function employeeName(id: any) {
  const emp = employees.value.find((e) => e.id == id)
  return emp?.full_name || emp?.name || null
}

function calcDays(start: string, end: string): number {
  if (!start || !end) return 0
  const diff = new Date(end).getTime() - new Date(start).getTime()
  return Math.max(1, Math.round(diff / 86400000) + 1)
}

function formatDate(d: string) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('ar-SA')
}

function leaveTypeLabel(t: string) {
  return { annual: 'سنوية', sick: 'مرضية', emergency: 'طارئة', unpaid: 'بدون راتب' }[t] ?? t
}

function leaveTypeBadge(t: string) {
  return {
    annual: 'bg-blue-100 text-blue-700',
    sick: 'bg-orange-100 text-orange-700',
    emergency: 'bg-red-100 text-red-700',
    unpaid: 'bg-gray-100 text-gray-600',
  }[t] ?? 'bg-gray-100 text-gray-600'
}

function statusLabel(s: string) {
  return { pending: 'معلقة', approved: 'مقبولة', rejected: 'مرفوضة' }[s] ?? s
}

function statusBadge(s: string) {
  return {
    pending: 'bg-yellow-100 text-yellow-700',
    approved: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
  }[s] ?? 'bg-gray-100 text-gray-600'
}

function openNew() {
  form.value = { employee_id: '', leave_type: '', start_date: '', end_date: '', reason: '' }
  modalError.value = ''
  showModal.value = true
}

function onLeaveRangeChange(val: { from: string; to: string }) {
  form.value.start_date = val.from
  form.value.end_date = val.to
}

function closeModal() {
  showModal.value = false
  modalError.value = ''
}

async function load() {
  loading.value = true
  try {
    const [l, e] = await Promise.all([get('/governance/leaves'), get('/workshop/employees')])
    leaves.value = l?.data ?? l ?? []
    employees.value = e?.data ?? e ?? []
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  modalError.value = ''
  try {
    await post('/governance/leaves', form.value)
    await load()
    closeModal()
  } catch (e: any) {
    modalError.value = e?.response?.data?.message ?? 'حدث خطأ'
  } finally {
    saving.value = false
  }
}

async function updateStatus(leave: any, status: string) {
  actionId.value = leave.id
  try {
    await post(`/governance/leaves/${leave.id}/${status}`)
    leave.status = status
  } catch {
    // silent
  } finally {
    actionId.value = null
  }
}

async function printPage() {
  await printDocument()
}

onMounted(load)
</script>
