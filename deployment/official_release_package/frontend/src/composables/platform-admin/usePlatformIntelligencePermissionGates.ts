import { computed, type ComputedRef } from 'vue'
import { useAuthStore } from '@/stores/auth'
import type { PlatformIntelligenceCapability } from '@/types/platform-admin/platformIntelligenceEnums'
import { platformIntelligencePermissionForCapability } from '@/types/platform-admin/platformIntelligencePermissionMatrix'

/**
 * Logical gates for intelligence capabilities — use before any future mutation or navigation.
 */
export function usePlatformIntelligencePermissionGates(): {
  gate: (cap: PlatformIntelligenceCapability) => boolean
  gates: ComputedRef<Record<PlatformIntelligenceCapability, boolean>>
} {
  const auth = useAuthStore()

  function gate(cap: PlatformIntelligenceCapability): boolean {
    return auth.hasPermission(platformIntelligencePermissionForCapability(cap))
  }

  const gates = computed(() => ({
    view_signals: gate('view_signals'),
    view_incident_candidates: gate('view_incident_candidates'),
    view_incidents: gate('view_incidents'),
    view_decision_log: gate('view_decision_log'),
    acknowledge_incident: gate('acknowledge_incident'),
    assign_incident_owner: gate('assign_incident_owner'),
    escalate_incident: gate('escalate_incident'),
    resolve_incident: gate('resolve_incident'),
    close_incident: gate('close_incident'),
    add_decision_entry: gate('add_decision_entry'),
    execute_guided_workflows: gate('execute_guided_workflows'),
  }))

  return { gate, gates }
}
