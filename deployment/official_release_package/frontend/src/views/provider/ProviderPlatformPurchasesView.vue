<template>
  <div class="app-shell-page">
    <div class="page-head">
      <div class="page-title-wrap">
        <h2 class="page-title-xl">{{ t('providerPortal.platformPurchases.title') }}</h2>
        <p class="page-subtitle">
          {{ t('providerPortal.platformPurchases.subtitle') }}
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

    <div id="provider-platform-purchases-print-root" class="print-container space-y-4">
      <p class="hidden print:block text-center text-sm text-gray-700 pb-2 border-b border-gray-200">
        {{ t('providerPortal.platformPurchases.printHeader') }} — {{ printedAt }}
      </p>

    <div class="table-toolbar no-print">
      <select v-model="filterStatus" class="table-filter w-48" @change="load">
        <option value="">{{ t('providerPortal.platformPurchases.filterAll') }}</option>
        <option value="pending">{{ t('providerPortal.platformPurchases.statusPending') }}</option>
        <option value="ordered">{{ t('providerPortal.platformPurchases.statusOrdered') }}</option>
        <option value="partial">{{ t('providerPortal.platformPurchases.statusPartial') }}</option>
        <option value="received">{{ t('providerPortal.platformPurchases.statusReceived') }}</option>
        <option value="cancelled">{{ t('providerPortal.platformPurchases.statusCancelled') }}</option>
      </select>
    </div>

    <div class="table-shell">
      <h3 class="hidden print:block text-base font-bold text-gray-900 pb-2">{{ t('providerPortal.platformPurchases.listTitle') }}</h3>
      <div class="panel-head no-print">
        <span class="panel-title">{{ t('providerPortal.platformPurchases.listTitle') }}</span>
        <span class="panel-muted">{{ purchases.length }} {{ t('providerPortal.platformPurchases.items') }}</span>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th class="px-4 py-3 text-right">{{ t('providerPortal.platformPurchases.colReference') }}</th>
            <th class="px-4 py-3 text-right">{{ t('providerPortal.platformPurchases.colSupplier') }}</th>
            <th class="px-4 py-3 text-right">{{ t('providerPortal.platformPurchases.colTotal') }}</th>
            <th class="px-4 py-3 text-right">{{ t('providerPortal.platformPurchases.colStatus') }}</th>
            <th class="px-4 py-3 text-right">{{ t('providerPortal.platformPurchases.colExpected') }}</th>
            <th class="no-print px-4 py-3"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in purchases" :key="p.id">
            <td class="font-mono font-semibold text-sm text-gray-900 dark:text-slate-100">{{ p.reference_number }}</td>
            <td>{{ p.supplier?.name ?? '—' }}</td>
            <td class="font-medium">{{ Number(p.total).toFixed(2) }} {{ t('providerPortal.sar') }}</td>
            <td>
              <span :class="statusClass(p.status)" class="rounded-full px-2 py-0.5 text-xs">{{ statusLabel(p.status) }}</span>
            </td>
            <td class="text-xs text-gray-400">{{ p.expected_at?.slice(0, 10) ?? '—' }}</td>
            <td class="no-print px-4 py-3 text-left">
              <RouterLink :to="`/purchases/${p.id}`" class="text-xs text-primary-600 hover:underline">{{ t('providerPortal.platformPurchases.view') }}</RouterLink>
            </td>
          </tr>
          <tr v-if="!purchases.length">
            <td colspan="6" class="table-empty">
              <p class="table-empty-title">{{ t('providerPortal.platformPurchases.emptyTitle') }}</p>
              <p class="table-empty-sub">{{ t('providerPortal.platformPurchases.emptySub') }}</p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta" class="table-pagination no-print">
      <span>{{ t('providerPortal.pagination.page') }} {{ meta.current_page }} {{ t('providerPortal.pagination.of') }} {{ meta.last_page }}</span>
      <div class="flex justify-end gap-2 text-sm">
        <button :disabled="meta.current_page <= 1" class="rounded-lg border px-3 py-1 disabled:opacity-40" @click="changePage(meta.current_page - 1)">{{ t('providerPortal.pagination.prev') }}</button>
        <span class="px-2 py-1 text-gray-500">{{ meta.current_page }} / {{ meta.last_page }}</span>
        <button :disabled="meta.current_page >= meta.last_page" class="rounded-lg border px-3 py-1 disabled:opacity-40" @click="changePage(meta.current_page + 1)">{{ t('providerPortal.pagination.next') }}</button>
      </div>
    </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import apiClient from '@/lib/apiClient'
