import { computed, unref, type MaybeRef } from 'vue'
import type { PlatformFinanceInsight } from './platformInsightTypes'

const STORAGE_KEY = 'verdent:platform:finance-insight-snapshot'

/** صف شركة كما يعيده GET /admin/companies (قراءة فقط) */
export interface PlatformCompanyLike {
  id: number
  name?: string
  subscription_status?: string | null
  financial_model_status?: string | null
  company_status?: string | null
  monthly_revenue?: number | string | null
  updated_at?: string | null
}

export interface FinanceInsightSnapshot {
  overdueLikeCount: number
  mrrActive: number
  activeSubscriptions: number
  savedAt: string
}

export function readFinanceSnapshot(): FinanceInsightSnapshot | null {
  if (typeof sessionStorage === 'undefined') return null
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY)
    if (!raw) return null
    const o = JSON.parse(raw) as Partial<FinanceInsightSnapshot>
    if (
      typeof o.overdueLikeCount !== 'number'
      || typeof o.mrrActive !== 'number'
      || typeof o.activeSubscriptions !== 'number'
      || typeof o.savedAt !== 'string'
    ) {
      return null
    }
    return {
      overdueLikeCount: o.overdueLikeCount,
      mrrActive: o.mrrActive,
      activeSubscriptions: o.activeSubscriptions,
      savedAt: o.savedAt,
    }
  } catch {
    return null
  }
}

export function writeFinanceSnapshot(s: Omit<FinanceInsightSnapshot, 'savedAt'> & { savedAt?: string }): void {
  if (typeof sessionStorage === 'undefined') return
  const payload: FinanceInsightSnapshot = {
    ...s,
    savedAt: s.savedAt ?? new Date().toISOString(),
  }
  try {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(payload))
  } catch {
    /* ignore quota */
  }
}

function subLower(c: PlatformCompanyLike): string {
  return String(c.subscription_status ?? '').trim().toLowerCase()
}

function finStatus(c: PlatformCompanyLike): string {
  return String(c.financial_model_status ?? '').trim()
}

function companyStLower(c: PlatformCompanyLike): string {
  return String(c.company_status ?? '').trim().toLowerCase()
}

export function computeFinanceMetrics(list: PlatformCompanyLike[]): {
  overdueLikeCount: number
  mrrActive: number
  activeSubscriptions: number
  suspendedCount: number
  pendingReviewCount: number
} {
  let overdueLikeCount = 0
  let mrrActive = 0
  let activeSubscriptions = 0
  let suspendedCount = 0
  let pendingReviewCount = 0

  for (const c of list) {
    const sub = subLower(c)
    const fin = finStatus(c)
    const companySt = companyStLower(c)
    const rev = Number(c?.monthly_revenue) || 0

    if (sub === 'active' && companySt !== 'suspended') {
      activeSubscriptions += 1
      mrrActive += rev
    }

    if (sub === 'grace_period' || fin === 'pending_platform_review') {
      overdueLikeCount += 1
    }
    if (fin === 'pending_platform_review') pendingReviewCount += 1

    if (sub === 'suspended' || companySt === 'suspended') {
      suspendedCount += 1
    }
  }

  return {
    overdueLikeCount,
    mrrActive,
    activeSubscriptions,
    suspendedCount,
    pendingReviewCount,
  }
}

function daysSinceUpdated(iso: string | null | undefined): number | null {
  if (!iso) return null
  const t = new Date(iso).getTime()
  if (Number.isNaN(t)) return null
  return Math.floor((Date.now() - t) / (24 * 60 * 60 * 1000))
}

/** شركات اشتراكها «متأخر» (فترة سماح) مع بقاء الحالة دون تحديث يفوق N يوماً — بديل عن overdueDays غير المتوفر في الـ API */
function chronicGraceCompanies(list: PlatformCompanyLike[], minDays: number): PlatformCompanyLike[] {
  return list.filter((c) => {
    if (subLower(c) !== 'grace_period') return false
    const d = daysSinceUpdated(c.updated_at)
    return d != null && d > minDays
  })
}

function severityOrder(s: PlatformFinanceInsight['severity']): number {
  if (s === 'risk') return 0
  if (s === 'warning') return 1
  return 2
}

/**
 * يبني رؤى مالية مفسّرة من قائمة الشركات الحالية ومقارنة مع لقطة محلية (جلسة المتصفح).
 * لا يستدعي أي API.
 */
