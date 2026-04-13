import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'

export type BusinessType = 'service_center' | 'retail' | 'fleet_operator'
export type FeatureMatrix = Record<string, boolean>

const FALLBACK: FeatureMatrix = {
  operations: true,
  hr: true,
  finance: true,
  accounting: true,
  inventory: true,
  reports: true,
  intelligence: true,
  crm: true,
  fleet: true,
  org_structure: true,
  supplier_contract_mgmt: true,
}

export const useBusinessProfileStore = defineStore('business-profile', () => {
  const businessType = ref<BusinessType>('service_center')
  const featureMatrix = ref<FeatureMatrix>({})
  const effectiveFeatureMatrix = ref<FeatureMatrix>({ ...FALLBACK })
  const loaded = ref(false)

  function isEnabled(key: string): boolean {
    return effectiveFeatureMatrix.value[key] !== false
  }

  async function load(): Promise<void> {
    if (loaded.value) return
    const auth = useAuthStore()
    if (!auth.user?.company_id) return
    try {
      const { data } = await apiClient.get(`/companies/${auth.user.company_id}/feature-profile`)
      const payload = data.data ?? {}
      businessType.value = payload.business_type ?? 'service_center'
      featureMatrix.value = payload.feature_matrix ?? {}
      effectiveFeatureMatrix.value = { ...FALLBACK, ...(payload.effective_feature_matrix ?? {}) }
      loaded.value = true
    } catch {
      effectiveFeatureMatrix.value = { ...FALLBACK }
    }
  }

  async function save(type: BusinessType, matrix: FeatureMatrix): Promise<void> {
    const auth = useAuthStore()
    if (!auth.user?.company_id) return
    const { data } = await apiClient.patch(`/companies/${auth.user.company_id}/feature-profile`, {
      business_type: type,
      feature_matrix: matrix,
    })
    const payload = data.data ?? {}
    businessType.value = payload.business_type ?? type
    featureMatrix.value = payload.feature_matrix ?? matrix
    effectiveFeatureMatrix.value = { ...FALLBACK, ...(payload.effective_feature_matrix ?? matrix) }
    loaded.value = true
  }

  function reset(): void {
    businessType.value = 'service_center'
    featureMatrix.value = {}
    effectiveFeatureMatrix.value = { ...FALLBACK }
    loaded.value = false
  }

  return { businessType, featureMatrix, effectiveFeatureMatrix, loaded, isEnabled, load, save, reset }
})
