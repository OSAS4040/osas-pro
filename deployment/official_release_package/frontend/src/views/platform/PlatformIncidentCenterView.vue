<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { usePlatformIntelligenceIncidents } from '@/composables/platform-admin/intelligence/usePlatformIntelligenceIncidents'
import { usePlatformIncidentLifecycleActions } from '@/composables/platform-admin/intelligence/usePlatformIncidentLifecycleActions'
import { PLATFORM_INTELLIGENCE_SEVERITY } from '@/types/platform-admin/platformIntelligenceEnums'
import { PLATFORM_INCIDENT_STATUS } from '@/types/platform-admin/platformIntelligenceEnums'

const {
  canView,
  canMaterialize,
  sorted,
  loading,
  error,
  statusFilter,
  severityFilter,
  escalationFilter,
  typeFilter,
  ownerFilter,
  companyFilter,
  freshHours,
  fetchIncidents,
} = usePlatformIntelligenceIncidents()

const { materialize } = usePlatformIncidentLifecycleActions()

const materializeKey = ref('')

onMounted(() => {
  void fetchIncidents()
})

watch([statusFilter, severityFilter, escalationFilter, typeFilter, ownerFilter, companyFilter, freshHours], () => {
  void fetchIncidents()
})

async function onMaterialize(): Promise<void> {
  const k = materializeKey.value.trim()
  if (!k) return
  try {
    await materialize(k)
    materializeKey.value = ''
    await fetchIncidents()
  } catch {
    /* optional: toast */
  }
}
</script>

