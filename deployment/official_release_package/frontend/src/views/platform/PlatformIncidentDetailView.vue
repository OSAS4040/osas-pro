<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import PlatformIncidentGuidedWorkflowsPanel from '@/components/platform-admin/intelligence/PlatformIncidentGuidedWorkflowsPanel.vue'
import PlatformIncidentDecisionLogPanel from '@/components/platform-admin/intelligence/PlatformIncidentDecisionLogPanel.vue'
import PlatformIncidentCorrelationPanel from '@/components/platform-admin/intelligence/PlatformIncidentCorrelationPanel.vue'
import PlatformIncidentControlledActionsPanel from '@/components/platform-admin/intelligence/PlatformIncidentControlledActionsPanel.vue'
import { usePlatformIncidentDetail } from '@/composables/platform-admin/intelligence/usePlatformIncidentDetail'
import { usePlatformIncidentLifecycleActions } from '@/composables/platform-admin/intelligence/usePlatformIncidentLifecycleActions'

const route = useRoute()
const incidentKey = computed(() => String(route.params.incidentKey ?? ''))

const { incident, timeline, operatorNotes, loading, error, fetchDetail } = usePlatformIncidentDetail(incidentKey)

const actions = usePlatformIncidentLifecycleActions()

const resolveReason = ref('')
const closeReason = ref('')
const noteText = ref('')
const ownerRef = ref('')

onMounted(() => {
  void fetchDetail()
  void scrollToFocus()
})

watch(incidentKey, () => {
  void fetchDetail()
  void scrollToFocus()
})

watch(
  () => route.query.focus,
  () => {
    void scrollToFocus()
  },
)

async function scrollToFocus(): Promise<void> {
  const focus = String(route.query.focus ?? '').trim()
  if (focus === '') return
  await nextTick()
  const id = focus === 'decisions'
    ? 'incident-detail-decisions'
    : focus === 'controlled-actions'
      ? 'incident-detail-controlled-actions'
      : focus === 'correlation'
        ? 'incident-detail-correlation'
        : ''
  if (!id) return
  const el = document.getElementById(id)
  el?.scrollIntoView({ block: 'start', behavior: 'smooth' })
}

async function run(fn: () => Promise<void>): Promise<void> {
  await fn()
  await fetchDetail()
}
</script>

