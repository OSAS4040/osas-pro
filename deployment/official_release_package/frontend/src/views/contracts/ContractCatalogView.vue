<template>
  <div class="space-y-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <RouterLink to="/contracts" class="text-xs text-primary-600 hover:underline mb-2 inline-block">← العقود</RouterLink>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">بنود الكتالوج التعاقدي</h1>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">
          {{ contract?.title ?? '…' }}
          <span v-if="contract" class="mx-2 text-gray-300">|</span>
          <span v-if="contract" class="text-xs">{{ statusLabel(contract.status) }}</span>
        </p>
      </div>
      <button v-if="canCreate" type="button" class="btn btn-primary text-sm" @click="openCreate">+ بند جديد</button>
    </div>

    <div
      v-if="contract?.status === 'active' && items.length === 0 && !loading"
      class="rounded-xl border border-amber-300/80 bg-amber-50 dark:bg-amber-950/30 dark:border-amber-800 p-4 text-sm text-amber-900 dark:text-amber-100 leading-relaxed"
    >
      <strong>تنبيه تشغيلي:</strong> هذا العقد <em>نشط</em> وليس عليه أي بنود كتالوج بعد. العملاء المربوطون به عبر
      «عقد التسعير» سيرون <strong>كتالوجًا فارغًا</strong> في بوابة الأسطول — هذا سلوك حوكمي متوقع حتى تُضاف بنود.
    </div>

    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
      <div class="card p-3 text-center">
        <p class="text-2xl font-bold text-green-700 dark:text-green-300">{{ kpi.active }}</p>
        <p class="text-xs text-gray-500">بنود نشطة</p>
      </div>
      <div class="card p-3 text-center">
        <p class="text-2xl font-bold text-slate-800 dark:text-slate-200">{{ kpi.vehicleScoped }}</p>
        <p class="text-xs text-gray-500">مقيّدة بمركبات</p>
      </div>
      <div class="card p-3 text-center">
        <p class="text-2xl font-bold text-slate-800 dark:text-slate-200">{{ kpi.branchScoped }}</p>
        <p class="text-xs text-gray-500">مقيّدة بفرع</p>
      </div>
      <div class="card p-3 text-center">
        <p class="text-2xl font-bold text-slate-800 dark:text-slate-200">{{ kpi.withCap }}</p>
        <p class="text-xs text-gray-500">لها سقف كمية</p>
      </div>
      <div class="card p-3 text-center">
        <p class="text-2xl font-bold text-gray-600 dark:text-gray-400">{{ kpi.inactive }}</p>
        <p class="text-xs text-gray-500">غير نشطة</p>
      </div>
    </div>

    <div class="flex flex-wrap gap-2 items-center">
      <input v-model="filterService" type="text" placeholder="تصفية باسم/كود خدمة…" class="field text-sm w-52" />
      <select v-model="filterStatus" class="field text-sm w-32">
        <option value="">كل الحالات</option>
        <option value="active">نشط</option>
        <option value="inactive">غير نشط</option>
      </select>
      <select v-model="filterBranch" class="field text-sm w-40">
        <option value="">كل الفروع</option>
        <option value="__scoped__">بفرع محدد فقط</option>
        <option v-for="b in branches" :key="b.id" :value="String(b.id)">{{ b.name }}</option>
      </select>
      <select v-model="filterVehicleScope" class="field text-sm w-44">
        <option value="">نطاق المركبات — الكل</option>
        <option value="all">جميع المركبات</option>
        <option value="some">مركبات محددة</option>
      </select>
      <input v-model="filterPriority" type="number" placeholder="أولوية ≥" class="field text-sm w-28" />
    </div>

    <div class="card overflow-hidden">
      <div v-if="loading" class="py-10 text-center"><div class="spinner mx-auto"></div></div>
      <table v-else class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50 dark:bg-slate-700/40 text-right border-b dark:border-slate-700">
            <th class="px-3 py-2 font-medium">الخدمة</th>
            <th class="px-3 py-2 font-medium">السعر / الضريبة / الخصم</th>
            <th class="px-3 py-2 font-medium">الفرع</th>
            <th class="px-3 py-2 font-medium">المركبات</th>
            <th class="px-3 py-2 font-medium">السقف</th>
            <th class="px-3 py-2 font-medium">الحالة</th>
            <th class="px-3 py-2 font-medium">أولوية</th>
            <th class="px-3 py-2 font-medium">موافقة</th>
            <th class="px-3 py-2 font-medium">تحديث</th>
            <th class="px-3 py-2"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-slate-700/50">
          <tr v-for="row in filteredItems" :key="row.id" class="hover:bg-gray-50 dark:hover:bg-slate-700/30 align-top">
            <td class="px-3 py-2">
              <p class="font-medium text-gray-900 dark:text-white">{{ row.service?.name_ar || row.service?.name || '—' }}</p>
              <p class="text-[11px] text-gray-400 dir-ltr">{{ row.service?.code }}</p>
            </td>
            <td class="px-3 py-2 whitespace-nowrap">
              {{ fmtMoney(row.unit_price) }}
              <span class="text-gray-400"> / </span>{{ row.tax_rate != null ? row.tax_rate + '%' : '—' }}
              <span class="text-gray-400"> / </span>{{ fmtMoney(row.discount_amount) }}
            </td>
            <td class="px-3 py-2">{{ row.branch ? row.branch.name : 'كل الفروع' }}</td>
            <td class="px-3 py-2 text-xs">{{ row.applies_to_all_vehicles ? 'جميع المركبات' : (row.vehicle_ids?.length ?? 0) + ' مركبة' }}</td>
            <td class="px-3 py-2">{{ row.max_total_quantity != null ? row.max_total_quantity : '—' }}</td>
            <td class="px-3 py-2">
              <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="row.status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900/40' : 'bg-gray-200 text-gray-600 dark:bg-slate-600'">
                {{ row.status === 'active' ? 'نشط' : 'غير نشط' }}
              </span>
            </td>
            <td class="px-3 py-2">{{ row.priority ?? 0 }}</td>
            <td class="px-3 py-2">{{ row.requires_internal_approval ? 'نعم' : 'لا' }}</td>
            <td class="px-3 py-2 text-xs text-gray-500">{{ fmtDateTime(row.updated_at) }}</td>
            <td class="px-3 py-2 whitespace-nowrap">
              <button v-if="canUpdate" type="button" class="text-xs text-primary-600 hover:underline ml-2" @click="openEdit(row)">تعديل</button>
              <button v-if="canMatchPreview" type="button" class="text-xs text-primary-600 hover:underline ml-2" @click="openTestForRow(row)">اختبر</button>
              <button v-if="canDelete" type="button" class="text-xs text-red-600 hover:underline" @click="deleteRow(row)">حذف</button>
            </td>
          </tr>
          <tr v-if="!filteredItems.length">
            <td colspan="10" class="text-center py-8 text-gray-400">لا توجد بنود مطابقة للفلاتر</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Form modal -->
    <div
      v-if="formOpen"
      class="fixed inset-0 bg-black/50 flex items-start justify-center z-50 p-4 overflow-y-auto"
      @click.self="formOpen = false"
    >
      <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-lg my-6 max-h-[92vh] overflow-y-auto">
        <div class="p-5 border-b dark:border-slate-700 sticky top-0 bg-white dark:bg-slate-800 z-10">
          <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ editingId ? 'تعديل بند' : 'بند جديد' }}</h3>
        </div>
        <div class="p-5 space-y-4">
          <div>
            <label class="label">الخدمة (بحث)</label>
            <input
              v-model="serviceQuery"
              type="text"
              class="field"
              placeholder="اسم أو كود…"
              :disabled="!!editingId"
              @input="onServiceSearchInput"
            />
            <ul v-if="serviceHits.length && !editingId" class="mt-1 border rounded-lg max-h-40 overflow-y-auto text-xs dark:border-slate-600">
              <li
                v-for="s in serviceHits"
                :key="s.id"
                class="px-3 py-2 hover:bg-gray-50 dark:hover:bg-slate-700 cursor-pointer border-b border-gray-100 dark:border-slate-700 last:border-0"
                @click="pickService(s)"
              >
                <span class="font-medium">{{ s.name_ar || s.name }}</span>
                <span class="text-gray-400 dir-ltr mr-2">{{ s.code }}</span>
              </li>
            </ul>
            <p v-if="pickedService" class="mt-1 text-xs text-gray-600">المختارة: {{ pickedService.name_ar || pickedService.name }} ({{ pickedService.code }})</p>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="label">سعر الوحدة *</label>
              <input v-model.number="form.unit_price" type="number" min="0" step="0.0001" class="field" />
            </div>
            <div>
              <label class="label">نسبة الضريبة %</label>
              <input v-model.number="form.tax_rate" type="number" min="0" max="100" step="0.01" class="field" />
            </div>
            <div>
              <label class="label">خصم على الوحدة</label>
              <input v-model.number="form.discount_amount" type="number" min="0" step="0.0001" class="field" />
            </div>
            <div>
              <label class="label">أولوية البند</label>
              <input v-model.number="form.priority" type="number" class="field" />
            </div>
          </div>
          <div>
            <label class="label">الفرع</label>
            <select v-model="form.branch_id" class="field">
              <option value="">كل الفروع</option>
              <option v-for="b in branches" :key="b.id" :value="String(b.id)">{{ b.name }}</option>
            </select>
          </div>
          <div>
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="form.applies_to_all_vehicles" type="checkbox" class="rounded" />
              <span class="text-sm">ينطبق على جميع مركبات العملاء المشمولين بالعقد</span>
            </label>
          </div>
          <div v-if="!form.applies_to_all_vehicles">
            <label class="label">إضافة مركبات (بحث باللوحة / الموديل)</label>
            <input v-model="vehicleQuery" type="text" class="field" placeholder="بحث…" @input="onVehicleSearchInput" />
            <ul v-if="vehicleHits.length" class="mt-1 border rounded-lg max-h-36 overflow-y-auto text-xs dark:border-slate-600">
              <li
                v-for="v in vehicleHits"
                :key="v.id"
                class="px-3 py-2 hover:bg-gray-50 dark:hover:bg-slate-700 cursor-pointer"
                @click="addVehicle(v)"
              >
                {{ v.plate_number }} — {{ v.make }} {{ v.model }}
              </li>
            </ul>
            <div class="flex flex-wrap gap-1 mt-2">
              <span
                v-for="id in form.vehicle_ids"
                :key="id"
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-[11px]"
              >
                {{ vehicleLabel(id) }}
                <button type="button" class="text-red-600" @click="removeVehicle(id)">×</button>
              </span>
            </div>
          </div>
          <div>
            <label class="label">حد أقصى للكمية الإجمالية (اختياري)</label>
            <input v-model.number="form.max_total_quantity" type="number" min="0" step="0.0001" class="field" />
            <p v-if="usedQtyPreview != null" class="text-[11px] text-amber-700 dark:text-amber-300 mt-1">
              مستهلك حاليًا في أوامر العمل: {{ usedQtyPreview }} — لا يجوز جعل السقف أقل من هذا عند التعديل.
            </p>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="label">الحالة</label>
              <select v-model="form.status" class="field">
                <option value="active">نشط</option>
                <option value="inactive">غير نشط (تعطيل)</option>
              </select>
            </div>
            <div class="flex items-end">
              <label class="flex items-center gap-2 cursor-pointer pb-2">
                <input v-model="form.requires_internal_approval" type="checkbox" class="rounded" />
                <span class="text-sm">يتطلب موافقة داخلية</span>
              </label>
            </div>
          </div>
          <div>
            <label class="label">ملاحظات</label>
            <textarea v-model="form.notes" rows="2" class="field" />
          </div>
          <p v-if="formError" class="text-xs text-red-600">{{ formError }}</p>
          <div class="flex flex-wrap gap-2 justify-between pt-2 border-t dark:border-slate-700">
            <button type="button" class="btn btn-secondary text-sm" @click="formOpen = false">إلغاء</button>
            <div class="flex gap-2">
              <button v-if="canMatchPreview" type="button" class="btn btn-secondary text-sm" @click="openTestFromForm">اختبر هذا البند</button>
              <button v-if="canSubmitForm" type="button" :disabled="saving" class="btn btn-primary text-sm" @click="saveForm">
                {{ saving ? '…' : 'حفظ' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Test (match) modal -->
    <div
      v-if="testOpen"
      class="fixed inset-0 bg-black/50 flex items-start justify-center z-[60] p-4 overflow-y-auto"
      @click.self="testOpen = false"
    >
      <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md my-10 p-5 space-y-4">
        <h3 class="font-bold text-lg text-gray-900 dark:text-white">اختبار انطباق البند</h3>
        <p class="text-xs text-gray-500">اختر عميلًا مربوطًا بهذا العقد (pricing_contract_id)، ثم مركبة من أسطوله، وفرعًا إن لزم.</p>
        <div>
          <label class="label">عميل (بحث)</label>
          <input v-model="testCustomerQ" class="field text-sm" @input="onTestCustomerInput" />
          <ul v-if="testCustomers.length" class="mt-1 border rounded-lg max-h-32 overflow-y-auto text-xs">
            <li
              v-for="c in testCustomers"
              :key="c.id"
              class="px-3 py-2 hover:bg-gray-50 dark:hover:bg-slate-700 cursor-pointer"
              @click="pickTestCustomer(c)"
            >
              {{ c.name }}
            </li>
          </ul>
        </div>
        <div>
          <label class="label">مركبة</label>
          <select v-model.number="testVehicleId" class="field text-sm" :disabled="!testCustomerId">
            <option :value="0">— اختر —</option>
            <option v-for="v in testVehicles" :key="v.id" :value="v.id">{{ v.plate_number }} — {{ v.make }}</option>
          </select>
        </div>
        <div>
          <label class="label">فرع السياق (اختياري)</label>
          <select v-model="testBranchId" class="field text-sm">
            <option value="">—</option>
            <option v-for="b in branches" :key="b.id" :value="String(b.id)">{{ b.name }}</option>
          </select>
        </div>
        <button type="button" :disabled="testLoading" class="btn btn-primary text-sm w-full" @click="runMatchPreview">تشغيل الاختبار</button>
        <div v-if="testResult" class="rounded-lg border dark:border-slate-600 p-3 text-sm space-y-2">
          <p>
            <strong>ينطبق؟</strong>
            {{ testResult.applies ? 'نعم' : 'لا' }}
          </p>
          <p class="text-xs text-gray-600 dark:text-slate-400 leading-relaxed">{{ testResult.reason_ar }}</p>
          <template v-if="testResult.applies">
            <p><strong>صافي سعر الوحدة:</strong> {{ fmtMoney(testResult.unit_price) }}</p>
            <p><strong>الضريبة:</strong> {{ testResult.tax_rate }}%</p>
            <p><strong>مصدر التسعير:</strong> {{ testResult.pricing_source_label_ar }}</p>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useApi } from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import { appConfirm } from '@/services/appConfirmDialog'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const api = useApi()
const toast = useToast()
const auth = useAuthStore()

const canCreate = computed(() => auth.hasPermission('contracts.service_items.create'))
const canUpdate = computed(() => auth.hasPermission('contracts.service_items.update'))
const canDelete = computed(() => auth.hasPermission('contracts.service_items.delete'))
const canMatchPreview = computed(() => auth.hasPermission('contracts.service_items.match_preview'))
const canSubmitForm = computed(() => (editingId.value ? canUpdate.value : canCreate.value))

const contractId = computed(() => Number(route.params.contractId))
const contract = ref<any>(null)
const items = ref<any[]>([])
const branches = ref<any[]>([])
const loading = ref(false)

const filterService = ref('')
const filterStatus = ref('')
const filterBranch = ref('')
const filterVehicleScope = ref('')
const filterPriority = ref<string>('')

const formOpen = ref(false)
const editingId = ref<number | null>(null)
const saving = ref(false)
const formError = ref('')
const pickedService = ref<any>(null)
const serviceQuery = ref('')
const serviceHits = ref<any[]>([])
let serviceSearchTimer: ReturnType<typeof setTimeout> | null = null

const vehicleQuery = ref('')
const vehicleHits = ref<any[]>([])

const vehicleCache = ref<Record<number, any>>({})
let vehicleSearchTimer: ReturnType<typeof setTimeout> | null = null

const usedQtyPreview = ref<number | null>(null)

const defaultForm = () => ({
  service_id: null as number | null,
  unit_price: null as number | null,
  tax_rate: null as number | null,
  discount_amount: 0,
  branch_id: '' as string,
  applies_to_all_vehicles: true,
  vehicle_ids: [] as number[],
  max_total_quantity: null as number | null,
  requires_internal_approval: false,
  status: 'active',
  priority: 0,
  notes: '',
})

const form = ref(defaultForm())

const testOpen = ref(false)
const testCustomerQ = ref('')
const testCustomers = ref<any[]>([])
const testCustomerId = ref<number | null>(null)
const testVehicles = ref<any[]>([])
const testVehicleId = ref(0)
const testBranchId = ref<string>('')
const testLoading = ref(false)
const testResult = ref<any>(null)
/** أثناء الاختبار من الجدول نرسل service_item_id؛ من النموذج نستخدم مسودة */
const testCtx = ref<{ mode: 'row' | 'form'; row?: any } | null>(null)
let testCustomerTimer: ReturnType<typeof setTimeout> | null = null

const kpi = computed(() => {
  const list = items.value
  return {
    active: list.filter((i) => i.status === 'active').length,
    vehicleScoped: list.filter((i) => !i.applies_to_all_vehicles).length,
    branchScoped: list.filter((i) => i.branch_id != null).length,
    withCap: list.filter((i) => i.max_total_quantity != null).length,
    inactive: list.filter((i) => i.status !== 'active').length,
  }
})

const filteredItems = computed(() => {
  let list = items.value.slice()
  const q = filterService.value.trim().toLowerCase()
  if (q) {
    list = list.filter((r) => {
      const name = (r.service?.name_ar || r.service?.name || '').toLowerCase()
      const code = String(r.service?.code || '').toLowerCase()
      return name.includes(q) || code.includes(q)
    })
  }
  if (filterStatus.value) list = list.filter((r) => r.status === filterStatus.value)
  if (filterBranch.value === '__scoped__') list = list.filter((r) => r.branch_id != null)
  else if (filterBranch.value) list = list.filter((r) => String(r.branch_id || '') === filterBranch.value)
  if (filterVehicleScope.value === 'all') list = list.filter((r) => r.applies_to_all_vehicles)
  if (filterVehicleScope.value === 'some') list = list.filter((r) => !r.applies_to_all_vehicles)
  const p = filterPriority.value.trim()
  if (p !== '' && !Number.isNaN(Number(p))) list = list.filter((r) => Number(r.priority ?? 0) >= Number(p))
  return list
})

function statusLabel(s: string) {
  return { draft: 'مسودة', pending_signature: 'بانتظار التوقيع', active: 'نشط', expired: 'منتهي', terminated: 'ملغي' }[s] ?? s
}

function fmtMoney(n: unknown) {
  if (n == null || n === '') return '—'
  return new Intl.NumberFormat('ar-SA', { minimumFractionDigits: 2, maximumFractionDigits: 4 }).format(Number(n))
}

function fmtDateTime(iso: string | undefined) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('ar-SA', { dateStyle: 'short', timeStyle: 'short' })
}

