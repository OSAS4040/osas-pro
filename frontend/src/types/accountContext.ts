/**
 * Mirrors backend {@see \App\Support\Auth\LoginAccountContext::toArray()} (WAVE 1 / PR2–PR4).
 * Clients must branch on principal_kind, guard_hint, home_route_hint — not on human message text.
 */
export interface LoginAccountContextPayload {
  principal_kind: string
  user_id: number
  company_id: number | null
  customer_id: number | null
  home_route_hint: string
  guard_hint: string
  role: string
  requires_context_selection: boolean
  display_context: Record<string, unknown>
  /** IAM role for platform_employee; null for tenant/fleet flows */
  platform_role?: string | null
}

export function parseLoginAccountContext(raw: unknown): LoginAccountContextPayload | null {
  if (raw === null || typeof raw !== 'object') return null
  const o = raw as Record<string, unknown>
  const principal = typeof o.principal_kind === 'string' ? o.principal_kind : ''
  const home = typeof o.home_route_hint === 'string' ? o.home_route_hint : ''
  const guard = typeof o.guard_hint === 'string' ? o.guard_hint : ''
  const role = typeof o.role === 'string' ? o.role : ''
  const platformRole =
    o.platform_role === null || o.platform_role === undefined
      ? null
      : typeof o.platform_role === 'string'
        ? o.platform_role
        : null
  if (principal === '' || home === '' || guard === '') return null
  const userId = Number(o.user_id)
  if (!Number.isFinite(userId)) return null

  return {
    principal_kind: principal,
    user_id: userId,
    company_id: o.company_id === null || o.company_id === undefined ? null : Number(o.company_id),
    customer_id: o.customer_id === null || o.customer_id === undefined ? null : Number(o.customer_id),
    home_route_hint: home,
    guard_hint: guard,
    role,
    requires_context_selection: Boolean(o.requires_context_selection),
    display_context:
      o.display_context !== null && typeof o.display_context === 'object' && !Array.isArray(o.display_context)
        ? (o.display_context as Record<string, unknown>)
        : {},
    platform_role: platformRole,
  }
}
