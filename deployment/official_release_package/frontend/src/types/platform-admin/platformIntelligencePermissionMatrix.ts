import type { PlatformIntelligenceCapability } from './platformIntelligenceEnums'

/** IAM keys — must match backend PlatformOperatorPermissionMatrix. */
export const PLATFORM_INTELLIGENCE_PERMISSIONS = {
  notificationsRead: 'platform.notifications.read',
  signalsRead: 'platform.intelligence.signals.read',
  candidatesRead: 'platform.intelligence.candidates.read',
  incidentsRead: 'platform.intelligence.incidents.read',
  incidentsMaterialize: 'platform.intelligence.incidents.materialize',
  decisionsRead: 'platform.intelligence.decisions.read',
  incidentsAcknowledge: 'platform.intelligence.incidents.acknowledge',
  incidentsAssignOwner: 'platform.intelligence.incidents.assign_owner',
  incidentsEscalate: 'platform.intelligence.incidents.escalate',
  incidentsResolve: 'platform.intelligence.incidents.resolve',
  incidentsClose: 'platform.intelligence.incidents.close',
  decisionsWrite: 'platform.intelligence.decisions.write',
  guidedWorkflowsExecute: 'platform.intelligence.guided_workflows.execute',
  controlledActionsView: 'platform.intelligence.controlled_actions.view',
  controlledActionsCreateFollowUp: 'platform.intelligence.controlled_actions.create_follow_up',
  controlledActionsRequestHumanReview: 'platform.intelligence.controlled_actions.request_human_review',
  controlledActionsLinkTaskReference: 'platform.intelligence.controlled_actions.link_task_reference',
  controlledActionsAssignOwner: 'platform.intelligence.controlled_actions.assign_owner',
  controlledActionsSchedule: 'platform.intelligence.controlled_actions.schedule',
  controlledActionsComplete: 'platform.intelligence.controlled_actions.complete',
  controlledActionsCancel: 'platform.intelligence.controlled_actions.cancel',
  controlledActionsReopen: 'platform.intelligence.controlled_actions.reopen',
} as const

const CAP_TO_PERM: Record<PlatformIntelligenceCapability, string> = {
  view_signals: PLATFORM_INTELLIGENCE_PERMISSIONS.signalsRead,
  view_incident_candidates: PLATFORM_INTELLIGENCE_PERMISSIONS.candidatesRead,
  view_incidents: PLATFORM_INTELLIGENCE_PERMISSIONS.incidentsRead,
  view_decision_log: PLATFORM_INTELLIGENCE_PERMISSIONS.decisionsRead,
  acknowledge_incident: PLATFORM_INTELLIGENCE_PERMISSIONS.incidentsAcknowledge,
  assign_incident_owner: PLATFORM_INTELLIGENCE_PERMISSIONS.incidentsAssignOwner,
  escalate_incident: PLATFORM_INTELLIGENCE_PERMISSIONS.incidentsEscalate,
  resolve_incident: PLATFORM_INTELLIGENCE_PERMISSIONS.incidentsResolve,
  close_incident: PLATFORM_INTELLIGENCE_PERMISSIONS.incidentsClose,
  add_decision_entry: PLATFORM_INTELLIGENCE_PERMISSIONS.decisionsWrite,
  execute_guided_workflows: PLATFORM_INTELLIGENCE_PERMISSIONS.guidedWorkflowsExecute,
}

export function platformIntelligencePermissionForCapability(cap: PlatformIntelligenceCapability): string {
  return CAP_TO_PERM[cap]
}
