/**
 * WorkshopOS — k6: الملف الشخصي عبر `K6_PROFILE`
 * القيم: smoke | normal | peak | stress | spike | soak
 */
import { getProfileOptions } from './config/profiles.js';
import { login } from './lib/auth.js';
import { discoverPosContext } from './lib/discover.js';
import { setupLoginFailures } from './lib/metrics.js';
import { buildHandleSummary } from './lib/report.js';
import {
  scenarioReadMix,
  scenarioHealth,
  scenarioPosSale,
  scenarioIsolation,
  scenarioSmokeMixed,
  scenarioIdempotencyMismatch,
  scenarioSoakReadSeg1,
  scenarioSoakReadSeg2,
  scenarioSoakReadSeg3,
  scenarioSoakPosSeg1,
  scenarioSoakPosSeg2,
  scenarioSoakPosSeg3,
} from './lib/api-scenarios.js';

const profile = (__ENV.K6_PROFILE || 'smoke').toLowerCase();
export const options = getProfileOptions(profile);

export {
  scenarioReadMix,
  scenarioHealth,
  scenarioPosSale,
  scenarioIsolation,
  scenarioSmokeMixed,
  scenarioIdempotencyMismatch,
  scenarioSoakReadSeg1,
  scenarioSoakReadSeg2,
  scenarioSoakReadSeg3,
  scenarioSoakPosSeg1,
  scenarioSoakPosSeg2,
  scenarioSoakPosSeg3,
};

export function setup() {
  const base = __ENV.K6_BASE_URL || 'http://localhost/api';
  const emailA = __ENV.K6_EMAIL_A || 'simulation.owner@demo.local';
  const passA = __ENV.K6_PASSWORD_A || 'SimulationDemo123!';
  const emailB = __ENV.K6_EMAIL_B || 'owner@demo.sa';
  const passB = __ENV.K6_PASSWORD_B || 'password';

  const tenantA = login(base, emailA, passA);
  const tenantB = login(base, emailB, passB);

  if (!tenantA || !tenantB) {
    setupLoginFailures.add(1);
    throw new Error(
      'فشل تسجيل الدخول في setup. تأكد: docker compose up، migrate/seed، وقيم K6_BASE_URL والبريد/كلمة المرور (انظر load-testing/env.example).',
    );
  }
  if (tenantA.companyId === tenantB.companyId) {
    throw new Error('يجب أن يكون لمستخدمي K6_EMAIL_A و K6_EMAIL_B شركتان مختلفتان لاختبار العزل.');
  }

  const ctx = discoverPosContext(base, tenantA.token);
  const posReady = Boolean(ctx.customerId && ctx.product);

  return {
    baseUrl: base,
    tokenA: tenantA.token,
    tokenB: tenantB.token,
    companyIdA: tenantA.companyId,
    companyIdB: tenantB.companyId,
    userIdA: tenantA.userId,
    customerId: ctx.customerId,
    product: ctx.product,
    posReady,
    k6Profile: profile,
  };
}

export const handleSummary = buildHandleSummary(profile);
