/**
 * تعريف رحلات تشغيلية (SLO) لتقرير تنفيذي أدق.
 * كل رحلة ترتبط بمقياس p95 ونسبة نجاح متوقعة.
 */
export const JOURNEY_SLOS = [
  {
    id: 'reads',
    label: 'قراءات تشغيلية',
    p95Metric: 'scen_read_mix_http_ms',
    p95LimitMs: 900,
    successMetric: 'read_mix_operational_ok',
    successMinRate: 0.99,
  },
  {
    id: 'pos_sale',
    label: 'POS (POST /v1/pos/sale)',
    p95Metric: 'scen_pos_post_http_ms',
    p95LimitMs: 1200,
    successMetric: 'pos_sale_2xx',
    successMinRate: 0.98,
  },
  {
    id: 'invoice_raw',
    label: 'Read-after-write بعد POS',
    p95Metric: 'scen_pos_invoice_get_http_ms',
    p95LimitMs: 1000,
    successMetric: 'raw_read_after_write_ok',
    successMinRate: 0.98,
  },
  {
    id: 'tenant_isolation',
    label: 'عزل المستأجر (403/404 متوقع)',
    p95Metric: 'scen_isolation_http_ms',
    p95LimitMs: 900,
    successMetric: 'tenant_isolation_403',
    successMinRate: 0.98,
  },
];

