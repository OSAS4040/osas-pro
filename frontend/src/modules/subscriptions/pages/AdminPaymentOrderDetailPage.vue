<template>
  <div class="space-y-4">
    <RouterLink class="text-sm font-semibold text-primary-700 hover:underline" :to="{ name: 'admin-subscriptions-review' }">
      ← طابور المراجعة
    </RouterLink>
    <div v-if="err" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">
      {{ err }}
    </div>
    <template v-else-if="d">
      <h1 class="text-xl font-bold">طلب دفع #{{ d.payment_order?.id }}</h1>
      <p class="text-sm">
        الشركة:
        <RouterLink class="font-semibold text-primary-700 hover:underline" :to="platformCompanyPath(Number(d.company?.id))">
          {{ d.company?.name }} (#{{ d.company?.id }})
        </RouterLink>
      </p>
      <p v-if="d.linked_subscription_id" class="text-sm">
        اشتراك مرتبط (أحدث اشتراك للشركة):
        <RouterLink class="font-semibold text-primary-700 hover:underline" :to="{ name: 'admin-subscriptions-detail', params: { subscriptionId: d.linked_subscription_id } }">
          #{{ d.linked_subscription_id }}
        </RouterLink>
      </p>
      <section class="rounded-xl border bg-white p-4 dark:bg-slate-900">
        <h2 class="mb-2 font-semibold">بيانات الطلب (كاملة)</h2>
        <pre class="overflow-auto text-xs" dir="ltr">{{ JSON.stringify(d.payment_order, null, 2) }}</pre>
      </section>
      <section class="rounded-xl border bg-white p-4 dark:bg-slate-900">
        <h2 class="mb-2 font-semibold">التحويلات والمرفقات</h2>
        <div v-for="s in d.bank_transfer_submissions || []" :key="s.id" class="mb-4 rounded-lg border p-3 text-sm">
          <pre class="mb-2 overflow-auto text-xs" dir="ltr">{{ JSON.stringify(s, null, 2) }}</pre>
          <a
            v-if="s.receipt_url"
            :href="s.receipt_url"
            target="_blank"
            rel="noopener noreferrer"
            class="text-primary-700 hover:underline dark:text-primary-400"
          >
            فتح إثبات التحويل
          </a>
        </div>
      </section>
      <section class="rounded-xl border bg-white p-4 dark:bg-slate-900">
        <h2 class="mb-2 font-semibold">المطابقات البنكية</h2>
        <div v-for="m in d.reconciliation_matches || []" :key="m.id" class="mb-3 rounded-lg border p-3 text-xs">
          <pre class="overflow-auto" dir="ltr">{{ JSON.stringify(m, null, 2) }}</pre>
          <RouterLink
            v-if="m.bank_transaction_id"
            class="mt-2 inline-block text-primary-700 hover:underline"
            :to="{ name: 'admin-subscriptions-bank-tx', params: { id: m.bank_transaction_id } }"
          >
            سجل المعاملة البنكية #{{ m.bank_transaction_id }}
          </RouterLink>
        </div>
      </section>
      <section class="rounded-xl border bg-white p-4 dark:bg-slate-900">
        <h2 class="mb-2 font-semibold">المدفوعات والفواتير الناتجة</h2>
        <pre class="overflow-auto text-xs" dir="ltr">{{ JSON.stringify({ payments: d.payments, resulting_invoices: d.resulting_invoices }, null, 2) }}</pre>
        <div class="mt-2 flex flex-wrap gap-2">
          <RouterLink
            v-for="inv in d.resulting_invoices || []"
            :key="inv.id"
            class="rounded bg-slate-100 px-2 py-1 text-xs dark:bg-slate-800"
            :to="{ name: 'admin-subscriptions-invoice-detail', params: { invoiceId: inv.id } }"
          >
            فاتورة #{{ inv.id }}
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
import { platformCompanyPath } from '../lib/platformLinks'

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
    const res = await subscriptionsApi.adminPaymentOrderDetail(id)
    d.value = res.data?.data ?? null
  } catch (e: any) {
    err.value = e?.response?.data?.message || e?.message || 'تعذر التحميل'
  }
}

onMounted(load)
watch(() => route.params.id, load)
</script>
