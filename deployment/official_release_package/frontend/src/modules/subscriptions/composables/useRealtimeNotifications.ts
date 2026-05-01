import { onBeforeUnmount, onMounted } from 'vue'
import { subscriptionsApi } from '../api'
import { useRealtimeNotificationsStore } from '@/stores/realtimeNotifications'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'

export function useRealtimeNotifications() {
  const store = useRealtimeNotificationsStore()
  const auth = useAuthStore()
  const toast = useToast()
  let pollTimer: number | null = null
  let ws: WebSocket | null = null

  async function pollOnce() {
    if (!auth.isAuthenticated || !auth.isStaff) return
    try {
      const res = await subscriptionsApi.notifications(store.lastId)
      const rows = (res.data?.data ?? []) as Array<any>
      store.pushBatch(rows)
      for (const row of rows) {
        const message = String(row?.payload?.message ?? row?.event_type ?? 'Notification')
        toast.info('تنبيه اشتراك', message)
      }
    } catch {
      // silent fallback
    }
  }

  function startWebSocket() {
    const wsUrl = (import.meta.env.VITE_SUBSCRIPTIONS_WS_URL as string | undefined)?.trim()
    if (!wsUrl) return
    try {
      ws = new WebSocket(wsUrl)
      ws.onmessage = (event) => {
        try {
          const data = JSON.parse(String(event.data ?? '{}'))
          const row = data?.payload?.event_id
            ? {
                id: Number(data.payload.event_id),
                event_type: String(data.type || 'realtime'),
                payload: data.payload || {},
                created_at: new Date().toISOString(),
              }
            : null
          if (row) {
            store.pushBatch([row])
            toast.info('تنبيه اشتراك', String(row.payload.message ?? row.event_type))
          }
        } catch {
          // ignore malformed message
        }
      }
    } catch {
      ws = null
    }
  }

  onMounted(() => {
    if (store.initialized) return
    store.initialized = true
    startWebSocket()
    pollOnce()
    pollTimer = window.setInterval(() => {
      pollOnce()
    }, 30000)
  })

  onBeforeUnmount(() => {
    if (pollTimer !== null) window.clearInterval(pollTimer)
    pollTimer = null
    if (ws) ws.close()
    ws = null
  })
}

