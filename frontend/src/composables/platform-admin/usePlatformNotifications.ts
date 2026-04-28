import { computed, ref } from 'vue'
import apiClient from '@/lib/apiClient'
import type { PlatformNotificationItem, PlatformNotificationsResponse } from '@/types/platform-admin/platformNotifications'

const READ_STORAGE_KEY = 'platform_notification_read_state_v1'

function loadReadMap(): Record<string, boolean> {
  try {
    const raw = localStorage.getItem(READ_STORAGE_KEY)
    if (!raw) return {}
    const parsed = JSON.parse(raw) as unknown
    if (!parsed || typeof parsed !== 'object') return {}
    return parsed as Record<string, boolean>
  } catch {
    return {}
  }
}

function saveReadMap(map: Record<string, boolean>): void {
  try {
    localStorage.setItem(READ_STORAGE_KEY, JSON.stringify(map))
  } catch {
    // ignore storage edge cases
  }
}

const readState = ref<Record<string, boolean>>(loadReadMap())

function hydrateReadState(rows: PlatformNotificationItem[]): PlatformNotificationItem[] {
  return rows.map((row) => ({
    ...row,
    is_read: row.is_read || Boolean(readState.value[row.notification_id]),
  }))
}

export function usePlatformNotifications() {
  const loading = ref(false)
  const error = ref<string | null>(null)
  const items = ref<PlatformNotificationItem[]>([])
  const total = ref(0)

  const unreadCount = computed(() => items.value.filter((x) => !x.is_read).length)
  const requiresActionCount = computed(() => items.value.filter((x) => x.requires_action).length)
  const attentionNow = computed(() =>
    [...items.value]
      .sort((a, b) => priorityWeight(String(b.priority)) - priorityWeight(String(a.priority))
        || Date.parse(String(b.created_at)) - Date.parse(String(a.created_at)))
      .slice(0, 8),
  )

  async function fetchNotifications(params?: {
    limit?: number
    category?: string
    requiresAction?: boolean
  }): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const query: Record<string, unknown> = {}
      if (typeof params?.limit === 'number') query.limit = params.limit
      if (params?.category) query.category = params.category
      if (params?.requiresAction === true) query.requires_action = true
      const { data } = await apiClient.get<PlatformNotificationsResponse>('/platform/notifications', {
        params: query,
        skipGlobalErrorToast: true,
      })
      const rows = Array.isArray(data?.data) ? data.data : []
      items.value = hydrateReadState(rows)
      total.value = Number(data?.meta?.total ?? items.value.length)
    } catch (e: unknown) {
      const code = (e as { response?: { status?: number } })?.response?.status
      error.value = code === 403 ? 'ليس لديك صلاحية للوصول' : 'تعذر تحميل مركز التنبيهات والمتابعة'
      items.value = []
      total.value = 0
    } finally {
      loading.value = false
    }
  }

  function markAsRead(notificationId: string): void {
    readState.value = { ...readState.value, [notificationId]: true }
    saveReadMap(readState.value)
    const hit = items.value.find((x) => x.notification_id === notificationId)
    if (hit) hit.is_read = true
  }

  function markAllAsRead(): void {
    const next = { ...readState.value }
    for (const row of items.value) {
      next[row.notification_id] = true
      row.is_read = true
    }
    readState.value = next
    saveReadMap(next)
  }

  return {
    loading,
    error,
    items,
    total,
    unreadCount,
    requiresActionCount,
    attentionNow,
    fetchNotifications,
    markAsRead,
    markAllAsRead,
  }
}

function priorityWeight(priority: string): number {
  switch (priority) {
    case 'critical': return 4
    case 'high': return 3
    case 'medium': return 2
    default: return 1
  }
}

