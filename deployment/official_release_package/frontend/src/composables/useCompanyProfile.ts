import { ref } from 'vue'
import apiClient from '@/lib/apiClient'
import type { CompanyProfilePayload, CompanyProfileResponse } from '@/types/companyProfile'

export function useCompanyProfile(companyId: () => number) {
  const loading = ref(false)
  const error = ref<string | null>(null)
  const payload = ref<CompanyProfilePayload | null>(null)
  const financialIncluded = ref(false)

  async function load(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const id = companyId()
      const { data } = await apiClient.get<CompanyProfileResponse>(`/companies/${id}/profile`)
      payload.value = data.data
      financialIncluded.value = Boolean(data.meta?.financial_metrics_included)
    } catch (e: unknown) {
      payload.value = null
      error.value =
        (e as { response?: { data?: { message?: string }; status?: number } })?.response?.data?.message ??
        (e as Error)?.message ??
        'Failed to load company profile'
    } finally {
      loading.value = false
    }
  }

  return { loading, error, payload, financialIncluded, load }
}
