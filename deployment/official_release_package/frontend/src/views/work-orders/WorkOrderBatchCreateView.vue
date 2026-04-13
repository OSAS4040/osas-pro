<template>
  <div class="app-shell-page max-w-4xl" dir="rtl">
    <div class="flex items-center justify-between gap-4 mb-6">
      <div>
        <RouterLink to="/work-orders" class="text-sm text-primary-600 hover:underline">← أوامر العمل</RouterLink>
        <h2 class="page-title-xl mt-1">دفعة أوامر عمل (عدة مركبات)</h2>
        <p class="page-subtitle text-sm text-gray-500 mt-1">كل سطر يُنفَّذ كأمر مستقل؛ قد تنجح بعض الأسطر وتفشل أخرى.</p>
      </div>
    </div>

    <div class="space-y-4">
      <div
        v-for="(line, idx) in lines"
        :key="idx"
        class="rounded-xl border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-900 p-4 grid gap-3 md:grid-cols-2"
      >
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">معرّف العميل</label>
          <input v-model.number="line.customer_id" type="number" min="1" class="w-full border rounded-lg px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">معرّف المركبة</label>
          <input v-model.number="line.vehicle_id" type="number" min="1" class="w-full border rounded-lg px-3 py-2 text-sm" />
        </div>
        <div class="md:col-span-2 flex justify-end">
          <button v-if="lines.length > 1" type="button" class="text-xs text-red-600 hover:underline" @click="removeLine(idx)">حذف السطر</button>
        </div>
      </div>
      <button type="button" class="text-sm text-primary-600 hover:underline" @click="addLine">+ إضافة مركبة</button>
    </div>

    <div class="mt-6 flex flex-wrap gap-3">
      <button
        type="button"
        class="btn btn-primary"
        :disabled="busy"
        @click="openPreview"
      >
        {{ busy ? 'جارٍ المعاينة...' : 'مراجعة ثم التنفيذ' }}
      </button>
    </div>
    <p v-if="pageError" class="mt-3 text-sm text-red-600">{{ pageError }}</p>

    <SensitiveOperationReviewModal
      v-model="reviewOpen"
      :summary="reviewSummary"
      :loading="reviewLoading"
      :error="reviewError"
      confirm-text="تنفيذ الدفعة"
      title="مراجعة دفعة أوامر العمل"
      @confirm="executeBatch"
    />
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import apiClient from '@/lib/apiClient'
import { summarizeAxiosError } from '@/utils/apiErrorSummary'
import SensitiveOperationReviewModal from '@/components/SensitiveOperationReviewModal.vue'

type Line = { customer_id: number | null; vehicle_id: number | null }

const lines = ref<Line[]>([{ customer_id: null, vehicle_id: null }])
const busy = ref(false)
const pageError = ref('')
const reviewOpen = ref(false)
const reviewSummary = ref<Record<string, any> | null>(null)
const reviewToken = ref('')
const reviewLoading = ref(false)
const reviewError = ref('')

function addLine() {
  lines.value.push({ customer_id: null, vehicle_id: null })
}

function removeLine(i: number) {
  lines.value.splice(i, 1)
}

function buildPayload() {
  return lines.value
    .filter((l) => l.customer_id && l.vehicle_id)
    .map((l) => ({
      customer_id: Number(l.customer_id),
      vehicle_id: Number(l.vehicle_id),
      items: [] as unknown[],
    }))
}

async function openPreview() {
  pageError.value = ''
  const payload = buildPayload()
  if (!payload.length) {
    pageError.value = 'أدخل عميلاً ومركبة لكل سطر على الأقل.'
    return
  }
  reviewLoading.value = true
  reviewError.value = ''
  reviewSummary.value = null
  reviewToken.value = ''
  reviewOpen.value = true
  busy.value = true
  try {
    const { data } = await apiClient.post('/sensitive-operations/preview', {
      operation: 'work_order_batch_create',
      lines: payload,
    })
    reviewSummary.value = data.data
    reviewToken.value = data.data.sensitive_preview_token
  } catch (e: unknown) {
    reviewError.value = summarizeAxiosError(e)
  } finally {
    reviewLoading.value = false
    busy.value = false
  }
}

async function executeBatch() {
  const payload = buildPayload()
  if (!reviewToken.value) return
  busy.value = true
  reviewError.value = ''
  try {
    const { data } = await apiClient.post('/work-orders/batches', {
      sensitive_preview_token: reviewToken.value,
      lines: payload,
    })
    reviewOpen.value = false
    const batch = data.data
    const failed = batch?.items?.filter((i: any) => i.status === 'failed') ?? []
    pageError.value = failed.length
      ? `اكتملت الدفعة مع فشل ${failed.length} سطر(ات). راجع التفاصيل في الاستجابة.`
      : 'تم إنشاء الدفعة بنجاح.'
  } catch (e: unknown) {
    reviewError.value = summarizeAxiosError(e)
  } finally {
    busy.value = false
  }
}
</script>
