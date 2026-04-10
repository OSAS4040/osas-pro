import { Rate, Counter, Trend } from 'k6/metrics';

/** أخطاء خادم 5xx فقط — لا يُحتسب 403 العزل أو 422 التحقق */
export const serverErrors5xx = new Rate('server_errors_5xx');

/** حالات بدون استجابة (مهلة، قطع اتصال، إلخ) */
export const clientTimeoutOrNetwork = new Rate('client_timeout_or_network');

/** نجاح مسارات POS الناجحة (عادة 201) */
export const posSaleOk = new Rate('pos_sale_2xx');

/** رفض الوصول عبر شركة أخرى (403/404) — مطلوب للعزل */
export const tenantIsolationBlocked = new Rate('tenant_isolation_403');

export const healthDegradedOk = new Rate('health_ok');

export const invoiceFollowMs = new Trend('invoice_follow_ms');

/** إعادة نفس مفتاح Idempotency بجسمة مختلفة → يُتوقع 409 */
export const idempotencyPayloadMismatch409 = new Rate('idempotency_409_payload_mismatch');

export const setupLoginFailures = new Counter('setup_login_failures');

export const posSkippedCatalog = new Counter('pos_skipped_no_catalog');

/** تصنيف صريح للأخطاء (لكل طلب HTTP يُسجَّل صنف واحد من «عدم النجاح» عبر Rate) */
export const errorTypeNetwork = new Rate('error_type_network');
export const errorTypeServer5xx = new Rate('error_type_server_5xx');
export const errorTypeClient4xx = new Rate('error_type_client_4xx');

/**
 * Read-after-write: بعد POS 201، هل تُرجع GET /invoices/{id} نفس المعرف وحالة متسقة؟
 * (يُضاف فقط عند نجاح إنشاء فاتورة بمعرف معروف)
 */
export const rawReadAfterWriteOk = new Rate('raw_read_after_write_ok');

/**
 * نجاح HTTP «تشغيلي» — يستثني احتساب 403/404 على مسار العزل كفشل.
 * يُستخدم لعتبات smoke/normal بدل الاعتماد فقط على http_req_failed (الذي يعدّ 4xx فشلاً).
 */
export const operationalHttpSuccess = new Rate('operational_http_success');

/** زمن ونجاح حسب نوع السيناريو — يظهر في latest-summary.json دون الاعتماد على وسوم HTTP في الملخص */
export const scenReadMixHttpMs = new Trend('scen_read_mix_http_ms');
export const readMixOperationalOk = new Rate('read_mix_operational_ok');
export const scenPosPostHttpMs = new Trend('scen_pos_post_http_ms');
export const scenPosInvoiceGetHttpMs = new Trend('scen_pos_invoice_get_http_ms');
export const scenIsolationHttpMs = new Trend('scen_isolation_http_ms');

export const soakReadSeg1HttpMs = new Trend('soak_read_seg1_http_ms');
export const soakReadSeg2HttpMs = new Trend('soak_read_seg2_http_ms');
export const soakReadSeg3HttpMs = new Trend('soak_read_seg3_http_ms');