<template>
  <div class="mx-auto max-w-6xl px-4 py-6" dir="rtl">
    <header class="mb-6 border-b border-slate-200/80 pb-4 dark:border-slate-700">
      <h1 class="text-lg font-semibold text-slate-900 dark:text-white">مركز الحوادث التشغيلية</h1>
      <p class="mt-1 max-w-3xl text-[12px] leading-relaxed text-slate-600 dark:text-slate-400">
        إدارة دورة حياة محدودة وآمنة — منفصل عن مرشّحات الحوادث وعن سجل القرارات. لا إصلاح تلقائي ولا أوامر على بيانات مالية.
      </p>
      <RouterLink
        to="/platform/intelligence/command"
        class="mt-2 inline-block text-[11px] font-semibold text-primary-700 hover:underline dark:text-primary-300"
      >
        سطح القيادة الموحّد (قراءة فقط) ←
      </RouterLink>
    </header>

    <section v-if="canMaterialize" class="mb-6 rounded-xl border border-slate-200/80 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-900/40">
      <p class="text-[11px] font-medium text-slate-700 dark:text-slate-300">تحويل مرشّح رسمي إلى حادث مُدار</p>
      <div class="mt-2 flex flex-wrap items-end gap-2">
        <input
          v-model="materializeKey"
          type="text"
          placeholder="incident_key من المرشّحات"
          class="min-w-[220px] flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-mono text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
          dir="ltr"
        >
        <button
          type="button"
          class="rounded-lg bg-primary-600 px-3 py-2 text-xs font-semibold text-white hover:bg-primary-700"
          @click="onMaterialize"
        >
          تمثيل من مرشّح
        </button>
      </div>
    </section>

    <div v-if="!canView" class="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-600 dark:border-slate-600 dark:text-slate-400">
      لا تملك صلاحية عرض الحوادث.
    </div>

    <template v-else>
      <div class="mb-4 flex flex-wrap gap-3">
        <label class="flex flex-col gap-1 text-[11px] text-slate-600 dark:text-slate-300">
          <span>الحالة</span>
          <select v-model="statusFilter" class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs dark:border-slate-600 dark:bg-slate-900">
            <option value="">الكل</option>
            <option v-for="s in PLATFORM_INCIDENT_STATUS" :key="s" :value="s">{{ s }}</option>
          </select>
        </label>
        <label class="flex flex-col gap-1 text-[11px] text-slate-600 dark:text-slate-300">
          <span>الشدة</span>
          <select v-model="severityFilter" class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs dark:border-slate-600 dark:bg-slate-900">
            <option value="">الكل</option>
            <option v-for="s in PLATFORM_INTELLIGENCE_SEVERITY" :key="s" :value="s">{{ s }}</option>
          </select>
        </label>
        <label class="flex flex-col gap-1 text-[11px] text-slate-600 dark:text-slate-300">
          <span>التصعيد</span>
          <select v-model="escalationFilter" class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs dark:border-slate-600 dark:bg-slate-900">
            <option value="">الكل</option>
            <option value="none">none</option>
            <option value="pending">pending</option>
            <option value="escalated">escalated</option>
            <option value="contained">contained</option>
          </select>
        </label>
        <label class="flex flex-col gap-1 text-[11px] text-slate-600 dark:text-slate-300">
          <span>نوع الحادث</span>
          <input v-model="typeFilter" type="text" class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs dark:border-slate-600 dark:bg-slate-900" dir="ltr">
        </label>
        <label class="flex flex-col gap-1 text-[11px] text-slate-600 dark:text-slate-300">
          <span>مالك (جزئي)</span>
          <input v-model="ownerFilter" type="text" class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs dark:border-slate-600 dark:bg-slate-900" dir="ltr">
        </label>
        <label class="flex flex-col gap-1 text-[11px] text-slate-600 dark:text-slate-300">
          <span>شركة</span>
          <input v-model="companyFilter" type="text" class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs dark:border-slate-600 dark:bg-slate-900" dir="ltr">
        </label>
        <label class="flex flex-col gap-1 text-[11px] text-slate-600 dark:text-slate-300">
          <span>حداثة (ساعات)</span>
          <input v-model="freshHours" type="number" min="1" class="w-24 rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs dark:border-slate-600 dark:bg-slate-900">
        </label>
      </div>

      <p v-if="error" class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">{{ error }}</p>
      <div v-else-if="loading" class="space-y-2">
        <div v-for="n in 4" :key="n" class="h-10 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800/80" />
      </div>
      <div v-else-if="sorted.length === 0" class="rounded-xl border border-dashed border-slate-200 px-4 py-10 text-center text-sm text-slate-600 dark:border-slate-700 dark:text-slate-400">
        لا توجد حوادث مسجّلة بعد — يمكن تمثيل مرشّح رسمي من الأعلى.
      </div>
      <div v-else class="overflow-x-auto rounded-xl border border-slate-200/80 dark:border-slate-700">
        <table class="min-w-full divide-y divide-slate-200 text-start text-xs dark:divide-slate-700">
          <thead class="bg-slate-50/80 dark:bg-slate-900/60">
            <tr>
              <th class="px-3 py-2 font-semibold text-slate-700 dark:text-slate-200">الشدة</th>
              <th class="px-3 py-2 font-semibold text-slate-700 dark:text-slate-200">الحالة</th>
              <th class="px-3 py-2 font-semibold text-slate-700 dark:text-slate-200">التصعيد</th>
              <th class="px-3 py-2 font-semibold text-slate-700 dark:text-slate-200">المالك</th>
              <th class="px-3 py-2 font-semibold text-slate-700 dark:text-slate-200">إشارات</th>
              <th class="px-3 py-2 font-semibold text-slate-700 dark:text-slate-200">شركات</th>
              <th class="px-3 py-2 font-semibold text-slate-700 dark:text-slate-200">ثقة</th>
              <th class="px-3 py-2 font-semibold text-slate-700 dark:text-slate-200">عنوان</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white/90 dark:divide-slate-800 dark:bg-slate-950/40">
            <tr v-for="row in sorted" :key="row.incident_key">
              <td class="px-3 py-2 font-mono text-[10px] uppercase">{{ row.severity }}</td>
              <td class="px-3 py-2">{{ row.status }}</td>
              <td class="px-3 py-2">{{ row.escalation_state }}</td>
              <td class="px-3 py-2 font-mono text-[10px]" dir="ltr">{{ row.owner ?? '—' }}</td>
              <td class="px-3 py-2">{{ row.source_signals?.length ?? 0 }}</td>
              <td class="px-3 py-2">{{ row.affected_companies?.length ?? 0 }}</td>
              <td class="px-3 py-2">{{ (row.confidence * 100).toFixed(0) }}٪</td>
              <td class="px-3 py-2">
                <RouterLink
                  class="text-primary-700 hover:underline dark:text-primary-300"
                  :to="{ name: 'platform-incident-detail', params: { incidentKey: row.incident_key } }"
                >
                  {{ row.title }}
                </RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>
