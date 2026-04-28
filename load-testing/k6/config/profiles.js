import {
  THRESHOLDS_SMOKE,
  THRESHOLDS_NORMAL,
  THRESHOLDS_PEAK,
  THRESHOLDS_STRESS,
  THRESHOLDS_SPIKE,
  THRESHOLDS_SOAK,
  THRESHOLDS_VERIFICATION,
  THRESHOLDS_CAPACITY_POS,
  PROFILE_LABELS,
} from './acceptance.js';
import { getEnterpriseGateOptions } from './enterprise-gate.js';

export { PROFILE_LABELS };

const GRACEFUL = '30s';

/**
 * @param {string} profile smoke|normal|peak|stress|spike|soak|capacity_pos|verification|…
 */
export function getProfileOptions(profile) {
  const p = (profile || 'smoke').toLowerCase();
  switch (p) {
    case 'enterprise_smoke':
    case 'enterprise_normal':
    case 'enterprise_peak':
      return getEnterpriseGateOptions(p);
    case 'peak_pos_short':
      return buildPeakPosShort();
    case 'peak_pos_raw_short':
      return buildPeakPosRawShort();
    case 'smoke':
      return buildSmoke();
    case 'normal':
    case 'load':
      return buildNormal();
    case 'peak':
      return buildPeak();
    case 'verification':
      return buildVerificationGate();
    case 'capacity_pos':
      return buildCapacityPos();
    case 'stress':
      return buildStress();
    case 'spike':
      return buildSpike();
    case 'soak':
      return buildSoak();
    default:
      return buildSmoke();
  }
}

/** Short decisive run: POS POST only (no read mix, no isolation). */
function buildPeakPosShort() {
  return {
    scenarios: {
      pos_writes: {
        executor: 'ramping-arrival-rate',
        startRate: 2,
        timeUnit: '1s',
        preAllocatedVUs: 40,
        maxVUs: 90,
        stages: [
          { duration: '45s', target: 6 },
          { duration: '4m', target: 9 },
          { duration: '45s', target: 0 },
        ],
        exec: 'scenarioPosSale',
      },
      peak_health: {
        executor: 'constant-arrival-rate',
        rate: 1,
        timeUnit: '15s',
        duration: '5m',
        preAllocatedVUs: 2,
        maxVUs: 4,
        exec: 'scenarioHealth',
        startTime: '10s',
      },
    },
    thresholds: THRESHOLDS_PEAK,
    summaryTrendStats: ['avg', 'med', 'p(50)', 'p(95)', 'p(99)', 'max'],
  };
}

/** Short decisive run: POS POST + RAW/read contention mix. */
function buildPeakPosRawShort() {
  return {
    scenarios: {
      read_mix: {
        executor: 'ramping-vus',
        startVUs: 0,
        stages: [
          { duration: '45s', target: 55 },
          { duration: '4m', target: 55 },
          { duration: '45s', target: 0 },
        ],
        gracefulRampDown: GRACEFUL,
        exec: 'scenarioReadMix',
      },
      pos_writes: {
        executor: 'ramping-arrival-rate',
        startRate: 2,
        timeUnit: '1s',
        preAllocatedVUs: 40,
        maxVUs: 100,
        stages: [
          { duration: '45s', target: 6 },
          { duration: '4m', target: 9 },
          { duration: '45s', target: 0 },
        ],
        exec: 'scenarioPosSale',
        startTime: '10s',
      },
      peak_health: {
        executor: 'constant-arrival-rate',
        rate: 1,
        timeUnit: '15s',
        duration: '5m',
        preAllocatedVUs: 2,
        maxVUs: 4,
        exec: 'scenarioHealth',
        startTime: '10s',
      },
    },
    thresholds: THRESHOLDS_PEAK,
    summaryTrendStats: ['avg', 'med', 'p(50)', 'p(95)', 'p(99)', 'max'],
  };
}

/** Smoke: 8 VU, 4 دقائق — ضمن خط الأساس 5–10 / 3–5 دقائق */
function buildSmoke() {
  return {
    scenarios: {
      smoke_mixed: {
        executor: 'constant-vus',
        vus: 8,
        duration: '4m',
        gracefulStop: GRACEFUL,
        exec: 'scenarioSmokeMixed',
      },
      smoke_idempotency: {
        executor: 'shared-iterations',
        vus: 1,
        iterations: 3,
        maxDuration: '2m',
        exec: 'scenarioIdempotencyMismatch',
        startTime: '10s',
      },
    },
    thresholds: THRESHOLDS_SMOKE,
    summaryTrendStats: ['avg', 'med', 'p(50)', 'p(95)', 'p(99)', 'max'],
  };
}

