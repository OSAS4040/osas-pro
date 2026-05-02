<template>
  <div class="app-shell-page print-container space-y-4" dir="rtl">
    <div class="page-head no-print">
      <div class="page-title-wrap">
        <h2 class="page-title-xl">أوامر العمل</h2>
        <p class="page-subtitle">عرض مفصل لأوامر العميل مع الإرسال والمشاركة والتصدير</p>
      </div>
      <div class="page-toolbar">
        <button type="button" class="btn btn-secondary" @click="exportJSON">JSON</button>
        <button type="button" class="btn btn-secondary" @click="exportCSV">CSV</button>
        <button type="button" class="btn btn-secondary" @click="exportExcel">Excel</button>
        <button type="button" class="btn btn-secondary" @click="printList">PDF</button>
        <button type="button" class="btn btn-primary" :disabled="loading" @click="() => loadOrders()">
          {{ loading ? 'جارٍ التحديث...' : 'تحديث' }}
        </button>
      </div>
    </div>
    <div
      v-if="demoMode"
      class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800"
    >
      تم تفعيل بيانات أوامر العمل التجريبية لتمكينك من مراجعة العمليات بشكل واقعي.
    </div>

    <div class="card no-print p-4 space-y-3">
      <h3 class="text-sm font-semibold text-gray-800">إنشاء أمر/أوامر عمل دفعة واحدة</h3>
      <div class="grid gap-3 md:grid-cols-2">
        <div class="rounded-xl border border-gray-200 p-3 space-y-2">
          <div class="flex items-center justify-between">
            <label class="text-xs font-semibold text-gray-700">المركبات المختارة</label>
            <button type="button" class="text-[11px] text-violet-700 hover:underline" @click="toggleAllVehicles">
              {{ allVehiclesSelected ? 'إلغاء الكل' : 'تحديد الكل (حتى 200)' }}
            </button>
          </div>
          <p class="text-[11px] font-medium text-violet-700">بحث المركبات</p>
          <input
            v-model.trim="vehicleSearch"
            type="search"
            class="field-sm text-xs bg-violet-50/40 border-violet-200"
            placeholder="بحث باللوحة أو الماركة أو الموديل أو الشاسيه"
            autocomplete="off"
          />
          <p class="text-[10px] text-gray-400">
            صفحة {{ vehiclePickerPage }} من {{ vehiclePickerLastPage }} — إجمالي مطابقة البحث: {{ vehiclePickerTotal }} مركبة
          </p>
          <div class="flex items-center justify-between gap-2 text-[11px]">
            <button
              type="button"
              class="px-2 py-1 rounded border border-gray-200 disabled:opacity-50"
              :disabled="vehiclesLoading || vehiclePickerPage <= 1"
              @click="goVehiclePickerPage(vehiclePickerPage - 1)"
            >
              السابق
            </button>
            <span class="text-gray-500">{{ vehiclesLoading ? 'جارٍ التحميل...' : '' }}</span>
            <button
              type="button"
              class="px-2 py-1 rounded border border-gray-200 disabled:opacity-50"
              :disabled="vehiclesLoading || vehiclePickerPage >= vehiclePickerLastPage"
              @click="goVehiclePickerPage(vehiclePickerPage + 1)"
            >
              التالي
            </button>
          </div>
          <div class="max-h-40 overflow-y-auto space-y-1">
            <label v-for="v in vehicles" :key="`veh-${v.id}`" class="flex items-center gap-2 text-xs text-gray-700">
              <input type="checkbox" class="rounded border-gray-300" :checked="selectedVehicleIds.includes(Number(v.id))" @change="toggleVehicle(v.id)" />
              <span>{{ v.plate_number }} — {{ v.make }} {{ v.model }}</span>
            </label>
            <p v-if="!vehiclesLoading && vehicles.length === 0" class="text-[11px] text-amber-700 py-2">لا توجد مركبات مطابقة للبحث.</p>
          </div>
          <p class="text-[11px] text-gray-500">المحدد: {{ selectedVehicleIds.length }} مركبة</p>
        </div>
        <div class="rounded-xl border border-gray-200 p-3 space-y-2">
          <div class="flex items-center justify-between">
            <label class="text-xs font-semibold text-gray-700">الخدمات المختارة</label>
            <button type="button" class="text-[11px] text-violet-700 hover:underline" :disabled="servicesLoading || !allowedServices.length" @click="toggleAllServices">
              {{ allServicesSelected ? 'إلغاء الكل' : 'تحديد كل الخدمات المتاحة' }}
            </button>
          </div>
          <p class="text-[11px] font-medium text-violet-700">بحث الخدمات</p>
          <input
            v-model.trim="serviceSearch"
            type="search"
            class="field-sm text-xs bg-violet-50/40 border-violet-200"
            placeholder="بحث باسم الخدمة أو الرقم"
            :disabled="servicesLoading || !allowedServices.length"
            autocomplete="off"
          />
          <p v-if="allowedServices.length" class="text-[10px] text-gray-400">عرض {{ filteredServicesForPicker.length }} من {{ allowedServices.length }}</p>
          <div class="max-h-40 overflow-y-auto space-y-1">
            <label v-for="s in filteredServicesForPicker" :key="`svc-${s.id}`" class="flex items-center gap-2 text-xs text-gray-700">
              <input type="checkbox" class="rounded border-gray-300" :checked="selectedServiceIds.includes(Number(s.id))" @change="toggleService(s.id)" />
              <span>{{ s.name_ar || s.name || ('خدمة #' + s.id) }}</span>
            </label>
            <p v-if="allowedServices.length && !filteredServicesForPicker.length" class="text-[11px] text-amber-700 py-2">لا توجد خدمات مطابقة للبحث.</p>
          </div>
          <p class="text-[11px] text-gray-500">المحدد: {{ selectedServiceIds.length }} خدمة</p>
        </div>
      </div>
      <div class="grid gap-3 md:grid-cols-2">
        <input v-model.trim="form.description" type="text" class="field-sm" placeholder="وصف موحّد اختياري لأوامر العمل" />
        <input v-model.number="maxOrders" type="number" min="1" max="200" class="field-sm" placeholder="الحد الأقصى للأوامر (200)" />
      </div>
      <div class="grid gap-3 md:grid-cols-2">
        <select v-model="form.creation_policy" class="field-sm">
          <option value="customer_self">سياسة الإنشاء: منشأ مباشرة بواسطة العميل</option>
          <option value="manager_assign">سياسة الإنشاء: يختار المسؤول منشئ الأمر</option>
        </select>
        <select v-model="form.approval_policy" class="field-sm">
          <option value="manager_required">سياسة التعميد: اعتماد مسؤول إلزامي</option>
          <option value="direct_execution">سياسة التعميد: تنفيذ مباشر بدون اعتماد</option>
        </select>
      </div>
      <div class="grid gap-3 md:grid-cols-2">
        <select v-model="form.creator_user_id" class="field-sm" :disabled="usersLoading || !teamUsers.length">
          <option value="">{{ usersLoading ? 'جارٍ تحميل المستخدمين...' : 'اختر المنشئ من مستخدمي المنصة' }}</option>
          <option v-for="u in teamUsers" :key="`creator-${u.id}`" :value="String(u.id)">
            {{ u.name }} — {{ roleLabel(u.role) }}
          </option>
        </select>
        <select v-model="form.approver_user_id" class="field-sm" :disabled="usersLoading || !teamUsers.length">
          <option value="">{{ usersLoading ? 'جارٍ تحميل المستخدمين...' : 'اختر المعمّد من مستخدمي المنصة' }}</option>
          <option v-for="u in approverCandidates" :key="`approver-${u.id}`" :value="String(u.id)">
            {{ u.name }} — {{ roleLabel(u.role) }}
          </option>
        </select>
      </div>
      <p class="text-xs" :class="allowedServices.length ? 'text-emerald-700' : 'text-amber-700'">{{ servicesHint }}</p>
      <p class="text-xs text-gray-600">سيتم إنشاء أمر لكل مركبة مختارة، وبداخله جميع الخدمات المحددة (حد أعلى: {{ effectiveOrderCap }} أمر).</p>
      <textarea v-model.trim="form.notes" rows="3" class="field-sm" placeholder="ملاحظات إضافية"></textarea>
      <p v-if="createError" class="text-sm text-red-600">{{ createError }}</p>
      <div class="flex justify-end">
        <button :disabled="creating" class="btn btn-primary" @click="createOrder">
          {{ creating ? 'جارٍ الإنشاء...' : 'إنشاء دفعة أوامر العمل' }}
        </button>
      </div>
    </div>

    <div class="card no-print p-4 space-y-3">
      <div class="grid gap-3 md:grid-cols-3">
        <input v-model.trim="filters.search" type="text" class="field-sm md:col-span-2" placeholder="بحث برقم الأمر أو اللوحة أو الوصف" />
        <button type="button" class="btn btn-secondary" @click="resetFilters">إعادة تعيين</button>
      </div>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="s in statusFilters"
          :key="s.value"
          type="button"
          class="px-3 py-1.5 text-xs rounded-lg border transition-colors"
          :class="filters.status === s.value ? 'bg-violet-600 text-white border-violet-600' : 'bg-white border-gray-200 text-gray-700 hover:bg-violet-50'"
          @click="filters.status = s.value"
        >
          {{ s.label }} ({{ statusCount(s.value) }})
        </button>
      </div>
    </div>

    <div class="table-shell">
      <div class="panel-head">
        <span class="panel-title">قائمة أوامر العمل</span>
        <span class="panel-muted">{{ listTotalCount }} عنصر</span>
      </div>
      <div class="no-print mb-2 flex items-center justify-between text-xs text-gray-500">
        <span>عرض {{ pageStart }} - {{ pageEnd }} من {{ listTotalCount }}</span>
        <div class="flex items-center gap-2">
          <button type="button" class="px-2 py-1 rounded border border-gray-200 disabled:opacity-50" :disabled="currentPage <= 1" @click="goOrdersPage(currentPage - 1)">السابق</button>
          <span>صفحة {{ currentPage }} / {{ totalPages }}</span>
          <button type="button" class="px-2 py-1 rounded border border-gray-200 disabled:opacity-50" :disabled="currentPage >= totalPages" @click="goOrdersPage(currentPage + 1)">التالي</button>
        </div>
      </div>

      <div v-if="loading" class="state-loading">جارٍ التحميل...</div>

      <table v-else class="data-table">
        <thead>
          <tr>
            <th>رقم الأمر</th>
            <th>المركبة</th>
            <th>الحالة</th>
            <th>الأولوية</th>
            <th>الإنشاء</th>
            <th>الوصف</th>
            <th class="no-print">الإجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="o in paginatedOrders" :key="o.id">
            <td class="font-semibold text-violet-700">{{ o.order_number || ('#' + o.id) }}</td>
            <td>{{ o.vehicle?.plate_number || '—' }}</td>
            <td class="no-print">
              <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="statusClass(o.status)">
                {{ statusLabel(o.status) }}
              </span>
            </td>
            <td>{{ priorityLabel(o.priority) }}</td>
            <td>{{ fmtDate(o.created_at) }}</td>
            <td class="max-w-[280px] truncate">{{ o.description || o.notes || '—' }}</td>
            <td>
              <div class="flex flex-wrap gap-1.5">
                <button
                  v-if="canSendForApproval(o.status)"
                  :disabled="busyId === o.id"
                  class="px-2 py-1 rounded text-xs bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50"
                  @click="updateStatus(o, 'pending_manager_approval')"
                >
                  إرسال
                </button>
                <button
                  type="button"
                  class="px-2 py-1 rounded text-xs border border-primary-200 bg-primary-50 text-primary-900 hover:bg-primary-100 disabled:opacity-50"
                  :disabled="shareBusyId === o.id"
                  @click="copyPublicVerifyLink(o)"
                >
                  نسخ رابط التحقق
                </button>
                <button
                  type="button"
                  class="px-2 py-1 rounded text-xs border border-primary-200 text-primary-800 hover:bg-primary-50 disabled:opacity-50"
                  :disabled="shareBusyId === o.id"
                  @click="copyShareMessageText(o)"
                >
                  نسخ نص المشاركة
                </button>
                <button
                  v-if="canWebShare"
                  type="button"
                  class="px-2 py-1 rounded text-xs border border-sky-200 bg-sky-50 text-sky-900 hover:bg-sky-100 disabled:opacity-50"
                  :disabled="shareBusyId === o.id"
                  @click="systemShareWorkOrder(o)"
                >
                  مشاركة النظام
                </button>
                <button
                  type="button"
                  class="px-2 py-1 rounded text-xs border border-gray-300 text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                  :disabled="shareBusyId === o.id"
                  @click="openWhatsAppShare(o)"
                >
                  واتساب
                </button>
                <button
                  type="button"
                  class="px-2 py-1 rounded text-xs bg-emerald-100 text-emerald-700 hover:bg-emerald-200"
                  @click="printOrder(o)"
                >
                  طباعة
                </button>
                <button
                  type="button"
                  class="px-2 py-1 rounded text-xs border border-gray-300 text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                  :disabled="shareBusyId === o.id"
                  @click="shareWorkOrderByEmail(o)"
                >
                  بريد + PDF
                </button>
                <button
                  v-if="canCancel(o.status)"
                  :disabled="busyId === o.id"
                  class="px-2 py-1 rounded text-xs bg-red-100 text-red-700 hover:bg-red-200 disabled:opacity-50"
                  @click="updateStatus(o, 'cancelled')"
                >
                  إلغاء
                </button>
                <button
                  v-if="canRequestPlatformCancellation(o.status)"
                  type="button"
                  class="px-2 py-1 rounded text-xs bg-amber-100 text-amber-800 hover:bg-amber-200"
                  @click="requestPlatformCancellation(o)"
                >
                  طلب إلغاء عبر الإدارة
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!filteredOrders.length">
            <td colspan="7" class="table-empty">
              <p class="table-empty-title">لا توجد أوامر عمل مطابقة</p>
              <p class="table-empty-sub">غيّر الفلاتر أو أنشئ أمرًا جديدًا</p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { watchDebounced } from '@vueuse/core'
