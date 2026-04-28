import http from 'k6/http';
import { check, sleep } from 'k6';
import exec from 'k6/execution';
import { uuidv4 } from 'https://jslib.k6.io/k6-utils/1.4.0/index.js';

/** وسوم موحّدة لتفكيك المقاييس في التقرير (مفتاح `scen` يطابق اسم سيناريو k6) */
function scenTags(extra = {}) {
  try {
    const n = exec.scenario.name;
    return { scen: n, ...extra };
  } catch {
    return { scen: 'unknown', ...extra };
  }
}
import { bearerHeaders } from './auth.js';
import {
  serverErrors5xx,
  clientTimeoutOrNetwork,
  posSaleOk,
  tenantIsolationBlocked,
  healthDegradedOk,
  invoiceFollowMs,
  idempotencyPayloadMismatch409,
  posSkippedCatalog,
  errorTypeNetwork,
  errorTypeServer5xx,
  errorTypeClient4xx,
  rawReadAfterWriteOk,
  operationalHttpSuccess,
  scenReadMixHttpMs,
  readMixOperationalOk,
  scenPosPostHttpMs,
  scenPosInvoiceGetHttpMs,
  scenIsolationHttpMs,
  soakReadSeg1HttpMs,
  soakReadSeg2HttpMs,
  soakReadSeg3HttpMs,
} from './metrics.js';

/** تسجيل موحّد: أخطاء الشبكة، 5xx، 4xx — بالإضافة للمقاييس التاريخية */
function recordHttpOutcome(res) {
  errorTypeNetwork.add(res.status === 0 ? 1 : 0);
  errorTypeServer5xx.add(res.status >= 500 ? 1 : 0);
  errorTypeClient4xx.add(res.status >= 400 && res.status < 500 ? 1 : 0);

  if (res.status === 0) {
    clientTimeoutOrNetwork.add(1);
    return;
  }
  clientTimeoutOrNetwork.add(0);
  if (res.status >= 500) {
    serverErrors5xx.add(1);
  } else {
    serverErrors5xx.add(0);
  }
}

function previewBody(res) {
  try {
    return String(res.body || '').slice(0, 500);
  } catch (_) {
    return '';
  }
}

function trace503Sample(res, context = {}) {
  if (res.status !== 503) {
    return;
  }
  const headers = res.headers || {};
  const sample = {
    kind: 'POS_503_SAMPLE',
    status: res.status,
    url: res.url,
    duration_ms: res.timings && res.timings.duration != null ? res.timings.duration : null,
    request_id: headers['X-Request-Id'] || headers['x-request-id'] || null,
    trace_id: headers['X-Trace-Id'] || headers['x-trace-id'] || null,
    headers,
    response_body_preview: previewBody(res),
    context,
  };
  console.error(JSON.stringify(sample));
}

/** خلط قراءة تشغيلي — السيناريو الأساس للحمل */
export function scenarioReadMix(data) {
  const h = { headers: bearerHeaders(data.tokenA) };
  const picks = [
    () => http.get(`${data.baseUrl}/v1/dashboard/summary`, { ...h, tags: scenTags({ name: 'DashboardSummary' }) }),
    () => http.get(`${data.baseUrl}/v1/customers?per_page=10`, { ...h, tags: scenTags({ name: 'CustomersIndex' }) }),
    () => http.get(`${data.baseUrl}/v1/products?per_page=10`, { ...h, tags: scenTags({ name: 'ProductsIndex' }) }),
    () => http.get(`${data.baseUrl}/v1/inventory?per_page=10`, { ...h, tags: scenTags({ name: 'InventoryIndex' }) }),
    () => http.get(`${data.baseUrl}/v1/work-orders?per_page=10`, { ...h, tags: scenTags({ name: 'WorkOrdersIndex' }) }),
    () => http.get(`${data.baseUrl}/v1/invoices?per_page=5`, { ...h, tags: scenTags({ name: 'InvoicesIndex' }) }),
    () => http.get(`${data.baseUrl}/v1/wallet`, { ...h, tags: scenTags({ name: 'WalletShow' }) }),
  ];
  const res = picks[Math.floor(Math.random() * picks.length)]();
  recordHttpOutcome(res);
  scenReadMixHttpMs.add(res.timings.duration);
  readMixOperationalOk.add(res.status >= 200 && res.status < 400 && res.status !== 0 ? 1 : 0);
  try {
    const sn = exec.scenario.name;
    if (sn === 'soak_read_seg1') {
      soakReadSeg1HttpMs.add(res.timings.duration);
    } else if (sn === 'soak_read_seg2') {
      soakReadSeg2HttpMs.add(res.timings.duration);
    } else if (sn === 'soak_read_seg3') {
      soakReadSeg3HttpMs.add(res.timings.duration);
    }
  } catch (_) {
    /* setup phase */
  }
  operationalHttpSuccess.add(res.status >= 200 && res.status < 400 && res.status !== 0 ? 1 : 0);
  check(res, {
    'read_mix لا 5xx': (r) => r.status < 500 || r.status === 0,
  });
  sleep(0.15 + Math.random() * 0.65);
}

