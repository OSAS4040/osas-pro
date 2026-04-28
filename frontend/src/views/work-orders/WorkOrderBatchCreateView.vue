<template>
  <div class="app-shell-page max-w-4xl" dir="rtl">
    <div class="flex items-center justify-between gap-4 mb-6">
      <div>
        <RouterLink to="/work-orders" class="text-sm text-primary-600 hover:underline">← أوامر العمل</RouterLink>
        <h2 class="page-title-xl mt-1">دفعة أوامر عمل (عدة مركبات)</h2>
        <p class="page-subtitle text-sm text-gray-500 dark:text-slate-400 mt-1">
          اختر العميل ثم المركبة من القوائم الذكية (بحث بالاسم، اللوحة، الهاتف…). كل سطر يُنفَّذ كأمر مستقل.
        </p>
      </div>
    </div>

    <div class="space-y-4">
      <div
        v-for="(line, idx) in lines"
        :key="line._uid"
        class="rounded-xl border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-900 p-4 grid gap-4 md:grid-cols-2"
      >
        <!-- عميل -->
        <div class="relative" :data-cust="idx">
          <label class="block text-xs font-medium text-gray-600 dark:text-slate-300 mb-1">العميل</label>
          <div class="relative">
            <input
              v-model="line.customerQ"
              type="search"
              enterkeyhint="search"
              autocomplete="off"
              class="w-full border border-gray-300 dark:border-slate-600 rounded-lg py-2.5 ps-3 pe-10 text-sm bg-white dark:bg-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 outline-none"
              placeholder="ابحث بالاسم أو الجوال أو البريد…"
              @focus="line.customerOpen = true"
              @input="scheduleCustomerSearch(idx)"
            />
            <span
              class="pointer-events-none absolute inset-y-0 end-2 flex items-center text-gray-400 text-xs font-medium"
              aria-hidden="true"
            >
              ⌕
            </span>
          </div>
          <ul
            v-show="line.customerOpen && (line.customerLoading || line.customerHits.length > 0 || customerEmptyHint(line))"
            class="absolute z-40 mt-1 w-full max-h-56 overflow-y-auto rounded-lg border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-900 shadow-lg"
            role="listbox"
          >
            <li v-if="line.customerLoading" class="px-3 py-2.5 text-sm text-gray-500">جارٍ البحث…</li>
            <li
              v-for="c in line.customerHits"
              :key="c.id"
              role="option"
              class="px-3 py-2.5 text-sm cursor-pointer hover:bg-gray-50 dark:hover:bg-slate-800 border-b border-gray-50 dark:border-slate-800 last:border-0"
              @mousedown.prevent="pickCustomer(idx, c)"
            >
              <span class="font-medium text-gray-900 dark:text-slate-100">{{ c.name }}</span>
              <span v-if="c.phone" class="block text-xs text-gray-500 mt-0.5" dir="ltr">{{ c.phone }}</span>
            </li>
            <li
              v-if="!line.customerLoading && line.customerQ.trim().length >= 1 && line.customerHits.length === 0"
              class="px-3 py-2.5 text-sm text-gray-500"
            >
              لا توجد نتائج
            </li>
          </ul>
          <p v-if="line.customerId" class="mt-1.5 text-xs text-teal-700 dark:text-teal-400">
            المختار: <strong>{{ line.customerDisplay }}</strong>
            <button type="button" class="text-primary-600 hover:underline ms-2" @click="clearCustomer(idx)">تغيير</button>
          </p>
        </div>

        <!-- مركبة -->
        <div class="relative" :data-veh="idx">
          <label class="block text-xs font-medium text-gray-600 dark:text-slate-300 mb-1">المركبة</label>
          <div class="relative">
            <input
              v-model="line.vehicleQ"
              type="search"
              enterkeyhint="search"
              autocomplete="off"
              :disabled="!line.customerId"
              class="w-full border border-gray-300 dark:border-slate-600 rounded-lg py-2.5 ps-3 pe-10 text-sm bg-white dark:bg-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 outline-none disabled:opacity-50 disabled:cursor-not-allowed"
              :placeholder="line.customerId ? 'ابحث باللوحة، الطراز، الماركة…' : 'اختر العميل أولاً'"
              @focus="onVehicleFocus(idx)"
              @input="scheduleVehicleSearch(idx)"
            />
            <span
              class="pointer-events-none absolute inset-y-0 end-2 flex items-center text-gray-400 text-xs font-medium"
              aria-hidden="true"
            >
              ⌕
            </span>
          </div>
          <ul
            v-show="line.vehicleOpen && line.customerId && (line.vehicleLoading || line.vehicleHits.length > 0 || vehicleEmptyHint(line))"
            class="absolute z-40 mt-1 w-full max-h-56 overflow-y-auto rounded-lg border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-900 shadow-lg"
            role="listbox"
          >
            <li v-if="line.vehicleLoading" class="px-3 py-2.5 text-sm text-gray-500">جارٍ التحميل…</li>
            <li
              v-for="v in line.vehicleHits"
              :key="v.id"
              role="option"
              class="px-3 py-2.5 text-sm cursor-pointer hover:bg-gray-50 dark:hover:bg-slate-800 border-b border-gray-50 dark:border-slate-800 last:border-0"
              @mousedown.prevent="pickVehicle(idx, v)"
            >
              <span class="font-mono font-medium text-gray-900 dark:text-slate-100">{{ v.plate_number || '—' }}</span>
              <span class="block text-xs text-gray-600 dark:text-slate-300">{{ v.make }} {{ v.model }}</span>
            </li>
            <li
              v-if="!line.vehicleLoading && line.customerId && line.vehicleHits.length === 0 && line.vehicleQ.trim().length >= 1"
              class="px-3 py-2.5 text-sm text-gray-500"
            >
              لا توجد مركبات مطابقة لهذا العميل
            </li>
          </ul>
          <p v-if="line.vehicleId" class="mt-1.5 text-xs text-teal-700 dark:text-teal-400">
            المختار: <strong>{{ line.vehicleDisplay }}</strong>
            <button type="button" class="text-primary-600 hover:underline ms-2" @click="clearVehicle(idx)">تغيير</button>
          </p>
        </div>

        <div class="md:col-span-2 flex justify-end">
          <button
            v-if="lines.length > 1"
            type="button"
            class="text-xs text-red-600 hover:underline"
            @click="removeLine(idx)"
          >
            حذف السطر
          </button>
        </div>
      </div>
      <button type="button" class="btn btn-secondary text-sm" @click="addLine">+ إضافة مركبة</button>
    </div>

    <div class="mt-6 flex flex-wrap gap-3">
      <button type="button" class="btn btn-primary" :disabled="busy" @click="openPreview">
        {{ busy ? 'جارٍ المعاينة...' : 'مراجعة ثم التنفيذ' }}
      </button>
    </div>
    <p
      v-if="pageError"
      class="mt-3 text-sm rounded-lg px-3 py-2"
      :class="pageErrorIsSuccess ? 'bg-emerald-50 text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200' : 'text-red-600 bg-red-50 dark:bg-red-950/30'"
    >
      {{ pageError }}
    </p>

    <SensitiveOperationReviewModal
      v-model="reviewOpen"
      :summary="reviewSummary"
      :loading="reviewLoading"
      :error="reviewError"
      confirm-text="تنفيذ الدفعة"
      title="مراجعة دفعة أوامر العمل"
      @confirm="executeBatch"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { RouterLink } from 'vue-router'
