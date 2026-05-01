<script setup lang="ts">
import { onMounted, watch, computed } from 'vue'
import { RouterLink } from 'vue-router'
import { usePlatformIncidentCorrelation } from '@/composables/platform-admin/intelligence/usePlatformIncidentCorrelation'

const props = defineProps<{ incidentKey: string }>()

const { canView, bundle, loading, error, fetchCorrelation } = usePlatformIncidentCorrelation(() => props.incidentKey)

onMounted(() => {
  void fetchCorrelation()
})

watch(
  () => props.incidentKey,
  () => {
    void fetchCorrelation()
  },
)

function arr(key: string): unknown[] {
  const b = bundle.value
  if (!b) {
    return []
  }
  const v = (b as Record<string, unknown>)[key]
  return Array.isArray(v) ? v : []
}

function record(key: string): Record<string, unknown> | null {
  const b = bundle.value
  if (!b) {
    return null
  }
  const v = (b as Record<string, unknown>)[key]
  return v && typeof v === 'object' && !Array.isArray(v) ? (v as Record<string, unknown>) : null
}

function incidentPath(key: string): string {
  return `/platform/intelligence/incidents/${encodeURIComponent(key)}`
}

const affectedCompanies = computed(() => {
  const inc = record('incident')
  const ac = inc?.affected_companies
  return Array.isArray(ac) ? ac : []
})
</script>

