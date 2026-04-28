<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { usePlatformNotifications } from '@/composables/platform-admin/usePlatformNotifications'
import { usePlatformNotificationCenterAccess } from '@/composables/platform-admin/usePlatformNotificationCenterAccess'
import type { PlatformNotificationItem } from '@/types/platform-admin/platformNotifications'

const { canAccess } = usePlatformNotificationCenterAccess()

const {
  loading,
  error,
  attentionNow,
  fetchNotifications,
  markAsRead,
} = usePlatformNotifications()

const shortList = computed(() => attentionNow.value.slice(0, 8))

function routeTarget(row: PlatformNotificationItem): { path: string; query?: Record<string, string> } {
  const query: Record<string, string> = {}
  for (const [k, v] of Object.entries(row.target_params ?? {})) {
    if (v !== null && v !== undefined && String(v) !== '') query[k] = String(v)
  }
  return Object.keys(query).length > 0 ? { path: row.target_route, query } : { path: row.target_route }
}

function priorityClass(priority: string): string {
  if (priority === 'critical') return 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-200'
  if (priority === 'high') return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200'
  if (priority === 'medium') return 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-200'
  return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
}

function openItem(row: PlatformNotificationItem): void {
  markAsRead(row.notification_id)
}

onMounted(() => {
  if (!canAccess.value) return
  void fetchNotifications({ limit: 40 })
})
</script>

<template>
  <section id="platform-overview-attention-now" class="mb-6 scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3 dark:border-slate-700">
      <div>
        <p class="text-[11px] font-bold tracking-wide text-primary-700 dark:text-primary-300">مركز التنبيهات والمتابعة</p>
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">يحتاج انتباهك الآن</h3>
      </div>
      <RouterLink to="/platform/notifications" class="text-xs font-semibold text-primary-700 hover:underline dark:text-primary-300">
        عرض الكل
      </RouterLink>
    </div>

    <div v-if="!canAccess" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
      ليس لديك صلاحية عرض التنبيهات المجمّعة (يتطلب قراءة الإشعارات أو إدارة اشتراكات المنصة).
    </div>
    <div v-else-if="loading" class="space-y-2">
      <div v-for="n in 4" :key="n" class="h-12 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
    </div>
    <div v-else-if="error" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-200">
      {{ error }}
    </div>
    <div v-else-if="shortList.length === 0" class="rounded-lg border border-dashed border-slate-300 px-3 py-8 text-center text-xs text-slate-500 dark:border-slate-700 dark:text-slate-400">
      لا توجد عناصر تحتاج انتباهًا الآن.
    </div>
    <ul v-else class="space-y-2">
      <li v-for="row in shortList" :key="row.notification_id" class="rounded-lg border border-slate-200/80 px-3 py-2 dark:border-slate-700">
        <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
          <p class="text-xs font-semibold text-slate-900 dark:text-white">{{ row.title }}</p>
          <div class="flex items-center gap-2">
            <span class="rounded px-1.5 py-0.5 text-[10px]" :class="priorityClass(String(row.priority))">{{ row.priority }}</span>
            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ row.notification_type }}</span>
          </div>
        </div>
        <p class="line-clamp-1 text-[11px] text-slate-600 dark:text-slate-300">{{ row.summary }}</p>
        <div class="mt-1 flex items-center justify-between">
          <span class="text-[10px] text-slate-500 dark:text-slate-400">{{ new Date(row.created_at).toLocaleString('ar-SA') }}</span>
          <RouterLink
            :to="routeTarget(row)"
            class="rounded bg-primary-600 px-2 py-1 text-[10px] font-semibold text-white hover:bg-primary-700"
            @click="openItem(row)"
          >
            {{ row.cta_label || 'فتح' }}
          </RouterLink>
        </div>
      </li>
    </ul>
  </section>
</template>