export function scenarioHealth(data) {
  const res = http.get(`${data.baseUrl}/v1/health`, {
    tags: scenTags({ name: 'Health' }),
  });
  recordHttpOutcome(res);
  const degradedOk = res.status === 200 || res.status === 503;
  healthDegradedOk.add(res.status === 200 ? 1 : 0);
  operationalHttpSuccess.add(degradedOk ? 1 : 0);
  check(res, {
    health_responds: () => degradedOk,
    health_json: () => {
      try {
        const j = res.json();
        return j && (j.status === 'healthy' || j.status === 'degraded');
      } catch (e) {
        return false;
      }
    },
  });
  sleep(0.25 + Math.random() * 0.4);
}

function posLineTotal(qty, unit, taxPct) {
  return Math.round(qty * unit * (1 + taxPct / 100) * 100) / 100;
}

function posPayload(data, qty, unitPrice, costPrice, taxRate) {
  const p = data.product;
  const amount = posLineTotal(qty, unitPrice, taxRate);
  return {
    body: JSON.stringify({
      customer_id: data.customerId,
      customer_type: 'b2c',
      items: [
        {
          name: p.name,
          product_id: p.id,
          quantity: qty,
          unit_price: unitPrice,
          cost_price: costPrice,
          tax_rate: taxRate,
        },
      ],
      payment: { method: 'cash', amount },
    }),
    amount,
  };
}

function selectPosActor(data) {
  const actors = Array.isArray(data.posActors) ? data.posActors : [];
  if (actors.length === 0) {
    return {
      key: 'A',
      token: data.tokenA,
      companyId: data.companyIdA,
      customerId: data.customerId,
      product: data.product,
    };
  }
  const mode = String(__ENV.K6_POS_DISTRIBUTION || 'single').toLowerCase();
  if (mode !== 'distributed' || actors.length === 1) {
    return actors[0];
  }
  const idx = Math.floor(Math.random() * actors.length);
  return actors[idx];
}

/** بيع POS — مفتاح Idempotency فريد لكل طلب (لا تكرار غير مقصود) */
export function scenarioPosSale(data) {
  if (!data.posReady) {
    posSkippedCatalog.add(1);
    return;
  }

  const actor = selectPosActor(data);
  const idem = uuidv4();
  const p = actor.product || data.product;
  const payloadData = {
    ...data,
    customerId: actor.customerId,
    product: p,
  };
  const { body } = posPayload(payloadData, 1, p.unit_price, p.cost_price, p.tax_rate);

  const res = http.post(`${data.baseUrl}/v1/pos/sale`, body, {
    headers: {
      ...bearerHeaders(actor.token),
      'Idempotency-Key': idem,
      'X-Request-Id': uuidv4(),
      'X-Trace-Id': uuidv4(),
    },
    tags: scenTags({ name: 'POSSale' }),
  });

  recordHttpOutcome(res);
  scenPosPostHttpMs.add(res.timings.duration);
  const ok201 = res.status === 201;
  operationalHttpSuccess.add(ok201 ? 1 : 0);
  posSaleOk.add(ok201 ? 1 : 0);
  check(res, {
    pos_no_5xx: (r) => r.status < 500 || r.status === 0,
  });
  trace503Sample(res, {
    scenario: 'scenarioPosSale',
    idempotency_key: idem,
    actor_key: actor.key,
    actor_company_id: actor.companyId,
  });

  const includeRawFollow = String(__ENV.K6_POS_INCLUDE_RAW || 'true').toLowerCase() !== 'false';
  if (ok201 && includeRawFollow) {
    try {
      const invBody = res.json();
      const invId =
        invBody.data && invBody.data.id
          ? invBody.data.id
          : invBody.data && invBody.data.invoice && invBody.data.invoice.id
            ? invBody.data.invoice.id
            : null;
      if (invId) {
        const t0 = Date.now();
        const invRes = http.get(`${data.baseUrl}/v1/invoices/${invId}`, {
          headers: bearerHeaders(actor.token),
          tags: scenTags({ name: 'InvoiceShowAfterSale' }),
        });
        recordHttpOutcome(invRes);
        scenPosInvoiceGetHttpMs.add(invRes.timings.duration);
        operationalHttpSuccess.add(invRes.status === 200 ? 1 : 0);
        invoiceFollowMs.add(Date.now() - t0);
        let rawOk = false;
        try {
          const invPayload = invRes.json();
          const row = invPayload.data != null ? invPayload.data : invPayload;
          const idMatch = row && Number(row.id) === Number(invId);
          const invStatuses = new Set(['draft', 'pending', 'paid', 'partial_paid', 'cancelled', 'refunded']);
          const statusOk = row && row.status != null && invStatuses.has(String(row.status));
          rawOk = invRes.status === 200 && Boolean(idMatch) && Boolean(statusOk);
        } catch (e) {
          rawOk = false;
        }
        rawReadAfterWriteOk.add(rawOk ? 1 : 0);
        check(invRes, {
          invoice_readable_same_tenant: (r) => r.status === 200,
          raw_read_after_write_consistency: () => rawOk,
        });
      }
    } catch (e) {
      // ignore
    }
  }

  sleep(0.12 + Math.random() * 0.35);
}

