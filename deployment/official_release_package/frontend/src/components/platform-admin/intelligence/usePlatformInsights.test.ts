import { describe, expect, it } from 'vitest'
import {
  buildPlatformFinanceInsights,
  computeFinanceMetrics,
} from './usePlatformInsights'
import type { PlatformCompanyLike } from './usePlatformInsights'

describe('buildPlatformFinanceInsights', () => {
  it('returns empty for empty companies', () => {
    expect(buildPlatformFinanceInsights([], null)).toEqual([])
  })

  it('flags pending review backlog', () => {
    const companies: PlatformCompanyLike[] = [
      { id: 1, name: 'أ', subscription_status: 'active', financial_model_status: 'pending_platform_review', monthly_revenue: 100 },
      { id: 2, name: 'ب', subscription_status: 'active', financial_model_status: 'pending_platform_review', monthly_revenue: 100 },
    ]
    const insights = buildPlatformFinanceInsights(companies, null)
    expect(insights.some((i) => i.id === 'pending-review-backlog')).toBe(true)
    expect(insights.find((i) => i.id === 'pending-review-backlog')?.recommendations.length).toBeGreaterThan(0)
  })

  it('detects overdue-like spike vs snapshot', () => {
    const prev = { overdueLikeCount: 5, mrrActive: 5000, activeSubscriptions: 10, savedAt: '2020-01-01T00:00:00.000Z' }
    const companies: PlatformCompanyLike[] = Array.from({ length: 7 }, (_, i) => ({
      id: i + 1,
      name: `م${i}`,
      subscription_status: 'grace_period',
      financial_model_status: 'approved_prepaid',
      monthly_revenue: 0,
      updated_at: new Date().toISOString(),
    }))
    const insights = buildPlatformFinanceInsights(companies, prev)
    expect(insights.some((i) => i.id === 'overdue-like-spike')).toBe(true)
  })

  it('detects chronic grace from updated_at', () => {
    const stale = new Date(Date.now() - 15 * 86400000).toISOString()
    const companies: PlatformCompanyLike[] = [
      { id: 1, name: 'مشترك قديم', subscription_status: 'grace_period', financial_model_status: 'approved_prepaid', monthly_revenue: 0, updated_at: stale },
    ]
    const insights = buildPlatformFinanceInsights(companies, null)
    expect(insights.some((i) => i.id === 'high-risk-grace-stale')).toBe(true)
  })

  it('computeFinanceMetrics counts active MRR', () => {
    const m = computeFinanceMetrics([
      { id: 1, subscription_status: 'active', company_status: 'active', monthly_revenue: 299 },
      { id: 2, subscription_status: 'grace_period', company_status: 'active', monthly_revenue: 0 },
    ])
    expect(m.activeSubscriptions).toBe(1)
    expect(m.mrrActive).toBe(299)
    expect(m.overdueLikeCount).toBe(1)
  })
})