import apiClient from '@/lib/apiClient'
import { summarizeAxiosError } from '@/utils/apiErrorSummary'
import SensitiveOperationReviewModal from '@/components/SensitiveOperationReviewModal.vue'

type CustomerHit = { id: number; name: string; phone?: string | null }
type VehicleHit = { id: number; plate_number?: string | null; make?: string | null; model?: string | null; customer_id?: number }

type BatchLine = {
  _uid: number
  customerId: number | null
  vehicleId: number | null
  customerDisplay: string
  vehicleDisplay: string
  customerQ: string
  vehicleQ: string
  customerHits: CustomerHit[]
  vehicleHits: VehicleHit[]
  customerOpen: boolean
  vehicleOpen: boolean
  customerLoading: boolean
  vehicleLoading: boolean
}

let lineUid = 0
function newLine(): BatchLine {
  return {
    _uid: ++lineUid,
    customerId: null,
    vehicleId: null,
    customerDisplay: '',
    vehicleDisplay: '',
    customerQ: '',
    vehicleQ: '',
    customerHits: [],
    vehicleHits: [],
    customerOpen: false,
    vehicleOpen: false,
    customerLoading: false,
    vehicleLoading: false,
  }
}

function extractPaginatedList(res: { data?: { data?: { data?: unknown[] } | unknown[] } }): unknown[] {
  const root = res.data?.data
  if (Array.isArray(root)) return root
  if (root && typeof root === 'object' && 'data' in root && Array.isArray((root as { data: unknown[] }).data)) {
    return (root as { data: unknown[] }).data
  }
  return []
}

