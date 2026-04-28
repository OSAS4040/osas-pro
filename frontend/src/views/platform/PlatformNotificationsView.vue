<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { usePlatformNotifications } from '@/composables/platform-admin/usePlatformNotifications'
import { usePlatformNotificationCenterAccess } from '@/composables/platform-admin/usePlatformNotificationCenterAccess'
import type { PlatformNotificationItem } from '@/types/platform-admin/platformNotifications'
import { useToast } from '@/composables/useToast'

const router = useRouter()
const toast = useToast()
const { canAccess } = usePlatformNotificationCenterAccess()
const tab = ref<'all' | 'unread' | 'requires_action'>('all')
const typeFilter = ref<'all' | 'approval' | 'support' | 'operational' | 'financial' | 'follow_up'>('all')

const {
  loading,
  error,
  items,
  unreadCount,
  requiresActionCount,
  fetchNotifications,
  markAsRead,
  markAllAsRead,
} = usePlatformNotifications()

const filtered = computed(() => {
  let list = [...items.value]
  if (tab.value === 'unread') list = list.filter((x) => !x.is_read)
  if (tab.value === 'requires_action') list = list.filter((x) => x.requires_action)
  if (typeFilter.value !== 'all') list = list.filter((x) => x.notification_type === typeFilter.value)
  return list
})

function routeTarget(row: PlatformNotificationItem): { path: string; query?: Record<string, string> } {
  const query: Record<string, string> = {}
  for (const [k, v] of Object.entries(row.target_params ?? {})) {
    if (v !== null && v !== undefined && String(v) !== '') query[k] = String(v)
  }
  return Object.keys(query).length > 0 ? { path: row.target_route, query } : { path: row.target_route }
}

function priorityLabel(priority: string): string {
  switch (priority) {
    case 'critical': return 'حرج'
    case 'high': return 'عالي'
    case 'medium': return 'متوسط'
    default: return 'معلوماتي'
  }
}

function typeLabel(type: string): string {
  const map: Record<string, string> = {
    approval: 'اعتماد',
    support: 'دعم',
    operational: 'تشغيل',
    financial: 'مالي',
    follow_up: 'متابعة',
    decision: 'قرار',
    governance: 'حوكمة',
  }
  return map[type] ?? type
}

async function openItem(row: PlatformNotificationItem): Promise<void> {
  if (!canAccess.value) {
    toast.warning('ليس لديك صلاحية للوصول')
    return
  }
  markAsRead(row.notification_id)
  try {
    await router.push(routeTarget(row))
  } catch {
    toast.error('ليس لديك صلاحية للوصول')
  }
}

onMounted(() => {
  if (!canAccess.value) return
  void fetchNotifications({ limit: 80 })
})
</script>

<template>
  <div class="mx-auto max-w-6xl px-4 py-6" dir="rtl">
    <header class="mb-4 border-b border-slate-200/80 pb-4 dark:border-slate-700">
      <h1 class="text-lg font-semibold text-slate-900 dark:text-white">جميع التنبيهات</h1>
      <p class="mt-1 text-[12px] text-slate-600 dark:text-slate-400">مركز التنبيهات والمتابعة — قراءة واضحة قليلة الضوضاء وروابط مباشرة.</p>
    </header>

    <div class="mb-3 flex flex-wrap items-center gap-2 text-xs">
      <button type="button" class="rounded-lg border px-3 py-1.5" :class="tab === 'all' ? 'border-primary-600 bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-200' : 'border-slate-300 text-slate-700 dark:border-slate-700 dark:text-slate-200'" @click="tab = 'all'">الكل</button>
      <button type="button" class="rounded-lg border px-3 py-1.5" :class="tab === 'unread' ? 'border-primary-600 bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-200' : 'border-slate-300 text-slate-700 dark:border-slate-700 dark:text-slate-200'" @click="tab = 'unread'">غير المقروءة ({{ unreadCount }})</button>
      <button type="button" class="rounded-lg border px-3 py-1.5" :class="tab === 'requires_action' ? 'border-primary-600 bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-200' : 'border-slate-300 text-slate-700 dark:border-slate-700 dark:text-slate-200'" @click="tab = 'requires_action'">تحتاج إجراء ({{ requiresActionCount }})</button>
      <select v-model="typeFilter" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
        <option value="all">كل الأنواع</option>
        <option value="approval">اعتماد</option>
        <option value="support">دعم</option>
        <option value="operational">تشغيل</option>
        <option value="financial">مالي</option>
        <option value="follow_up">متابعة</option>
      </select>
      <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 dark:border-slate-700" @click="markAllAsRead">تحديد الكل كمقروء</button>
    </div>

    <div v-if="!canAccess" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
      ليس لديك صلاحية الوصول إلى مركز التنبيهات (قراءة إشعارات المنصة أو إدارة اشتراكات المنصة).
    </div>
    <div v-else-if="loading" class="space-y-2">
      <div v-for="n in 6" :key="n" class="h-14 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800/70" />
    </div>
    <div v-else-if="error" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-200">
      {{ error }}
    </div>
    <div v-else-if="filtered.length === 0" class="rounded-lg border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
      لا توجد تنبيهات في هذا العرض.
    </div>
    <ul v-else class="space-y-2">
      <li v-for="row in filtered" :key="row.notification_id" class="rounded-xl border border-slate-200/80 bg-white/80 px-3 py-3 dark:border-slate-700 dark:bg-slate-900/30">
        <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-sm font-semibold text-slate-900 dark:text-white">{{ row.title }}</h2>
          <div class="flex items-center gap-2 text-[10px]">
            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ typeLabel(String(row.notification_type)) }}</span>
            <span class="rounded bg-primary-100 px-1.5 py-0.5 text-primary-700 dark:bg-primary-900/30 dark:text-primary-200">{{ priorityLabel(String(row.priority)) }}</span>
          </div>
        </div>
        <p class="text-xs text-slate-600 dark:text-slate-300">{{ row.summary }}</p>
        <div class="mt-2 flex items-center justify-between">
          <span class="text-[10px] text-slate-500 dark:text-slate-400">{{ new Date(row.created_at).toLocaleString('ar-SA') }}</span>
          <button type="button" class="rounded-lg bg-primary-600 px-3 py-1 text-xs font-semibold text-white hover:bg-primary-700" @click="openItem(row)">
            {{ row.cta_label || 'فتح' }}
          </button>
        </div>
      </li>
    </ul>
  </div>
</template>

