<template>
  <section class="space-y-4">
    <header class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2 class="text-lg font-bold text-gray-900 dark:text-slate-100">حسابات فريق العمل</h2>
        <p class="text-xs text-gray-500 dark:text-slate-400">تحديث البيانات، إضافة الموظفين، تحديد الأدوار والصلاحيات، الحذف والتنشيط.</p>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-gray-200" :disabled="loading" @click="load">تحديث</button>
        <button type="button" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-primary-600 text-white hover:bg-primary-700" @click="openCreate">إضافة موظف</button>
      </div>
    </header>

    <section class="rounded-2xl border border-gray-100 bg-white p-3 dark:border-slate-700 dark:bg-slate-800">
      <div class="grid gap-2 md:grid-cols-3">
        <div>
          <label class="mb-1 block text-[10px] text-gray-500">بحث سريع</label>
          <input v-model.trim="searchQuery" type="search" placeholder="الاسم أو البريد أو الجوال" class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs dark:border-slate-600 dark:bg-slate-900" />
        </div>
        <div>
          <label class="mb-1 block text-[10px] text-gray-500">الدور</label>
          <select v-model="roleFilter" class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs dark:border-slate-600 dark:bg-slate-900">
            <option value="">الكل</option>
            <option v-for="r in roleOptions" :key="r.value" :value="r.value">{{ r.label }}</option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-[10px] text-gray-500">الحالة</label>
          <select v-model="statusFilter" class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs dark:border-slate-600 dark:bg-slate-900">
            <option value="">الكل</option>
            <option value="active">نشط</option>
            <option value="inactive">غير نشط</option>
          </select>
        </div>
      </div>
    </section>

    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 overflow-hidden">
      <div v-if="loading" class="py-10 text-center text-gray-400 text-sm">جارٍ التحميل...</div>
      <div v-else-if="errorMessage" class="py-10 text-center text-red-500 text-sm px-4">{{ errorMessage }}</div>
      <div v-else class="space-y-2">
        <div class="px-4 pt-3 flex items-center justify-between text-xs text-gray-500">
          <span>عرض {{ pageStart }} - {{ pageEnd }} من {{ totalRows }}</span>
          <div class="flex items-center gap-2">
            <button type="button" class="px-2 py-1 rounded border border-gray-200 disabled:opacity-50" :disabled="currentPage <= 1" @click="currentPage -= 1; load()">السابق</button>
            <span>صفحة {{ currentPage }} / {{ totalPages }}</span>
            <button type="button" class="px-2 py-1 rounded border border-gray-200 disabled:opacity-50" :disabled="currentPage >= totalPages" @click="currentPage += 1; load()">التالي</button>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-slate-900/70 text-right text-xs text-gray-500">
              <tr>
                <th class="px-4 py-3 font-medium">الاسم</th>
                <th class="px-4 py-3 font-medium">البريد</th>
                <th class="px-4 py-3 font-medium">الجوال</th>
                <th class="px-4 py-3 font-medium">الدور</th>
                <th class="px-4 py-3 font-medium">الحالة</th>
                <th class="px-4 py-3 font-medium">الإجراءات</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
              <tr v-for="u in rows" :key="u.id">
                <td class="px-4 py-3 font-medium text-gray-800 dark:text-slate-100">{{ u.name || '—' }}</td>
                <td class="px-4 py-3 text-xs text-gray-500 font-mono">{{ u.email || '—' }}</td>
                <td class="px-4 py-3 text-xs text-gray-500">{{ u.phone || '—' }}</td>
                <td class="px-4 py-3 text-xs text-gray-600 dark:text-slate-300">
                  <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-700 dark:bg-slate-700 dark:text-slate-200">{{ roleLabel(u.role) }}</span>
                </td>
                <td class="px-4 py-3">
                  <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="u.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'">
                    {{ u.is_active ? 'نشط' : 'غير نشط' }}
                  </span>
                </td>
                <td class="px-4 py-3 text-xs whitespace-nowrap">
                  <button type="button" class="text-primary-600 hover:underline ms-2" @click="openEdit(u)">تعديل</button>
                  <button type="button" class="text-amber-600 hover:underline ms-2" @click="toggleActive(u)">{{ u.is_active ? 'إيقاف' : 'تنشيط' }}</button>
                  <button type="button" class="text-red-600 hover:underline" @click="removeUser(u)">حذف</button>
                </td>
              </tr>
              <tr v-if="!rows.length">
                <td colspan="6" class="px-4 py-10 text-center text-gray-400 text-sm">لا توجد حسابات فريق حالياً.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="closeForm">
      <div class="w-full max-w-lg rounded-xl bg-white p-5 shadow-xl dark:bg-slate-800">
        <h3 class="mb-3 text-sm font-bold">{{ editId ? 'تعديل موظف' : 'إضافة موظف جديد' }}</h3>
        <div class="grid gap-2 md:grid-cols-2">
          <div class="md:col-span-2">
            <label class="mb-1 block text-[11px] text-gray-500">الاسم</label>
            <input v-model.trim="form.name" type="text" class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs dark:border-slate-600 dark:bg-slate-900" />
          </div>
          <div>
            <label class="mb-1 block text-[11px] text-gray-500">البريد</label>
            <input v-model.trim="form.email" :disabled="Boolean(editId)" type="email" class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs dark:border-slate-600 dark:bg-slate-900 disabled:opacity-60" />
          </div>
          <div>
            <label class="mb-1 block text-[11px] text-gray-500">الجوال</label>
            <input v-model.trim="form.phone" type="text" class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs dark:border-slate-600 dark:bg-slate-900" />
          </div>
          <div>
            <label class="mb-1 block text-[11px] text-gray-500">الدور</label>
            <select v-model="form.role" class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs dark:border-slate-600 dark:bg-slate-900">
              <option v-for="r in roleOptions" :key="r.value" :value="r.value">{{ r.label }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-[11px] text-gray-500">{{ editId ? 'كلمة المرور (اختياري)' : 'كلمة المرور' }}</label>
            <input v-model="form.password" type="password" class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs dark:border-slate-600 dark:bg-slate-900" />
          </div>
        </div>
        <div class="mt-3 space-y-2 rounded-xl border border-gray-100 p-3 text-xs dark:border-slate-700">
          <div class="font-semibold text-gray-700 dark:text-slate-200">الصلاحيات حسب الدور</div>
          <div class="text-gray-500 dark:text-slate-400">{{ rolePermissionsText(form.role) }}</div>
          <label class="flex items-center gap-2">
            <input v-model="form.is_active" type="checkbox" />
            <span>الحساب نشط</span>
          </label>
          <label v-if="!editId" class="flex items-center gap-2">
            <input v-model="form.send_welcome_notification" type="checkbox" />
            <span>إرسال رسالة SMS/واتساب تلقائيًا ببيانات الدخول</span>
          </label>
        </div>
        <p v-if="formError" class="mt-2 text-xs text-red-600">{{ formError }}</p>
        <div class="mt-4 flex justify-end gap-2">
          <button type="button" class="rounded-lg border px-3 py-1.5 text-xs" @click="closeForm">إلغاء</button>
          <button type="button" class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-60" :disabled="saving" @click="save">
            {{ saving ? 'جارٍ الحفظ...' : 'حفظ' }}
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue'
import apiClient from '@/lib/apiClient'
import { useToast } from '@/composables/useToast'
import { appConfirm } from '@/services/appConfirmDialog'

type TeamUserRow = {
  id: number
  name: string
  email: string
  phone?: string | null
  role: string
  is_active: boolean
}

const toast = useToast()
const rows = ref<TeamUserRow[]>([])
const loading = ref(true)
const saving = ref(false)
const errorMessage = ref('')
const formError = ref('')
const searchQuery = ref('')
const roleFilter = ref('')
const statusFilter = ref('')
const currentPage = ref(1)
const totalPages = ref(1)
const totalRows = ref(0)
const showForm = ref(false)
const editId = ref<number | null>(null)

const roleOptions = [
  { value: 'customer', label: 'مدير عميل' },
  { value: 'fleet_manager', label: 'مدير أسطول' },
  { value: 'fleet_contact', label: 'منسق العميل' },
  { value: 'viewer', label: 'مراقب' },
]

const form = reactive({
  name: '',
  email: '',
  phone: '',
  password: '',
  role: 'fleet_contact',
  is_active: true,
  send_welcome_notification: true,
})

const pageStart = ref(0)
const pageEnd = ref(0)

function roleLabel(role: string): string {
  return roleOptions.find((r) => r.value === role)?.label ?? role
}

function rolePermissionsText(role: string): string {
  if (role === 'customer') return 'إدارة كاملة لبوابة العميل وتقاريرها وحسابات الفريق.'
  if (role === 'fleet_manager') return 'اعتماد الطلبات والاطلاع على التقارير وإدارة الاستخدام المالي.'
  if (role === 'fleet_contact') return 'تشغيل يومي: إنشاء الطلبات ومتابعة المركبات والمحفظة.'
  return 'عرض البيانات فقط بدون تعديلات تشغيلية.'
}

function resetForm() {
  form.name = ''
  form.email = ''
  form.phone = ''
  form.password = ''
  form.role = 'fleet_contact'
  form.is_active = true
  form.send_welcome_notification = true
  formError.value = ''
}

async function load() {
  loading.value = true
  errorMessage.value = ''
  try {
    const params: Record<string, unknown> = { page: currentPage.value, per_page: 20 }
    if (searchQuery.value) params.search = searchQuery.value
    if (roleFilter.value) params.role = roleFilter.value
    if (statusFilter.value === 'active') params.is_active = true
    if (statusFilter.value === 'inactive') params.is_active = false

    const { data } = await apiClient.get('/customer-portal/team-users', { params })
    const payload = data?.data
    rows.value = Array.isArray(payload?.data) ? payload.data : []
    totalPages.value = Number(payload?.last_page || 1)
    totalRows.value = Number(payload?.total || rows.value.length)
    pageStart.value = totalRows.value ? (currentPage.value - 1) * 20 + 1 : 0
    pageEnd.value = Math.min(currentPage.value * 20, totalRows.value)
  } catch (e: any) {
    rows.value = []
    errorMessage.value = e?.response?.data?.message || 'تعذّر تحميل حسابات الفريق حالياً.'
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editId.value = null
  resetForm()
  showForm.value = true
}

function openEdit(u: TeamUserRow) {
  editId.value = u.id
  form.name = u.name || ''
  form.email = u.email || ''
  form.phone = u.phone || ''
  form.password = ''
  form.role = u.role || 'fleet_contact'
  form.is_active = Boolean(u.is_active)
  form.send_welcome_notification = false
  formError.value = ''
  showForm.value = true
}

function closeForm() {
  showForm.value = false
}

async function save() {
  formError.value = ''
  saving.value = true
  try {
    if (!editId.value) {
      await apiClient.post('/customer-portal/team-users', {
        name: form.name,
        email: form.email,
        phone: form.phone || null,
        password: form.password,
        role: form.role,
        is_active: form.is_active,
        send_welcome_notification: form.send_welcome_notification,
      })
      toast.success('تمت إضافة الموظف بنجاح')
    } else {
      const body: Record<string, unknown> = {
        name: form.name,
        phone: form.phone || null,
        role: form.role,
        is_active: form.is_active,
      }
      if (form.password) body.password = form.password
      await apiClient.put(`/customer-portal/team-users/${editId.value}`, body)
      toast.success('تم تحديث بيانات الموظف')
    }
    closeForm()
    await load()
  } catch (e: any) {
    const errs = e?.response?.data?.errors
    formError.value = errs && typeof errs === 'object' ? Object.values(errs).flat().join(' — ') : e?.response?.data?.message || 'فشل حفظ البيانات.'
  } finally {
    saving.value = false
  }
}

async function toggleActive(u: TeamUserRow) {
  try {
    await apiClient.put(`/customer-portal/team-users/${u.id}`, { is_active: !u.is_active })
    await load()
  } catch (e: any) {
    toast.error('تعذّر تحديث الحالة', e?.response?.data?.message || '')
  }
}

async function removeUser(u: TeamUserRow) {
  const ok = await appConfirm({
    title: 'تأكيد الحذف',
    message: `هل تريد حذف المستخدم ${u.name}؟`,
    variant: 'danger',
    confirmLabel: 'حذف',
    cancelLabel: 'إلغاء',
  })
  if (!ok) return
  try {
    await apiClient.delete(`/customer-portal/team-users/${u.id}`)
    toast.success('تم حذف المستخدم')
    await load()
  } catch (e: any) {
    toast.error('تعذّر الحذف', e?.response?.data?.message || '')
  }
}

watch([searchQuery, roleFilter, statusFilter], () => {
  currentPage.value = 1
  load()
})

onMounted(load)
</script>
