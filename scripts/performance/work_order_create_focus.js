/**
 * Focused load on POST /api/v1/work-orders only.
 * TARGET_VUS: default 100 (set 150 for second run).
 * summaryTrendStats enables p(99) in the exported summary / stdout.
 */
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';
import { login, authHeaders, fetchFirstCustomerVehicle } from './common.js';

const wo2xx = new Counter('wo_status_2xx');
const wo4xx = new Counter('wo_status_4xx');
const wo5xx = new Counter('wo_status_5xx');
const woOther = new Counter('wo_status_other');

const target = parseInt(__ENV.TARGET_VUS || '100', 10);

export const options = {
  summaryTrendStats: ['avg', 'min', 'med', 'max', 'p(90)', 'p(95)', 'p(99)'],
  scenarios: {
    wo_focus: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '1m', target: Math.min(50, target) },
        { duration: '90s', target },
        { duration: '3m', target },
      ],
      gracefulRampDown: '45s',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.02'],
    'http_req_duration{name:work_order_create}': ['p(99)<60000'],
  },
};

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

export default function (data) {
  if (!data.writeEnabled) {
    return;
  }

  const bodyObj = {
    customer_id: data.customerId,
    vehicle_id: data.vehicleId,
    items: [
      {
        item_type: 'labor',
        name: `k6 wo-focus ${__VU}-${__ITER}-${Date.now()}`,
        quantity: 1,
        unit_price: 10,
        tax_rate: 15,
      },
    ],
  };
  if (data.vehiclePlate) {
    bodyObj.vehicle_plate = data.vehiclePlate;
  }

  const res = http.post(`${data.baseUrl}/api/v1/work-orders`, JSON.stringify(bodyObj), {
    headers: data.headers,
    tags: { name: 'work_order_create' },
  });

  if (res.status >= 200 && res.status < 300) {
    wo2xx.add(1);
  } else if (res.status >= 400 && res.status < 500) {
    wo4xx.add(1);
  } else if (res.status >= 500) {
    wo5xx.add(1);
  } else {
    woOther.add(1);
  }

  check(res, {
    'wo_create 2xx': (r) => r.status >= 200 && r.status < 300,
  });

  sleep(0.15 + Math.random() * 0.25);
}
