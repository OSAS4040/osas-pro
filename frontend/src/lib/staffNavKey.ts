/** يفصل المسار عن الهاش لربط RouterLink بنفس منطق المفاتيح. */
export function splitStaffNavHref(href: string): { path: string; hash: string } {
  const i = href.indexOf('#')
  if (i === -1) {
    return { path: href, hash: '' }
  }
  return { path: href.slice(0, i), hash: href.slice(i) }
}

/**
 * يطابق منطق App\Support\StaffNav\StaffNavKey في الخادم — مفاتيح مستقرة لعناصر القائمة.
 */
export function pathToStaffNavKey(href: string): string {
  const noQuery = (href.split('?', 2)[0] ?? '').trim()
  const hashIdx = noQuery.indexOf('#')
  const pathPart = hashIdx === -1 ? noQuery : noQuery.slice(0, hashIdx)
  const hashRaw = hashIdx === -1 ? '' : noQuery.slice(hashIdx + 1)

  const path = pathPart === '' ? '/' : pathPart

  let slug =
    path === '/'
      ? 'dashboard'
      : path
          .replace(/^\/+|\/+$/g, '')
          .split('/')
          .join('_')
          .replace(/[^a-zA-Z0-9_]+/g, '_')
          .replace(/^_+|_+$/g, '')

  if (hashRaw !== '') {
    const h = hashRaw.replace(/[^a-zA-Z0-9_]+/g, '_').replace(/^_+|_+$/g, '')
    if (h !== '') {
      slug = `${slug}_${h}`
    }
  }

  if (slug === '') {
    slug = 'dashboard'
  }

  return `staff.nav.${slug}`
}

export function pathToCustomerNavKey(href: string): string {
  const path = (href.split('?', 2)[0] ?? '').trim().replace(/^\/customer\/?/i, '')
  const slug =
    path === '' || path === '/'
      ? 'dashboard'
      : path
          .replace(/^\/+|\/+$/g, '')
          .split('/')
          .join('_')
          .replace(/[^a-zA-Z0-9_]+/g, '_')
          .replace(/^_+|_+$/g, '')

  return `customer.nav.${slug === '' ? 'dashboard' : slug}`
}

export function staffNavKeysForRoutePath(path: string, hash: string): string[] {
  const hashPart = hash && hash.startsWith('#') ? hash.slice(1) : hash || ''
  const withHash = path + (hashPart ? `#${hashPart}` : '')
  const keys = new Set<string>()
  keys.add(pathToStaffNavKey(withHash))

  const raw = (path.split('?', 2)[0] ?? '/').trim() || '/'
  const segments = raw.split('/').filter(Boolean)
  for (let len = segments.length; len > 0; len--) {
    const p = `/${segments.slice(0, len).join('/')}`
    keys.add(pathToStaffNavKey(p))
  }
  keys.add(pathToStaffNavKey('/'))
  return [...keys]
}

export function isStaffNavHidden(path: string, hash: string, hidden: Set<string>): boolean {
  return staffNavKeysForRoutePath(path, hash).some((k) => hidden.has(k))
}

export function isCustomerNavHidden(path: string, hidden: Set<string>): boolean {
  const key = pathToCustomerNavKey(path)
  if (hidden.has(key)) return true
  const raw = (path.split('?', 2)[0] ?? '').trim()
  const stripped = raw.replace(/^\/customer\/?/i, '') || '/'
  const segments = stripped.split('/').filter(Boolean)
  for (let len = segments.length; len > 0; len--) {
    const p = `/customer/${segments.slice(0, len).join('/')}`
    if (hidden.has(pathToCustomerNavKey(p))) return true
  }
  if (hidden.has(pathToCustomerNavKey('/customer'))) return true
  return false
}
