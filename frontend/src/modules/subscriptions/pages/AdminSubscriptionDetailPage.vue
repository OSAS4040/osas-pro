<template>
  <div class="space-y-4">
    <div v-if="err" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">
      {{ err }}
    </div>
    <template v-else-if="d">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <h1 class="text-xl font-bold">اشتراك #{{ d.subscription?.id }}</h1>
        <RouterLink class="text-sm font-semibold text-primary-700 hover:underline dark:text-primary-400" :to="{ name: 'admin-subscriptions-list' }">
          ← القائمة
        </RouterLink>
      </div>
      <section class="rounded-xl border bg-white p-4 dark:bg-slate-900">
        <h2 class="mb-2 font-semibold">الشركة والخطة</h2>
        <p class="text-sm">
          <RouterLink class="font-semibold text-primary-700 hover:underline" :to="platformCompanyPath(Number(d.company?.id))">
            {{ d.company?.name }} (#{{ d.company?.id }})
          </RouterLink>
        </p>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
          الخطة: {{ d.plan_catalog?.name }} ({{ d.plan_catalog?.slug }}) — المبلغ {{ d.subscription?.amount }} {{ d.subscription?.currency }}
        </p>
        <p class="mt-1 text-sm">الحالة: {{ d.subscription?.status }}</p>
        <p class="mt-1 text-xs" dir="ltr">starts_at: {{ d.subscription?.starts_at }} — ends_at: {{ d.subscription?.ends_at }}</p>
      </section>
      <section v-if="d.at_risk" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm dark:border-amber-900/50 dark:bg-amber-950/30">
        <h2 class="mb-1 font-semibold">إشارات at-risk</h2>
        <pre class="overflow-auto text-xs" dir="ltr">{{ JSON.stringify(d.at_risk, null, 2) }}</pre>
      </section>
      <section class="rounded-xl border bg-white p-4 dark:bg-slate-900">
        <h2 class="mb-2 font-semibold">المحفظة</h2>
        <p v-if="!d.wallet" class="text-sm text-slate-500">لا توجد محفظة شركة (عميل null) مسجّلة.</p>
        <template v-else>
          <RouterLink class="text-primary-700 hover:underline dark:text-primary-400" :to="{ name: 'admin-subscriptions-wallets' }">
            الرصيد {{ d.wallet.balance }} {{ d.wallet.currency }} — {{ d.wallet.status }}
          </RouterLink>
          <p class="mt-1 text-xs text-slate-500" dir="ltr">{{ JSON.stringify(d.wallet) }}</p>
        </template>
      </section>
      <section class="rounded-xl border bg-white p-4 dark:bg-slate-900">
        <h2 class="mb-2 font-semibold">طلبات الدفع</h2>
        <div class="overflow-auto">
          <table class="w-full text-xs">
            <thead>
              <tr class="bg-slate-50 dark:bg-slate-800">
                <th class="p-2 text-start">#</th>
                <th class="p-2 text-start">مرجع</th>
                <th class="p-2 text-start">حالة</th>
                <th class="p-2 text-start">مبلغ</th>
                <th class="p-2 text-start">رابط</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="o in d.payment_orders || []" :key="o.id" class="border-t">
                <td class="p-2">{{ o.id }}</td>
                <td class="p-2" dir="ltr">{{ o.reference_code }}</td>
                <td class="p-2">{{ o.status }}</td>
                <td class="p-2" dir="ltr">{{ o.total }} {{ o.currency }}</td>
                <td class="p-2">
                  <RouterLink
                    class="text-primary-700 hover:underline"
                    :to="{ name: 'admin-subscriptions-payment-order', params: { id: o.id } }"
                  >
                    تفاصيل الطلب
                  </RouterLink>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
      <section class="rounded-xl border bg-white p-4 dark:bg-slate-900">
        <h2 class="mb-2 font-semibold">المدفوعات</h2>
        <div class="overflow-auto">
          <table class="w-full text-xs">
            <thead>
              <tr class="bg-slate-50 dark:bg-slate-800">
                <th class="p-2 text-start">#</th>
                <th class="p-2 text-start">مبلغ</th>
                <th class="p-2 text-start">طلب دفع</th>
                <th class="p-2 text-start">فاتورة</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in d.payments || []" :key="p.id" class="border-t">
                <td class="p-2">{{ p.id }}</td>
                <td class="p-2" dir="ltr">{{ p.amount }} {{ p.currency }}</td>
                <td class="p-2">
                  <RouterLink
                    v-if="p.payment_order_id"
                    class="text-primary-700 hover:underline"
                    :to="{ name: 'admin-subscriptions-payment-order', params: { id: p.payment_order_id } }"
                  >
                    #{{ p.payment_order_id }}
                  </RouterLink>
                  <span v-else>—</span>
                </td>
                <td class="p-2">
                  <RouterLink
                    v-if="p.invoice_id"
                    class="text-primary-700 hover:underline"
                    :to="{ name: 'admin-subscriptions-invoice-detail', params: { invoiceId: p.invoice_id } }"
                  >
                    #{{ p.invoice_id }}
                  </RouterLink>
                  <span v-else>—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
      <section class="rounded-xl border bg-white p-4 dark:bg-slate-900">
        <h2 class="mb-2 font-semibold">الفواتير (نوع subscription)</h2>
        <div class="overflow-auto">
          <table class="w-full text-xs">
            <thead>
              <tr class="bg-slate-50 dark:bg-slate-800">
                <th class="p-2 text-start">#</th>
                <th class="p-2 text-start">رقم</th>
                <th class="p-2 text-start">حالة</th>
                <th class="p-2 text-start">مبلغ</th>
                <th class="p-2 text-start">رابط</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="inv in d.invoices || []" :key="inv.id" class="border-t">
                <td class="p-2">{{ inv.id }}</td>
                <td class="p-2" dir="ltr">{{ inv.invoice_number }}</td>
                <td class="p-2">{{ inv.status }}</td>
                <td class="p-2" dir="ltr">{{ inv.total }} {{ inv.currency }}</td>
                <td class="p-2">
                  <RouterLink
                    class="text-primary-700 hover:underline"
                    :to="{ name: 'admin-subscriptions-invoice-detail', params: { invoiceId: inv.id } }"
                  >
                    تفاصيل
                  </RouterLink>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
      <section class="rounded-xl border bg-white p-4 dark:bg-slate-900">
        <h2 class="mb-2 font-semibold">سجل التغييرات (SubscriptionChange)</h2>
        <pre class="max-h-64 overflow-auto text-xs" dir="ltr">{{ JSON.stringify(d.subscription_changes || [], null, 2) }}</pre>
      </section>
      <section class="rounded-xl border bg-white p-4 dark:bg-slate-900">
        <h2 class="mb-2 font-semibold">سجل التدقيق (subscriptions_v2_audit_logs)</h2>
        <pre class="max-h-96 overflow-auto text-xs" dir="ltr">{{ JSON.stringify(d.audit_timeline || [], null, 2) }}</pre>
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
  const id = Number(route.params.subscriptionId)
  if (!id) {
    err.value = 'معرّف غير صالح'
    return
  }
  err.value = ''
  d.value = null
  try {
    const res = await subscriptionsApi.adminSubscriptionDetail(id)
    d.value = res.data?.data ?? null
  } catch (e: any) {
    err.value = e?.response?.data?.message || e?.message || 'تعذر التحميل'
  }
}

onMounted(load)
watch(() => route.params.subscriptionId, load)
</script>
