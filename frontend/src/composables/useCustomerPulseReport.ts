import { ref, computed, watch, type ComputedRef, type Ref } from 'vue'

type CustomerIdSource = Ref<number> | ComputedRef<number>
import apiClient from '@/lib/apiClient'
import type { CustomerPulseEnvelope, CustomerPulseSummary } from '@/types/customerPulseReport'
import { previousInclusiveRange } from '@/utils/customerPulseRules'

function ymd(d: Date): string {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

function defaultRangeLast30(): { from: string; to: string } {
  const to = new Date()
  const from = new Date(to)
  from.setDate(from.getDate() - 29)
  return { from: ymd(from), to: ymd(to) }
}

export function useCustomerPulseReport(customerId: CustomerIdSource) {
  const range = ref(defaultRangeLast30())
  const branchId = ref('')
  const loading = ref(false)
  const error = ref<string | null>(null)
  const current = ref<CustomerPulseEnvelope | null>(null)
  const previous = ref<CustomerPulseEnvelope | null>(null)

  const financialIncluded = computed(() => Boolean(current.value?.meta?.financial_metrics_included))

  async function fetchOne(from: string, to: string): Promise<CustomerPulseEnvelope> {
    const params: Record<string, string> = {
      from,
      to,
      customer_id: String(customerId.value),
    }
    if (branchId.value) params.branch_id = branchId.value
    const { data } = await apiClient.get<CustomerPulseEnvelope>('/reporting/v1/customer/pulse-summary', { params })
    return data
  }

  async function reload(): Promise<void> {
    const id = customerId.value
    if (!Number.isFinite(id) || id < 1) return
    loading.value = true
    error.value = null
    try {
      const { from, to } = range.value
      const prev = previousInclusiveRange(from, to)
      const [c, p] = await Promise.all([fetchOne(from, to), fetchOne(prev.from, prev.to)])
      current.value = c
      previous.value = p
    } catch (e: unknown) {
      const msg =
        (e as { response?: { data?: { message?: string } } })?.response?.data?.message ??
        (e as Error)?.message ??
        'Failed to load report'
      error.value = String(msg)
      current.value = null
      previous.value = null
    } finally {
      loading.value = false
    }
  }

  function setRange(from: string, to: string): void {
    range.value = { from, to }
  }

  const prevSummary = computed<CustomerPulseSummary | null>(() => previous.value?.data?.summary ?? null)

  watch(
    () => customerId.value,
    (id) => {
      if (Number.isFinite(id) && id >= 1) void reload()
    },
    { immediate: true },
  )

  watch(branchId, () => {
    void reload()
  })

  return {
    range,
    branchId,
    loading,
    error,
    current,
    previous,
    financialIncluded,
    prevSummary,
    reload,
    setRange,
  }
}
