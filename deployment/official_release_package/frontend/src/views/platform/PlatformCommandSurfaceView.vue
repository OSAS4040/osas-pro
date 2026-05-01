<script setup lang="ts">
import { onMounted, computed } from 'vue'
import { RouterLink } from 'vue-router'
import { usePlatformCommandSurface } from '@/composables/platform-admin/intelligence/usePlatformCommandSurface'

/** شكل الاستجابة من CommandSurfaceAssembler (قراءة فقط). */
export interface PlatformCommandSurfacePayload {
  error?: string
  message?: string
  summary?: Record<string, number>
  open_high_severity_incidents?: Array<Record<string, unknown>>
  recently_escalated_incidents?: Array<Record<string, unknown>>
  monitoring_incidents_sample?: Array<Record<string, unknown>>
  decisions_requiring_follow_up?: Array<Record<string, unknown>>
  recent_workflow_executions?: Array<Record<string, unknown>>
  signals_not_on_open_incidents?: Array<Record<string, unknown>>
  candidates_likely_to_materialize?: Array<Record<string, unknown>>
  companies_with_stacked_signals?: Array<Record<string, unknown>>
  meta?: Record<string, unknown>
}

const SUMMARY_LABELS: Record<string, string> = {
  open_high_severity: 'حوادث عالية خطورة (مفتوحة)',
  escalated: 'حوادث مصعّدة',
  monitoring_sample: 'عيّنة مراقبة',
  decisions_follow_up: 'قرارات متابعة',
  workflows_recent: 'تنفيذات مسارات حديثة',
  signals_uncovered: 'إشارات بلا تغطية حادث مفتوح',
  candidates_next: 'مرشحات قادمة',
  company_risk_cards: 'شركات بكثافة إشارات',
}

const { canView, payload, loading, error, fetchSurface } = usePlatformCommandSurface()

const surface = computed(() => (payload.value ?? null) as PlatformCommandSurfacePayload | null)

onMounted(() => {
  void fetchSurface()
})

function list(key: keyof PlatformCommandSurfacePayload): unknown[] {
  const p = surface.value
  if (!p) {
    return []
  }
  const v = p[key]
  return Array.isArray(v) ? v : []
}

function incidentPath(key: string): string {
  return `/platform/intelligence/incidents/${encodeURIComponent(key)}`
}
</script>

