import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';

// ── Custom Metrics ────────────────────────────────────────────────────────
const errorRate      = new Rate('error_rate');
const invoiceLatency = new Trend('invoice_latency', true);
const kpiLatency     = new Trend('kpi_latency', true);
const walletLatency  = new Trend('wallet_latency', true);
const authLatency    = new Trend('auth_latency', true);
const posLatency     = new Trend('pos_latency', true);
const totalErrors    = new Counter('total_errors');

// ── Test Config ───────────────────────────────────────────────────────────
const BASE = 'http://saas_nginx/api/v1';

// Staged load: ramp up → sustain → ramp up more → peak → ramp down
export const options = {
  stages: [
    { duration: '30s', target: 50   },   // Warm-up
    { duration: '60s', target: 200  },   // Normal load
    { duration: '60s', target: 500  },   // Medium load
    { duration: '60s', target: 1000 },   // High load
    { duration: '60s', target: 2000 },   // Stress
    { duration: '60s', target: 3000 },   // Heavy stress
    { duration: '30s', target: 0    },   // Ramp down
  ],
  thresholds: {
    http_req_duration:      ['p(95)<2000', 'p(99)<5000'],
    http_req_failed:        ['rate<0.05'],
    error_rate:             ['rate<0.05'],
    kpi_latency:            ['p(95)<3000'],
    invoice_latency:        ['p(95)<3000'],
    wallet_latency:         ['p(95)<2000'],
  },
  summaryTrendStats: ['min', 'med', 'avg', 'p(90)', 'p(95)', 'p(99)', 'max'],
};

// ── Auth: get token ───────────────────────────────────────────────────────
function getToken() {
  const res = http.post(`${BASE}/auth/login`,
    JSON.stringify({ email: 'owner@demo.sa', password: 'password123' }),
    { headers: { 'Content-Type': 'application/json' } }
  );
  authLatency.add(res.timings.duration);
  const ok = check(res, { 'login 200': (r) => r.status === 200 });
  if (!ok) { errorRate.add(1); totalErrors.add(1); return null; }
  errorRate.add(0);
  try { return res.json('data.token') || res.json('token'); }
  catch { return null; }
}

// ── Main VU Loop ──────────────────────────────────────────────────────────
export default function () {
  const token = getToken();
  if (!token) { sleep(1); return; }

  const headers = {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };

  // ── Scenario 1: Health check (baseline) ──────────────────────────────
  group('health_check', () => {
    const r = http.get(`${BASE}/health`, { headers });
    const ok = check(r, { 'health 200': (x) => x.status === 200 });
    errorRate.add(!ok ? 1 : 0);
    if (!ok) totalErrors.add(1);
  });

  // ── Scenario 2: KPI Dashboard ─────────────────────────────────────────
  group('kpi_dashboard', () => {
    const t0 = Date.now();
    const r = http.get(`${BASE}/reports/kpi`, { headers });
    kpiLatency.add(Date.now() - t0);
    const ok = check(r, { 'kpi 200': (x) => x.status === 200 || x.status === 403 });
    errorRate.add(!ok ? 1 : 0);
    if (!ok) totalErrors.add(1);
  });

  // ── Scenario 3: Invoice List (ZATCA) ─────────────────────────────────
  group('invoice_list', () => {
    const t0 = Date.now();
    const r = http.get(`${BASE}/invoices?per_page=20`, { headers });
    invoiceLatency.add(Date.now() - t0);
    const ok = check(r, { 'invoices 200': (x) => x.status === 200 || x.status === 403 });
    errorRate.add(!ok ? 1 : 0);
    if (!ok) totalErrors.add(1);
  });

  // ── Scenario 4: Work Orders list ─────────────────────────────────────
  group('work_orders', () => {
    const r = http.get(`${BASE}/work-orders?per_page=10`, { headers });
    const ok = check(r, { 'wo 200': (x) => x.status === 200 || x.status === 403 });
    errorRate.add(!ok ? 1 : 0);
    if (!ok) totalErrors.add(1);
  });

  // ── Scenario 5: Wallet / Financial ───────────────────────────────────
  group('wallet_query', () => {
    const t0 = Date.now();
    const r = http.get(`${BASE}/wallet/transactions`, { headers });
    walletLatency.add(Date.now() - t0);
    const ok = check(r, { 'wallet 200': (x) => x.status === 200 || x.status === 403 });
    errorRate.add(!ok ? 1 : 0);
    if (!ok) totalErrors.add(1);
  });

  // ── Scenario 6: Customers list ───────────────────────────────────────
  group('customers', () => {
    const r = http.get(`${BASE}/customers?per_page=20`, { headers });
    const ok = check(r, { 'customers 200': (x) => x.status === 200 || x.status === 403 });
    errorRate.add(!ok ? 1 : 0);
    if (!ok) totalErrors.add(1);
  });

  // ── Scenario 7: Products / POS lookup ────────────────────────────────
  group('products_pos', () => {
    const t0 = Date.now();
    const r = http.get(`${BASE}/products?per_page=20`, { headers });
    posLatency.add(Date.now() - t0);
    const ok = check(r, { 'products 200': (x) => x.status === 200 || x.status === 403 });
    errorRate.add(!ok ? 1 : 0);
    if (!ok) totalErrors.add(1);
  });

  sleep(Math.random() * 0.5 + 0.1); // realistic think-time: 0.1-0.6s
}

