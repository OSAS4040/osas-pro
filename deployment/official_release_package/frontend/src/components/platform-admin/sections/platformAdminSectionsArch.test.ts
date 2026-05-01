import { readdirSync, readFileSync } from 'node:fs'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

const sectionsDir = dirname(fileURLToPath(import.meta.url))

/** Phase 4 — لا side effects داخل أقسام العرض: بلا API ولا composables تنقل/تنبيه/حافظة. */
const FORBIDDEN = [
  /\bapiClient\b/,
  /\buseRouter\s*\(/,
  /\buseToast\s*\(/,
  /navigator\.clipboard/,
] as const

describe('platform-admin sections architecture', () => {
  it('Vue/TS sources under sections/ omit forbidden side-effect patterns', () => {
    const files = readdirSync(sectionsDir).filter(
      (f) => (f.endsWith('.vue') || f.endsWith('.ts')) && !f.endsWith('.test.ts'),
    )
    expect(files.length).toBeGreaterThan(0)
    for (const f of files) {
      const content = readFileSync(join(sectionsDir, f), 'utf8')
      for (const pattern of FORBIDDEN) {
        expect.soft(content, `${f} must not match ${pattern}`).not.toMatch(pattern)
      }
    }
  })
})
