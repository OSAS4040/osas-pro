<template>
  <div class="space-y-6">
    <div>
      <h2 class="text-xl font-bold text-gray-900">التحقق من لوحة المركبة</h2>
      <p class="text-sm text-gray-500 mt-0.5">أدخل رقم اللوحة للتحقق من أهلية الخدمة</p>
    </div>

    <!-- Search Box -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
      <div class="flex gap-3">
        <input
          v-model="plate"
          @keyup.enter="verify"
          type="text"
          placeholder="أدخل رقم اللوحة (مثال: ABC1234)"
          class="flex-1 border border-gray-300 rounded-xl px-4 py-3 text-sm font-mono uppercase tracking-widest focus:outline-none focus:ring-2 focus:ring-primary-500"
          :disabled="loading"
          ref="plateInput"
        />
        <button
          @click="verify"
          :disabled="loading || !plate.trim()"
          class="bg-primary-600 hover:bg-primary-700 disabled:opacity-50 text-white px-6 rounded-xl text-sm font-medium transition-colors flex items-center gap-2"
        >
          <span v-if="loading" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
          <span>{{ loading ? 'جارٍ البحث...' : 'تحقق' }}</span>
        </button>
      </div>
      <!-- Camera Scan Button -->
      <div class="mt-3 pt-3 border-t border-gray-100 flex items-center gap-3">
        <span class="text-xs text-gray-400">أو</span>
        <CameraPlateScanner @plate="onPlateScanned" />
        <span class="text-xs text-gray-400">اكتشاف تلقائي بالكاميرا</span>
      </div>
    </div>

    <!-- Result Card -->
    <div v-if="result" class="space-y-4">

      <!-- Verdict Banner -->
      <div
        class="rounded-2xl p-5 flex items-start gap-4"
        :class="result.verdict.can_proceed
          ? (result.verdict.payment_mode === 'credit' ? 'bg-amber-50 border-2 border-amber-400' : 'bg-green-50 border-2 border-green-400')
          : 'bg-red-50 border-2 border-red-400'"
      >
        <div class="text-3xl">
          {{ result.verdict.can_proceed
              ? (result.verdict.payment_mode === 'credit' ? '⚡' : '✅')
              : '🚫' }}
        </div>
        <div>
          <div class="font-bold text-lg"
            :class="result.verdict.can_proceed
              ? (result.verdict.payment_mode === 'credit' ? 'text-amber-800' : 'text-green-800')
              : 'text-red-800'"
          >
            {{ result.verdict.can_proceed
                ? (result.verdict.payment_mode === 'credit' ? 'متابعة — وضع الائتمان' : 'متابعة — دفع مسبق')
                : 'مرفوض — لا يمكن تنفيذ الخدمة' }}
          </div>
          <div class="text-sm mt-1 text-gray-600" v-if="result.verdict.denial_message">
            {{ result.verdict.denial_message }}
          </div>
          <div class="text-sm mt-1 text-gray-600" v-if="result.verdict.can_proceed && result.verdict.payment_mode === 'prepaid'">
            سيتم الخصم من محفظة المركبة عند إنشاء الفاتورة.
          </div>
          <div class="text-sm mt-1 text-amber-700" v-if="result.verdict.can_proceed && result.verdict.payment_mode === 'credit'">
            الرصيد غير كافٍ — الخدمة ستُنفَّذ بموجب تفويض الائتمان الممنوح.
          </div>
        </div>
      </div>

      <!-- Details Grid -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

        <!-- Vehicle Card -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
          <div class="text-xs font-semibold text-gray-400 uppercase mb-3">المركبة</div>
          <div v-if="result.vehicle" class="space-y-1.5">
            <div class="font-mono text-2xl font-bold text-gray-900 tracking-widest">{{ result.vehicle.plate_number }}</div>
            <div class="text-sm text-gray-700">{{ result.vehicle.make }} {{ result.vehicle.model }} {{ result.vehicle.year }}</div>
            <div class="text-xs text-gray-400">{{ result.vehicle.customer_name }}</div>
          </div>
          <div v-else class="text-sm text-red-500">لوحة غير مسجلة</div>
        </div>

        <!-- Work Order Card -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
          <div class="text-xs font-semibold text-gray-400 uppercase mb-3">أمر العمل</div>
          <div v-if="result.work_order" class="space-y-2">
            <div class="font-semibold text-gray-800">{{ result.work_order.order_number }}</div>
            <div class="flex items-center gap-2">
              <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                :class="statusClass(result.work_order.status)">
                {{ statusLabel(result.work_order.status) }}
              </span>
              <span class="text-xs px-2 py-0.5 rounded-full"
                :class="result.work_order.approval_status === 'approved'
                  ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700'">
                {{ result.work_order.approval_status === 'approved' ? 'معتمد' : 'غير معتمد' }}
              </span>
            </div>
            <div v-if="result.work_order.credit_authorized" class="text-xs text-amber-600 bg-amber-50 rounded-lg px-2 py-1">
              ⚡ مفوَّض ائتمان
            </div>
            <!-- Approve Button -->
            <button
              v-if="result.work_order.approval_status !== 'approved'"
              @click="approveWorkOrder(result.work_order.id)"
              :disabled="approving"
              class="mt-2 w-full text-xs bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white rounded-lg py-1.5 transition-colors"
            >
              {{ approving ? 'جارٍ الاعتماد...' : 'اعتماد أمر العمل' }}
            </button>
          </div>
          <div v-else class="text-sm text-orange-500">لا يوجد أمر عمل نشط</div>
        </div>

        <!-- Wallet Card -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
          <div class="text-xs font-semibold text-gray-400 uppercase mb-3">محفظة المركبة</div>
          <div class="text-3xl font-bold" :class="result.wallet?.balance > 0 ? 'text-green-600' : 'text-red-500'">
            {{ formatAmount(result.wallet?.balance ?? 0) }}
          </div>
          <div class="text-xs text-gray-400 mt-0.5">ر.س</div>
          <div class="mt-2 text-xs px-2 py-0.5 inline-block rounded-full"
            :class="result.wallet?.status === 'active' ? 'bg-green-50 text-green-600' : 'bg-gray-100 text-gray-400'">
            {{ result.wallet?.status === 'active' ? 'نشطة' : (result.wallet?.status === 'not_created' ? 'لم تُنشأ بعد' : result.wallet?.status) }}
          </div>
        </div>
      </div>

      <!-- New Search -->
      <div class="flex justify-center">
        <button @click="reset" class="text-sm text-primary-600 hover:underline">← البحث عن مركبة أخرى</button>
      </div>
    </div>

    <!-- Approve Credit Modal -->
    <div v-if="approveModal.open" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-5 space-y-4">
        <h3 class="font-semibold text-gray-900">اعتماد أمر العمل</h3>
        <label class="flex items-center gap-3 cursor-pointer">
          <input type="checkbox" v-model="approveModal.creditAuthorized" class="w-4 h-4 text-amber-600" />
          <span class="text-sm text-gray-700">تفويض خدمة الائتمان (الخصم لاحقاً)</span>
        </label>
        <div v-if="approveModal.error" class="text-xs text-red-600 bg-red-50 rounded-lg p-2">{{ approveModal.error }}</div>
        <div class="flex gap-3">
          <button
            @click="submitApprove"
            :disabled="approveModal.loading"
            class="flex-1 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white rounded-xl py-2.5 text-sm font-medium"
          >
            {{ approveModal.loading ? 'جارٍ الاعتماد...' : 'تأكيد الاعتماد' }}
          </button>
          <button @click="approveModal.open = false" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl py-2.5 text-sm">
            إلغاء
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, nextTick } from 'vue'
import CameraPlateScanner from '@/components/CameraPlateScanner.vue'

