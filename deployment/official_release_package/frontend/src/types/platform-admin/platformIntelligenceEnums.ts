/**
 * Canonical enums for Platform Intelligence Operations — keep aligned with
 * backend/app/Support/PlatformIntelligence/Enums (PHP).
 */

export const PLATFORM_INTELLIGENCE_SEVERITY = ['info', 'low', 'medium', 'high', 'critical'] as const
export type PlatformIntelligenceSeverity = (typeof PLATFORM_INTELLIGENCE_SEVERITY)[number]

export const PLATFORM_INCIDENT_STATUS = [
  'open',
  'acknowledged',
  'under_review',
  'escalated',
  'monitoring',
  'resolved',
  'closed',
] as const
export type PlatformIncidentStatus = (typeof PLATFORM_INCIDENT_STATUS)[number]

export const PLATFORM_INCIDENT_OWNERSHIP_STATE = ['unassigned', 'assigned', 'reassigned'] as const
export type PlatformIncidentOwnershipState = (typeof PLATFORM_INCIDENT_OWNERSHIP_STATE)[number]

export const PLATFORM_INCIDENT_ESCALATION_STATE = ['none', 'pending', 'escalated', 'contained'] as const
export type PlatformIncidentEscalationState = (typeof PLATFORM_INCIDENT_ESCALATION_STATE)[number]

export const PLATFORM_DECISION_TYPE = [
  'observation',
  'escalation',
  'false_positive',
  'monitor',
  'closure',
  'action_approved',
] as const
export type PlatformDecisionType = (typeof PLATFORM_DECISION_TYPE)[number]

/** UI labels (AR) — must stay aligned with {@link PLATFORM_DECISION_TYPE}. */
export const PLATFORM_DECISION_TYPE_LABEL_AR: Record<PlatformDecisionType, string> = {
  observation: 'ملاحظة',
  escalation: 'تصعيد مؤسسي',
  false_positive: 'إيجابية خاطئة',
  monitor: 'مراقبة',
  closure: 'إغلاق مؤسسي',
  action_approved: 'موافقة على إجراء (توثيق فقط)',
}

export const PLATFORM_SIGNAL_SOURCE_TYPE = [
  'finance',
  'operations',
  'adoption',
  'compliance',
  'integrations',
  'governance',
  'intelligence',
  'system',
] as const
export type PlatformSignalSourceType = (typeof PLATFORM_SIGNAL_SOURCE_TYPE)[number]

export const PLATFORM_SIGNAL_TYPE = [
  'metric_threshold',
  'trend',
  'anomaly',
  'rule',
  'manual',
  'correlation',
  'composite',
] as const
export type PlatformSignalType = (typeof PLATFORM_SIGNAL_TYPE)[number]

export const PLATFORM_INTELLIGENCE_CAPABILITY = [
  'view_signals',
  'view_incident_candidates',
  'view_incidents',
  'view_decision_log',
  'acknowledge_incident',
  'assign_incident_owner',
  'escalate_incident',
  'resolve_incident',
  'close_incident',
  'add_decision_entry',
  'execute_guided_workflows',
] as const
export type PlatformIntelligenceCapability = (typeof PLATFORM_INTELLIGENCE_CAPABILITY)[number]
