<template>
  <div class="mx-auto max-w-[1000px] space-y-6 pb-12" dir="rtl">
    <div class="flex flex-wrap justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">نسخ أسعار العملاء</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">عرض نسخ الأسعار المعتمدة والمرجعية لكل عميل.</p>
      </div>
      <RouterLink :to="{ name: 'platform-overview' }" class="text-sm font-semibold text-primary-700 hover:underline dark:text-primary-400">← الملخص</RouterLink>
    </div>

    <div v-if="!auth.hasPermission('platform.pricing.view')" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm">لا صلاحية عرض التسعير.</div>

    <div v-else class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900/40">
      <div class="flex flex-wrap gap-3">
        <label class="text-xs font-semibold">
          الشركة
          <select
            v-model.number="companyId"
            class="mt-1 min-w-[14rem] rounded border bg-white px-2 py-2 dark:border-slate-600 dark:bg-slate-800"
            @change="onCompanyChange"
          >
            <option :value="0">— اختر شركة —</option>
            <option v-for="c in companiesPicklist" :key="c.id" :value="c.id">{{ c.name }} · #{{ c.id }}</option>
          </select>
        </label>
        <label class="text-xs font-semibold">
          العميل
          <select
            v-model.number="customerId"
            class="mt-1 min-w-[14rem] rounded border bg-white px-2 py-2 dark:border-slate-600 dark:bg-slate-800"
            :disabled="!companyId"
          >
            <option :value="0">— اختر عميلاً —</option>
            <option v-for="cu in customersPicklist" :key="cu.id" :value="cu.id">{{ cu.name }} · #{{ cu.id }}</option>
          </select>
        </label>
        <button type="button" class="self-end rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="loading" @click="load">
          تحميل
        </button>
      </div>
    </div>

    <div v-if="auth.hasPermission('platform.pricing.view')" class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900/40">
      <div v-if="loading" class="p-8 text-center">جارٍ التحميل…</div>
      <table v-else-if="rows.length" class="w-full text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-600 dark:bg-slate-800/80">
          <tr>
            <th class="px-4 py-2 text-right">version</th>
            <th class="px-4 py-2 text-right">مرجعي</th>
            <th class="px-4 py-2 text-right">تفعيل</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
          <tr v-for="r in rows" :key="String(r.id)">
            <td class="px-4 py-2 font-mono">{{ r.version_no }}</td>
            <td class="px-4 py-2">{{ r.is_reference ? 'نعم' : 'لا' }}</td>
            <td class="px-4 py-2 text-xs">{{ r.activated_at || '—' }}</td>
          </tr>
        </tbody>
      </table>
      <div v-else class="p-8 text-center text-slate-400">أدخل المعرفات واضغط تحميل</div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { fetchCustomerPriceVersions, pricingApiErrorMessage } from '@/composables/platform-admin/usePlatformPricingControlPlane'
import {
  fetchPlatformCompaniesOptions,
  fetchPlatformCustomersOptions,
  type PlatformCompanyOption,
  type PlatformCustomerOption,
} from '@/composables/platform-admin/usePlatformEntityPicklists'
import { useToast } from '@/composables/useToast'

const auth = useAuthStore()
const toast = useToast()

const companyId = ref(0)
const customerId = ref(0)
const companiesPicklist = ref<PlatformCompanyOption[]>([])
const customersPicklist = ref<PlatformCustomerOption[]>([])
const rows = ref<Record<string, unknown>[]>([])
const loading = ref(false)

async function loadCompanies(): Promise<void> {
  try {
    companiesPicklist.value = await fetchPlatformCompaniesOptions({ per_page: 100 })
  } catch {
    companiesPicklist.value = []
  }
}

async function onCompanyChange(): Promise<void> {
  customerId.value = 0
  customersPicklist.value = []
  if (!companyId.value) return
  try {
    customersPicklist.value = await fetchPlatformCustomersOptions({ company_id: companyId.value, per_page: 100 })
  } catch {
    customersPicklist.value = []
  }
}

async function load(): Promise<void> {
  if (!companyId.value || !customerId.value) {
    toast.warning('بيانات', 'اختر الشركة والعميل')
    return
  }
  loading.value = true
  try {
    const { rows: data } = await fetchCustomerPriceVersions({
      company_id: companyId.value,
      customer_id: customerId.value,
    })
    rows.value = data
  } catch (e) {
    toast.error('فشل', pricingApiErrorMessage(e))
    rows.value = []
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void loadCompanies()
})
</script>
