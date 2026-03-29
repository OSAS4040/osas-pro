<template>
  <div class="space-y-6" dir="rtl">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <CreditCardIcon class="w-7 h-7 text-primary-600" />
          إدارة المحافظ
        </h1>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">إدارة أرصدة ومحافظ العملاء</p>
      </div>
      <button @click="showTopUp = true"
        class="flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-xl text-sm font-semibold hover:bg-primary-700 transition-colors shadow-sm">
        <PlusCircleIcon class="w-5 h-5" />
        شحن رصيد
      </button>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <template v-if="loading">
        <div v-for="i in 4" :key="i" class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 p-5 shadow-sm animate-pulse">
          <div class="h-10 w-10 rounded-xl bg-gray-200 dark:bg-slate-600 mb-3" />
          <div class="h-8 bg-gray-200 dark:bg-slate-600 rounded w-2/3 mb-2" />
          <div class="h-3 bg-gray-100 dark:bg-slate-700 rounded w-1/2" />
        </div>
      </template>
      <template v-else>
      <div v-for="wt in walletTypes" :key="wt.key"
        class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 p-5 shadow-sm transition-shadow hover:shadow-md">
        <div class="flex items-center justify-between mb-3">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center" :class="wt.bg">
            <component :is="wt.icon" class="w-5 h-5" :class="wt.iconColor" />
          </div>
          <span class="text-xs font-medium px-2 py-0.5 rounded-full" :class="wt.badge">{{ wt.label }}</span>
        </div>
        <p class="text-2xl font-bold text-gray-900 dark:text-white tabular-nums">{{ fmt(totals[wt.key] ?? 0) }}</p>
        <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">إجمالي {{ wt.label }}</p>
      </div>
      </template>
    </div>

    <!-- Search + Filter -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 p-4 shadow-sm">
      <div class="flex flex-wrap gap-3 items-center">
        <div class="flex-1 min-w-[200px] relative">
          <MagnifyingGlassIcon class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
          <input v-model="search" @input="debouncedLoad" placeholder="بحث بالاسم أو الهاتف..."
            class="w-full pr-9 py-2 border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent" />
        </div>
        <select v-model="filterType" @change="load"
          class="px-3 py-2 border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl text-sm focus:ring-2 focus:ring-primary-500">
          <option value="">كل الأنواع</option>
          <option value="cash">نقدية</option>
          <option value="promotional">ترويجية</option>
          <option value="reserved">محجوزة</option>
          <option value="credit">ائتمان</option>
        </select>
        <select v-model="filterStatus" @change="load"
          class="px-3 py-2 border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl text-sm focus:ring-2 focus:ring-primary-500">
          <option value="">كل الحالات</option>
          <option value="active">نشطة</option>
          <option value="frozen">مجمّدة</option>
          <option value="closed">مغلقة</option>
        </select>
      </div>
    </div>

    <!-- Wallets Table -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
        <h3 class="font-semibold text-gray-800 dark:text-white">قائمة المحافظ</h3>
        <span class="text-xs text-gray-400 dark:text-slate-500">{{ wallets.length }} محفظة</span>
      </div>

      <div v-if="loading" class="px-2 py-4">
        <div class="space-y-3">
          <div v-for="n in 5" :key="n" class="flex gap-4 px-4 py-3 border-b border-gray-50 dark:border-slate-700/50 last:border-0">
            <div class="h-10 w-10 rounded-full bg-gray-100 dark:bg-slate-700 animate-pulse flex-shrink-0" />
            <div class="flex-1 space-y-2 pt-1">
              <div class="h-3 bg-gray-100 dark:bg-slate-700 rounded w-1/3 animate-pulse" />
              <div class="h-3 bg-gray-50 dark:bg-slate-800 rounded w-1/2 animate-pulse" />
            </div>
            <div class="h-5 w-20 bg-gray-100 dark:bg-slate-700 rounded animate-pulse" />
          </div>
        </div>
      </div>

      <div v-else-if="!wallets.length" class="py-16 px-4 text-center bg-gray-50/50 dark:bg-slate-800/30">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-600 mb-4 shadow-sm">
          <CreditCardIcon class="w-8 h-8 text-gray-300 dark:text-slate-500" />
        </div>
        <p class="text-gray-600 dark:text-slate-300 text-sm font-medium">لا توجد محافظ مطابقة</p>
        <p class="text-gray-400 dark:text-slate-500 text-xs mt-1 max-w-xs mx-auto">شحن رصيد لعميل أو تغيير عوامل البحث لعرض النتائج</p>
        <button
          type="button"
          class="mt-4 text-sm font-semibold text-primary-600 dark:text-primary-400 hover:underline"
          @click="showTopUp = true"
        >
          + شحن رصيد
        </button>
      </div>

      <table v-else class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-slate-700/50 text-xs text-gray-500 dark:text-slate-400">
          <tr>
            <th class="px-5 py-3 text-right font-semibold">العميل</th>
            <th class="px-4 py-3 text-right font-semibold">نوع المحفظة</th>
            <th class="px-4 py-3 text-right font-semibold">الرصيد</th>
            <th class="px-4 py-3 text-right font-semibold">الحالة</th>
            <th class="px-4 py-3 text-right font-semibold">آخر تحديث</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
          <tr v-for="w in wallets" :key="w.id" class="hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors">
            <td class="px-5 py-3.5">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-primary-100 dark:bg-primary-900/40 rounded-full flex items-center justify-center flex-shrink-0">
                  <span class="text-primary-700 dark:text-primary-300 font-bold text-sm">{{ (w.customer?.name ?? 'أ')[0] }}</span>
                </div>
                <div>
                  <p class="font-medium text-gray-900 dark:text-white">{{ w.customer?.name ?? 'عميل غير محدد' }}</p>
                  <p class="text-xs text-gray-400">{{ w.customer?.phone ?? '' }}</p>
                </div>
              </div>
            </td>
            <td class="px-4 py-3.5">
              <span class="px-2.5 py-1 rounded-full text-xs font-semibold" :class="typeClass(w.wallet_type)">
                {{ typeLabel(w.wallet_type) }}
              </span>
            </td>
            <td class="px-4 py-3.5">
              <span class="font-bold text-gray-900 dark:text-white" :class="w.balance < 0 ? 'text-red-500' : ''">
                {{ fmt(w.balance) }}
              </span>
            </td>
            <td class="px-4 py-3.5">
              <span class="px-2 py-0.5 rounded-full text-xs font-semibold" :class="statusClass(w.status)">
                {{ statusLabel(w.status) }}
              </span>
            </td>
            <td class="px-4 py-3.5 text-xs text-gray-500 dark:text-slate-400">
              {{ fmtDate(w.updated_at) }}
            </td>
            <td class="px-4 py-3.5">
              <div class="flex items-center gap-2 justify-end">
                <RouterLink
                  :to="{ name: 'wallet-transactions', params: { walletId: w.id } }"
                  class="text-xs font-medium text-primary-600 hover:underline px-1"
                >سجل</RouterLink>
                <button @click="openTxn(w)" title="معاينة سريعة"
                  class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-600 text-gray-400 hover:text-primary-600 transition-colors">
                  <ClockIcon class="w-4 h-4" />
                </button>
                <button @click="openTopUpFor(w)" title="شحن"
                  class="p-1.5 rounded-lg hover:bg-green-50 text-gray-400 hover:text-green-600 transition-colors">
                  <PlusCircleIcon class="w-4 h-4" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Top-Up Modal -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="showTopUp" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showTopUp = false" dir="rtl">
          <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-md shadow-2xl">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-slate-700">
              <h3 class="font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <PlusCircleIcon class="w-5 h-5 text-green-500" />
                شحن رصيد
              </h3>
              <button @click="showTopUp = false" class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700">
                <XMarkIcon class="w-5 h-5 text-gray-400" />
              </button>
            </div>
            <div class="p-6 space-y-4">
              <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">العميل <span class="text-red-500">*</span></label>
                <select v-model="topUpForm.customer_id" class="w-full px-3 py-2.5 border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl text-sm focus:ring-2 focus:ring-primary-500">
                  <option value="">اختر عميل</option>
                  <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }} — {{ c.phone }}</option>
                </select>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">نوع الشحن</label>
                  <select v-model="topUpForm.target" class="w-full px-3 py-2.5 border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl text-sm focus:ring-2 focus:ring-primary-500">
                    <option value="individual">عميل / محفظة فردية</option>
                    <option value="fleet">محفظة أسطول</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">المبلغ (ريال) <span class="text-red-500">*</span></label>
                  <input v-model.number="topUpForm.amount" type="number" min="1" step="0.01"
                    class="w-full px-3 py-2.5 border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl text-sm focus:ring-2 focus:ring-primary-500"
                    placeholder="0.00" />
                </div>
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">مركبة (اختياري — للأسطول)</label>
                <input v-model.number="topUpForm.vehicle_id" type="number" min="0" step="1"
                  class="w-full px-3 py-2.5 border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl text-sm focus:ring-2 focus:ring-primary-500"
                  placeholder="معرّف المركبة" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">ملاحظة</label>
                <input v-model="topUpForm.note" type="text"
                  class="w-full px-3 py-2.5 border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl text-sm focus:ring-2 focus:ring-primary-500"
                  placeholder="اختياري..." />
              </div>
              <div v-if="topUpError" class="text-sm text-red-600 bg-red-50 dark:bg-red-900/30 rounded-xl p-3">{{ topUpError }}</div>
            </div>
            <div class="flex gap-3 px-6 py-4 border-t border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-700/50 rounded-b-2xl">
              <button @click="showTopUp = false" class="flex-1 px-4 py-2.5 border border-gray-200 dark:border-slate-600 rounded-xl text-sm font-medium text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors">إلغاء</button>
              <button @click="submitTopUp" :disabled="submitting"
                class="flex-1 px-4 py-2.5 bg-green-600 text-white rounded-xl text-sm font-semibold hover:bg-green-700 disabled:opacity-50 transition-colors">
                {{ submitting ? 'جارٍ الشحن...' : 'شحن الرصيد' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Transactions Modal -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="showTxnModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showTxnModal = false" dir="rtl">
          <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-2xl shadow-2xl max-h-[80vh] flex flex-col">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-slate-700 flex-shrink-0">
              <h3 class="font-bold text-gray-900 dark:text-white">
                سجل العمليات — {{ selectedWallet?.customer?.name }}
              </h3>
              <button @click="showTxnModal = false" class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700">
                <XMarkIcon class="w-5 h-5 text-gray-400" />
              </button>
            </div>
            <div class="overflow-y-auto flex-1 p-4">
              <div v-if="txnLoading" class="flex justify-center py-8">
                <div class="w-7 h-7 border-4 border-primary-200 border-t-primary-600 rounded-full animate-spin"></div>
              </div>
              <div v-else-if="!transactions.length" class="text-center py-10 px-4 rounded-xl bg-gray-50 dark:bg-slate-700/40">
                <ClockIcon class="w-10 h-10 text-gray-200 dark:text-slate-600 mx-auto mb-2" />
                <p class="text-gray-500 dark:text-slate-400 text-sm font-medium">لا عمليات في هذا السجل</p>
                <p class="text-gray-400 text-xs mt-1">ستظهر الشحنات والخصومات هنا عند حدوثها</p>
              </div>
              <div v-else class="space-y-2">
                <div v-for="t in transactions" :key="t.id"
                  class="flex items-center gap-4 p-3 rounded-xl border border-gray-100 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors">
                  <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0"
                    :class="txnDisplay(t).credit ? 'bg-green-100 dark:bg-green-900/40' : 'bg-red-100 dark:bg-red-900/40'">
                    <component :is="txnDisplay(t).credit ? ArrowDownIcon : ArrowUpIcon" class="w-4 h-4"
                      :class="txnDisplay(t).credit ? 'text-green-600' : 'text-red-500'" />
                  </div>
                  <div class="flex-1">
                    <p class="text-sm font-medium text-gray-800 dark:text-white">{{ t.description ?? txnTypeLabel(t.type) }}</p>
                    <p class="text-xs text-gray-400">{{ fmtDate(t.created_at) }}</p>
                  </div>
                  <div class="text-right">
                    <p class="font-bold text-sm" :class="txnDisplay(t).credit ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400'">
                      {{ txnDisplay(t).credit ? '+' : '-' }}{{ fmt(Math.abs(Number(t.amount))) }}
                    </p>
                    <p v-if="t.balance_after != null" class="text-xs text-gray-400">رصيد: {{ fmt(t.balance_after) }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import {
  CreditCardIcon, PlusCircleIcon, MagnifyingGlassIcon,
  ClockIcon, XMarkIcon,
  ArrowDownIcon, ArrowUpIcon,
} from '@heroicons/vue/24/outline'
import apiClient, { withIdempotency } from '@/lib/apiClient'
import { v4 as uuidv4 } from 'uuid'
import { useToast } from '@/composables/useToast'

const toast = useToast()
const loading = ref(false)
const submitting = ref(false)
const showTopUp = ref(false)
const showTxnModal = ref(false)
const txnLoading = ref(false)
const topUpError = ref('')
const search = ref('')
const filterType = ref('')
const filterStatus = ref('')
const wallets = ref<any[]>([])
const customers = ref<any[]>([])
const transactions = ref<any[]>([])
const selectedWallet = ref<any>(null)
const totals = ref<Record<string, number>>({})

const topUpForm = reactive({
  customer_id: '' as string | number,
  target: 'individual' as 'individual' | 'fleet',
  vehicle_id: null as number | null,
  amount: null as number | null,
  note: '',
})

function txnDisplay(t: any): { credit: boolean } {
  if (t.direction === 'credit' || t.type === 'credit') return { credit: true }
  if (t.direction === 'debit' || t.type === 'debit') return { credit: false }
  const typ = String(t.transaction_type ?? t.type ?? '').toUpperCase()
  const debitLike = ['TRANSFER_OUT', 'INVOICE_DEBIT', 'DEBIT', 'ADJUSTMENT_SUB']
  return { credit: !debitLike.some((x) => typ.includes(x)) }
}

const walletTypes = [
  { key: 'cash', label: 'نقدية', bg: 'bg-green-100 dark:bg-green-900/30', iconColor: 'text-green-600', icon: CreditCardIcon, badge: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' },
  { key: 'promotional', label: 'ترويجية', bg: 'bg-purple-100 dark:bg-purple-900/30', iconColor: 'text-purple-600', icon: CreditCardIcon, badge: 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300' },
  { key: 'reserved', label: 'محجوزة', bg: 'bg-yellow-100 dark:bg-yellow-900/30', iconColor: 'text-yellow-600', icon: CreditCardIcon, badge: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300' },
  { key: 'credit', label: 'ائتمان', bg: 'bg-blue-100 dark:bg-blue-900/30', iconColor: 'text-blue-600', icon: CreditCardIcon, badge: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' },
]

const fmt = (n: any) => new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR' }).format(parseFloat(n) || 0)
const fmtDate = (d: string) => new Date(d).toLocaleDateString('ar-SA', { day: 'numeric', month: 'short', year: 'numeric' })

function typeLabel(t: string) {
  return { cash: 'نقدية', promotional: 'ترويجية', reserved: 'محجوزة', credit: 'ائتمان' }[t] ?? t
}
function typeClass(t: string) {
  return {
    cash: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    promotional: 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
    reserved: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300',
    credit: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
  }[t] ?? 'bg-gray-100 text-gray-700'
}
function statusLabel(s: string) {
  return { active: 'نشطة', frozen: 'مجمّدة', closed: 'مغلقة' }[s] ?? s
}
function statusClass(s: string) {
  return {
    active: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    frozen: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300',
    closed: 'bg-gray-100 text-gray-500',
  }[s] ?? 'bg-gray-100 text-gray-500'
}
function txnTypeLabel(t: string) {
  const map: Record<string, string> = {
    top_up: 'شحن رصيد', debit: 'خصم', credit: 'إضافة',
    reservation: 'حجز', release: 'إلغاء حجز', transfer: 'تحويل',
  }
  return map[t] ?? t
}

let debounceTimer: ReturnType<typeof setTimeout>
function debouncedLoad() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(load, 400)
}

async function load() {
  loading.value = true
  try {
    const r = await apiClient.get('/wallet', {
      params: { search: search.value || undefined, wallet_type: filterType.value || undefined, status: filterStatus.value || undefined }
    })
    const w = r.data?.wallets
    wallets.value = Array.isArray(w?.data) ? w.data : (Array.isArray(w) ? w : [])
    totals.value = r.data?.totals ?? {}
  } catch {
    wallets.value = []
    totals.value = {}
  } finally {
    loading.value = false
  }
}

async function loadCustomers() {
  try {
    const r = await apiClient.get('/customers', { params: { per_page: 300 } })
    customers.value = r.data?.data ?? []
  } catch { /* silent */ }
}

function openTopUpFor(w: any) {
  topUpForm.customer_id = w.customer_id ?? ''
  topUpForm.target = 'individual'
  topUpForm.vehicle_id = null
  topUpForm.amount = null
  topUpForm.note = ''
  topUpError.value = ''
  showTopUp.value = true
}

async function submitTopUp() {
  if (!topUpForm.customer_id || !topUpForm.amount || topUpForm.amount <= 0) {
    topUpError.value = 'يرجى اختيار عميل وإدخال مبلغ صحيح'
    return
  }
  submitting.value = true
  topUpError.value = ''
  try {
    await apiClient.post(
      '/wallet/top-up',
      {
        customer_id: Number(topUpForm.customer_id),
        amount: topUpForm.amount,
        target: topUpForm.target,
        vehicle_id: topUpForm.vehicle_id || undefined,
        notes: topUpForm.note || undefined,
        idempotency_key: uuidv4(),
      },
      withIdempotency(),
    )
    toast.success(`تم شحن ${fmt(topUpForm.amount)} بنجاح`)
    showTopUp.value = false
    await load()
  } catch (e: any) {
    topUpError.value = e?.response?.data?.message ?? 'حدث خطأ أثناء الشحن'
  } finally {
    submitting.value = false
  }
}

async function openTxn(w: any) {
  selectedWallet.value = w
  showTxnModal.value = true
  txnLoading.value = true
  transactions.value = []
  try {
    const r = await apiClient.get('/wallet/transactions', { params: { wallet_id: w.id, per_page: 100 } })
    const pag = r.data?.data
    transactions.value = Array.isArray(pag?.data) ? pag.data : (Array.isArray(pag) ? pag : [])
  } catch { transactions.value = [] }
  finally { txnLoading.value = false }
}

onMounted(async () => {
  await Promise.all([load(), loadCustomers()])
})
</script>
