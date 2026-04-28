import { ref, computed, toValue, type MaybeRefOrGetter } from 'vue'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'
import { PLATFORM_INTELLIGENCE_PERMISSIONS } from '@/types/platform-admin/platformIntelligencePermissionMatrix'

export function usePlatformIncidentCorrelation(incidentKey: MaybeRefOrGetter<string>) {
  const auth = useAuthStore()
  const canView = computed(() => auth.hasPermission(PLATFORM_INTELLIGENCE_PERMISSIONS.incidentsRead))

  const bundle = ref<Record<string, unknown> | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  const keyEnc = computed(() => encodeURIComponent(toValue(incidentKey)))

  async function fetchCorrelation(): Promise<void> {
    if (!canView.value) {
      bundle.value = null
      return
    }
    loading.value = true
    error.value = null
    try {
      const { data } = await apiClient.get<{ data: Record<string, unknown> }>(
        `/platform/intelligence/incidents/${keyEnc.value}/correlation`,
      )
      bundle.value = (data.data as Record<string, unknown>) ?? null
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'correlation_failed'
      bundle.value = null
    } finally {
      loading.value = false
    }
  }

  return { canView, bundle, loading, error, fetchCorrelation }
}
