/** @vitest-environment node */
import { describe, expect, it } from 'vitest'
import {
  PLATFORM_DECISION_TYPE,
  PLATFORM_INCIDENT_ESCALATION_STATE,
  PLATFORM_INCIDENT_OWNERSHIP_STATE,
  PLATFORM_INCIDENT_STATUS,
  PLATFORM_INTELLIGENCE_SEVERITY,
  PLATFORM_SIGNAL_SOURCE_TYPE,
  PLATFORM_SIGNAL_TYPE,
} from './platformIntelligenceEnums'

describe('platform intelligence enums (integrity)', () => {
  it('matches canonical backend string sets', () => {
    expect([...PLATFORM_INTELLIGENCE_SEVERITY]).toEqual(['info', 'low', 'medium', 'high', 'critical'])
    expect([...PLATFORM_INCIDENT_STATUS]).toEqual([
      'open',
      'acknowledged',
      'under_review',
      'escalated',
      'monitoring',
      'resolved',
      'closed',
    ])
    expect([...PLATFORM_INCIDENT_OWNERSHIP_STATE]).toEqual(['unassigned', 'assigned', 'reassigned'])
    expect([...PLATFORM_INCIDENT_ESCALATION_STATE]).toEqual(['none', 'pending', 'escalated', 'contained'])
    expect([...PLATFORM_DECISION_TYPE]).toEqual([
      'observation',
      'escalation',
      'false_positive',
      'monitor',
      'closure',
      'action_approved',
    ])
    expect([...PLATFORM_SIGNAL_SOURCE_TYPE]).toEqual([
      'finance',
      'operations',
      'adoption',
      'compliance',
      'integrations',
      'governance',
      'intelligence',
      'system',
    ])
    expect([...PLATFORM_SIGNAL_TYPE]).toEqual([
      'metric_threshold',
      'trend',
      'anomaly',
      'rule',
      'manual',
      'correlation',
      'composite',
    ])
  })
})
