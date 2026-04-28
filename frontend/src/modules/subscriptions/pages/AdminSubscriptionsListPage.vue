<template>
  <div class="space-y-4">
    <h1 class="text-xl font-bold">قائمة اشتراكات المنصة</h1>
    <p class="text-sm text-slate-600 dark:text-slate-400">
      عرض تفصيلي لكل اشتراك مع روابط للشركة وتفاصيل الاشتراك والمحفظة. أدناه: تفكيك حالات الفواتير المرتبطة
      <strong>بمصدر الاشتراك</strong> و<strong>جميع فواتير نوع subscription للشركة</strong>.
    </p>
    <div v-if="err" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200">
      {{ err }}
    </div>
    <div class="rounded-xl border bg-white dark:bg-slate-900 overflow-auto">
      <table class="w-full min-w-[1100px] text-sm">
        <thead class="bg-slate-50 dark:bg-slate-800">
          <tr>
            <th class="p-2 text-start">الشركة</th>
            <th class="p-2 text-start">الخطة</th>
            <th class="p-2 text-start">الحالة</th>
            <th class="p-2 text-start">البداية / التجديد</th>
            <th class="p-2 text-start">المحفظة</th>
            <th class="p-2 text-start">فواتير (مصدر اشتراك)</th>
            <th class="p-2 text-start">فواتير الشركة (نوع اشتراك)</th>
            <th class="p-2 text-start">آخر نشاط</th>
            <th class="p-2 text-start">إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.subscription?.id" class="border-t border-slate-100 dark:border-slate-800">
            <td class="p-2">
              <RouterLink
                class="font-semibold text-primary-700 hover:underline dark:text-primary-400"
                :to="platformCompanyPath(Number(row.company?.id))"
              >
                {{ row.company?.name || '—' }}
              </RouterLink>
              <span class="block text-xs text-slate-500" dir="ltr">#{{ row.company?.id }}</span>
            </td>
            <td class="p-2">
              <span class="font-medium">{{ row.plan_catalog?.name || row.subscription?.plan }}</span>
              <span class="block text-xs text-slate-500" dir="ltr">{{ row.plan_catalog?.slug || '—' }}</span>
            </td>
            <td class="p-2">{{ row.subscription?.status }}</td>
            <td class="p-2 text-xs" dir="ltr">
              <div>من {{ fmt(row.subscription?.starts_at) }}</div>
              <div>إلى {{ fmt(row.subscription?.ends_at) }}</div>
            </td>
            <td class="p-2 text-xs">
              <RouterLink class="text-primary-700 hover:underline dark:text-primary-400" :to="{ name: 'admin-subscriptions-wallets' }">
                {{ row.wallet?.balance ?? '—' }} {{ row.wallet?.currency || '' }}
              </RouterLink>
              <div class="text-slate-500">{{ row.wallet?.status || '—' }}</div>
            </td>
            <td class="p-2 text-xs">{{ formatBreakdown(row.invoice_status_breakdown) }}</td>
            <td class="p-2 text-xs">{{ formatBreakdown(row.company_subscription_invoice_status_breakdown) }}</td>
            <td class="p-2 text-xs" dir="ltr">{{ fmt(row.last_activity_at) }}</td>
            <td class="p-2">
              <RouterLink
                class="rounded-lg bg-primary-600 px-2 py-1 text-xs font-semibold text-white hover:bg-primary-700"
                :to="{ name: 'admin-subscriptions-detail', params: { subscriptionId: row.subscription?.id } }"
              >
                تفاصيل الاشتراك
              </RouterLink>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
      <button
        type="button"
        class="rounded-lg border px-3 py-1.5 disabled:opacity-40"
        :disabled="page <= 1 || loading"
        @click="page--; load()"
      >
        السابق
      </button>
      <span class="text-slate-600 dark:text-slate-400">صفحة {{ page }} من {{ lastPage }}</span>
      <button
        type="button"
        class="rounded-lg border px-3 py-1.5 disabled:opacity-40"
        :disabled="page >= lastPage || loading"
        @click="page++; load()"
      >
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

function fmt(v: string | null | undefined) {
  if (!v) return '—'
  try {
    return new Date(v).toLocaleString('ar-SA')
  } catch {
    return v
  }
}

function formatBreakdown(obj: Record<string, number> | null | undefined) {
  if (!obj || typeof obj !== 'object' || !Object.keys(obj).length) return '—'
  return Object.entries(obj)
    .map(([k, v]) => `${k}: ${v}`)
    .join('، ')
}

async function load() {
  loading.value = true
  err.value = ''
  try {
    const res = await subscriptionsApi.adminSubscriptionList({ page: page.value, per_page: 25 })
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
