<template>
  <div class="space-y-4">
    <div>
      <h2 class="text-lg font-bold text-gray-900 dark:text-white">المحفظة</h2>
      <p class="text-xs text-gray-500 mt-0.5">إدارة رصيد الشركة والتحويل بين محافظ المركبات وفق النموذج المالي للعميل</p>
    </div>
    <div class="grid gap-3 md:grid-cols-3">
      <div class="rounded-xl border p-3" :class="isCreditCustomer ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50'">
        <p class="text-xs text-gray-600">النموذج المالي</p>
        <p class="mt-1 text-sm font-bold" :class="isCreditCustomer ? 'text-amber-800' : 'text-emerald-800'">
          {{ isCreditCustomer ? 'ائتماني' : 'مسبق الدفع' }}
        </p>
      </div>
      <div class="rounded-xl border border-blue-200 bg-blue-50 p-3">
        <p class="text-xs text-gray-600">رصيد محفظة الشركة</p>
        <p class="mt-1 text-sm font-bold text-blue-900">{{ fmt(companyWalletBalance) }} ر.س</p>
      </div>
      <div class="rounded-xl border border-violet-200 bg-violet-50 p-3">
        <p class="text-xs text-gray-600">{{ isCreditCustomer ? 'المتاح من الائتمان' : 'إجمالي محافظ المركبات' }}</p>
        <p class="mt-1 text-sm font-bold text-violet-900">
          {{ fmt(isCreditCustomer ? availableCredit : vehicleWalletsBalance) }} ر.س
        </p>
      </div>
    </div>
    <div class="rounded-xl border border-blue-100 bg-blue-50/70 p-3 text-xs text-blue-900">
      الشحن يتم على <strong>محفظة الشركة</strong> فقط، وبعدها يتم التحويل بين محافظ المركبات عند الحاجة. لا يوجد شحن مباشر لمحفظة مركبة.
    </div>
    <div v-if="!isCreditCustomer" class="rounded-xl border border-emerald-100 bg-emerald-50/70 p-3 text-xs text-emerald-900">
      هذا الحساب يعمل بنموذج <strong>مسبق الدفع</strong>: ارفع طلب شحن، وبعد الاعتماد تتم الإضافة إلى محفظة الشركة ثم التحويل لمركباتك.
    </div>
    <div class="flex justify-end">
      <RouterLink v-if="!isCreditCustomer" to="/customer/wallet/top-up-requests" class="btn btn-primary text-sm">
        رفع طلب شحن
      </RouterLink>
      <RouterLink v-else to="/customer/invoices" class="btn btn-outline text-sm">
        عرض الملف الائتماني
      </RouterLink>
    </div>

    <!-- Wallet Cards -->
    <div v-if="loading" class="flex justify-center py-8"><div class="spinner"></div></div>
    <div v-else-if="wallets.length" class="space-y-3">
      <div v-for="w in wallets" :key="w.id"
           class="rounded-2xl p-5 text-white shadow-md"
           :class="walletGradient(walletKind(w))"
      >
        <div class="flex items-start justify-between mb-4">
          <div>
            <p class="text-xs text-white/70">{{ walletTypeLabel(walletKind(w)) }}</p>
            <p class="text-2xl font-bold mt-1">{{ fmt(w.balance) }}</p>
            <p class="text-xs text-white/70 mt-0.5">ر.س</p>
          </div>
          <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
            <CreditCardIcon class="w-5 h-5 text-white" />
          </div>
        </div>
        <div class="flex items-center justify-between text-xs text-white/70">
          <span>محفظة {{ walletKind(w) === 'fleet_vehicle' ? '#' + w.id : 'الشركة' }}</span>
          <span>آخر تحديث: {{ fmtDate(w.updated_at) }}</span>
        </div>
      </div>
    </div>
    <div v-else class="card p-8 text-center text-gray-500">
      <CreditCardIcon class="w-10 h-10 text-gray-300 mx-auto mb-2" />
      <p>لا توجد محافظ مرتبطة بحسابك</p>
    </div>

    <!-- Recent Transactions -->
    <div class="card p-4">
      <h3 class="font-semibold text-gray-800 dark:text-white mb-3">آخر المعاملات</h3>
      <div v-if="txLoading" class="flex justify-center py-4"><div class="spinner"></div></div>
      <div v-else-if="transactions.length" class="space-y-2">
        <div v-for="tx in transactions" :key="tx.id"
             class="flex items-center justify-between p-3 rounded-xl bg-gray-50 dark:bg-slate-700/40"
        >
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center"
                 :class="txCredit(tx) ? 'bg-green-100 dark:bg-green-900/30' : 'bg-red-100 dark:bg-red-900/30'"
            >
              <span class="text-sm">{{ txCredit(tx) ? '⬆️' : '⬇️' }}</span>
            </div>
            <div>
              <p class="text-sm font-medium text-gray-800 dark:text-white">{{ tx.description ?? tx.transaction_type ?? '—' }}</p>
              <p class="text-xs text-gray-400">{{ fmtDate(tx.created_at) }}</p>
            </div>
          </div>
          <span class="font-semibold text-sm" :class="txCredit(tx) ? 'text-green-600' : 'text-red-600'">
            {{ txCredit(tx) ? '+' : '-' }}{{ fmt(Math.abs(Number(tx.amount))) }}
          </span>
        </div>
      </div>
      <div v-else class="text-center text-gray-400 py-4 text-sm">لا توجد معاملات</div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { RouterLink } from 'vue-router'
