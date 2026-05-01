<template>
  <div class="app-shell-page space-y-6">
    <div class="page-head">
      <div class="page-title-wrap">
        <h2 class="page-title-xl">{{ l('مطالبات المشتريات', 'Purchase claims') }}</h2>
        <p class="page-subtitle">
          {{
            l(
              'أرسل مطالبة للإدارة للمراجعة؛ يمكن الموافقة أو الرفض أو إضافة ملاحظات.',
              'Submit a claim for management review — approve, reject, or add notes.',
            )
          }}
        </p>
      </div>
    </div>

    <div v-if="auth.hasPermission('purchases.claims.create')" class="rounded-2xl border border-gray-200/90 bg-white/90 p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900/50">
      <h3 class="text-sm font-bold text-gray-800 dark:text-slate-100">{{ l('مطالبة جديدة', 'New claim') }}</h3>
      <div class="mt-3 grid gap-3 sm:grid-cols-2">
        <div class="sm:col-span-2">
          <label class="label">{{ l('عنوان مختصر (اختياري)', 'Title (optional)') }}</label>
          <input v-model="form.title" type="text" class="field w-full" maxlength="255" />
        </div>
        <div>
          <label class="label">{{ l('مبلغ مقترح (اختياري)', 'Suggested amount (optional)') }}</label>
          <input v-model="form.requested_amount" type="text" inputmode="decimal" class="field w-full" placeholder="0.00" />
        </div>
        <div class="sm:col-span-2">
          <label class="label">{{ l('التفاصيل', 'Details') }}</label>
          <textarea v-model="form.description" rows="4" class="field w-full" :placeholder="l('صف البنود أو الفاتورة أو السياق…', 'Describe items, invoice, or context…')" />
        </div>
      </div>
      <div class="mt-4 flex justify-end">
        <button type="button" class="btn btn-primary" :disabled="saving || !form.description.trim()" @click="submitClaim">
          {{ saving ? l('جارٍ الإرسال…', 'Sending…') : l('إرسال للإدارة', 'Send to management') }}
        </button>
      </div>
    </div>

    <div class="table-shell">
      <div class="panel-head">
        <span class="panel-title">{{ l('سجل المطالبات', 'Claims log') }}</span>
        <select v-model="filterStatus" class="table-filter w-44" @change="load">
          <option value="">{{ l('كل الحالات', 'All') }}</option>
          <option value="pending">{{ l('قيد المراجعة', 'Pending') }}</option>
          <option value="approved">{{ l('موافق عليها', 'Approved') }}</option>
          <option value="rejected">{{ l('مرفوضة', 'Rejected') }}</option>
        </select>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th class="px-4 py-3 text-right">#</th>
            <th class="px-4 py-3 text-right">{{ l('العنوان', 'Title') }}</th>
            <th class="px-4 py-3 text-right">{{ l('المبلغ', 'Amount') }}</th>
            <th class="px-4 py-3 text-right">{{ l('الحالة', 'Status') }}</th>
            <th class="px-4 py-3 text-right">{{ l('من', 'By') }}</th>
            <th class="px-4 py-3 text-right">{{ l('ملاحظات الإدارة', 'Admin notes') }}</th>
            <th v-if="auth.hasPermission('purchases.claims.review')" class="px-4 py-3 text-right">{{ l('إجراء', 'Action') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in claims" :key="c.id">
            <td class="font-mono text-sm">{{ c.id }}</td>
            <td>
              <div class="max-w-xs font-medium">{{ c.title || '—' }}</div>
              <p class="mt-1 line-clamp-2 text-xs text-gray-500">{{ c.description }}</p>
            </td>
            <td>{{ formatMoney(c.requested_amount) }}</td>
            <td>
              <span :class="claimStatusClass(c.status)" class="rounded-full px-2 py-0.5 text-xs">{{ claimStatusLabel(c.status) }}</span>
            </td>
            <td class="text-xs">{{ c.creator?.name ?? '—' }}</td>
            <td class="max-w-xs text-xs text-gray-600">{{ c.admin_notes || '—' }}</td>
            <td v-if="auth.hasPermission('purchases.claims.review')" class="text-left">
              <div v-if="c.status === 'pending'" class="flex flex-wrap gap-1">
                <button type="button" class="btn btn-secondary px-2 py-1 text-xs" @click="openReview(c, 'approved')">{{ l('موافقة', 'Approve') }}</button>
                <button type="button" class="btn btn-secondary px-2 py-1 text-xs" @click="openReview(c, 'rejected')">{{ l('رفض', 'Reject') }}</button>
              </div>
              <span v-else class="text-xs text-gray-400">{{ c.reviewer?.name ?? '—' }}</span>
            </td>
          </tr>
          <tr v-if="!claims.length">
            <td :colspan="auth.hasPermission('purchases.claims.review') ? 7 : 6" class="table-empty">
              <p class="table-empty-title">{{ l('لا توجد مطالبات', 'No claims yet') }}</p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta" class="table-pagination">
      <span>{{ meta.current_page }} / {{ meta.last_page }}</span>
      <div class="flex justify-end gap-2 text-sm">
        <button :disabled="meta.current_page <= 1" class="rounded-lg border px-3 py-1 disabled:opacity-40" @click="changePage(meta.current_page - 1)">{{ l('السابق', 'Prev') }}</button>
        <button :disabled="meta.current_page >= meta.last_page" class="rounded-lg border px-3 py-1 disabled:opacity-40" @click="changePage(meta.current_page + 1)">{{ l('التالي', 'Next') }}</button>
      </div>
    </div>

    <div
      v-if="reviewOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      dir="rtl"
      @click.self="reviewOpen = false"
    >
      <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-xl dark:border-slate-600 dark:bg-slate-900">
        <h3 class="text-lg font-bold">{{ reviewDecision === 'approved' ? l('موافقة على المطالبة', 'Approve claim') : l('رفض المطالبة', 'Reject claim') }}</h3>
        <label class="label mt-3">{{ l('ملاحظات الإدارة', 'Admin notes') }}</label>
        <textarea v-model="reviewNotes" rows="3" class="field w-full" />
        <div class="mt-4 flex justify-end gap-2">
          <button type="button" class="btn btn-secondary" @click="reviewOpen = false">{{ l('إلغاء', 'Cancel') }}</button>
          <button type="button" class="btn btn-primary" :disabled="reviewSaving" @click="confirmReview">{{ l('تأكيد', 'Confirm') }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import apiClient from '@/lib/apiClient'
import { useLocale } from '@/composables/useLocale'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'

const locale = useLocale()
const auth = useAuthStore()
const toast = useToast()
const l = (ar: string, en: string) => (locale.lang.value === 'ar' ? ar : en)

const claims = ref<any[]>([])
const meta = ref<any>(null)
const page = ref(1)
const filterStatus = ref('')
const saving = ref(false)
const form = ref({ title: '', description: '', requested_amount: '' })

const reviewOpen = ref(false)
const reviewTarget = ref<any>(null)
const reviewDecision = ref<'approved' | 'rejected'>('approved')
const reviewNotes = ref('')
const reviewSaving = ref(false)

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
  return `${n.toFixed(2)} ${l('ر.س', 'SAR')}`
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
    pending: l('قيد المراجعة', 'Pending'),
    approved: l('موافق عليها', 'Approved'),
    rejected: l('مرفوضة', 'Rejected'),
  }
  return m[s] ?? s
}

async function submitClaim() {
  saving.value = true
  try {
    const payload: Record<string, unknown> = { description: form.value.description.trim() }
    if (form.value.title.trim()) payload.title = form.value.title.trim()
    if (form.value.requested_amount.trim()) {
      const n = Number(form.value.requested_amount.replace(/,/g, ''))
      if (!Number.isNaN(n)) payload.requested_amount = n
    }
    await apiClient.post('/purchase-claims', payload)
    toast.success(l('تم الإرسال', 'Sent'), l('ستصل المطالبة للإدارة.', 'The claim was sent to management.'))
    form.value = { title: '', description: '', requested_amount: '' }
    await load()
  } catch (e: any) {
    toast.error(l('تعذّر الإرسال', 'Failed'), e?.response?.data?.message ?? '')
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
    toast.success(l('تم التحديث', 'Updated'), '')
    reviewOpen.value = false
    await load()
  } catch (e: any) {
    toast.error(l('تعذّر التحديث', 'Update failed'), e?.response?.data?.message ?? '')
  } finally {
    reviewSaving.value = false
  }
}

onMounted(() => load())
</script>
