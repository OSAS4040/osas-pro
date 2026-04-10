const http = require('http');
const { performance } = require('perf_hooks');
const fs = require('fs');
const path = require('path');

const BASE_HOST = process.env.BENCH_HOST || 'localhost';
const BASE_PORT = Number(process.env.BENCH_PORT || 80);
const API_PREFIX = '/api/v1';
const TOTAL_OPS = Number(process.env.BENCH_TOTAL || 10000);
const CONCURRENCY = Number(process.env.BENCH_CONCURRENCY || 200);
const DURATION_SEC = Number(process.env.BENCH_DURATION_SEC || 3600);
const OUT_FILE = process.env.BENCH_OUT || path.join(__dirname, 'realistic_mix_10000_200.json');
const EMAIL = process.env.BENCH_EMAIL || 'admin@osas.sa';
const PASSWORD = process.env.BENCH_PASSWORD || '12345678';

const scenarioWeights = [
  { name: 'customers_vehicles', weight: 20 },
  { name: 'work_orders', weight: 25 },
  { name: 'billing_payments', weight: 20 },
  { name: 'inventory', weight: 15 },
  { name: 'purchases', weight: 10 },
  { name: 'subscription', weight: 5 },
  { name: 'reports_dashboard', weight: 5 },
];

function weightedPick(items) {
  const sum = items.reduce((a, b) => a + b.weight, 0);
  let r = Math.random() * sum;
  for (const item of items) {
    r -= item.weight;
    if (r <= 0) return item.name;
  }
  return items[items.length - 1].name;
}

