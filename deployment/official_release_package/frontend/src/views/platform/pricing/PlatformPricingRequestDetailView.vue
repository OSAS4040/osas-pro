<template>
  <div v-if="!canView" class="mx-auto max-w-2xl p-6 text-center text-slate-500" dir="rtl">لا تملك صلاحية عرض التسعير.</div>
  <div v-else class="mx-auto max-w-4xl space-y-6 pb-12" dir="rtl">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">طلب تسعير</h1>
        <p class="mt-1 font-mono text-xs text-slate-500" dir="ltr">{{ uuid }}</p>
      </div>
      <RouterLink :to="backTo" class="text-sm font-semibold text-primary-700 hover:underline dark:text-primary-400">← رجوع للقائمة</RouterLink>
    </div>

    <div v-if="loading" class="text-slate-400">جارٍ التحميل…</div>
    <div v-else-if="detail" class="space-y-4">
      <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900/40">
        <dl class="grid gap-2 text-sm md:grid-cols-2">
          <div><span class="text-slate-500">الحالة:</span> {{ String(detail.status) }}</div>
          <div dir="ltr"><span class="text-slate-500">company / customer:</span> {{ detail.company_id }} / {{ detail.customer_id }}</div>
          <div class="md:col-span-2"><span class="text-slate-500">عنوان:</span> {{ detail.title || '—' }}</div>
        </dl>
      </div>

      <div v-if="lines.length" class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900/40">
        <h2 class="mb-2 text-sm font-bold">البنود</h2>
        <pre class="overflow-x-auto rounded-lg bg-slate-50 p-3 text-xs dark:bg-slate-950">{{ JSON.stringify(lines, null, 2) }}</pre>
      </div>

      <div class="rounded-xl border border-amber-200 bg-amber-50/80 p-4 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
        نفِّذ الانتقالات بحذر؛ الخادم يفرض سير العمل والصلاحيات (RBAC).
      </div>

      <div class="flex flex-wrap gap-2">
        <button
          v-if="auth.hasPermission('platform.pricing.create')"
          type="button"
          class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600"
          :disabled="acting"
          @click="doSubmit"
        >
          إرسال للمراجعة
        </button>
        <button
          v-if="auth.hasPermission('platform.pricing.review')"
          type="button"
          class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600"
          :disabled="acting"
          @click="doBeginReview"
        >
          بدء المراجعة
        </button>
        <button
          v-if="auth.hasPermission('platform.pricing.review')"
          type="button"
          class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600"
          :disabled="acting"
          @click="doCompleteReview"
        >
          إكمال المراجعة (توصية)
        </button>
        <button
          v-if="auth.hasPermission('platform.pricing.review')"
          type="button"
          class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600"
          :disabled="acting"
          @click="doEscalate"
        >
          تصعيد للاعتماد
        </button>
        <button
          v-if="auth.hasPermission('platform.pricing.approve')"
          type="button"
          class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
          :disabled="acting"
          @click="doApprove"
        >
          اعتماد (JSON sell_snapshot)
        </button>
        <button
          v-if="auth.hasPermission('platform.pricing.approve')"
          type="button"
          class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-50"
          :disabled="acting"
          @click="doReject"
        >
          رفض
        </button>
        <button
          v-if="auth.hasPermission('platform.pricing.approve')"
          type="button"
          class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600"
          :disabled="acting"
          @click="doReturnEdit"
        >
          إعادة للتعديل
        </button>
      </div>

      <div v-if="auth.hasPermission('platform.pricing.approve')" class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900/40">
        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">sell_snapshot (JSON) للاعتماد</label>
        <textarea
          v-model="sellSnapshotJson"
          rows="6"
          class="mt-2 w-full rounded-lg border border-slate-300 bg-white p-2 font-mono text-xs dark:border-slate-600 dark:bg-slate-800 dark:text-white"
          dir="ltr"
          placeholder='[{"service_code":"oil_change","unit_price":120,"currency":"SAR"}]'
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import {
  fetchPricingRequestDetail,
  postPricingAction,
  pricingApiErrorMessage,
} from '@/composables/platform-admin/usePlatformPricingControlPlane'
import { useToast } from '@/composables/useToast'

const auth = useAuthStore()
const route = useRoute()
const toast = useToast()

const uuid = computed(() => String(route.params.uuid ?? ''))
const canView = computed(() => auth.hasPermission('platform.pricing.view'))

