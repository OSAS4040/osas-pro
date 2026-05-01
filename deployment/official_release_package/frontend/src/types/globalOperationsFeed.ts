export interface GlobalFeedSummary {
  total_items_in_window: number
  work_orders_count: number
  invoices_count: number
  payments_count: number
  tickets_count: number
  attention_count: number
}

export interface GlobalFeedItem {
  type: string
  id: number
  occurred_at: string | null
  title: string
  subtitle: string
  description: string | null
  status: string
  severity: string
  attention_level: string
  company_id: number
  company_name: string
  branch_id: number | null
  branch_name: string | null
  customer_id: number | null
  customer_name: string | null
  actor_user_id: number | null
  actor_name: string | null
  amount: number | null
  currency: string | null
  reference: string
  entity_route: string | null
  tags: string[]
  financial_visibility_applied: boolean
  read_only: boolean
}

import type { OperationalIntelligencePayload } from '@/types/operationalIntelligence'

export interface GlobalFeedEnvelope {
  report: {
    id: string
    version?: number
    generated_at?: string
    read_only: boolean
    period: { from: string; to: string }
    filters: Record<string, unknown>
  }
  data: {
    summary: GlobalFeedSummary
    items: GlobalFeedItem[]
    intelligence?: OperationalIntelligencePayload
  }
  meta: {
    pagination: { page: number; per_page: number; total: number; last_page: number }
    financial_metrics_included: boolean
    filters_applied: Record<string, unknown>
    source_entities_included?: string[]
    read_only?: boolean
    generated_at?: string
    intelligence_version?: string
  }
  trace_id?: string
}