function formatVehicle(v: VehicleHit): string {
  const p = v.plate_number || ''
  const mm = [v.make, v.model].filter(Boolean).join(' ')
  return mm ? `${p} — ${mm}` : p || `#${v.id}`
}

const lines = ref<BatchLine[]>([newLine()])
const busy = ref(false)
const pageError = ref('')
const reviewOpen = ref(false)
const reviewSummary = ref<Record<string, unknown> | null>(null)
const reviewToken = ref('')
const reviewLoading = ref(false)
const reviewError = ref('')

const pageErrorIsSuccess = computed(() => pageError.value.includes('نجاح'))

const customerTimers = new Map<number, ReturnType<typeof setTimeout>>()
const vehicleTimers = new Map<number, ReturnType<typeof setTimeout>>()

function customerEmptyHint(line: BatchLine): boolean {
  return !line.customerLoading && line.customerQ.trim().length >= 1 && line.customerHits.length === 0
}

function vehicleEmptyHint(line: BatchLine): boolean {
  return (
    !line.vehicleLoading &&
    !!line.customerId &&
    line.vehicleQ.trim().length >= 1 &&
    line.vehicleHits.length === 0
  )
}

function onDocClick(e: MouseEvent) {
  const el = e.target as HTMLElement
  lines.value.forEach((_, idx) => {
    if (!el.closest(`[data-cust="${idx}"]`)) {
      lines.value[idx].customerOpen = false
    }
    if (!el.closest(`[data-veh="${idx}"]`)) {
      lines.value[idx].vehicleOpen = false
    }
  })
}

onMounted(() => {
  document.addEventListener('click', onDocClick)
})

onUnmounted(() => {
  document.removeEventListener('click', onDocClick)
  customerTimers.forEach((t) => clearTimeout(t))
  vehicleTimers.forEach((t) => clearTimeout(t))
})

function addLine() {
  lines.value.push(newLine())
}

function removeLine(i: number) {
  lines.value.splice(i, 1)
}

function clearCustomer(idx: number) {
  const line = lines.value[idx]
  line.customerId = null
  line.customerDisplay = ''
  line.customerQ = ''
  line.customerHits = []
  line.vehicleId = null
  line.vehicleDisplay = ''
  line.vehicleQ = ''
  line.vehicleHits = []
}

function clearVehicle(idx: number) {
  const line = lines.value[idx]
  line.vehicleId = null
  line.vehicleDisplay = ''
  line.vehicleQ = ''
  line.vehicleHits = []
}

function pickCustomer(idx: number, c: CustomerHit) {
  const line = lines.value[idx]
  line.customerId = c.id
  line.customerDisplay = c.name
  line.customerQ = c.name
  line.customerHits = []
  line.customerOpen = false
  line.vehicleId = null
  line.vehicleDisplay = ''
  line.vehicleQ = ''
  line.vehicleHits = []
}

function pickVehicle(idx: number, v: VehicleHit) {
  const line = lines.value[idx]
  line.vehicleId = v.id
  line.vehicleDisplay = formatVehicle(v)
  line.vehicleQ = formatVehicle(v)
  line.vehicleHits = []
  line.vehicleOpen = false
}

