/** @vitest-environment node */
import { describe, expect, it } from 'vitest'

/** Keep aligned with App\Support\PlatformIntelligence\GuidedWorkflows\GuidedWorkflowKey */
const CANONICAL_WORKFLOW_KEYS = [
  'acknowledge_assign',
  'under_review_decision',
  'escalate_decision',
  'monitor_transition',
  'monitor_with_decision',
  'resolve_closure',
  'close_final',
  'false_positive',
] as const

describe('guided workflow keys (TS parity stub)', () => {
  it('defines eight MVP workflow keys', () => {
    expect(CANONICAL_WORKFLOW_KEYS.length).toBe(8)
  })
})
