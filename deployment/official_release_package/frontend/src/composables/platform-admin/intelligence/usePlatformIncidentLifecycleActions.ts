import { computed, reactive } from 'vue'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'
import { PLATFORM_INTELLIGENCE_PERMISSIONS } from '@/types/platform-admin/platformIntelligencePermissionMatrix'

function enc(k: string): string {
  return encodeURIComponent(k)
}

export function usePlatformIncidentLifecycleActions() {
  const auth = useAuthStore()

  async function acknowledge(incidentKey: string): Promise<void> {
    await apiClient.post(`/platform/intelligence/incidents/${enc(incidentKey)}/acknowledge`)
  }

  async function moveUnderReview(incidentKey: string): Promise<void> {
    await apiClient.post(`/platform/intelligence/incidents/${enc(incidentKey)}/move-under-review`)
  }

  async function escalate(incidentKey: string, reason?: string): Promise<void> {
    await apiClient.post(`/platform/intelligence/incidents/${enc(incidentKey)}/escalate`, { reason: reason ?? null })
  }

  async function moveMonitoring(incidentKey: string): Promise<void> {
    await apiClient.post(`/platform/intelligence/incidents/${enc(incidentKey)}/move-monitoring`)
  }

  async function resolve(incidentKey: string, reason: string): Promise<void> {
    await apiClient.post(`/platform/intelligence/incidents/${enc(incidentKey)}/resolve`, { reason })
  }

  async function close(incidentKey: string, reason: string): Promise<void> {
    await apiClient.post(`/platform/intelligence/incidents/${enc(incidentKey)}/close`, { reason })
  }

  async function assignOwner(incidentKey: string, ownerRef: string): Promise<void> {
    await apiClient.post(`/platform/intelligence/incidents/${enc(incidentKey)}/assign-owner`, { owner_ref: ownerRef })
  }

  async function appendNote(incidentKey: string, text: string): Promise<void> {
    await apiClient.post(`/platform/intelligence/incidents/${enc(incidentKey)}/notes`, { text })
  }

  async function materialize(incidentKey: string): Promise<void> {
    await apiClient.post('/platform/intelligence/incidents/materialize', { incident_key: incidentKey })
  }

  return reactive({
    canAck: computed(() => auth.hasPermission(PLATFORM_INTELLIGENCE_PERMISSIONS.incidentsAcknowledge)),
    canAssign: computed(() => auth.hasPermission(PLATFORM_INTELLIGENCE_PERMISSIONS.incidentsAssignOwner)),
    canEscalate: computed(() => auth.hasPermission(PLATFORM_INTELLIGENCE_PERMISSIONS.incidentsEscalate)),
    canResolve: computed(() => auth.hasPermission(PLATFORM_INTELLIGENCE_PERMISSIONS.incidentsResolve)),
    canClose: computed(() => auth.hasPermission(PLATFORM_INTELLIGENCE_PERMISSIONS.incidentsClose)),
    acknowledge,
    moveUnderReview,
    escalate,
    moveMonitoring,
    resolve,
    close,
    assignOwner,
    appendNote,
    materialize,
  })
}
