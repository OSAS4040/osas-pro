export type InsightSeverity = 'risk' | 'warning' | 'info'

/** رؤية واحدة من محرك «لماذا؟» — بيانات مُشتقة فقط من قائمة المشتركين */
export interface PlatformFinanceInsight {
  id: string
  title: string
  severity: InsightSeverity
  reasons: string[]
  signals: string[]
  recommendations: string[]
  /** 0–100 */
  confidence: number
}
