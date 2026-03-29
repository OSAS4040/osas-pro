<template>
  <div class="space-y-6" dir="rtl">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h2 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
        <ClipboardDocumentCheckIcon class="w-6 h-6 text-primary-600" />
        إدارة المهام
      </h2>
      <button @click="openAddModal"
        class="flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm font-medium">
        <PlusIcon class="w-4 h-4" />
        مهمة جديدة
      </button>
    </div>

    <!-- Stats Bar -->
    <div class="grid grid-cols-3 md:grid-cols-6 gap-3">
      <div
        v-for="s in stats" :key="s.status"
        class="bg-white rounded-xl p-3 border border-gray-200 text-center cursor-pointer hover:border-primary-300 transition-colors"
        :class="{ 'ring-2 ring-primary-400 border-primary-400': filterStatus === s.status }"
        @click="filterStatus = filterStatus === s.status ? '' : s.status"
      >
        <p class="text-2xl font-bold" :class="s.color">{{ s.value }}</p>
        <p class="text-xs text-gray-500 mt-0.5">{{ s.label }}</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl border border-gray-200 p-4 flex flex-wrap gap-3 items-center">
      <div class="flex items-center gap-2 flex-1 min-w-[180px]">
        <FunnelIcon class="w-4 h-4 text-gray-400 flex-shrink-0" />
        <select v-model="filterEmployee" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
          <option value="">كل الموظفين</option>
          <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.full_name }}</option>
        </select>
      </div>
      <div class="flex items-center gap-2 flex-1 min-w-[160px]">
        <select v-model="filterPriority" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
          <option value="">كل الأولويات</option>
          <option value="high">عالية</option>
          <option value="medium">متوسطة</option>
          <option value="low">منخفضة</option>
        </select>
      </div>
      <button
        v-if="filterEmployee || filterPriority || filterStatus"
        @click="filterEmployee = ''; filterPriority = ''; filterStatus = ''"
        class="text-xs text-gray-500 hover:text-red-500 flex items-center gap-1 transition-colors"
      >
        <XMarkIcon class="w-4 h-4" /> مسح الفلاتر
      </button>

      <!-- View Toggle -->
      <div class="flex items-center gap-1 bg-gray-100 rounded-lg p-1 mr-auto">
        <button
          @click="viewMode = 'kanban'"
          class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors"
          :class="viewMode === 'kanban' ? 'bg-white text-primary-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
        >
          <ViewColumnsIcon class="w-4 h-4 inline-block ml-1" />كانبان
        </button>
        <button
          @click="viewMode = 'list'"
          class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors"
          :class="viewMode === 'list' ? 'bg-white text-primary-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
        >
          <ListBulletIcon class="w-4 h-4 inline-block ml-1" />قائمة
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-16">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
    </div>

    <!-- Kanban View -->
    <div v-else-if="viewMode === 'kanban'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
      <div
        v-for="col in columns" :key="col.status"
        class="bg-gray-50 rounded-xl border border-gray-200 flex flex-col min-h-[320px]"
      >
        <!-- Column Header -->
        <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between rounded-t-xl" :class="col.headerBg">
          <span class="font-semibold text-sm" :class="col.headerText">{{ col.label }}</span>
          <span class="px-2 py-0.5 rounded-full text-xs font-bold" :class="col.badge">
            {{ tasksByStatus(col.status).length }}
          </span>
        </div>
        <!-- Cards -->
        <div class="p-3 space-y-2 flex-1 overflow-y-auto max-h-[600px]">
          <div
            v-for="task in tasksByStatus(col.status)" :key="task.id"
            class="bg-white rounded-lg border border-gray-200 p-3 shadow-sm hover:shadow-md transition-shadow cursor-default"
          >
            <!-- Title + Priority -->
            <div class="flex items-start justify-between gap-2 mb-2">
              <p class="text-sm font-medium text-gray-900 flex-1 leading-snug">{{ task.title }}</p>
              <span :class="priorityClass(task.priority)" class="text-xs px-2 py-0.5 rounded-full font-medium flex-shrink-0 whitespace-nowrap">
                {{ priorityLabel(task.priority) }}
              </span>
            </div>

            <!-- Work Order Link -->
            <div v-if="task.work_order_id || task.work_order_number" class="flex items-center gap-1 mb-2">
              <WrenchScrewdriverIcon class="w-3 h-3 text-gray-400" />
              <span class="text-xs text-primary-600 font-medium">
                أمر عمل #{{ task.work_order_number ?? task.work_order_id }}
              </span>
            </div>

            <!-- Due Date -->
            <div v-if="task.due_date" class="flex items-center gap-1 mb-2" :class="isOverdue(task) ? 'text-red-500' : 'text-gray-400'">
              <CalendarDaysIcon class="w-3 h-3" />
              <span class="text-xs">{{ formatDate(task.due_date) }}</span>
              <span v-if="isOverdue(task)" class="text-xs font-semibold">(متأخر)</span>
            </div>

            <!-- Assignee + Actions -->
            <div class="flex items-center justify-between mt-2 gap-2">
              <!-- Assignee Avatar -->
              <div class="flex items-center gap-1.5 min-w-0">
                <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 text-white text-xs font-bold"
                  :class="avatarColor(task.assignee_name)">
                  {{ avatarInitial(task.assignee_name) }}
                </div>
                <span class="text-xs text-gray-500 truncate">{{ task.assignee_name ?? 'غير محدد' }}</span>
              </div>
              <!-- Quick Actions -->
              <div class="flex gap-1 flex-shrink-0">
                <button
                  v-if="col.nextStatus"
                  @click="advance(task, col.nextStatus)"
                  :disabled="advancing === task.id"
                  class="text-xs px-2 py-1 rounded-md font-medium transition-colors bg-primary-50 text-primary-700 hover:bg-primary-100 disabled:opacity-50"
                  :title="'تقديم إلى: ' + col.nextLabel"
                >
                  {{ col.nextLabel }}
                </button>
                <button
                  v-if="task.status !== 'cancelled' && task.status !== 'completed'"
                  @click="cancelTask(task)"
                  :disabled="advancing === task.id"
                  class="text-xs px-2 py-1 rounded-md font-medium transition-colors bg-red-50 text-red-600 hover:bg-red-100 disabled:opacity-50"
                  title="إلغاء المهمة"
                >
                  إلغاء
                </button>
              </div>
            </div>
          </div>
          <p v-if="!tasksByStatus(col.status).length" class="text-xs text-gray-400 text-center py-6">لا توجد مهام</p>
        </div>
      </div>
    </div>

    <!-- List View -->
    <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
          <tr>
            <th class="px-4 py-3 text-right font-semibold text-gray-700">المهمة</th>
            <th class="px-4 py-3 text-right font-semibold text-gray-700">الأولوية</th>
            <th class="px-4 py-3 text-right font-semibold text-gray-700">المسؤول</th>
            <th class="px-4 py-3 text-right font-semibold text-gray-700">تاريخ الاستحقاق</th>
            <th class="px-4 py-3 text-right font-semibold text-gray-700">الحالة</th>
            <th class="px-4 py-3 text-right font-semibold text-gray-700">أمر العمل</th>
            <th class="px-4 py-3 text-right font-semibold text-gray-700">إجراءات</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-if="!filteredTasks.length">
            <td colspan="7" class="text-center py-10 text-gray-400 text-sm">لا توجد مهام</td>
          </tr>
          <tr v-for="task in filteredTasks" :key="task.id" class="hover:bg-gray-50 transition-colors">
            <td class="px-4 py-3">
              <p class="font-medium text-gray-900">{{ task.title }}</p>
              <p v-if="task.description" class="text-xs text-gray-400 mt-0.5 line-clamp-1">{{ task.description }}</p>
            </td>
            <td class="px-4 py-3">
              <span :class="priorityClass(task.priority)" class="text-xs px-2 py-0.5 rounded-full font-medium">
                {{ priorityLabel(task.priority) }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                  :class="avatarColor(task.assignee_name)">
                  {{ avatarInitial(task.assignee_name) }}
                </div>
                <span class="text-sm text-gray-700">{{ task.assignee_name ?? 'غير محدد' }}</span>
              </div>
            </td>
            <td class="px-4 py-3">
              <span v-if="task.due_date" class="text-sm" :class="isOverdue(task) ? 'text-red-500 font-medium' : 'text-gray-600'">
                {{ formatDate(task.due_date) }}
              </span>
              <span v-else class="text-gray-400 text-sm">—</span>
            </td>
            <td class="px-4 py-3">
              <span :class="statusClass(task.status)" class="text-xs px-2 py-0.5 rounded-full font-medium">
                {{ statusLabel(task.status) }}
              </span>
            </td>
            <td class="px-4 py-3">
              <span v-if="task.work_order_id || task.work_order_number" class="text-xs text-primary-600 font-medium">
                #{{ task.work_order_number ?? task.work_order_id }}
              </span>
              <span v-else class="text-gray-400 text-sm">—</span>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-1.5">
                <button
                  v-if="nextStatus(task.status)"
                  @click="advance(task, nextStatus(task.status)!)"
                  :disabled="advancing === task.id"
                  class="text-xs px-2 py-1 rounded-md bg-primary-50 text-primary-700 hover:bg-primary-100 font-medium disabled:opacity-50 transition-colors"
                >
                  {{ nextLabel(task.status) }}
                </button>
                <button
                  v-if="task.status !== 'cancelled' && task.status !== 'completed'"
                  @click="cancelTask(task)"
                  :disabled="advancing === task.id"
                  class="text-xs px-2 py-1 rounded-md bg-red-50 text-red-600 hover:bg-red-100 font-medium disabled:opacity-50 transition-colors"
                >
                  إلغاء
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add Task Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="showModal = false">
      <div class="bg-white rounded-2xl w-full max-w-lg shadow-xl" dir="rtl">
        <div class="flex items-center justify-between px-6 py-4 border-b">
          <h3 class="font-bold text-lg text-gray-900">مهمة جديدة</h3>
          <button @click="showModal = false" class="text-gray-400 hover:text-gray-600 transition-colors">
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>
        <form @submit.prevent="save" class="p-6 space-y-4 max-h-[75vh] overflow-y-auto">
          <!-- Title -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">العنوان <span class="text-red-500">*</span></label>
            <input
              v-model="form.title"
              required
              placeholder="عنوان المهمة"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            />
          </div>
          <!-- Description -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الوصف</label>
            <textarea
              v-model="form.description"
              rows="3"
              placeholder="وصف المهمة..."
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none"
            ></textarea>
          </div>
          <!-- Priority + Assignee -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">الأولوية</label>
              <select v-model="form.priority" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                <option value="low">منخفضة</option>
                <option value="medium">متوسطة</option>
                <option value="high">عالية</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">المسؤول</label>
              <select v-model="form.assignee_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                <option value="">اختر موظفاً</option>
                <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.full_name }}</option>
              </select>
            </div>
          </div>
          <!-- Due Date -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ الاستحقاق</label>
            <input
              v-model="form.due_date"
              type="date"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            />
          </div>
          <!-- Notes -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ملاحظات</label>
            <textarea
              v-model="form.notes"
              rows="2"
              placeholder="ملاحظات إضافية..."
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none"
            ></textarea>
          </div>
          <!-- Related Entity (optional) -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الكيان المرتبط (اختياري)</label>
            <input
              v-model="form.related_entity"
              placeholder="مثل: أمر عمل، عميل..."
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            />
          </div>
          <!-- Error -->
          <div v-if="modalError" class="text-red-600 text-sm bg-red-50 rounded-lg p-3 border border-red-200">
            {{ modalError }}
          </div>
          <!-- Actions -->
          <div class="flex gap-3 justify-end pt-1">
            <button
              type="button"
              @click="showModal = false"
              class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors"
            >
              إلغاء
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50 transition-colors"
            >
              {{ saving ? 'جاري الحفظ...' : 'إنشاء المهمة' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import {
  PlusIcon,
  XMarkIcon,
  ClipboardDocumentCheckIcon,
  FunnelIcon,
  CalendarDaysIcon,
  WrenchScrewdriverIcon,
  ViewColumnsIcon,
  ListBulletIcon,
} from '@heroicons/vue/24/outline'
import { useApi } from '@/composables/useApi'
import apiClient from '@/lib/apiClient'

const api = useApi()

// ───── State ─────
const tasks = ref<any[]>([])
const employees = ref<any[]>([])
const loading = ref(true)
const advancing = ref<number | null>(null)

const viewMode = ref<'kanban' | 'list'>('kanban')
const filterStatus = ref('')
const filterEmployee = ref('')
const filterPriority = ref('')

const showModal = ref(false)
const saving = ref(false)
const modalError = ref('')

const defaultForm = () => ({
  title: '',
  description: '',
  priority: 'medium',
  assignee_id: '',
  due_date: '',
  notes: '',
  related_entity: '',
})
const form = ref(defaultForm())

// ───── Columns ─────
const columns = [
  {
    status: 'pending',
    label: 'للتنفيذ',
    badge: 'bg-yellow-100 text-yellow-700',
    headerBg: 'bg-yellow-50',
    headerText: 'text-yellow-800',
    nextStatus: 'in_progress',
    nextLabel: 'بدء',
  },
  {
    status: 'in_progress',
    label: 'جاري',
    badge: 'bg-blue-100 text-blue-700',
    headerBg: 'bg-blue-50',
    headerText: 'text-blue-800',
    nextStatus: 'review',
    nextLabel: 'مراجعة',
  },
  {
    status: 'review',
    label: 'مراجعة',
    badge: 'bg-purple-100 text-purple-700',
    headerBg: 'bg-purple-50',
    headerText: 'text-purple-800',
    nextStatus: 'completed',
    nextLabel: 'إتمام',
  },
  {
    status: 'completed',
    label: 'مكتمل',
    badge: 'bg-green-100 text-green-700',
    headerBg: 'bg-green-50',
    headerText: 'text-green-800',
    nextStatus: null,
    nextLabel: '',
  },
  {
    status: 'cancelled',
    label: 'ملغى',
    badge: 'bg-red-100 text-red-700',
    headerBg: 'bg-red-50',
    headerText: 'text-red-800',
    nextStatus: null,
    nextLabel: '',
  },
]

const statusFlowNext: Record<string, string> = {
  pending: 'in_progress',
  in_progress: 'review',
  review: 'completed',
}
const statusFlowLabel: Record<string, string> = {
  pending: 'بدء',
  in_progress: 'مراجعة',
  review: 'إتمام',
}

function nextStatus(status: string): string | null {
  return statusFlowNext[status] ?? null
}
function nextLabel(status: string): string {
  return statusFlowLabel[status] ?? ''
}

// ───── Stats ─────
const stats = computed(() => [
  { label: 'الكل',     status: '',            value: tasks.value.length,                                            color: 'text-gray-700' },
  { label: 'للتنفيذ', status: 'pending',      value: tasks.value.filter(t => t.status === 'pending').length,      color: 'text-yellow-600' },
  { label: 'جاري',    status: 'in_progress',  value: tasks.value.filter(t => t.status === 'in_progress').length,  color: 'text-blue-600' },
  { label: 'مراجعة',  status: 'review',       value: tasks.value.filter(t => t.status === 'review').length,       color: 'text-purple-600' },
  { label: 'مكتمل',   status: 'completed',    value: tasks.value.filter(t => t.status === 'completed').length,    color: 'text-green-600' },
  { label: 'ملغى',    status: 'cancelled',    value: tasks.value.filter(t => t.status === 'cancelled').length,    color: 'text-red-500' },
])

// ───── Filtered ─────
const filteredTasks = computed(() => {
  return tasks.value.filter(t => {
    if (filterStatus.value && t.status !== filterStatus.value) return false
    if (filterEmployee.value && String(t.assignee_id) !== String(filterEmployee.value)) return false
    if (filterPriority.value && t.priority !== filterPriority.value) return false
    return true
  })
})

function tasksByStatus(status: string) {
  return filteredTasks.value.filter(t => t.status === status)
}

// ───── Helpers ─────
function priorityLabel(p: string): string {
  return { high: 'عالية', medium: 'متوسطة', low: 'منخفضة' }[p] ?? p
}

function priorityClass(p: string): string {
  return (
    { high: 'bg-red-100 text-red-700', medium: 'bg-yellow-100 text-yellow-700', low: 'bg-gray-100 text-gray-600' }[p] ??
    'bg-gray-100 text-gray-600'
  )
}

function statusLabel(s: string): string {
  return (
    { pending: 'للتنفيذ', in_progress: 'جاري', review: 'مراجعة', completed: 'مكتمل', cancelled: 'ملغى' }[s] ?? s
  )
}

function statusClass(s: string): string {
  return (
    {
      pending:     'bg-yellow-100 text-yellow-700',
      in_progress: 'bg-blue-100 text-blue-700',
      review:      'bg-purple-100 text-purple-700',
      completed:   'bg-green-100 text-green-700',
      cancelled:   'bg-red-100 text-red-700',
    }[s] ?? 'bg-gray-100 text-gray-600'
  )
}

function formatDate(dateStr: string): string {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleDateString('ar-SA', { year: 'numeric', month: 'short', day: 'numeric' })
}

function isOverdue(task: any): boolean {
  if (!task.due_date) return false
  if (task.status === 'completed' || task.status === 'cancelled') return false
  return new Date(task.due_date) < new Date()
}

const avatarColors = [
  'bg-blue-500', 'bg-emerald-500', 'bg-violet-500', 'bg-amber-500',
  'bg-rose-500', 'bg-cyan-500', 'bg-indigo-500', 'bg-teal-500',
]

function avatarColor(name?: string): string {
  if (!name) return 'bg-gray-400'
  let hash = 0
  for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash)
  return avatarColors[Math.abs(hash) % avatarColors.length]
}

function avatarInitial(name?: string): string {
  if (!name) return '?'
  return name.trim()[0] ?? '?'
}

// ───── API ─────
async function load() {
  loading.value = true
  try {
    const [tasksRes, empsRes] = await Promise.all([
      api.get('/workshop/tasks'),
      api.get('/workshop/employees'),
    ])
    tasks.value = tasksRes?.data ?? tasksRes ?? []
    employees.value = empsRes?.data ?? empsRes ?? []
  } catch {
    tasks.value = []
    employees.value = []
  } finally {
    loading.value = false
  }
}

async function advance(task: any, toStatus: string) {
  advancing.value = task.id
  try {
    await apiClient.patch(`/workshop/tasks/${task.id}/status`, { status: toStatus })
    task.status = toStatus
  } catch {
    // silent
  } finally {
    advancing.value = null
  }
}

async function cancelTask(task: any) {
  advancing.value = task.id
  try {
    await apiClient.patch(`/workshop/tasks/${task.id}/status`, { status: 'cancelled' })
    task.status = 'cancelled'
  } catch {
    // silent
  } finally {
    advancing.value = null
  }
}

function openAddModal() {
  form.value = defaultForm()
  modalError.value = ''
  showModal.value = true
}

async function save() {
  saving.value = true
  modalError.value = ''
  try {
    const payload: Record<string, any> = {
      title: form.value.title,
      description: form.value.description,
      priority: form.value.priority,
    }
    if (form.value.assignee_id) payload.assignee_id = form.value.assignee_id
    if (form.value.due_date) payload.due_date = form.value.due_date
    if (form.value.notes) payload.notes = form.value.notes
    if (form.value.related_entity) payload.related_entity = form.value.related_entity

    await api.post('/workshop/tasks', payload)
    await load()
    showModal.value = false
    form.value = defaultForm()
  } catch (e: any) {
    modalError.value = e?.response?.data?.message ?? 'حدث خطأ أثناء الحفظ'
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>
