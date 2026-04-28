<template>
  <div class="space-y-4">
    <div>
      <h2 class="text-lg font-bold text-gray-900 dark:text-white">التسعير</h2>
      <p class="mt-0.5 text-xs text-gray-500 dark:text-slate-400">
        عرض للاطلاع فقط — نسخ أسعار البيع المعتمدة المرتبطة بحسابك (لا يشمل تكاليف المزودين).
      </p>
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
          class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800"
        >
          <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
              <span class="text-sm font-bold text-gray-900 dark:text-white">النسخة {{ v.version_no }}</span>
              <span
                v-if="v.is_reference"
                class="rounded-full bg-orange-100 px-2 py-0.5 text-[10px] font-bold text-orange-800 dark:bg-orange-900/40 dark:text-orange-100"
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
          <ul v-if="lineItems(v.sell_snapshot).length" class="space-y-1.5 border-t border-gray-100 pt-2 dark:border-slate-700">
            <li
              v-for="(line, idx) in lineItems(v.sell_snapshot)"
              :key="idx"
              class="flex justify-between gap-2 text-xs text-gray-700 dark:text-slate-200"
            >
              <span class="min-w-0 truncate font-medium">{{ line.label }}</span>
              <span class="shrink-0 font-semibold tabular-nums" dir="ltr">{{ line.price }}</span>
            </li>
          </ul>
          <pre
            v-else
            class="mt-2 max-h-40 overflow-auto rounded-lg bg-slate-50 p-2 text-[10px] leading-relaxed text-slate-700 dark:bg-slate-900 dark:text-slate-200"
            dir="ltr"
          >{{ formatSnapshotFallback(v.sell_snapshot) }}</pre>
          <p v-if="v.contract_id != null" class="mt-2 text-[10px] text-gray-400 dark:text-slate-500">
            عقد رقم {{ v.contract_id }}
          </p>
        </article>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import apiClient from '@/lib/apiClient'

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

function lineItems(snapshot: unknown): { label: string; price: string }[] {
  if (!Array.isArray(snapshot)) return []
  return snapshot.map((row, i) => {
    if (row && typeof row === 'object') {
      const r = row as Record<string, unknown>
      const code = String(r.service_code ?? r.code ?? `بند ${i + 1}`)
      const price = r.unit_price != null ? String(r.unit_price) : '—'
      const cur = r.currency != null ? String(r.currency) : 'SAR'
      return { label: code, price: `${price} ${cur}` }
    }
    return { label: `بند ${i + 1}`, price: '—' }
  })
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

onMounted(async () => {
  loading.value = true
  errorMessage.value = ''
  try {
    const { data } = await apiClient.get<{ data?: { versions?: PriceVersionRow[] } }>('/customer-portal/pricing')
    const raw = data.data?.versions
    versions.value = Array.isArray(raw) ? raw : []
  } catch (e: unknown) {
    const ax = e as { response?: { status?: number; data?: { message?: string } } }
    const msg = ax.response?.data?.message
    if (ax.response?.status === 403) {
      errorMessage.value = msg ?? 'هذه الصفحة متاحة لحسابات العملاء فقط.'
    } else {
      errorMessage.value = msg ?? 'تعذر تحميل التسعير. حاول لاحقاً.'
    }
  } finally {
    loading.value = false
  }
})
</script>
