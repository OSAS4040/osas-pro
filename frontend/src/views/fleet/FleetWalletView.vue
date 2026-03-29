<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-bold text-gray-900">محافظ الأسطول</h2>
        <p class="text-sm text-gray-500 mt-0.5">إدارة محافظ عملاء الأسطول وتوزيع الرصيد على المركبات</p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-16">
      <div class="w-8 h-8 border-2 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 text-sm">{{ error }}</div>

    <!-- Empty -->
    <div v-else-if="!customers.length" class="bg-white rounded-xl border border-gray-200 p-12 text-center">
      <div class="text-4xl mb-3">🚛</div>
      <p class="text-gray-500">لا توجد محافظ أسطول مسجلة بعد.</p>
    </div>

    <!-- Fleet Customers List -->
    <div v-else class="space-y-4">
      <div
        v-for="item in customers"
        :key="item.customer?.id"
        class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden"
      >
        <!-- Header -->
        <div class="flex items-center justify-between p-4 border-b border-gray-100 bg-gray-50">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-bold text-sm">
              {{ item.customer?.name?.charAt(0) ?? '؟' }}
            </div>
            <div>
              <div class="font-semibold text-gray-900">{{ item.customer?.name }}</div>
              <div class="text-xs text-gray-400">{{ item.customer?.phone }}</div>
            </div>
          </div>
          <div class="text-right">
            <div class="text-xs text-gray-400">إجمالي الرصيد</div>
            <div class="text-lg font-bold" :class="item.total_balance > 0 ? 'text-green-600' : 'text-red-500'">
              {{ formatAmount(item.total_balance) }} <span class="text-xs font-normal">ر.س</span>
            </div>
          </div>
        </div>

        <!-- Wallet Details -->
        <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Fleet Main Wallet -->
          <div class="bg-blue-50 rounded-lg p-3">
            <div class="text-xs text-blue-600 font-medium mb-1">المحفظة الرئيسية للأسطول</div>
            <div class="text-xl font-bold text-blue-800">
              {{ formatAmount(item.fleet_wallet?.balance) }} <span class="text-xs font-normal">ر.س</span>
            </div>
            <div class="mt-2 flex gap-2">
              <button
                @click="openTopUp(item)"
                class="text-xs bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded-lg transition-colors"
              >
                شحن رصيد
              </button>
              <RouterLink
                :to="`/fleet/transactions/${item.fleet_wallet?.id}`"
                class="text-xs bg-white hover:bg-blue-100 text-blue-700 border border-blue-200 px-3 py-1 rounded-lg transition-colors"
              >
                المعاملات
              </RouterLink>
            </div>
          </div>

          <!-- Vehicle Wallets -->
          <div>
            <div class="text-xs text-gray-500 font-medium mb-2">محافظ المركبات ({{ item.vehicle_wallets?.length ?? 0 }})</div>
            <div v-if="!item.vehicle_wallets?.length" class="text-xs text-gray-400">لا توجد محافظ مركبات بعد.</div>
            <div v-else class="space-y-1.5 max-h-40 overflow-y-auto">
              <div
                v-for="vw in item.vehicle_wallets"
                :key="vw.wallet_id"
                class="flex items-center justify-between bg-gray-50 rounded-lg p-2"
              >
                <div>
                  <span class="text-xs font-mono font-semibold text-gray-700">{{ vw.plate }}</span>
                  <span class="text-xs text-gray-400 ms-2">(لوحة)</span>
                </div>
                <div class="flex items-center gap-2">
                  <span class="text-sm font-semibold" :class="vw.balance > 0 ? 'text-green-600' : 'text-orange-500'">
                    {{ formatAmount(vw.balance) }}
                  </span>
                  <button
                    @click="openTransfer(item, vw)"
                    class="text-xs text-primary-600 hover:underline"
                  >
                    تحويل
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Top-Up Modal -->
    <div v-if="topUpModal.open" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm">
        <div class="flex items-center justify-between p-4 border-b border-gray-100">
          <h3 class="font-semibold text-gray-900">شحن المحفظة الرئيسية</h3>
          <button @click="topUpModal.open = false" class="text-gray-400 hover:text-gray-600 text-xl">✕</button>
        </div>
        <div class="p-4 space-y-3">
          <div class="text-sm text-gray-500">العميل: <span class="font-medium text-gray-800">{{ topUpModal.customerName }}</span></div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">المبلغ (ر.س)</label>
            <input
              v-model.number="topUpModal.amount"
              type="number" min="0.01" step="0.01"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
              placeholder="0.00"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">ملاحظات (اختياري)</label>
            <input
              v-model="topUpModal.notes"
              type="text"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            />
          </div>
          <div v-if="topUpModal.error" class="text-xs text-red-600 bg-red-50 rounded-lg p-2">{{ topUpModal.error }}</div>
          <button
            @click="submitTopUp"
            :disabled="topUpModal.loading || !topUpModal.amount"
            class="w-full bg-primary-600 hover:bg-primary-700 disabled:opacity-50 text-white rounded-lg py-2.5 text-sm font-medium transition-colors"
          >
            {{ topUpModal.loading ? 'جارٍ الشحن...' : 'تأكيد الشحن' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Transfer Modal -->
    <div v-if="transferModal.open" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm">
        <div class="flex items-center justify-between p-4 border-b border-gray-100">
          <h3 class="font-semibold text-gray-900">تحويل لمحفظة مركبة</h3>
          <button @click="transferModal.open = false" class="text-gray-400 hover:text-gray-600 text-xl">✕</button>
        </div>
        <div class="p-4 space-y-3">
          <div class="text-sm text-gray-500">
            المركبة: <span class="font-mono font-bold text-gray-800">{{ transferModal.plate }}</span>
          </div>
          <div class="text-xs text-gray-400">
            رصيد الأسطول المتاح: <span class="font-semibold text-blue-700">{{ formatAmount(transferModal.fleetBalance) }} ر.س</span>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">المبلغ (ر.س)</label>
            <input
              v-model.number="transferModal.amount"
              type="number" min="0.01" :max="transferModal.fleetBalance" step="0.01"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
              placeholder="0.00"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">ملاحظات</label>
            <input v-model="transferModal.notes" type="text"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
          </div>
          <div v-if="transferModal.error" class="text-xs text-red-600 bg-red-50 rounded-lg p-2">{{ transferModal.error }}</div>
          <button
            @click="submitTransfer"
            :disabled="transferModal.loading || !transferModal.amount"
            class="w-full bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white rounded-lg py-2.5 text-sm font-medium transition-colors"
          >
            {{ transferModal.loading ? 'جارٍ التحويل...' : 'تأكيد التحويل' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { RouterLink } from 'vue-router'

const API = '/api/v1'
const token = () => localStorage.getItem('auth_token') ?? ''

const loading   = ref(false)
const error     = ref('')
const customers = ref<any[]>([])

const topUpModal = reactive({
  open: false, loading: false, error: '',
  customerId: 0, customerName: '', amount: 0, notes: ''
})

const transferModal = reactive({
  open: false, loading: false, error: '',
  customerId: 0, vehicleId: 0, plate: '',
  fleetBalance: 0, amount: 0, notes: ''
})

function formatAmount(v: number): string {
  return (v ?? 0).toLocaleString('ar-SA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

async function fetchCustomers() {
  loading.value = true
  error.value = ''
  try {
    const res = await fetch(`${API}/fleet/customers`, { headers: { Authorization: `Bearer ${token()}` } })
    if (!res.ok) throw new Error('فشل تحميل البيانات')
    const json = await res.json()
    customers.value = json.data ?? []
  } catch (e: any) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function openTopUp(item: any) {
  topUpModal.customerId   = item.customer?.id
  topUpModal.customerName = item.customer?.name
  topUpModal.amount       = 0
  topUpModal.notes        = ''
  topUpModal.error        = ''
  topUpModal.open         = true
}

function openTransfer(item: any, vw: any) {
  transferModal.customerId   = item.customer?.id
  transferModal.vehicleId    = vw.vehicle_id
  transferModal.plate        = vw.plate
  transferModal.fleetBalance = item.fleet_wallet?.balance ?? 0
  transferModal.amount       = 0
  transferModal.notes        = ''
  transferModal.error        = ''
  transferModal.open         = true
}

async function submitTopUp() {
  topUpModal.loading = true
  topUpModal.error   = ''
  try {
    const idem = `fleet-topup-${Date.now()}-${Math.random().toString(36).slice(2)}`
    const res = await fetch(`${API}/wallet/top-up`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token()}`,
        'Idempotency-Key': idem,
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        customer_id: topUpModal.customerId,
        amount:      topUpModal.amount,
        target:      'fleet',
        notes:       topUpModal.notes || undefined,
        idempotency_key: idem,
      }),
    })
    const json = await res.json()
    if (!res.ok) throw new Error(json.message ?? 'فشل الشحن')
    topUpModal.open = false
    await fetchCustomers()
  } catch (e: any) {
    topUpModal.error = e.message
  } finally {
    topUpModal.loading = false
  }
}

async function submitTransfer() {
  transferModal.loading = true
  transferModal.error   = ''
  try {
    const idem = `fleet-transfer-${Date.now()}-${Math.random().toString(36).slice(2)}`
    const res = await fetch(`${API}/wallet/transfer`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token()}`,
        'Idempotency-Key': idem,
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        customer_id:     transferModal.customerId,
        vehicle_id:      transferModal.vehicleId,
        amount:          transferModal.amount,
        notes:           transferModal.notes || undefined,
        idempotency_key: idem,
      }),
    })
    const json = await res.json()
    if (!res.ok) throw new Error(json.message ?? 'فشل التحويل')
    transferModal.open = false
    await fetchCustomers()
  } catch (e: any) {
    transferModal.error = e.message
  } finally {
    transferModal.loading = false
  }
}

onMounted(fetchCustomers)
</script>
