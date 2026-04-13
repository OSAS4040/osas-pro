/**
 * Mirrors backend config/domain_mapping.php `entities.*.canonical` for future UI.
 * Route paths are unchanged; this is label/generalization metadata only.
 */
export const DOMAIN_MAP: Record<string, string> = {
  vehicle: 'asset',
  work_order: 'job',
  customer: 'account',
  invoice: 'invoice',
  workshop: 'business_unit',
  fleet: 'fleet',
}

export const useCanonicalDomainLabels = (): boolean =>
  import.meta.env.VITE_DOMAIN_USE_CANONICAL_LABELS === 'true'
