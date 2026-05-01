import { ref, computed, toValue, type MaybeRefOrGetter } from 'vue'
import apiClient from '@/lib/apiClient'
import type { PlatformIncident, PlatformIncidentTimelineEntry } from '@/types/platform-admin/platformIntelligenceContracts'
import { useAuthStore } from '@/stores/auth'
import { PLATFORM_INTELLIGENCE_PERMISSIONS } from '@/types/platform-admin/platformIntelligencePermissionMatrix'

export function usePlatformIncidentDetail(incidentKey: MaybeRefOrGetter<string>) {
  const auth = useAuthStore()
  const canView = computed(() => auth.hasPermission(PLATFORM_INTELLIGENCE_PERMISSIONS.incidentsRead))

  const incident = ref<PlatformIncident | null>(null)
  const timeline = ref<PlatformIncidentTimelineEntry[]>([])
  const operatorNotes = ref<unknown[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const key = computed(() => encodeURIComponent(toValue(incidentKey)))

  async function fetchDetail(): Promise<void> {
    if (!canView.value) {
      incident.value = null
      timeline.value = []
      return
    }
    loading.value = true
    error.value = null
    try {
      const { data } = await apiClient.get<{
        data: PlatformIncident
        timeline: PlatformIncidentTimelineEntry[]
        operator_notes?: unknown[]
      }>(`/platform/intelligence/incidents/${key.value}`)
      incident.value = data.data ?? null
      timeline.value = Array.isArray(data.timeline) ? data.timeline : []
      operatorNotes.value = Array.isArray(data.operator_notes) ? data.operator_notes : []
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'load_failed'
      incident.value = null
      timeline.value = []
    } finally {
      loading.value = false
    }
  }

  return {
    canView,
    incident,
    timeline,
    operatorNotes,
    loading,
    error,
    fetchDetail,
  }
}