function scheduleCustomerSearch(idx: number) {
  const prev = customerTimers.get(idx)
  if (prev) clearTimeout(prev)
  customerTimers.set(
    idx,
    setTimeout(() => {
      void runCustomerSearch(idx)
    }, 280),
  )
}

async function runCustomerSearch(idx: number) {
  const line = lines.value[idx]
  const q = line.customerQ.trim()
  line.customerOpen = true
  if (q.length < 1) {
    line.customerHits = []
    line.customerLoading = false
    return
  }
  line.customerLoading = true
  try {
    const { data } = await apiClient.get('/customers', {
      params: { search: q, per_page: 20 },
      skipGlobalErrorToast: true,
    })
    line.customerHits = extractPaginatedList({ data }) as CustomerHit[]
  } catch {
    line.customerHits = []
  } finally {
    line.customerLoading = false
  }
}

function onVehicleFocus(idx: number) {
  const line = lines.value[idx]
  if (!line.customerId) return
  line.vehicleOpen = true
  if (line.vehicleQ.trim() === '' && line.vehicleHits.length === 0) {
    void loadVehiclesForCustomer(idx, '')
  }
}

function scheduleVehicleSearch(idx: number) {
  const line = lines.value[idx]
  if (!line.customerId) return
  const prev = vehicleTimers.get(idx)
  if (prev) clearTimeout(prev)
  vehicleTimers.set(
    idx,
    setTimeout(() => {
      void loadVehiclesForCustomer(idx, line.vehicleQ.trim())
    }, 280),
  )
}

async function loadVehiclesForCustomer(idx: number, search: string) {
  const line = lines.value[idx]
  if (!line.customerId) return
  line.vehicleOpen = true
  line.vehicleLoading = true
  try {
    const params: Record<string, string | number> = {
      customer_id: line.customerId,
      per_page: 50,
    }
    if (search.length >= 1) {
      params.search = search
    }
    const { data } = await apiClient.get('/vehicles', {
      params,
      skipGlobalErrorToast: true,
    })
    line.vehicleHits = extractPaginatedList({ data }) as VehicleHit[]
  } catch {
    line.vehicleHits = []
  } finally {
    line.vehicleLoading = false
  }
}

function buildPayload() {
  return lines.value
    .filter((l) => l.customerId && l.vehicleId)
    .map((l) => ({
      customer_id: Number(l.customerId),
      vehicle_id: Number(l.vehicleId),
      items: [] as unknown[],
    }))
}

async function openPreview() {
  pageError.value = ''
  const payload = buildPayload()
  if (!payload.length) {
    pageError.value = 'أكمل اختيار عميل ومركبة لكل سطر على الأقل (من القوائم وليس يدوياً).'
    return
  }
  reviewLoading.value = true
  reviewError.value = ''
  reviewSummary.value = null
  reviewToken.value = ''
  reviewOpen.value = true
  busy.value = true
  try {
    const { data } = await apiClient.post('/sensitive-operations/preview', {
      operation: 'work_order_batch_create',
      lines: payload,
    })
    reviewSummary.value = data.data as Record<string, unknown>
    reviewToken.value = String((data.data as { sensitive_preview_token?: string })?.sensitive_preview_token ?? '')
  } catch (e: unknown) {
    reviewError.value = summarizeAxiosError(e)
  } finally {
    reviewLoading.value = false
    busy.value = false
  }
}

async function executeBatch() {
  const payload = buildPayload()
  if (!reviewToken.value) return
  busy.value = true
  reviewError.value = ''
  try {
    const { data } = await apiClient.post('/work-orders/batches', {
      sensitive_preview_token: reviewToken.value,
      lines: payload,
    })
    reviewOpen.value = false
    const batch = data.data as { items?: Array<{ status?: string }> }
    const failed = batch?.items?.filter((i) => i.status === 'failed') ?? []
    pageError.value = failed.length
      ? `اكتملت الدفعة مع فشل ${failed.length} سطر(ات). راجع التفاصيل في الاستجابة.`
      : 'تم إنشاء الدفعة بنجاح.'
  } catch (e: unknown) {
    reviewError.value = summarizeAxiosError(e)
  } finally {
    busy.value = false
  }
}
</script>
