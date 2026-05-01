import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'
import {
  featureMatrixForBusinessType,
  normalizeBusinessType,
  type BusinessType,
  type FeatureMatrix,
} from '@/config/businessFeatureProfileDefaults'

export type { BusinessType, FeatureMatrix } from '@/config/businessFeatureProfileDefaults'

const INITIAL_EFFECTIVE = featureMatrixForBusinessType('service_center')

function mergeEffectiveMatrix(businessType: unknown, patch: FeatureMatrix | undefined | null): FeatureMatrix {
  const t = normalizeBusinessType(businessType)
  return { ...featureMatrixForBusinessType(t), ...(patch ?? {}) }
}

export const useBusinessProfileStore = defineStore('business-profile', () => {
  const businessType = ref<BusinessType>('service_center')
  const featureMatrix = ref<FeatureMatrix>({})
  const effectiveFeatureMatrix = ref<FeatureMatrix>({ ...INITIAL_EFFECTIVE })
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
      businessType.value = normalizeBusinessType(payload.business_type)
      featureMatrix.value = (payload.feature_matrix ?? {}) as FeatureMatrix
      effectiveFeatureMatrix.value = mergeEffectiveMatrix(payload.business_type, payload.effective_feature_matrix as FeatureMatrix | undefined)
      loaded.value = true
    } catch {
      effectiveFeatureMatrix.value = { ...featureMatrixForBusinessType(businessType.value) }
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
    businessType.value = normalizeBusinessType(payload.business_type ?? type)
    featureMatrix.value = (payload.feature_matrix ?? matrix) as FeatureMatrix
    effectiveFeatureMatrix.value = mergeEffectiveMatrix(
      payload.business_type ?? type,
      (payload.effective_feature_matrix ?? matrix) as FeatureMatrix,
    )
    loaded.value = true
  }

  function reset(): void {
    businessType.value = 'service_center'
    featureMatrix.value = {}
    effectiveFeatureMatrix.value = { ...INITIAL_EFFECTIVE }
    loaded.value = false
  }

  return { businessType, featureMatrix, effectiveFeatureMatrix, loaded, isEnabled, load, save, reset }
})
