<template>
  <Teleport to="body">
    <div
      v-if="modelValue"
      class="fixed inset-0 z-[60] flex items-center justify-center p-4"
      dir="rtl"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="titleId"
      :aria-describedby="ariaDescribedBy"
      :aria-busy="loading"
    >
      <div
        class="absolute inset-0 bg-black/45 backdrop-blur-[1px]"
        aria-hidden="true"
        @click="close"
      />
      <div
        ref="panelRef"
        class="relative z-10 w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-2xl border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-900 shadow-2xl outline-none"
        tabindex="-1"
        @keydown.tab="trapTab"
      >
        <div class="border-b border-gray-100 dark:border-slate-700 px-5 py-4">
          <h3 :id="titleId" class="text-lg font-semibold text-gray-900 dark:text-slate-100">{{ title }}</h3>
          <p :id="descId" class="text-xs text-gray-500 dark:text-slate-400 mt-1">لا يمكن تنفيذ العملية الحساسة دون هذه المراجعة.</p>
        </div>

        <div class="px-5 py-4 space-y-4 text-sm">
          <div v-if="loading" class="py-8 text-center text-gray-500 dark:text-slate-400">جارٍ تحميل ملخص المراجعة...</div>

          <template v-else-if="summary">
            <div role="region" aria-live="polite" class="space-y-4">
            <dl class="grid grid-cols-1 gap-2">
              <div class="flex justify-between gap-2">
                <dt class="text-gray-500 dark:text-slate-400">نوع العملية</dt>
                <dd class="font-medium text-gray-900 dark:text-slate-100">{{ operationLabel }}</dd>
              </div>
              <div class="flex justify-between gap-2">
                <dt class="text-gray-500 dark:text-slate-400">الشركة</dt>
                <dd class="font-medium text-gray-900 dark:text-slate-100">{{ summary.company?.name ?? '—' }}</dd>
              </div>
              <div class="flex justify-between gap-2">
                <dt class="text-gray-500 dark:text-slate-400">المستخدم</dt>
                <dd class="text-gray-900 dark:text-slate-100">{{ summary.user?.name ?? '—' }}</dd>
              </div>
              <div v-if="summary.branch_id != null" class="flex justify-between gap-2">
                <dt class="text-gray-500 dark:text-slate-400">الفرع</dt>
                <dd class="text-gray-900 dark:text-slate-100" :title="branchDisplay">{{ branchDisplay }}</dd>
              </div>
            </dl>

            <div class="rounded-xl bg-slate-50 dark:bg-slate-800/60 p-3 space-y-2">
              <h4 class="text-xs font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wide">النموذج المالي</h4>
              <div class="flex justify-between">
                <span class="text-gray-500 dark:text-slate-400">الحالة</span>
                <span class="text-xs font-medium text-gray-900 dark:text-slate-100" :title="summary.billing?.financial_model_status ?? ''">
                  {{ companyFinancialModelStatusLabel(summary.billing?.financial_model_status) }}
                </span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500 dark:text-slate-400">النوع</span>
                <span class="text-xs font-medium text-gray-900 dark:text-slate-100" :title="summary.billing?.financial_model ?? ''">
                  {{ companyFinancialModelLabel(summary.billing?.financial_model) }}
                </span>
              </div>
            </div>

            <div class="rounded-xl border border-gray-100 dark:border-slate-700 p-3 space-y-2">
              <h4 class="text-xs font-semibold text-gray-600 dark:text-slate-300 uppercase tracking-wide">الملخص المالي</h4>
              <div v-if="summary.credit_net_receivable_exposure_before != null" class="flex justify-between text-xs">
                <span class="text-gray-500 dark:text-slate-400">ذمم مفتوحة (قبل)</span>
                <span class="font-mono text-gray-900 dark:text-slate-100">{{ formatMoney(summary.credit_net_receivable_exposure_before) }}</span>
              </div>
              <div v-if="summary.credit_net_receivable_exposure_after_estimate != null" class="flex justify-between text-xs">
                <span class="text-gray-500 dark:text-slate-400">ذمم (بعد — تقديري)</span>
                <span class="font-mono text-gray-900 dark:text-slate-100">{{ formatMoney(summary.credit_net_receivable_exposure_after_estimate) }}</span>
              </div>
              <div v-if="summary.credit_limit != null && summary.credit_limit !== ''" class="flex justify-between text-xs">
                <span class="text-gray-500 dark:text-slate-400">حد ائتمان الشركة</span>
                <span class="font-mono font-medium text-gray-900 dark:text-slate-100">{{ formatMoney(summary.credit_limit) }}</span>
              </div>
              <div class="flex justify-between text-xs">
                <span class="text-gray-500 dark:text-slate-400">إجمالي تقديري للأمر / الدفعة</span>
                <span class="font-mono font-semibold text-gray-900 dark:text-slate-100">{{ formatMoney(summary.work_orders_estimated_total) }}</span>
              </div>
              <div class="flex justify-between text-xs">
                <span class="text-gray-500 dark:text-slate-400">عدد المركبات المتأثرة (تقدير)</span>
                <span class="text-gray-900 dark:text-slate-100">{{ summary.affected_vehicles_estimate ?? 0 }}</span>
              </div>
            </div>

            <ul v-if="summary.warnings?.length" class="rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/50 px-3 py-2 text-xs text-amber-900 dark:text-amber-100 space-y-1">
              <li v-for="(w, i) in summary.warnings" :key="i">• {{ w }}</li>
            </ul>

            <slot name="footer-extra" />
            </div>
          </template>

          <div
            v-else-if="!error"
            :id="emptyStateId"
            class="rounded-xl border border-amber-200/80 bg-amber-50/90 px-3 py-4 text-center text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/25 dark:text-amber-100"
            role="status"
          >
            لم يُرجِع الخادم ملخص المراجعة. يمكنك الإلغاء والمحاولة مرة أخرى.
          </div>

          <p v-if="error" :id="errorTextId" class="text-red-600 dark:text-red-400 text-sm" role="alert">{{ error }}</p>
        </div>

        <div class="flex justify-end gap-2 border-t border-gray-100 dark:border-slate-700 px-5 py-3">
          <button
            ref="cancelBtnRef"
            type="button"
            class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-800"
            :disabled="loading"
            @click="close"
          >
            إلغاء
          </button>
          <button
            ref="confirmBtnRef"
            type="button"
            class="px-4 py-2 text-sm rounded-lg font-medium bg-primary-600 text-white hover:bg-primary-700 disabled:opacity-50"
            :disabled="loading || !summary || confirmDisabled"
            @click="$emit('confirm')"
          >
            {{ confirmText }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, ref, watch, nextTick, useId, onUnmounted } from 'vue'
