const http = require('http');
const { performance } = require('perf_hooks');
const fs = require('fs');
const path = require('path');

const BASE_HOST = process.env.BENCH_HOST || 'localhost';
const BASE_PORT = Number(process.env.BENCH_PORT || 80);
const API_PREFIX = '/api/v1';
const CONCURRENCY = Number(process.env.SOAK_CONCURRENCY || 120);
const DURATION_SEC = Number(process.env.SOAK_DURATION_SEC || 1800);
const OUT_FILE = process.env.SOAK_OUT || path.join(__dirname, 'soak_30m.json');

const EMAIL = process.env.BENCH_EMAIL || 'admin@osas.sa';
const PASSWORD = process.env.BENCH_PASSWORD || '12345678';

const ENDPOINTS = [
  '/dashboard/kpi',
  '/reports/sales',
  '/invoices?per_page=20',
  '/wallet/transactions',
  '/customers?per_page=20',
  '/products?per_page=20',
  '/work-orders?per_page=10',
  '/inventory?per_page=20',
  '/purchases?per_page=20',
  '/services?per_page=20',
  '/ledger?per_page=20',
  '/subscription',
];

function percentile(sorted, p) {
  if (!sorted.length) return 0;
  const idx = Math.ceil((p / 100) * sorted.length) - 1;
  return sorted[Math.max(0, Math.min(idx, sorted.length - 1))];
}

function requestJson(method, pathName, body, token) {
  return new Promise((resolve, reject) => {
    const started = performance.now();
    const payload = body ? JSON.stringify(body) : null;
    const headers = { Accept: 'application/json', 'Content-Type': 'application/json' };
    if (token) headers.Authorization = `Bearer ${token}`;
    if (payload) headers['Content-Length'] = Buffer.byteLength(payload);

    const req = http.request(
      { host: BASE_HOST, port: BASE_PORT, path: `${API_PREFIX}${pathName}`, method, headers, timeout: 15000 },
      (res) => {
        let b = '';
        res.on('data', (d) => (b += d));
        res.on('end', () => resolve({ status: res.statusCode, body: b, ms: performance.now() - started }));
      }
    );
    req.on('timeout', () => req.destroy(new Error('timeout')));
    req.on('error', reject);
    if (payload) req.write(payload);
    req.end();
  });
}

async function login() {
  const res = await requestJson('POST', '/auth/login', { email: EMAIL, password: PASSWORD });
  if (res.status !== 200) throw new Error(`Login failed with status ${res.status}`);
  const parsed = JSON.parse(res.body);
  const token = parsed?.token || parsed?.data?.token;
  if (!token) throw new Error('No token in login response');
  return token;
}

async function main() {
  const token = await login();
  let cursor = 0;
  let total = 0;
  let success = 0;
  let fail = 0;
  let http5xx = 0;
  let networkFailures = 0;
  const allTimes = [];
  const perMinute = [];
  let minuteTimes = [];
  let minuteReqs = 0;
  let minuteErrors = 0;

  const start = Date.now();
  const end = start + DURATION_SEC * 1000;

  function closeMinute(now) {
    const sorted = [...minuteTimes].sort((a, b) => a - b);
    perMinute.push({
      at: new Date(now).toISOString(),
      requests: minuteReqs,
      errors: minuteErrors,
      avg_ms: Number((minuteTimes.reduce((a, b) => a + b, 0) / (minuteTimes.length || 1)).toFixed(2)),
      p95_ms: Number(percentile(sorted, 95).toFixed(2)),
      p99_ms: Number(percentile(sorted, 99).toFixed(2)),
    });
    minuteTimes = [];
    minuteReqs = 0;
    minuteErrors = 0;
  }

  let nextMinute = start + 60_000;

  async function worker() {
    while (Date.now() < end) {
      const ep = ENDPOINTS[cursor++ % ENDPOINTS.length];
      try {
        const r = await requestJson('GET', ep, null, token);
        const ms = Number(r.ms.toFixed(2));
        total += 1;
        allTimes.push(ms);
        minuteTimes.push(ms);
        minuteReqs += 1;
        if (r.status >= 200 && r.status < 500) success += 1;
        else {
          fail += 1;
          minuteErrors += 1;
        }
        if (r.status >= 500) http5xx += 1;
      } catch (_e) {
        total += 1;
        fail += 1;
        minuteReqs += 1;
        minuteErrors += 1;
        networkFailures += 1;
      }
      const now = Date.now();
      if (now >= nextMinute) {
        closeMinute(now);
        nextMinute += 60_000;
      }
    }
  }

  await Promise.all(Array.from({ length: CONCURRENCY }, () => worker()));
  closeMinute(Date.now());

  const durationSec = (Date.now() - start) / 1000;
  const sortedAll = [...allTimes].sort((a, b) => a - b);
  const result = {
    scenario: 'mixed_soak',
    captured_at: new Date().toISOString(),
    config: { concurrency: CONCURRENCY, duration_sec: DURATION_SEC },
    overall: {
      total_requests: total,
      success,
      fail,
      http_5xx: http5xx,
      network_failures: networkFailures,
      avg_ms: Number((allTimes.reduce((a, b) => a + b, 0) / (allTimes.length || 1)).toFixed(2)),
      p50_ms: Number(percentile(sortedAll, 50).toFixed(2)),
      p95_ms: Number(percentile(sortedAll, 95).toFixed(2)),
      p99_ms: Number(percentile(sortedAll, 99).toFixed(2)),
      max_ms: Number((sortedAll[sortedAll.length - 1] || 0).toFixed(2)),
      throughput_rps: Number((total / durationSec).toFixed(2)),
      duration_sec: Number(durationSec.toFixed(2)),
    },
    per_minute: perMinute,
  };

  fs.writeFileSync(OUT_FILE, JSON.stringify(result, null, 2), 'utf8');
  console.log(JSON.stringify(result, null, 2));
  console.log(`Saved: ${OUT_FILE}`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

