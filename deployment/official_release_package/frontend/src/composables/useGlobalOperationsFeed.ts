import { ref, computed } from 'vue'
import apiClient from '@/lib/apiClient'
import type { GlobalFeedEnvelope, GlobalFeedItem } from '@/types/globalOperationsFeed'
import type { OperationalIntelligencePayload } from '@/types/operationalIntelligence'

function ymd(d: Date): string {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

export function useGlobalOperationsFeed() {
  const from = ref(ymd(new Date(Date.now() - 86400000 * 6)))
  const to = ref(ymd(new Date()))
  const branchId = ref('')
  const customerId = ref('')
  const userId = ref('')
  const types = ref<string[]>([])
  const attentionLevel = ref('')
  const includeFinancial = ref(true)
  const page = ref(1)
  const perPage = ref(25)

  const loading = ref(false)
  const error = ref<string | null>(null)
  const envelope = ref<GlobalFeedEnvelope | null>(null)

  const items = computed<GlobalFeedItem[]>(() => envelope.value?.data?.items ?? [])
  const summary = computed(() => envelope.value?.data?.summary ?? null)
  const intelligence = computed<OperationalIntelligencePayload | null>(() => envelope.value?.data?.intelligence ?? null)
  const pagination = computed(() => envelope.value?.meta?.pagination ?? null)
  const financialIncluded = computed(() => Boolean(envelope.value?.meta?.financial_metrics_included))

  async function fetchFeed(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const params: Record<string, string | number | boolean | string[] | undefined> = {
        from: from.value,
        to: to.value,
        page: page.value,
        per_page: perPage.value,
        include_financial: includeFinancial.value,
      }
      if (branchId.value) params.branch_id = Number(branchId.value)
      if (customerId.value) params.customer_id = Number(customerId.value)
      if (userId.value) params.user_id = Number(userId.value)
      if (attentionLevel.value) params.attention_level = attentionLevel.value
      if (types.value.length) {
        params.types = types.value
      }
      const { data } = await apiClient.get<GlobalFeedEnvelope>('/reporting/v1/operations/global-feed', { params })
      envelope.value = data
    } catch (e: unknown) {
      envelope.value = null
      error.value =
        (e as { response?: { data?: { message?: string } } })?.response?.data?.message ??
        (e as Error)?.message ??
        'Failed to load feed'
    } finally {
      loading.value = false
    }
  }

  function resetFilters(): void {
    branchId.value = ''
    customerId.value = ''
    userId.value = ''
    types.value = []
    attentionLevel.value = ''
    includeFinancial.value = true
    page.value = 1
  }

  return {
    from,
    to,
    branchId,
    customerId,
    userId,
    types,
    attentionLevel,
    includeFinancial,
    page,
    perPage,
    loading,
    error,
    envelope,
    items,
    summary,
    intelligence,
    pagination,
    financialIncluded,
    fetchFeed,
    resetFilters,
  }
}
