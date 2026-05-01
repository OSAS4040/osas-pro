import type { GlobalFeedItem } from '@/types/globalOperationsFeed'

export type FeedDayGroup = 'today' | 'yesterday' | 'earlier'

function startOfLocalDay(d: Date): number {
  return new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime()
}

/** Groups feed items by local calendar day relative to `now` (for timeline UI). */
export function groupOperationsFeedByDay(items: GlobalFeedItem[], now = new Date()): Record<FeedDayGroup, GlobalFeedItem[]> {
  const todayStart = startOfLocalDay(now)
  const yesterdayStart = todayStart - 86400000
  const groups: Record<FeedDayGroup, GlobalFeedItem[]> = { today: [], yesterday: [], earlier: [] }
  for (const it of items) {
    if (!it.occurred_at) {
      groups.earlier.push(it)
      continue
    }
    const t = startOfLocalDay(new Date(it.occurred_at))
    if (t === todayStart) groups.today.push(it)
    else if (t === yesterdayStart) groups.yesterday.push(it)
    else groups.earlier.push(it)
  }
  return groups
}
