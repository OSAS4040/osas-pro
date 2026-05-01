import { ref, computed } from 'vue'
import apiClient from '@/lib/apiClient'
import type { PlatformIncidentCandidate } from '@/types/platform-admin/platformIntelligenceContracts'
import { usePlatformIntelligencePermissionGates } from '@/composables/platform-admin/usePlatformIntelligencePermissionGates'

export function usePlatformIntelligenceCandidates() {
  const { gate } = usePlatformIntelligencePermissionGates()
  const canLoad = computed(() => gate('view_incident_candidates'))

  const candidates = ref<PlatformIncidentCandidate[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const severityFilter = ref<string | ''>('')

  const filtered = computed(() => {
    const f = severityFilter.value
    if (!f) return candidates.value
    return candidates.value.filter((c) => c.severity === f)
  })

  async function fetchCandidates(): Promise<void> {
    if (!canLoad.value) {
      candidates.value = []
      return
    }
    loading.value = true
    error.value = null
    try {
      const { data } = await apiClient.get<{ data: PlatformIncidentCandidate[] }>(
        '/platform/intelligence/incident-candidates',
      )
      candidates.value = Array.isArray(data.data) ? data.data : []
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'load_failed'
      candidates.value = []
    } finally {
      loading.value = false
    }
  }

  return {
    canLoad,
    candidates,
    filtered,
    loading,
    error,
    severityFilter,
    fetchCandidates,
  }
}
