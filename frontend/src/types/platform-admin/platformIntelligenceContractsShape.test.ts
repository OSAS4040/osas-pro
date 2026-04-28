/** @vitest-environment node */
import { describe, expect, it } from 'vitest'
import type {
  PlatformDecisionLogEntry,
  PlatformIncident,
  PlatformIncidentCandidate,
  PlatformSignal,
} from './platformIntelligenceContracts'

const REQUIRED: Record<string, string[]> = {
  PlatformSignal: [
    'signal_key',
    'signal_type',
    'title',
    'summary',
    'why_summary',
    'severity',
    'confidence',
    'source',
    'source_ref',
    'affected_scope',
    'affected_entities',
    'affected_companies',
    'first_seen_at',
    'last_seen_at',
    'recommended_next_step',
    'correlation_keys',
    'trace_id',
    'correlation_id',
  ],
  PlatformIncidentCandidate: [
    'incident_key',
    'incident_type',
    'title',
    'summary',
    'why_summary',
    'severity',
    'confidence',
    'source_signals',
    'affected_scope',
    'affected_entities',
    'affected_companies',
    'first_seen_at',
    'last_seen_at',
    'recommended_actions',
    'grouping_reason',
    'dedupe_fingerprint',
  ],
  PlatformIncident: [
    'incident_key',
    'incident_type',
    'title',
    'summary',
    'why_summary',
    'severity',
    'confidence',
    'status',
    'owner',
    'ownership_state',
    'escalation_state',
    'affected_scope',
    'affected_entities',
    'affected_companies',
    'source_signals',
    'recommended_actions',
    'first_seen_at',
    'last_seen_at',
    'acknowledged_at',
    'resolved_at',
    'closed_at',
    'last_status_change_at',
    'resolve_reason',
    'close_reason',
  ],
  PlatformDecisionLogEntry: [
    'decision_id',
    'incident_key',
    'decision_type',
    'decision_summary',
    'rationale',
    'actor',
    'created_at',
    'linked_signals',
    'linked_notes',
    'expected_outcome',
    'evidence_refs',
    'follow_up_required',
  ],
}

function assertShape<T extends object>(label: keyof typeof REQUIRED, obj: T): void {
  const keys = REQUIRED[label]
  for (const k of keys) {
    expect(Object.prototype.hasOwnProperty.call(obj, k), `${label} missing ${k}`).toBe(true)
  }
}

describe('platform intelligence contract shapes', () => {
  it('accepts fully-populated structural samples', () => {
    const signal = {
      signal_key: 's',
      signal_type: 'rule',
      title: 't',
      summary: 'su',
      why_summary: 'w',
      severity: 'low',
      confidence: 1,
      source: 'operations',
      source_ref: null,
      affected_scope: 'x',
      affected_entities: [],
      affected_companies: [],
      first_seen_at: '2026-01-01T00:00:00Z',
      last_seen_at: '2026-01-01T00:00:00Z',
      recommended_next_step: 'n',
      correlation_keys: [],
      trace_id: null,
      correlation_id: null,
    } satisfies PlatformSignal
    assertShape('PlatformSignal', signal)

    const candidate = {
      incident_key: 'c',
      incident_type: 't',
      title: 't',
      summary: 's',
      why_summary: 'w',
      severity: 'medium',
      confidence: 0.5,
      source_signals: [],
      affected_scope: 'a',
      affected_entities: [],
      affected_companies: [],
      first_seen_at: '2026-01-01T00:00:00Z',
      last_seen_at: '2026-01-01T00:00:00Z',
      recommended_actions: [],
      grouping_reason: 'g',
      dedupe_fingerprint: 'f',
    } satisfies PlatformIncidentCandidate
    assertShape('PlatformIncidentCandidate', candidate)

    const incident = {
      incident_key: 'i',
      incident_type: 't',
      title: 't',
      summary: 's',
      why_summary: 'w',
      severity: 'high',
      confidence: 0.9,
      status: 'open',
      owner: null,
      ownership_state: 'unassigned',
      escalation_state: 'none',
      affected_scope: 'a',
      affected_entities: [],
      affected_companies: [],
      source_signals: [],
      recommended_actions: [],
      first_seen_at: '2026-01-01T00:00:00Z',
      last_seen_at: '2026-01-01T00:00:00Z',
      acknowledged_at: null,
      resolved_at: null,
      closed_at: null,
      last_status_change_at: null,
      resolve_reason: null,
      close_reason: null,
    } satisfies PlatformIncident
    assertShape('PlatformIncident', incident)

    const decision = {
      decision_id: 'd',
      incident_key: 'i',
      decision_type: 'observation',
      decision_summary: 'x',
      rationale: 'r',
      actor: 'u',
      created_at: '2026-01-01T00:00:00Z',
      linked_signals: [],
      linked_notes: [],
      expected_outcome: 'e',
      evidence_refs: [],
      follow_up_required: false,
    } satisfies PlatformDecisionLogEntry
    assertShape('PlatformDecisionLogEntry', decision)
  })
})
