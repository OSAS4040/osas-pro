/** @vitest-environment happy-dom */
import { describe, expect, it } from 'vitest'
import router from '@/router/index'

describe('platform intelligence routing (Incident Center MVP)', () => {
  it('registers incident center routes but not early decision log UI', () => {
    const paths = router.getRoutes().map((x) => x.path).join('\n')
    expect(paths).toMatch(/intelligence\/incidents/)
    expect(paths).toMatch(/intelligence\/command/)
    expect(paths).not.toMatch(/intelligence\/decisions/i)
  })
})