const detail = ref<Record<string, unknown> | null>(null)
const lines = computed(() => (Array.isArray(detail.value?.lines) ? detail.value?.lines : []) as unknown[])
const loading = ref(true)
const acting = ref(false)
const sellSnapshotJson = ref(
  '[{"service_code":"oil_change","unit_price":120,"currency":"SAR"}]',
)

const backTo = computed(() =>
  route.query.from === 'review'
    ? { name: 'platform-pricing-review' }
    : route.query.from === 'approve'
      ? { name: 'platform-pricing-approve' }
      : { name: 'platform-pricing-requests' },
)

async function refresh(): Promise<void> {
  if (!canView.value || !uuid.value) return
  loading.value = true
  try {
    detail.value = await fetchPricingRequestDetail(uuid.value)
  } catch (e) {
    toast.error('تحميل', pricingApiErrorMessage(e))
    detail.value = null
  } finally {
    loading.value = false
  }
}

async function doSubmit(): Promise<void> {
  acting.value = true
  try {
    await postPricingAction(`/platform/pricing/requests/${uuid.value}/submit-for-review`)
    toast.success('تم', 'أُرسل للمراجعة')
    await refresh()
  } catch (e) {
    toast.error('فشل', pricingApiErrorMessage(e))
  } finally {
    acting.value = false
  }
}

async function doBeginReview(): Promise<void> {
  acting.value = true
  try {
    await postPricingAction(`/platform/pricing/requests/${uuid.value}/begin-review`)
    toast.success('تم', 'بدء المراجعة')
    await refresh()
  } catch (e) {
    toast.error('فشل', pricingApiErrorMessage(e))
  } finally {
    acting.value = false
  }
}

async function doCompleteReview(): Promise<void> {
  acting.value = true
  try {
    await postPricingAction(`/platform/pricing/requests/${uuid.value}/complete-review`, {
      recommendation: { summary: 'مراجعة من الواجهة', recommended_sell_total: null },
    })
    toast.success('تم', 'اكتملت المراجعة')
    await refresh()
  } catch (e) {
    toast.error('فشل', pricingApiErrorMessage(e))
  } finally {
    acting.value = false
  }
}

async function doEscalate(): Promise<void> {
  acting.value = true
  try {
    await postPricingAction(`/platform/pricing/requests/${uuid.value}/escalate`)
    toast.success('تم', 'تصعيد للاعتماد')
    await refresh()
  } catch (e) {
    toast.error('فشل', pricingApiErrorMessage(e))
  } finally {
    acting.value = false
  }
}

async function doApprove(): Promise<void> {
  let snap: unknown
  try {
    snap = JSON.parse(sellSnapshotJson.value)
  } catch {
    toast.warning('JSON', 'sell_snapshot غير صالح')
    return
  }
  if (!Array.isArray(snap) || snap.length === 0) {
    toast.warning('JSON', 'sell_snapshot يجب أن يكون مصفوفة')
    return
  }
  acting.value = true
  try {
    await postPricingAction(`/platform/pricing/requests/${uuid.value}/approve`, {
      sell_snapshot: snap,
      contract_id: null,
    })
    toast.success('تم', 'اعتماد الطلب')
    await refresh()
  } catch (e) {
    toast.error('فشل الاعتماد', pricingApiErrorMessage(e))
  } finally {
    acting.value = false
  }
}

async function doReject(): Promise<void> {
  const reason = window.prompt('سبب الرفض؟')
  if (reason === null || reason.trim() === '') return
  acting.value = true
  try {
    await postPricingAction(`/platform/pricing/requests/${uuid.value}/reject`, { reason })
    toast.success('تم', 'طلب مرفوض')
    await refresh()
  } catch (e) {
    toast.error('فشل', pricingApiErrorMessage(e))
  } finally {
    acting.value = false
  }
}

async function doReturnEdit(): Promise<void> {
  const note = window.prompt('ملاحظة إعادة التعديل؟')
  if (note === null || note.trim() === '') return
  acting.value = true
  try {
    await postPricingAction(`/platform/pricing/requests/${uuid.value}/return-for-edit`, { note })
    toast.success('تم', 'أُعيد للتعديل')
    await refresh()
  } catch (e) {
    toast.error('فشل', pricingApiErrorMessage(e))
  } finally {
    acting.value = false
  }
}

onMounted(() => {
  void refresh()
})
</script>