<template>
  <div class="mx-auto max-w-5xl px-4 py-6" dir="rtl">
    <header class="mb-6 border-b border-slate-200/80 pb-4 dark:border-slate-700">
      <h1 class="text-lg font-semibold text-slate-900 dark:text-white">سطح القيادة الموحّد</h1>
      <p class="mt-1 text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
        قراءة فقط — يربط الإشارات والمرشحين والحوادث والقرارات والمسارات دون تنفيذ جديد أو إصلاح تلقائي.
      </p>
      <RouterLink to="/platform/intelligence/incidents" class="mt-2 inline-block text-[11px] text-primary-700 hover:underline dark:text-primary-300">
        ← مركز الحوادث
      </RouterLink>
    </header>

    <p v-if="!canView" class="text-sm text-amber-800 dark:text-amber-200">لا تملك صلاحية عرض سطح القيادة.</p>
    <p v-else-if="error" class="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950">{{ error }}</p>
    <p v-else-if="surface?.error === 'forbidden'" class="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950">
      ممنوع — {{ surface.message ?? 'forbidden' }}
    </p>
    <div v-else-if="loading" class="space-y-3">
      <div v-for="i in 4" :key="i" class="h-20 animate-pulse rounded-xl bg-slate-100 dark:bg-slate-800/80" />
    </div>
    <div v-else-if="surface && !surface.error" class="space-y-8">
      <section v-if="surface.summary" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div
          v-for="(v, k) in surface.summary"
          :key="String(k)"
          class="rounded-xl border border-slate-200/80 bg-white p-3 text-center dark:border-slate-700 dark:bg-slate-900/40"
        >
          <p class="text-2xl font-semibold text-slate-900 dark:text-white">{{ v }}</p>
          <p class="text-[10px] text-slate-500">{{ SUMMARY_LABELS[String(k)] ?? k }}</p>
        </div>
      </section>

      <section class="rounded-xl border border-slate-200/80 bg-white/60 p-4 dark:border-slate-700 dark:bg-slate-900/30">
        <h2 class="text-xs font-semibold text-slate-800 dark:text-slate-100">حوادث عالية الخطورة (مفتوحة)</h2>
        <ul class="mt-2 space-y-1.5 text-[11px]">
          <li v-for="row in list('open_high_severity_incidents')" :key="String((row as Record<string, unknown>).incident_key)">
            <RouterLink
              :to="incidentPath(String((row as Record<string, unknown>).incident_key))"
              class="font-medium text-primary-700 hover:underline dark:text-primary-300"
            >
              {{ (row as Record<string, unknown>).title }}
            </RouterLink>
            <span class="text-slate-500"> — {{ (row as Record<string, unknown>).severity }}</span>
          </li>
          <li v-if="list('open_high_severity_incidents').length === 0" class="text-slate-500">لا توجد عناصر في هذه الفئة.</li>
        </ul>
      </section>

      <section class="rounded-xl border border-slate-200/80 bg-white/60 p-4 dark:border-slate-700 dark:bg-slate-900/30">
        <h2 class="text-xs font-semibold text-slate-800 dark:text-slate-100">حوادث مصعّدة مؤخرًا</h2>
        <ul class="mt-2 space-y-1.5 text-[11px]">
          <li v-for="row in list('recently_escalated_incidents')" :key="'e-' + String((row as Record<string, unknown>).incident_key)">
            <RouterLink
              :to="incidentPath(String((row as Record<string, unknown>).incident_key))"
              class="text-primary-700 hover:underline dark:text-primary-300"
            >
              {{ (row as Record<string, unknown>).title }}
            </RouterLink>
          </li>
          <li v-if="list('recently_escalated_incidents').length === 0" class="text-slate-500">لا توجد حوادث مصعّدة في العيّنة الحالية.</li>
        </ul>
      </section>

      <section class="rounded-xl border border-slate-200/80 bg-white/60 p-4 dark:border-slate-700 dark:bg-slate-900/30">
        <h2 class="text-xs font-semibold text-slate-800 dark:text-slate-100">عيّنة مراقبة (متابعة)</h2>
        <ul class="mt-2 space-y-1.5 text-[11px]">
          <li v-for="row in list('monitoring_incidents_sample')" :key="'m-' + String((row as Record<string, unknown>).incident_key)">
            <RouterLink
              :to="incidentPath(String((row as Record<string, unknown>).incident_key))"
              class="text-primary-700 hover:underline dark:text-primary-300"
            >
              {{ (row as Record<string, unknown>).title }}
            </RouterLink>
          </li>
          <li v-if="list('monitoring_incidents_sample').length === 0" class="text-slate-500">لا توجد عيّنة مراقبة في القائمة المختصرة.</li>
        </ul>
      </section>

      <section class="rounded-xl border border-slate-200/80 bg-white/60 p-4 dark:border-slate-700 dark:bg-slate-900/30">
        <h2 class="text-xs font-semibold text-slate-800 dark:text-slate-100">قرارات تتطلب متابعة</h2>
        <ul class="mt-2 space-y-1.5 text-[11px]">
          <li v-for="row in list('decisions_requiring_follow_up')" :key="String((row as Record<string, unknown>).decision_id)">
            <RouterLink
              :to="incidentPath(String((row as Record<string, unknown>).incident_key))"
              class="text-primary-700 hover:underline dark:text-primary-300"
            >
              {{ (row as Record<string, unknown>).decision_summary }}
            </RouterLink>
            <span class="text-slate-500"> — {{ (row as Record<string, unknown>).compact_why }}</span>
            <span v-if="(row as Record<string, unknown>).relation_type" class="block text-[10px] text-slate-400" dir="ltr">
              {{ (row as Record<string, unknown>).relation_type }}
            </span>
          </li>
          <li v-if="list('decisions_requiring_follow_up').length === 0" class="text-slate-500">
            لا توجد قرارات متابعة ظاهرة (قد تكون الصلاحيات محدودة أو لا توجد بيانات).
          </li>
        </ul>
      </section>

      <section class="rounded-xl border border-slate-200/80 bg-white/60 p-4 dark:border-slate-700 dark:bg-slate-900/30">
        <h2 class="text-xs font-semibold text-slate-800 dark:text-slate-100">تنفيذات مسارات موجّهة حديثة</h2>
        <ul class="mt-2 space-y-1.5 text-[11px]">
          <li v-for="row in list('recent_workflow_executions')" :key="'w-' + String((row as Record<string, unknown>).workflow_key) + String((row as Record<string, unknown>).incident_key)">
            <span class="font-mono text-[10px]" dir="ltr">{{ (row as Record<string, unknown>).workflow_key }}</span>
            <RouterLink
              v-if="(row as Record<string, unknown>).incident_key"
              :to="incidentPath(String((row as Record<string, unknown>).incident_key))"
              class="ms-1 text-primary-700 hover:underline dark:text-primary-300"
            >
              → الحادث
            </RouterLink>
            <span class="text-slate-500"> — {{ (row as Record<string, unknown>).compact_why }}</span>
          </li>
          <li v-if="list('recent_workflow_executions').length === 0" class="text-slate-500">لا توجد تنفيذات حديثة في العيّنة.</li>
        </ul>
      </section>

      <section class="rounded-xl border border-slate-200/80 bg-white/60 p-4 dark:border-slate-700 dark:bg-slate-900/30">
        <h2 class="text-xs font-semibold text-slate-800 dark:text-slate-100">إشارات بلا تغطية حادث مفتوح</h2>
        <ul class="mt-2 space-y-1.5 text-[11px]">
          <li v-for="row in list('signals_not_on_open_incidents')" :key="String((row as Record<string, unknown>).signal_key)">
            <span class="font-mono text-[10px]" dir="ltr">{{ (row as Record<string, unknown>).signal_key }}</span>
            <span class="text-slate-600"> — {{ (row as Record<string, unknown>).title }}</span>
            <span class="block text-[10px] text-slate-500">{{ (row as Record<string, unknown>).compact_why }}</span>
          </li>
          <li v-if="list('signals_not_on_open_incidents').length === 0" class="text-slate-500">لا توجد إشارات غير مغطاة أو غير مصرّح بعرض الإشارات.</li>
        </ul>
      </section>

      <section class="rounded-xl border border-slate-200/80 bg-white/60 p-4 dark:border-slate-700 dark:bg-slate-900/30">
        <h2 class="text-xs font-semibold text-slate-800 dark:text-slate-100">مرشحات قد تُمثَّل لاحقًا</h2>
        <ul class="mt-2 space-y-1.5 text-[11px]">
          <li v-for="row in list('candidates_likely_to_materialize')" :key="String((row as Record<string, unknown>).incident_key)">
            <span class="font-mono text-[10px]" dir="ltr">{{ (row as Record<string, unknown>).incident_key }}</span>
            <span class="text-slate-600"> — {{ (row as Record<string, unknown>).title }}</span>
            <span class="block text-[10px] text-slate-500">{{ (row as Record<string, unknown>).compact_why }}</span>
          </li>
          <li v-if="list('candidates_likely_to_materialize').length === 0" class="text-slate-500">لا مرشحات في هذه اللقطة أو الصلاحيات لا تشمل المرشحات.</li>
        </ul>
      </section>

      <section class="rounded-xl border border-slate-200/80 bg-white/60 p-4 dark:border-slate-700 dark:bg-slate-900/30">
        <h2 class="text-xs font-semibold text-slate-800 dark:text-slate-100">شركات بكثافة إشارات (سياق)</h2>
        <ul class="mt-2 space-y-1.5 text-[11px]">
          <li v-for="row in list('companies_with_stacked_signals')" :key="String((row as Record<string, unknown>).company_id)">
            <RouterLink
              :to="`/platform/companies/${encodeURIComponent(String((row as Record<string, unknown>).company_id))}`"
              class="text-primary-700 hover:underline dark:text-primary-300"
            >
              شركة {{ (row as Record<string, unknown>).company_id }}
            </RouterLink>
            <span class="text-slate-500"> — عدد مراجع الإشارات: {{ (row as Record<string, unknown>).stacked_signal_refs }}</span>
            <span class="block text-[10px] text-slate-500">{{ (row as Record<string, unknown>).compact_why }}</span>
          </li>
          <li v-if="list('companies_with_stacked_signals').length === 0" class="text-slate-500">لا بيانات كثافة أو غير مصرّح بعرض الإشارات.</li>
        </ul>
      </section>

      <p v-if="surface.meta?.permissions_used" class="text-[10px] text-slate-400" dir="ltr">
        permissions_used: {{ JSON.stringify(surface.meta.permissions_used) }}
      </p>
    </div>
  </div>
</template>
