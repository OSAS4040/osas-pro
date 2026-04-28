import { ref, computed } from 'vue'
import apiClient from '@/lib/apiClient'
import type { PlatformIncident } from '@/types/platform-admin/platformIntelligenceContracts'
import { useAuthStore } from '@/stores/auth'
import { PLATFORM_INTELLIGENCE_PERMISSIONS } from '@/types/platform-admin/platformIntelligencePermissionMatrix'

export function usePlatformIntelligenceIncidents() {
  const auth = useAuthStore()
  const canView = computed(() => auth.hasPermission(PLATFORM_INTELLIGENCE_PERMISSIONS.incidentsRead))
  const canMaterialize = computed(() => auth.hasPermission(PLATFORM_INTELLIGENCE_PERMISSIONS.incidentsMaterialize))

  const incidents = ref<PlatformIncident[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const statusFilter = ref<string | ''>('')
  const severityFilter = ref<string | ''>('')
  const escalationFilter = ref<string | ''>('')
  const typeFilter = ref<string | ''>('')
  const ownerFilter = ref<string | ''>('')
  const companyFilter = ref<string | ''>('')
  const freshHours = ref<string | ''>('')

  const sorted = computed(() => {
    const rows = [...incidents.value]
    rows.sort((a, b) => {
      const sev = (x: PlatformIncident) =>
        ({ critical: 5, high: 4, medium: 3, low: 2, info: 1 }[x.severity] ?? 0)
      const d = sev(b) - sev(a)
      if (d !== 0) return d
      const ta = a.last_status_change_at ? new Date(a.last_status_change_at).getTime() : 0
      const tb = b.last_status_change_at ? new Date(b.last_status_change_at).getTime() : 0
      if (tb !== ta) return tb - ta
      return a.incident_key.localeCompare(b.incident_key)
    })
    return rows
  })

  async function fetchIncidents(): Promise<void> {
    if (!canView.value) {
      incidents.value = []
      return
    }
    loading.value = true
    error.value = null
    try {
      const params: Record<string, string> = {}
      if (statusFilter.value) params.status = statusFilter.value
      if (severityFilter.value) params.severity = severityFilter.value
      if (escalationFilter.value) params.escalation_state = escalationFilter.value
      if (typeFilter.value) params.incident_type = typeFilter.value
      if (ownerFilter.value) params.owner = ownerFilter.value
      if (companyFilter.value) params.company_id = companyFilter.value
      if (freshHours.value) params.fresh_hours = freshHours.value

      const { data } = await apiClient.get<{ data: PlatformIncident[] }>('/platform/intelligence/incidents', { params })
      incidents.value = Array.isArray(data.data) ? data.data : []
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'load_failed'
      incidents.value = []
    } finally {
      loading.value = false
    }
  }

  return {
    canView,
    canMaterialize,
    incidents,
    sorted,
    loading,
    error,
    statusFilter,
    severityFilter,
    escalationFilter,
    typeFilter,
    ownerFilter,
    companyFilter,
    freshHours,
    fetchIncidents,
  }
}