import { onKeyStroke } from '@vueuse/core'
import { lockBodyScroll, unlockBodyScroll } from '@/composables/useBodyScrollLock'
import { companyFinancialModelLabel, companyFinancialModelStatusLabel } from '@/utils/companyFinancialLabels'

const props = withDefaults(
  defineProps<{
    modelValue: boolean
    title?: string
    summary: Record<string, any> | null
    loading?: boolean
    error?: string
    confirmText?: string
    confirmDisabled?: boolean
  }>(),
  {
    title: 'مراجعة نهائية',
    loading: false,
    error: '',
    confirmText: 'تأكيد التنفيذ',
    confirmDisabled: false,
  },
)

const emit = defineEmits<{
  'update:modelValue': [v: boolean]
  confirm: []
}>()

const uid = useId()
const titleId = `sensitive-review-title-${uid}`
const descId = `sensitive-review-desc-${uid}`
const emptyStateId = `sensitive-review-empty-${uid}`
const errorTextId = `sensitive-review-error-${uid}`

const ariaDescribedBy = computed(() => {
  if (props.loading || props.summary) return descId
  if (props.error) return `${descId} ${errorTextId}`
  return `${descId} ${emptyStateId}`
})
const panelRef = ref<HTMLElement | null>(null)
const cancelBtnRef = ref<HTMLButtonElement | null>(null)
const confirmBtnRef = ref<HTMLButtonElement | null>(null)

function close() {
  emit('update:modelValue', false)
}

onKeyStroke('Escape', (e) => {
  if (!props.modelValue) return
  e.preventDefault()
  close()
})

watch(
  () => props.modelValue,
  async (open, wasOpen) => {
    if (open && !wasOpen) lockBodyScroll()
    if (!open && wasOpen) unlockBodyScroll()
    if (open) {
      await nextTick()
      cancelBtnRef.value?.focus()
    }
  },
)

onUnmounted(() => {
  if (props.modelValue) unlockBodyScroll()
})

function trapTab(e: KeyboardEvent) {
  if (!props.modelValue || e.key !== 'Tab') return
  const root = panelRef.value
  if (!root) return
  const focusables = root.querySelectorAll<HTMLElement>(
    'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
  )
  const list = [...focusables].filter((el) => el.offsetParent !== null || el === document.activeElement)
  if (list.length === 0) return
  const first = list[0]
  const last = list[list.length - 1]
  const active = document.activeElement as HTMLElement | null
  if (e.shiftKey) {
    if (active === first || !root.contains(active)) {
      e.preventDefault()
      last.focus()
    }
  } else if (active === last) {
    e.preventDefault()
    first.focus()
  }
}

const branchDisplay = computed(() => {
  const s = props.summary
  if (s?.branch_id == null) return '—'
  const name = s.branch_name ?? s.branch?.name ?? s.branch?.name_ar
  if (name) return `${name} (#${s.branch_id})`
  return `فرع #${s.branch_id}`
})

const operationLabel = computed(() => {
  const op = props.summary?.operation
  const map: Record<string, string> = {
    work_order_status_to_approved: 'اعتماد أمر عمل',
    work_order_cancellation_request: 'طلب إلغاء أمر عمل',
    work_order_batch_create: 'إنشاء دفعة أوامر عمل',
  }
  if (op && map[op]) return map[op]
  if (typeof op === 'string' && op.trim() !== '') return `عملية حساسة (${op})`
  return '—'
})

function formatMoney(v: string | number | null | undefined): string {
  if (v === null || v === undefined) return '—'
  const n = Number(v)
  if (Number.isNaN(n)) return String(v)
  return `${n.toFixed(2)} ر.س`
}
</script>
