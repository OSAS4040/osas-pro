/** @vitest-environment node */
import { existsSync, readFileSync } from 'node:fs'
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

function canonicalFixturePath(): string {
  const name = 'platform_intelligence_canonical_enum_values.json'
  const candidates = [
    '/fixtures/backend-tests/' + name,
    resolve(process.cwd(), '../backend/tests/fixtures/' + name),
    resolve(process.cwd(), '../../backend/tests/fixtures/' + name),
  ]
  for (const p of candidates) {
    if (existsSync(p)) return p
  }
  throw new Error(`Fixture not found (Vitest/Docker). Tried:\n${candidates.join('\n')}`)
}

function loadFixture(): Record<string, string[]> {
  const raw = readFileSync(canonicalFixturePath(), 'utf8')
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
