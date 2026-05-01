/** @vitest-environment happy-dom */
import { describe, expect, it, beforeEach, vi } from 'vitest'

describe('usePlatformWelcomeHint', () => {
  beforeEach(() => {
    localStorage.clear()
    sessionStorage.clear()
    vi.resetModules()
  })

  it('shows strip when storage empty', async () => {
    const { usePlatformWelcomeHint } = await import('./usePlatformWelcomeHint')
    const { showWelcomeStrip } = usePlatformWelcomeHint()
    expect(showWelcomeStrip.value).toBe(true)
  })

  it('hides strip when localStorage dismissed', async () => {
    localStorage.setItem('verdent:platformWelcomeHintDismissed', '1')
    vi.resetModules()
    const { usePlatformWelcomeHint } = await import('./usePlatformWelcomeHint')
    const { showWelcomeStrip } = usePlatformWelcomeHint()
    expect(showWelcomeStrip.value).toBe(false)
  })
})
