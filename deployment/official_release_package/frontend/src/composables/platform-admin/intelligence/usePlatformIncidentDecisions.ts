import { ref, computed, toValue, type MaybeRefOrGetter } from 'vue'
import apiClient from '@/lib/apiClient'
import type { PlatformDecisionLogEntry } from '@/types/platform-admin/platformIntelligenceContracts'
import { useAuthStore } from '@/stores/auth'
import { PLATFORM_INTELLIGENCE_PERMISSIONS } from '@/types/platform-admin/platformIntelligencePermissionMatrix'

export interface CreateDecisionPayload {
  decision_type: string
  decision_summary: string
  rationale: string
  expected_outcome?: string
  evidence_refs?: string[]
  linked_notes?: string[]
  linked_signals?: string[]
  follow_up_required?: boolean
}

export function usePlatformIncidentDecisions(incidentKey: MaybeRefOrGetter<string>) {
  const auth = useAuthStore()
  const canView = computed(() => auth.hasPermission(PLATFORM_INTELLIGENCE_PERMISSIONS.decisionsRead))
  const canAdd = computed(() => auth.hasPermission(PLATFORM_INTELLIGENCE_PERMISSIONS.decisionsWrite))

  const entries = ref<PlatformDecisionLogEntry[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const submitting = ref(false)

  const keyEnc = computed(() => encodeURIComponent(toValue(incidentKey)))

  async function fetchDecisions(): Promise<void> {
    if (!canView.value) {
      entries.value = []
      return
    }
    loading.value = true
    error.value = null
    try {
      const { data } = await apiClient.get<{ data: PlatformDecisionLogEntry[] }>(
        `/platform/intelligence/incidents/${keyEnc.value}/decisions?per_page=100`,
      )
      entries.value = Array.isArray(data.data) ? data.data : []
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'decisions_load_failed'
      entries.value = []
    } finally {
      loading.value = false
    }
  }

  async function createEntry(payload: CreateDecisionPayload): Promise<void> {
    if (!canAdd.value) {
      throw new Error('forbidden')
    }
    submitting.value = true
    error.value = null
    try {
      await apiClient.post(`/platform/intelligence/incidents/${keyEnc.value}/decisions`, payload)
      await fetchDecisions()
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'decision_create_failed'
      throw e
    } finally {
      submitting.value = false
    }
  }

  return {
    canView,
    canAdd,
    entries,
    loading,
    error,
    submitting,
    fetchDecisions,
    createEntry,
  }
}