/** Normal: 30 VU، 15 دقيقة — ضمن 20–40 / 10–20 */
function buildNormal() {
  return {
    scenarios: {
      read_mix: {
        executor: 'ramping-vus',
        startVUs: 0,
        stages: [
          { duration: '1m', target: 30 },
          { duration: '13m', target: 30 },
          { duration: '1m', target: 0 },
        ],
        gracefulRampDown: GRACEFUL,
        exec: 'scenarioReadMix',
      },
      normal_health: {
        executor: 'constant-arrival-rate',
        rate: 1,
        timeUnit: '10s',
        duration: '14m',
        preAllocatedVUs: 2,
        maxVUs: 4,
        exec: 'scenarioHealth',
        startTime: '10s',
      },
      pos_writes: {
        executor: 'ramping-arrival-rate',
        startRate: 1,
        timeUnit: '1s',
        preAllocatedVUs: 25,
        maxVUs: 40,
        stages: [
          { duration: '1m', target: 2 },
          { duration: '13m', target: 3 },
          { duration: '1m', target: 0 },
        ],
        exec: 'scenarioPosSale',
        startTime: '15s',
      },
      isolation_probe: {
        executor: 'constant-arrival-rate',
        rate: 1,
        timeUnit: '10s',
        duration: '14m',
        preAllocatedVUs: 2,
        maxVUs: 5,
        exec: 'scenarioIsolation',
        startTime: '30s',
      },
      idempotency_probe: {
        executor: 'shared-iterations',
        vus: 1,
        iterations: 5,
        maxDuration: '3m',
        exec: 'scenarioIdempotencyMismatch',
        startTime: '45s',
      },
    },
    thresholds: THRESHOLDS_NORMAL,
    summaryTrendStats: ['avg', 'med', 'p(50)', 'p(95)', 'p(99)', 'max'],
  };
}

/**
 * بوابة تحقق ما بعد التحسين: قراءات ثقيلة (~150 VU) + POS لمدة ≥16 دقيقة، عتبات صارمة (acceptance.THRESHOLDS_VERIFICATION).
 */
function buildVerificationGate() {
  return {
    scenarios: {
      read_mix: {
        executor: 'ramping-vus',
        startVUs: 0,
        stages: [
          { duration: '2m', target: 150 },
          { duration: '13m', target: 150 },
          { duration: '1m', target: 0 },
        ],
        gracefulRampDown: GRACEFUL,
        exec: 'scenarioReadMix',
      },
      peak_health: {
        executor: 'constant-arrival-rate',
        rate: 1,
        timeUnit: '12s',
        duration: '15m',
        preAllocatedVUs: 2,
        maxVUs: 5,
        exec: 'scenarioHealth',
        startTime: '20s',
      },
      pos_writes: {
        executor: 'ramping-arrival-rate',
        startRate: 2,
        timeUnit: '1s',
        preAllocatedVUs: 50,
        maxVUs: 120,
        stages: [
          { duration: '2m', target: 8 },
          { duration: '13m', target: 10 },
          { duration: '1m', target: 0 },
        ],
        exec: 'scenarioPosSale',
        startTime: '30s',
      },
      isolation_probe: {
        executor: 'constant-arrival-rate',
        rate: 1,
        timeUnit: '5s',
        duration: '15m',
        preAllocatedVUs: 2,
        maxVUs: 8,
        exec: 'scenarioIsolation',
        startTime: '40s',
      },
      idempotency_probe: {
        executor: 'shared-iterations',
        vus: 1,
        iterations: 4,
        maxDuration: '2m',
        exec: 'scenarioIdempotencyMismatch',
        startTime: '50s',
      },
    },
    thresholds: THRESHOLDS_VERIFICATION,
    summaryTrendStats: ['avg', 'med', 'p(50)', 'p(95)', 'p(99)', 'max'],
  };
}

/**
 * سعة POS — بيع فقط، معدّل وصول ثابت (iterations/sec) يُضبط بـ K6_CAPACITY_POS_RATE.
 * مدة الهضم: K6_CAPACITY_POS_RAMP (افتراضي 30s) + K6_CAPACITY_POS_STEADY_MIN (افتراضي 5m) + هبوط قصير.
 */
