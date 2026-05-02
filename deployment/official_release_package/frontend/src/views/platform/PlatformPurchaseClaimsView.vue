<template>
  <div data-testid="platform-purchase-claims-root" class="mx-auto max-w-[1600px] space-y-6 pb-12" dir="rtl">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">مطالبات صرف المستحقات — إشراف المنصة</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
          بعد موافقة المستأجر، تُراجع المطالبة هنا للاعتماد النهائي أو الرفض — الصلاحية: مراجعة مطالبات الصرف.
        </p>
      </div>
      <RouterLink
        :to="{ name: 'platform-overview' }"
        class="text-sm font-semibold text-primary-700 underline decoration-primary-300 underline-offset-2 dark:text-primary-400"
      >
        ← الملخص
      </RouterLink>
    </div>

    <div v-if="!auth.hasPermission('platform.purchase_claims.read')" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-100">
      لا تملك صلاحية الإشراف على مطالبات الصرف.
    </div>

    <template v-else>
      <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900/40">
        <div class="flex flex-wrap gap-3">
          <input
            v-model.number="filters.company_id"
            type="number"
            min="1"
            placeholder="رقم الشركة"
            class="min-w-[10rem] rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white"
            dir="ltr"
            @change="fetchPage(1)"
          />
          <select
            v-model="filters.status"
            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white"
            @change="fetchPage(1)"
          >
            <option value="">كل حالات المستأجر</option>
            <option value="pending">قيد انتظار المستأجر</option>
            <option value="approved">موافق من المستأجر</option>
            <option value="rejected">مرفوض من المستأجر</option>
          </select>
          <select
            v-model="filters.platform_review_status"
            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white"
            @change="fetchPage(1)"
          >
            <option value="">كل حالات المنصة</option>
            <option value="pending">بانتظار اعتماد المنصة</option>
            <option value="approved">معتمد من المنصة</option>
            <option value="rejected">مرفوض من المنصة</option>
          </select>
        </div>
      </div>

      <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900/40">
        <div v-if="loading" class="p-12 text-center text-slate-400">جارٍ التحميل…</div>
        <div v-else-if="rows.length === 0" class="p-12 text-center text-slate-400">لا توجد مطالبات مطابقة</div>
        <table v-else class="w-full text-sm">
          <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-600 dark:bg-slate-800/80">
            <tr>
              <th class="px-4 py-3 text-right font-semibold">المعرّف</th>
              <th class="px-4 py-3 text-right font-semibold">الشركة</th>
              <th class="px-4 py-3 text-right font-semibold">حالة المستأجر</th>
              <th class="px-4 py-3 text-right font-semibold">اعتماد المنصة</th>
              <th class="px-4 py-3 text-right font-semibold">المبلغ المطلوب</th>
              <th class="px-4 py-3 text-right font-semibold">أوامر الشراء</th>
              <th v-if="auth.hasPermission('platform.purchase_claims.review')" class="px-4 py-3 text-right font-semibold">إجراء</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            <tr v-for="c in rows" :key="c.uuid">
              <td class="px-4 py-3 font-mono text-xs" dir="ltr">{{ c.id }}</td>
              <td class="px-4 py-3">
                <RouterLink
                  v-if="c.company_id"
                  :to="{ name: 'platform-company-detail', params: { id: String(c.company_id) } }"
                  class="font-medium text-primary-700 hover:underline dark:text-primary-400"
                >
                  {{ c.company?.name ?? '—' }}
                </RouterLink>
                <span v-else>—</span>
              </td>
              <td class="px-4 py-3">{{ c.status }}</td>
              <td class="px-4 py-3">
                <span class="text-xs">{{ platformReviewLabel(c) }}</span>
                <span v-if="c.platform_reviewer?.name" class="mt-0.5 block text-[11px] text-slate-500" dir="ltr">
                  {{ c.platform_reviewer.name }}
                </span>
              </td>
              <td class="px-4 py-3 font-mono" dir="ltr">{{ c.requested_amount ?? '—' }}</td>
              <td class="px-4 py-3 text-xs">
                <span v-for="(p, i) in c.purchases ?? []" :key="p.id" class="block font-mono text-[11px]" dir="ltr">
                  {{ i + 1 }}. #{{ p.id }} {{ p.reference_number }} — {{ p.billing_flow_type || '—' }}
                </span>
              </td>
              <td v-if="auth.hasPermission('platform.purchase_claims.review')" class="px-4 py-3">
                <div v-if="canPlatformReview(c)" class="flex flex-wrap gap-1">
                  <button
                    type="button"
                    class="rounded border border-emerald-300 px-2 py-1 text-xs text-emerald-800 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-200 dark:hover:bg-emerald-950/40"
                    @click="openReview(c, 'approved')"
                  >
                    اعتماد
                  </button>
                  <button
                    type="button"
                    class="rounded border border-rose-300 px-2 py-1 text-xs text-rose-800 hover:bg-rose-50 dark:border-rose-800 dark:text-rose-200 dark:hover:bg-rose-950/40"
                    @click="openReview(c, 'rejected')"
                  >
                    رفض
                  </button>
                </div>
                <span v-else class="text-xs text-slate-400">—</span>
              </td>
            </tr>
          </tbody>
        </table>
        <div
          v-if="pagination && pagination.last_page && pagination.last_page > 1"
          class="flex items-center justify-between border-t border-slate-200 px-4 py-3 dark:border-slate-600"
        >
          <span class="text-xs text-slate-500">صفحة {{ pagination.current_page }} من {{ pagination.last_page }}</span>
          <div class="flex gap-2">
            <button type="button" class="rounded border px-3 py-1 text-xs" :disabled="page <= 1" @click="fetchPage(page - 1)">السابق</button>
            <button
              type="button"
              class="rounded border px-3 py-1 text-xs"
              :disabled="page >= (pagination.last_page ?? 1)"
              @click="fetchPage(page + 1)"
            >
              التالي
            </button>
          </div>
        </div>
      </div>
    </template>

    <div
      v-if="reviewOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      dir="rtl"
      @click.self="reviewOpen = false"
    >
      <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-600 dark:bg-slate-900">
        <h3 class="text-lg font-bold text-slate-900 dark:text-white">
          {{ reviewDecision === 'approved' ? 'اعتماد صرف المستحقات' : 'رفض من قبل المنصة' }}
        </h3>
        <p class="mt-1 text-xs text-slate-500">المطالبة #{{ reviewTarget?.id }} — {{ reviewTarget?.company?.name }}</p>
        <label class="mt-4 block text-sm font-medium text-slate-700 dark:text-slate-300">ملاحظات (اختياري)</label>
        <textarea v-model="reviewNotes" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white" />
        <div class="mt-4 flex justify-end gap-2">
          <button type="button" class="rounded border px-4 py-2 text-sm" @click="reviewOpen = false">إلغاء</button>
          <button
            type="button"
            class="rounded bg-primary-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
            :disabled="reviewSaving"
            @click="confirmReview"
          >
            {{ reviewSaving ? 'جارٍ الحفظ…' : 'تأكيد' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import {
  fetchPlatformPurchaseClaims,
  purchaseClaimsApiErrorMessage,
  reviewPlatformPurchaseClaim,
  type PlatformPurchaseClaimRow,
} from '@/composables/platform-admin/usePlatformPurchaseClaimsApi'
import { useToast } from '@/composables/useToast'

const auth = useAuthStore()
const toast = useToast()

const filters = ref<{ status: string; platform_review_status: string; company_id: number | null }>({
  status: '',
  platform_review_status: '',
  company_id: null,
})
const rows = ref<PlatformPurchaseClaimRow[]>([])
const pagination = ref<{ current_page?: number; last_page?: number } | null>(null)
const page = ref(1)
const loading = ref(false)

const reviewOpen = ref(false)
const reviewTarget = ref<PlatformPurchaseClaimRow | null>(null)
const reviewDecision = ref<'approved' | 'rejected'>('approved')
const reviewNotes = ref('')
const reviewSaving = ref(false)

function platformReviewLabel(c: PlatformPurchaseClaimRow): string {
  const pr = c.platform_review_status
  if (c.status !== 'approved') return pr ? String(pr) : '—'
  if (pr === 'pending') return 'بانتظار اعتماد المنصة'
  if (pr === 'approved') return 'معتمد من المنصة'
  if (pr === 'rejected') return 'مرفوض من المنصة'
  return '—'
}

function canPlatformReview(c: PlatformPurchaseClaimRow): boolean {
  return c.status === 'approved' && c.platform_review_status === 'pending'
}

function openReview(c: PlatformPurchaseClaimRow, decision: 'approved' | 'rejected'): void {
  reviewTarget.value = c
  reviewDecision.value = decision
  reviewNotes.value = ''
  reviewOpen.value = true
}

async function confirmReview(): Promise<void> {
  if (!reviewTarget.value) return
  reviewSaving.value = true
  try {
    await reviewPlatformPurchaseClaim(reviewTarget.value.id, {
      status: reviewDecision.value,
      platform_review_notes: reviewNotes.value.trim() || null,
    })
    toast.success('مطالبات الصرف', 'تم حفظ قرار المنصة.')
    reviewOpen.value = false
    await fetchPage(page.value)
  } catch (e) {
    toast.error('مطالبات الصرف', purchaseClaimsApiErrorMessage(e))
  } finally {
    reviewSaving.value = false
  }
}

async function fetchPage(p: number): Promise<void> {
  if (!auth.hasPermission('platform.purchase_claims.read')) return
  loading.value = true
  page.value = p
  try {
    const { rows: data, pagination: pag } = await fetchPlatformPurchaseClaims({
      page: p,
      per_page: 25,
      status: filters.value.status || undefined,
      platform_review_status: filters.value.platform_review_status || undefined,
      company_id: filters.value.company_id ?? undefined,
    })
    rows.value = data
    pagination.value = pag
  } catch (e) {
    toast.error('مطالبات الصرف', purchaseClaimsApiErrorMessage(e))
    rows.value = []
  } finally {
    loading.value = false
  }
}

onMounted(() => void fetchPage(1))
</script>
