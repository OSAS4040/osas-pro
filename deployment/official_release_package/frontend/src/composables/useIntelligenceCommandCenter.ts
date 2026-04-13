import { ref, computed, reactive } from 'vue'
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

export type IntelligenceSectionKey = 'overview' | 'insights' | 'recommendations' | 'alerts' | 'commandCenter'

export function useIntelligenceCommandCenter() {
  /** Full-page skeleton: true until any endpoint returns usable data */
  const loading = ref(true)
  /** Background refresh (strip spin) */
  const refreshing = ref(false)
  const error = ref<string | null>(null)
  const refreshedAt = ref<Date | null>(null)
  const traceId = ref<string | null>(null)

  const sectionLoading = reactive<Record<IntelligenceSectionKey, boolean>>({
    overview: true,
    insights: true,
    recommendations: true,
    alerts: true,
    commandCenter: true,
  })

  const overview = ref<OverviewData | null>(null)
  const insights = ref<InsightsData | null>(null)
  const recommendations = ref<unknown[] | null>(null)
  const alerts = ref<AlertRow[] | null>(null)
  const commandCenter = ref<CommandCenterData | null>(null)

  const hasCommandCenterPayload = computed(() => commandCenter.value !== null)

  const hasAnyData = computed(
    () =>
      overview.value !== null ||
      insights.value !== null ||
      commandCenter.value !== null ||
      recommendations.value !== null ||
      alerts.value !== null,
  )

  function collectErr(reason: unknown, errs: string[]): void {
    const msg = (reason as { response?: { status?: number } })?.response?.status
    if (msg === 404) {
      errs.push('أحد واجهات الذكاء غير مفعّل في الخادم (404).')
    } else if (msg === 403) {
      errs.push('غير مصرح بعرض لوحة العمليات.')
    } else {
      errs.push('تعذّر تحميل بيانات الذكاء.')
    }
  }

  async function loadAll(): Promise<void> {
    const isBootstrap = !hasAnyData.value
    if (isBootstrap) {
      loading.value = true
      Object.keys(sectionLoading).forEach((k) => {
        sectionLoading[k as IntelligenceSectionKey] = true
      })
    } else {
      refreshing.value = true
    }
    error.value = null

    const errs: string[] = []
    let firstPaintUnlocked = false
    const unlockSkeletonOnFirstResponse = () => {
      if (!firstPaintUnlocked && loading.value) {
        firstPaintUnlocked = true
        loading.value = false
      }
    }

    const run = async <T>(
      key: IntelligenceSectionKey,
      url: string,
      assign: (data: T | null) => void,
    ): Promise<void> => {
      if (isBootstrap) {
        sectionLoading[key] = true
      }
      try {
        const res = await apiClient.get<ApiEnvelope<T>>(url)
        const env = res.data as ApiEnvelope<T>
        traceId.value = env.trace_id ?? traceId.value
        assign((env?.data ?? null) as T | null)
      } catch (e) {
        collectErr(e, errs)
        assign(null)
      } finally {
        unlockSkeletonOnFirstResponse()
        if (isBootstrap) {
          sectionLoading[key] = false
        }
      }
    }

    await Promise.all([
      run<OverviewData>('overview', `${INTERNAL}/overview`, (d) => {
        overview.value = d
      }),
      run<InsightsData>('insights', `${INTERNAL}/insights`, (d) => {
        insights.value = d
      }),
      run<unknown[]>('recommendations', `${INTERNAL}/recommendations`, (d) => {
        recommendations.value = d
      }),
      run<AlertRow[]>('alerts', `${INTERNAL}/alerts`, (d) => {
        alerts.value = d
      }),
      run<CommandCenterData>('commandCenter', `${INTERNAL}/command-center`, (d) => {
        commandCenter.value = d
      }),
    ])

    if (!overview.value && !insights.value && !commandCenter.value) {
      error.value = errs[0] ?? 'تعذّر الاتصال بواجهات الذكاء.'
    } else {
      error.value = null
    }

    refreshedAt.value = new Date()
    loading.value = false
    refreshing.value = false
  }

  return {
    loading,
    refreshing,
    sectionLoading,
    error,
    refreshedAt,
    traceId,
    overview,
    insights,
    recommendations,
    alerts,
    commandCenter,
    hasCommandCenterPayload,
    hasAnyData,
    loadAll,
  }
}
