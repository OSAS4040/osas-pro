import { describe, expect, it } from 'vitest'
import { resolveViteApiProxyTarget } from './resolveViteApiProxyTarget'

describe('resolveViteApiProxyTarget', () => {
  it('outside Docker: empty file + empty proc falls back to host nginx (:80)', () => {
    expect(resolveViteApiProxyTarget(false, {}, {})).toBe('http://127.0.0.1')
  })

  it('outside Docker: file wins over proc', () => {
    expect(
      resolveViteApiProxyTarget(
        false,
        { VITE_DEV_PROXY_TARGET: 'http://127.0.0.1:9000' },
        { VITE_DEV_PROXY_TARGET: 'http://wrong:1' },
      ),
    ).toBe('http://127.0.0.1:9000')
  })

  it('inside Docker: proc wins over file (compose injects nginx)', () => {
    expect(
      resolveViteApiProxyTarget(
        true,
        { VITE_DEV_PROXY_TARGET: 'http://127.0.0.1:8000' },
        { VITE_DEV_PROXY_TARGET: 'http://nginx' },
      ),
    ).toBe('http://nginx')
  })

  it('inside Docker: localhost in file is rewritten to nginx', () => {
    expect(
      resolveViteApiProxyTarget(true, { VITE_DEV_PROXY_TARGET: 'http://127.0.0.1:8000' }, {}),
    ).toBe('http://nginx')
  })

  it('inside Docker: host.docker.internal is preserved', () => {
    expect(
      resolveViteApiProxyTarget(
        true,
        {},
        { VITE_DEV_PROXY_TARGET: 'http://host.docker.internal:8000' },
      ),
    ).toBe('http://host.docker.internal:8000')
  })
})
