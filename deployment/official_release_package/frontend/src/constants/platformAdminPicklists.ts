/** عملات شائعة في واجهات التسعير الإدارية */
export const PLATFORM_CURRENCY_OPTIONS = [
  'SAR',
  'USD',
  'EUR',
  'GBP',
  'AED',
  'KWD',
  'BHD',
  'OMR',
  'QAR',
  'EGP',
] as const

/** أكواد خدمات مستخدمة في الاختبارات والمسارات — يمكن توسيعها عند توفر كتالوج مركزي */
export const PLATFORM_SERVICE_CODE_OPTIONS = [
  'oil_change',
  'tire_rotation',
  'wash',
  'SRV-OIL',
  'service',
] as const

/** كميات شائعة لطلبات التسعير */
export const PLATFORM_PRICING_QUANTITY_PRESETS = [0.5, 1, 2, 5, 10, 25, 100] as const

/** نطاق «حداثة» مرشّحات مركز الحوادث (بالساعات) */
export const PLATFORM_INCIDENT_FRESH_HOURS_OPTIONS = [6, 12, 24, 48, 72, 168] as const