import axios from 'axios'
import apiClient from '@/lib/apiClient'
import { summarizeAxiosError } from '@/utils/apiErrorSummary'
import { useAuthStore } from '@/stores/auth'
import { printDocument } from '@/composables/useAppPrint'
import { useToast } from '@/composables/useToast'
import { demoCustomerVehicles, demoCustomerWorkOrders, demoCustomerServices } from '@/utils/customerDemoData'

const loading = ref(false)
const creating = ref(false)
const servicesLoading = ref(false)
const usersLoading = ref(false)
const createError = ref('')
const busyId = ref<number | null>(null)
const shareBusyId = ref<number | null>(null)
const orders = ref<any[]>([])
const ordersTotal = ref(0)
const ordersLastPage = ref(1)
const statusCountsFromApi = ref<Record<string, number>>({})
const vehicles = ref<any[]>([])
const vehiclesLoading = ref(false)
const vehiclesListDemo = ref(false)
const vehiclePickerPage = ref(1)
const vehiclePickerLastPage = ref(1)
const vehiclePickerTotal = ref(0)
const vehiclePickerPerPage = 50
const allowedServices = ref<any[]>([])
const teamUsers = ref<Array<{ id: number; name: string; role: string; is_active?: boolean }>>([])
const form = ref({
  vehicle_id: '',
  service_id: '',
  description: '',
  notes: '',
  creation_policy: 'manager_assign',
  approval_policy: 'manager_required',
  creator_user_id: '',
  approver_user_id: '',
})
const filters = reactive({ search: '', status: 'all' })
const selectedVehicleIds = ref<number[]>([])
const selectedServiceIds = ref<number[]>([])
const vehicleSearch = ref('')
const serviceSearch = ref('')
const maxOrders = ref(200)
const pageSize = 20
const currentPage = ref(1)
const auth = useAuthStore()
const toast = useToast()
const demoMode = ref(false)
const canWebShare = computed(() => typeof navigator !== 'undefined' && typeof navigator.share === 'function')

