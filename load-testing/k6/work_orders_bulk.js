/**
 * OSAS hot-tenant bulk work orders (single company burst).
 *
 * Official test closure and duplicate criterion: see HOT_TENANT_BULK_WORK_ORDERS.md
 * (no duplicate vehicle_id within the same work_order_batch_id; same vehicle may repeat across batches / another bulk_service_code).
 *
 * Env:
 *   K6_BASE_URL          — e.g. http://host.docker.internal/api
 *   K6_EMAIL_A / K6_PASSWORD_A — tenant user with work_orders.create + vehicles
 *   K6_BULK_VEHICLE_IDS  — comma-separated vehicle ids (e.g. 200 ids from seeding)
 *   K6_BULK_SERVICE_CODE — default oil_change
 *   K6_BULK_VUS          — virtual users (1 = Test A, 5 = Test B, 10 = Test C)
 *   K6_BULK_ITERATIONS   — total iterations shared across VUs (default = K6_BULK_VUS so each VU runs one bulk)
 *
 * Example (Test B, 5 concurrent identical bulks):
 *   docker run --rm -v "${PWD}/load-testing:/work" -w /work/k6 --add-host=host.docker.internal:host-gateway \
 *     -e K6_BASE_URL=http://host.docker.internal/api \
 *     -e K6_BULK_VEHICLE_IDS="1,2,3,..." \
 *     -e K6_BULK_VUS=5 \
 *     grafana/k6:latest run work_orders_bulk.js
 */
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Trend } from 'k6/metrics';
import { login, bearerHeaders } from './lib/auth.js';

const bulkVus = parseInt(__ENV.K6_BULK_VUS || '1', 10);
const bulkIterations = parseInt(
  __ENV.K6_BULK_ITERATIONS || String(Math.max(1, bulkVus)),
  10,
);

const bulkPostMs = new Trend('wo_bulk_post_ms');
const bulkPollMs = new Trend('wo_bulk_poll_ms');
const bulk2xx = new Counter('wo_bulk_http_2xx');
const bulk5xx = new Counter('wo_bulk_http_5xx');

export const options = {
  scenarios: {
    bulk_burst: {
      executor: 'shared-iterations',
      vus: bulkVus,
      iterations: bulkIterations,
      maxDuration: '15m',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.001'],
    checks: ['rate>0.99'],
  },
};

function parseVehicleIds() {
  const raw = (__ENV.K6_BULK_VEHICLE_IDS || '').trim();
  if (!raw) {
    return [];
  }
  return raw
    .split(',')
    .map((s) => parseInt(s.trim(), 10))
    .filter((n) => Number.isFinite(n) && n > 0);
}

function pollBatch(base, headers, pollPath) {
  const deadline = Date.now() + 10 * 60 * 1000;
  while (Date.now() < deadline) {
    const t0 = Date.now();
    const r = http.get(`${base}${pollPath}`, { headers, tags: { name: 'WorkOrderBulkPoll' } });
    bulkPollMs.add(Date.now() - t0);
    if (r.status >= 500) {
      bulk5xx.add(1);
    } else if (r.status === 200) {
      bulk2xx.add(1);
    }
    if (r.status !== 200) {
      sleep(1);
      continue;
    }
    const body = r.json();
    const st = body && body.data ? body.data.status : '';
    if (st === 'completed' || st === 'failed') {
      return { ok: st === 'completed', status: st, body };
    }
    sleep(0.5);
  }
  return { ok: false, status: 'timeout', body: null };
}

export default function () {
  const base = __ENV.K6_BASE_URL || 'http://localhost/api';
  const vehicleIds = parseVehicleIds();
  if (vehicleIds.length === 0) {
    check(null, { 'K6_BULK_VEHICLE_IDS set': () => false });
    return;
  }

  const auth = login(base, __ENV.K6_EMAIL_A || 'simulation.owner@demo.local', __ENV.K6_PASSWORD_A || 'SimulationDemo123!');
  if (!auth) {
    check(null, { 'login ok': () => false });
    return;
  }

  const headers = bearerHeaders(auth.token);
  const idem = `bulk-${__VU}-${__ITER}-${Date.now()}`;
  headers['Idempotency-Key'] = idem;

  const payload = JSON.stringify({
    vehicle_ids: vehicleIds,
    service_code: __ENV.K6_BULK_SERVICE_CODE || 'oil_change',
  });

  const t0 = Date.now();
  const post = http.post(`${base}/v1/work-orders/bulk`, payload, {
    headers,
    tags: { name: 'WorkOrderBulkPost' },
  });
  bulkPostMs.add(Date.now() - t0);

  if (post.status >= 500) {
    bulk5xx.add(1);
  } else if (post.status === 200 || post.status === 202) {
    bulk2xx.add(1);
  }

  const postOk = check(post, {
    'bulk POST 2xx': (r) => r.status === 200 || r.status === 202,
    'bulk POST under 2s': (r) => r.timings.duration < 2000,
  });

  if (!postOk || (post.status !== 200 && post.status !== 202)) {
    return;
  }

  const j = post.json();
  const pollPath = j && j.data && j.data.poll_url ? j.data.poll_url : null;
  check(pollPath, { 'poll_url present': (p) => !!p });
  if (!pollPath) {
    return;
  }

  const poll = pollBatch(base, headers, pollPath);
  check(poll, {
    'batch completed': (p) => p.ok === true,
  });
  if (poll.ok && poll.body && poll.body.data && poll.body.data.counts) {
    const c = poll.body.data.counts;
    check(c, {
      'counts.failed is 0': (x) => Number(x.failed) === 0,
      'counts.succeeded equals total': (x) =>
        Number(x.succeeded) === Number(x.total) && Number(x.total) > 0,
    });
  }
}
