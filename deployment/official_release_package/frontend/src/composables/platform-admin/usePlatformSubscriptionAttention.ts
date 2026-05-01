import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { subscriptionsApi } from '@/modules/subscriptions/api'

const POLL_MS = 45_000

export interface UsePlatformSubscriptionAttentionOptions {
  /** عند true يبدأ التحديث الدوري تلقائياً (مثلاً في شريط إدارة المنصة). */
  autoPoll?: boolean
}

export function usePlatformSubscriptionAttention(options: UsePlatformSubscriptionAttentionOptions = {}) {
  const { autoPoll = false } = options
  const auth = useAuthStore()
  const summary = ref<{
    awaiting_review: number
    matched_pending_final_approval: number
    pending_transfer_with_submission: number
    total_attention: number
  } | null>(null)
  const loading = ref(false)
  let timer: ReturnType<typeof setInterval> | null = null

  const canSee = computed(() => auth.hasPermission('platform.subscription.manage'))

  const totalAttention = computed(() => Number(summary.value?.total_attention ?? 0))

  const badgeCount = computed(() => {
    if (!canSee.value) return 0
    return totalAttention.value
  })

  async function refresh(): Promise<void> {
    if (!canSee.value) {
      summary.value = null
      return
    }
    loading.value = true
    try {
      const { data } = await subscriptionsApi.adminSubscriptionAttentionSummary()
      summary.value = (data?.data as typeof summary.value) ?? null
    } catch {
      summary.value = null
    } finally {
      loading.value = false
    }
  }

  function startPolling(): void {
    void refresh()
    if (timer !== null) clearInterval(timer)
    timer = setInterval(() => void refresh(), POLL_MS)
  }

  function stopPolling(): void {
    if (timer !== null) {
      clearInterval(timer)
      timer = null
    }
  }

  if (autoPoll) {
    onMounted(() => {
      if (canSee.value) startPolling()
    })
    onUnmounted(() => {
      stopPolling()
    })
  }

  return {
    summary,
    loading,
    canSee,
    totalAttention,
    badgeCount,
    refresh,
    startPolling,
    stopPolling,
  }
}