<template>
  <div class="mx-auto max-w-4xl px-4 py-6" dir="rtl">
    <div class="flex flex-wrap gap-3 text-[11px]">
      <RouterLink to="/platform/intelligence/incidents" class="text-primary-700 hover:underline dark:text-primary-300">
        ← العودة لمركز الحوادث
      </RouterLink>
      <RouterLink to="/platform/intelligence/command" class="text-primary-700 hover:underline dark:text-primary-300">
        سطح القيادة الموحّد
      </RouterLink>
    </div>

    <p v-if="error" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950">{{ error }}</p>
    <div v-else-if="loading" class="mt-6 space-y-2">
      <div class="h-24 animate-pulse rounded-xl bg-slate-100 dark:bg-slate-800/80" />
    </div>
    <template v-else-if="incident">
      <header class="mt-4 border-b border-slate-200/80 pb-4 dark:border-slate-700">
        <h1 class="text-lg font-semibold text-slate-900 dark:text-white">{{ incident.title }}</h1>
        <p class="mt-1 font-mono text-[11px] text-slate-500 dark:text-slate-400" dir="ltr">{{ incident.incident_key }}</p>
        <div class="mt-2 flex flex-wrap gap-2 text-[11px]">
          <span class="rounded-full bg-slate-100 px-2 py-0.5 dark:bg-slate-800">{{ incident.status }}</span>
          <span class="rounded-full bg-slate-100 px-2 py-0.5 dark:bg-slate-800">{{ incident.severity }}</span>
          <span class="rounded-full bg-slate-100 px-2 py-0.5 dark:bg-slate-800">{{ incident.escalation_state }}</span>
          <span class="rounded-full bg-slate-100 px-2 py-0.5 dark:bg-slate-800">مالك: {{ incident.owner ?? '—' }}</span>
        </div>
      </header>

      <section class="mt-4 space-y-3 text-sm text-slate-800 dark:text-slate-100">
        <p class="text-[12px] leading-relaxed">{{ incident.summary }}</p>
        <div class="whitespace-pre-line rounded-xl border border-slate-200/80 bg-slate-50/60 p-3 text-[11px] dark:border-slate-700 dark:bg-slate-900/40">
          {{ incident.why_summary }}
        </div>
      </section>

      <div id="incident-detail-correlation">
        <PlatformIncidentCorrelationPanel :incident-key="incident.incident_key" />
      </div>

      <div id="incident-detail-controlled-actions">
        <PlatformIncidentControlledActionsPanel :incident-key="incident.incident_key" />
      </div>

      <section v-if="actions.canAck" class="mt-6 space-y-3 rounded-xl border border-slate-200/80 p-4 dark:border-slate-700">
        <h2 class="text-xs font-semibold text-slate-900 dark:text-white">إجراءات آمنة</h2>
        <div class="flex flex-wrap gap-2">
          <button v-if="incident.status === 'open'" type="button" class="rounded-lg bg-slate-800 px-2 py-1 text-[11px] text-white" @click="run(() => actions.acknowledge(incidentKey))">إقرار استلام</button>
          <button v-if="incident.status === 'acknowledged'" type="button" class="rounded-lg bg-slate-800 px-2 py-1 text-[11px] text-white" @click="run(() => actions.moveUnderReview(incidentKey))">تحت المراجعة</button>
          <button v-if="incident.status === 'under_review' && actions.canEscalate" type="button" class="rounded-lg bg-amber-700 px-2 py-1 text-[11px] text-white" @click="run(() => actions.escalate(incidentKey, 'تصعيد تشغيلي'))">تصعيد</button>
          <button v-if="(incident.status === 'under_review' || incident.status === 'escalated') && actions.canAck" type="button" class="rounded-lg bg-slate-700 px-2 py-1 text-[11px] text-white" @click="run(() => actions.moveMonitoring(incidentKey))">مراقبة</button>
        </div>
        <div v-if="actions.canAssign" class="flex flex-wrap items-end gap-2">
          <input v-model="ownerRef" type="text" placeholder="user:123" class="rounded border border-slate-300 px-2 py-1 text-[11px] font-mono dark:border-slate-600" dir="ltr">
          <button type="button" class="rounded-lg bg-primary-700 px-2 py-1 text-[11px] text-white" @click="run(() => actions.assignOwner(incidentKey, ownerRef))">تعيين مالك</button>
        </div>
        <div v-if="actions.canResolve && (incident.status === 'monitoring' || incident.status === 'escalated')" class="space-y-1">
          <textarea v-model="resolveReason" rows="2" class="w-full rounded border border-slate-300 p-2 text-[11px] dark:border-slate-600 dark:bg-slate-950" placeholder="سبب الحل (إلزامي)" />
          <button type="button" class="rounded-lg bg-emerald-800 px-2 py-1 text-[11px] text-white" @click="run(() => actions.resolve(incidentKey, resolveReason))">حل</button>
        </div>
        <div v-if="actions.canClose && incident.status === 'resolved'" class="space-y-1">
          <textarea v-model="closeReason" rows="2" class="w-full rounded border border-slate-300 p-2 text-[11px] dark:border-slate-600 dark:bg-slate-950" placeholder="سبب الإغلاق (إلزامي)" />
          <button type="button" class="rounded-lg bg-slate-600 px-2 py-1 text-[11px] text-white" @click="run(() => actions.close(incidentKey, closeReason))">إغلاق</button>
        </div>
        <div v-if="actions.canAck && incident.status !== 'closed'" class="space-y-1">
          <textarea v-model="noteText" rows="2" class="w-full rounded border border-slate-300 p-2 text-[11px] dark:border-slate-600 dark:bg-slate-950" placeholder="ملاحظة تشغيلية" />
          <button type="button" class="rounded-lg border border-slate-400 px-2 py-1 text-[11px]" @click="run(async () => { await actions.appendNote(incidentKey, noteText); noteText = '' })">إضافة ملاحظة</button>
        </div>
      </section>

      <section class="mt-8">
        <h2 class="text-xs font-semibold text-slate-900 dark:text-white">خط زمني للتغييرات</h2>
        <ul class="mt-2 space-y-2 text-[11px] text-slate-700 dark:text-slate-300">
          <li v-for="ev in timeline" :key="ev.id" class="rounded-lg border border-slate-100 px-2 py-1 dark:border-slate-800">
            <span class="font-mono text-[10px]" dir="ltr">{{ ev.event_type }}</span>
            — {{ ev.prior_status }} → {{ ev.next_status }}
            <span v-if="ev.reason" class="block text-slate-500">سبب: {{ ev.reason }}</span>
          </li>
        </ul>
      </section>

      <section v-if="operatorNotes.length" class="mt-6">
        <h2 class="text-xs font-semibold text-slate-900 dark:text-white">ملاحظات</h2>
        <ul class="mt-2 list-disc ps-4 text-[11px]">
          <li v-for="(n, i) in operatorNotes" :key="i">{{ JSON.stringify(n) }}</li>
        </ul>
      </section>

      <PlatformIncidentGuidedWorkflowsPanel :incident-key="incidentKey" @completed="run(() => fetchDetail())" />

      <div id="incident-detail-decisions">
        <PlatformIncidentDecisionLogPanel :incident-key="incidentKey" />
      </div>
    </template>
  </div>
</template>
