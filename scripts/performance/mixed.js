/**
 * Progressive mixed API load (read-heavy + optional WO creates).
 * Run via Docker k6; set LOAD_LEVEL=0..7, K6_EMAIL, K6_PASSWORD.
 * BASE_URL: use http://nginx (same Docker network as backend) for accurate routing; host.docker.internal can hit a different :80 on the host.
 *
 * LOAD_LEVEL:
 * 0 smoke, 1 baseline, 2 low, 3 normal, 4 peak, 5 stress, 6 soak (short), 7 soak long (35m)
 */
import http from 'k6/http';
import { check, sleep } from 'k6';
import { login, authHeaders, fetchFirstCustomerVehicle } from './common.js';

const level = __ENV.LOAD_LEVEL || '0';

function optionsForLevel() {
  const strictFail = {
    http_req_failed: ['rate<0.01'],
    'http_req_duration{name:auth_login}': ['p(95)<10000'],
    'http_req_duration{name:work_orders_list}': ['p(95)<12000'],
    'http_req_duration{name:invoices_list}': ['p(95)<12000'],
    'http_req_duration{name:dashboard_summary}': ['p(95)<12000'],
    'http_req_duration{name:health}': ['p(95)<5000'],
  };
  const relaxed = {
    http_req_failed: ['rate<0.15'],
    'http_req_duration{name:work_orders_list}': ['p(99)<30000'],
  };
  const stress = {
    http_req_failed: ['rate<0.35'],
  };

  switch (level) {
    case '0':
      return {
        summaryTrendStats: ['avg', 'min', 'med', 'max', 'p(90)', 'p(95)', 'p(99)'],
        scenarios: {
          main: { executor: 'constant-vus', vus: 5, duration: '2m' },
        },
        thresholds: strictFail,
      };
    case '1':
      return {
        summaryTrendStats: ['avg', 'min', 'med', 'max', 'p(90)', 'p(95)', 'p(99)'],
        scenarios: {
          main: { executor: 'constant-vus', vus: 10, duration: '5m' },
        },
        thresholds: strictFail,
      };
    case '2':
      return {
        summaryTrendStats: ['avg', 'min', 'med', 'max', 'p(90)', 'p(95)', 'p(99)'],
        scenarios: {
          main: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
              { duration: '2m', target: 10 },
              { duration: '2m', target: 25 },
              { duration: '4m', target: 25 },
            ],
            gracefulRampDown: '30s',
          },
        },
        thresholds: strictFail,
      };
    case '3':
      return {
        summaryTrendStats: ['avg', 'min', 'med', 'max', 'p(90)', 'p(95)', 'p(99)'],
        scenarios: {
          main: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
              { duration: '2m', target: 20 },
              { duration: '2m', target: 35 },
              { duration: '2m', target: 50 },
              { duration: '4m', target: 50 },
            ],
            gracefulRampDown: '45s',
          },
        },
        thresholds: relaxed,
      };
    case '4':
      return {
        summaryTrendStats: ['avg', 'min', 'med', 'p(50)', 'max', 'p(90)', 'p(95)', 'p(99)'],
        scenarios: {
          main: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
              { duration: '2m', target: 30 },
              { duration: '3m', target: 70 },
              { duration: '3m', target: 100 },
              { duration: '4m', target: 100 },
            ],
            gracefulRampDown: '60s',
          },
        },
        thresholds: relaxed,
      };
    case '5':
      return {
        summaryTrendStats: ['avg', 'min', 'med', 'p(50)', 'max', 'p(90)', 'p(95)', 'p(99)'],
        scenarios: {
          main: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
              { duration: '2m', target: 50 },
              { duration: '2m', target: 100 },
              { duration: '2m', target: 150 },
              { duration: '4m', target: 150 },
            ],
            gracefulRampDown: '60s',
          },
        },
        thresholds: stress,
      };
    case '6':
      return {
        summaryTrendStats: ['avg', 'min', 'med', 'p(50)', 'max', 'p(90)', 'p(95)', 'p(99)'],
        scenarios: {
          main: { executor: 'constant-vus', vus: 30, duration: '10m' },
        },
        thresholds: relaxed,
      };
    case '7':
      return {
        summaryTrendStats: ['avg', 'min', 'med', 'p(50)', 'max', 'p(90)', 'p(95)', 'p(99)'],
        scenarios: {
          main: { executor: 'constant-vus', vus: 30, duration: '35m' },
        },
        thresholds: relaxed,
      };
    default:
      return {
        summaryTrendStats: ['avg', 'min', 'med', 'max', 'p(90)', 'p(95)', 'p(99)'],
        scenarios: {
          main: { executor: 'constant-vus', vus: 5, duration: '1m' },
        },
        thresholds: strictFail,
      };
  }
}

