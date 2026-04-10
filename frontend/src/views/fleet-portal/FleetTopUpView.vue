<template>
  <div class="p-6 max-w-lg mx-auto">
    <div class="flex items-center gap-3 mb-6">
      <button class="text-gray-400 hover:text-gray-600" @click="$router.back()">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
      </button>
      <h1 class="text-xl font-bold text-gray-900">شحن رصيد المحفظة</h1>
    </div>

    <form class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5" @submit.prevent="submit">
      <!-- نوع المحفظة -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">نوع المحفظة</label>
        <select v-model="form.wallet_type"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
        >
          <option value="fleet_main">المحفظة الرئيسية للأسطول</option>
        </select>
        <p class="text-xs text-gray-400 mt-1">يمكن لاحقاً تحويل الرصيد لمحافظ المركبات الفردية</p>
      </div>

      <!-- المبلغ -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">المبلغ (ر.س) <span class="text-red-500">*</span></label>
        <input v-model.number="form.amount" type="number" min="1" step="0.01" required
               placeholder="أدخل المبلغ"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 text-lg font-semibold"
        />
      </div>

      <!-- ملاحظات -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">ملاحظات</label>
        <input v-model="form.notes" type="text" placeholder="مثال: دفعة شهر مارس"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
        />
      </div>

      <!-- Info Box -->
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-800">
        <p class="font-medium mb-1">📋 آلية الشحن</p>
        <ul class="text-xs text-blue-700 space-y-1 list-disc list-inside">
          <li>يُسجَّل الشحن فوراً في محفظتك ودفتر الأستاذ</li>
          <li>يمكنك توزيع الرصيد لاحقاً على مركبات بعينها</li>
          <li>لا يمكن حذف عمليات الشحن — فقط عمليات عكس</li>
        </ul>
      </div>

      <!-- Error -->
      <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">{{ error }}</div>

      <!-- Success -->
      <div v-if="success" class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-4 text-sm">
        <p class="font-medium">✅ تم شحن الرصيد بنجاح</p>
        <p class="mt-1">تم إضافة {{ formatMoney(form.amount) }} ر.س للمحفظة الرئيسية</p>
        <button class="mt-3 px-4 py-1.5 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700" @click="$router.push('/fleet-portal')">
          العودة للوحة التحكم
        </button>
      </div>

      <div v-if="!success" class="flex gap-3 pt-2">
        <button type="submit" :disabled="submitting || !form.amount"
                class="flex-1 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium text-sm disabled:opacity-60"
        >
          {{ submitting ? 'جارٍ الشحن...' : `شحن ${form.amount ? formatMoney(form.amount) + ' ر.س' : ''}` }}
        </button>
        <button type="button" class="px-4 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm"
                @click="$router.back()"
        >
          إلغاء
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'

const BASE = '/api/v1'
const token = () => localStorage.getItem('auth_token') ?? ''

const error     = ref('')
const success   = ref(false)
const submitting = ref(false)

const form = ref({
  wallet_type: 'fleet_main',
  amount: null as number | null,
  notes: '',
})

async function submit() {
  if (!form.value.amount || form.value.amount < 1) {
    error.value = 'يرجى إدخال مبلغ صحيح (1 ر.س على الأقل).'; return
  }
  submitting.value = true; error.value = ''
  try {
    const ikey = `topup-${Date.now()}-${Math.random().toString(36).slice(2)}`
    const r = await fetch(`${BASE}/fleet-portal/wallet/top-up`, {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${token()}`, 'Content-Type': 'application/json',
                 'Accept': 'application/json', 'Idempotency-Key': ikey },
      body: JSON.stringify({
        wallet_type:     form.value.wallet_type,
        amount:          form.value.amount,
        notes:           form.value.notes,
        idempotency_key: ikey,
      }),
    })
    const json = await r.json()
    if (!r.ok) throw new Error(json.message ?? `HTTP ${r.status}`)
    success.value = true
  } catch (e: any) {
    error.value = e.message
  } finally {
    submitting.value = false
  }
}

function formatMoney(v: any) {
  return Number(v ?? 0).toLocaleString('ar-SA', { minimumFractionDigits: 2 })
}
</script>
