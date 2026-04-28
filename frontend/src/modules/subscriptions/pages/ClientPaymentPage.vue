<template>
  <div class="mx-auto max-w-2xl space-y-5 pb-8" dir="rtl">
    <h1 class="text-xl font-bold text-slate-900 dark:text-white">الدفع وطلبات الاشتراك</h1>

    <div
      v-if="orderId"
      class="rounded-xl border border-primary-200 bg-primary-50/90 px-4 py-3 text-sm text-primary-950 dark:border-primary-900 dark:bg-primary-950/40 dark:text-primary-50"
    >
      تتابع الآن الطلب رقم
      <span class="font-mono font-bold" dir="ltr">#{{ orderId }}</span>
      — أكمل بيانات التحويل أو أرفق الإيصال حسب تعليمات المنصة.
    </div>

    <div class="space-y-3 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
      <p class="text-sm text-slate-700 dark:text-slate-200">المبلغ المطلوب: <strong>{{ money(total) }}</strong></p>
      <p class="text-sm text-slate-700 dark:text-slate-200">الرصيد الحالي: <strong>{{ money(walletBalance) }}</strong></p>
      <label class="block text-sm font-semibold text-slate-800 dark:text-slate-100">طريقة الدفع</label>
      <select v-model="method" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-white">
        <option value="wallet">محفظة الشركة</option>
        <option value="bank">تحويل بنكي</option>
      </select>
      <button
        type="button"
        class="rounded-lg bg-primary-600 px-3 py-2 text-xs font-bold text-white hover:bg-primary-700 disabled:opacity-50"
        :disabled="!planId || !!orderId"
        @click="createOrder"
      >
        إنشاء طلب دفع جديد (للباقة الحالية)
      </button>
      <p v-if="orderId" class="text-xs text-slate-500 dark:text-slate-400">تم ربط جلسة الدفع بطلب قائم.</p>
    </div>

    <div v-if="method === 'bank' && orderId" class="space-y-3 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
      <h2 class="font-semibold text-slate-900 dark:text-white">رفع التحويل والإيصال</h2>
      <input v-model="bankName" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-white" placeholder="اسم البنك" />
      <input type="file" class="text-sm" @change="onFile" />
      <button type="button" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700" @click="submitBank">إرسال التحويل</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { subscriptionsApi } from '../api'
import { useToast } from '@/composables/useToast'

const toast = useToast()
const route = useRoute()
const method = ref<'wallet' | 'bank'>('wallet')
const total = ref(0)
const walletBalance = ref(0)
const orderId = ref<number | null>(null)
const planId = ref<number | null>(null)
const bankName = ref('Bank')
const receipt = ref<File | null>(null)

const money = (v: number) => new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR' }).format(v || 0)

function onFile(e: Event) {
  const target = e.target as HTMLInputElement
  receipt.value = target.files?.[0] ?? null
}

async function createOrder() {
  if (!planId.value || orderId.value) return
  const created = await subscriptionsApi.createPaymentOrder(planId.value)
  const body = created.data?.data as Record<string, unknown> | undefined
  const oid = Number(body?.id ?? body?.payment_order_id)
  orderId.value = Number.isFinite(oid) && oid > 0 ? oid : null
  if (body?.total != null) total.value = Number(body.total)
  toast.success('طلب دفع', 'تم إنشاء الطلب.')
}

async function submitBank() {
  if (!orderId.value) return
  await subscriptionsApi.submitTransfer(orderId.value, {
    amount: total.value,
    transfer_date: new Date().toISOString().slice(0, 10),
    bank_name: bankName.value,
  })
  if (receipt.value) {
    const form = new FormData()
    form.append('receipt', receipt.value)
    await subscriptionsApi.uploadReceipt(orderId.value, form)
  }
  toast.success('تم الاستلام', 'تم استلام التحويل وإرساله للمراجعة.')
}

onMounted(async () => {
  const [current, wallet, plans] = await Promise.all([
    subscriptionsApi.getCurrent(),
    subscriptionsApi.getWallet(),
    subscriptionsApi.getPlans(),
  ])
  planId.value = current.data?.data?.plan?.id ?? null
  const fallbackPlan = Array.isArray(plans.data?.data) ? plans.data.data[0] : null
  if (!planId.value && fallbackPlan?.id) {
    planId.value = Number(fallbackPlan.id)
  }
  const monthly = Number(current.data?.data?.plan?.price_monthly ?? fallbackPlan?.price_monthly ?? 0)
  total.value = monthly * 1.15
  walletBalance.value = Number(wallet.data?.data?.wallet?.balance || 0)

  const qOrder = Number(route.query.order ?? route.query.order_id)
  if (Number.isFinite(qOrder) && qOrder > 0) {
    orderId.value = qOrder
    method.value = 'bank'
    const qTotal = Number(route.query.total)
    if (Number.isFinite(qTotal) && qTotal > 0) {
      total.value = qTotal
    }
  }
})
</script>

