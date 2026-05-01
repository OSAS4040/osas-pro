import type { InjectionKey, Ref } from 'vue'

export type PlatformSubscriptionAttentionInject = {
  badgeCount: Readonly<Ref<number>>
  summary: Ref<{
    awaiting_review: number
    matched_pending_final_approval: number
    pending_transfer_with_submission: number
    total_attention: number
  } | null>
  refresh: () => Promise<void>
}

export const platformSubscriptionAttentionKey: InjectionKey<PlatformSubscriptionAttentionInject> = Symbol('platformSubscriptionAttention')
