/**
 * OSAS Pro — enterprise production readiness thresholds (strict).
 * Used by K6_PROFILE=enterprise_smoke|enterprise_normal|enterprise_peak (short runs).
 *
 * Targets: http_req_failed < 1%, p(95) < 2s, 5xx bounded.
 */

/** @typedef {Record<string, string[]>} K6Thresholds */

export const THRESHOLDS_ENTERPRISE_GATE = /** @type {K6Thresholds} */ ({
  http_req_failed: ['rate<0.01'],
  server_errors_5xx: ['rate<0.01'],
  client_timeout_or_network: ['rate<0.01'],
  http_req_duration: ['p(50)<800', 'p(95)<2000', 'p(99)<5000'],
  operational_http_success: ['rate>0.985'],
  health_ok: ['rate>0.75'],
});

const GRACEFUL = '20s';

/** @param {string} profile enterprise_smoke | enterprise_normal | enterprise_peak */
export function getEnterpriseGateOptions(profile) {
  const p = (profile || 'enterprise_smoke').toLowerCase();

  if (p === 'enterprise_peak') {
    return {
      scenarios: {
        eg_read: {
          executor: 'constant-vus',
          vus: 20,
          duration: '2m',
          gracefulStop: GRACEFUL,
          exec: 'scenarioReadMix',
        },
        eg_pos: {
          executor: 'constant-arrival-rate',
          rate: 1,
          timeUnit: '2s',
          duration: '2m',
          preAllocatedVUs: 10,
          maxVUs: 35,
          exec: 'scenarioPosSale',
          startTime: '15s',
        },
        eg_health: {
          executor: 'constant-arrival-rate',
          rate: 1,
          timeUnit: '5s',
          duration: '2m',
          preAllocatedVUs: 2,
          maxVUs: 5,
          exec: 'scenarioHealth',
          startTime: '5s',
        },
      },
      thresholds: THRESHOLDS_ENTERPRISE_GATE,
      summaryTrendStats: ['avg', 'med', 'p(50)', 'p(95)', 'p(99)', 'max'],
    };
  }

  if (p === 'enterprise_normal') {
    return {
      scenarios: {
        eg_read: {
          executor: 'constant-vus',
          vus: 12,
          duration: '2m',
          gracefulStop: GRACEFUL,
          exec: 'scenarioReadMix',
        },
        eg_pos: {
          executor: 'constant-arrival-rate',
          rate: 1,
          timeUnit: '3s',
          duration: '2m',
          preAllocatedVUs: 8,
          maxVUs: 25,
          exec: 'scenarioPosSale',
          startTime: '20s',
        },
        eg_health: {
          executor: 'constant-arrival-rate',
          rate: 1,
          timeUnit: '8s',
          duration: '2m',
          preAllocatedVUs: 2,
          maxVUs: 4,
          exec: 'scenarioHealth',
          startTime: '10s',
        },
      },
      thresholds: THRESHOLDS_ENTERPRISE_GATE,
      summaryTrendStats: ['avg', 'med', 'p(50)', 'p(95)', 'p(99)', 'max'],
    };
  }

  // enterprise_smoke (default)
  return {
    scenarios: {
      eg_smoke: {
        executor: 'constant-vus',
        vus: 6,
        duration: '90s',
        gracefulStop: GRACEFUL,
        exec: 'scenarioSmokeMixed',
      },
      eg_health: {
        executor: 'constant-arrival-rate',
        rate: 1,
        timeUnit: '10s',
        duration: '90s',
        preAllocatedVUs: 2,
        maxVUs: 4,
        exec: 'scenarioHealth',
        startTime: '10s',
      },
    },
    thresholds: THRESHOLDS_ENTERPRISE_GATE,
    summaryTrendStats: ['avg', 'med', 'p(50)', 'p(95)', 'p(99)', 'max'],
  };
}
