import { ref, computed, toValue, type MaybeRefOrGetter } from 'vue'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'
import { PLATFORM_INTELLIGENCE_PERMISSIONS } from '@/types/platform-admin/platformIntelligencePermissionMatrix'

function newIdempotencyKey(): string {
  if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
    return crypto.randomUUID()
  }
  return `${Date.now()}-${Math.random().toString(16).slice(2)}`
}

export interface GuidedWorkflowCatalogRow {
  workflow_key: string
  workflow_type: string
  label: string
  description: string
  preview: string
  available: boolean
  unavailable_reason: string | null
  requires_owner_ref: boolean
  requires_rationale: boolean
  requires_decision_summary: boolean
  requires_expected_outcome: boolean
}

export function usePlatformGuidedWorkflows(incidentKey: MaybeRefOrGetter<string>) {
  const auth = useAuthStore()
  const canViewCatalog = computed(() => auth.hasPermission(PLATFORM_INTELLIGENCE_PERMISSIONS.incidentsRead))
  const canExecute = computed(() => auth.hasPermission(PLATFORM_INTELLIGENCE_PERMISSIONS.guidedWorkflowsExecute))

  const catalog = ref<GuidedWorkflowCatalogRow[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const executingKey = ref<string | null>(null)

  const keyEnc = computed(() => encodeURIComponent(toValue(incidentKey)))

  async function fetchCatalog(): Promise<void> {
    if (!canViewCatalog.value) {
      catalog.value = []
      return
    }
    loading.value = true
    error.value = null
    try {
      const { data } = await apiClient.get<{ data: GuidedWorkflowCatalogRow[] }>(
        `/platform/intelligence/incidents/${keyEnc.value}/workflows`,
      )
      catalog.value = Array.isArray(data.data) ? data.data : []
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'workflows_load_failed'
      catalog.value = []
    } finally {
      loading.value = false
    }
  }

  async function executeWorkflow(payload: Record<string, unknown> & { workflow_key: string }): Promise<void> {
    if (!canExecute.value) {
      throw new Error('forbidden')
    }
    const body = {
      ...payload,
      confirm: true,
      idempotency_key: newIdempotencyKey(),
    }
    executingKey.value = payload.workflow_key
    error.value = null
    try {
      await apiClient.post(`/platform/intelligence/incidents/${keyEnc.value}/workflows/execute`, body)
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'workflow_execute_failed'
      throw e
    } finally {
      executingKey.value = null
    }
  }

  return {
    canViewCatalog,
    canExecute,
    catalog,
    loading,
    error,
    executingKey,
    fetchCatalog,
    executeWorkflow,
  }
}
