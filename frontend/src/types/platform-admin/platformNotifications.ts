export type PlatformNotificationPriority = 'critical' | 'high' | 'medium' | 'informational'

export type PlatformNotificationType =
  | 'approval'
  | 'financial'
  | 'support'
  | 'operational'
  | 'follow_up'
  | 'decision'
  | 'governance'

export interface PlatformNotificationItem {
  notification_id: string
  notification_type: PlatformNotificationType | string
  title: string
  summary: string
  priority: PlatformNotificationPriority | string
  status: string
  created_at: string
  is_read: boolean
  target_type: string
  target_id: string
  target_route: string
  target_params: Record<string, unknown>
  cta_label: string
  group_key: string | null
  metadata?: Record<string, unknown>
  requires_action: boolean
  related_company?: { id: number; name: string } | null
  related_incident_key?: string | null
  related_ticket_id?: number | null
}

export interface PlatformNotificationsResponse {
  data: PlatformNotificationItem[]
  meta?: {
    total?: number
    unread_count?: number
    requires_action_count?: number
    attention_now?: PlatformNotificationItem[]
  }
}

