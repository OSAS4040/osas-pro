/**
 * Build-time feature flags (Vite). Each flag documents its own default below.
 */
export const featureFlags = {
  /** Phase 4 Smart Command Center — افتراضيًا مفعّل؛ عطّل صراحةً بـ VITE_INTELLIGENCE_COMMAND_CENTER=false */
  intelligenceCommandCenter: import.meta.env.VITE_INTELLIGENCE_COMMAND_CENTER !== 'false',

  /**
   * عند true: أول زيارة تفعّل «وضع مركز الخدمة السريع» تلقائيًا (قابل للعكس من الشريط أو localStorage).
   * يمكن للمستخدم تعطيل/تفعيل الزر حتى لو كان false.
   */
  staffCompactUiDefaultOn: import.meta.env.VITE_STAFF_COMPACT_UI === 'true',

  /**
   * الأرشفة الإلكترونية (`/electronic-archive` و `/workshop/hr-archive`).
   * الافتراضي: مفعّل؛ عطّل بـ VITE_ELECTRONIC_ARCHIVE=false
   */
  electronicArchive: import.meta.env.VITE_ELECTRONIC_ARCHIVE !== 'false',
}
