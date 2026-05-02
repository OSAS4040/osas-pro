<template>
  <div class="mx-auto max-w-[1600px] space-y-6 pb-12" dir="rtl">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ heading }}</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ subtitle }}</p>
      </div>
      <RouterLink
        :to="{ name: 'platform-overview' }"
        class="text-sm font-semibold text-primary-700 underline decoration-primary-300 underline-offset-2 dark:text-primary-400"
      >
        ← الملخص
      </RouterLink>
    </div>

    <div v-if="auth.hasPermission('platform.pricing.create')" class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900/40">
      <h2 class="mb-3 text-sm font-bold text-slate-800 dark:text-white">إنشاء طلب تسعير (مسودة)</h2>
      <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">
          company_id
          <input v-model.number="createForm.company_id" type="number" min="1" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white" dir="ltr" />
        </label>
        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">
          customer_id
          <input v-model.number="createForm.customer_id" type="number" min="1" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white" dir="ltr" />
        </label>
        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 md:col-span-2">
          عنوان (اختياري)
          <input v-model="createForm.title" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white" />
        </label>
        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 md:col-span-2">
          كود الخدمة
          <input v-model="createForm.service_code" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-2 font-mono text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white" dir="ltr" placeholder="oil_change" />
        </label>
        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">
          الكمية
          <input v-model.number="createForm.quantity" type="number" min="0.001" step="any" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white" dir="ltr" />
        </label>
      </div>
      <div class="mt-3 flex flex-wrap gap-2">
        <button
          type="button"
          class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50"
          :disabled="creating"
          @click="onCreateDraft"
        >
          {{ creating ? 'جارٍ الإنشاء…' : 'إنشاء مسودة' }}
        </button>
        <p class="text-[11px] text-slate-500 dark:text-slate-400">
          استخدم البحث الشامل في الملخص أو مركز الشركات لمعرفة معرفات العميل والشركة بدقة.
        </p>
      </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900/40">
      <div class="flex flex-wrap gap-3">
        <select v-model="filters.status" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white" @change="load(1)">
          <option value="">{{ statusPlaceholder }}</option>
          <option v-for="s in statusOptions" :key="s" :value="s">{{ s }}</option>
        </select>
        <input
          v-model.number="filters.company_id"
          type="number"
          min="1"
          placeholder="فلتر company_id"
          class="min-w-[8rem] rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white"
          dir="ltr"
          @change="load(1)"
        />
        <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600" @click="resetFilters">
          إزالة الفلاتر
        </button>
      </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900/40">
      <div v-if="loading" class="p-12 text-center text-slate-400">جارٍ التحميل…</div>
      <div v-else-if="rows.length === 0" class="p-12 text-center text-slate-400">لا توجد طلبات مطابقة</div>
      <table v-else class="w-full text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-600 dark:bg-slate-800/80">
          <tr>
            <th class="px-4 py-3 text-right font-semibold">UUID</th>
            <th class="px-4 py-3 text-right font-semibold">الحالة</th>
            <th class="px-4 py-3 text-right font-semibold">شركة / عميل</th>
            <th class="px-4 py-3 text-right font-semibold">عنوان</th>
            <th class="px-4 py-3 text-right font-semibold">تاريخ</th>
            <th class="px-4 py-3" />
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
          <tr v-for="r in rows" :key="r.uuid" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50">
            <td class="px-4 py-3 font-mono text-[11px] text-slate-600 dark:text-slate-300" dir="ltr">{{ r.uuid }}</td>
            <td class="px-4 py-3 font-medium">{{ r.status }}</td>
            <td class="px-4 py-3 font-mono text-xs" dir="ltr">{{ r.company_id }} / {{ r.customer_id }}</td>
            <td class="px-4 py-3">{{ r.title || '—' }}</td>
            <td class="px-4 py-3 text-xs text-slate-500">{{ r.created_at || '—' }}</td>
            <td class="px-4 py-3">
              <RouterLink
                :to="{
                  name: 'platform-pricing-request-detail',
                  params: { uuid: r.uuid },
                  query: mode === 'review' ? { from: 'review' } : mode === 'approve' ? { from: 'approve' } : {},
                }"
                class="font-semibold text-primary-700 hover:underline dark:text-primary-400"
              >
                تفاصيل
              </RouterLink>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="pagination && pagination.last_page && pagination.last_page > 1" class="flex items-center justify-between border-t border-slate-200 px-4 py-3 dark:border-slate-600">
        <span class="text-xs text-slate-500">صفحة {{ pagination.current_page }} من {{ pagination.last_page }}</span>
        <div class="flex gap-2">
          <button type="button" class="rounded border px-3 py-1 text-xs" :disabled="page <= 1" @click="load(page - 1)">السابق</button>
          <button type="button" class="rounded border px-3 py-1 text-xs" :disabled="page >= (pagination.last_page ?? 1)" @click="load(page + 1)">التالي</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import {
  fetchPricingRequests,
  pricingApiErrorMessage,
  type PlatformPricingRequestRow,
} from '@/composables/platform-admin/usePlatformPricingControlPlane'
import apiClient from '@/lib/apiClient'
import { useToast } from '@/composables/useToast'

