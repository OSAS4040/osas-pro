export interface PlatformAdminOverviewKpis {
  total_companies: number
  active_companies: number
  low_activity_companies: number
  inactive_companies: number
  trial_companies: number
  churn_risk_companies: number
  total_users: number
  subscriptions_active: number
  estimated_mrr: number
  companies_new_7d: number
  companies_new_30d: number
}

export interface PlatformAdminOverviewTrendPoint {
  date: string
  count?: number
  activity_score?: number
  work_orders?: number
  invoices?: number
  logins?: number
  status_mix?: Record<string, number>
}

export interface PlatformAdminOverviewHealth {
  api: 'ok' | 'degraded' | string
  queue: 'ok' | 'degraded' | string
  failed_jobs: number | null
  trend: 'stable' | 'degraded' | string
  database_ok: boolean
}

export interface PlatformAdminOverviewAlert {
  type: string
  severity: 'high' | 'medium' | 'low' | string
  company_id?: number
  message: string
  action_hint?: string
  action_path: string
}

export interface PlatformAdminAttentionCompany {
  company_id: number
  name: string
  reason: string
  reasons?: string[]
  activity_score?: number
  last_activity_days_ago?: number
  is_active: boolean
  company_status?: string | null
  financial_model_status?: string | null
  updated_at?: string | null
  action_hint?: string
  action_path: string
}

export interface PlatformAdminInsight {
  tone: string
  text: string
}

/** صف من ذكاء النشاط (أعلى/أقل الشركات) — يطابق PlatformAdminOverviewService */
export interface PlatformAdminActivityRow {
  company_id: number
  company_name: string
  activity_score: number
  last_activity_days_ago: number
}

export interface PlatformAdminOverviewPayload {
  generated_at: string
  cache?: { ttl_seconds?: number }
  definitions: Record<string, string>
  kpis: PlatformAdminOverviewKpis
  trends: {
    companies_growth: PlatformAdminOverviewTrendPoint[]
    activity_trend: PlatformAdminOverviewTrendPoint[]
    subscription_trend: PlatformAdminOverviewTrendPoint[]
  }
  distribution: {
    by_plan: Record<string, number>
    by_status: Record<string, number>
  }
  activity: {
    most_active_companies: PlatformAdminActivityRow[]
    least_active_companies: PlatformAdminActivityRow[]
    avg_activity_score: number
  }
  alerts: PlatformAdminOverviewAlert[]
  companies_requiring_attention: PlatformAdminAttentionCompany[]
  health: PlatformAdminOverviewHealth
  insights: PlatformAdminInsight[]
}
