<template>
  <div class="print-container space-y-4">
    <section class="rounded-2xl border border-violet-100 bg-gradient-to-l from-violet-50 via-white to-indigo-50 p-4 dark:border-violet-900/40 dark:from-violet-950/30 dark:via-slate-900 dark:to-indigo-950/20">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="text-lg font-bold text-gray-900 dark:text-white">التسعير</h2>
          <p class="mt-0.5 text-xs text-gray-600 dark:text-slate-300">
            نسخ أسعار البيع المعتمدة المرتبطة بحسابك، مع عرض واضح بالعربية حسب خدمات العقد.
          </p>
        </div>
        <div class="no-print flex items-center gap-2">
          <button class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-gray-200 text-gray-700 hover:bg-gray-50" @click="printPricing">
            طباعة
          </button>
          <button class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-violet-200 text-violet-700 hover:bg-violet-50" @click="sharePricing">
            مشاركة
          </button>
        </div>
      </div>
    </section>
    <section class="no-print rounded-2xl border border-gray-100 bg-white p-3 dark:border-slate-700 dark:bg-slate-800">
      <div class="grid gap-2 md:grid-cols-4">
        <div>
          <label class="mb-1 block text-[10px] text-gray-500">نوع الخدمة</label>
          <select v-model="serviceFilter" class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs dark:border-slate-600 dark:bg-slate-900">
            <option value="">الكل</option>
            <option v-for="name in availableServiceNames" :key="name" :value="name">{{ name }}</option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-[10px] text-gray-500">من سعر</label>
          <input v-model.number="minPriceFilter" type="number" min="0" step="0.01" class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs dark:border-slate-600 dark:bg-slate-900" />
        </div>
        <div>
          <label class="mb-1 block text-[10px] text-gray-500">إلى سعر</label>
          <input v-model.number="maxPriceFilter" type="number" min="0" step="0.01" class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs dark:border-slate-600 dark:bg-slate-900" />
        </div>
        <div class="flex items-end">
          <button class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700" @click="clearFilters">
            مسح الفلاتر
          </button>
        </div>
      </div>
      <div class="mt-2 flex flex-wrap items-center gap-2 text-[11px]">
        <span class="rounded-md bg-violet-50 px-2 py-1 font-semibold text-violet-700 dark:bg-violet-900/30 dark:text-violet-200">عدد النسخ: {{ versions.length }}</span>
        <span class="rounded-md bg-blue-50 px-2 py-1 font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-200">الخدمات الظاهرة: {{ summary.serviceCount }}</span>
        <span class="rounded-md bg-emerald-50 px-2 py-1 font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200">إجمالي الأسعار الظاهرة: {{ summary.totalPrice }}</span>
      </div>
    </section>

    <div
      v-if="demoMode"
      class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800"
    >
      تم تفعيل بيانات العقد والخدمات التجريبية لتمكينك من التجربة.
    </div>
    <p v-if="errorMessage" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-100">
      {{ errorMessage }}
    </p>
    <p v-else-if="loading" class="text-sm text-gray-500 dark:text-slate-400">جاري التحميل…</p>
    <template v-else>
      <p v-if="versions.length === 0" class="text-sm text-gray-500 dark:text-slate-400">
        لا توجد نسخ أسعار مسجّلة بعد. عند اعتماد المنصة لعرض سعرك سيظهر هنا.
      </p>
      <div v-else class="space-y-3">
        <article
          v-for="v in versions"
          :key="v.uuid"
          class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800"
        >
          <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
              <span class="text-sm font-bold text-gray-900 dark:text-white">نسخة التسعير {{ v.version_no }}</span>
              <span
                v-if="v.is_reference"
                class="rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-bold text-violet-800 dark:bg-violet-900/40 dark:text-violet-100"
              >
                السعر الحالي
              </span>
            </div>
            <time
              v-if="v.activated_at"
              class="text-[11px] text-gray-400 dark:text-slate-500"
              :datetime="v.activated_at"
            >
              {{ formatDate(v.activated_at) }}
            </time>
          </div>
          <ul v-if="filteredLineItems(v.sell_snapshot).length" class="space-y-1.5 border-t border-gray-100 pt-2 dark:border-slate-700">
            <li
              v-for="(line, idx) in filteredLineItems(v.sell_snapshot)"
              :key="idx"
              class="grid grid-cols-[1fr_auto] items-center gap-2 rounded-lg bg-gray-50 px-2.5 py-1.5 text-xs text-gray-700 dark:bg-slate-900/60 dark:text-slate-200"
            >
              <span class="min-w-0 truncate font-medium">{{ line.label }}</span>
              <span class="shrink-0 rounded-md bg-white px-2 py-0.5 font-semibold tabular-nums text-violet-700 dark:bg-slate-800 dark:text-violet-300" dir="ltr">{{ line.price }}</span>
            </li>
          </ul>
          <pre
            v-else
            class="mt-2 max-h-40 overflow-auto rounded-lg bg-slate-50 p-2 text-[10px] leading-relaxed text-slate-700 dark:bg-slate-900 dark:text-slate-200"
            dir="ltr"
          >{{ formatSnapshotFallback(v.sell_snapshot) }}</pre>
          <p v-if="v.contract_id != null" class="mt-2 text-[10px] text-gray-500 dark:text-slate-400">
            عقد رقم {{ v.contract_id }}
          </p>
        </article>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import apiClient from '@/lib/apiClient'
import { demoCustomerPricingVersions } from '@/utils/customerDemoData'
import { printDocument } from '@/composables/useAppPrint'
import { useToast } from '@/composables/useToast'

