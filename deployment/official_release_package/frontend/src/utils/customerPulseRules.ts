import type { ComparativeHint, CustomerPulseSummary, PulseHealth } from '@/types/customerPulseReport'

export function previousInclusiveRange(fromStr: string, toStr: string): { from: string; to: string } {
  const pad = (d: Date) => {
    const y = d.getFullYear()
    const m = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${y}-${m}-${day}`
  }
  const from = new Date(`${fromStr}T12:00:00`)
  const to = new Date(`${toStr}T12:00:00`)
  const ms = 86400000
  const inclusiveDays = Math.round((to.getTime() - from.getTime()) / ms) + 1
  const prevTo = new Date(from.getTime() - ms)
  const prevFrom = new Date(prevTo.getTime() - (inclusiveDays - 1) * ms)
  return { from: pad(prevFrom), to: pad(prevTo) }
}

export function pctDelta(current: number, previous: number): number | null {
  if (previous <= 0) return current > 0 ? 100 : null
  return Math.round(((current - previous) / previous) * 1000) / 10
}

export function comparativeHint(delta: number | null, inverseGood = false): ComparativeHint {
  if (delta === null) return 'stable'
  if (Math.abs(delta) < 5) return 'stable'
  const up = delta > 0
  const good = inverseGood ? !up : up
  if (good) return 'improving'
  if (Math.abs(delta) >= 25) return 'needs_attention'
  return 'declining'
}

export function inferPulseHealth(args: {
  summary: CustomerPulseSummary
  financial: boolean
  prevSummary: CustomerPulseSummary | null
}): PulseHealth {
  const { summary, financial, prevSummary } = args
  const wo = summary.work_orders_in_period
  const tOpen = summary.tickets_open
  const tOver = summary.tickets_overdue
  const hasActivity = wo > 0 || (financial && (summary.invoices_in_period > 0 || summary.payments_in_period > 0))

  if (!hasActivity && tOpen === 0 && !summary.last_activity_at) {
    return 'no_data'
  }

  const riskFinancial =
    financial && summary.invoices_in_period > 0 && summary.payments_in_period === 0 && summary.tickets_open > 0

  if (tOver > 0 || riskFinancial) {
    return 'at_risk'
  }

  let declining = false
  if (prevSummary) {
    const d = pctDelta(wo, prevSummary.work_orders_in_period)
    declining = d !== null && d <= -20
  }

  if (tOpen > 0 || declining) {
    return 'watch'
  }

  return 'healthy'
}

export function buildStatusBanner(args: {
  health: PulseHealth
  summary: CustomerPulseSummary
  financial: boolean
  ar: boolean
}): { tone: 'success' | 'warning' | 'danger' | 'neutral'; message: string } {
  const { health, summary, financial, ar } = args
  if (health === 'no_data') {
    return {
      tone: 'neutral',
      message: ar
        ? 'لا توجد بيانات كافية خلال الفترة المحددة — جرّب توسيع النطاق الزمني.'
        : 'Not enough data in this range — try widening the date window.',
    }
  }
  if (health === 'at_risk') {
    const parts: string[] = []
    if (summary.tickets_overdue > 0) {
      parts.push(ar ? `${summary.tickets_overdue} تذكرة متأخرة` : `${summary.tickets_overdue} overdue ticket(s)`)
    }
    if (financial && summary.invoices_in_period > 0 && summary.payments_in_period === 0) {
      parts.push(ar ? 'فواتير دون مدفوعات مُسجّلة في الفترة' : 'Invoices without matching payments in period')
    }
    if (summary.tickets_open > 0 && summary.tickets_overdue === 0) {
      parts.push(ar ? `${summary.tickets_open} تذكرة مفتوحة` : `${summary.tickets_open} open ticket(s)`)
    }
    return {
      tone: 'danger',
      message: parts.length
        ? (ar ? `يستحق الانتباه: ${parts.join(' — ')}.` : `Needs attention: ${parts.join(' — ')}.`)
        : ar
          ? 'مؤشرات تستدعي المتابعة.'
          : 'Signals require follow-up.',
    }
  }
  if (health === 'watch') {
    return {
      tone: 'warning',
      message: ar
        ? 'العميل بحاجة لمتابعة خفيفة — تذاكر مفتوحة أو تباطؤ في النشاط مقارنة بالفترة السابقة.'
        : 'Watch this account — open tickets or slower activity vs the prior period.',
    }
  }
  return {
    tone: 'success',
    message: ar
      ? 'العميل نشط ضمن الفترة ولا توجد مؤشرات تعثر واضحة.'
      : 'Customer looks active for this period with no clear distress signals.',
  }
}

export interface DerivedActivityLine {
  at: string
  labelAr: string
  labelEn: string
  hintAr?: string
  hintEn?: string
}

export function buildDerivedActivityLines(summary: CustomerPulseSummary, financial: boolean): DerivedActivityLine[] {
  const out: DerivedActivityLine[] = []
  const now = new Date().toISOString()

  if (summary.last_activity_at) {
    out.push({
      at: summary.last_activity_at,
      labelAr: 'آخر نشاط مسجّل',
      labelEn: 'Latest recorded activity',
    })
  }
  if (summary.work_orders_in_period > 0) {
    out.push({
      at: now,
      labelAr: `أوامر عمل في الفترة (${summary.work_orders_in_period})`,
      labelEn: `Work orders in period (${summary.work_orders_in_period})`,
      hintAr: 'ملخّص تلقائي من المؤشرات',
      hintEn: 'Auto summary from metrics',
    })
  }
  if (financial && summary.invoices_in_period > 0) {
    out.push({
      at: now,
      labelAr: `فواتير صادرة (${summary.invoices_in_period})`,
      labelEn: `Invoices issued (${summary.invoices_in_period})`,
    })
  }
  if (financial && summary.payments_in_period > 0) {
    out.push({
      at: now,
      labelAr: `مدفوعات مسجّلة (${summary.payments_in_period})`,
      labelEn: `Payments recorded (${summary.payments_in_period})`,
    })
  }
  if (summary.tickets_open > 0) {
    out.push({
      at: now,
      labelAr: `تذاكر مفتوحة (${summary.tickets_open}${summary.tickets_overdue ? `، ${summary.tickets_overdue} متأخرة` : ''})`,
      labelEn: `Open tickets (${summary.tickets_open}${summary.tickets_overdue ? `, ${summary.tickets_overdue} overdue` : ''})`,
    })
  }
  return out.slice(0, 8)
}

export interface AttentionItem {
  severity: 'high' | 'medium' | 'low'
  textAr: string
  textEn: string
}

export function buildAttentionItems(summary: CustomerPulseSummary, financial: boolean): AttentionItem[] {
  const items: AttentionItem[] = []
  if (summary.tickets_overdue > 0) {
    items.push({
      severity: 'high',
      textAr: `${summary.tickets_overdue} تذكرة متأخرة عن SLA`,
      textEn: `${summary.tickets_overdue} ticket(s) past SLA`,
    })
  }
  if (summary.tickets_open > 0 && summary.tickets_overdue === 0) {
    items.push({
      severity: 'medium',
      textAr: `${summary.tickets_open} تذكرة مفتوحة تستحق المتابعة`,
      textEn: `${summary.tickets_open} open ticket(s) to follow up`,
    })
  }
  if (financial && summary.invoices_in_period > 0 && summary.payments_in_period === 0) {
    items.push({
      severity: 'high',
      textAr: 'فواتير في الفترة دون مدفوعات مُسجّلة',
      textEn: 'Invoices in period with no recorded payments',
    })
  }
  if (summary.work_orders_in_period === 0 && summary.last_activity_at) {
    items.push({
      severity: 'low',
      textAr: 'لا أوامر عمل جديدة في الفترة رغم وجود نشاط سابق',
      textEn: 'No new work orders this period despite older activity',
    })
  }
  return items.slice(0, 6)
}

export function trendCaptionFromSeries(rows: { count: number }[], ar: boolean): string {
  if (!rows.length) return ar ? 'لا بيانات أسبوعية في النطاق.' : 'No weekly buckets in range.'
  let maxI = 0
  let maxC = -1
  rows.forEach((r, i) => {
    if (r.count > maxC) {
      maxC = r.count
      maxI = i
    }
  })
  if (maxC <= 0) return ar ? 'النشاط الأسبوعي صفر ضمن الفترة.' : 'Weekly activity is flat at zero.'
  const tail = rows.slice(-2).reduce((a, b) => a + b.count, 0)
  const head = rows.slice(0, 2).reduce((a, b) => a + b.count, 0)
  if (rows.length >= 4 && tail * 2 < head) {
    return ar ? 'انخفاض واضح في آخر الأسابيع.' : 'Noticeable dip in the latest weeks.'
  }
  return ar ? `ذروة العدّ في الفترة الأسبوعية رقم ${maxI + 1}.` : `Peak weekly bucket is bucket #${maxI + 1}.`
}
