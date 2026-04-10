const http = require('http');
const { performance } = require('perf_hooks');
const fs = require('fs');
const path = require('path');

const BASE_HOST = process.env.BENCH_HOST || 'localhost';
const BASE_PORT = Number(process.env.BENCH_PORT || 80);
const API_PREFIX = '/api/v1';
const TOTAL_REQUESTS = Number(process.env.BENCH_TOTAL || 10000);
const CONCURRENCY = Number(process.env.BENCH_CONCURRENCY || 200);
const OUT_FILE = process.env.BENCH_OUT || path.join(__dirname, 'mixed_benchmark_after_fix.json');

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
    const headers = {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    };
    if (token) headers.Authorization = `Bearer ${token}`;
    if (payload) headers['Content-Length'] = Buffer.byteLength(payload);

    const req = http.request(
      {
        host: BASE_HOST,
        port: BASE_PORT,
        path: `${API_PREFIX}${pathName}`,
        method,
        headers,
        timeout: 15000,
      },
      (res) => {
        let b = '';
        res.on('data', (d) => (b += d));
        res.on('end', () => {
          const took = performance.now() - started;
          resolve({ status: res.statusCode, body: b, ms: took });
        });
      }
    );

    req.on('timeout', () => req.destroy(new Error('timeout')));
    req.on('error', reject);
    if (payload) req.write(payload);
    req.end();
  });
}

async function login() {
  const res = await requestJson('POST', '/auth/login', {
    email: process.env.BENCH_EMAIL || 'owner@demo.sa',
    password: process.env.BENCH_PASSWORD || 'password123',
  });
  if (res.status !== 200) {
    throw new Error(`Login failed with status ${res.status}`);
  }
  const parsed = JSON.parse(res.body);
  const token = parsed?.token || parsed?.data?.token;
  if (!token) throw new Error('No token in login response');
  return token;
}

async function main() {
  const token = await login();

  const endpointStats = Object.fromEntries(
    ENDPOINTS.map((ep) => [
      ep,
      { count: 0, times: [], errors: 0, http5xx: 0, network: 0, success: 0 },
    ])
  );

  const allTimes = [];
  let success = 0;
  let fail = 0;
  let http5xx = 0;
  let networkFailures = 0;
  let completed = 0;
  let cursor = 0;

  const startedAt = performance.now();

  async function worker() {
    while (true) {
      const idx = cursor++;
      if (idx >= TOTAL_REQUESTS) break;
      const ep = ENDPOINTS[idx % ENDPOINTS.length];
      const epStat = endpointStats[ep];
      epStat.count += 1;

      try {
        const r = await requestJson('GET', ep, null, token);
        const ms = Number(r.ms.toFixed(2));
        allTimes.push(ms);
        epStat.times.push(ms);
        completed += 1;

        const ok = r.status >= 200 && r.status < 500;
        if (ok) {
          success += 1;
          epStat.success += 1;
        } else {
          fail += 1;
          epStat.errors += 1;
        }
        if (r.status >= 500) {
          http5xx += 1;
          epStat.http5xx += 1;
        }
      } catch (_err) {
        completed += 1;
        fail += 1;
        networkFailures += 1;
        epStat.errors += 1;
        epStat.network += 1;
      }
    }
  }

  await Promise.all(Array.from({ length: CONCURRENCY }, () => worker()));
  const durationSec = (performance.now() - startedAt) / 1000;

  const sortedAll = [...allTimes].sort((a, b) => a - b);
  const overall = {
    total_requests: TOTAL_REQUESTS,
    completed,
    success,
    fail,
    http_5xx: http5xx,
    network_failures: networkFailures,
    avg_ms: Number((allTimes.reduce((a, b) => a + b, 0) / (allTimes.length || 1)).toFixed(2)),
    p50_ms: Number(percentile(sortedAll, 50).toFixed(2)),
    p95_ms: Number(percentile(sortedAll, 95).toFixed(2)),
    p99_ms: Number(percentile(sortedAll, 99).toFixed(2)),
    max_ms: Number((sortedAll[sortedAll.length - 1] || 0).toFixed(2)),
    throughput_rps: Number((TOTAL_REQUESTS / durationSec).toFixed(2)),
    duration_sec: Number(durationSec.toFixed(2)),
    concurrency: CONCURRENCY,
  };

  const perEndpoint = Object.entries(endpointStats).map(([endpoint, stat]) => {
    const sorted = [...stat.times].sort((a, b) => a - b);
    const avg = stat.times.length
      ? stat.times.reduce((a, b) => a + b, 0) / stat.times.length
      : 0;
    return {
      endpoint,
      count: stat.count,
      avg_ms: Number(avg.toFixed(2)),
      p95_ms: Number(percentile(sorted, 95).toFixed(2)),
      p99_ms: Number(percentile(sorted, 99).toFixed(2)),
      errors: stat.errors,
      http_5xx: stat.http5xx,
      network_failures: stat.network,
    };
  });

  perEndpoint.sort((a, b) => b.p95_ms - a.p95_ms);

  const result = {
    scenario: 'mixed_10000_200_after_view_fix',
    captured_at: new Date().toISOString(),
    overall,
    slowest_endpoints_by_p95: perEndpoint.slice(0, 5),
    per_endpoint: perEndpoint,
  };

  fs.writeFileSync(OUT_FILE, JSON.stringify(result, null, 2), 'utf8');
  console.log(JSON.stringify(result, null, 2));
  console.log(`\nSaved: ${OUT_FILE}`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