const API   = '/api/v1'
const token = () => localStorage.getItem('auth_token') ?? ''

const plate      = ref('')
const loading    = ref(false)
const approving  = ref(false)
const result     = ref<any>(null)
const plateInput = ref<HTMLInputElement | null>(null)

function onPlateScanned(scanned: string) {
  plate.value = scanned
  nextTick(() => verify())
}

const approveModal = reactive({
  open: false, loading: false, error: '',
  workOrderId: 0, creditAuthorized: false
})

function formatAmount(v: number): string {
  return (v ?? 0).toLocaleString('ar-SA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function statusClass(s: string): string {
  const m: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-700',
    in_progress: 'bg-blue-100 text-blue-700',
    completed: 'bg-green-100 text-green-700',
    cancelled: 'bg-red-100 text-red-700',
  }
  return m[s] ?? 'bg-gray-100 text-gray-600'
}

function statusLabel(s: string): string {
  const m: Record<string, string> = {
    pending: 'معلق', in_progress: 'جارٍ', completed: 'مكتمل', cancelled: 'ملغي', on_hold: 'موقوف'
  }
  return m[s] ?? s
}

async function verify() {
  if (!plate.value.trim()) return
  loading.value = true
  result.value  = null
  try {
    const res  = await fetch(`${API}/fleet/verify-plate`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token()}` },
      body: JSON.stringify({ plate_number: plate.value.trim().toUpperCase() }),
    })
    result.value = await res.json()
  } finally {
    loading.value = false
  }
}

function approveWorkOrder(id: number) {
  approveModal.workOrderId     = id
  approveModal.creditAuthorized = false
  approveModal.error            = ''
  approveModal.open             = true
}

async function submitApprove() {
  approveModal.loading = true
  approveModal.error   = ''
  try {
    const res = await fetch(`${API}/fleet/work-orders/${approveModal.workOrderId}/approve`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token()}` },
      body: JSON.stringify({ credit_authorized: approveModal.creditAuthorized }),
    })
    const json = await res.json()
    if (!res.ok) throw new Error(json.message ?? 'فشل الاعتماد')
    approveModal.open = false
    await verify()
  } catch (e: any) {
    approveModal.error = e.message
  } finally {
    approveModal.loading = false
  }
}

function reset() {
  result.value = null
  plate.value  = ''
  nextTick(() => plateInput.value?.focus())
}
</script>