const statusFilters = [
  { value: 'all', label: 'الكل' },
  { value: 'draft', label: 'مسودة' },
  { value: 'pending_manager_approval', label: 'بانتظار الاعتماد' },
  { value: 'approved', label: 'معتمد' },
  { value: 'in_progress', label: 'قيد التنفيذ' },
  { value: 'completed', label: 'مكتمل' },
  { value: 'cancelled', label: 'ملغي' },
]

function extractList(payload: any): any[] {
  if (Array.isArray(payload?.data?.data)) return payload.data.data
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload)) return payload
  return []
}
function statusLabel(s: string): string {
  const x = String(s || '').toLowerCase()
  if (x === 'draft') return 'مسودة'
  if (x === 'pending_manager_approval') return 'بانتظار الاعتماد'
  if (x === 'approved') return 'معتمد'
  if (x === 'in_progress') return 'قيد التنفيذ'
  if (x === 'completed') return 'مكتمل'
  if (x === 'cancelled') return 'ملغي'
  return x || '—'
}
function statusClass(s: string): string {
  const x = String(s || '').toLowerCase()
  if (x === 'draft') return 'bg-gray-100 text-gray-700'
  if (x === 'pending_manager_approval') return 'bg-blue-100 text-blue-700'
  if (x === 'approved') return 'bg-emerald-100 text-emerald-700'
  if (x === 'in_progress') return 'bg-amber-100 text-amber-700'
  if (x === 'completed') return 'bg-green-100 text-green-700'
  if (x === 'cancelled') return 'bg-red-100 text-red-700'
  return 'bg-gray-100 text-gray-700'
}
function canSendForApproval(status: string): boolean {
  return String(status).toLowerCase() === 'draft'
}
function canCancel(status: string): boolean {
  return ['draft', 'pending_manager_approval'].includes(String(status).toLowerCase())
}
function canRequestPlatformCancellation(status: string): boolean {
  return ['approved', 'in_progress', 'completed'].includes(String(status).toLowerCase())
}
function fmtDate(d: string): string {
  return d ? new Date(d).toLocaleDateString('ar-SA') : '—'
}
function priorityLabel(p: string): string {
  const x = String(p || '').toLowerCase()
  if (x === 'urgent') return 'عاجلة'
  if (x === 'high') return 'عالية'
  if (x === 'low') return 'منخفضة'
  return 'عادية'
}
const servicesHint = computed(() => {
  if (!selectedVehicleIds.value.length) return 'اختر مركبة واحدة على الأقل لعرض الخدمات المسموح بها حسب العقد.'
  if (servicesLoading.value) return 'جارٍ تحميل الخدمات المسموح بها...'
  if (!allowedServices.value.length) return 'لا توجد خدمات متاحة لهذه المركبة ضمن العقد الحالي.'
  return `الخدمات المتاحة حسب العقد: ${allowedServices.value.length} خدمة`
})
const filteredServicesForPicker = computed(() => {
  const q = serviceSearch.value.trim().toLowerCase()
  if (!q) return allowedServices.value
  return allowedServices.value.filter((s) => {
    const label = String(s.name_ar || s.name || `خدمة #${s.id}`).toLowerCase()
    const idStr = String(s.id ?? '')
    return label.includes(q) || idStr.includes(q)
  })
})
const allVehiclesSelected = computed(() => {
  const cap = Math.min(200, vehiclePickerTotal.value || 0)
  return cap > 0 && selectedVehicleIds.value.length >= cap
})
const allServicesSelected = computed(() => allowedServices.value.length > 0 && selectedServiceIds.value.length === allowedServices.value.length)
const effectiveOrderCap = computed(() => Math.max(1, Math.min(200, Number(maxOrders.value || 200))))
const approverCandidates = computed(() =>
  teamUsers.value.filter((u) => ['owner', 'manager', 'accountant'].includes(String(u.role || '').toLowerCase())),
)

