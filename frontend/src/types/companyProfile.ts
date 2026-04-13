import type { OperationalIntelligencePayload } from '@/types/operationalIntelligence'

export interface CompanyProfileCompany {
  id: number
  name: string
  status: string
  type: string | null
  created_at: string | null
}

export interface CompanyProfileSummary {
  users_count: number
  customers_count: number
  branches_count: number
  work_orders_active: number
  invoices_in_period: number | null
  last_activity_at: string | null
  activity_window_days: number
}

export interface ActivitySnapshotItem {
  id: number
  reference: string
  status: string
  occurred_at: string | null
  subtitle: string | null
}

export interface CompanyProfileActivitySnapshot {
  last_work_order: ActivitySnapshotItem | null
  last_invoice: ActivitySnapshotItem | null
  last_payment: ActivitySnapshotItem | null
  last_ticket: ActivitySnapshotItem | null
}

export interface CompanyProfileHealth {
  activity_status: 'healthy' | 'watch' | 'at_risk' | 'inactive'
  inactivity_flag: boolean
  open_tickets: number
  possible_risk_flag: boolean
}

export interface CompanyProfileTopCustomer {
  customer_id: number
  customer_name: string
  work_orders_count: number
}

export interface CompanyProfileTopUser {
  user_id: number
  user_name: string
  work_orders_touched: number
}

export interface CompanyProfileBranchSummary {
  branch_id: number
  branch_name: string
  work_orders_in_period: number
}

export interface CompanyOperationalMap {
  version: number
  scope: 'company'
  company_id: number
  counts: {
    customers: number
    users: number
    branches: number
  }
  visibility: {
    customer_profiles: boolean
    user_directory: boolean
    branch_directory: boolean
    branch_settings: boolean
  }
}

export interface CompanyProfileRelationships {
  top_customers: CompanyProfileTopCustomer[]
  top_users: CompanyProfileTopUser[]
  branches_summary: CompanyProfileBranchSummary[]
  operational_map?: CompanyOperationalMap
}

export interface CompanyProfileAttentionItem {
  code: string
  severity: string
  message: string
}

export interface CompanyProfilePayload {
  company: CompanyProfileCompany
  summary: CompanyProfileSummary
  activity_snapshot: CompanyProfileActivitySnapshot
  health_indicators: CompanyProfileHealth
  relationships: CompanyProfileRelationships
  attention_items: CompanyProfileAttentionItem[]
  intelligence?: OperationalIntelligencePayload
}

export interface CompanyProfileResponse {
  data: CompanyProfilePayload
  meta: {
    financial_metrics_included: boolean
    read_only: boolean
    intelligence_version?: string
  }
  trace_id?: string
}