function buildCapacityPos() {
  const rate = envPositiveInt('K6_CAPACITY_POS_RATE', 3);
  const steadyMin = envPositiveInt('K6_CAPACITY_POS_STEADY_MIN', 5);
  const ramp = envDuration('K6_CAPACITY_POS_RAMP', '30s');
  const tail = envDuration('K6_CAPACITY_POS_TAIL', '25s');
  const startRate = rate > 1 ? 1 : rate;
  const preVu = envPositiveInt('K6_CAPACITY_POS_PRE_VUS', Math.min(120, Math.max(20, rate * 10)));
  const maxVu = envPositiveInt('K6_CAPACITY_POS_MAX_VUS', Math.min(180, Math.max(35, rate * 16)));

  return {
    scenarios: {
      /** POS فقط — بدون read_mix لقياس سقف المعاملة المالية بعزل أفضل */
      pos_writes: {
        executor: 'ramping-arrival-rate',
        startRate,
        timeUnit: '1s',
        preAllocatedVUs: preVu,
        maxVUs: maxVu,
        stages: [
          { duration: ramp, target: rate },
          { duration: `${steadyMin}m`, target: rate },
          { duration: tail, target: 0 },
        ],
        exec: 'scenarioPosSale',
      },
    },
    thresholds: THRESHOLDS_CAPACITY_POS,
    summaryTrendStats: ['avg', 'med', 'p(50)', 'p(95)', 'p(99)', 'max'],
  };
}

function envPositiveInt(name, fallback) {
  const raw = __ENV[name];
  if (raw == null || raw === '') {
    return fallback;
  }
  const n = parseInt(String(raw), 10);
  return Number.isFinite(n) && n > 0 ? n : fallback;
}

/** قيمة مدة k6 مثل 30s أو 2m — غير صالحة تُستبدل بالافتراضي */
function envDuration(name, defaultVal) {
  const raw = __ENV[name];
  if (raw == null || raw === '') {
    return defaultVal;
  }
  const s = String(raw).trim();
  return /^[0-9]+(\.[0-9]+)?(ms|s|m|h|d)$/.test(s) ? s : defaultVal;
}

/** Peak: 75 VU قراءة، POS أعلى — ضمن 50–100 / 10–15 */
function buildPeak() {
  return {
    scenarios: {
      read_mix: {
        executor: 'ramping-vus',
        startVUs: 0,
        stages: [
          { duration: '90s', target: 75 },
          { duration: '10m', target: 75 },
          { duration: '1m', target: 0 },
        ],
        gracefulRampDown: GRACEFUL,
        exec: 'scenarioReadMix',
      },
      peak_health: {
        executor: 'constant-arrival-rate',
        rate: 1,
        timeUnit: '12s',
        duration: '11m',
        preAllocatedVUs: 2,
        maxVUs: 5,
        exec: 'scenarioHealth',
        startTime: '15s',
      },
      pos_writes: {
        executor: 'ramping-arrival-rate',
        startRate: 2,
        timeUnit: '1s',
        preAllocatedVUs: 40,
        maxVUs: 100,
        stages: [
          { duration: '1m', target: 6 },
          { duration: '10m', target: 10 },
          { duration: '1m', target: 0 },
        ],
        exec: 'scenarioPosSale',
        startTime: '20s',
      },
      isolation_probe: {
        executor: 'constant-arrival-rate',
        rate: 1,
        timeUnit: '5s',
        duration: '11m',
        preAllocatedVUs: 2,
        maxVUs: 8,
        exec: 'scenarioIsolation',
        startTime: '30s',
      },
      idempotency_probe: {
        executor: 'shared-iterations',
        vus: 1,
        iterations: 4,
        maxDuration: '2m',
        exec: 'scenarioIdempotencyMismatch',
        startTime: '40s',
      },
    },
    thresholds: THRESHOLDS_PEAK,
    summaryTrendStats: ['avg', 'med', 'p(50)', 'p(95)', 'p(99)', 'max'],
  };
}

/**
 * Stress: مراحل متتالية بدون تداخل — لقياس أول مرحلة تتدهور فيها p95 أو الأخطاء.
 * 15 → 30 → 50 → 70 → 100 → 130 VU，كل مرحلة 3 دقائق.
 */
function buildStress() {
  const stagesVu = [15, 30, 50, 70, 100, 130];
  const scenarios = {};
  stagesVu.forEach((n, i) => {
    const start = `${i * 3}m`;
    scenarios[`stress_s${n}`] = {
      executor: 'constant-vus',
      vus: n,
      duration: '3m',
      gracefulStop: GRACEFUL,
      exec: 'scenarioReadMix',
      startTime: start,
    };
  });
  return {
    scenarios,
    thresholds: THRESHOLDS_STRESS,
    summaryTrendStats: ['avg', 'med', 'p(50)', 'p(95)', 'p(99)', 'max'],
  };
}

