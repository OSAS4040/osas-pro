<template>
  <div class="space-y-6" dir="rtl">
    <div class="flex items-center justify-between flex-wrap gap-2">
      <h2 class="text-2xl font-bold text-gray-900">إدارة الموظفين</h2>
      <div class="flex items-center gap-2 flex-wrap">
        <ExcelImport
          endpoint="/api/v1/employees/import"
          template-url="/templates/employees_template.csv"
          label="استيراد Excel"
          title="استيراد موظفين من Excel"
          @imported="load"
        />
        <button @click="showModal = true"
          class="flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm font-medium">
          <PlusIcon class="w-4 h-4" />
          موظف جديد
        </button>
      </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div v-for="s in stats" :key="s.label" class="bg-white rounded-xl p-4 border border-gray-200 text-center">
        <p class="text-2xl font-bold" :class="s.color">{{ s.value }}</p>
        <p class="text-xs text-gray-500 mt-1">{{ s.label }}</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl border border-gray-200 p-4 flex gap-3 flex-wrap">
      <input v-model="search" type="text" placeholder="بحث بالاسم أو التخصص..."
        class="flex-1 min-w-[200px] border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
      <select v-model="filterStatus" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none">
        <option value="">كل الحالات</option>
        <option value="active">نشط</option>
        <option value="inactive">غير نشط</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
    </div>

    <!-- Table -->
    <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
          <tr>
            <th class="px-4 py-3 text-right font-semibold text-gray-700">الموظف</th>
            <th class="px-4 py-3 text-right font-semibold text-gray-700">التخصص</th>
            <th class="px-4 py-3 text-right font-semibold text-gray-700">الراتب</th>
            <th class="px-4 py-3 text-right font-semibold text-gray-700">الحالة</th>
            <th class="px-4 py-3 text-right font-semibold text-gray-700">إجراءات</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="emp in filtered" :key="emp.id" class="hover:bg-gray-50 transition-colors">
            <td class="px-4 py-3">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-bold text-sm">
                  {{ emp.full_name?.charAt(0) }}
                </div>
                <div>
                  <p class="font-medium text-gray-900">{{ emp.full_name }}</p>
                  <p class="text-xs text-gray-400">{{ emp.employee_code }}</p>
                </div>
              </div>
            </td>
            <td class="px-4 py-3 text-gray-600">{{ emp.specialization || '—' }}</td>
            <td class="px-4 py-3 text-gray-600">{{ formatNum(emp.base_salary) }} ر.س</td>
            <td class="px-4 py-3">
              <span :class="emp.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                class="px-2 py-0.5 rounded-full text-xs font-medium">
                {{ emp.is_active ? 'نشط' : 'موقوف' }}
              </span>
            </td>
            <td class="px-4 py-3">
              <button @click="openEdit(emp)" class="text-primary-600 hover:text-primary-800 text-xs font-medium">تعديل</button>
            </td>
          </tr>
          <tr v-if="!filtered.length">
            <td colspan="5" class="text-center py-10 text-gray-400">لا يوجد موظفون</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl w-full max-w-lg shadow-xl">
        <div class="flex items-center justify-between px-6 py-4 border-b">
          <h3 class="font-bold text-lg">{{ editId ? 'تعديل موظف' : 'موظف جديد' }}</h3>
          <button @click="closeModal" class="text-gray-400 hover:text-gray-700"><XMarkIcon class="w-5 h-5" /></button>
        </div>
        <form @submit.prevent="save" class="p-6 space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">الاسم الكامل *</label>
              <input v-model="form.full_name" required class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">كود الموظف</label>
              <input v-model="form.employee_code" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">التخصص</label>
              <input v-model="form.specialization" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">الراتب الأساسي</label>
              <input v-model.number="form.base_salary" type="number" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">رقم الجوال</label>
              <input v-model="form.phone" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
              <input v-model="form.email" type="email" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
            </div>
          </div>
          <div v-if="modalError" class="text-red-600 text-sm bg-red-50 rounded-lg p-3">{{ modalError }}</div>
          <div class="flex gap-3 justify-end pt-2">
            <button type="button" @click="closeModal" class="px-4 py-2 border rounded-lg text-sm text-gray-700 hover:bg-gray-50">إلغاء</button>
            <button type="submit" :disabled="saving"
              class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50">
              {{ saving ? 'جاري الحفظ...' : 'حفظ' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import ExcelImport from '@/components/ExcelImport.vue'
import apiClient from '@/lib/apiClient'


const employees = ref<any[]>([])
const loading = ref(true)
const search = ref('')
const filterStatus = ref('')
const showModal = ref(false)
const editId = ref<number | null>(null)
const saving = ref(false)
const modalError = ref('')

const form = ref({ full_name: '', employee_code: '', specialization: '', base_salary: 0, phone: '', email: '' })

const filtered = computed(() =>
  employees.value.filter(e => {
    const q = search.value.toLowerCase()
    const matchQ = !q || e.full_name?.toLowerCase().includes(q) || e.specialization?.toLowerCase().includes(q)
    const matchS = !filterStatus.value || (filterStatus.value === 'active' ? e.is_active : !e.is_active)
    return matchQ && matchS
  })
)

const stats = computed(() => [
  { label: 'إجمالي الموظفين', value: employees.value.length, color: 'text-primary-600' },
  { label: 'نشط', value: employees.value.filter(e => e.is_active).length, color: 'text-green-600' },
  { label: 'موقوف', value: employees.value.filter(e => !e.is_active).length, color: 'text-red-600' },
  { label: 'متخصصون', value: employees.value.filter(e => e.specialization).length, color: 'text-blue-600' },
])

function formatNum(n: number) { return Number(n || 0).toLocaleString('ar-SA') }

async function load() {
  loading.value = true
  try {
    const res = await apiClient.get('/workshop/employees')
    employees.value = res.data?.data ?? []
  } finally { loading.value = false }
}

function openEdit(emp: any) {
  editId.value = emp.id
  Object.assign(form.value, emp)
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editId.value = null
  modalError.value = ''
  form.value = { full_name: '', employee_code: '', specialization: '', base_salary: 0, phone: '', email: '' }
}

async function save() {
  saving.value = true
  modalError.value = ''
  try {
    if (editId.value) {
      await apiClient.put(`/workshop/employees/${editId.value}`, form.value)
    } else {
      await apiClient.post('/workshop/employees', form.value)
    }
    await load()
    closeModal()
  } catch (e: any) {
    modalError.value = e?.response?.data?.message ?? 'حدث خطأ'
  } finally { saving.value = false }
}

onMounted(load)
</script>
