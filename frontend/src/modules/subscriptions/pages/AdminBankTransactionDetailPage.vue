<template>
  <div class="space-y-4">
    <RouterLink class="text-sm font-semibold text-primary-700 hover:underline" :to="{ name: 'admin-subscriptions-transactions' }">
      ← جدول المعاملات
    </RouterLink>
    <div v-if="err" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">
      {{ err }}
    </div>
    <template v-else-if="d">
      <h1 class="text-xl font-bold">معاملة بنكية #{{ d.bank_transaction?.id }}</h1>
      <section class="rounded-xl border bg-white p-4 dark:bg-slate-900">
        <h2 class="mb-2 font-semibold">بيانات المعاملة</h2>
        <pre class="overflow-auto text-xs" dir="ltr">{{ JSON.stringify(d.bank_transaction, null, 2) }}</pre>
      </section>
      <section class="rounded-xl border bg-white p-4 dark:bg-slate-900">
        <h2 class="mb-2 font-semibold">المطابقات وطلبات الدفع</h2>
        <div v-for="m in d.reconciliation_matches || []" :key="m.id" class="mb-3 rounded-lg border p-3 text-xs">
          <pre class="overflow-auto" dir="ltr">{{ JSON.stringify(m, null, 2) }}</pre>
          <RouterLink
            v-if="m.payment_order_id"
            class="mt-2 inline-block text-primary-700 hover:underline"
            :to="{ name: 'admin-subscriptions-payment-order', params: { id: m.payment_order_id } }"
          >
            طلب الدفع #{{ m.payment_order_id }}
          </RouterLink>
        </div>
      </section>
      <AdminSubscriptionsFullPayload :payload="d" />
    </template>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { subscriptionsApi } from '../api'
import AdminSubscriptionsFullPayload from '../components/AdminSubscriptionsFullPayload.vue'

const route = useRoute()
const d = ref<any>(null)
const err = ref('')

async function load() {
  const id = Number(route.params.id)
  if (!id) {
    err.value = 'معرّف غير صالح'
    return
  }
  err.value = ''
  d.value = null
  try {
    const res = await subscriptionsApi.adminBankTransactionDetail(id)
    d.value = res.data?.data ?? null
  } catch (e: any) {
    err.value = e?.response?.data?.message || e?.message || 'تعذر التحميل'
  }
}

onMounted(load)
watch(() => route.params.id, load)
</script>
