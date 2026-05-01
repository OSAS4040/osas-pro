// @vitest-environment happy-dom
import { describe, expect, it } from 'vitest'

describe('customer portal views', () => {
  it('loads customer pricing view module', async () => {
    const mod = await import('./CustomerPricingView.vue')
    expect(mod.default).toBeDefined()
  })
})
