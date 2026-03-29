import { ref, computed } from 'vue'
import apiClient from '@/lib/apiClient'

export type Severity = 'info' | 'warning' | 'critical' | string

/** Phase 5 — navigable context (SPA path + display); no API calls on click. */
export interface EntityReference {
  type: string
  id: string | number
  label: string
  /** Internal app path, e.g. /invoices/12 */
  href: string
}

export interface IntelligenceMeta {
  read_only?: boolean
  phase?: number
}

export interface CommandZoneItem {
  id: string
  source: 'alert' | 'recommendation' | string
  severity: Severity
  title: string
  why_now: string
  suggested_action: string
  impact_if_ignored: string
  related_entity_references: EntityReference[]
  /** Phase 6 — explainability (Why Engine), read-only */
  why_details?: string[]
  signals_used?: string[]
  thresholds?: Record<string, unknown>
  confidence?: number | null
  meta?: Record<string, unknown>
}

export interface CommandCenterData {
  read_only: boolean
  phase: number
  generated_at: string
  window: { from: string; to: string }
  summary: {
    total_now: number
    total_next: number
    total_watch: number
    low_signal: boolean
  }
  zones: {
    now: CommandZoneItem[]
    next: CommandZoneItem[]
    watch: CommandZoneItem[]
  }
  insights_snapshot: {
    total_events: number
    last_occurred_at: string | null
    first_occurred_at: string | null
  }
}

export interface OverviewData {
  read_only?: boolean
  window?: { from: string; to: string }
  summary?: Record<string, unknown>
  feature_flags?: Record<string, boolean>
}

export interface InsightsData {
  window: { from: string; to: string }
  totals: { events: number }
  by_event_name: { event_name: string; count: number }[]
  last_occurred_at?: string | null
  daily_counts?: { date: string; count: number }[]
}

export interface AlertRow {
  id: string
  severity: string
  type?: string
  message: string
  detected_at?: string
  basis?: string
}

export interface ApiEnvelope<T> {
  data: T
  meta?: IntelligenceMeta
  trace_id?: string | null
}

const INTERNAL = '/internal/intelligence'

export function useIntelligenceCommandCenter() {
  const loading = ref(true)
  const error = ref<string | null>(null)
  const refreshedAt = ref<Date | null>(null)
  const traceId = ref<string | null>(null)

  const overview = ref<OverviewData | null>(null)
  const insights = ref<InsightsData | null>(null)
  const recommendations = ref<unknown[] | null>(null)
  const alerts = ref<AlertRow[] | null>(null)
  const commandCenter = ref<CommandCenterData | null>(null)

  const hasCommandCenterPayload = computed(() => commandCenter.value !== null)

  async function loadAll(): Promise<void> {
    loading.value = true
    error.value = null

    const results = await Promise.allSettled([
      apiClient.get<ApiEnvelope<OverviewData>>(`${INTERNAL}/overview`),
      apiClient.get<ApiEnvelope<InsightsData>>(`${INTERNAL}/insights`),
      apiClient.get<ApiEnvelope<unknown[]>>(`${INTERNAL}/recommendations`),
      apiClient.get<ApiEnvelope<AlertRow[]>>(`${INTERNAL}/alerts`),
      apiClient.get<ApiEnvelope<CommandCenterData>>(`${INTERNAL}/command-center`),
    ])

    const errs: string[] = []

    const pick = <T>(i: number): T | null => {
      const r = results[i]
      if (r.status === 'fulfilled') {
        const body = r.value.data as ApiEnvelope<T>
        traceId.value = body.trace_id ?? traceId.value
        return body.data as T
      }
      const msg = (r.reason as { response?: { status?: number } })?.response?.status
      if (msg === 404) {
        errs.push('أحد واجهات الذكاء غير مفعّل في الخادم (404).')
      } else if (msg === 403) {
        errs.push('غير مصرح بعرض لوحة العمليات.')
      } else {
        errs.push('تعذّر تحميل بيانات الذكاء.')
      }
      return null
    }

    overview.value = pick<OverviewData>(0)
    insights.value = pick<InsightsData>(1)
    recommendations.value = pick<unknown[]>(2)
    alerts.value = pick<AlertRow[]>(3)
    commandCenter.value = pick<CommandCenterData>(4)

    if (!overview.value && !insights.value && !commandCenter.value) {
      error.value = errs[0] ?? 'تعذّر الاتصال بواجهات الذكاء.'
    } else {
      error.value = null
    }

    refreshedAt.value = new Date()
    loading.value = false
  }

  return {
    loading,
    error,
    refreshedAt,
    traceId,
    overview,
    insights,
    recommendations,
    alerts,
    commandCenter,
    hasCommandCenterPayload,
    loadAll,
  }
}
