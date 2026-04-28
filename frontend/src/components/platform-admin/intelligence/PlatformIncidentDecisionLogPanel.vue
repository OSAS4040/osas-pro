<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import type { PlatformDecisionType } from '@/types/platform-admin/platformIntelligenceEnums'
import { PLATFORM_DECISION_TYPE_LABEL_AR } from '@/types/platform-admin/platformIntelligenceEnums'
import type { PlatformDecisionLogEntry } from '@/types/platform-admin/platformIntelligenceContracts'
import { usePlatformIncidentDecisions, type CreateDecisionPayload } from '@/composables/platform-admin/intelligence/usePlatformIncidentDecisions'

const props = defineProps<{ incidentKey: string }>()

const {
  canView,
  canAdd,
  entries,
  loading,
  error,
  submitting,
  fetchDecisions,
  createEntry,
} = usePlatformIncidentDecisions(() => props.incidentKey)

const form = reactive({
  decision_type: 'observation' as PlatformDecisionType,
  decision_summary: '',
  rationale: '',
  expected_outcome: '',
  evidence_refs_text: '',
  linked_signals_text: '',
  linked_notes_text: '',
  follow_up_required: false,
})

const typeOptions = computed(() =>
  (Object.keys(PLATFORM_DECISION_TYPE_LABEL_AR) as PlatformDecisionType[]).map((v) => ({
    value: v,
    label: PLATFORM_DECISION_TYPE_LABEL_AR[v],
  })),
)

function splitLines(s: string): string[] {
  return s
    .split(/\r?\n/)
    .map((x) => x.trim())
    .filter(Boolean)
}

function labelFor(row: PlatformDecisionLogEntry): string {
  return PLATFORM_DECISION_TYPE_LABEL_AR[row.decision_type] ?? row.decision_type
}

const formError = ref<string | null>(null)

async function submit(): Promise<void> {
  formError.value = null
  const payload: CreateDecisionPayload = {
    decision_type: form.decision_type,
    decision_summary: form.decision_summary.trim(),
    rationale: form.rationale.trim(),
    follow_up_required: form.follow_up_required,
  }
  const eo = form.expected_outcome.trim()
  if (eo) {
    payload.expected_outcome = eo
  }
  const evList = splitLines(form.evidence_refs_text)
  const lsList = splitLines(form.linked_signals_text)
  const lnList = splitLines(form.linked_notes_text)
  if (evList.length) {
    payload.evidence_refs = evList
  }
  if (lsList.length) {
    payload.linked_signals = lsList
  }
  if (lnList.length) {
    payload.linked_notes = lnList
  }
  try {
    await createEntry(payload)
  } catch {
    formError.value = 'تعذر حفظ القرار. تحقق من الحقول أو الصلاحيات.'
    return
  }
  form.decision_summary = ''
  form.rationale = ''
  form.expected_outcome = ''
  form.evidence_refs_text = ''
  form.linked_signals_text = ''
  form.linked_notes_text = ''
  form.follow_up_required = false
}

onMounted(() => {
  void fetchDecisions()
})

watch(
  () => props.incidentKey,
  () => {
    void fetchDecisions()
  },
)
</script>