function paginatedList(r: any): any[] {
  const outer = r?.data ?? r
  if (Array.isArray(outer)) return outer
  return outer?.data ?? []
}

async function loadAll() {
  loading.value = true
  try {
    const id = contractId.value
    const [cRes, iRes, bRes] = await Promise.all([
      api.get(`/governance/contracts/${id}`),
      api.get(`/governance/contracts/${id}/service-items`),
      api.get('/branches', { per_page: 200 }),
    ])
    contract.value = cRes?.data ?? cRes
    items.value = Array.isArray(iRes?.data) ? iRes.data : iRes ?? []
    branches.value = paginatedList(bRes)
  } catch (e: any) {
    toast.error(e.response?.data?.message ?? 'تعذر التحميل')
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingId.value = null
  form.value = defaultForm()
  pickedService.value = null
  serviceQuery.value = ''
  serviceHits.value = []
  vehicleHits.value = []
  usedQtyPreview.value = null
  formError.value = ''
  formOpen.value = true
}

async function openEdit(row: any) {
  editingId.value = row.id
  form.value = {
    service_id: row.service_id,
    unit_price: Number(row.unit_price),
    tax_rate: row.tax_rate != null ? Number(row.tax_rate) : null,
    discount_amount: Number(row.discount_amount ?? 0),
    branch_id: row.branch_id != null ? String(row.branch_id) : '',
    applies_to_all_vehicles: !!row.applies_to_all_vehicles,
    vehicle_ids: Array.isArray(row.vehicle_ids) ? row.vehicle_ids.map(Number) : [],
    max_total_quantity: row.max_total_quantity != null ? Number(row.max_total_quantity) : null,
    requires_internal_approval: !!row.requires_internal_approval,
    status: row.status || 'active',
    priority: row.priority ?? 0,
    notes: row.notes || '',
  }
  pickedService.value = row.service ?? { id: row.service_id }
  serviceQuery.value = ''
  formError.value = ''
  formOpen.value = true
  usedQtyPreview.value = null
  try {
    const u = await api.get(`/governance/contracts/${contractId.value}/service-items/${row.id}/usage`)
    usedQtyPreview.value = Number(u?.data?.used_quantity ?? 0)
  } catch {
    usedQtyPreview.value = null
  }
  for (const vid of form.value.vehicle_ids) {
    void ensureVehicleLabel(vid)
  }
}

function onServiceSearchInput() {
  if (editingId.value) return
  if (serviceSearchTimer) clearTimeout(serviceSearchTimer)
  serviceSearchTimer = setTimeout(async () => {
    const q = serviceQuery.value.trim()
    if (q.length < 1) {
      serviceHits.value = []
      return
    }
    try {
      const r = await api.get('/services', { search: q, per_page: 15, is_active: true })
      serviceHits.value = paginatedList(r)
    } catch {
      serviceHits.value = []
    }
  }, 300)
}

function pickService(s: any) {
  pickedService.value = s
  form.value.service_id = s.id
  if (form.value.tax_rate == null && s.tax_rate != null) form.value.tax_rate = Number(s.tax_rate)
  serviceHits.value = []
  serviceQuery.value = s.name_ar || s.name
}

function onVehicleSearchInput() {
  if (vehicleSearchTimer) clearTimeout(vehicleSearchTimer)
  vehicleSearchTimer = setTimeout(async () => {
    const q = vehicleQuery.value.trim()
    if (q.length < 1) {
      vehicleHits.value = []
      return
    }
    try {
      const r = await api.get('/vehicles', { search: q, per_page: 15 })
      vehicleHits.value = paginatedList(r)
    } catch {
      vehicleHits.value = []
    }
  }, 320)
}

function addVehicle(v: any) {
  vehicleCache.value[v.id] = v
  if (!form.value.vehicle_ids.includes(v.id)) form.value.vehicle_ids.push(v.id)
  vehicleHits.value = []
  vehicleQuery.value = ''
}

function removeVehicle(id: number) {
  form.value.vehicle_ids = form.value.vehicle_ids.filter((x) => x !== id)
}

async function ensureVehicleLabel(id: number) {
  if (vehicleCache.value[id]) return
  try {
    const r = await api.get(`/vehicles/${id}`)
    const v = r?.data ?? r
    if (v?.id) vehicleCache.value[v.id] = v
  } catch { /* */ }
}

function vehicleLabel(id: number) {
  const v = vehicleCache.value[id]
  return v ? `${v.plate_number}` : `#${id}`
}

function validateFormLocal(): string | null {
  if (!form.value.service_id) return 'اختر خدمة.'
  if (form.value.unit_price == null || form.value.unit_price <= 0) return 'سعر الوحدة يجب أن يكون أكبر من صفر.'
  const disc = Number(form.value.discount_amount ?? 0)
  if (disc > Number(form.value.unit_price) + 0.0001) return 'الخصم لا يجوز أن يتجاوز سعر الوحدة.'
  if (!form.value.applies_to_all_vehicles && form.value.vehicle_ids.length === 0) {
    return 'عند تقييد المركبات أضف مركبة واحدة على الأقل (بحث أعلاه).'
  }
  return null
}

async function saveForm() {
  formError.value = ''
  const err = validateFormLocal()
  if (err) {
    formError.value = err
    return
  }
  saving.value = true
  const body: any = {
    service_id: form.value.service_id,
    unit_price: form.value.unit_price,
    tax_rate: form.value.tax_rate,
    discount_amount: form.value.discount_amount ?? 0,
    branch_id: form.value.branch_id === '' ? null : Number(form.value.branch_id),
    applies_to_all_vehicles: form.value.applies_to_all_vehicles,
    vehicle_ids: form.value.applies_to_all_vehicles ? null : form.value.vehicle_ids,
    max_total_quantity: form.value.max_total_quantity,
    requires_internal_approval: form.value.requires_internal_approval,
    status: form.value.status,
    priority: form.value.priority,
    notes: form.value.notes || null,
  }
  try {
    if (editingId.value) {
      await api.put(`/governance/contracts/${contractId.value}/service-items/${editingId.value}`, body)
      toast.success('تم تحديث البند')
    } else {
      await api.post(`/governance/contracts/${contractId.value}/service-items`, body)
      toast.success('تم إنشاء البند')
    }
    formOpen.value = false
    await loadAll()
  } catch (e: any) {
    const msg = e.response?.data?.message
    const errs = e.response?.data?.errors
    if (errs && typeof errs === 'object') {
      formError.value = Object.values(errs).flat().join(' — ')
    } else {
      formError.value = msg ?? 'فشل الحفظ'
    }
    toast.error(formError.value)
  } finally {
    saving.value = false
  }
}

async function deleteRow(row: any) {
  const ok = await appConfirm({
    title: 'حذف البند',
    message: `حذف بند «${row.service?.name_ar || row.service?.name}»؟ لا يمكن الحذف إن وُجد استهلاك في أوامر عمل.`,
    variant: 'danger',
    confirmLabel: 'حذف',
  })
  if (!ok) return
  try {
    await api.del(`/governance/contracts/${contractId.value}/service-items/${row.id}`)
    toast.success('تم الحذف')
    await loadAll()
  } catch (e: any) {
    toast.error(e.response?.data?.message ?? 'تعذر الحذف')
  }
}

function openTestForRow(row: any) {
  testCtx.value = { mode: 'row', row }
  testOpen.value = true
  testResult.value = null
  testCustomerId.value = null
  testVehicleId.value = 0
  testVehicles.value = []
  testCustomerQ.value = ''
  testCustomers.value = []
  testBranchId.value = row.branch_id != null ? String(row.branch_id) : ''
}

function openTestFromForm() {
  const err = validateFormLocal()
  if (err) {
    toast.error(err)
    return
  }
  testCtx.value = { mode: 'form' }
  testOpen.value = true
  testResult.value = null
  testCustomerId.value = null
  testVehicleId.value = 0
  testVehicles.value = []
  testCustomerQ.value = ''
  testCustomers.value = []
  testBranchId.value = form.value.branch_id || ''
}

function onTestCustomerInput() {
  if (testCustomerTimer) clearTimeout(testCustomerTimer)
  testCustomerTimer = setTimeout(async () => {
    const q = testCustomerQ.value.trim()
    if (q.length < 1) {
      testCustomers.value = []
      return
    }
    try {
      const r = await api.get('/customers', { search: q, per_page: 15 })
      testCustomers.value = paginatedList(r)
    } catch {
      testCustomers.value = []
    }
  }, 300)
}

function pickTestCustomer(c: any) {
  testCustomerId.value = c.id
  testCustomerQ.value = c.name
  testCustomers.value = []
  testVehicleId.value = 0
  void loadTestVehicles(c.id)
}

async function loadTestVehicles(customerId: number) {
  try {
    const r = await api.get('/vehicles', { customer_id: customerId, per_page: 100 })
    testVehicles.value = paginatedList(r)
  } catch {
    testVehicles.value = []
  }
}

async function runMatchPreview() {
  const ctx = testCtx.value
  if (!ctx || !testCustomerId.value || !testVehicleId.value) {
    toast.error('اختر عميلًا ومركبة.')
    return
  }
  const sid =
    ctx.mode === 'row'
      ? ctx.row!.service_id
      : pickedService.value?.id ?? form.value.service_id
  if (!sid) {
    toast.error('لا توجد خدمة.')
    return
  }
  testLoading.value = true
  testResult.value = null
  try {
    const base: any = {
      customer_id: testCustomerId.value,
      vehicle_id: testVehicleId.value,
      branch_id: testBranchId.value === '' ? null : Number(testBranchId.value),
      service_id: sid,
    }
    if (ctx.mode === 'row') {
      base.service_item_id = ctx.row!.id
    } else {
      base.draft = {
        branch_id: form.value.branch_id === '' ? null : Number(form.value.branch_id),
        unit_price: form.value.unit_price,
        tax_rate: form.value.tax_rate,
        discount_amount: form.value.discount_amount ?? 0,
        applies_to_all_vehicles: form.value.applies_to_all_vehicles,
        vehicle_ids: form.value.applies_to_all_vehicles ? null : form.value.vehicle_ids,
      }
    }
    const res = await api.post(`/governance/contracts/${contractId.value}/service-items/match-preview`, base)
    testResult.value = res?.data ?? res
  } catch (e: any) {
    toast.error(e.response?.data?.message ?? 'فشل الاختبار')
  } finally {
    testLoading.value = false
  }
}

onMounted(() => {
  void loadAll()
})
</script>
