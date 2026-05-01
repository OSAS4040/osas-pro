export type OperationalHealthStatus = 'healthy' | 'watch' | 'at_risk' | 'inactive'

export interface OperationalIndicators {
  activity_level: string
  engagement_level: string
  payment_behavior: string
}

export interface OperationalAttentionItem {
  type: string
  severity: 'low' | 'medium' | 'high' | string
  message_key: string
  related_entity: string
  created_at: string
}

export interface OperationalIntelligencePayload {
  health_status: OperationalHealthStatus | string
  indicators: OperationalIndicators
  attention_items: OperationalAttentionItem[]
}