<template>
  <section v-if="canView" class="mt-8 rounded-xl border border-slate-200/80 p-4 dark:border-slate-700">
    <h2 class="text-xs font-semibold text-slate-900 dark:text-white">سجل القرار المؤسسي</h2>
    <p class="mt-1 text-[10px] leading-relaxed text-slate-500 dark:text-slate-400">
      قرارات موثّقة منفصلة عن انتقالات دورة حياة الحادث. لا تنفّذ إجراءات على البيانات.
    </p>

    <p v-if="error" class="mt-2 rounded border border-amber-200 bg-amber-50 px-2 py-1 text-[11px] text-amber-950">{{ error }}</p>
    <div v-else-if="loading" class="mt-3 h-16 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800/80" />
    <ul v-else-if="entries.length" class="mt-3 space-y-2">
      <li
        v-for="row in entries"
        :key="row.decision_id"
        class="rounded-lg border border-slate-100 bg-slate-50/40 px-3 py-2 text-[11px] dark:border-slate-800 dark:bg-slate-900/30"
      >
        <div class="flex flex-wrap items-baseline justify-between gap-2">
          <span class="font-medium text-slate-800 dark:text-slate-100">{{ labelFor(row) }}</span>
          <span class="font-mono text-[10px] text-slate-500" dir="ltr">{{ row.created_at }}</span>
        </div>
        <p class="mt-1 text-slate-800 dark:text-slate-100">{{ row.decision_summary }}</p>
        <p class="mt-1 text-slate-600 dark:text-slate-300">{{ row.rationale }}</p>
        <p class="mt-1 text-[10px] text-slate-500">الجهة: {{ row.actor }}</p>
        <p v-if="row.expected_outcome" class="mt-1 text-[10px] text-slate-600">المتوقع: {{ row.expected_outcome }}</p>
        <p v-if="row.follow_up_required" class="mt-1 text-[10px] text-amber-800 dark:text-amber-200">متابعة لاحقة مطلوبة</p>
        <p v-if="row.evidence_refs.length" class="mt-1 font-mono text-[10px] text-slate-500" dir="ltr">
          مراجع: {{ row.evidence_refs.join(' · ') }}
        </p>
      </li>
    </ul>
    <p v-else class="mt-3 text-[11px] text-slate-500">لا توجد قرارات مسجّلة لهذا الحادث.</p>

    <div v-if="canAdd" class="mt-4 border-t border-slate-200/80 pt-4 dark:border-slate-700">
      <h3 class="text-[11px] font-semibold text-slate-800 dark:text-slate-100">تسجيل قرار</h3>
      <p v-if="formError" class="mt-2 rounded border border-red-200 bg-red-50 px-2 py-1 text-[11px] text-red-900">{{ formError }}</p>
      <form class="mt-2 space-y-2" @submit.prevent="submit()">
        <label class="block text-[10px] text-slate-500">نوع القرار</label>
        <select v-model="form.decision_type" class="w-full rounded border border-slate-300 bg-white px-2 py-1 text-[11px] dark:border-slate-600 dark:bg-slate-950">
          <option v-for="o in typeOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
        </select>
        <label class="block text-[10px] text-slate-500">ملخص القرار (إلزامي)</label>
        <textarea v-model="form.decision_summary" required rows="2" class="w-full rounded border border-slate-300 p-2 text-[11px] dark:border-slate-600 dark:bg-slate-950" />
        <label class="block text-[10px] text-slate-500">المبرر (إلزامي)</label>
        <textarea v-model="form.rationale" required rows="3" class="w-full rounded border border-slate-300 p-2 text-[11px] dark:border-slate-600 dark:bg-slate-950" />
        <label class="block text-[10px] text-slate-500">النتيجة المتوقعة (اختياري)</label>
        <input v-model="form.expected_outcome" type="text" class="w-full rounded border border-slate-300 px-2 py-1 text-[11px] dark:border-slate-600 dark:bg-slate-950" />
        <label class="block text-[10px] text-slate-500">مراجع أدلة (سطر لكل مرجع، اختياري)</label>
        <textarea v-model="form.evidence_refs_text" rows="2" class="w-full rounded border border-slate-300 p-2 font-mono text-[10px] dark:border-slate-600 dark:bg-slate-950" dir="ltr" />
        <label class="block text-[10px] text-slate-500">إشارات مرتبطة (سطر لكل مفتاح، اختياري)</label>
        <textarea v-model="form.linked_signals_text" rows="2" class="w-full rounded border border-slate-300 p-2 font-mono text-[10px] dark:border-slate-600 dark:bg-slate-950" dir="ltr" />
        <label class="block text-[10px] text-slate-500">ملاحظات مرتبطة (سطر لكل مرجع، اختياري)</label>
        <textarea v-model="form.linked_notes_text" rows="2" class="w-full rounded border border-slate-300 p-2 font-mono text-[10px] dark:border-slate-600 dark:bg-slate-950" dir="ltr" />
        <label class="flex items-center gap-2 text-[11px] text-slate-700 dark:text-slate-200">
          <input v-model="form.follow_up_required" type="checkbox" class="rounded border-slate-400">
          متابعة لاحقة مطلوبة
        </label>
        <button
          type="submit"
          :disabled="submitting || form.decision_summary.trim().length < 3 || form.rationale.trim().length < 3"
          class="rounded-lg bg-slate-800 px-3 py-1.5 text-[11px] text-white disabled:opacity-50"
        >
          {{ submitting ? 'جاري الحفظ…' : 'حفظ القرار' }}
        </button>
      </form>
    </div>
  </section>
</template>