<template>
  <section v-if="canView" class="mt-8 rounded-xl border border-slate-200/80 p-4 dark:border-slate-700">
    <h2 class="text-xs font-semibold text-slate-900 dark:text-white">السياق المترابط</h2>
    <p class="mt-1 text-[10px] text-slate-500">روابط مفسَّرة (سببية / سياقية / مشتقة / زمنية) — بديل لا يعني دمج سجل القرارات أو دورة الحياة.</p>
    <p v-if="error" class="mt-2 text-[11px] text-amber-800">{{ error }}</p>
    <div v-else-if="loading" class="mt-3 h-16 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800/80" />
    <div v-else-if="bundle" class="mt-3 space-y-4 text-[11px] text-slate-700 dark:text-slate-200">
      <p v-if="typeof (bundle as Record<string, unknown>).executive_summary === 'string'" class="leading-relaxed">
        {{ (bundle as Record<string, string>).executive_summary }}
      </p>

      <div v-if="record('incident')" class="flex flex-wrap gap-2 text-[10px] text-slate-600 dark:text-slate-400">
        <span>شدة: {{ record('incident')?.severity }}</span>
        <span>ثقة: {{ record('incident')?.confidence }}</span>
        <span>تصعيد: {{ record('incident')?.escalation_state }}</span>
      </div>

      <div v-if="record('originating_candidate')" class="rounded-lg border border-slate-100 bg-slate-50/50 p-2 dark:border-slate-800 dark:bg-slate-900/50">
        <h3 class="font-medium text-slate-800 dark:text-slate-100">سياق مرشح منشئ (أوّل تطابق)</h3>
        <p class="mt-1 font-mono text-[10px]" dir="ltr">{{ record('originating_candidate')?.incident_key }}</p>
        <p class="mt-1 text-slate-600 dark:text-slate-300">{{ record('originating_candidate')?.title }}</p>
        <p v-if="record('originating_candidate')?.why_summary" class="mt-1 text-[10px] text-slate-500">{{ record('originating_candidate')?.why_summary }}</p>
      </div>

      <div>
        <h3 class="font-medium text-slate-800 dark:text-slate-100">إشارات سببية</h3>
        <ul class="mt-1 list-disc space-y-1 ps-4">
          <li v-for="(it, i) in arr('causal_signal_links')" :key="'c' + i">
            <span class="font-mono text-[10px]" dir="ltr">{{ (it as Record<string, unknown>).entity_ref }}</span>
            <span class="text-slate-500"> — {{ (it as Record<string, unknown>).compact_why }}</span>
            <span v-if="(it as Record<string, unknown>).relation_type" class="block text-[10px] text-slate-400" dir="ltr">
              {{ (it as Record<string, unknown>).relation_type }} · {{ (it as Record<string, unknown>).relation_reason }}
            </span>
          </li>
          <li v-if="arr('causal_signal_links').length === 0" class="text-slate-500">لا يوجد تطابق سببي في لقطة الإشارات الحالية.</li>
        </ul>
      </div>

      <div>
        <h3 class="font-medium text-slate-800 dark:text-slate-100">إشارات ذات سياق مشترك (غير سببية بالضرورة)</h3>
        <ul class="mt-1 list-disc space-y-1 ps-4">
          <li v-for="(it, i) in arr('contextual_signals')" :key="'x' + i">
            <span class="font-mono text-[10px]" dir="ltr">{{ (it as Record<string, unknown>).entity_ref }}</span>
            <span class="text-slate-500"> — {{ (it as Record<string, unknown>).compact_why }}</span>
            <span v-if="(it as Record<string, unknown>).relation_type" class="block text-[10px] text-slate-400" dir="ltr">
              {{ (it as Record<string, unknown>).relation_type }} · {{ (it as Record<string, unknown>).relation_reason }}
            </span>
          </li>
          <li v-if="arr('contextual_signals').length === 0" class="text-slate-500">لا إشارات سياقية ضمن الحدّ الأقصى للعرض.</li>
        </ul>
      </div>

      <div>
        <h3 class="font-medium text-slate-800 dark:text-slate-100">شركات متأثرة</h3>
        <ul class="mt-1 flex flex-wrap gap-2">
          <li v-for="cid in affectedCompanies" :key="'co-' + String(cid)">
            <RouterLink
              :to="`/platform/companies/${encodeURIComponent(String(cid))}`"
              class="rounded border border-slate-200 px-1.5 py-0.5 text-[10px] text-primary-700 hover:bg-slate-50 dark:border-slate-600 dark:text-primary-300 dark:hover:bg-slate-800"
            >
              {{ cid }}
            </RouterLink>
          </li>
          <li v-if="affectedCompanies.length === 0" class="text-slate-500">—</li>
        </ul>
      </div>

      <div>
        <h3 class="font-medium text-slate-800 dark:text-slate-100">قرارات مرتبطة (مختصرة)</h3>
        <ul class="mt-1 list-disc space-y-1 ps-4">
          <li v-for="(it, i) in arr('decisions')" :key="'d' + i">
            {{ (it as Record<string, unknown>).entry && ((it as Record<string, unknown>).entry as Record<string, unknown>).decision_type }}
            —
            {{ (it as Record<string, unknown>).entry && ((it as Record<string, unknown>).entry as Record<string, unknown>).decision_summary }}
            <span class="text-slate-500"> — {{ (it as Record<string, unknown>).compact_why }}</span>
          </li>
          <li v-if="arr('decisions').length === 0" class="text-slate-500">لا قرارات ظاهرة أو الصلاحية decisions.read غير مفعّلة.</li>
        </ul>
      </div>

      <div>
        <h3 class="font-medium text-slate-800 dark:text-slate-100">مسارات موجّهة مرتبطة</h3>
        <ul class="mt-1 list-disc space-y-1 ps-4">
          <li v-for="(it, i) in arr('workflow_runs')" :key="'w' + i">
            <span class="font-mono text-[10px]" dir="ltr">{{ (it as Record<string, unknown>).workflow_key }}</span>
            <span class="text-slate-500"> — {{ (it as Record<string, unknown>).compact_why }}</span>
            <span v-if="(it as Record<string, unknown>).relation_reason" class="block text-[10px] text-slate-400" dir="ltr">
              {{ (it as Record<string, unknown>).relation_reason }}
            </span>
          </li>
        </ul>
      </div>

      <RouterLink to="/platform/intelligence/command" class="inline-block text-[10px] text-primary-700 hover:underline dark:text-primary-300">
        فتح سطح القيادة الموحّد ←
      </RouterLink>
      <RouterLink :to="incidentPath(String(props.incidentKey))" class="ms-3 inline-block text-[10px] text-slate-500 hover:underline">
        إعادة تحميل السياق
      </RouterLink>
    </div>
  </section>
</template>
