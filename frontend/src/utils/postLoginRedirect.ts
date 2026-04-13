import type { LoginAccountContextPayload } from '@/types/accountContext'
import { resolvePhoneOnboardingPath } from '@/utils/phoneOnboardingRedirect'

/** Structural match for {@link import('@/stores/auth').RegistrationFlowState} — avoids store import cycle. */
export interface PostLoginRegistrationFlow {
  onboarding_active: boolean
  needs_account_type: boolean
  needs_basic_profile: boolean
  company_pending_review: boolean
  registration_stage?: string | null
  account_type?: string | null
}

/** Safe in-app path only (no scheme, no //, no ..). Returns path without query. */
export function sanitizeInternalPath(input: unknown): string | null {
  if (typeof input !== 'string') return null
  const t = input.trim()
  if (t === '' || !t.startsWith('/') || t.startsWith('//')) return null
  if (t.includes('://') || t.toLowerCase().includes('\\')) return null
  const pathOnly = t.split('?')[0].split('#')[0]
  if (pathOnly.includes('..')) return null
  if (!/^\/[A-Za-z0-9/_-]*$/.test(pathOnly)) return null
  return pathOnly
}

/**
 * Ensure home_route_hint / deep-link cannot send users to the wrong portal.
 */
export function isPathConsistentWithAccountContext(
  path: string,
  ctx: LoginAccountContextPayload | null,
): boolean {
  if (!ctx) return false
  const pk = ctx.principal_kind
  const guard = ctx.guard_hint

  if (pk === 'platform_employee') {
    const cid = ctx.company_id
    if (typeof cid === 'number' && cid > 0) {
      if (path === '/admin' || path.startsWith('/admin/')) {
        return true
      }
      if (path.startsWith('/fleet-portal') || path.startsWith('/customer')) {
        return false
      }

      return true
    }

    return path === '/admin' || path.startsWith('/admin/')
  }
  if (guard === 'onboarding') {
    return path.startsWith('/phone/onboarding')
  }
  if (pk === 'tenant_user') {
    if (path.startsWith('/admin') || path.startsWith('/fleet-portal') || path.startsWith('/customer')) {
      return false
    }
    return true
  }
  if (pk === 'customer_user') {
    return path.startsWith('/fleet-portal') || path.startsWith('/customer')
  }
  if (pk === 'unknown') {
    return path === '/' || path.startsWith('/phone/')
  }
  return path === '/'
}

function fallbackHome(ctx: LoginAccountContextPayload | null, portalHomeFromRole: string): string {
  if (!ctx) return portalHomeFromRole || '/'
  switch (ctx.principal_kind) {
    case 'platform_employee': {
      const cid = ctx.company_id
      if (typeof cid === 'number' && cid > 0) {
        const h = sanitizeInternalPath(ctx.home_route_hint)
        if (h && isPathConsistentWithAccountContext(h, ctx)) {
          return h
        }

        return portalHomeFromRole || '/'
      }

      return '/admin'
    }
    case 'customer_user':
      if (ctx.home_route_hint.startsWith('/fleet-portal')) return '/fleet-portal'
      return '/customer/dashboard'
    case 'tenant_user':
      return portalHomeFromRole || '/'
    case 'unknown':
    default:
      if (ctx.guard_hint === 'onboarding') return '/phone/onboarding'
      return portalHomeFromRole || '/'
  }
}

export function resolvePostLoginTarget(options: {
  accountContext: LoginAccountContextPayload | null
  registrationFlow: PostLoginRegistrationFlow | null
  registrationStage: string | null | undefined
  accountType: string | null | undefined
  portalHomeFromRole: string
  redirectQuery: unknown
}): string {
  const { accountContext, registrationFlow, registrationStage, accountType, portalHomeFromRole, redirectQuery } =
    options

  const fromQuery = sanitizeInternalPath(redirectQuery)
  if (fromQuery && isPathConsistentWithAccountContext(fromQuery, accountContext)) {
    return fromQuery
  }

  if (registrationFlow?.onboarding_active) {
    return resolvePhoneOnboardingPath(registrationFlow, registrationStage, accountType)
  }

  const hint = sanitizeInternalPath(accountContext?.home_route_hint)
  if (hint && isPathConsistentWithAccountContext(hint, accountContext)) {
    return hint
  }

  return fallbackHome(accountContext, portalHomeFromRole)
}
