/**
 * معايير قبول ثابتة — تُدمج في خيارات k6 حسب الملف الشخصي (profile).
 * أسماء المقاييس يجب أن تطابق lib/metrics.js وطلبات HTTP الافتراضية.
 */

/** معايير توثيق التدهور/الانهيار في تقرير stress (يجب أن تبقى متزامنة مع lib/report.js) */
export const STRESS_REPORT_CRITERIA = {
  /** أول «تدهور غير مقبول» للمرحلة */
  degradeFailRate: 0.05,
  degradeP95Ms: 8000,
  /** «انهيار» تقريبي */
  collapseFailRate: 0.25,
  collapseP95Ms: 30000,
};

/** @typedef {Record<string, string[]>} K6Thresholds */

/** دخان: صارم — خطأ قليل، لا 5xx، زمن مقبول */
export const THRESHOLDS_SMOKE = /** @type {K6Thresholds} */ ({
  /** لا نعتمد http_req_failed هنا: 403 العزل يُحسب فشلاً في k6 رغم أنه سلوك متوقع */
  operational_http_success: ['rate>0.99'],
  server_errors_5xx: ['rate<0.005'],
  client_timeout_or_network: ['rate<0.01'],
  http_req_duration: ['p(50)<600', 'p(95)<2200', 'p(99)<6000'],
  health_ok: ['rate>0.85'],
  tenant_isolation_403: ['rate>0.90'],
  /** يُقاس فقط عند نجاح أول بيع في سيناريو idempotency */
  idempotency_409_payload_mismatch: ['rate>0.80'],
  /** بعد POS 201 — قراءة فورية لنفس معرف الفاتورة */
  raw_read_after_write_ok: ['rate>0.80'],
});

/** حمل اعتيادي: معدل أخطاء منخفض، p95 محكوم */
export const THRESHOLDS_NORMAL = /** @type {K6Thresholds} */ ({
  operational_http_success: ['rate>0.985'],
  server_errors_5xx: ['rate<0.01'],
  client_timeout_or_network: ['rate<0.02'],
  http_req_duration: ['p(50)<800', 'p(95)<3500', 'p(99)<8000'],
  health_ok: ['rate>0.80'],
  tenant_isolation_403: ['rate>0.90'],
  pos_sale_2xx: ['rate>0.75'],
  idempotency_409_payload_mismatch: ['rate>0.85'],
  raw_read_after_write_ok: ['rate>0.85'],
});

/**
 * بوابة التحقق بعد تحسين POS / البنية (معايير صارمة — تستخدم مع K6_PROFILE=verification).
 * 5xx: عملياً صفر؛ p99 للطلبات الإجمالية ≤ 1.5s؛ نجاح POS ≥ 99.9%.
 */
export const THRESHOLDS_VERIFICATION = /** @type {K6Thresholds} */ ({
  operational_http_success: ['rate>=0.999'],
  server_errors_5xx: ['rate<0.000001'],
  client_timeout_or_network: ['rate<0.02'],
  http_req_duration: ['p(50)<600', 'p(95)<1000', 'p(99)<1500'],
  health_ok: ['rate>0.70'],
  tenant_isolation_403: ['rate>0.85'],
  pos_sale_2xx: ['rate>0.999'],
  idempotency_409_payload_mismatch: ['rate>0.85'],
  raw_read_after_write_ok: ['rate>0.85'],
});

/** ذروة: أكثر تساهلاً في الزمن، لكن 5xx و timeouts محكومة */
export const THRESHOLDS_PEAK = /** @type {K6Thresholds} */ ({
  operational_http_success: ['rate>0.97'],
  server_errors_5xx: ['rate<0.02'],
  client_timeout_or_network: ['rate<0.04'],
  http_req_duration: ['p(50)<1200', 'p(95)<6000', 'p(99)<15000'],
  health_ok: ['rate>0.70'],
  tenant_isolation_403: ['rate>0.85'],
  pos_sale_2xx: ['rate>0.60'],
  raw_read_after_write_ok: ['rate>0.75'],
});

/**
 * قياس سعة POS (capacity_pos): عتبات مرخية جداً — الهدف تسجيل p99/5xx وليس «بوابة قبول».
 * استخدم مع K6_CAPACITY_POS_RATE وشغّل عدة مرات (مثلاً 3 ثم 5 ثم 7) وقارِن التقارير.
 */
export const THRESHOLDS_CAPACITY_POS = /** @type {K6Thresholds} */ ({
  http_req_failed: ['rate<0.995'],
  server_errors_5xx: ['rate<0.99'],
  client_timeout_or_network: ['rate<0.99'],
  http_req_duration: ['p(99)<120000'],
});

/**
 * إجهاد: نسمح بارتفاع الأخطاء لاستكمال المنحنى — عدم إيقاف التشغيل عند أول خرق.
 * يُحلّل التقرير يدوياً/آلياً لنقطة التدهور.
 */
export const THRESHOLDS_STRESS = /** @type {K6Thresholds} */ ({
  http_req_failed: ['rate<0.35'],
  server_errors_5xx: ['rate<0.25'],
  client_timeout_or_network: ['rate<0.20'],
  http_req_duration: ['p(99)<60000'],
  tenant_isolation_403: ['rate>0.70'],
});

/** قفزة: مثل الذروة مع حد أعلى قليلاً للفشل المؤقت */
export const THRESHOLDS_SPIKE = /** @type {K6Thresholds} */ ({
  operational_http_success: ['rate>0.96'],
  server_errors_5xx: ['rate<0.03'],
  client_timeout_or_network: ['rate<0.06'],
  http_req_duration: ['p(50)<1500', 'p(95)<8000', 'p(99)<20000'],
  health_ok: ['rate>0.65'],
  tenant_isolation_403: ['rate>0.80'],
  pos_sale_2xx: ['rate>0.50'],
});

/** نقع: استقرار زمن طويل — أخطاء منخفضة جداً */
export const THRESHOLDS_SOAK = /** @type {K6Thresholds} */ ({
  operational_http_success: ['rate>0.99'],
  server_errors_5xx: ['rate<0.01'],
  client_timeout_or_network: ['rate<0.015'],
  http_req_duration: ['p(50)<900', 'p(95)<4000', 'p(99)<10000'],
  health_ok: ['rate>0.75'],
  tenant_isolation_403: ['rate>0.88'],
  pos_sale_2xx: ['rate>0.70'],
  raw_read_after_write_ok: ['rate>0.80'],
});

export const PROFILE_LABELS = {
  smoke: 'Smoke — 8 VU, 4 دقائق (ضمن 5–10 / 3–5)',
  normal: 'Normal Load — 30 VU, 15 دقيقة (20–40 / 10–20)',
  capacity_pos:
    'Capacity POS — بيع POS فقط (معدّل ثابت عبر K6_CAPACITY_POS_RATE)، عتبات استكشافية — قارِن التقارير بين المعدلات',
  verification: 'Verification Gate — 150 VU read + POS، ~16 دقيقة (عتبات صارمة بعد التحسين)',
  peak: 'Peak Load — 75 VU read + POS، 12 دقيقة (50–100 / 10–15)',
  stress: 'Stress — تصعيد 15→130 VU على مراحل',
  spike: 'Spike — قفزة 10→95 VU ثم هبوط',
  soak: 'Soak — 22 VU قراءة × 3 شرائح 30 دقيقة + POS/صحة/عزل موازية (إجمالي ~90 دقيقة)',
};
