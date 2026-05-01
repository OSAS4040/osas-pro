import { ref } from 'vue'
import apiClient from '@/lib/apiClient'
import type { CustomerProfilePayload, CustomerProfileResponse } from '@/types/customerProfile'

export function useCustomerProfile(customerId: () => number) {
  const loading = ref(false)
  const error = ref<string | null>(null)
  const payload = ref<CustomerProfilePayload | null>(null)
  const financialIncluded = ref(false)

  async function load(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const id = customerId()
      const { data } = await apiClient.get<CustomerProfileResponse>(`/customers/${id}/profile`)
      payload.value = data.data
      financialIncluded.value = Boolean(data.meta?.financial_metrics_included)
    } catch (e: unknown) {
      payload.value = null
      const status = (e as { response?: { status?: number } })?.response?.status
      if (status === 403) {
        error.value = 'Customer is outside your branch scope.'
      } else if (status === 404) {
        error.value = 'Customer not found.'
      } else {
        error.value =
          (e as { response?: { data?: { message?: string } } })?.response?.data?.message ??
          (e as Error)?.message ??
          'Failed to load customer profile'
      }
    } finally {
      loading.value = false
    }
  }

  return { loading, error, payload, financialIncluded, load }
}