function roleLabel(role: string): string {
  const r = String(role || '').toLowerCase()
  if (r === 'owner') return 'مالك'
  if (r === 'manager') return 'مدير'
  if (r === 'accountant') return 'محاسب'
  if (r === 'staff') return 'موظف'
  if (r === 'viewer') return 'مراقب'
  return role || 'مستخدم'
}
function demoOrdersMatchingSearch(): typeof demoCustomerWorkOrders {
  const q = String(filters.search || '').trim().toLowerCase()
  if (!q) return demoCustomerWorkOrders
  return demoCustomerWorkOrders.filter((o) => {
    const hay = `${o.order_number ?? ''} ${o.vehicle?.plate_number ?? ''} ${o.description ?? ''} ${o.notes ?? ''}`.toLowerCase()
    return hay.includes(q)
  })
}
function statusCount(status: string): number {
  if (!demoMode.value) {
    if (status === 'all') return Number(statusCountsFromApi.value.all ?? ordersTotal.value ?? 0)
    return Number(statusCountsFromApi.value[status] ?? 0)
  }
  const base = demoOrdersMatchingSearch()
  if (status === 'all') return base.length
  return base.filter((o) => String(o.status || '').toLowerCase() === status).length
}
function toggleVehicle(id: number): void {
  const numericId = Number(id)
  if (!numericId) return
  if (selectedVehicleIds.value.includes(numericId)) {
    selectedVehicleIds.value = selectedVehicleIds.value.filter((x) => x !== numericId)
    return
  }
  if (selectedVehicleIds.value.length >= 200) {
    toast.warning('تم الوصول للحد الأقصى', 'يمكن تحديد 200 مركبة كحد أقصى لكل دفعة.')
    return
  }
  selectedVehicleIds.value = [...selectedVehicleIds.value, numericId]
  if (!form.value.vehicle_id) {
    form.value.vehicle_id = String(numericId)
    void loadAllowedServices()
  }
}
async function toggleAllVehicles(): Promise<void> {
  if (allVehiclesSelected.value) {
    selectedVehicleIds.value = []
    return
  }
  if (vehiclesListDemo.value) {
    const rows = filterDemoVehiclesForPicker().slice(0, 200)
    selectedVehicleIds.value = rows.map((v) => Number(v.id)).filter((id) => Number.isFinite(id) && id > 0)
    if (selectedVehicleIds.value.length && !form.value.vehicle_id) {
      form.value.vehicle_id = String(selectedVehicleIds.value[0])
      void loadAllowedServices()
    }
    return
  }
  try {
    const params: Record<string, unknown> = { per_page: 200, page: 1 }
    const s = vehicleSearch.value.trim()
    if (s) params.search = s
    if (auth.user?.customer_id != null) params.customer_id = auth.user.customer_id
    const { data } = await apiClient.get('/vehicles', { params })
    const rows = extractList(data)
    selectedVehicleIds.value = rows.map((v) => Number(v.id)).filter((id) => Number.isFinite(id) && id > 0).slice(0, 200)
    if (selectedVehicleIds.value.length && !form.value.vehicle_id) {
      form.value.vehicle_id = String(selectedVehicleIds.value[0])
      void loadAllowedServices()
    }
  } catch {
    toast.warning('تعذر التحديد', 'لم نتمكن من جلب قائمة المركبات للتحديد الجماعي.')
  }
}
function toggleService(id: number): void {
  const numericId = Number(id)
  if (!numericId) return
  if (selectedServiceIds.value.includes(numericId)) {
    selectedServiceIds.value = selectedServiceIds.value.filter((x) => x !== numericId)
    return
  }
  selectedServiceIds.value = [...selectedServiceIds.value, numericId]
}
function toggleAllServices(): void {
  if (allServicesSelected.value) {
    selectedServiceIds.value = []
    return
  }
  selectedServiceIds.value = allowedServices.value.map((s) => Number(s.id)).filter((id) => Number.isFinite(id) && id > 0)
}
const filteredOrders = computed(() => {
  if (!demoMode.value) return orders.value
  return demoOrdersMatchingSearch().filter((o) => {
    if (filters.status !== 'all' && String(o.status || '').toLowerCase() !== filters.status) return false
    return true
  })
})
const listTotalCount = computed(() => (demoMode.value ? filteredOrders.value.length : ordersTotal.value))
const totalPages = computed(() => {
  if (demoMode.value) return Math.max(1, Math.ceil(filteredOrders.value.length / pageSize))
  return Math.max(1, ordersLastPage.value)
})
const pageStart = computed(() => {
  if (!listTotalCount.value) return 0
  return (currentPage.value - 1) * pageSize + 1
})
const pageEnd = computed(() => {
  if (demoMode.value) return Math.min(currentPage.value * pageSize, filteredOrders.value.length)
  return Math.min((currentPage.value - 1) * pageSize + orders.value.length, ordersTotal.value)
})
const paginatedOrders = computed(() => {
  if (!demoMode.value) return orders.value
  const start = (currentPage.value - 1) * pageSize
  return filteredOrders.value.slice(start, start + pageSize)
})
function goOrdersPage(p: number): void {
  const next = Math.max(1, Math.min(p, totalPages.value))
  if (next === currentPage.value) return
  currentPage.value = next
  if (!demoMode.value) void loadOrders()
}
function resetFilters(): void {
  filters.search = ''
  filters.status = 'all'
  currentPage.value = 1
  if (!demoMode.value) void loadOrders()
}
watchDebounced(
  () => filters.search,
  () => {
    currentPage.value = 1
    if (!demoMode.value) void loadOrders()
  },
  { debounce: 400 },
)
watch(
  () => filters.status,
  () => {
    currentPage.value = 1
    if (!demoMode.value) void loadOrders()
  },
)
watch(totalPages, (next) => {
  if (currentPage.value > next) currentPage.value = next
})
function filterDemoVehiclesForPicker(): typeof demoCustomerVehicles {
  const q = vehicleSearch.value.trim().toLowerCase()
  if (!q) return [...demoCustomerVehicles]
  return demoCustomerVehicles.filter((v) => {
    const hay = `${v?.plate_number ?? ''} ${v?.make ?? ''} ${v?.model ?? ''} ${v?.vin ?? ''}`.toLowerCase()
    return hay.includes(q)
  })
}
async function loadVehiclePickerPage(page = 1): Promise<void> {
  vehiclePickerPage.value = page
  if (vehiclesListDemo.value) {
    const all = filterDemoVehiclesForPicker()
    vehiclePickerTotal.value = all.length
    vehiclePickerLastPage.value = Math.max(1, Math.ceil(all.length / vehiclePickerPerPage))
    const start = (page - 1) * vehiclePickerPerPage
    vehicles.value = all.slice(start, start + vehiclePickerPerPage)
    return
  }
  vehiclesLoading.value = true
  try {
    const params: Record<string, unknown> = { per_page: vehiclePickerPerPage, page }
    const s = vehicleSearch.value.trim()
    if (s) params.search = s
    if (auth.user?.customer_id != null) params.customer_id = auth.user.customer_id
    const { data } = await apiClient.get('/vehicles', { params })
    const paginator = data?.data
    vehicles.value = Array.isArray(paginator?.data) ? paginator.data : extractList(data)
    vehiclePickerTotal.value = Number(paginator?.total ?? vehicles.value.length)
    vehiclePickerLastPage.value = Math.max(1, Number(paginator?.last_page ?? 1))
    vehiclePickerPage.value = Math.min(Number(paginator?.current_page ?? page), vehiclePickerLastPage.value)
  } catch {
    vehiclesListDemo.value = true
    const all = filterDemoVehiclesForPicker()
    vehiclePickerTotal.value = all.length
    vehiclePickerLastPage.value = Math.max(1, Math.ceil(all.length / vehiclePickerPerPage))
    const p = Math.min(page, vehiclePickerLastPage.value)
    vehiclePickerPage.value = p
    const start = (p - 1) * vehiclePickerPerPage
    vehicles.value = all.slice(start, start + vehiclePickerPerPage)
  } finally {
    vehiclesLoading.value = false
  }
}
function goVehiclePickerPage(p: number): void {
  const next = Math.max(1, Math.min(p, vehiclePickerLastPage.value))
  if (next === vehiclePickerPage.value) return
  void loadVehiclePickerPage(next)
}
watchDebounced(
  vehicleSearch,
  () => {
    void loadVehiclePickerPage(1)
  },
  { debounce: 350 },
)
async function fetchAllOrdersForExport(): Promise<any[]> {
  if (demoMode.value) return filteredOrders.value
  const out: any[] = []
  let page = 1
  const perPage = 100
  while (page <= 10000) {
    const params: Record<string, unknown> = { per_page: perPage, page, include_status_counts: 0 }
    if (auth.user?.customer_id != null) params.customer_id = auth.user.customer_id
    const q = filters.search.trim()
    if (q) params.search = q
    if (filters.status !== 'all') params.status = filters.status
    const { data } = await apiClient.get('/work-orders', { params })
    const paginator = data?.data
    const rows = Array.isArray(paginator?.data) ? paginator.data : extractList(data)
    out.push(...rows)
    const last = Math.max(1, Number(paginator?.last_page ?? 1))
    if (page >= last || rows.length === 0) break
    page += 1
  }
  return out
}
async function normalizedExportRows(): Promise<any[]> {
  const rows = await fetchAllOrdersForExport()
  return rows.map((o) => ({
    order_number: o.order_number || `#${o.id}`,
    vehicle_plate: o.vehicle?.plate_number || '',
    status: statusLabel(o.status),
    priority: priorityLabel(o.priority),
    created_at: fmtDate(o.created_at),
    description: o.description || '',
    notes: o.notes || '',
  }))
}
async function exportCSV(): Promise<void> {
  const rows = await normalizedExportRows()
  if (!rows.length) {
    toast.warning('لا توجد بيانات', 'لا توجد أوامر عمل للتصدير.')
    return
  }
  const keys = Object.keys(rows[0] ?? {})
  const csv = [keys.join(','), ...rows.map((r) => keys.map((k) => `"${String(r[k] ?? '').replace(/"/g, '""')}"`).join(','))].join('\n')
  const url = URL.createObjectURL(new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' }))
  const a = document.createElement('a')
  a.href = url
  a.download = 'customer_work_orders.csv'
  a.click()
  URL.revokeObjectURL(url)
  toast.success('تم التصدير', 'تم تنزيل ملف CSV بنجاح.')
}
async function exportExcel(): Promise<void> {
  const rows = await normalizedExportRows()
  if (!rows.length) {
    toast.warning('لا توجد بيانات', 'لا توجد أوامر عمل للتصدير.')
    return
  }
  const { downloadExcelFromRows } = await import('@/utils/exportExcel')
  await downloadExcelFromRows(rows, 'أوامر العمل', 'customer_work_orders.xlsx')
  toast.success('تم التصدير', 'تم تنزيل ملف Excel بنجاح.')
}
async function exportJSON(): Promise<void> {
  const rows = await normalizedExportRows()
  if (!rows.length) {
    toast.warning('لا توجد بيانات', 'لا توجد أوامر عمل للتصدير.')
    return
  }
  const blob = new Blob([JSON.stringify({ generated_at: new Date().toISOString(), rows }, null, 2)], { type: 'application/json;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'customer_work_orders.json'
  a.click()
  URL.revokeObjectURL(url)
  toast.success('تم التصدير', 'تم تنزيل ملف JSON بنجاح.')
}
function printList(): void {
  void printDocument({ rootSelector: '.print-container' })
}
async function fetchShareLinks(orderId: number) {
  const { data } = await apiClient.get(`/work-orders/${orderId}/share-links`, { skipGlobalErrorToast: true })
  return data.data as {
    public_verify_url: string
    whatsapp_open_href: string
    share_text: string
  }
}

async function copyToClipboard(text: string): Promise<boolean> {
  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(text)
      return true
    }
  } catch {
    /* fallback */
  }
  try {
    const ta = document.createElement('textarea')
    ta.value = text
    ta.setAttribute('readonly', '')
    ta.style.position = 'fixed'
    ta.style.left = '-9999px'
    document.body.appendChild(ta)
    ta.select()
    const ok = document.execCommand('copy')
    document.body.removeChild(ta)
    return ok
  } catch {
    return false
  }
}

async function copyPublicVerifyLink(order: any): Promise<void> {
  shareBusyId.value = Number(order.id)
  try {
    const links = await fetchShareLinks(Number(order.id))
    const ok = await copyToClipboard(links.public_verify_url)
    if (ok) toast.success('تم النسخ', 'تم نسخ رابط التحقق.')
    else toast.error('تعذر النسخ', 'يرجى السماح بالنسخ من المتصفح.')
  } catch (e: unknown) {
    toast.error('تعذّر جلب الرابط', summarizeAxiosError(e))
  } finally {
    shareBusyId.value = null
  }
}

async function copyShareMessageText(order: any): Promise<void> {
  shareBusyId.value = Number(order.id)
  try {
    const links = await fetchShareLinks(Number(order.id))
    const ok = await copyToClipboard(links.share_text)
    if (ok) toast.success('تم النسخ', 'تم نسخ نص المشاركة.')
    else toast.error('تعذر النسخ', 'يرجى السماح بالنسخ من المتصفح.')
  } catch (e: unknown) {
    toast.error('تعذّر جلب النص', summarizeAxiosError(e))
  } finally {
    shareBusyId.value = null
  }
}

async function systemShareWorkOrder(order: any): Promise<void> {
  shareBusyId.value = Number(order.id)
  try {
    const links = await fetchShareLinks(Number(order.id))
    if (typeof navigator.share !== 'function') {
      await copyShareMessageText(order)
      return
    }
    await navigator.share({
      title: `أمر عمل ${order.order_number || ('#' + order.id)}`,
      text: links.share_text,
      url: links.public_verify_url,
    })
  } catch (e: unknown) {
    const err = e as { name?: string }
    if (err?.name !== 'AbortError') {
      toast.error('تعذّرت المشاركة', summarizeAxiosError(e))
    }
  } finally {
    shareBusyId.value = null
  }
}

async function openWhatsAppShare(order: any): Promise<void> {
  shareBusyId.value = Number(order.id)
  try {
    const links = await fetchShareLinks(Number(order.id))
    window.open(links.whatsapp_open_href, '_blank', 'noopener,noreferrer')
  } catch (e: unknown) {
    toast.error('تعذّر فتح واتساب', summarizeAxiosError(e))
  } finally {
    shareBusyId.value = null
  }
}

async function shareWorkOrderByEmail(order: any): Promise<void> {
  const email = window.prompt('أدخل البريد الإلكتروني للمستلم:')
  if (!email || !email.trim()) return
  shareBusyId.value = Number(order.id)
  try {
    await apiClient.post(`/work-orders/${order.id}/share-email`, { email: email.trim() })
    toast.success('تم الإرسال', 'أُرسل البريد مع مرفق PDF.')
  } catch (e: unknown) {
    toast.error('تعذّر الإرسال', summarizeAxiosError(e))
  } finally {
    shareBusyId.value = null
  }
}
function toAbsoluteUrl(value: string): string {
  if (!value) return ''
  if (/^https?:\/\//i.test(value)) return value
  if (value.startsWith('/')) return `${window.location.origin}${value}`
  return `${window.location.origin}/${value}`
}

function escHtml(value: unknown): string {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}

async function printOrder(order: any): Promise<void> {
  const win = window.open('', '_blank', 'width=900,height=700')
  if (!win) return

  const userAny = (auth.user ?? {}) as any
  const companyAny = (userAny.company ?? {}) as any
  const platformName = companyAny.name || userAny.company_name || 'منصة Verdent'
  const commercialRegister =
    companyAny.commercial_register
    || companyAny.commercial_registration
    || companyAny.cr_number
    || 'غير متوفر'
  const serviceDescription = order?.description || order?.notes || 'تنفيذ أعمال الصيانة/التشغيل المعتمدة حسب العقد.'
  const issuedBy = userAny.name || 'مستخدم المنصة'
  const issuerEntity = `${platformName} - بوابة العميل`
  const sourceCustomerName = order?.customer?.name || userAny.customer_name || 'العميل المصدر'
  const sourceCustomerRep = order?.customer_contact_name || order?.driver_name || sourceCustomerName
  const fleetRepName = issuedBy
  const vehicleOdometer =
    order?.odometer_reading
    ?? order?.mileage_in
    ?? order?.vehicle?.odometer_reading
    ?? order?.vehicle?.mileage
    ?? 'غير متوفر'
  const vehiclePlate = order?.vehicle?.plate_number || '—'
  const logoUrl = toAbsoluteUrl(companyAny.logo_url || '/favicon.ico')
  let vehicleQr = ''
  try {
    const qrcode = await import('qrcode')
    vehicleQr = await qrcode.toDataURL(`Vehicle:${vehiclePlate}|WO:${order.order_number || order.id}`)
  } catch {
    vehicleQr = ''
  }
  const issuedDate = fmtDate(order?.created_at || '')
  const hijriDate = new Date(order?.created_at || Date.now()).toLocaleDateString('ar-SA-u-ca-islamic')
  const serviceItems = Array.isArray(order?.items) ? order.items : []
  const serviceRows = serviceItems.length
    ? serviceItems.map((item: any, idx: number) => `
      <tr>
        <td>${idx + 1}</td>
        <td>${escHtml(item?.description || item?.service?.name_ar || item?.service?.name || `خدمة #${item?.service_id || idx + 1}`)}</td>
        <td>${escHtml(item?.quantity ?? 1)}</td>
        <td>${escHtml(item?.price ?? item?.unit_price ?? '—')}</td>
      </tr>
    `).join('')
    : `
      <tr>
        <td>1</td>
        <td>${escHtml(serviceDescription)}</td>
        <td>1</td>
        <td>—</td>
      </tr>
    `

  win.document.write(`
    <html>
      <head>
        <title>أمر عمل العميل</title>
        <meta charset="utf-8" />
        <style>
          @page { size: A4; margin: 10mm; }
          body {
            font-family: 'Tajawal', Arial, sans-serif;
            color: #0f172a;
            background: #fff;
            margin: 0;
            padding: 0;
          }
          .doc { border: 1px solid #cbd5e1; border-radius: 12px; overflow: hidden; }
          .head {
            display: flex; justify-content: space-between; align-items: center;
            padding: 12px 14px; border-bottom: 2px solid #7c3aed;
            background: linear-gradient(90deg, #faf5ff, #eef2ff);
          }
          .brand { display: flex; align-items: center; gap: 10px; }
          .brand img { width: 56px; height: 56px; object-fit: contain; border-radius: 10px; background: #fff; border: 1px solid #cbd5e1; }
          .brand h1 { margin: 0; font-size: 16px; font-weight: 800; }
          .brand p { margin: 2px 0 0; font-size: 11px; color: #475569; }
          .meta-title { text-align: left; }
          .meta-title h2 { margin: 0; font-size: 20px; font-weight: 900; color: #312e81; }
          .meta-title p { margin: 3px 0 0; font-size: 12px; color: #334155; font-weight: 700; }
          .content { padding: 12px 14px; }
          .summary-table, .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
          }
          .summary-table th, .summary-table td,
          .items-table th, .items-table td {
            border: 1px solid #94a3b8;
            padding: 6px 8px;
            font-size: 12px;
          }
          .summary-table th, .items-table th {
            background: #f3f4f6;
            font-weight: 800;
          }
          .summary-table td.value {
            font-weight: 700;
            background: #fff;
          }
          .doc-title {
            text-align: center;
            margin-top: 8px;
            font-weight: 900;
            color: #111827;
          }
          .doc-subtitle {
            text-align: center;
            margin-top: 2px;
            font-size: 12px;
            color: #374151;
            font-weight: 700;
          }
          .status-chip {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            background: #ede9fe;
            color: #5b21b6;
            font-weight: 800;
          }
          .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 10px;
          }
          .note-box {
            border: 1px dashed #94a3b8;
            border-radius: 8px;
            padding: 8px;
            background: #f8fafc;
            font-size: 12px;
            line-height: 1.8;
          }
          .qr-box { border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px; text-align: center; background: #fff; }
          .qr-box img { width: 88px; height: 88px; }
          .signatures {
            margin-top: 14px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
          }
          .sign-box {
            border-top: 1px solid #334155;
            padding-top: 6px;
            font-size: 12px;
          }
          .foot {
            border-top: 1px solid #cbd5e1; padding: 8px 12px; font-size: 10px; color: #64748b;
            display: flex; justify-content: space-between; gap: 8px;
          }
        </style>
      </head>
      <body dir="rtl">
        <div class="doc">
          <div class="head">
            <div class="brand">
              <img src="${escHtml(logoUrl)}" alt="logo" />
              <div>
                <h1>${escHtml(platformName)}</h1>
                <p>رقم السجل التجاري: ${escHtml(commercialRegister)}</p>
              </div>
            </div>
            <div class="meta-title">
              <h2>أمر عمل</h2>
              <p>${escHtml(order.order_number || ('#' + order.id))}</p>
            </div>
          </div>
          <div class="content">
            <div class="doc-title">B2B Job Order (Credit)</div>
            <div class="doc-subtitle">الجهة: ${escHtml(platformName)} — ${escHtml(issuerEntity)}</div>
            <table class="summary-table">
              <tr>
                <th>اليوم</th>
                <td class="value">${escHtml(new Date(order?.created_at || Date.now()).toLocaleDateString('ar-SA', { weekday: 'long' }))}</td>
                <th>الحالة</th>
                <td class="value"><span class="status-chip">${escHtml(statusLabel(order.status))}</span></td>
              </tr>
              <tr>
                <th>التاريخ</th>
                <td class="value">${escHtml(issuedDate)}</td>
                <th>التاريخ الهجري</th>
                <td class="value">${escHtml(hijriDate)}</td>
              </tr>
              <tr>
                <th>المركبة</th>
                <td class="value">${escHtml(vehiclePlate)}</td>
                <th>الموديل / النوع</th>
                <td class="value">${escHtml(order?.vehicle?.make || '')} ${escHtml(order?.vehicle?.model || '')}</td>
              </tr>
              <tr>
                <th>الأولوية</th>
                <td class="value">${escHtml(priorityLabel(order.priority))}</td>
                <th>المعرف الداخلي</th>
                <td class="value">#${escHtml(order.id)}</td>
              </tr>
              <tr>
                <th>عداد المركبة (كم)</th>
                <td class="value">${escHtml(vehicleOdometer)}</td>
                <th>العميل المصدر</th>
                <td class="value">${escHtml(sourceCustomerName)}</td>
              </tr>
            </table>
            <table class="items-table">
              <thead>
                <tr>
                  <th style="width:44px;">م</th>
                  <th>البند</th>
                  <th style="width:90px;">الكمية</th>
                  <th style="width:120px;">السعر</th>
                </tr>
              </thead>
              <tbody>
                ${serviceRows}
              </tbody>
            </table>
            <div class="meta-grid">
              <div class="note-box">
                <strong>وصف الخدمة</strong><br />
                ${escHtml(serviceDescription)}
              </div>
              <div class="qr-box">
                ${vehicleQr ? `<img src="${vehicleQr}" alt="vehicle-qr" />` : '<div style="width:88px;height:88px;display:flex;align-items:center;justify-content:center;font-size:10px;color:#64748b;">QR غير متاح</div>'}
                <div style="font-size:10px;color:#475569;margin-top:4px;">باركود المركبة</div>
              </div>
            </div>
            <div class="note-box" style="margin-top:10px;">
              <strong>ملاحظات إضافية</strong><br />
              ${escHtml(order.notes || 'لا توجد ملاحظات إضافية.')}
            </div>
            <div class="signatures">
              <div class="sign-box">
                <div><strong>مندوب أسس الأسطول</strong></div>
                <div>الاسم: ${escHtml(fleetRepName)}</div>
                <div>التوقيع: ____________________</div>
                <div>التاريخ: ${escHtml(issuedDate)}</div>
              </div>
              <div class="sign-box">
                <div><strong>مندوب الجهة المصدرة للأمر</strong></div>
                <div>الاسم: ${escHtml(sourceCustomerRep)}</div>
                <div>حسب بيانات العميل المصدر: ${escHtml(sourceCustomerName)}</div>
                <div>التوقيع: ____________________</div>
                <div>التاريخ: ____________________</div>
              </div>
            </div>
          </div>
          <div class="foot">
            <span>تم الإنشاء عبر واجهة منصة العميل</span>
            <span>وقت الطباعة: ${escHtml(new Date().toLocaleString('ar-SA-u-ca-gregory'))}</span>
          </div>
        </div>
      </body>
    </html>
  `)
  win.document.close()
  win.focus()
  win.print()
}

async function loadOrders(options?: { clampOnly?: boolean }): Promise<void> {
  loading.value = true
  demoMode.value = false
  try {
    const params: Record<string, unknown> = {
      per_page: pageSize,
      page: currentPage.value,
      include_status_counts: 1,
    }
    if (auth.user?.customer_id != null) params.customer_id = auth.user.customer_id
    const q = filters.search.trim()
    if (q) params.search = q
    if (filters.status !== 'all') params.status = filters.status
    const { data } = await apiClient.get('/work-orders', { params })
    const paginator = data?.data
    orders.value = Array.isArray(paginator?.data) ? paginator.data : extractList(data)
    ordersTotal.value = Number(paginator?.total ?? orders.value.length)
    ordersLastPage.value = Math.max(1, Number(paginator?.last_page ?? 1))
    if (data?.status_counts && typeof data.status_counts === 'object') {
      statusCountsFromApi.value = data.status_counts as Record<string, number>
    }
    if (!orders.value.length && ordersTotal.value === 0) {
      orders.value = demoCustomerWorkOrders
      demoMode.value = true
      ordersTotal.value = demoCustomerWorkOrders.length
      ordersLastPage.value = Math.max(1, Math.ceil(demoCustomerWorkOrders.length / pageSize))
      statusCountsFromApi.value = {}
    } else if (!options?.clampOnly && !demoMode.value && ordersLastPage.value >= 1 && currentPage.value > ordersLastPage.value) {
      currentPage.value = ordersLastPage.value
      await loadOrders({ clampOnly: true })
      return
    }
  } catch {
    orders.value = demoCustomerWorkOrders
    demoMode.value = true
    ordersTotal.value = demoCustomerWorkOrders.length
    ordersLastPage.value = Math.max(1, Math.ceil(demoCustomerWorkOrders.length / pageSize))
    statusCountsFromApi.value = {}
  } finally {
    loading.value = false
  }
}

async function loadTeamUsers(): Promise<void> {
  usersLoading.value = true
  try {
    const { data } = await apiClient.get('/users', { params: { per_page: 200, is_active: true } })
    const rows = extractList(data)
    teamUsers.value = rows.map((u: any) => ({
      id: Number(u.id),
      name: String(u.name || '—'),
      role: String(u.role?.value || u.role || 'staff'),
      is_active: Boolean(u.is_active ?? true),
    })).filter((u: any) => Number.isFinite(u.id))
  } catch {
    teamUsers.value = []
  } finally {
    if (!form.value.creator_user_id && auth.user?.id != null) {
      form.value.creator_user_id = String(auth.user.id)
    }
    if (!form.value.approver_user_id && approverCandidates.value.length) {
      form.value.approver_user_id = String(approverCandidates.value[0].id)
    }
    usersLoading.value = false
  }
}
function selectedVehicleCustomerId(): number | null {
  const selected = vehicles.value.find((v) => String(v.id) === String(form.value.vehicle_id))
  if (selected?.customer_id != null) return Number(selected.customer_id)
  if (auth.user?.customer_id != null) return Number(auth.user.customer_id)
  return null
}
async function filterServicesByPricingPreview(list: any[]): Promise<any[]> {
  const customerId = selectedVehicleCustomerId()
  const vehicleId = Number(form.value.vehicle_id)
  if (!customerId || !vehicleId || !list.length) return []
  const out: any[] = []
  const batchSize = 8
  for (let i = 0; i < list.length; i += batchSize) {
    const batch = list.slice(i, i + batchSize)
    const checks = await Promise.allSettled(
      batch.map((svc: any) =>
        apiClient.post(
          '/work-orders/line-pricing-preview',
          { customer_id: customerId, vehicle_id: vehicleId, service_id: Number(svc.id), quantity: 1 },
          { skipGlobalErrorToast: true },
        ),
      ),
    )
    checks.forEach((res, idx) => {
      if (res.status === 'fulfilled') out.push(batch[idx])
    })
  }
  return out
}
async function loadAllowedServices(): Promise<void> {
  if (!form.value.vehicle_id) {
    allowedServices.value = []
    form.value.service_id = ''
    selectedServiceIds.value = []
    return
  }
  servicesLoading.value = true
  try {
    const { data } = await apiClient.get('/fleet-portal/service-catalog', {
      params: { vehicle_id: Number(form.value.vehicle_id) },
      skipGlobalErrorToast: true,
    })
    const rows = extractList(data)
    allowedServices.value = rows
    if (!allowedServices.value.length) allowedServices.value = demoCustomerServices
  } catch {
    try {
      const { data } = await apiClient.get('/services', {
        params: { per_page: 200, is_active: true },
        skipGlobalErrorToast: true,
      })
      const all = extractList(data)
      allowedServices.value = await filterServicesByPricingPreview(all)
    } catch {
      allowedServices.value = demoCustomerServices
    }
  } finally {
    const validSet = new Set(allowedServices.value.map((s) => Number(s.id)))
    selectedServiceIds.value = selectedServiceIds.value.filter((id) => validSet.has(id))
    if (!selectedServiceIds.value.length && allowedServices.value.length) {
      selectedServiceIds.value = [Number(allowedServices.value[0].id)]
    }
    const validSingleSet = new Set(allowedServices.value.map((s) => String(s.id)))
    if (!validSingleSet.has(String(form.value.service_id || ''))) form.value.service_id = ''
    servicesLoading.value = false
  }
}
async function createOrder(): Promise<void> {
  if (!selectedVehicleIds.value.length || !selectedServiceIds.value.length) {
    createError.value = 'حدد مركبة واحدة على الأقل وخدمة واحدة على الأقل.'
    return
  }
  if (form.value.creation_policy === 'manager_assign' && !form.value.creator_user_id) {
    createError.value = 'اختر المستخدم المنشئ حسب سياسة الإنشاء.'
    return
  }
  if (form.value.approval_policy === 'manager_required' && !form.value.approver_user_id) {
    createError.value = 'اختر المستخدم المعمّد حسب سياسة التعميد.'
    return
  }
  creating.value = true
  createError.value = ''
  try {
    const cap = effectiveOrderCap.value
    const targetVehicles = selectedVehicleIds.value.slice(0, cap)
    const serviceIds = selectedServiceIds.value.slice()
    const serviceNames = allowedServices.value
      .filter((s) => serviceIds.includes(Number(s.id)))
      .map((s) => s.name_ar || s.name || `Service #${s.id}`)
    const description = form.value.description || `خدمات: ${serviceNames.join('، ')}`
    let successCount = 0
    const errors: string[] = []
    for (const vehicleId of targetVehicles) {
      try {
        await apiClient.post('/work-orders', {
          vehicle_id: Number(vehicleId),
          description,
          notes: form.value.notes || undefined,
          creator_user_id: form.value.creator_user_id ? Number(form.value.creator_user_id) : undefined,
          approver_user_id: form.value.approver_user_id ? Number(form.value.approver_user_id) : undefined,
          creation_policy: form.value.creation_policy,
          approval_policy: form.value.approval_policy,
          items: serviceIds.map((sid) => ({ item_type: 'service', service_id: Number(sid), quantity: 1 })),
        })
        successCount += 1
      } catch (e: unknown) {
        errors.push(`مركبة #${vehicleId}: ${summarizeAxiosError(e)}`)
      }
    }
    if (errors.length && !successCount) {
      throw new Error(errors.slice(0, 3).join(' | '))
    }
    form.value = {
      vehicle_id: '',
      service_id: '',
      description: '',
      notes: '',
      creation_policy: 'manager_assign',
      approval_policy: 'manager_required',
      creator_user_id: auth.user?.id != null ? String(auth.user.id) : '',
      approver_user_id: approverCandidates.value[0] ? String(approverCandidates.value[0].id) : '',
    }
    selectedVehicleIds.value = []
    selectedServiceIds.value = []
    allowedServices.value = []
    await loadOrders()
    if (errors.length) {
      toast.warning('تم إنشاء جزئي', `تم إنشاء ${successCount} أمر، وتعذر ${errors.length} أمر.`)
    } else {
      toast.success('تم الحفظ', `تم إنشاء ${successCount} أمر عمل بنجاح.`)
    }
  } catch (e: unknown) {
    createError.value = summarizeAxiosError(e)
    toast.error('تعذر الحفظ', createError.value)
  } finally {
    creating.value = false
  }
}
async function updateStatus(order: any, status: string): Promise<void> {
  busyId.value = Number(order.id)
  try {
    await apiClient.patch(`/work-orders/${order.id}/status`, {
      status,
      version: order.version,
    })
    await loadOrders()
    toast.success('تم الحفظ', `تم تحديث حالة أمر العمل إلى: ${statusLabel(status)}`)
  } catch {
    toast.error('تعذر التحديث', 'لم نتمكن من تحديث حالة أمر العمل.')
  } finally {
    busyId.value = null
  }
}

async function requestPlatformCancellation(order: any): Promise<void> {
  const orderNo = order?.order_number || `#${order?.id || ''}`
  const title = `طلب إلغاء أمر عمل بعد الاعتماد: ${orderNo}`
  const details = [
    `رقم أمر العمل: ${orderNo}`,
    `الحالة الحالية: ${statusLabel(order?.status)}`,
    `المركبة: ${order?.vehicle?.plate_number || '—'}`,
    `سبب الطلب: الإلغاء بعد الاعتماد يتطلب مراجعة واعتماد من إدارة المنصة.`,
    `مقدم الطلب: ${auth.user?.name || 'مستخدم العميل'}`,
  ].join('\n')
  try {
    await axios.post('/api/v1/support/tickets', {
      subject: title,
      description: details,
      category: 'operational',
      priority: 'high',
      channel: 'portal',
    })
    toast.success('تم إرسال الطلب', 'تم رفع طلب إلغاء أمر العمل لإدارة المنصة للمراجعة.')
  } catch {
    toast.warning('تعذر الإرسال المباشر', 'تعذر رفع الطلب تلقائيًا. تم نسخ نص الطلب للحافظة.')
    await navigator.clipboard.writeText(`${title}\n\n${details}`).catch(() => {})
  }
}

onMounted(() => {
  void loadVehiclePickerPage(1)
  loadTeamUsers()
  loadOrders()
})
</script>

<style scoped>
.field-sm {
  @apply w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none;
}
</style>