interface PriceVersionRow {
  uuid: string
  version_no: number
  is_reference: boolean
  activated_at: string | null
  sell_snapshot: unknown
  contract_id: number | null
  root_contract_id: number | null
}

const versions = ref<PriceVersionRow[]>([])
const loading = ref(true)
const errorMessage = ref('')
const demoMode = ref(false)
const toast = useToast()
const serviceFilter = ref('')
const minPriceFilter = ref<number | null>(null)
const maxPriceFilter = ref<number | null>(null)

const serviceCodeArabicMap: Record<string, string> = {
  OIL_CHANGE: 'تغيير الزيت',
  FILTER_CHANGE: 'تغيير الفلاتر',
  CAR_WASH: 'غسيل المركبة',
  GENERAL_INSPECTION: 'فحص عام',
}

function serviceLabel(value: unknown, fallback: string): string {
  const raw = String(value || '').trim()
  if (!raw) return fallback
  if (serviceCodeArabicMap[raw]) return serviceCodeArabicMap[raw]
  if (raw.includes('_')) {
    const normalized = raw.toLowerCase().replace(/_/g, ' ')
    return normalized
  }
  return raw
}

function formatPrice(value: unknown, currency: string): string {
  const amount = Number(value ?? 0)
  const safe = Number.isFinite(amount) ? amount : 0
  const cur = currency === 'SAR' ? 'ر.س' : currency
  return `${safe.toLocaleString('ar-SA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${cur}`
}

type PriceLine = { label: string; price: string; rawPrice: number }

function lineItems(snapshot: unknown): PriceLine[] {
  if (!Array.isArray(snapshot)) return []
  return snapshot.map((row, i) => {
    if (row && typeof row === 'object') {
      const r = row as Record<string, unknown>
      const code = r.service_code ?? r.code ?? `ITEM_${i + 1}`
      const label = serviceLabel(code, `بند ${i + 1}`)
      const price = r.unit_price != null ? Number(r.unit_price) : 0
      const cur = r.currency != null ? String(r.currency) : 'SAR'
      return { label, price: formatPrice(price, cur), rawPrice: price }
    }
    return { label: `بند ${i + 1}`, price: '—', rawPrice: 0 }
  })
}

const availableServiceNames = computed(() => {
  const names = new Set<string>()
  for (const v of versions.value) {
    for (const line of lineItems(v.sell_snapshot)) names.add(line.label)
  }
  return Array.from(names).sort((a, b) => a.localeCompare(b, 'ar'))
})

function filteredLineItems(snapshot: unknown): PriceLine[] {
  return lineItems(snapshot).filter((line) => {
    if (serviceFilter.value && line.label !== serviceFilter.value) return false
    if (minPriceFilter.value != null && line.rawPrice < Number(minPriceFilter.value)) return false
    if (maxPriceFilter.value != null && line.rawPrice > Number(maxPriceFilter.value)) return false
    return true
  })
}

const summary = computed(() => {
  let count = 0
  let total = 0
  for (const v of versions.value) {
    const lines = filteredLineItems(v.sell_snapshot)
    count += lines.length
    total += lines.reduce((acc, x) => acc + x.rawPrice, 0)
  }
  return {
    serviceCount: count,
    totalPrice: formatPrice(total, 'SAR'),
  }
})

function clearFilters(): void {
  serviceFilter.value = ''
  minPriceFilter.value = null
  maxPriceFilter.value = null
}

function formatSnapshotFallback(snapshot: unknown): string {
  try {
    return JSON.stringify(snapshot, null, 2)
  } catch {
    return String(snapshot)
  }
}

function formatDate(iso: string): string {
  try {
    return new Date(iso).toLocaleString('ar-SA', { dateStyle: 'medium', timeStyle: 'short' })
  } catch {
    return iso
  }
}

function printPricing(): void {
  void printDocument({ rootSelector: '.print-container', title: 'تسعير العميل', includeFormalFrame: false })
}

async function sharePricing(): Promise<void> {
  const url = window.location.href
  const payload = {
    title: 'تسعير العميل',
    text: 'مشاركة صفحة التسعير المعتمدة',
    url,
  }
  try {
    if (navigator.share) {
      await navigator.share(payload)
      toast.success('تمت المشاركة', 'تمت مشاركة صفحة التسعير بنجاح.')
      return
    }
    await navigator.clipboard.writeText(url)
    toast.success('تم النسخ', 'تم نسخ رابط صفحة التسعير.')
  } catch {
    toast.warning('تعذّرت المشاركة', 'يمكنك نسخ الرابط يدويًا من المتصفح.')
  }
}

onMounted(async () => {
  loading.value = true
  errorMessage.value = ''
  demoMode.value = false
  try {
    const { data } = await apiClient.get<{ data?: { versions?: PriceVersionRow[] } }>('/customer-portal/pricing')
    const raw = data.data?.versions
    versions.value = Array.isArray(raw) ? raw : []
    if (!versions.value.length) {
      versions.value = demoCustomerPricingVersions as PriceVersionRow[]
      demoMode.value = true
    }
  } catch (e: unknown) {
    const ax = e as { response?: { status?: number; data?: { message?: string } } }
    const msg = ax.response?.data?.message
    versions.value = demoCustomerPricingVersions as PriceVersionRow[]
    demoMode.value = true
    errorMessage.value = ax.response?.status === 403
      ? (msg ?? 'تعذر جلب العقد الفعلي، لذلك تم عرض نسخة تجريبية.')
      : (msg ?? 'تعذر تحميل التسعير الفعلي، لذلك تم عرض نسخة تجريبية.')
  } finally {
    loading.value = false
  }
})
</script>
