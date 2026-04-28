<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { usePlatformControlledActions } from '@/composables/platform-admin/intelligence/usePlatformControlledActions'
import type { PlatformControlledActionRow } from '@/composables/platform-admin/intelligence/usePlatformControlledActions'
import { datetimeLocalToIso } from '@/utils/datetimeLocalToIso'
import { isAxiosError } from 'axios'

const props = defineProps<{ incidentKey: string }>()

const summaryNew = ref('')
const humanSummary = ref('')
const taskRef = ref('')
const idem = ref('')

/** مسودات لكل صف — لا مشاركة حقول بين صفوف متعددة */
const rowDrafts = reactive({
  owner: {} as Record<string, string>,
  scheduleLocal: {} as Record<string, string>,
  complete: {} as Record<string, string>,
  cancel: {} as Record<string, string>,
})

const localOpError = ref<string | null>(null)

const ctrl = usePlatformControlledActions(() => props.incidentKey)

onMounted(() => {
  void ctrl.fetchList()
})

watch(
  () => props.incidentKey,
  () => {
    localOpError.value = null
    void ctrl.fetchList()
  },
)

const sorted = computed(() => [...ctrl.items.value].sort((a, b) => (a.created_at < b.created_at ? 1 : a.created_at > b.created_at ? -1 : a.action_id.localeCompare(b.action_id))))

function setOpError(e: unknown): void {
  if (isAxiosError(e) && e.response?.data && typeof e.response.data === 'object') {
    const m = (e.response.data as { message?: string }).message
    localOpError.value = typeof m === 'string' ? m : JSON.stringify(e.response.data)
    return
  }
  localOpError.value = e instanceof Error ? e.message : 'request_failed'
}

async function onCreateFollowUp(): Promise<void> {
  const s = summaryNew.value.trim()
  if (!s) return
  localOpError.value = null
  try {
    await ctrl.createFollowUp(s, idem.value.trim() || undefined)
    summaryNew.value = ''
    idem.value = ''
  } catch (e) {
    setOpError(e)
  }
}

async function onHumanReview(): Promise<void> {
  const s = humanSummary.value.trim()
  if (!s) return
  localOpError.value = null
  try {
    await ctrl.requestHumanReview(s)
    humanSummary.value = ''
  } catch (e) {
    setOpError(e)
  }
}

async function onLinkTask(): Promise<void> {
  const r = taskRef.value.trim()
  if (!r) return
  localOpError.value = null
  try {
    await ctrl.linkTaskReference(r)
    taskRef.value = ''
  } catch (e) {
    setOpError(e)
  }
}

async function onAssign(row: PlatformControlledActionRow): Promise<void> {
  const o = (rowDrafts.owner[row.action_id] ?? '').trim()
  if (o.length < 2) {
    localOpError.value = 'assigned_owner_required'
    return
  }
  localOpError.value = null
  try {
    await ctrl.assignOwner(row.action_id, o)
    rowDrafts.owner[row.action_id] = ''
  } catch (e) {
    setOpError(e)
  }
}

async function onSchedule(row: PlatformControlledActionRow): Promise<void> {
  const raw = rowDrafts.scheduleLocal[row.action_id] ?? ''
  const iso = datetimeLocalToIso(raw)
  if (iso === null) {
    localOpError.value = 'schedule_datetime_invalid_or_empty'
    return
  }
  localOpError.value = null
  try {
    await ctrl.scheduleAction(row.action_id, iso)
    rowDrafts.scheduleLocal[row.action_id] = ''
  } catch (e) {
    setOpError(e)
  }
}

async function onComplete(row: PlatformControlledActionRow): Promise<void> {
  const r = (rowDrafts.complete[row.action_id] ?? '').trim()
  if (r.length < 1) {
    localOpError.value = 'completion_reason_required'
    return
  }
  localOpError.value = null
  try {
    await ctrl.completeAction(row.action_id, r)
    rowDrafts.complete[row.action_id] = ''
  } catch (e) {
    setOpError(e)
  }
}

