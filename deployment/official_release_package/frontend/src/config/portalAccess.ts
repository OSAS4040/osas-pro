/**
 * تفعيل البوابات الاختيارية (build-time عبر Vite).
 * بوابة فريق العمل (staff) دائمة — لا تُعرَّف هنا.
 *
 * - غير مضبوط أو فارغ: تُفعَّل فقط بوابتا العميل والمنصة (والأسطول معطّل افتراضيًا).
 * - مضبوط: قائمة مفصولة بفواصل من المعرفات: customer, admin
 *   مثال: VITE_ENABLED_PORTALS=customer,admin — إبقاء البوابات المعتمدة فقط.
 *
 * ملاحظة: بوابة الأسطول (fleet) معطّلة قسريًا بقرار منتج، ولا يمكن تفعيلها من المتغير.
 */

export type OptionalPortal = 'fleet' | 'customer' | 'admin'

export type EnabledPortals = Record<OptionalPortal, boolean>

const DEFAULT_ALL: EnabledPortals = {
  fleet: false,
  customer: true,
  admin: true,
}

/**
 * للاختبارات ووحدات تفسير القيمة الخام.
 */
export function parseEnabledPortals(raw: string | undefined | null): EnabledPortals {
  if (raw === undefined || raw === null || String(raw).trim() === '') {
    return { ...DEFAULT_ALL }
  }
  const set = new Set(
    String(raw)
      .split(',')
      .map((s) => s.trim().toLowerCase())
      .filter(Boolean),
  )
  if (set.size === 0) {
    return { ...DEFAULT_ALL }
  }
  return {
    // Disabled for all users by product decision.
    fleet: false,
    customer: set.has('customer'),
    admin: set.has('admin'),
  }
}

export const enabledPortals: EnabledPortals = parseEnabledPortals(
  import.meta.env.VITE_ENABLED_PORTALS as string | undefined,
)

export function isOptionalPortalEnabled(id: OptionalPortal): boolean {
  return enabledPortals[id]
}
