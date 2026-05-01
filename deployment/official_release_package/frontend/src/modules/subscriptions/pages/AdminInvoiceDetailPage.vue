<template>
  <div class="space-y-4">
    <RouterLink class="text-sm font-semibold text-primary-700 hover:underline" :to="{ name: 'admin-subscriptions-invoices' }">
      ← قائمة الفواتير
    </RouterLink>
    <div v-if="err" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">
      {{ err }}
    </div>
    <template v-else-if="d">
      <h1 class="text-xl font-bold">فاتورة #{{ d.invoice?.id }}</h1>
      <p class="text-sm">
        الشركة:
        <RouterLink class="font-semibold text-primary-700 hover:underline" :to="platformCompanyPath(Number(d.company?.id))">
          {{ d.company?.name }} (#{{ d.company?.id }})
        </RouterLink>
      </p>
      <p class="text-sm">
        اشتراك مرتبط:
        <RouterLink
          v-if="d.linked_subscription_id"
          class="font-semibold text-primary-700 hover:underline"
          :to="{ name: 'admin-subscriptions-detail', params: { subscriptionId: d.linked_subscription_id } }"
        >
          #{{ d.linked_subscription_id }}
        </RouterLink>
        <span v-else class="text-slate-500">—</span>
      </p>
      <p class="text-sm">
        طلب دفع مرتبط (عبر المصدر):
        <RouterLink
          v-if="d.linked_payment_order_id"
          class="font-semibold text-primary-700 hover:underline"
          :to="{ name: 'admin-subscriptions-payment-order', params: { id: d.linked_payment_order_id } }"
        >
          #{{ d.linked_payment_order_id }}
        </RouterLink>
        <span v-else class="text-slate-500">—</span>
      </p>
      <section class="rounded-xl border bg-white p-4 dark:bg-slate-900">
        <h2 class="mb-2 font-semibold">الفاتورة (كامل الحقول)</h2>
        <pre class="overflow-auto text-xs" dir="ltr">{{ JSON.stringify(d.invoice, null, 2) }}</pre>
      </section>
      <section class="rounded-xl border bg-white p-4 dark:bg-slate-900">
        <h2 class="mb-2 font-semibold">المدفوعات المرتبطة</h2>
        <pre class="overflow-auto text-xs" dir="ltr">{{ JSON.stringify(d.payments, null, 2) }}</pre>
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
  const id = Number(route.params.invoiceId)
  if (!id) {
    err.value = 'معرّف غير صالح'
    return
  }
  err.value = ''
  d.value = null
  try {
    const res = await subscriptionsApi.adminSubscriptionInvoiceDetail(id)
    d.value = res.data?.data ?? null
  } catch (e: any) {
    err.value = e?.response?.data?.message || e?.message || 'تعذر التحميل'
  }
}

onMounted(load)
watch(() => route.params.invoiceId, load)
</script>