async function onCancel(row: PlatformControlledActionRow): Promise<void> {
  const r = (rowDrafts.cancel[row.action_id] ?? '').trim()
  if (r.length < 1) {
    localOpError.value = 'canceled_reason_required'
    return
  }
  localOpError.value = null
  try {
    await ctrl.cancelAction(row.action_id, r)
    rowDrafts.cancel[row.action_id] = ''
  } catch (e) {
    setOpError(e)
  }
}

async function onReopen(row: PlatformControlledActionRow): Promise<void> {
  localOpError.value = null
  try {
    await ctrl.reopenAction(row.action_id)
  } catch (e) {
    setOpError(e)
  }
}
</script>

<template>
  <section v-if="ctrl.canView" class="mt-8 rounded-xl border border-slate-200/80 p-4 dark:border-slate-700">
    <h2 class="text-xs font-semibold text-slate-900 dark:text-white">أفعال متابعة مضبوطة</h2>
    <p class="mt-1 text-[10px] text-slate-500">
      قائمة ضيقة فقط — لا تغيّر دورة حياة الحادث تلقائياً ولا أي بيانات مالية.
    </p>
    <p v-if="ctrl.error" class="mt-2 text-[11px] text-amber-800">{{ ctrl.error }}</p>
    <p v-if="localOpError" class="mt-1 text-[11px] text-rose-800 dark:text-rose-200">{{ localOpError }}</p>
    <div v-else-if="ctrl.loading" class="mt-3 h-12 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800/80" />
    <div v-else class="mt-3 space-y-4 text-[11px]">
      <ul class="space-y-2">
        <li v-for="row in sorted" :key="row.action_id" class="rounded-lg border border-slate-100 px-2 py-2 dark:border-slate-800">
          <div class="flex flex-wrap items-center gap-2">
            <span class="font-mono text-[10px]" dir="ltr">{{ row.action_id.slice(0, 8) }}…</span>
            <span class="rounded bg-slate-100 px-1.5 py-0.5 dark:bg-slate-800">{{ row.status }}</span>
            <span class="text-slate-500">{{ row.action_type }}</span>
          </div>
          <p class="mt-1 text-slate-700 dark:text-slate-200">{{ row.action_summary }}</p>
          <div v-if="ctrl.canAssign && (row.status === 'open' || row.status === 'assigned' || row.status === 'scheduled')" class="mt-2 flex flex-wrap gap-1">
            <input
              :value="rowDrafts.owner[row.action_id] ?? ''"
              type="text"
              placeholder="user:123"
              class="max-w-[160px] rounded border px-1 py-0.5 font-mono text-[10px] dark:border-slate-600"
              dir="ltr"
              @input="rowDrafts.owner[row.action_id] = ($event.target as HTMLInputElement).value"
            >
            <button type="button" class="rounded bg-slate-700 px-2 py-0.5 text-[10px] text-white" @click="onAssign(row)">تعيين مالك</button>
          </div>
          <div v-if="ctrl.canSchedule && row.action_type === 'follow_up' && (row.status === 'open' || row.status === 'assigned')" class="mt-2 space-y-1">
            <div class="flex flex-wrap items-center gap-1">
              <input
                :value="rowDrafts.scheduleLocal[row.action_id] ?? ''"
                type="datetime-local"
                class="rounded border px-1 py-0.5 text-[10px] dark:border-slate-600"
                @input="rowDrafts.scheduleLocal[row.action_id] = ($event.target as HTMLInputElement).value"
              >
              <button type="button" class="rounded bg-slate-600 px-2 py-0.5 text-[10px] text-white" @click="onSchedule(row)">جدولة</button>
            </div>
            <p class="text-[9px] text-slate-500">يُرسل للخادم كـ ISO8601؛ يُرفض الحقل الفارغ أو غير الصالح قبل الطلب.</p>
          </div>
          <div v-if="ctrl.canComplete && (row.status === 'open' || row.status === 'assigned' || row.status === 'scheduled') && (row.action_type === 'follow_up' || row.action_type === 'human_review_request')" class="mt-2 flex flex-wrap gap-1">
            <input
              :value="rowDrafts.complete[row.action_id] ?? ''"
              type="text"
              placeholder="سبب الإكمال"
              class="min-w-[120px] flex-1 rounded border px-1 py-0.5 dark:border-slate-600"
              @input="rowDrafts.complete[row.action_id] = ($event.target as HTMLInputElement).value"
            >
            <button type="button" class="rounded bg-emerald-800 px-2 py-0.5 text-[10px] text-white" @click="onComplete(row)">إكمال</button>
          </div>
          <div v-if="ctrl.canCancel && row.status !== 'completed' && row.status !== 'canceled'" class="mt-2 flex flex-wrap gap-1">
            <input
              :value="rowDrafts.cancel[row.action_id] ?? ''"
              type="text"
              placeholder="سبب الإلغاء"
              class="min-w-[120px] flex-1 rounded border px-1 py-0.5 dark:border-slate-600"
              @input="rowDrafts.cancel[row.action_id] = ($event.target as HTMLInputElement).value"
            >
            <button type="button" class="rounded bg-amber-800 px-2 py-0.5 text-[10px] text-white" @click="onCancel(row)">إلغاء</button>
          </div>
          <div v-if="ctrl.canReopen && (row.status === 'completed' || row.status === 'canceled')" class="mt-2">
            <button type="button" class="rounded border border-slate-400 px-2 py-0.5 text-[10px]" @click="onReopen(row)">إعادة فتح</button>
          </div>
        </li>
        <li v-if="sorted.length === 0" class="text-slate-500">لا توجد أفعال مسجّلة بعد.</li>
      </ul>

      <div v-if="ctrl.canCreateFollowUp" class="rounded-lg border border-dashed border-slate-200 p-2 dark:border-slate-700">
        <p class="mb-1 font-medium text-slate-800 dark:text-slate-100">متابعة جديدة</p>
        <textarea v-model="summaryNew" rows="2" class="w-full rounded border px-2 py-1 text-[11px] dark:border-slate-600" placeholder="ملخص المتابعة" />
        <input v-model="idem" type="text" class="mt-1 w-full rounded border px-2 py-1 font-mono text-[10px] dark:border-slate-600" placeholder="idempotency_key (اختياري)" dir="ltr">
        <button type="button" class="mt-2 rounded bg-primary-700 px-2 py-1 text-[10px] text-white" @click="onCreateFollowUp">إنشاء</button>
      </div>

      <div v-if="ctrl.canRequestHumanReview" class="rounded-lg border border-dashed border-slate-200 p-2 dark:border-slate-700">
        <p class="mb-1 font-medium text-slate-800 dark:text-slate-100">طلب مراجعة بشرية</p>
        <textarea v-model="humanSummary" rows="2" class="w-full rounded border px-2 py-1 text-[11px] dark:border-slate-600" />
        <button type="button" class="mt-2 rounded bg-slate-800 px-2 py-1 text-[10px] text-white" @click="onHumanReview">تسجيل الطلب</button>
      </div>

      <div v-if="ctrl.canLinkTask" class="rounded-lg border border-dashed border-slate-200 p-2 dark:border-slate-700">
        <p class="mb-1 font-medium text-slate-800 dark:text-slate-100">ربط مرجع مهمة داخلي</p>
        <input v-model="taskRef" type="text" class="w-full rounded border px-2 py-1 font-mono text-[10px] dark:border-slate-600" placeholder="task:internal-1" dir="ltr">
        <button type="button" class="mt-2 rounded bg-slate-800 px-2 py-1 text-[10px] text-white" @click="onLinkTask">ربط</button>
      </div>
    </div>
  </section>
</template>