import { CreditCardIcon } from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const customerProfile = ref<any | null>(null)

const loading      = ref(false)
const txLoading    = ref(false)
const wallets      = ref<any[]>([])
const transactions = ref<any[]>([])
const isCreditCustomer = computed(() => {
  const profile = String(customerProfile.value?.customer_pricing_profile ?? '').toLowerCase()
  if (profile === 'credit') return true
  const limit = Number(customerProfile.value?.credit_limit ?? 0)
  return Number.isFinite(limit) && limit > 0
})
const companyWalletBalance = computed(() =>
  wallets.value
    .filter((w) => walletKind(w) !== 'fleet_vehicle')
    .reduce((sum, w) => sum + (Number(w?.balance) || 0), 0),
)
const vehicleWalletsBalance = computed(() =>
  wallets.value
    .filter((w) => walletKind(w) === 'fleet_vehicle')
    .reduce((sum, w) => sum + (Number(w?.balance) || 0), 0),
)
const availableCredit = computed(() => {
  const limit = Number(customerProfile.value?.credit_limit ?? 0)
  const used = Number(customerProfile.value?.used_credit ?? customerProfile.value?.credit_used ?? 0)
  return Math.max(0, limit - used)
})

function walletGradient(type: string) {
  const map: Record<string, string> = {
    cash:          'bg-gradient-to-br from-blue-500 to-blue-700',
    promotional:   'bg-gradient-to-br from-primary-500 to-primary-700',
    reserved:      'bg-gradient-to-br from-gray-500 to-gray-700',
    fleet:         'bg-gradient-to-br from-teal-500 to-teal-700',
    fleet_vehicle: 'bg-gradient-to-br from-orange-500 to-amber-600',
  }
  return map[type] ?? 'bg-gradient-to-br from-gray-500 to-gray-700'
}

function walletKind(w: Record<string, unknown>): string {
  return String(w.wallet_type ?? w.type ?? 'cash')
}

function txCredit(tx: any): boolean {
  if (tx.direction === 'credit' || tx.type === 'credit') return true
  if (tx.direction === 'debit' || tx.type === 'debit') return false
  const typ = String(tx.transaction_type ?? '').toUpperCase()
  return !['TRANSFER_OUT', 'INVOICE_DEBIT', 'DEBIT', 'ADJUSTMENT_SUB'].some((x) => typ.includes(x))
}

function walletTypeLabel(type: string) {
  const map: Record<string, string> = {
    cash:          'المحفظة النقدية',
    promotional:   'المحفظة الترويجية',
    reserved:      'المبالغ المحجوزة',
    fleet:         'محفظة الأسطول',
    fleet_vehicle: 'محفظة المركبة',
  }
  return map[type] ?? type
}

const fmt = (n: any) =>
  new Intl.NumberFormat('ar-SA', { style: 'decimal', minimumFractionDigits: 2 }).format(parseFloat(n) || 0)

const fmtDate = (d: string) => new Date(d).toLocaleDateString('ar-SA')

async function loadWallets() {
  loading.value = true
  try {
    const { data } = await apiClient.get('/wallet')
    const w = data?.wallets
    wallets.value = Array.isArray(w?.data) ? w.data : (Array.isArray(w) ? w : [])
  } catch { wallets.value = [] } finally { loading.value = false }
}

async function loadTransactions() {
  txLoading.value = true
  try {
    const cid = auth.user?.customer_id
    if (cid == null || Number(cid) < 1) {
      transactions.value = []
      return
    }
    const { data } = await apiClient.get('/wallet/transactions', {
      params: { customer_id: cid, per_page: 20 },
    })
    const pag = data?.data
    transactions.value = Array.isArray(pag?.data) ? pag.data : []
  } catch { transactions.value = [] } finally { txLoading.value = false }
}

async function loadCustomerProfile() {
  try {
    const cid = auth.user?.customer_id
    if (!cid) {
      customerProfile.value = null
      return
    }
    const { data } = await apiClient.get(`/customers/${cid}`)
    customerProfile.value = data?.data ?? data ?? null
  } catch {
    customerProfile.value = null
  }
}

onMounted(() => {
  loadCustomerProfile()
  loadWallets()
  loadTransactions()
})
</script>
