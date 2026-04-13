/** مفاتيح الأيام كما في الخادم (BranchOpeningHours) */
export const BRANCH_DAY_KEYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'] as const
export type BranchDayKey = (typeof BRANCH_DAY_KEYS)[number]

export const BRANCH_DAY_LABEL_AR: Record<BranchDayKey, string> = {
  sun: 'الأحد',
  mon: 'الإثنين',
  tue: 'الثلاثاء',
  wed: 'الأربعاء',
  thu: 'الخميس',
  fri: 'الجمعة',
  sat: 'السبت',
}

export const BRANCH_DAY_LABEL_EN: Record<BranchDayKey, string> = {
  sun: 'Sun',
  mon: 'Mon',
  tue: 'Tue',
  wed: 'Wed',
  thu: 'Thu',
  fri: 'Fri',
  sat: 'Sat',
}

export type DaySlotForm = { enabled: boolean; open: string; close: string }

/** نموذج يوم واحد لكل مفتاح (واجهة مبسّطة) */
export type WeekHoursForm = Record<BranchDayKey, DaySlotForm>

export function defaultWeekHoursForm(): WeekHoursForm {
  const row = (enabled: boolean, open: string, close: string): DaySlotForm => ({
    enabled,
    open,
    close,
  })
  return {
    sun: row(false, '09:00', '17:00'),
    mon: row(true, '08:00', '18:00'),
    tue: row(true, '08:00', '18:00'),
    wed: row(true, '08:00', '18:00'),
    thu: row(true, '08:00', '18:00'),
    fri: row(true, '08:00', '18:00'),
    sat: row(false, '09:00', '14:00'),
  }
}

function normalizeTime(t: string): string {
  const s = String(t ?? '').trim()
  if (/^\d{1,2}:\d{2}$/.test(s)) {
    const [h, m] = s.split(':')
    return `${h.padStart(2, '0')}:${m}`
  }
  return s
}

function isValidPair(open: string, close: string): boolean {
  const o = normalizeTime(open)
  const c = normalizeTime(close)
  return o !== '' && c !== '' && o < c
}

/** تحويل JSON الفرع إلى نموذج النموذج (يأخذ أول فترة لكل يوم إن وُجدت أكثر من واحدة) */
export function weekFormFromOpeningHours(raw: unknown): { useHours: boolean; week: WeekHoursForm } {
  const week = defaultWeekHoursForm()
  if (raw == null || typeof raw !== 'object') {
    return { useHours: false, week }
  }
  const o = raw as Record<string, unknown>
  let any = false
  for (const k of BRANCH_DAY_KEYS) {
    const slots = o[k]
    if (!Array.isArray(slots) || slots.length === 0) continue
    const first = slots[0]
    if (!Array.isArray(first) || first.length < 2) continue
    const op = normalizeTime(String(first[0]))
    const cl = normalizeTime(String(first[1]))
    if (!op || !cl) continue
    week[k] = { enabled: true, open: op, close: cl }
    any = true
  }
  return { useHours: any, week }
}

/** للإرسال إلى API — يوم مغلق أو فارغ لا يُدرَج؛ إن لم يبقَ أي فترة يُرجَع null */
export function openingHoursPayloadFromWeekForm(
  useHours: boolean,
  week: WeekHoursForm,
): Record<string, [string, string][]> | null {
  if (!useHours) return null
  const out: Record<string, [string, string][]> = {}
  for (const k of BRANCH_DAY_KEYS) {
    const d = week[k]
    if (!d.enabled || !isValidPair(d.open, d.close)) continue
    out[k] = [[normalizeTime(d.open), normalizeTime(d.close)]]
  }
  return Object.keys(out).length ? out : null
}

/** هل يوجد فترات فعلية (للعرض في الجدول / الحجوزات) */
export function scheduleHasIntervals(o: Record<string, unknown> | null | undefined): boolean {
  if (!o || typeof o !== 'object') return false
  return Object.values(o).some((v) => Array.isArray(v) && (v as unknown[]).length > 0)
}

/**
 * توحيد مفاتيح الأيام والقيم القادمة من API (نصوص، أشكال مختلفة).
 */
export function normalizeOpeningHoursForDisplay(
  raw: unknown,
): Record<BranchDayKey, [string, string][]> | null {
  if (raw == null || typeof raw !== 'object') return null
  const src = raw as Record<string, unknown>
  const out: Partial<Record<BranchDayKey, [string, string][]>> = {}
  const alias: Record<string, BranchDayKey> = {
    mon: 'mon',
    mo: 'mon',
    monday: 'mon',
    tue: 'tue',
    tu: 'tue',
    tuesday: 'tue',
    wed: 'wed',
    we: 'wed',
    wednesday: 'wed',
    thu: 'thu',
    th: 'thu',
    thursday: 'thu',
    fri: 'fri',
    fr: 'fri',
    friday: 'fri',
    sat: 'sat',
    sa: 'sat',
    saturday: 'sat',
    sun: 'sun',
    su: 'sun',
    sunday: 'sun',
  }
  for (const [rawKey, val] of Object.entries(src)) {
    const k = alias[String(rawKey).toLowerCase().trim()]
    if (!k) continue
    if (!Array.isArray(val) || val.length === 0) continue
    const pairs: [string, string][] = []
    for (const slot of val) {
      if (!Array.isArray(slot) || slot.length < 2) continue
      const a = normalizeTime(String(slot[0]))
      const b = normalizeTime(String(slot[1]))
      if (a && b) pairs.push([a, b])
    }
    if (pairs.length) out[k] = pairs
  }
  const keys = Object.keys(out) as BranchDayKey[]
  if (!keys.length) return null
  return out as Record<BranchDayKey, [string, string][]>
}
