<template>
  <div class="app-shell-page space-y-6">
    <div class="page-head">
      <div class="page-title-wrap">
        <h2 class="page-title-xl">{{ t('providerPortal.purchaseClaims.title') }}</h2>
        <p class="page-subtitle">
          {{ t('providerPortal.purchaseClaims.subtitle') }} {{ t('providerPortal.purchaseClaims.subtitlePlatform') }}
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

    <div id="provider-purchase-claims-print-root" class="print-container space-y-6">
      <p class="hidden print:block text-center text-sm text-gray-700 pb-2 border-b border-gray-200">
        {{ t('providerPortal.purchaseClaims.printHeader') }} — {{ printedAt }}
      </p>

      <div v-if="auth.hasPermission('purchases.claims.create')" class="no-print rounded-2xl border border-gray-200/90 bg-white/90 p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900/50">
        <h3 class="text-sm font-bold text-gray-800 dark:text-slate-100">{{ t('providerPortal.purchaseClaims.newClaim') }}</h3>

        <div class="mt-3 space-y-4">
          <div>
            <label class="label">{{ t('providerPortal.purchaseClaims.settlementPoLabel') }}</label>
            <p class="mb-2 text-xs text-gray-500">
              {{ t('providerPortal.purchaseClaims.settlementPoHint') }}
            </p>
            <div v-if="purchasesLoading" class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500">
              {{ t('providerPortal.purchaseClaims.loadingList') }}
            </div>
            <div v-else-if="!settlementPurchases.length" class="rounded-xl border border-dashed border-amber-200/80 bg-amber-50/50 px-4 py-6 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
              {{ t('providerPortal.purchaseClaims.emptySettlementList') }}
            </div>
            <div v-else class="max-h-72 overflow-auto rounded-xl border border-gray-200/90 dark:border-slate-600">
              <table class="data-table text-sm">
                <thead>
                  <tr>
                    <th class="w-10 px-2 py-2" />
                    <th class="px-3 py-2 text-right">{{ t('providerPortal.purchaseClaims.colRef') }}</th>
                    <th class="px-3 py-2 text-right">{{ t('providerPortal.purchaseClaims.colTotal') }}</th>
                    <th class="px-3 py-2 text-right">{{ t('providerPortal.purchaseClaims.colOutstanding') }}</th>
                    <th class="px-3 py-2 text-right">{{ t('providerPortal.purchaseClaims.colStatus') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="p in settlementPurchases" :key="p.id">
                    <td class="px-2 py-2">
                      <input
                        v-model="selectedPurchaseIds"
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-300"
                        :value="p.id"
                      />
                    </td>
                    <td class="font-mono text-xs">{{ p.reference_number || '#' + p.id }}</td>
                    <td>{{ formatMoneyAmount(p.total, p.currency) }}</td>
                    <td>{{ formatMoneyAmount(outstanding(p), p.currency) }}</td>
                    <td><span class="text-xs">{{ purchaseStatusLabel(p.status) }}</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="grid gap-3 sm:grid-cols-2">
            <div class="sm:col-span-2">
              <label class="label">{{ t('providerPortal.purchaseClaims.titleOptional') }}</label>
              <input v-model="form.title" type="text" class="field w-full" maxlength="255" />
            </div>
            <div>
              <label class="label">{{ t('providerPortal.purchaseClaims.suggestedAmountOptional') }}</label>
              <input v-model="form.requested_amount" type="text" inputmode="decimal" class="field w-full" placeholder="0.00" />
              <p class="mt-1 text-xs text-gray-500">
                {{ t('providerPortal.purchaseClaims.suggestedAmountHint') }}
              </p>
            </div>
            <div class="sm:col-span-2">
              <label class="label">{{ t('providerPortal.purchaseClaims.notesOptional') }}</label>
              <textarea v-model="form.description" rows="3" class="field w-full" :placeholder="t('providerPortal.purchaseClaims.notesPlaceholder')" />
            </div>
          </div>
        </div>
        <div class="mt-4 flex justify-end">
          <button
            type="button"
            class="btn btn-primary"
            :disabled="saving || !selectedPurchaseIds.length"
            @click="submitClaim"
          >
            {{ saving ? t('providerPortal.purchaseClaims.sending') : t('providerPortal.purchaseClaims.sendToManagement') }}
          </button>
        </div>
      </div>

      <div class="table-shell">
        <h3 class="hidden print:block text-base font-bold text-gray-900 pb-2">{{ t('providerPortal.purchaseClaims.claimsLogTitle') }}</h3>
        <div class="panel-head no-print">
          <span class="panel-title">{{ t('providerPortal.purchaseClaims.claimsLogTitle') }}</span>
          <select v-model="filterStatus" class="table-filter w-44" @change="load">
            <option value="">{{ t('providerPortal.purchaseClaims.filterAll') }}</option>
            <option value="pending">{{ t('providerPortal.purchaseClaims.filterPending') }}</option>
            <option value="approved">{{ t('providerPortal.purchaseClaims.filterApproved') }}</option>
            <option value="rejected">{{ t('providerPortal.purchaseClaims.filterRejected') }}</option>
          </select>
        </div>
        <table class="data-table">
          <thead>
            <tr>
              <th class="px-4 py-3 text-right">#</th>
              <th class="px-4 py-3 text-right">{{ t('providerPortal.purchaseClaims.colLinkedPos') }}</th>
              <th class="px-4 py-3 text-right">{{ t('providerPortal.purchaseClaims.colTitle') }}</th>
              <th class="px-4 py-3 text-right">{{ t('providerPortal.purchaseClaims.colAmount') }}</th>
              <th class="px-4 py-3 text-right">{{ t('providerPortal.purchaseClaims.colStatus') }}</th>
              <th class="px-4 py-3 text-right">{{ t('providerPortal.purchaseClaims.colPlatform') }}</th>
              <th class="px-4 py-3 text-right">{{ t('providerPortal.purchaseClaims.colBy') }}</th>
              <th class="px-4 py-3 text-right">{{ t('providerPortal.purchaseClaims.colAdminNotes') }}</th>
              <th v-if="auth.hasPermission('purchases.claims.review')" class="no-print px-4 py-3 text-right">{{ t('providerPortal.purchaseClaims.colAction') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in claims" :key="c.id">
              <td class="font-mono text-sm">{{ c.id }}</td>
              <td class="max-w-xs">
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="p in c.purchases || []"
                    :key="p.id"
                    class="inline-flex rounded-md bg-violet-50 px-1.5 py-0.5 text-xs font-mono text-violet-900 dark:bg-violet-950/50 dark:text-violet-100"
                  >
                    {{ p.reference_number || '#' + p.id }}
                  </span>
                  <span v-if="!(c.purchases && c.purchases.length)" class="text-xs text-gray-400">—</span>
                </div>
              </td>
              <td>
                <div class="max-w-xs font-medium">{{ c.title || '—' }}</div>
                <p class="mt-1 line-clamp-2 text-xs text-gray-500">{{ c.description }}</p>
              </td>
              <td>{{ formatMoney(c.requested_amount) }}</td>
              <td>
                <span :class="claimStatusClass(c.status)" class="rounded-full px-2 py-0.5 text-xs">{{ claimStatusLabel(c.status) }}</span>
              </td>
              <td>
                <span :class="platformReviewClass(c)" class="inline-block text-xs">{{ platformReviewDisplay(c) }}</span>
              </td>
              <td class="text-xs">{{ c.creator?.name ?? '—' }}</td>
              <td class="max-w-xs text-xs text-gray-600">{{ c.admin_notes || '—' }}</td>
              <td v-if="auth.hasPermission('purchases.claims.review')" class="no-print text-left">
                <div v-if="c.status === 'pending'" class="flex flex-wrap gap-1">
                  <button type="button" class="btn btn-secondary px-2 py-1 text-xs" @click="openReview(c, 'approved')">{{ t('providerPortal.purchaseClaims.approve') }}</button>
                  <button type="button" class="btn btn-secondary px-2 py-1 text-xs" @click="openReview(c, 'rejected')">{{ t('providerPortal.purchaseClaims.reject') }}</button>
                </div>
                <span v-else class="text-xs text-gray-400">{{ c.reviewer?.name ?? '—' }}</span>
              </td>
            </tr>
            <tr v-if="!claims.length">
              <td :colspan="auth.hasPermission('purchases.claims.review') ? 9 : 8" class="table-empty">
                <p class="table-empty-title">{{ t('providerPortal.purchaseClaims.emptyClaims') }}</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="meta" class="table-pagination no-print">
        <span>{{ meta.current_page }} / {{ meta.last_page }}</span>
        <div class="flex justify-end gap-2 text-sm">
          <button :disabled="meta.current_page <= 1" class="rounded-lg border px-3 py-1 disabled:opacity-40" @click="changePage(meta.current_page - 1)">{{ t('providerPortal.pagination.prev') }}</button>
          <button :disabled="meta.current_page >= meta.last_page" class="rounded-lg border px-3 py-1 disabled:opacity-40" @click="changePage(meta.current_page + 1)">{{ t('providerPortal.pagination.next') }}</button>
        </div>
      </div>
    </div>

    <div
      v-if="reviewOpen"
      class="no-print fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      :dir="langInfo.dir"
      @click.self="reviewOpen = false"
    >
      <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-xl dark:border-slate-600 dark:bg-slate-900">
        <h3 class="text-lg font-bold">{{ reviewDecision === 'approved' ? t('providerPortal.purchaseClaims.modalApproveTitle') : t('providerPortal.purchaseClaims.modalRejectTitle') }}</h3>
        <label class="label mt-3">{{ t('providerPortal.purchaseClaims.colAdminNotes') }}</label>
        <textarea v-model="reviewNotes" rows="3" class="field w-full" />
        <div class="mt-4 flex justify-end gap-2">
          <button type="button" class="btn btn-secondary" @click="reviewOpen = false">{{ t('providerPortal.purchaseClaims.cancel') }}</button>
          <button type="button" class="btn btn-primary" :disabled="reviewSaving" @click="confirmReview">{{ t('providerPortal.purchaseClaims.confirm') }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import apiClient from '@/lib/apiClient'
import { useLocale } from '@/composables/useLocale'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useProviderPortalDocument } from '@/composables/useProviderPortalDocument'

const { t, icuLocale, langInfo } = useLocale()
const auth = useAuthStore()
const toast = useToast()
const doc = useProviderPortalDocument()

const printedAt = computed(() =>
  new Date().toLocaleString(icuLocale.value, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }),
)

async function onPrint(): Promise<void> {
  await doc.printRegion('#provider-purchase-claims-print-root', t('providerPortal.purchaseClaims.printDocumentTitle'))
}

async function onShare(): Promise<void> {
  await doc.shareUrl({
    path: '/provider/purchase-claims',
    query: { status: filterStatus.value || undefined, page: page.value },
    title: t('providerPortal.purchaseClaims.shareTitle'),
    text: t('providerPortal.purchaseClaims.shareText'),
  })
}

function onSaveJson(): void {
  doc.saveJson(`purchase_claims_${filterStatus.value || 'all'}_p${page.value}.json`, {
    exported_at: new Date().toISOString(),
    filter_status: filterStatus.value || null,
    page: page.value,
    meta: meta.value,
    claims: claims.value,
    settlement_purchase_orders: settlementPurchases.value,
  })
}

const claims = ref<any[]>([])
const meta = ref<any>(null)
const page = ref(1)
const filterStatus = ref('')
const saving = ref(false)
const form = ref({ title: '', description: '', requested_amount: '' })

const settlementPurchases = ref<any[]>([])
const purchasesLoading = ref(false)
const selectedPurchaseIds = ref<number[]>([])

const reviewOpen = ref(false)
const reviewTarget = ref<any>(null)
const reviewDecision = ref<'approved' | 'rejected'>('approved')
const reviewNotes = ref('')
const reviewSaving = ref(false)

function outstanding(p: any): number {
  const totalAmt = Number(p.total ?? 0)
  const paid = Number(p.paid_amount ?? 0)
  return Math.max(0, totalAmt - paid)
}

function formatMoneyAmount(v: unknown, currency?: string): string {
  if (v === null || v === undefined || v === '') return '—'
  const n = Number(v)
  if (Number.isNaN(n)) return '—'
  const cur = currency || 'SAR'
  return `${n.toFixed(2)} ${cur === 'SAR' ? t('providerPortal.sar') : cur}`
}

function purchaseStatusLabel(s: string): string {
  const m: Record<string, string> = {
    pending: t('providerPortal.purchaseClaims.poStatus.pending'),
    ordered: t('providerPortal.purchaseClaims.poStatus.ordered'),
    partial: t('providerPortal.purchaseClaims.poStatus.partial'),
    received: t('providerPortal.purchaseClaims.poStatus.received'),
    cancelled: t('providerPortal.purchaseClaims.poStatus.cancelled'),
  }
  return m[s] ?? String(s)
}

async function loadSettlementPurchases() {
  purchasesLoading.value = true
  try {
    const { data } = await apiClient.get('/purchases', {
      params: { billing_flow_type: 'platform_to_provider_purchase', per_page: 100 },
    })
    const root = data?.data
    settlementPurchases.value = Array.isArray(root?.data) ? root.data : []
  } catch {
    settlementPurchases.value = []
    toast.error(t('providerPortal.purchaseClaims.toastLoadPurchasesError'), '')
  } finally {
    purchasesLoading.value = false
  }
}

async function load() {
  const params: Record<string, any> = { page: page.value, per_page: 25 }
  if (filterStatus.value) params.status = filterStatus.value
  const { data } = await apiClient.get('/purchase-claims', { params })
  const root = data?.data
  claims.value = Array.isArray(root?.data) ? root.data : []
  meta.value = root && 'current_page' in root
    ? { current_page: root.current_page, last_page: root.last_page }
    : null
}

function changePage(p: number) {
  page.value = p
  load()
}

function formatMoney(v: unknown): string {
  if (v === null || v === undefined || v === '') return '—'
  const n = Number(v)
  if (Number.isNaN(n)) return '—'
  return `${n.toFixed(2)} ${t('providerPortal.sar')}`
}

function claimStatusClass(s: string): string {
  const m: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-700',
  }
  return m[s] ?? 'bg-gray-100 text-gray-700'
}

function claimStatusLabel(s: string): string {
  const m: Record<string, string> = {
    pending: t('providerPortal.purchaseClaims.claimStatus.pending'),
    approved: t('providerPortal.purchaseClaims.claimStatus.approved'),
    rejected: t('providerPortal.purchaseClaims.claimStatus.rejected'),
  }
  return m[s] ?? s
}

function platformReviewDisplay(c: Record<string, unknown>): string {
  if (c.status !== 'approved') return t('providerPortal.purchaseClaims.platformReview.na')
  const pr = c.platform_review_status as string | null | undefined
  if (pr === 'pending') return t('providerPortal.purchaseClaims.platformReview.pending')
  if (pr === 'approved') return t('providerPortal.purchaseClaims.platformReview.approved')
  if (pr === 'rejected') return t('providerPortal.purchaseClaims.platformReview.rejected')
  return t('providerPortal.purchaseClaims.platformReview.na')
}

function platformReviewClass(c: Record<string, unknown>): string {
  if (c.status !== 'approved') return 'text-gray-400'
  const pr = c.platform_review_status as string | null | undefined
  if (pr === 'pending') return 'rounded-full bg-amber-100 px-2 py-0.5 font-medium text-amber-900 dark:bg-amber-950/50 dark:text-amber-100'
  if (pr === 'approved') return 'rounded-full bg-emerald-100 px-2 py-0.5 font-medium text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100'
  if (pr === 'rejected') return 'rounded-full bg-red-100 px-2 py-0.5 font-medium text-red-800 dark:bg-red-950/40 dark:text-red-100'
  return 'text-gray-400'
}

function onExportCsv(): void {
  const headers = [
    t('providerPortal.purchaseClaims.csvColId'),
    t('providerPortal.purchaseClaims.csvColRefs'),
    t('providerPortal.purchaseClaims.csvColTitle'),
    t('providerPortal.purchaseClaims.csvColAmount'),
    t('providerPortal.purchaseClaims.csvColStatus'),
    t('providerPortal.purchaseClaims.csvColPlatform'),
    t('providerPortal.purchaseClaims.csvColBy'),
    t('providerPortal.purchaseClaims.csvColAdminNotes'),
  ]
  const rows = claims.value.map((c: any) => [
    String(c.id),
    (c.purchases || []).map((p: any) => p.reference_number || p.id).join('; '),
    String(c.title ?? ''),
    String(c.requested_amount ?? ''),
    claimStatusLabel(c.status),
    platformReviewDisplay(c),
    String(c.creator?.name ?? ''),
    String(c.admin_notes ?? ''),
  ])
  doc.saveCsv(`purchase_claims_${filterStatus.value || 'all'}_p${page.value}.csv`, headers, rows)
}

watch(
  selectedPurchaseIds,
  (ids) => {
    const sum = settlementPurchases.value
      .filter((p) => ids.includes(Number(p.id)))
      .reduce((acc, p) => acc + outstanding(p), 0)
    form.value.requested_amount = sum > 0 ? sum.toFixed(2) : ''
  },
  { deep: true },
)

async function submitClaim() {
  if (!selectedPurchaseIds.value.length) return
  saving.value = true
  try {
    const payload: Record<string, unknown> = {
      purchase_ids: selectedPurchaseIds.value.map((id) => Number(id)),
    }
    if (form.value.title.trim()) payload.title = form.value.title.trim()
    if (form.value.description.trim()) payload.description = form.value.description.trim()
    if (form.value.requested_amount.trim()) {
      const n = Number(form.value.requested_amount.replace(/,/g, ''))
      if (!Number.isNaN(n)) payload.requested_amount = n
    }
    await apiClient.post('/purchase-claims', payload)
    toast.success(t('providerPortal.purchaseClaims.toastSentTitle'), t('providerPortal.purchaseClaims.toastSentBody'))
    form.value = { title: '', description: '', requested_amount: '' }
    selectedPurchaseIds.value = []
    await load()
    await loadSettlementPurchases()
  } catch (e: any) {
    toast.error(t('providerPortal.purchaseClaims.toastSendError'), e?.response?.data?.message ?? '')
  } finally {
    saving.value = false
  }
}

function openReview(c: any, decision: 'approved' | 'rejected') {
  reviewTarget.value = c
  reviewDecision.value = decision
  reviewNotes.value = ''
  reviewOpen.value = true
}

async function confirmReview() {
  if (!reviewTarget.value) return
  reviewSaving.value = true
  try {
    await apiClient.patch(`/purchase-claims/${reviewTarget.value.id}/review`, {
      status: reviewDecision.value,
      admin_notes: reviewNotes.value.trim() || null,
    })
    toast.success(t('providerPortal.purchaseClaims.toastUpdatedTitle'), '')
    reviewOpen.value = false
    await load()
  } catch (e: any) {
    toast.error(t('providerPortal.purchaseClaims.toastUpdateError'), e?.response?.data?.message ?? '')
  } finally {
    reviewSaving.value = false
  }
}

onMounted(() => {
  load()
  loadSettlementPurchases()
})
</script>
