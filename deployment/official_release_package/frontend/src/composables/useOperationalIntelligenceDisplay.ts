import type { OperationalAttentionItem, OperationalHealthStatus, OperationalIntelligencePayload } from '@/types/operationalIntelligence'

/** Banner copy from API health_status only (no local rule logic). */
export function healthStatusBannerMessage(
  health: OperationalHealthStatus | string | undefined,
  l: (ar: string, en: string) => string,
): string {
  switch (health) {
    case 'healthy':
      return l('الوضع التشغيلي مستقر.', 'Operational health looks stable.')
    case 'watch':
      return l('مراقبة: إشارات تستحق المتابعة.', 'Watch: signals deserve follow-up.')
    case 'at_risk':
      return l('خطر تشغيلي — تدخل مقترح.', 'At risk — operational follow-up suggested.')
    case 'inactive':
      return l('قليل أو لا يوجد نشاط في النطاق الحالي.', 'Little or no activity in the current scope.')
    default:
      return ''
  }
}

export function attentionItemLabel(item: OperationalAttentionItem, l: (ar: string, en: string) => string): string {
  const map: Record<string, [string, string]> = {
    'intelligence.attention.company_not_fully_active': ['الشركة غير نشطة بالكامل.', 'Company is not fully active.'],
    'intelligence.attention.open_tickets_overdue': ['تذاكر متأخرة.', 'Overdue support tickets.'],
    'intelligence.attention.open_support_tickets': ['تذاكر دعم مفتوحة.', 'Open support tickets.'],
    'intelligence.attention.no_recent_activity': ['لا نشاط حديث.', 'No recent activity.'],
    'intelligence.attention.low_operational_pulse': ['نبض تشغيلي منخفض.', 'Lower operational pulse.'],
    'intelligence.attention.customer_open_tickets': ['تذاكر للعميل.', 'Customer has open tickets.'],
    'intelligence.attention.overdue_invoices': ['فواتير متأخرة.', 'Overdue invoices.'],
    'intelligence.attention.customer_inactive': ['عميل غير نشط.', 'Customer looks inactive.'],
    'intelligence.attention.feed_high_attention': ['كثرة عناصر تحتاج انتباهاً.', 'Many items need attention.'],
    'intelligence.attention.feed_attention_mix': ['بعض العناصر تحتاج انتباهاً.', 'Some items need attention.'],
    'intelligence.attention.feed_empty_window': ['لا أحداث في النافذة.', 'No events in this window.'],
    'intelligence.attention.feed_invoices_without_payments': ['فواتير دون مدفوعات مطابقة.', 'Invoices without matching payments.'],
    'intelligence.attention.feed_watch': ['تدفق بحالة مراقبة.', 'Feed is in a watch state.'],
    'intelligence.attention.work_orders_empty_window': ['لا أوامر عمل في الفترة.', 'No work orders in period.'],
    'intelligence.attention.work_orders_many_on_hold': ['عدد كبير معلّق.', 'Many work orders on hold.'],
    'intelligence.attention.work_orders_watch': ['مزيج أوامر عمل يستحق المراقبة.', 'Work order mix is watch-worthy.'],
  }
  const pair = map[item.message_key]
  return pair ? l(pair[0], pair[1]) : item.message_key
}

export function indicatorHint(payload: OperationalIntelligencePayload | null | undefined, l: (ar: string, en: string) => string): string {
  if (!payload) return ''
  const { activity_level, engagement_level, payment_behavior } = payload.indicators
  return l(
    `النشاط: ${activity_level} · التفاعل: ${engagement_level} · الدفع: ${payment_behavior}`,
    `Activity: ${activity_level} · Engagement: ${engagement_level} · Payment: ${payment_behavior}`,
  )
}
