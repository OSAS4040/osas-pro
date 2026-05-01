/**
 * Structural contracts for read models / API parity — not runtime validators.
 * Naming distinguishes: Signal vs IncidentCandidate vs Incident vs DecisionLogEntry.
 */
import type {
  PlatformDecisionType,
  PlatformIncidentEscalationState,
  PlatformIncidentOwnershipState,
  PlatformIncidentStatus,
  PlatformIntelligenceSeverity,
  PlatformSignalSourceType,
  PlatformSignalType,
} from './platformIntelligenceEnums'

export interface PlatformSignal {
  signal_key: string
  signal_type: PlatformSignalType
  title: string
  summary: string
  why_summary: string
  severity: PlatformIntelligenceSeverity
  confidence: number
  source: PlatformSignalSourceType
  source_ref: string | null
  affected_scope: string
  affected_entities: string[]
  affected_companies: Array<number | string>
  first_seen_at: string
  last_seen_at: string
  recommended_next_step: string
  correlation_keys: string[]
  trace_id: string | null
  correlation_id: string | null
}

export interface PlatformIncidentCandidate {
  incident_key: string
  incident_type: string
  title: string
  summary: string
  why_summary: string
  severity: PlatformIntelligenceSeverity
  confidence: number
  source_signals: string[]
  affected_scope: string
  affected_entities: string[]
  affected_companies: Array<number | string>
  first_seen_at: string
  last_seen_at: string
  recommended_actions: string[]
  grouping_reason: string
  dedupe_fingerprint: string
}

export interface PlatformIncident {
  incident_key: string
  incident_type: string
  title: string
  summary: string
  why_summary: string
  severity: PlatformIntelligenceSeverity
  confidence: number
  status: PlatformIncidentStatus
  owner: string | null
  ownership_state: PlatformIncidentOwnershipState
  escalation_state: PlatformIncidentEscalationState
  affected_scope: string
  affected_entities: string[]
  affected_companies: Array<number | string>
  source_signals: string[]
  recommended_actions: string[]
  first_seen_at: string
  last_seen_at: string
  acknowledged_at: string | null
  resolved_at: string | null
  closed_at: string | null
  last_status_change_at: string | null
  resolve_reason: string | null
  close_reason: string | null
}

export interface PlatformIncidentTimelineEntry {
  id: number
  event_type: string
  prior_status: string | null
  next_status: string | null
  prior_escalation_state: string | null
  next_escalation_state: string | null
  prior_owner: string | null
  next_owner: string | null
  reason: string | null
  actor_user_id: number | null
  created_at: string | null
}

export interface PlatformDecisionLogEntry {
  decision_id: string
  incident_key: string
  decision_type: PlatformDecisionType
  decision_summary: string
  rationale: string
  actor: string
  created_at: string
  linked_signals: string[]
  linked_notes: string[]
  expected_outcome: string
  evidence_refs: string[]
  follow_up_required: boolean
}
