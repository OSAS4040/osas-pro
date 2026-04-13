<template>
  <div v-if="alerts.length" class="mt-4 space-y-2">
    <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200">Alerts</h3>
    <div v-for="(a, i) in alerts" :key="i" class="rounded-xl border px-3 py-2 text-xs" :class="boxClass(a.severity)">
      <div class="font-semibold">{{ a.message }}</div>
      <div class="mt-1 flex items-center justify-between gap-2">
        <span class="text-[11px] opacity-80">{{ a.action_hint }}</span>
        <RouterLink :to="a.action_path" class="font-bold underline">Open</RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { PlatformAdminOverviewAlert } from '@/types/platformAdminOverview'

defineProps<{ alerts: PlatformAdminOverviewAlert[] }>()

function boxClass(severity: string): string {
  if (severity === 'high') return 'border-red-200 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950/35 dark:text-red-100'
  if (severity === 'medium') return 'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-800 dark:bg-amber-950/35 dark:text-amber-100'
  return 'border-sky-200 bg-sky-50 text-sky-950 dark:border-sky-900 dark:bg-sky-950/30 dark:text-sky-100'
}
</script>
