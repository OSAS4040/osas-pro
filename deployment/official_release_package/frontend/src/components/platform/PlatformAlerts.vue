<template>
  <div class="mt-4 space-y-3">
    <div class="border-b border-slate-100 pb-2 dark:border-slate-800">
      <h3 class="text-sm font-semibold text-slate-900 dark:text-white">تنبيهات تشغيلية</h3>
      <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">كل تنبيه يربط المشكلة بخطوة تالية واضحة.</p>
    </div>
    <template v-if="alerts.length">
      <PlatformAlertCard v-for="(a, i) in alerts" :key="i" :title="a.message" :description="a.action_hint || ''" :severity="alertSeverity(a.severity)">
        <div class="mt-3 flex justify-end border-t border-white/40 pt-3 dark:border-slate-700/60">
          <RouterLink
            :to="a.action_path"
            class="inline-flex rounded-lg bg-primary-600 px-3 py-1.5 text-[11px] font-medium text-white transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-400 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
          >
            {{ alertCta(a) }}
          </RouterLink>
        </div>
      </PlatformAlertCard>
    </template>
    <p v-else class="rounded-xl border border-slate-200/90 bg-slate-50/80 px-3 py-3 text-center text-[11px] text-slate-600 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-400">
      لا توجد تنبيهات حالياً — الحالة ضمن الحدود المعتادة أو لا توجد أحداث تستدعي تنبيهاً.
    </p>
  </div>
</template>

<script setup lang="ts">
import { RouterLink } from 'vue-router'
import type { PlatformAdminOverviewAlert } from '@/types/platformAdminOverview'
import PlatformAlertCard from '@/components/platform-admin/ui/PlatformAlertCard.vue'

defineProps<{ alerts: PlatformAdminOverviewAlert[] }>()

function alertSeverity(severity: string): 'low' | 'medium' | 'high' {
  if (severity === 'high') return 'high'
  if (severity === 'medium') return 'medium'
  return 'low'
}

function alertCta(a: PlatformAdminOverviewAlert): string {
  if (a.action_hint && a.action_hint.length > 0) return 'تنفيذ الخطوة المقترحة'
  return 'فتح المسار المرتبط'
}
</script>
