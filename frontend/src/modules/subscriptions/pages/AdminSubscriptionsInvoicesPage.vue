<template>
  <div class="space-y-4">
    <h1 class="text-xl font-bold">فواتير اشتراكات المنصة</h1>
    <div v-if="err" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">
      {{ err }}
    </div>
    <div class="rounded-xl border bg-white dark:bg-slate-900 overflow-auto">
      <table class="w-full min-w-[900px] text-sm">
        <thead class="bg-slate-50 dark:bg-slate-800">
          <tr>
            <th class="p-2 text-start">#</th>
            <th class="p-2 text-start">رقم الفاتورة</th>
            <th class="p-2 text-start">الشركة</th>
            <th class="p-2 text-start">اشتراك مرتبط</th>
            <th class="p-2 text-start">حالة</th>
            <th class="p-2 text-start">تاريخ</th>
            <th class="p-2 text-start">مبلغ</th>
            <th class="p-2 text-start">رابط</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.invoice?.id" class="border-t border-slate-100 dark:border-slate-800">
            <td class="p-2">{{ row.invoice?.id }}</td>
            <td class="p-2 font-mono text-xs" dir="ltr">{{ row.invoice?.invoice_number }}</td>
            <td class="p-2">
              <RouterLink class="text-primary-700 hover:underline" :to="platformCompanyPath(Number(row.company?.id))">
                {{ row.company?.name }}
              </RouterLink>
            </td>
            <td class="p-2">
              <RouterLink
                v-if="row.linked_subscription_id"
                class="text-primary-700 hover:underline"
                :to="{ name: 'admin-subscriptions-detail', params: { subscriptionId: row.linked_subscription_id } }"
              >
                #{{ row.linked_subscription_id }}
              </RouterLink>
              <span v-else class="text-slate-500">—</span>
            </td>
            <td class="p-2">{{ row.invoice?.status }}</td>
            <td class="p-2 text-xs" dir="ltr">{{ row.invoice?.issued_at }}</td>
            <td class="p-2 text-xs" dir="ltr">{{ row.invoice?.total }} {{ row.invoice?.currency }}</td>
            <td class="p-2">
              <RouterLink
                class="rounded-lg bg-primary-600 px-2 py-1 text-xs font-semibold text-white"
                :to="{ name: 'admin-subscriptions-invoice-detail', params: { invoiceId: row.invoice?.id } }"
              >
                تفاصيل
              </RouterLink>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
      <button type="button" class="rounded-lg border px-3 py-1.5 disabled:opacity-40" :disabled="page <= 1 || loading" @click="page--; load()">
        السابق
      </button>
      <span class="text-slate-600 dark:text-slate-400">صفحة {{ page }} من {{ lastPage }}</span>
      <button type="button" class="rounded-lg border px-3 py-1.5 disabled:opacity-40" :disabled="page >= lastPage || loading" @click="page++; load()">
        التالي
      </button>
    </div>
    <AdminSubscriptionsFullPayload v-if="rawPage" :payload="rawPage" />
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { subscriptionsApi } from '../api'
import AdminSubscriptionsFullPayload from '../components/AdminSubscriptionsFullPayload.vue'
import { platformCompanyPath } from '../lib/platformLinks'

const rows = ref<any[]>([])
const rawPage = ref<unknown>(null)
const page = ref(1)
const lastPage = ref(1)
const loading = ref(false)
const err = ref('')

async function load() {
  loading.value = true
  err.value = ''
  try {
    const res = await subscriptionsApi.adminSubscriptionInvoices({ page: page.value, per_page: 25 })
    const p = res.data?.data
    rawPage.value = p ?? null
    rows.value = Array.isArray(p?.data) ? p.data : []
    lastPage.value = Number(p?.last_page || 1)
    page.value = Number(p?.current_page || 1)
  } catch (e: any) {
    err.value = e?.response?.data?.message || e?.message || 'تعذر التحميل'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