import { useLocale } from '@/composables/useLocale'
import { useProviderPortalDocument } from '@/composables/useProviderPortalDocument'

const { t, icuLocale } = useLocale()
const doc = useProviderPortalDocument()

const printedAt = computed(() =>
  new Date().toLocaleString(icuLocale.value, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }),
)

async function onPrint(): Promise<void> {
  await doc.printRegion(
    '#provider-platform-purchases-print-root',
    t('providerPortal.platformPurchases.printDocumentTitle'),
  )
}

async function onShare(): Promise<void> {
  await doc.shareUrl({
    path: '/provider/platform-purchases',
    query: { status: filterStatus.value || undefined, page: page.value },
    title: t('providerPortal.platformPurchases.shareTitle'),
    text: t('providerPortal.platformPurchases.shareText'),
  })
}

function onSaveJson(): void {
  doc.saveJson(`platform_purchases_${filterStatus.value || 'all'}_p${page.value}.json`, {
    exported_at: new Date().toISOString(),
    filter_status: filterStatus.value || null,
    page: page.value,
    meta: meta.value,
    purchases: purchases.value,
  })
}

const purchases = ref<any[]>([])
const filterStatus = ref('')
const meta = ref<any>(null)
const page = ref(1)

async function load() {
  const params: Record<string, any> = {
    page: page.value,
    per_page: 25,
    platform_settlement: 1,
  }
  if (filterStatus.value) params.status = filterStatus.value
  const { data } = await apiClient.get('/purchases', { params })
  const root = data?.data
  purchases.value = Array.isArray(root?.data) ? root.data : []
  meta.value = root && 'current_page' in root
    ? { current_page: root.current_page, last_page: root.last_page }
    : null
}

function changePage(p: number) {
  page.value = p
  load()
}

function statusClass(s: string): string {
  const m: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-700',
    ordered: 'bg-blue-100 text-blue-700',
    partial: 'bg-orange-100 text-orange-700',
    received: 'bg-green-100 text-green-700',
    cancelled: 'bg-red-100 text-red-600',
  }
  return m[String(s)] ?? 'bg-gray-100 text-gray-700'
}

function statusLabel(s: string): string {
  const m: Record<string, string> = {
    pending: t('providerPortal.platformPurchases.statusPending'),
    ordered: t('providerPortal.platformPurchases.statusOrdered'),
    partial: t('providerPortal.platformPurchases.statusPartial'),
    received: t('providerPortal.platformPurchases.statusReceived'),
    cancelled: t('providerPortal.platformPurchases.statusCancelled'),
  }
  return m[String(s)] ?? String(s)
}

function onExportCsv(): void {
  const headers = [
    t('providerPortal.platformPurchases.colReference'),
    t('providerPortal.platformPurchases.colSupplier'),
    t('providerPortal.platformPurchases.colTotal'),
    t('providerPortal.platformPurchases.colStatus'),
    t('providerPortal.platformPurchases.colExpected'),
  ]
  const rows = purchases.value.map((p: any) => [
    String(p.reference_number ?? p.id),
    String(p.supplier?.name ?? ''),
    `${Number(p.total).toFixed(2)}`,
    statusLabel(p.status),
    String(p.expected_at?.slice(0, 10) ?? ''),
  ])
  doc.saveCsv(`platform_purchases_${filterStatus.value || 'all'}_p${page.value}.csv`, headers, rows)
}

onMounted(() => load())
</script>
