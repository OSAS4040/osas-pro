/**
 * قرارات إظهار/الوصول لميزات الموظف (staff) — منطق واحد للراوتر والتنقل والاختصارات.
 * لا تستدعي الـ API؛ تمرّر نتيجة `businessProfile.isEnabled` و`auth` و`featureFlags` من المكوّنات.
 */

export function tenantSectionOpen(isOwner: boolean, isEnabled: (key: string) => boolean, key: string): boolean {
  if (isOwner) return true
  return isEnabled(key)
}

/** ذكاء الأعمال (BI) في الواجهة: علم البناء + بوابة intelligence في ملف النشاط. */
export function canAccessStaffBusinessIntelligence(args: {
  buildFlagOn: boolean
  isOwner: boolean
  isEnabled: (key: string) => boolean
}): boolean {
  if (!args.buildFlagOn) return false
  return tenantSectionOpen(args.isOwner, args.isEnabled, 'intelligence')
}

/** مركز العمليات الداخلي: ما سبق + صلاحية التقارير الذكية. */
export function canAccessStaffCommandCenter(args: {
  buildFlagOn: boolean
  isOwner: boolean
  isEnabled: (key: string) => boolean
  hasIntelligenceReportPermission: boolean
}): boolean {
  if (!canAccessStaffBusinessIntelligence(args)) return false
  return args.hasIntelligenceReportPermission
}

/** مسارات الورشة / الموارد البشرية في URL. */
export function canAccessWorkshopArea(isOwner: boolean, isEnabled: (key: string) => boolean): boolean {
  return tenantSectionOpen(isOwner, isEnabled, 'hr')
}

/** بيارات، حجوزات، اجتماعات — بوابة `operations` في ملف النشاط. */
export function canAccessStaffOperationsArea(isOwner: boolean, isEnabled: (key: string) => boolean): boolean {
  return tenantSectionOpen(isOwner, isEnabled, 'operations')
}