function percentile(sorted, p) {
  if (!sorted.length) return 0;
  const idx = Math.ceil((p / 100) * sorted.length) - 1;
  return sorted[Math.max(0, Math.min(idx, sorted.length - 1))];
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function requestJson(method, pathName, body, token) {
  return new Promise((resolve, reject) => {
    const started = performance.now();
    const payload = body ? JSON.stringify(body) : null;
    const headers = { Accept: 'application/json', 'Content-Type': 'application/json' };
    if (token) headers.Authorization = `Bearer ${token}`;
    if (payload) headers['Content-Length'] = Buffer.byteLength(payload);

    const req = http.request(
      { host: BASE_HOST, port: BASE_PORT, path: `${API_PREFIX}${pathName}`, method, headers, timeout: 20000 },
      (res) => {
        let b = '';
        res.on('data', (d) => (b += d));
        res.on('end', () => resolve({ status: res.statusCode, body: b, ms: performance.now() - started, endpoint: pathName }));
      }
    );
    req.on('timeout', () => req.destroy(new Error('timeout')));
    req.on('error', reject);
    if (payload) req.write(payload);
    req.end();
  });
}

async function login() {
  const r = await requestJson('POST', '/auth/login', { email: EMAIL, password: PASSWORD });
  if (r.status !== 200) throw new Error(`Login failed: ${r.status}`);
  const j = JSON.parse(r.body);
  return j.token || j.data?.token;
}

function randomPlate() {
  return `TST-${Math.floor(Math.random() * 9000 + 1000)}`;
}

function randomEmail(prefix) {
  return `${prefix}.${Date.now()}.${Math.floor(Math.random() * 100000)}@load.local`;
}

async function doScenario(token, scenarioName) {
  const calls = [];

  switch (scenarioName) {
    case 'customers_vehicles':
      calls.push(() => requestJson('GET', '/customers?per_page=20', null, token));
      calls.push(() => requestJson('POST', '/customers', { name: 'Load User', phone: `05${Math.floor(Math.random() * 100000000)}`, email: randomEmail('cust'), type: 'b2c' }, token));
      calls.push(() => requestJson('GET', '/vehicles?per_page=20', null, token));
      calls.push(() => requestJson('POST', '/vehicles', { customer_id: 1, plate_number: randomPlate(), make: 'Toyota', model: 'Corolla', year: 2023 }, token));
      break;
    case 'work_orders':
      calls.push(() => requestJson('GET', '/work-orders?per_page=10', null, token));
      calls.push(() => requestJson('POST', '/work-orders', { customer_id: 1, vehicle_id: 1, description: 'load scenario order', items: [{ type: 'service', description: 'inspection', quantity: 1, unit_price: 50 }] }, token));
      calls.push(() => requestJson('PATCH', '/work-orders/1/status', { status: 'in_progress' }, token));
      calls.push(() => requestJson('PATCH', '/work-orders/1/status', { status: 'completed' }, token));
      break;
    case 'billing_payments':
      calls.push(() => requestJson('GET', '/invoices?per_page=20', null, token));
      calls.push(() => requestJson('GET', '/wallet/transactions', null, token));
      calls.push(() => requestJson('GET', '/wallet', null, token));
      // Edge case: duplicate action simulation (double click semantics).
      calls.push(() => requestJson('POST', '/pos/sale', { customer_type: 'b2c', items: [{ name: 'load-item', item_type: 'part', product_id: 1, unit_price: 10, tax_rate: 15, quantity: 1 }], payment: { method: 'cash', amount: 11.5 } }, token));
      calls.push(() => requestJson('POST', '/pos/sale', { customer_type: 'b2c', items: [{ name: 'load-item', item_type: 'part', product_id: 1, unit_price: 10, tax_rate: 15, quantity: 1 }], payment: { method: 'cash', amount: 11.5 } }, token));
      break;
    case 'inventory':
      calls.push(() => requestJson('GET', '/inventory?per_page=20', null, token));
      calls.push(() => requestJson('GET', '/inventory/movements', null, token));
      calls.push(() => requestJson('POST', '/inventory/adjustments', { product_id: 1, quantity: -1, reason: 'load_test_issue' }, token));
      break;
    case 'purchases':
      calls.push(() => requestJson('GET', '/purchases?per_page=20', null, token));
      calls.push(() => requestJson('POST', '/purchases', { supplier_id: 1, reference_number: `PO-${Date.now()}-${Math.floor(Math.random() * 1000)}`, items: [{ product_id: 1, quantity: 1, unit_cost: 8 }] }, token));
      break;
    case 'subscription':
      calls.push(() => requestJson('GET', '/subscription', null, token));
      calls.push(() => requestJson('GET', '/plans', null, token));
      break;
    default:
      calls.push(() => requestJson('GET', '/dashboard/kpi', null, token));
      calls.push(() => requestJson('GET', '/reports/kpi', null, token));
      break;
  }

  // Human behavior: page nav/back/refresh style reads.
  if (Math.random() < 0.25) calls.push(() => requestJson('GET', '/dashboard/kpi', null, token));
  if (Math.random() < 0.15) calls.push(() => requestJson('GET', '/customers?per_page=20', null, token));

  const responses = [];
  for (const call of calls) {
    responses.push(await call());
    await sleep(1000 + Math.floor(Math.random() * 2000));
  }
  return responses;
}

async function main() {
  const token = await login();
  const scenarioStats = {};
  const endpointStats = {};
  const allTimes = [];
  let success = 0;
  let fail = 0;
  let http5xx = 0;
  let networkFailures = 0;
  let totalDone = 0;

  for (const s of scenarioWeights) {
    scenarioStats[s.name] = { count: 0, errors: 0 };
  }

  const start = Date.now();
  const end = start + DURATION_SEC * 1000;
  const targetRatePerSec = TOTAL_OPS / DURATION_SEC;

  async function worker() {
    await sleep(Math.floor(Math.random() * 3000));
    while (Date.now() < end && totalDone < TOTAL_OPS) {
      const elapsed = (Date.now() - start) / 1000;
      const allowed = Math.floor(elapsed * targetRatePerSec);
      if (totalDone >= allowed) {
        await sleep(500);
        continue;
      }

      const scenario = weightedPick(scenarioWeights);
      scenarioStats[scenario].count += 1;
      try {
        const results = await doScenario(token, scenario);
        for (const r of results) {
          totalDone += 1;
          const ms = Number(r.ms.toFixed(2));
          allTimes.push(ms);
          const ep = r.endpoint;
          if (!endpointStats[ep]) endpointStats[ep] = { count: 0, errors: 0, http5xx: 0, network: 0, times: [] };
          endpointStats[ep].count += 1;
          endpointStats[ep].times.push(ms);
          const ok = r.status >= 200 && r.status < 500;
          if (ok) success += 1;
          else {
            fail += 1;
            endpointStats[ep].errors += 1;
            scenarioStats[scenario].errors += 1;
          }
          if (r.status >= 500) {
            http5xx += 1;
            endpointStats[ep].http5xx += 1;
          }
          if (totalDone >= TOTAL_OPS) break;
        }
      } catch (_e) {
        totalDone += 1;
        fail += 1;
        networkFailures += 1;
        scenarioStats[scenario].errors += 1;
      }
    }
  }

  await Promise.all(Array.from({ length: CONCURRENCY }, () => worker()));
  const duration = (Date.now() - start) / 1000;
  const sorted = [...allTimes].sort((a, b) => a - b);

  const perEndpoint = Object.entries(endpointStats).map(([endpoint, stat]) => {
    const s = [...stat.times].sort((a, b) => a - b);
    return {
      endpoint,
      count: stat.count,
      avg_ms: Number((stat.times.reduce((a, b) => a + b, 0) / (stat.times.length || 1)).toFixed(2)),
      p95_ms: Number(percentile(s, 95).toFixed(2)),
      p99_ms: Number(percentile(s, 99).toFixed(2)),
      errors: stat.errors,
      http_5xx: stat.http5xx,
      network_failures: stat.network,
    };
  }).sort((a, b) => b.p95_ms - a.p95_ms);

  const out = {
    scenario: 'realistic_mix_10000_200',
    captured_at: new Date().toISOString(),
    config: { total_ops: TOTAL_OPS, concurrency: CONCURRENCY, duration_sec: DURATION_SEC, think_time: '1-3s' },
    overall: {
      total_requests: totalDone,
      success,
      fail,
      http_5xx: http5xx,
      network_failures: networkFailures,
      avg_ms: Number((allTimes.reduce((a, b) => a + b, 0) / (allTimes.length || 1)).toFixed(2)),
      p50_ms: Number(percentile(sorted, 50).toFixed(2)),
      p95_ms: Number(percentile(sorted, 95).toFixed(2)),
      p99_ms: Number(percentile(sorted, 99).toFixed(2)),
      max_ms: Number((sorted[sorted.length - 1] || 0).toFixed(2)),
      throughput_rps: Number((totalDone / duration).toFixed(2)),
      duration_sec: Number(duration.toFixed(2)),
    },
    scenario_distribution: scenarioStats,
    per_endpoint: perEndpoint,
    slowest_endpoints_by_p95: perEndpoint.slice(0, 8),
  };

  fs.writeFileSync(OUT_FILE, JSON.stringify(out, null, 2), 'utf8');
  console.log(JSON.stringify(out, null, 2));
  console.log(`Saved: ${OUT_FILE}`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

