import { defineStore } from 'pinia'
import { ref } from 'vue'

export interface RealtimeNotificationItem {
  id: number
  event_type: string
  payload: Record<string, unknown>
  created_at: string
}

export const useRealtimeNotificationsStore = defineStore('realtime-notifications', () => {
  const items = ref<RealtimeNotificationItem[]>([])
  const lastId = ref(0)
  const initialized = ref(false)

  function pushBatch(batch: RealtimeNotificationItem[]) {
    if (!batch.length) return
    for (const row of batch) {
      if (!items.value.some((x) => x.id === row.id)) {
        items.value.unshift(row)
      }
      if (row.id > lastId.value) lastId.value = row.id
    }
    if (items.value.length > 200) items.value = items.value.slice(0, 200)
  }

  return { items, lastId, initialized, pushBatch }
})

