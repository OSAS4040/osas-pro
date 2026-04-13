/** Response shape for GET /reporting/v1/customer/pulse-summary */

export interface CustomerPulseSummary {
  work_orders_in_period: number
  invoices_in_period: number
  payments_in_period: number
  tickets_open: number
  tickets_overdue: number
  last_activity_at: string | null
  vehicles_count: number
}

export interface StatusBucketRow {
  status: string
  count: number
}

export interface WeeklyBucketRow {
  period_start: string
  count: number
}

export interface CustomerPulseBreakdown {
  by_status: {
    work_orders: StatusBucketRow[]
    invoices: StatusBucketRow[]
    support_tickets: StatusBucketRow[]
  }
  by_activity: {
    work_orders_created_in_period: number
    invoices_issued_in_period: number
    payments_recorded_in_period: number
  }
  by_time_period: {
    granularity: string
    work_orders: WeeklyBucketRow[]
    invoices: WeeklyBucketRow[]
  }
}

export interface CustomerPulseEnvelope {
  report: {
    id: string
    version?: number
    read_only: boolean
    generated_at?: string
    period: { from: string; to: string }
    filters: Record<string, unknown>
  }
  data: {
    summary: CustomerPulseSummary
    breakdown: CustomerPulseBreakdown
  }
  meta: {
    financial_metrics_included?: boolean
    read_only?: boolean
    filters_applied?: Record<string, unknown>
    query_kind?: string
  }
  trace_id?: string
}

export type PulseHealth = 'healthy' | 'watch' | 'at_risk' | 'no_data'

export type ComparativeHint = 'stable' | 'improving' | 'declining' | 'needs_attention'
