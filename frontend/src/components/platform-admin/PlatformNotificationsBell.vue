<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { BellIcon, BellSlashIcon } from '@heroicons/vue/24/outline'
import { usePlatformNotifications } from '@/composables/platform-admin/usePlatformNotifications'
import { usePlatformNotificationCenterAccess } from '@/composables/platform-admin/usePlatformNotificationCenterAccess'
import type { PlatformNotificationItem } from '@/types/platform-admin/platformNotifications'
import { useToast } from '@/composables/useToast'

const { canAccess } = usePlatformNotificationCenterAccess()
const toast = useToast()
const router = useRouter()
const panelOpen = ref(false)
const rootRef = ref<HTMLElement | null>(null)

const {
  loading,
  error,
  items,
  unreadCount,
  fetchNotifications,
  markAsRead,
  markAllAsRead,
} = usePlatformNotifications()

const shortList = computed(() => items.value.slice(0, 10))

function resolveRouteTarget(row: PlatformNotificationItem): { path: string; query?: Record<string, string> } {
  const query: Record<string, string> = {}
  const params = row.target_params ?? {}
  for (const [k, v] of Object.entries(params)) {
    if (v !== null && v !== undefined && String(v) !== '') query[k] = String(v)
  }
  return Object.keys(query).length > 0 ? { path: row.target_route, query } : { path: row.target_route }
}

async function openNotification(row: PlatformNotificationItem): Promise<void> {
  if (!canAccess.value) {
    toast.warning('ليس لديك صلاحية للوصول')
    return
  }
  markAsRead(row.notification_id)
  const target = resolveRouteTarget(row)
  try {
    await router.push(target)
    panelOpen.value = false
  } catch {
    toast.error('ليس لديك صلاحية للوصول')
  }
}

async function refresh(): Promise<void> {
  if (!canAccess.value) return
  await fetchNotifications({ limit: 20 })
}

function onOutsideClick(ev: MouseEvent): void {
  if (rootRef.value && !rootRef.value.contains(ev.target as Node)) {
    panelOpen.value = false
  }
}

onMounted(() => {
  if (canAccess.value) void refresh()
  document.addEventListener('click', onOutsideClick)
})

onUnmounted(() => {
  document.removeEventListener('click', onOutsideClick)
})
</script>

<template>
  <div v-if="canAccess" ref="rootRef" class="relative" dir="rtl">
    <button
      type="button"
      class="relative rounded-lg p-2 text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
      title="التنبيهات"
      aria-label="التنبيهات"
      @click="panelOpen = !panelOpen"
    >
      <BellIcon class="h-5 w-5" />
      <span
        v-if="unreadCount > 0"
        class="absolute -end-0.5 -top-0.5 min-w-[18px] rounded-full bg-rose-600 px-1 text-center text-[10px] font-bold leading-4 text-white"
      >
        {{ unreadCount > 99 ? '99+' : unreadCount }}
      </span>
    </button>

    <div
      v-if="panelOpen"
      class="absolute end-0 top-full z-40 mt-2 w-96 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900"
    >
      <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-slate-700">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">التنبيهات</h3>
        <div class="flex items-center gap-3">
          <button
            type="button"
            class="text-xs font-semibold text-primary-700 hover:underline dark:text-primary-300"
            @click="markAllAsRead"
          >
            تحديد كمقروء
          </button>
          <RouterLink to="/platform/notifications" class="text-xs font-semibold text-primary-700 hover:underline dark:text-primary-300" @click="panelOpen = false">
            جميع التنبيهات
          </RouterLink>
        </div>
      </div>

      <div class="max-h-96 overflow-y-auto">
        <div v-if="loading" class="px-4 py-6 text-center text-xs text-slate-500 dark:text-slate-400">
          جاري التحميل...
        </div>
        <div v-else-if="error" class="px-4 py-6 text-center text-xs text-amber-700 dark:text-amber-300">
          {{ error }}
        </div>
        <div v-else-if="shortList.length === 0" class="px-4 py-8 text-center text-xs text-slate-500 dark:text-slate-400">
          <BellSlashIcon class="mx-auto mb-2 h-6 w-6 opacity-50" />
          لا توجد تنبيهات
        </div>
        <button
          v-for="row in shortList"
          :key="row.notification_id"
          type="button"
          class="w-full border-b border-slate-100 px-4 py-3 text-start transition hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800"
          :class="!row.is_read ? 'bg-primary-50/40 dark:bg-primary-900/10' : ''"
          @click="openNotification(row)"
        >
          <div class="mb-1 flex items-center justify-between gap-2">
            <p class="text-xs font-semibold text-slate-900 dark:text-white">{{ row.title }}</p>
            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-600 dark:bg-slate-800 dark:text-slate-300">
              {{ row.cta_label || 'فتح' }}
            </span>
          </div>
          <p class="line-clamp-2 text-[11px] text-slate-600 dark:text-slate-300">{{ row.summary }}</p>
        </button>
      </div>
    </div>
  </div>
</template>