const auth = useAuthStore()
const route = useRoute()
const toast = useToast()

const mode = computed(() => {
  const n = route.name
  if (n === 'platform-pricing-review') return 'review'
  if (n === 'platform-pricing-approve') return 'approve'
  return 'list'
})

const heading = computed(() => {
  if (mode.value === 'review') return 'مراجعة طلبات التسعير'
  if (mode.value === 'approve') return 'اعتماد طلبات التسعير'
  return 'طلبات التسعير'
})

const subtitle = computed(() => {
  if (mode.value === 'review') return 'طلبات تحتاج مراجعة فنية أو قيد المراجعة.'
  if (mode.value === 'approve') return 'طلبات بانتظار اعتماد المنصة بعد التوصية.'
  return 'قائمة طلبات التسعير ومسارات سير العمل.'
})

const statusPlaceholder = computed(() => {
  if (mode.value === 'review') return 'حالات المراجعة (افتراضي)'
  if (mode.value === 'approve') return 'بانتظار اعتماد المنصة (افتراضي)'
  return 'كل الحالات'
})

const STATUS_ALL = ['draft', 'pending_review', 'under_review', 'reviewed_recommended', 'pending_platform_approval', 'approved', 'rejected', 'returned_for_edit']

const statusOptions = computed(() => STATUS_ALL)

const filters = ref<{ status: string; company_id: number | null }>({ status: '', company_id: null })
const rows = ref<PlatformPricingRequestRow[]>([])
const pagination = ref<{ current_page?: number; last_page?: number } | null>(null)
const page = ref(1)
const loading = ref(false)

const createForm = ref({
  company_id: null as number | null,
  customer_id: null as number | null,
  title: '',
  service_code: 'oil_change',
  quantity: 1,
})
const creating = ref(false)

function applyModeDefaults(): void {
  if (mode.value === 'review') {
    filters.value.status = 'pending_review'
  } else if (mode.value === 'approve') {
    filters.value.status = 'pending_platform_approval'
  } else {
    filters.value.status = ''
  }
}

watch(
  () => route.name,
  () => {
    applyModeDefaults()
    load(1)
  },
)

async function load(p: number): Promise<void> {
  if (!auth.hasPermission('platform.pricing.view')) {
    rows.value = []
    return
  }
  loading.value = true
  page.value = p
  try {
    const { rows: data, pagination: pag } = await fetchPricingRequests({
      page: p,
      per_page: 25,
      status: filters.value.status || undefined,
      company_id: filters.value.company_id ?? undefined,
    })
    rows.value = data
    pagination.value = pag
  } catch (e) {
    toast.error('تعذّر التحميل', pricingApiErrorMessage(e))
    rows.value = []
  } finally {
    loading.value = false
  }
}

function resetFilters(): void {
  applyModeDefaults()
  filters.value.company_id = null
  load(1)
}

async function onCreateDraft(): Promise<void> {
  if (!auth.hasPermission('platform.pricing.create')) return
  const cid = createForm.value.company_id
  const cust = createForm.value.customer_id
  if (!cid || !cust) {
    toast.warning('بيانات ناقصة', 'أدخل company_id و customer_id')
    return
  }
  creating.value = true
  try {
    await apiClient.post('/platform/pricing/requests', {
      company_id: cid,
      customer_id: cust,
      title: createForm.value.title || undefined,
      lines: [{ service_code: createForm.value.service_code || 'service', quantity: createForm.value.quantity || 1 }],
    })
    toast.success('تم', 'أُنشئت مسودة طلب التسعير')
    await load(1)
  } catch (e) {
    toast.error('فشل الإنشاء', pricingApiErrorMessage(e))
  } finally {
    creating.value = false
  }
}

onMounted(() => {
  applyModeDefaults()
  load(1)
})
</script>
