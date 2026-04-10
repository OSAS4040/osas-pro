/**
 * تشخيص سريع: صحة الخدمة + تسجيل دخول المستأجرين A و B (بدون ضغط).
 * تشغيل: k6 run preflight.js
 */
import http from 'k6/http';
import { check } from 'k6';
import { login } from './lib/auth.js';

export const options = {
  vus: 1,
  iterations: 1,
  thresholds: {
    checks: ['rate==1'],
    http_req_failed: ['rate<0.01'],
  },
};

export default function () {
  const base = __ENV.K6_BASE_URL || 'http://localhost/api';
  const h = http.get(`${base}/v1/health`);
  check(h, { 'health 200 أو 503': (r) => r.status === 200 || r.status === 503 });

  const a = login(base, __ENV.K6_EMAIL_A || 'simulation.owner@demo.local', __ENV.K6_PASSWORD_A || 'SimulationDemo123!');
  const b = login(base, __ENV.K6_EMAIL_B || 'owner@demo.sa', __ENV.K6_PASSWORD_B || 'password');

  check(null, { 'tenant A يدخل': () => a !== null });
  check(null, { 'tenant B يدخل': () => b !== null });
  if (a && b) {
    check(null, { 'شركتان مختلفتان': () => a.companyId !== b.companyId });
  }
}
