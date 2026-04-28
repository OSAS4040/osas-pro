<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { usePlatformGuidedWorkflows } from '@/composables/platform-admin/intelligence/usePlatformGuidedWorkflows'

const props = defineProps<{ incidentKey: string }>()
const emit = defineEmits<{ (e: 'completed'): void }>()

const { canViewCatalog, canExecute, catalog, loading, error, executingKey, fetchCatalog, executeWorkflow } =
  usePlatformGuidedWorkflows(() => props.incidentKey)

const selectedKey = ref<string>('')
const confirmRun = ref(false)
const fields = reactive({
  owner_ref: '',
  decision_summary: '',
  rationale: '',
  expected_outcome: '',
  close_reason: '',
  escalate_reason: '',
  follow_up_required: false,
})

const selected = computed(() => catalog.value.find((r) => r.workflow_key === selectedKey.value) ?? null)

const availableRows = computed(() => catalog.value.filter((r) => r.available))

async function run(): Promise<void> {
  if (!selected.value || !confirmRun.value) {
    return
  }
  const key = selected.value.workflow_key
  const payload: Record<string, unknown> = { workflow_key: key }
  if (selected.value.requires_owner_ref) {
    payload.owner_ref = fields.owner_ref.trim()
  }
  if (selected.value.requires_decision_summary) {
    payload.decision_summary = fields.decision_summary.trim()
  }
  if (selected.value.requires_rationale) {
    payload.rationale = fields.rationale.trim()
  }
  if (selected.value.requires_expected_outcome) {
    payload.expected_outcome = fields.expected_outcome.trim()
  }
  if (key === 'close_final') {
    payload.close_reason = fields.close_reason.trim()
  }
  if (key === 'escalate_decision' && fields.escalate_reason.trim()) {
    payload.escalate_reason = fields.escalate_reason.trim()
  }
  if (key === 'monitor_with_decision') {
    payload.follow_up_required = fields.follow_up_required
  }
  try {
    await executeWorkflow(payload as Record<string, unknown> & { workflow_key: string })
    emit('completed')
    await fetchCatalog()
    confirmRun.value = false
  } catch {
    /* error ref set in composable */
  }
}

onMounted(() => {
  void fetchCatalog()
})

watch(
  () => props.incidentKey,
  () => {
    void fetchCatalog()
  },
)

watch(selectedKey, () => {
  confirmRun.value = false
})
</script>