/** Spike: منخفض → قفزة → هدوء */
function buildSpike() {
  return {
    scenarios: {
      spike_wave: {
        executor: 'ramping-vus',
        startVUs: 0,
        stages: [
          { duration: '2m', target: 10 },
          { duration: '30s', target: 95 },
          { duration: '2m', target: 95 },
          { duration: '2m', target: 18 },
          { duration: '90s', target: 8 },
        ],
        gracefulRampDown: GRACEFUL,
        exec: 'scenarioReadMix',
      },
      pos_spike: {
        executor: 'ramping-arrival-rate',
        startRate: 1,
        timeUnit: '1s',
        preAllocatedVUs: 30,
        maxVUs: 80,
        stages: [
          { duration: '2m', target: 1 },
          { duration: '30s', target: 12 },
          { duration: '2m', target: 12 },
          { duration: '2m', target: 2 },
          { duration: '90s', target: 0 },
        ],
        exec: 'scenarioPosSale',
        startTime: '5s',
      },
      isolation_probe: {
        executor: 'constant-arrival-rate',
        rate: 1,
        timeUnit: '15s',
        duration: '9m',
        preAllocatedVUs: 2,
        maxVUs: 5,
        exec: 'scenarioIsolation',
        startTime: '1m',
      },
    },
    thresholds: THRESHOLDS_SPIKE,
    summaryTrendStats: ['avg', 'med', 'p(50)', 'p(95)', 'p(99)', 'max'],
  };
}

/** Soak: نفس الحمولة ~90 د مقسّمة إلى 3 شرائح زمنية لمقارنة p95/fail ولرصد التدهور التدريجي */
function buildSoak() {
  const segDur = '30m';
  const readGrace = '2m';
  return {
    scenarios: {
      soak_read_seg1: {
        executor: 'constant-vus',
        vus: 22,
        duration: segDur,
        gracefulStop: readGrace,
        exec: 'scenarioSoakReadSeg1',
      },
      soak_read_seg2: {
        executor: 'constant-vus',
        vus: 22,
        duration: segDur,
        gracefulStop: readGrace,
        startTime: '30m',
        exec: 'scenarioSoakReadSeg2',
      },
      soak_read_seg3: {
        executor: 'constant-vus',
        vus: 22,
        duration: segDur,
        gracefulStop: readGrace,
        startTime: '60m',
        exec: 'scenarioSoakReadSeg3',
      },
      soak_pos_seg1: {
        executor: 'constant-arrival-rate',
        rate: 1,
        timeUnit: '20s',
        duration: segDur,
        preAllocatedVUs: 5,
        maxVUs: 15,
        exec: 'scenarioSoakPosSeg1',
        startTime: '60s',
      },
      soak_pos_seg2: {
        executor: 'constant-arrival-rate',
        rate: 1,
        timeUnit: '20s',
        duration: segDur,
        preAllocatedVUs: 5,
        maxVUs: 15,
        exec: 'scenarioSoakPosSeg2',
        startTime: '30m',
      },
      soak_pos_seg3: {
        executor: 'constant-arrival-rate',
        rate: 1,
        timeUnit: '20s',
        duration: segDur,
        preAllocatedVUs: 5,
        maxVUs: 15,
        exec: 'scenarioSoakPosSeg3',
        startTime: '60m',
      },
      soak_health_seg1: {
        executor: 'constant-arrival-rate',
        rate: 1,
        timeUnit: '15s',
        duration: segDur,
        preAllocatedVUs: 2,
        maxVUs: 5,
        exec: 'scenarioHealth',
        startTime: '30s',
      },
      soak_health_seg2: {
        executor: 'constant-arrival-rate',
        rate: 1,
        timeUnit: '15s',
        duration: segDur,
        preAllocatedVUs: 2,
        maxVUs: 5,
        exec: 'scenarioHealth',
        startTime: '30m',
      },
      soak_health_seg3: {
        executor: 'constant-arrival-rate',
        rate: 1,
        timeUnit: '15s',
        duration: segDur,
        preAllocatedVUs: 2,
        maxVUs: 5,
        exec: 'scenarioHealth',
        startTime: '60m',
      },
      soak_isolation_seg1: {
        executor: 'constant-arrival-rate',
        rate: 1,
        timeUnit: '60s',
        duration: segDur,
        preAllocatedVUs: 1,
        maxVUs: 3,
        exec: 'scenarioIsolation',
        startTime: '2m',
      },
      soak_isolation_seg2: {
        executor: 'constant-arrival-rate',
        rate: 1,
        timeUnit: '60s',
        duration: segDur,
        preAllocatedVUs: 1,
        maxVUs: 3,
        exec: 'scenarioIsolation',
        startTime: '30m',
      },
      soak_isolation_seg3: {
        executor: 'constant-arrival-rate',
        rate: 1,
        timeUnit: '60s',
        duration: segDur,
        preAllocatedVUs: 1,
        maxVUs: 3,
        exec: 'scenarioIsolation',
        startTime: '60m',
      },
    },
    thresholds: THRESHOLDS_SOAK,
    summaryTrendStats: ['avg', 'med', 'p(50)', 'p(95)', 'p(99)', 'max'],
  };
}