// ── Summary Handler ───────────────────────────────────────────────────────
export function handleSummary(data) {
  const m   = data.metrics;
  const dur = (key) => {
    const t = m[key];
    if (!t) return 'N/A';
    return `avg=${t.values.avg?.toFixed(1)}ms p50=${t.values.med?.toFixed(1)}ms p95=${t.values['p(95)']?.toFixed(1)}ms p99=${t.values['p(99)']?.toFixed(1)}ms max=${t.values.max?.toFixed(1)}ms`;
  };

  const reqs      = m.http_reqs?.values?.count || 0;
  const rps       = m.http_reqs?.values?.rate?.toFixed(2) || 0;
  const failRate  = ((m.http_req_failed?.values?.rate || 0) * 100).toFixed(2);
  const errRate   = ((m.error_rate?.values?.rate || 0) * 100).toFixed(2);
  const totalReq  = m.http_req_duration;
  const peakVUs   = m.vus_max?.values?.max || 0;

  const report = `
╔══════════════════════════════════════════════════════════════════════════════╗
║            OSAS LOAD TEST — PERFORMANCE REPORT                             ║
║            Date: ${new Date().toISOString().split('T')[0]}   Peak VUs: ${peakVUs.toString().padEnd(6)}                     ║
╠══════════════════════════════════════════════════════════════════════════════╣
║  OVERALL THROUGHPUT                                                         ║
║  Total Requests : ${String(reqs).padEnd(12)} Req/sec: ${String(rps).padEnd(10)}                   ║
║  Error Rate     : ${String(failRate + '%').padEnd(12)} Custom Errors: ${String(errRate + '%').padEnd(8)}                 ║
╠══════════════════════════════════════════════════════════════════════════════╣
║  LATENCY BREAKDOWN (all endpoints)                                          ║
║  ${dur('http_req_duration').padEnd(73)}║
╠══════════════════════════════════════════════════════════════════════════════╣
║  SCENARIO LATENCIES                                                         ║
║  KPI Dashboard  : ${dur('kpi_latency').padEnd(55)}║
║  Invoices ZATCA : ${dur('invoice_latency').padEnd(55)}║
║  Wallet/Finance : ${dur('wallet_latency').padEnd(55)}║
║  Auth Login     : ${dur('auth_latency').padEnd(55)}║
║  POS Products   : ${dur('pos_latency').padEnd(55)}║
╠══════════════════════════════════════════════════════════════════════════════╣
║  THRESHOLD RESULTS                                                          ║
║  p95 < 2000ms   : ${(totalReq?.values['p(95)'] < 2000 ? '✅ PASS' : '❌ FAIL').padEnd(55)}║
║  p99 < 5000ms   : ${(totalReq?.values['p(99)'] < 5000 ? '✅ PASS' : '❌ FAIL').padEnd(55)}║
║  Error < 5%     : ${(parseFloat(failRate) < 5 ? '✅ PASS' : '❌ FAIL').padEnd(55)}║
╚══════════════════════════════════════════════════════════════════════════════╝
`;

  return {
    stdout: report,
    '/tmp/osas_load_report.json': JSON.stringify(data, null, 2),
  };
}
