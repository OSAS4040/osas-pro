/** @vitest-environment node */
import { describe, expect, it } from 'vitest'
import {
  assertIncidentStatusTransitionAllowed,
  incidentStatusTransitionEdges,
  isIncidentStatusTransitionAllowed,
} from './incidentStatusTransitionPolicy'

describe('incidentStatusTransitionPolicy', () => {
  it('allows the documented backbone path', () => {
    expect(isIncidentStatusTransitionAllowed('open', 'acknowledged')).toBe(true)
    expect(isIncidentStatusTransitionAllowed('acknowledged', 'under_review')).toBe(true)
    expect(isIncidentStatusTransitionAllowed('under_review', 'monitoring')).toBe(true)
    expect(isIncidentStatusTransitionAllowed('monitoring', 'resolved')).toBe(true)
    expect(isIncidentStatusTransitionAllowed('resolved', 'closed')).toBe(true)
  })

  it('allows escalation branch per policy', () => {
    expect(isIncidentStatusTransitionAllowed('under_review', 'escalated')).toBe(true)
    expect(isIncidentStatusTransitionAllowed('escalated', 'monitoring')).toBe(true)
    expect(isIncidentStatusTransitionAllowed('escalated', 'resolved')).toBe(true)
  })

  it('rejects undocumented jumps', () => {
    expect(isIncidentStatusTransitionAllowed('open', 'closed')).toBe(false)
    expect(isIncidentStatusTransitionAllowed('acknowledged', 'resolved')).toBe(false)
  })

  it('assert throws on invalid transition', () => {
    expect(() => assertIncidentStatusTransitionAllowed('open', 'resolved')).toThrow(/not allowed/)
  })

  it('edge list stays aligned with backend policy count', () => {
    expect(incidentStatusTransitionEdges().length).toBe(8)
  })
})