<template>
  <section v-if="canViewCatalog" class="mt-8 rounded-xl border border-slate-200/80 p-4 dark:border-slate-700">
    <h2 class="text-xs font-semibold text-slate-900 dark:text-white">مسارات تشغيل موجّهة</h2>
    <p class="mt-1 text-[10px] leading-relaxed text-slate-500 dark:text-slate-400">
      تنسيق آمن لخطوات متكررة باستخدام نفس صلاحيات وأفعال مركز الحوادث وسجل القرار — دون تنفيذ تلقائي على بيانات حساسة.
    </p>

    <p v-if="error" class="mt-2 rounded border border-amber-200 bg-amber-50 px-2 py-1 text-[11px] text-amber-950">{{ error }}</p>
    <div v-else-if="loading" class="mt-3 h-14 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800/80" />
    <template v-else>
      <ul class="mt-3 space-y-1 text-[10px] text-slate-600 dark:text-slate-300">
        <li v-for="w in catalog" :key="w.workflow_key" class="flex flex-wrap items-center justify-between gap-2 rounded border border-transparent px-1 py-0.5">
          <span :class="w.available ? '' : 'text-slate-400 line-through'">{{ w.label }}</span>
          <span v-if="!w.available && w.unavailable_reason" class="font-mono text-[9px] text-slate-400" dir="ltr">{{ w.unavailable_reason }}</span>
        </li>
      </ul>

      <div v-if="canExecute && availableRows.length" class="mt-4 border-t border-slate-200/80 pt-4 dark:border-slate-700">
        <label class="block text-[10px] text-slate-500">اختر مسارًا متاحًا</label>
        <select v-model="selectedKey" class="mt-1 w-full rounded border border-slate-300 bg-white px-2 py-1 text-[11px] dark:border-slate-600 dark:bg-slate-950">
          <option value="" disabled>—</option>
          <option v-for="w in availableRows" :key="w.workflow_key" :value="w.workflow_key">{{ w.label }}</option>
        </select>

        <div v-if="selected" class="mt-3 space-y-2 rounded-lg bg-slate-50/50 p-3 text-[11px] dark:bg-slate-900/30">
          <p class="text-slate-700 dark:text-slate-200">{{ selected.description }}</p>
          <p class="font-mono text-[10px] text-slate-500" dir="ltr">{{ selected.preview }}</p>

          <div v-if="selected.requires_owner_ref" class="space-y-1">
            <label class="text-[10px] text-slate-500">مرجع المالك (مثل user:123)</label>
            <input v-model="fields.owner_ref" type="text" class="w-full rounded border border-slate-300 px-2 py-1 font-mono text-[11px] dark:border-slate-600 dark:bg-slate-950" dir="ltr">
          </div>
          <div v-if="selected.requires_decision_summary" class="space-y-1">
            <label class="text-[10px] text-slate-500">ملخص القرار</label>
            <textarea v-model="fields.decision_summary" rows="2" class="w-full rounded border border-slate-300 p-2 text-[11px] dark:border-slate-600 dark:bg-slate-950" />
          </div>
          <div v-if="selected.requires_rationale" class="space-y-1">
            <label class="text-[10px] text-slate-500">المبرر</label>
            <textarea v-model="fields.rationale" rows="2" class="w-full rounded border border-slate-300 p-2 text-[11px] dark:border-slate-600 dark:bg-slate-950" />
          </div>
          <div v-if="selected.requires_expected_outcome" class="space-y-1">
            <label class="text-[10px] text-slate-500">النتيجة المتوقعة</label>
            <input v-model="fields.expected_outcome" type="text" class="w-full rounded border border-slate-300 px-2 py-1 text-[11px] dark:border-slate-600 dark:bg-slate-950" />
          </div>
          <div v-if="selected.workflow_key === 'close_final'" class="space-y-1">
            <label class="text-[10px] text-slate-500">سبب الإغلاق التشغيلي</label>
            <textarea v-model="fields.close_reason" rows="2" class="w-full rounded border border-slate-300 p-2 text-[11px] dark:border-slate-600 dark:bg-slate-950" />
          </div>
          <div v-if="selected.workflow_key === 'escalate_decision'" class="space-y-1">
            <label class="text-[10px] text-slate-500">سبب التصعيد (اختياري)</label>
            <input v-model="fields.escalate_reason" type="text" class="w-full rounded border border-slate-300 px-2 py-1 text-[11px] dark:border-slate-600 dark:bg-slate-950" />
          </div>
          <label v-if="selected.workflow_key === 'monitor_with_decision'" class="flex items-center gap-2 text-[11px] text-slate-700 dark:text-slate-200">
            <input v-model="fields.follow_up_required" type="checkbox" class="rounded border-slate-400">
            متابعة لاحقة
          </label>

          <label class="flex items-center gap-2 text-[11px] text-slate-800 dark:text-slate-100">
            <input v-model="confirmRun" type="checkbox" class="rounded border-slate-400">
            تأكيد تنفيذ المسار أعلاه
          </label>

          <button
            type="button"
            :disabled="!confirmRun || !selectedKey || executingKey !== null"
            class="rounded-lg bg-slate-800 px-3 py-1.5 text-[11px] text-white disabled:opacity-40"
            @click="run()"
          >
            {{ executingKey ? 'جاري التنفيذ…' : 'تنفيذ المسار' }}
          </button>
        </div>
      </div>
    </template>
  </section>
</template>