export function buildPlatformFinanceInsights(
  companies: PlatformCompanyLike[],
  previous: FinanceInsightSnapshot | null,
): PlatformFinanceInsight[] {
  const list = Array.isArray(companies) ? companies : []
  if (list.length === 0) return []

  const cur = computeFinanceMetrics(list)
  const prev = previous
  const out: PlatformFinanceInsight[] = []
  let hasOverdueSpike = false
  let hasMrrDrop = false

  const chronic = chronicGraceCompanies(list, 10)
  if (chronic.length > 0) {
    const names = chronic.slice(0, 5).map((c) => c.name || `شركة #${c.id}`)
    const pct = Math.round((chronic.length / Math.max(list.length, 1)) * 100)
    out.push({
      id: 'high-risk-grace-stale',
      title: 'تم رصد مشتركين متأخرين لفترة طويلة',
      severity: 'risk',
      reasons: [
        `يوجد ${chronic.length.toLocaleString('ar-SA')} مشترك في حالة «فترة سماح» مع بقاء السجل دون تحديث يفوق 10 أيام.`,
        'غياب حقل «أيام التأخير» في واجهة المنصة؛ تم الاعتماد على مدة ثبات حالة الاشتراك كإشارة بديلة.',
      ],
      signals: [
        `عدد الحالات: ${chronic.length.toLocaleString('ar-SA')}`,
        `نسبة تقريبية من المشتركين المعروضين: ${pct.toLocaleString('ar-SA')}٪`,
        ...names.map((n) => `مشترك: ${n}`),
      ],
      recommendations: [
        'مراجعة النموذج المالي والاشتراك لكل مشترك في القائمة أدناه.',
        'التنسيق مع فريق العمل للمستأجر عند الحاجة للتحصيل أو تمديد الاعتماد.',
      ],
      confidence: Math.min(95, 72 + Math.min(chronic.length * 3, 20)),
    })
  }

  if (prev) {
    const prevO = prev.overdueLikeCount
    const curO = cur.overdueLikeCount
    let spike = false
    let pct = 0
    if (prevO >= 1 && curO > prevO * 1.2) {
      spike = true
      pct = Math.round(((curO - prevO) / Math.max(prevO, 1)) * 100)
    } else if (prevO === 0 && curO >= 3) {
      spike = true
      pct = 100
    }
    if (spike) {
      hasOverdueSpike = true
      out.push({
        id: 'overdue-like-spike',
        title: 'ارتفاع عدد الاشتراكات المتأخرة أو قيد المراجعة',
        severity: curO >= prevO * 1.5 ? 'risk' : 'warning',
        reasons: [
          prevO >= 1
            ? `زيادة تقريبية ${pct.toLocaleString('ar-SA')}٪ مقارنة بآخر زيارة لقسم المالية (من ${prevO.toLocaleString('ar-SA')} إلى ${curO.toLocaleString('ar-SA')}).`
            : `قفزة من صفر إلى ${curO.toLocaleString('ar-SA')} حالة ضمن نفس تعريف «المتأخر/قيد المراجعة».`,
          'التعريف: اشتراك في فترة سماح، أو نموذج مالي قيد مراجعة المنصة.',
        ],
        signals: [
          `عدد_المتأخر_أو_المراجعة=${curO}`,
          'اتجاه_صاعد',
          `الإيراد_الشهري_المتكرر_تقدير=${Math.round(cur.mrrActive)}`,
        ],
        recommendations: ['متابعة المشتركين في الجدول بعد تصفية الحالة المالية أو الاشتراك.', 'أولوية لمن هم في «قيد مراجعة المنصة».'],
        confidence: Math.min(94, 78 + Math.min(curO, 12)),
      })
    }

    if (prev.mrrActive > 0 && cur.mrrActive < prev.mrrActive) {
      hasMrrDrop = true
      const dropPct = Math.round(((prev.mrrActive - cur.mrrActive) / prev.mrrActive) * 100)
      out.push({
        id: 'mrr-drop-vs-snapshot',
        title: 'انخفاض الإيراد الشهري المتكرر مقارنة بآخر لقطة',
        severity: dropPct >= 8 ? 'warning' : 'info',
        reasons: [
          `الإيراد الشهري المتكرر النشط حالياً ${Math.round(cur.mrrActive).toLocaleString('ar-SA')} ر.س. شهرياً أقل من اللقطة السابقة (${Math.round(prev.mrrActive).toLocaleString('ar-SA')} ر.س.).`,
          'القيمة مبنية على أسعار الباقات في الكتالوج وليست إيراداً محصّلاً.',
        ],
        signals: [
          `الإيراد_السابق=${Math.round(prev.mrrActive)}`,
          `الإيراد_الحالي=${Math.round(cur.mrrActive)}`,
          `نسبة_الانخفاض_تقريباً=${dropPct}٪`,
        ],
        recommendations: ['التحقق من انخفاض عدد الاشتراكات النشطة أو تخفيض باقات.', 'مراجعة المشتركين الموقوفين أو المنتقلين من نشط.'],
        confidence: Math.min(92, 80 + Math.min(dropPct, 10)),
      })
    }

    const stableGrowth =
      cur.activeSubscriptions > prev.activeSubscriptions
      && cur.overdueLikeCount === 0
      && prev.activeSubscriptions > 0
      && !hasMrrDrop
      && !hasOverdueSpike

    if (stableGrowth) {
      const delta = cur.activeSubscriptions - prev.activeSubscriptions
      out.push({
        id: 'stable-growth-active',
        title: 'نمو مستقر في الاشتراكات النشطة',
        severity: 'info',
        reasons: [
          `زاد عدد الاشتراكات النشطة بمقدار ${delta.toLocaleString('ar-SA')} مقارنة بآخر زيارة لقسم المالية، ولا توجد حالات متأخرة/قيد مراجعة ضمن التعريف الحالي.`,
        ],
        signals: [
          `الاشتراكات_النشطة=${cur.activeSubscriptions}`,
          'لا_متأخرين_ضمن_التعريف',
          `فرق_النشط=${delta}`,
        ],
        recommendations: ['الاستمرار بمراقبة جودة الإعداد للمشتركين الجدد.', 'التأكد من اكتمال النماذج المالية لتفادي تراكم «قيد المراجعة».'],
        confidence: 86,
      })
    }
  }

  if (cur.pendingReviewCount >= 2 && !out.some((x) => x.id === 'pending-review-backlog')) {
    out.push({
      id: 'pending-review-backlog',
      title: 'تراكم طلبات مراجعة النموذج المالي',
      severity: 'warning',
      reasons: [
        `تم رصد ${cur.pendingReviewCount.toLocaleString('ar-SA')} مشتركاً بحالة «قيد مراجعة المنصة» في الصفحة الحالية.`,
      ],
      signals: [`قيد_مراجعة_المنصة=${cur.pendingReviewCount}`, 'طابور_مراجعة_مالية'],
      recommendations: ['معالجة المراجعات بالتسلسل حسب الأولوية التشغيلية.', 'استخدام تصفية «قيد مراجعة المنصة» في الجدول.'],
      confidence: Math.min(90, 70 + cur.pendingReviewCount * 4),
    })
  }

  const ratioSus = list.length ? cur.suspendedCount / list.length : 0
  if (cur.suspendedCount >= 3 && ratioSus >= 0.15) {
    out.push({
      id: 'suspended-elevated-share',
      title: 'حصة مرتفعة من الاشتراكات أو الشركات الموقوفة',
      severity: 'warning',
      reasons: [
        `يوجد ${cur.suspendedCount.toLocaleString('ar-SA')} موقوفاً من أصل ${list.length.toLocaleString('ar-SA')} مشترك في الصفحة الحالية (حوالي ${Math.round(ratioSus * 100)}٪).`,
      ],
      signals: [`عدد_الموقوفين=${cur.suspendedCount.toLocaleString('ar-SA')}`, `حصة_تقريبية=${Math.round(ratioSus * 100)}٪`],
      recommendations: ['مراجعة أسباب الإيقاف (اشتراك أو شركة) في الجدول.', 'مقارنة مع سياسات الائتمان والتجديد لدى المشغّل.'],
      confidence: 81,
    })
  }

  out.sort((a, b) => severityOrder(a.severity) - severityOrder(b.severity) || b.confidence - a.confidence)

  const byId = new Map<string, PlatformFinanceInsight>()
  for (const x of out) {
    if (!byId.has(x.id)) byId.set(x.id, x)
  }
  return [...byId.values()]
}

/** Composable: رؤى ومقاييس مالية مُشتقة من مصفوفة شركات (بدون API) */
export function usePlatformInsights(companies: MaybeRef<PlatformCompanyLike[]>) {
  const insights = computed(() => {
    const list = unref(companies)
    if (!Array.isArray(list) || list.length === 0) return []
    return buildPlatformFinanceInsights(list, readFinanceSnapshot())
  })
  const metrics = computed(() => computeFinanceMetrics(unref(companies)))
  return { insights, metrics }
}
