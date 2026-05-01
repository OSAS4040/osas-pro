/** @vitest-environment node */
import { describe, expect, it } from 'vitest'
import { PLATFORM_DECISION_TYPE, PLATFORM_DECISION_TYPE_LABEL_AR } from './platformIntelligenceEnums'

describe('PLATFORM_DECISION_TYPE_LABEL_AR', () => {
  it('has exactly one label per canonical decision type', () => {
    for (const t of PLATFORM_DECISION_TYPE) {
      expect(PLATFORM_DECISION_TYPE_LABEL_AR[t]).toBeTruthy()
    }
    expect(Object.keys(PLATFORM_DECISION_TYPE_LABEL_AR).length).toBe(PLATFORM_DECISION_TYPE.length)
  })
})
