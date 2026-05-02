<template>
  <div class="app-shell-page space-y-6" :dir="langInfo.dir">
    <div class="page-head">
      <div class="page-title-wrap">
        <h1 class="page-title-xl">{{ t('providerPortal.executionHub.title') }}</h1>
        <p class="page-subtitle">
          {{ t('providerPortal.executionHub.subtitle') }}
        </p>
        <p class="mt-2 text-xs text-gray-500 dark:text-slate-400">
          {{ t('providerPortal.executionHub.branchHint') }}
        </p>
      </div>
      <div class="page-toolbar">
        <button
          type="button"
          class="px-3 py-2 text-sm rounded-lg border border-primary-300 text-primary-700 dark:border-primary-700 dark:text-primary-300 hover:bg-primary-50 dark:hover:bg-primary-950/40"
          @click="onShare"
        >
          {{ t('providerPortal.share') }}
        </button>
        <button
          type="button"
          class="px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-slate-600 dark:text-white hover:bg-gray-50 dark:hover:bg-slate-700"
          @click="onPrint"
        >
          {{ t('providerPortal.print') }}
        </button>
        <button
          type="button"
          class="px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-slate-600 dark:text-white hover:bg-gray-50 dark:hover:bg-slate-700"
          @click="onSaveJson"
        >
          {{ t('providerPortal.saveJson') }}
        </button>
        <button
          type="button"
          class="px-3 py-2 text-sm rounded-lg border border-emerald-500 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/30"
          @click="onExportCsv"
        >
          {{ t('providerPortal.exportCsv') }}
        </button>
      </div>
    </div>

    <div
      v-if="auth.isPlatform"
      class="no-print rounded-2xl border border-amber-200/90 bg-amber-50/80 p-4 shadow-sm dark:border-amber-900/50 dark:bg-amber-950/30"
    >
      <h2 class="text-sm font-bold text-amber-950 dark:text-amber-100">
        {{ t('providerPortal.executionHub.delegationTitle') }}
      </h2>
      <p class="mt-1 text-xs text-amber-900/80 dark:text-amber-200/90">
        {{ t('providerPortal.executionHub.delegationHint') }}
      </p>
      <div class="mt-3 max-w-lg">
        <label class="flex flex-col gap-1">
          <span class="text-xs font-semibold text-gray-700 dark:text-slate-300">{{
            t('providerPortal.executionHub.selectProvider')
          }}</span>
          <select v-model.number="onBehalfCompanyId" class="field w-full">
            <option :value="0">{{ t('providerPortal.executionHub.selectProviderPlaceholder') }}</option>
            <option v-for="c in executionPartnerCompanies" :key="c.id" :value="c.id">
              {{ c.name }}
            </option>
          </select>
        </label>
      </div>
      <p v-if="auth.isPlatform && onBehalfCompanyId > 0" class="mt-2 text-xs font-medium text-primary-800 dark:text-primary-200">
        {{ delegationActiveLabel }}
      </p>
    </div>

    <div id="provider-execution-hub-print-root" class="print-container space-y-6">
      <p class="hidden print:block text-center text-sm text-gray-700 pb-2 border-b border-gray-200">
        {{ t('providerPortal.executionHub.printHeader') }} — {{ printedAt }}
      </p>

      <div class="no-print rounded-2xl border border-gray-200/90 bg-white/90 p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900/60">
        <div class="space-y-2">
          <label class="label">{{ t('providerPortal.executionHub.workOrderNumber') }}</label>
          <input
            v-model="orderQuery"
            type="text"
            class="field w-full font-mono"
            :placeholder="t('providerPortal.executionHub.woPlaceholder')"
            autocomplete="off"
            @keydown.enter.prevent="runLookup"
          />
        </div>

        <div class="mt-6 space-y-2 border-t border-gray-100 pt-5 dark:border-slate-700">
          <label class="label">{{ t('providerPortal.executionHub.plateLabel') }}</label>
          <p class="text-xs text-gray-500 dark:text-slate-400">
            {{ t('providerPortal.executionHub.plateFormatHint') }}
          </p>
          <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex flex-1 flex-wrap items-end gap-3" dir="ltr">
              <div class="min-w-[7rem] flex-1">
                <span class="mb-1 block text-[11px] font-medium text-gray-500 dark:text-slate-400">{{ t('providerPortal.executionHub.letters3') }}</span>
                <input
                  v-model="plateLetters"
                  type="text"
                  maxlength="3"
                  inputmode="text"
                  autocapitalize="characters"
                  class="field w-full text-center font-mono text-lg font-semibold uppercase tracking-widest"
                  placeholder="ABC"
                  @input="onPlateLettersInput"
                  @keydown.enter.prevent="runLookup"
                />
              </div>
              <div class="min-w-[7rem] flex-1">
                <span class="mb-1 block text-[11px] font-medium text-gray-500 dark:text-slate-400">{{ t('providerPortal.executionHub.digits34') }}</span>
                <input
                  v-model="plateDigits"
                  type="text"
                  maxlength="4"
                  inputmode="numeric"
                  class="field w-full text-center font-mono text-lg font-semibold tracking-wide"
                  placeholder="1234"
                  @input="onPlateDigitsInput"
                  @keydown.enter.prevent="runLookup"
                />
              </div>
            </div>
            <button
              type="button"
              class="btn btn-primary shrink-0 px-6 py-2.5 text-sm font-semibold disabled:opacity-50"
              :disabled="loading || !canLookup"
              @click="runLookup"
            >
              {{ loading ? t('providerPortal.executionHub.searching') : t('providerPortal.executionHub.search') }}
            </button>
          </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4 dark:border-slate-700">
          <CameraIntakeScanner
            :on-behalf-company-id="auth.isPlatform && onBehalfCompanyId > 0 ? onBehalfCompanyId : null"
            @plate="onPlateScanned"
            @order="onOrderFromScan"
            @intake="applyCameraIntake"
          />
          <span class="text-xs text-gray-500 dark:text-slate-400">
            {{ t('providerPortal.executionHub.cameraRowHint') }}
          </span>
        </div>
        <p v-if="errorMsg" class="mt-3 text-sm text-red-600 dark:text-red-400">{{ errorMsg }}</p>
      </div>

      <div v-if="payload" class="grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl border border-gray-200/90 bg-white p-4 dark:border-slate-700 dark:bg-slate-900/50">
          <div class="text-xs font-semibold uppercase text-gray-400">{{ t('providerPortal.executionHub.vehicle') }}</div>
          <template v-if="payload.vehicle">
            <p class="mt-2 font-mono text-xl font-bold tracking-wide text-gray-900 dark:text-slate-100">
              {{ payload.vehicle.plate_number }}
            </p>
            <p class="text-sm text-gray-600 dark:text-slate-300">
              {{ [payload.vehicle.make, payload.vehicle.model].filter(Boolean).join(' ') }}
            </p>
          </template>
          <p v-else class="mt-2 text-sm text-amber-700 dark:text-amber-300">{{ t('providerPortal.executionHub.noVehicle') }}</p>
        </div>

        <div class="rounded-2xl border border-gray-200/90 bg-white p-4 dark:border-slate-700 dark:bg-slate-900/50">
          <div class="text-xs font-semibold uppercase text-gray-400">{{ t('providerPortal.executionHub.workOrder') }}</div>
          <template v-if="payload.work_order">
            <p class="mt-2 font-semibold text-gray-900 dark:text-slate-100">{{ payload.work_order.order_number }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ payload.work_order.status }}</p>
            <p class="mt-2 text-xs" :class="payload.work_order.is_active ? 'text-green-600' : 'text-gray-500'">
              {{ payload.work_order.is_active ? t('providerPortal.executionHub.active') : t('providerPortal.executionHub.notActive') }}
            </p>
            <RouterLink
              v-if="payload.work_order.id"
              :to="{ name: 'work-orders.show', params: { id: String(payload.work_order.id) } }"
              class="no-print mt-3 inline-flex text-sm font-semibold text-primary-600 hover:underline"
            >
              {{ t('providerPortal.executionHub.openWorkOrder') }} →
            </RouterLink>
          </template>
          <p v-else class="mt-2 text-sm text-gray-500">{{ t('providerPortal.executionHub.noWorkOrder') }}</p>
        </div>

        <div class="rounded-2xl border border-gray-200/90 bg-white p-4 dark:border-slate-700 dark:bg-slate-900/50">
          <div class="text-xs font-semibold uppercase text-gray-400">{{ t('providerPortal.executionHub.balanceExecution') }}</div>
          <dl class="mt-2 space-y-1 text-sm text-gray-700 dark:text-slate-300">
            <div class="flex justify-between gap-2">
              <dt>{{ t('providerPortal.executionHub.fleetWallet') }}</dt>
              <dd class="font-mono font-semibold">{{ formatMoney(payload.prepaid?.fleet_main_balance) }}</dd>
            </div>
            <div class="flex justify-between gap-2">
              <dt>{{ t('providerPortal.executionHub.vehicleWallet') }}</dt>
              <dd class="font-mono font-semibold">{{ formatMoney(payload.prepaid?.vehicle_wallet_balance) }}</dd>
            </div>
            <div class="flex justify-between gap-2">
              <dt>{{ t('providerPortal.executionHub.customerWallet') }}</dt>
              <dd class="font-mono font-semibold">{{ formatMoney(payload.prepaid?.customer_main_balance) }}</dd>
            </div>
          </dl>
          <p v-if="payload.execution" class="mt-3 text-xs text-gray-500">
            {{
              payload.execution.can_execute_now
                ? t('providerPortal.executionHub.canExecuteHint')
                : t('providerPortal.executionHub.checkBalanceHint')
            }}
          </p>
        </div>
      </div>

      <div
        v-if="payload && showServiceLinesPanel"
        class="rounded-2xl border border-primary-200/80 bg-primary-50/40 p-5 shadow-sm dark:border-primary-900/50 dark:bg-primary-950/25"
      >
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 class="text-base font-bold text-gray-900 dark:text-slate-100">
              {{ t('providerPortal.executionHub.servicesTitle') }}
            </h2>
            <p class="mt-1 text-xs text-gray-600 dark:text-slate-400">
              {{ t('providerPortal.executionHub.servicesSub') }}
            </p>
          </div>
          <RouterLink
            v-if="payload.work_order?.id"
            :to="{ name: 'work-orders.show', params: { id: String(payload.work_order.id) } }"
            class="no-print inline-flex shrink-0 items-center justify-center rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow hover:bg-primary-700"
          >
            {{ t('providerPortal.executionHub.continueWorkOrder') }} →
          </RouterLink>
        </div>

        <div v-if="serviceLinesList.length" class="mt-4 overflow-x-auto rounded-xl border border-gray-200/90 bg-white dark:border-slate-600 dark:bg-slate-900/40">
          <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-slate-700">
            <thead class="bg-gray-50/90 text-xs font-semibold uppercase text-gray-500 dark:bg-slate-800 dark:text-slate-400">
              <tr>
                <th class="px-3 py-2 text-right">{{ t('providerPortal.executionHub.colType') }}</th>
                <th class="px-3 py-2 text-right">{{ t('providerPortal.executionHub.colItem') }}</th>
                <th class="px-3 py-2 text-left">{{ t('providerPortal.executionHub.colQty') }}</th>
                <th class="px-3 py-2 text-left">{{ t('providerPortal.executionHub.colLineTotal') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
              <tr v-for="row in serviceLinesList" :key="row.id" class="text-gray-800 dark:text-slate-200">
                <td class="whitespace-nowrap px-3 py-2 text-xs text-gray-500">{{ itemTypeLabel(row.item_type) }}</td>
                <td class="px-3 py-2 font-medium">{{ row.name }}</td>
                <td class="whitespace-nowrap px-3 py-2 font-mono text-xs" dir="ltr">{{ row.quantity }}</td>
                <td class="whitespace-nowrap px-3 py-2 font-mono text-xs" dir="ltr">{{ formatMoney(row.total) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="mt-4 rounded-xl border border-dashed border-gray-300 bg-white/80 px-4 py-3 text-sm text-gray-600 dark:border-slate-600 dark:bg-slate-900/30 dark:text-slate-400">
          {{ t('providerPortal.executionHub.noLines') }}
        </p>
      </div>

      <div class="no-print rounded-2xl border border-gray-200/90 bg-white p-5 dark:border-slate-700 dark:bg-slate-900/50">
        <h2 class="text-sm font-bold text-gray-800 dark:text-slate-100">{{ t('providerPortal.executionHub.odometerTitle') }}</h2>
        <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">
          {{ t('providerPortal.executionHub.odometerSub') }}
        </p>
        <div class="mt-3 flex flex-wrap items-center gap-3">
          <input ref="odoFileRef" type="file" accept="image/*" capture="environment" class="hidden" @change="onOdoFile" />
          <button type="button" class="btn btn-secondary text-sm" @click="odoFileRef?.click()">
            {{ t('providerPortal.executionHub.pickOdometer') }}
          </button>
          <span v-if="odoLoading" class="text-xs text-gray-500">{{ t('providerPortal.executionHub.analyzing') }}</span>
        </div>
        <p v-if="odoError" class="mt-2 text-xs text-red-600">{{ odoError }}</p>
        <div v-if="odoSuggestion !== null" class="mt-3 rounded-xl bg-primary-50/80 p-3 text-sm dark:bg-primary-950/30">
          <span class="text-gray-600 dark:text-slate-400">{{ t('providerPortal.executionHub.suggestedReading') }}</span>
          <span class="mr-2 font-mono text-lg font-bold text-primary-800 dark:text-primary-200">{{ odoSuggestion }}</span>
          <span v-if="odoConfidence !== null" class="text-xs text-gray-500">({{ Math.round(odoConfidence * 100) }}%)</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, nextTick, watch, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import CameraIntakeScanner from '@/components/CameraIntakeScanner.vue'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'
import { useLocale } from '@/composables/useLocale'
import { useProviderPortalDocument } from '@/composables/useProviderPortalDocument'
import { PLATFORM_ON_BEHALF_STORAGE_KEY } from '@/composables/usePlatformOnBehalfCatalog'

const { t, icuLocale, langInfo } = useLocale()
const doc = useProviderPortalDocument()
const auth = useAuthStore()

const executionPartnerCompanies = ref<{ id: number; name: string }[]>([])
/** 0 = لم يُختر مزوّد (مشغّل منصّة يجب اختيار مزوّد قبل البحث) */
const onBehalfCompanyId = ref(0)

const delegationActiveLabel = computed(() => {
  if (!auth.isPlatform || onBehalfCompanyId.value < 1) return ''
  const c = executionPartnerCompanies.value.find((x) => x.id === onBehalfCompanyId.value)
  return t('providerPortal.executionHub.delegationActive', { name: c?.name ?? '—' })
})

function readStoredOnBehalf(): void {
  try {
    const raw = localStorage.getItem(PLATFORM_ON_BEHALF_STORAGE_KEY)
    if (raw != null && raw !== '') {
      const n = Number(raw)
      if (!Number.isNaN(n) && n > 0) onBehalfCompanyId.value = n
    }
  } catch {
    /* ignore */
  }
}

onMounted(async () => {
  readStoredOnBehalf()
  if (!auth.isPlatform) return
  try {
    const { data } = await apiClient.get<{ data?: { id: number; name: string; platform_execution_partner?: boolean }[] }>(
      '/platform/companies',
      { params: { per_page: 100 } },
    )
    const rows = data?.data ?? []
    executionPartnerCompanies.value = rows
      .filter((r) => r.platform_execution_partner === true)
      .map((r) => ({ id: r.id, name: r.name }))
  } catch {
    executionPartnerCompanies.value = []
  }
})

watch(onBehalfCompanyId, (v) => {
  if (v < 1) localStorage.removeItem(PLATFORM_ON_BEHALF_STORAGE_KEY)
  else localStorage.setItem(PLATFORM_ON_BEHALF_STORAGE_KEY, String(v))
})

const printedAt = computed(() =>
  new Date().toLocaleString(icuLocale.value, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }),
)

async function onPrint(): Promise<void> {
  await doc.printRegion(
    '#provider-execution-hub-print-root',
    t('providerPortal.executionHub.printDocumentTitle'),
  )
}

async function onShare(): Promise<void> {
  const ord = orderQuery.value.trim()
  const L = normalizedPlateLetters(plateLetters.value)
  const D = normalizedPlateDigits(plateDigits.value)
  const plateFull = L.length === 3 && D.length >= 3 && D.length <= 4 ? `${L} ${D}` : ''
  await doc.shareUrl({
    path: '/execution-hub',
    query: {
      order_number: ord || undefined,
      plate: plateFull || undefined,
    },
    title: t('providerPortal.executionHub.shareTitle'),
    text: t('providerPortal.executionHub.shareText'),
  })
}

function onSaveJson(): void {
  doc.saveJson(
    `execution_hub_${payload.value?.work_order?.order_number ?? 'lookup'}.json`,
    {
      exported_at: new Date().toISOString(),
      lookup: {
        order_query: orderQuery.value.trim() || null,
        plate_letters: plateLetters.value,
        plate_digits: plateDigits.value,
      },
      payload: payload.value,
    },
  )
}

function onExportCsv(): void {
  const p = payload.value
  const headers = [
    t('providerPortal.executionHub.csvWo'),
    t('providerPortal.executionHub.csvPlate'),
    t('providerPortal.executionHub.csvVehicle'),
    t('providerPortal.executionHub.csvLineType'),
    t('providerPortal.executionHub.csvItem'),
    t('providerPortal.executionHub.csvQty'),
    t('providerPortal.executionHub.csvLineTotal'),
  ]
  const wo = p?.work_order?.order_number ?? ''
  const plate = p?.vehicle?.plate_number ?? ''
  const veh = p?.vehicle ? [p.vehicle.make, p.vehicle.model].filter(Boolean).join(' ') : ''
  const lines = serviceLinesList.value
  if (!lines.length) {
    doc.saveCsv(`execution_hub_${wo || 'snapshot'}.csv`, headers, [
      [wo, plate, veh, '', '', '', ''],
    ])
    return
  }
  const rows = lines.map((row: any) => [
    wo,
    plate,
    veh,
    itemTypeLabel(row.item_type),
    String(row.name ?? ''),
    String(row.quantity ?? ''),
    formatMoney(row.total),
  ])
  doc.saveCsv(`execution_hub_${wo || 'lines'}.csv`, headers, rows)
}

const orderQuery = ref('')
const plateLetters = ref('')
const plateDigits = ref('')

const loading = ref(false)
const errorMsg = ref('')
const payload = ref<any>(null)

const serviceLinesList = computed(() => {
  const raw = payload.value?.service_lines
  return Array.isArray(raw) ? raw : []
})

const showServiceLinesPanel = computed(() => {
  const p = payload.value
  if (!p?.work_order?.id) return false
  if (typeof p.show_service_lines === 'boolean') return p.show_service_lines
  const wo = p.work_order
  const anyBal =
    (Number(p.prepaid?.fleet_main_balance) || 0) +
      (Number(p.prepaid?.vehicle_wallet_balance) || 0) +
      (Number(p.prepaid?.customer_main_balance) || 0) >
    0.0001
  const status = String(wo.status ?? '')
  const terminal = ['completed', 'delivered', 'cancelled'].includes(status)
  if (terminal) return false
  return wo.is_active === true || anyBal
})

function itemTypeLabel(rawType: string | null | undefined): string {
  const key = String(rawType ?? '')
  const safe = ['service', 'labor', 'part', 'other'].includes(key) ? key : 'other'
  return t(`providerPortal.executionHub.itemTypeLabels.${safe}`)
}

const odoFileRef = ref<HTMLInputElement | null>(null)
const odoLoading = ref(false)
const odoError = ref('')
const odoSuggestion = ref<number | null>(null)
const odoConfidence = ref<number | null>(null)

const canLookup = computed(() => {
  if (orderQuery.value.trim() !== '') return true
  const L = normalizedPlateLetters(plateLetters.value)
  const D = normalizedPlateDigits(plateDigits.value)
  return L.length === 3 && D.length >= 3 && D.length <= 4
})

function normalizedPlateLetters(raw: string): string {
  return String(raw ?? '')
    .toUpperCase()
    .replace(/[^A-Z]/g, '')
    .slice(0, 3)
}

function normalizedPlateDigits(raw: string): string {
  return String(raw ?? '')
    .replace(/\D/g, '')
    .slice(0, 4)
}

function onPlateLettersInput() {
  plateLetters.value = normalizedPlateLetters(plateLetters.value)
}

function onPlateDigitsInput() {
  plateDigits.value = normalizedPlateDigits(plateDigits.value)
}

function buildLookupParams(): { order_number?: string; plate_number?: string } {
  const ord = orderQuery.value.trim()
  if (ord !== '') {
    return { order_number: ord }
  }
  const L = normalizedPlateLetters(plateLetters.value)
  const D = normalizedPlateDigits(plateDigits.value)
  if (L.length === 3 && D.length >= 3 && D.length <= 4) {
    return { plate_number: `${L} ${D}` }
  }
  return {}
}

function formatMoney(v: unknown): string {
  const n = typeof v === 'number' ? v : Number(v)
  if (Number.isNaN(n)) return '—'
  return n.toLocaleString(icuLocale.value, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

async function runLookup() {
  errorMsg.value = ''
  payload.value = null
  const params = buildLookupParams()
  if (!params.order_number && !params.plate_number) {
    errorMsg.value = t('providerPortal.executionHub.validationNeedInput')
    return
  }
  loading.value = true
  try {
    const requestParams: Record<string, string> = { ...params }
    if (auth.isPlatform && onBehalfCompanyId.value > 0) {
      requestParams.on_behalf_company_id = String(onBehalfCompanyId.value)
    }
    const { data } = await apiClient.get('/work-orders/intake-lookup', { params: requestParams })
    payload.value = data?.data ?? data
  } catch (e: any) {
    errorMsg.value = e?.response?.data?.message ?? t('providerPortal.executionHub.lookupFailed')
  } finally {
    loading.value = false
  }
}

function applyPlatePartsFromString(p: string) {
  const compact = String(p ?? '')
    .toUpperCase()
    .replace(/\s+/g, '')
    .replace(/[^A-Z0-9]/g, '')
  const m = compact.match(/^([A-Z]{3})(\d{3,4})$/)
  if (m) {
    plateLetters.value = m[1] ?? ''
    plateDigits.value = m[2] ?? ''
  }
}

function applyCameraIntake(inner: Record<string, unknown>) {
  errorMsg.value = ''
  loading.value = false
  payload.value = inner as any
  const lk = inner.lookup as Record<string, unknown> | undefined
  const ord = typeof lk?.order_number === 'string' ? lk.order_number.trim() : ''
  const pn = typeof lk?.plate_number === 'string' ? lk.plate_number.trim() : ''
  if (ord) {
    orderQuery.value = ord
    plateLetters.value = ''
    plateDigits.value = ''
  } else if (pn) {
    orderQuery.value = ''
    applyPlatePartsFromString(pn.replace(/\s+/g, ' '))
  }
}

function onPlateScanned(p: string) {
  orderQuery.value = ''
  applyPlatePartsFromString(p)
  nextTick(() => runLookup())
}

function extractWorkOrderFromScan(text: string): string {
  const raw = String(text ?? '').trim()
  if (!raw) return ''
  const pickWo = (s: string) => {
    const m = s.match(/\b(WO[-A-Z0-9]+)\b/i)
    return m?.[1] ? m[1].toUpperCase() : ''
  }
  const direct = pickWo(raw)
  if (direct) return direct
  try {
    const u = new URL(raw)
    const blob = `${u.pathname}${u.search}${u.hash}`
    const fromUrl = pickWo(blob)
    if (fromUrl) return fromUrl
  } catch {
    /* not URL */
  }
  return raw
}

function onOrderFromScan(code: string) {
  const c = extractWorkOrderFromScan(code)
  if (!c) return
  orderQuery.value = c
  plateLetters.value = ''
  plateDigits.value = ''
  nextTick(() => runLookup())
}

async function onOdoFile(ev: Event) {
  const input = ev.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return
  odoError.value = ''
  odoSuggestion.value = null
  odoConfidence.value = null
  odoLoading.value = true
  try {
    const b64 = await fileToBase64(file)
    const { data } = await apiClient.post('/work-orders/intake-odometer-ocr', { image: b64 })
    const d = data?.data ?? data
    odoSuggestion.value = typeof d?.suggested_reading === 'number' ? d.suggested_reading : null
    odoConfidence.value = typeof d?.confidence === 'number' ? d.confidence : null
    if (odoSuggestion.value === null) {
      odoError.value = t('providerPortal.executionHub.ocrNoDigits')
    }
  } catch (e: any) {
    odoError.value = e?.response?.data?.message ?? t('providerPortal.executionHub.ocrFailed')
  } finally {
    odoLoading.value = false
  }
}

function fileToBase64(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const r = new FileReader()
    r.onload = () => resolve(String(r.result ?? ''))
    r.onerror = () => reject(new Error('read'))
    r.readAsDataURL(file)
  })
}
</script>
