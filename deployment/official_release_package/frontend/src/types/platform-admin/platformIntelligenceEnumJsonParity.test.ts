/** @vitest-environment node */
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
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

function loadFixture(): Record<string, string[]> {
  const p = resolve(process.cwd(), '../backend/tests/fixtures/platform_intelligence_canonical_enum_values.json')
  const raw = readFileSync(p, 'utf8')
  return JSON.parse(raw) as Record<string, string[]>
}

describe('platform intelligence enum parity (JSON fixture)', () => {
  it('TypeScript const arrays match backend canonical JSON', () => {
    const j = loadFixture()
    expect([...PLATFORM_INTELLIGENCE_SEVERITY]).toEqual(j.severity)
    expect([...PLATFORM_INCIDENT_STATUS]).toEqual(j.incident_status)
    expect([...PLATFORM_INCIDENT_OWNERSHIP_STATE]).toEqual(j.ownership_state)
    expect([...PLATFORM_INCIDENT_ESCALATION_STATE]).toEqual(j.escalation_state)
    expect([...PLATFORM_DECISION_TYPE]).toEqual(j.decision_type)
    expect([...PLATFORM_SIGNAL_SOURCE_TYPE]).toEqual(j.signal_source_type)
    expect([...PLATFORM_SIGNAL_TYPE]).toEqual(j.signal_type)
  })
})
