import type { OperationalIntelligencePayload } from '@/types/operationalIntelligence'

export interface CustomerProfileCustomer {
  id: number
  name: string
  type: string
  created_at: string | null
}

export interface CustomerProfileSummary {
  work_orders_count: number
  invoices_count: number | null
  payments_count: number | null
  tickets_open: number
  last_activity_at: string | null
}

export interface CustomerProfileActivityItem {
  id: number
  reference: string
  status: string
  occurred_at: string | null
  subtitle: string | null
}

export interface CustomerProfileActivitySnapshot {
  last_work_order: CustomerProfileActivityItem | null
  last_invoice: CustomerProfileActivityItem | null
  last_payment: CustomerProfileActivityItem | null
  last_ticket: CustomerProfileActivityItem | null
}

export interface CustomerProfileBehavior {
  activity_level: string
  payment_behavior: string
  engagement_level: string
  inactivity_flag: boolean
}

export interface CustomerProfileBranch {
  branch_id: number
  branch_name: string
}

export interface CustomerProfileAssignedUser {
  user_id: number
  user_name: string
  role_hint: string
}

export interface CustomerProfileTopVehicle {
  vehicle_id: number
  plate_number: string
  make: string | null
  model: string | null
  year: number | null
}

export interface CustomerOperationalMap {
  version: number
  scope: 'customer'
  customer_id: number
  counts: {
    vehicles: number
    assigned_users: number
  }
  visibility: {
    vehicle_assets: boolean
    user_directory: boolean
  }
}

export interface CustomerProfileRelationships {
  vehicles_count: number
  branches: CustomerProfileBranch[]
  assigned_users: CustomerProfileAssignedUser[]
  top_vehicles: CustomerProfileTopVehicle[]
  operational_map?: CustomerOperationalMap
}

export interface CustomerProfileAttentionItem {
  code: string
  severity: string
  message: string
}

export interface CustomerProfilePayload {
  customer: CustomerProfileCustomer
  summary: CustomerProfileSummary
  activity_snapshot: CustomerProfileActivitySnapshot
  behavior_indicators: CustomerProfileBehavior
  relationships: CustomerProfileRelationships
  attention_items: CustomerProfileAttentionItem[]
  intelligence?: OperationalIntelligencePayload
}

export interface CustomerProfileResponse {
  data: CustomerProfilePayload
  meta: {
    financial_metrics_included: boolean
    read_only: boolean
    intelligence_version?: string
  }
  trace_id?: string
}
