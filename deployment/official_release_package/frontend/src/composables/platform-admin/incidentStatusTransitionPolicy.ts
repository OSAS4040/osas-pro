import type { PlatformIncidentStatus } from '@/types/platform-admin/platformIntelligenceEnums'

/** Directed edges — mirror backend PlatformIncidentStatusTransitionPolicy::allowedDirectedEdges */
const EDGES: ReadonlyArray<readonly [PlatformIncidentStatus, PlatformIncidentStatus]> = [
  ['open', 'acknowledged'],
  ['acknowledged', 'under_review'],
  ['under_review', 'escalated'],
  ['under_review', 'monitoring'],
  ['escalated', 'monitoring'],
  ['escalated', 'resolved'],
  ['monitoring', 'resolved'],
  ['resolved', 'closed'],
]

export function isIncidentStatusTransitionAllowed(
  from: PlatformIncidentStatus,
  to: PlatformIncidentStatus,
): boolean {
  if (from === to) return true
  return EDGES.some(([a, b]) => a === from && b === to)
}

export function assertIncidentStatusTransitionAllowed(
  from: PlatformIncidentStatus,
  to: PlatformIncidentStatus,
): void {
  if (!isIncidentStatusTransitionAllowed(from, to)) {
    throw new Error(`Incident status transition not allowed: ${from} -> ${to}`)
  }
}

export function allowedIncidentStatusTargets(from: PlatformIncidentStatus): PlatformIncidentStatus[] {
  const out = new Set<PlatformIncidentStatus>([from])
  for (const [a, b] of EDGES) {
    if (a === from) out.add(b)
  }
  return [...out]
}

export function incidentStatusTransitionEdges(): ReadonlyArray<readonly [PlatformIncidentStatus, PlatformIncidentStatus]> {
  return EDGES
}
