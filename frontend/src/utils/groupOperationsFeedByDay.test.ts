import { describe, expect, it } from 'vitest'
import { groupOperationsFeedByDay } from './groupOperationsFeedByDay'
import type { GlobalFeedItem } from '@/types/globalOperationsFeed'

function item(id: number, occurred: string): GlobalFeedItem {
  return {
    type: 'invoice',
    id,
    occurred_at: occurred,
    title: 't',
    subtitle: 's',
    description: null,
    status: 'open',
    severity: 'normal',
    attention_level: 'normal',
    company_id: 1,
    company_name: 'C',
    branch_id: null,
    branch_name: null,
    customer_id: null,
    customer_name: null,
    actor_user_id: null,
    actor_name: null,
    amount: null,
    currency: null,
    reference: '',
    entity_route: null,
    tags: [],
    financial_visibility_applied: false,
    read_only: true,
  }
}

describe('groupOperationsFeedByDay', () => {
  /** Local date-time strings (no `Z`) parse as local time — stable grouping per machine TZ. */
  const anchor = new Date(2026, 3, 12, 12, 0, 0)

  it('splits today, yesterday, earlier', () => {
    const items = [
      item(1, '2026-04-12T08:00:00'),
      item(2, '2026-04-11T10:00:00'),
      item(3, '2026-04-01T10:00:00'),
    ]
    const g = groupOperationsFeedByDay(items, anchor)
    expect(g.today.map((x) => x.id)).toEqual([1])
    expect(g.yesterday.map((x) => x.id)).toEqual([2])
    expect(g.earlier.map((x) => x.id)).toEqual([3])
  })

  it('puts missing occurred_at in earlier', () => {
    const i = item(9, '')
    i.occurred_at = null
    const g = groupOperationsFeedByDay([i], anchor)
    expect(g.earlier.map((x) => x.id)).toEqual([9])
  })
})