/** تحقق صريح: نفس مفتاح Idempotency مع حمولة مختلفة → 409 (سلامة عدم تكرار مالي) */
export function scenarioIdempotencyMismatch(data) {
  if (!data.posReady) {
    return;
  }
  const actor = selectPosActor(data);
  const idem = uuidv4();
  const p = actor.product || data.product;
  const payloadData = {
    ...data,
    customerId: actor.customerId,
    product: p,
  };
  const first = posPayload(payloadData, 1, p.unit_price, p.cost_price, p.tax_rate);
  const second = posPayload(payloadData, 2, p.unit_price, p.cost_price, p.tax_rate);

  const r1 = http.post(`${data.baseUrl}/v1/pos/sale`, first.body, {
    headers: {
      ...bearerHeaders(actor.token),
      'Idempotency-Key': idem,
      'X-Request-Id': uuidv4(),
      'X-Trace-Id': uuidv4(),
    },
    tags: scenTags({ name: 'POSIdemFirst' }),
  });
  recordHttpOutcome(r1);
  operationalHttpSuccess.add(r1.status === 201 ? 1 : 0);

  if (r1.status !== 201) {
    check(r1, { idem_first_sale_ok: () => false });
    sleep(0.2);
    return;
  }

  const r2 = http.post(`${data.baseUrl}/v1/pos/sale`, second.body, {
    headers: {
      ...bearerHeaders(actor.token),
      'Idempotency-Key': idem,
      'X-Request-Id': uuidv4(),
      'X-Trace-Id': uuidv4(),
    },
    tags: scenTags({ name: 'POSIdemSecond' }),
  });
  recordHttpOutcome(r2);

  const got409 = r2.status === 409;
  operationalHttpSuccess.add(got409 ? 1 : 0);
  idempotencyPayloadMismatch409.add(got409 ? 1 : 0);
  check(r2, {
    idempotency_rejects_payload_change: () => got409,
  });
  sleep(0.3);
}

export function scenarioIsolation(data) {
  const res = http.get(`${data.baseUrl}/v1/companies/${data.companyIdB}`, {
    headers: bearerHeaders(data.tokenA),
    tags: scenTags({ name: 'CrossTenantCompanyShow' }),
  });
  recordHttpOutcome(res);
  scenIsolationHttpMs.add(res.timings.duration);
  const blocked = res.status === 403 || res.status === 404;
  operationalHttpSuccess.add(blocked ? 1 : 0);
  tenantIsolationBlocked.add(blocked ? 1 : 0);
  check(res, {
    cross_tenant_company_denied: () => blocked,
  });
  sleep(0.35);
}

/**
 * خلط للـ smoke: نسب ثابتة لاختبار سريع متوازن
 */
/** شرائح نقع زمنية — نفس منطق القراءة، أسماء exec مختلفة لقياس التدهور عبر الزمن */
export function scenarioSoakReadSeg1(data) {
  scenarioReadMix(data);
}
export function scenarioSoakReadSeg2(data) {
  scenarioReadMix(data);
}
export function scenarioSoakReadSeg3(data) {
  scenarioReadMix(data);
}

export function scenarioSoakPosSeg1(data) {
  scenarioPosSale(data);
}
export function scenarioSoakPosSeg2(data) {
  scenarioPosSale(data);
}
export function scenarioSoakPosSeg3(data) {
  scenarioPosSale(data);
}

export function scenarioSmokeMixed(data) {
  const r = Math.random();
  if (r < 0.12) {
    scenarioHealth(data);
  } else if (r < 0.88) {
    scenarioReadMix(data);
  } else if (r < 0.94 && data.posReady) {
    scenarioPosSale(data);
  } else if (r < 0.98) {
    scenarioIsolation(data);
  } else {
    scenarioReadMix(data);
  }
}

export { uuidv4 };
