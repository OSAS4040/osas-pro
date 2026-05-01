<template>
  <div class="space-y-4">
    <h1 class="text-xl font-bold">طابور مراجعة طلبات الدفع</h1>
    <div
      v-for="row in rows"
      :id="'po-row-' + row.payment_order?.id"
      :key="row.payment_order?.id"
      class="rounded-xl border p-4 bg-white dark:bg-slate-900"
    >
      <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
          <p class="text-sm font-semibold">
            طلب #{{ row.payment_order?.id }} — {{ row.payment_order?.reference_code }}
          </p>
          <p class="text-xs text-slate-500 mt-1">
            الشركة:
            <RouterLink
              v-if="row.payment_order?.company_id"
              class="font-semibold text-primary-700 hover:underline"
              :to="platformCompanyPath(Number(row.payment_order.company_id))"
            >
              #{{ row.payment_order.company_id }}
            </RouterLink>
          </p>
          <p class="text-xs text-slate-500 mt-1">الحالة: {{ row.payment_order?.status }}</p>
          <p class="text-xs text-slate-500" dir="ltr">المبلغ: {{ row.payment_order?.total }} {{ row.payment_order?.currency }}</p>
        </div>
        <RouterLink
          class="rounded-lg border border-primary-600 px-3 py-1 text-xs font-semibold text-primary-700 hover:bg-primary-50 dark:hover:bg-slate-800"
          :to="{ name: 'admin-subscriptions-payment-order', params: { id: row.payment_order?.id } }"
        >
          صفحة تفاصيل الطلب
        </RouterLink>
      </div>
      <div v-if="row.payment_order?.bank_transfer_submissions?.length" class="mt-3 text-xs">
        <p class="font-semibold text-slate-700 dark:text-slate-200">بيانات التحويل (من الـ API)</p>
        <pre class="mt-1 max-h-48 overflow-auto rounded bg-slate-50 p-2 dark:bg-slate-950" dir="ltr">{{
          JSON.stringify(row.payment_order.bank_transfer_submissions, null, 2)
        }}</pre>
      </div>
      <div v-if="row.matches?.length" class="mt-2 text-xs">
        <p class="font-semibold">المطابقات الحالية</p>
        <pre class="mt-1 max-h-40 overflow-auto rounded bg-slate-50 p-2 dark:bg-slate-950" dir="ltr">{{ JSON.stringify(row.matches, null, 2) }}</pre>
      </div>
      <div class="mt-3 flex gap-2 flex-wrap">
        <template v-for="cand in row.candidates?.slice?.(0, 8) || []" :key="cand.transaction?.id">
          <button
            class="px-2 py-1 border rounded text-xs"
            type="button"
            @click="match(row.payment_order.id, cand.transaction.id)"
          >
            Match Tx#{{ cand.transaction?.id }} ({{ cand.score }})
          </button>
          <RouterLink
            class="px-2 py-1 rounded border border-slate-300 text-xs text-primary-700 dark:border-slate-600"
            :to="{ name: 'admin-subscriptions-bank-tx', params: { id: cand.transaction?.id } }"
          >
            سجل Tx#{{ cand.transaction?.id }}
          </RouterLink>
        </template>
        <button
          class="px-2 py-1 rounded bg-emerald-600 text-white text-xs"
          type="button"
          :disabled="!hasConfirmed(row)"
          @click="approve(row.payment_order.id)"
        >
          Approve
        </button>
        <button class="px-2 py-1 rounded bg-rose-600 text-white text-xs" type="button" @click="reject(row.payment_order.id)">Reject</button>
      </div>
      <AdminSubscriptionsFullPayload class="mt-3" :payload="row" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { nextTick, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { subscriptionsApi } from '../api'
import AdminSubscriptionsFullPayload from '../components/AdminSubscriptionsFullPayload.vue'
import { platformCompanyPath } from '../lib/platformLinks'

const route = useRoute()
const rows = ref<any[]>([])
const hasConfirmed = (row: any) => Array.isArray(row.matches) && row.matches.some((m: any) => String(m.status) === 'confirmed')

async function load() {
  const res = await subscriptionsApi.adminReviewQueue()
  const payload = res.data?.data
  rows.value = Array.isArray(payload?.data) ? payload.data : Array.isArray(payload) ? payload : []
}

async function match(orderId: number, txId: number) {
  await subscriptionsApi.adminMatch(orderId, txId)
  await load()
}
async function approve(orderId: number) {
  await subscriptionsApi.adminApprove(orderId)
  await load()
}
async function reject(orderId: number) {
  await subscriptionsApi.adminReject(orderId, 'manual admin decision')
  await load()
}
function scrollToFocusedOrder(): void {
  const raw = route.query.focus_order ?? route.query.highlight
  const id = raw !== undefined && raw !== null ? String(raw) : ''
  if (!id || !rows.value.length) return
  void nextTick(() => {
    document.getElementById(`po-row-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  })
}

onMounted(async () => {
  await load()
  scrollToFocusedOrder()
})

watch(
  () => [route.query.focus_order, route.query.highlight, rows.value.length],
  () => scrollToFocusedOrder(),
)
</script>
