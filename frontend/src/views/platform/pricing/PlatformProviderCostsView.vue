<template>
  <div class="mx-auto max-w-[1000px] space-y-6 pb-12" dir="rtl">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-bold text-slate-900 dark:text-white">تكاليف المزود</h1>
      <RouterLink :to="{ name: 'platform-providers-list' }" class="text-sm font-semibold text-primary-700 hover:underline dark:text-primary-400">← قائمة المزودين</RouterLink>
    </div>

    <div v-if="!auth.hasPermission('platform.providers.manage')" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm">لا صلاحية.</div>

    <div v-else class="space-y-4">
      <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">
        معرّف المزود
        <input
          v-model.number="providerId"
          type="number"
          min="1"
          class="mt-1 w-full max-w-xs rounded-lg border border-slate-300 px-2 py-2 font-mono text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white"
          dir="ltr"
          @change="load"
        />
      </label>

      <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900/40">
        <h2 class="mb-2 text-sm font-bold">إضافة تكلفة خدمة</h2>
        <div class="grid gap-2 md:grid-cols-3">
          <input v-model="costForm.service_code" placeholder="service_code" class="rounded border px-2 py-2 font-mono text-sm dark:border-slate-600 dark:bg-slate-800" dir="ltr" />
          <input v-model.number="costForm.cost_amount" type="number" min="0" step="any" placeholder="المبلغ" class="rounded border px-2 py-2 text-sm dark:border-slate-600 dark:bg-slate-800" dir="ltr" />
          <input v-model="costForm.currency" placeholder="SAR" class="rounded border px-2 py-2 text-sm dark:border-slate-600 dark:bg-slate-800" dir="ltr" />
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
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import {
  createProviderCost,
  fetchProviderCosts,
  providersApiErrorMessage,
} from '@/composables/platform-admin/usePlatformServiceProvidersApi'
import { useToast } from '@/composables/useToast'

const auth = useAuthStore()
const route = useRoute()
const toast = useToast()

const providerId = ref<number | null>(null)
const rows = ref<Record<string, unknown>[]>([])
const loading = ref(false)
const saving = ref(false)
const costForm = ref({ service_code: '', cost_amount: 0, currency: 'SAR' })

function syncProviderFromRoute(): void {
  const q = route.query.providerId
  if (q != null && q !== '') {
    const n = parseInt(String(q), 10)
    providerId.value = Number.isFinite(n) && n > 0 ? n : null
  }
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
  void load()
})

watch(
  () => route.query.providerId,
  () => void load(),
)
</script>
