import { ref, computed, toValue, type MaybeRefOrGetter } from 'vue'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'
import { PLATFORM_INTELLIGENCE_PERMISSIONS } from '@/types/platform-admin/platformIntelligencePermissionMatrix'

export interface PlatformControlledActionRow {
  action_id: string
  incident_key: string
  action_type: string
  action_summary: string
  actor: string
  created_at: string
  status: string
  assigned_owner: string | null
  follow_up_required: boolean
  scheduled_for: string | null
  linked_decision_id: string | null
  linked_notes: string | null
  external_reference: string | null
  completion_reason: string | null
  canceled_reason: string | null
}

export function usePlatformControlledActions(incidentKey: MaybeRefOrGetter<string>) {
  const auth = useAuthStore()

  const canView = computed(
    () =>
      auth.hasPermission(PLATFORM_INTELLIGENCE_PERMISSIONS.incidentsRead) &&
      auth.hasPermission(PLATFORM_INTELLIGENCE_PERMISSIONS.controlledActionsView),
  )

  const canCreateFollowUp = computed(
    () => canView.value && auth.hasPermission(PLATFORM_INTELLIGENCE_PERMISSIONS.controlledActionsCreateFollowUp),
  )
  const canRequestHumanReview = computed(
    () => canView.value && auth.hasPermission(PLATFORM_INTELLIGENCE_PERMISSIONS.controlledActionsRequestHumanReview),
  )
  const canLinkTask = computed(
    () => canView.value && auth.hasPermission(PLATFORM_INTELLIGENCE_PERMISSIONS.controlledActionsLinkTaskReference),
  )
  const canAssign = computed(() => auth.hasPermission(PLATFORM_INTELLIGENCE_PERMISSIONS.controlledActionsAssignOwner))
  const canSchedule = computed(() => auth.hasPermission(PLATFORM_INTELLIGENCE_PERMISSIONS.controlledActionsSchedule))
  const canComplete = computed(() => auth.hasPermission(PLATFORM_INTELLIGENCE_PERMISSIONS.controlledActionsComplete))
  const canCancel = computed(() => auth.hasPermission(PLATFORM_INTELLIGENCE_PERMISSIONS.controlledActionsCancel))
  const canReopen = computed(() => auth.hasPermission(PLATFORM_INTELLIGENCE_PERMISSIONS.controlledActionsReopen))

  const items = ref<PlatformControlledActionRow[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const keyEnc = computed(() => encodeURIComponent(toValue(incidentKey)))

  async function fetchList(): Promise<void> {
    if (!canView.value) {
      items.value = []
      return
    }
    loading.value = true
    error.value = null
    try {
      const { data } = await apiClient.get<{ data: PlatformControlledActionRow[] }>(
        `/platform/intelligence/incidents/${keyEnc.value}/controlled-actions`,
      )
      items.value = Array.isArray(data.data) ? data.data : []
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'controlled_actions_load_failed'
      items.value = []
    } finally {
      loading.value = false
    }
  }

  async function createFollowUp(summary: string, idempotencyKey?: string): Promise<void> {
    await apiClient.post(`/platform/intelligence/incidents/${keyEnc.value}/controlled-actions/create-follow-up`, {
      action_summary: summary,
      idempotency_key: idempotencyKey || undefined,
    })
    await fetchList()
  }

  async function requestHumanReview(summary: string): Promise<void> {
    await apiClient.post(`/platform/intelligence/incidents/${keyEnc.value}/controlled-actions/request-human-review`, {
      action_summary: summary,
    })
    await fetchList()
  }

  async function linkTaskReference(externalRef: string, summary?: string): Promise<void> {
    await apiClient.post(`/platform/intelligence/incidents/${keyEnc.value}/controlled-actions/link-internal-task-reference`, {
      external_reference: externalRef,
      action_summary: summary,
    })
    await fetchList()
  }

  async function assignOwner(actionId: string, owner: string): Promise<void> {
    await apiClient.post(`/platform/intelligence/controlled-actions/${encodeURIComponent(actionId)}/assign-owner`, {
      assigned_owner: owner,
    })
    await fetchList()
  }

  async function scheduleAction(actionId: string, iso: string): Promise<void> {
    await apiClient.post(`/platform/intelligence/controlled-actions/${encodeURIComponent(actionId)}/schedule-follow-up-window`, {
      scheduled_for: iso,
    })
    await fetchList()
  }

  async function completeAction(actionId: string, reason: string): Promise<void> {
    await apiClient.post(`/platform/intelligence/controlled-actions/${encodeURIComponent(actionId)}/mark-completed`, {
      completion_reason: reason,
    })
    await fetchList()
  }

  async function cancelAction(actionId: string, reason: string): Promise<void> {
    await apiClient.post(`/platform/intelligence/controlled-actions/${encodeURIComponent(actionId)}/cancel`, {
      canceled_reason: reason,
    })
    await fetchList()
  }

  async function reopenAction(actionId: string): Promise<void> {
    await apiClient.post(`/platform/intelligence/controlled-actions/${encodeURIComponent(actionId)}/reopen`, {})
    await fetchList()
  }

  return {
    canView,
    canCreateFollowUp,
    canRequestHumanReview,
    canLinkTask,
    canAssign,
    canSchedule,
    canComplete,
    canCancel,
    canReopen,
    items,
    loading,
    error,
    fetchList,
    createFollowUp,
    requestHumanReview,
    linkTaskReference,
    assignOwner,
    scheduleAction,
    completeAction,
    cancelAction,
    reopenAction,
  }
}
