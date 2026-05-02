<template>
  <div class="mx-auto max-w-[1000px] space-y-6 pb-12" dir="rtl">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-bold text-slate-900 dark:text-white">تكاليف المزود</h1>
      <RouterLink :to="{ name: 'platform-providers-list' }" class="text-sm font-semibold text-primary-700 hover:underline dark:text-primary-400">← قائمة المزودين</RouterLink>
    </div>

    <div v-if="!auth.hasPermission('platform.providers.manage')" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm">لا صلاحية.</div>

    <div v-else class="space-y-4">
      <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">
        المزوّد
        <select
          class="mt-1 w-full max-w-md rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white"
          :value="providerId ?? ''"
          @change="onProviderPick"
        >
          <option value="">— اختر مزوّد خدمة —</option>
          <option v-for="p in providerOptions" :key="p.id" :value="p.id">{{ p.name }} · #{{ p.id }}</option>
        </select>
      </label>

      <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900/40">
        <h2 class="mb-2 text-sm font-bold">إضافة تكلفة خدمة</h2>
        <div class="grid gap-2 md:grid-cols-3">
          <select
            v-model="costForm.service_code"
            class="rounded border bg-white px-2 py-2 font-mono text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white"
            dir="ltr"
          >
            <option value="">— كود الخدمة —</option>
            <option v-for="code in PLATFORM_SERVICE_CODE_OPTIONS" :key="code" :value="code">{{ code }}</option>
          </select>
          <input
            v-model.number="costForm.cost_amount"
            type="number"
            min="0"
            step="any"
            placeholder="المبلغ"
            class="rounded border px-2 py-2 text-sm dark:border-slate-600 dark:bg-slate-800"
            dir="ltr"
          />
          <select
            v-model="costForm.currency"
            class="rounded border bg-white px-2 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white"
            dir="ltr"
          >
            <option v-for="cur in PLATFORM_CURRENCY_OPTIONS" :key="cur" :value="cur">{{ cur }}</option>
          </select>
        </div>
        <button type="button" class="mt-2 rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="saving || !providerId" @click="addCost">
          {{ saving ? '…' : 'إضافة' }}
        </button>
      </div>

      <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900/40">
        <div v-if="loading" class="p-8 text-center text-slate-400">جارٍ التحميل…</div>
        <table v-else-if="rows.length" class="w-full text-sm">
          <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-600 dark:bg-slate-800/80">
            <tr>
              <th class="px-4 py-2 text-right">service_code</th>
              <th class="px-4 py-2 text-right">التكلفة</th>
              <th class="px-4 py-2 text-right">العملة</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            <tr v-for="c in rows" :key="String(c.id)">
              <td class="px-4 py-2 font-mono text-xs" dir="ltr">{{ c.service_code }}</td>
              <td class="px-4 py-2 font-mono" dir="ltr">{{ c.cost_amount }}</td>
              <td class="px-4 py-2">{{ c.currency }}</td>
            </tr>
          </tbody>
        </table>
        <div v-else class="p-8 text-center text-slate-400">لا توجد تكاليف أو اختر معرّف مزوداً صالحاً</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import {
  createProviderCost,
  fetchPlatformProviders,
  fetchProviderCosts,
  providersApiErrorMessage,
  type PlatformServiceProviderRow,
} from '@/composables/platform-admin/usePlatformServiceProvidersApi'
import { PLATFORM_CURRENCY_OPTIONS, PLATFORM_SERVICE_CODE_OPTIONS } from '@/constants/platformAdminPicklists'
import { useToast } from '@/composables/useToast'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const toast = useToast()

const providerId = ref<number | null>(null)
const providerOptions = ref<PlatformServiceProviderRow[]>([])
const rows = ref<Record<string, unknown>[]>([])
const loading = ref(false)
const saving = ref(false)
const costForm = ref({ service_code: '', cost_amount: 0, currency: 'SAR' })

function syncProviderFromRoute(): void {
  const q = route.query.providerId
  if (q != null && q !== '') {
    const n = parseInt(String(q), 10)
    providerId.value = Number.isFinite(n) && n > 0 ? n : null
  } else {
    providerId.value = null
  }
}

async function loadProviders(): Promise<void> {
  if (!auth.hasPermission('platform.providers.manage')) {
    providerOptions.value = []
    return
  }
  try {
    const { rows: data } = await fetchPlatformProviders({ per_page: 100, active_only: false })
    providerOptions.value = data
  } catch {
    providerOptions.value = []
  }
}

function onProviderPick(e: Event): void {
  const v = (e.target as HTMLSelectElement).value
  const id = v === '' ? null : parseInt(v, 10)
  const nextQuery = { ...route.query } as Record<string, string | string[] | null | undefined>
  if (id && Number.isFinite(id)) {
    nextQuery.providerId = String(id)
  } else {
    delete nextQuery.providerId
  }
  void router.replace({ query: nextQuery }).then(() => {
    syncProviderFromRoute()
    void load()
  })
}

async function load(): Promise<void> {
  syncProviderFromRoute()
  if (!auth.hasPermission('platform.providers.manage') || !providerId.value) {
    rows.value = []
    return
  }
  loading.value = true
  try {
    const { rows: data } = await fetchProviderCosts(providerId.value, { per_page: 200 })
    rows.value = data
  } catch (e) {
    toast.error('التكاليف', providersApiErrorMessage(e))
    rows.value = []
  } finally {
    loading.value = false
  }
}

async function addCost(): Promise<void> {
  if (!providerId.value || !costForm.value.service_code.trim()) return
  saving.value = true
  try {
    await createProviderCost(providerId.value, {
      service_code: costForm.value.service_code.trim(),
      cost_amount: Number(costForm.value.cost_amount),
      currency: costForm.value.currency || 'SAR',
    })
    toast.success('تم', 'أُضيفت التكلفة')
    costForm.value.service_code = ''
    await load()
  } catch (e) {
    toast.error('فشل', providersApiErrorMessage(e))
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  void loadProviders()
  void load()
})

watch(
  () => route.query.providerId,
  () => void load(),
)
</script>