export const options = optionsForLevel();

export function setup() {
  const baseUrl = (__ENV.BASE_URL || 'http://host.docker.internal').replace(/\/$/, '');
  const email = __ENV.K6_EMAIL || 'owner@demo.sa';
  const password = __ENV.K6_PASSWORD || 'password';

  const lr = login(baseUrl, email, password);
  if (!lr.ok) {
    throw new Error(`login failed: ${lr.status} ${lr.body}`);
  }
  const headers = authHeaders(lr.token);
  const { customerId, vehicleId, vehiclePlate } = fetchFirstCustomerVehicle(baseUrl, headers);

  return {
    baseUrl,
    headers,
    customerId,
    vehicleId,
    vehiclePlate,
    writeEnabled: !!(customerId && vehicleId),
  };
}

function doWriteWorkOrder(data) {
  if (!data.writeEnabled) return;
  const tag = { tags: { name: 'work_order_create' } };
  const body = {
    customer_id: data.customerId,
    vehicle_id: data.vehicleId,
    items: [
      {
        item_type: 'labor',
        name: `k6 load ${__VU}-${__ITER}-${Date.now()}`,
        quantity: 1,
        unit_price: 10,
        tax_rate: 15,
      },
    ],
  };
  if (data.vehiclePlate) {
    body.vehicle_plate = data.vehiclePlate;
  }
  const payload = JSON.stringify(body);
  const res = http.post(`${data.baseUrl}/api/v1/work-orders`, payload, {
    headers: data.headers,
    ...tag,
  });
  check(res, {
    'wo_create 2xx': (r) => r.status >= 200 && r.status < 300,
  });
}

export default function (data) {
  const base = data.baseUrl;
  const h = data.headers;
  const r = Math.random();

  if (r < 0.06) {
    const res = http.get(`${base}/api/v1/health`, { tags: { name: 'health' } });
    check(res, { 'health 200': (x) => x.status === 200 });
  } else if (r < 0.10) {
    const res = http.get(`${base}/api/v1/system/version`, { tags: { name: 'system_version' } });
    check(res, { 'version 200': (x) => x.status === 200 });
  } else if (r < 0.58) {
    const res = http.get(`${base}/api/v1/work-orders?per_page=15`, {
      headers: h,
      tags: { name: 'work_orders_list' },
    });
    check(res, { 'wo_list 200': (x) => x.status === 200 });
  } else if (r < 0.83) {
    const res = http.get(`${base}/api/v1/invoices?per_page=15`, {
      headers: h,
      tags: { name: 'invoices_list' },
    });
    check(res, { 'inv_list 200': (x) => x.status === 200 });
  } else if (r < 0.93) {
    const res = http.get(`${base}/api/v1/dashboard/summary`, {
      headers: h,
      tags: { name: 'dashboard_summary' },
    });
    check(res, { 'dash 200': (x) => x.status === 200 });
  } else if (r < 0.97) {
    const res = http.get(`${base}/api/v1/auth/me`, { headers: h, tags: { name: 'auth_me' } });
    check(res, { 'me 200': (x) => x.status === 200 });
  } else if (r < 0.985) {
    const res = http.post(
      `${base}/api/v1/work-orders/batches`,
      JSON.stringify({ lines: [] }),
      {
        headers: h,
        tags: { name: 'batch_negative_expect_422' },
        responseCallback: http.expectedStatuses(422, 400),
      },
    );
    check(res, {
      'batch_neg expected 4xx': (x) => x.status === 422 || x.status === 400,
    });
  } else {
    doWriteWorkOrder({
      baseUrl: base,
      headers: h,
      customerId: data.customerId,
      vehicleId: data.vehicleId,
      vehiclePlate: data.vehiclePlate,
      writeEnabled: data.writeEnabled,
    });
  }

  sleep(0.25 + Math.random() * 0.45);
}
