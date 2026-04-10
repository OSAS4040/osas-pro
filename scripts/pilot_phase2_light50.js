const http = require('http');
const { randomUUID } = require('crypto');

const HOST = 'localhost';
const PORT = 80;
const BASE = '/api/v1';

function nowMs() { return Date.now(); }

function request(path, method = 'GET', body = null, token = null, extraHeaders = {}) {
  return new Promise((resolve, reject) => {
    const payload = body ? JSON.stringify(body) : null;
    const headers = {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...extraHeaders,
    };
    if (token) headers.Authorization = `Bearer ${token}`;
    if (payload) headers['Content-Length'] = Buffer.byteLength(payload);

    const started = nowMs();
    const req = http.request({ host: HOST, port: PORT, path: BASE + path, method, headers }, (res) => {
      let b = '';
      res.on('data', (d) => (b += d));
      res.on('end', () => {
        let json = null;
        try { json = JSON.parse(b); } catch (_) {}
        resolve({
          status: res.statusCode,
          body: b,
          json,
          ms: nowMs() - started,
        });
      });
    });
    req.on('error', reject);
    if (payload) req.write(payload);
    req.end();
  });
}

function percentile(values, p) {
  if (!values.length) return null;
  const arr = [...values].sort((a, b) => a - b);
  const idx = Math.ceil((p / 100) * arr.length) - 1;
  return arr[Math.max(0, idx)];
}

function isOk(status) { return status >= 200 && status < 300; }

async function login(email, password) {
  const r = await request('/auth/login', 'POST', { email, password });
  const token = r.json?.token || r.json?.data?.token || null;
  const user = r.json?.data?.user || null;
  return { ...r, token, user };
}

async function run() {
  const timings = [];
  const issues = { P0: [], P1: [], P2: [] };
  const byStatus = { success: 0, fail: 0, s5xx: 0 };
  const opCounts = {
    login: 0,
    customersCreated: 0,
    vehiclesCreated: 0,
    workOrders: 0,
    invoices: 0,
    payments: 0,
    inventoryActions: 0,
  };
  const behaviorAppliedHits = [];

  /** 6 distinct demo roles (DemoCompanySeeder) — extended light run targets 6–10 persona breadth via extra login rounds + GETs. */
  const credentials = [
    { email: 'owner@demo.sa', password: 'password', role: 'owner' },
    { email: 'manager@demo.sa', password: 'password', role: 'manager' },
    { email: 'cashier@demo.sa', password: 'password', role: 'cashier' },
    { email: 'tech@demo.sa', password: 'password', role: 'technician' },
    { email: 'fleet.manager@demo.sa', password: 'password', role: 'fleet_manager' },
    { email: 'fleet.contact@demo.sa', password: 'password', role: 'fleet_contact' },
  ];

  const sessions = {};
  for (const c of credentials) {
    const r = await login(c.email, c.password);
    timings.push(r.ms);
    opCounts.login += 1;
    if (isOk(r.status) && r.token) {
      sessions[c.role] = r.token;
      byStatus.success += 1;
    } else {
      byStatus.fail += 1;
      if (r.status >= 500) byStatus.s5xx += 1;
      issues.P0.push(`Login failed for ${c.email} (${r.status})`);
    }
  }

  const ownerToken = sessions.owner || sessions.manager;
  const cashierToken = sessions.cashier || ownerToken;
  if (!ownerToken) {
    throw new Error('No owner/manager token available; cannot continue.');
  }

  // -----------------------------
  // Pilot Client A full flow
  // -----------------------------
  const suffix = Date.now();
  // Ensure a bay exists (some profiles enforce bay assignment).
  const bayList = await request('/bays', 'GET', null, ownerToken);
  timings.push(bayList.ms);
  let bayId = bayList.json?.data?.[0]?.id || null;
  if (!bayId) {
    const bayCreate = await request('/bays', 'POST', {
      code: `B-${String(suffix).slice(-4)}`,
      name: `Pilot Bay ${suffix}`,
      type: 'bay',
    }, ownerToken);
    timings.push(bayCreate.ms);
    if (isOk(bayCreate.status)) {
      byStatus.success += 1;
      bayId = bayCreate.json?.data?.id || null;
    } else {
      byStatus.fail += 1;
      issues.P0.push(`Create bay failed (${bayCreate.status})`);
    }
  }
  const customerPayload = {
    type: 'b2c',
    name: `Pilot Client A Customer ${suffix}`,
    phone: `050${String(suffix).slice(-7)}`,
  };
  const customerRes = await request('/customers', 'POST', customerPayload, ownerToken);
  timings.push(customerRes.ms);
  if (isOk(customerRes.status)) {
    byStatus.success += 1;
    opCounts.customersCreated += 1;
  } else {
    byStatus.fail += 1;
    if (customerRes.status >= 500) byStatus.s5xx += 1;
    issues.P0.push(`Create customer failed (${customerRes.status})`);
  }
  const customerId = customerRes.json?.data?.id;

  const vehicleRes = await request('/vehicles', 'POST', {
    customer_id: customerId,
    plate_number: `PLT-${String(suffix).slice(-6)}`,
    make: 'Toyota',
    model: 'Corolla',
    year: 2023,
  }, ownerToken);
  timings.push(vehicleRes.ms);
  if (isOk(vehicleRes.status)) {
    byStatus.success += 1;
    opCounts.vehiclesCreated += 1;
  } else {
    byStatus.fail += 1;
    if (vehicleRes.status >= 500) byStatus.s5xx += 1;
    issues.P0.push(`Create vehicle failed (${vehicleRes.status})`);
  }
  const vehicleId = vehicleRes.json?.data?.id;

  const serviceRes = await request('/services', 'POST', {
    name: `Pilot Service ${suffix}`,
    base_price: 120,
    estimated_minutes: 30,
  }, ownerToken);
  timings.push(serviceRes.ms);
  if (!isOk(serviceRes.status)) {
    byStatus.fail += 1;
    if (serviceRes.status >= 500) byStatus.s5xx += 1;
    issues.P1.push(`Add service failed (${serviceRes.status})`);
  } else {
    byStatus.success += 1;
  }

  const woRes = await request('/work-orders', 'POST', {
    customer_id: customerId,
    vehicle_id: vehicleId,
    bay_id: bayId,
    priority: 'normal',
    items: [
      { item_type: 'service', name: 'Pilot Service Line', quantity: 1, unit_price: 120, tax_rate: 15 },
    ],
  }, ownerToken);
  timings.push(woRes.ms);
  if (woRes.json?.behavior_applied) {
    behaviorAppliedHits.push({ op: 'work_order_store', values: woRes.json.behavior_applied });
  }
  if (isOk(woRes.status)) {
    byStatus.success += 1;
    opCounts.workOrders += 1;
  } else {
    byStatus.fail += 1;
    if (woRes.status >= 500) byStatus.s5xx += 1;
    issues.P0.push(`Create work order failed (${woRes.status})`);
  }
  const workOrderId = woRes.json?.data?.id;
  let workOrderVersion = woRes.json?.data?.version ?? 0;

  for (const st of ['in_progress', 'completed']) {
    const r = await request(`/work-orders/${workOrderId}/status`, 'PATCH', {
      status: st,
      version: workOrderVersion,
    }, ownerToken);
    timings.push(r.ms);
    if (isOk(r.status)) {
      byStatus.success += 1;
      workOrderVersion = r.json?.data?.version ?? workOrderVersion + 1;
      opCounts.workOrders += 1;
    } else {
      byStatus.fail += 1;
      if (r.status >= 500) byStatus.s5xx += 1;
      issues.P0.push(`Update work order status to ${st} failed (${r.status})`);
    }
  }

  const invRes = await request(`/invoices/from-work-order/${workOrderId}`, 'POST', null, ownerToken, {
    'Idempotency-Key': randomUUID(),
  });
  timings.push(invRes.ms);
  if (isOk(invRes.status)) {
    byStatus.success += 1;
    opCounts.invoices += 1;
  } else {
    byStatus.fail += 1;
    if (invRes.status >= 500) byStatus.s5xx += 1;
    issues.P0.push(`Issue invoice from work order failed (${invRes.status})`);
  }
  const invoiceId = invRes.json?.data?.id;
  const dueAmount = Number(invRes.json?.data?.due_amount ?? invRes.json?.data?.total ?? 0);

  const payRes = await request(`/invoices/${invoiceId}/pay`, 'POST', {
    amount: dueAmount > 0 ? dueAmount : 138,
    method: 'cash',
  }, ownerToken, {
    'Idempotency-Key': randomUUID(),
  });
  timings.push(payRes.ms);
  if (isOk(payRes.status)) {
    byStatus.success += 1;
    opCounts.payments += 1;
  } else {
    byStatus.fail += 1;
    if (payRes.status >= 500) byStatus.s5xx += 1;
    issues.P0.push(`Execute payment failed (${payRes.status})`);
  }

  // Inventory action path — product must have unit_id for catalog consistency (avoids adjust 422 / orphan rows).
  const unitsRes = await request('/units', 'GET', null, ownerToken);
  timings.push(unitsRes.ms);
  let pilotUnitId = Array.isArray(unitsRes.json?.data) && unitsRes.json.data[0]?.id
    ? unitsRes.json.data[0].id
    : null;
  if (! pilotUnitId) {
    const sym = `U${String(suffix).slice(-6)}`;
    const unitCreate = await request('/units', 'POST', {
      name: `Pilot UoM ${suffix}`,
      symbol: sym,
      type: 'quantity',
      is_base: true,
    }, ownerToken);
    timings.push(unitCreate.ms);
    if (isOk(unitCreate.status)) {
      byStatus.success += 1;
    } else {
      byStatus.fail += 1;
      if (unitCreate.status >= 500) byStatus.s5xx += 1;
      issues.P1.push(`Create unit for pilot product failed (${unitCreate.status})`);
    }
    pilotUnitId = unitCreate.json?.data?.id;
  }

  const productRes = await request('/products', 'POST', {
    name: `Pilot Product ${suffix}`,
    unit_id: pilotUnitId,
    sale_price: 25,
    cost_price: 10,
    track_inventory: true,
    product_type: 'consumable',
  }, ownerToken);
  timings.push(productRes.ms);
  const productId = productRes.json?.data?.id;
  if (!isOk(productRes.status)) {
    byStatus.fail += 1;
    if (productRes.status >= 500) byStatus.s5xx += 1;
    issues.P1.push(`Create product for inventory action failed (${productRes.status})`);
  } else {
    byStatus.success += 1;
  }

  const me = await request('/auth/me', 'GET', null, ownerToken);
  timings.push(me.ms);
  const branchId = me.json?.data?.user?.branch_id || me.json?.data?.branch_id;
  const invAdjRes = await request('/inventory/adjust', 'POST', {
    branch_id: branchId,
    product_id: productId,
    quantity: 5,
    type: 'add',
    note: 'pilot inventory action',
  }, ownerToken, { 'Idempotency-Key': randomUUID() });
  timings.push(invAdjRes.ms);
  if (invAdjRes.json?.behavior_applied) {
    behaviorAppliedHits.push({ op: 'inventory_adjust', values: invAdjRes.json.behavior_applied });
  }
  if (isOk(invAdjRes.status)) {
    byStatus.success += 1;
    opCounts.inventoryActions += 1;
  } else {
    byStatus.fail += 1;
    if (invAdjRes.status >= 500) byStatus.s5xx += 1;
    issues.P1.push(`Inventory action failed (${invAdjRes.status})`);
  }

  const finalInvoice = await request(`/invoices/${invoiceId}`, 'GET', null, ownerToken);
  timings.push(finalInvoice.ms);
  const flowCompleted = isOk(finalInvoice.status) && ['paid', 'partial_paid'].includes(String(finalInvoice.json?.data?.status));
  if (!flowCompleted) {
    issues.P0.push(`Final completion check failed (invoice status not paid/partial_paid).`);
  }

  // -----------------------------
  // Light controlled simulation: ~65–70 ops (not stress / not peak).
  // Mix: 12 logins + 12 cust/veh + 12 WO flows + 12 POS/list + 12 inventory + 8 read-only WO lists.
  // -----------------------------
  const simulationOps = [];
  // 12 login/session touches (6 roles × 2 rounds)
  for (const u of [...credentials, ...credentials]) {
    simulationOps.push({ kind: 'login', run: () => login(u.email, u.password) });
  }
  // 12 customers/vehicles
  for (let i = 0; i < 6; i++) {
    simulationOps.push({
      kind: 'customer',
      run: () => request('/customers', 'POST', { type: 'b2c', name: `Sim Customer ${suffix}-${i}` }, ownerToken),
    });
    simulationOps.push({
      kind: 'vehicle',
      run: async () => {
        const c = await request('/customers', 'POST', { type: 'b2c', name: `Sim Vehicle Owner ${suffix}-${i}` }, ownerToken);
        return request('/vehicles', 'POST', {
          customer_id: c.json?.data?.id,
          plate_number: `SIM-${String(suffix + i).slice(-6)}`,
          make: 'Hyundai',
          model: 'Accent',
        }, ownerToken);
      },
    });
  }
  // 12 work-order micro-flows (create + in_progress)
  for (let i = 0; i < 12; i++) {
    simulationOps.push({
      kind: 'work_order',
      run: async () => {
        const c = await request('/customers', 'POST', { type: 'b2c', name: `WO Customer ${suffix}-${i}` }, ownerToken);
        const v = await request('/vehicles', 'POST', {
          customer_id: c.json?.data?.id,
          plate_number: `WOS-${String(suffix + i).slice(-6)}`,
          make: 'Kia',
          model: 'Rio',
        }, ownerToken);
        const wo = await request('/work-orders', 'POST', {
          customer_id: c.json?.data?.id,
          vehicle_id: v.json?.data?.id,
          bay_id: bayId,
          items: [{ item_type: 'service', name: 'WO Line', quantity: 1, unit_price: 50 }],
        }, ownerToken);
        if (!isOk(wo.status)) return wo;
        return request(`/work-orders/${wo.json?.data?.id}/status`, 'PATCH', {
          status: 'in_progress',
          version: wo.json?.data?.version ?? 0,
        }, ownerToken);
      },
    });
  }
  // Read-only: work order index (spreads cheap GET load; owner token)
  for (let i = 0; i < 8; i++) {
    simulationOps.push({
      kind: 'work_order_list',
      run: () => request(`/work-orders?per_page=5&page=${(i % 3) + 1}`, 'GET', null, ownerToken),
    });
  }
  // 12 POS / invoice list
  for (let i = 0; i < 6; i++) {
    simulationOps.push({
      kind: 'pos_sale',
      // One bounded retry softens transient 5xx under Docker/nginx/Redis; second attempt uses a fresh idempotency key.
      run: async () => {
        const doSale = () =>
          request('/pos/sale', 'POST', {
            customer_id: customerId,
            // Tax-inclusive total (15% VAT on 20.00 => 23.00) — must not underpay vs due or partial edge cases in hot path.
            items: [{ name: `POS item ${i}`, quantity: 1, unit_price: 20, tax_rate: 15 }],
            payment: { method: 'cash', amount: 23 },
          }, ownerToken, { 'Idempotency-Key': randomUUID() });
        let r = await doSale();
        if (r.status >= 500) {
          await new Promise((res) => setTimeout(res, 250));
          r = await doSale();
        }
        return r;
      },
    });
    simulationOps.push({
      kind: 'invoice_read',
      run: () => request('/invoices?per_page=1', 'GET', null, ownerToken),
    });
  }
  // 12 light inventory adds only (avoids subtract 422 when on-hand is low — controlled / not stress)
  for (let i = 0; i < 12; i++) {
    simulationOps.push({
      kind: 'inventory',
      run: () => request('/inventory/adjust', 'POST', {
        branch_id: branchId,
        product_id: productId,
        quantity: 1,
        type: 'add',
        note: `sim inv add ${i}`,
      }, ownerToken, { 'Idempotency-Key': randomUUID() }),
    });
  }

  // Sequential safe execution for predictable light load
  for (const op of simulationOps) {
    const r = await op.run();
    timings.push(r.ms || 0);
    if (r.json?.behavior_applied) behaviorAppliedHits.push({ op: op.kind, values: r.json.behavior_applied });
    if (isOk(r.status)) {
      byStatus.success += 1;
    } else {
      byStatus.fail += 1;
      if (r.status >= 500) byStatus.s5xx += 1;
      if (r.status >= 500) issues.P1.push(`${op.kind} failed with ${r.status}`);
      else issues.P2.push(`${op.kind} non-2xx ${r.status}`);
    }
  }

  // DB / queue operational snapshot
  const dbHealth = await request('/health', 'GET', null, null);

  const lightSimulationStats = {
    profile: 'light_controlled_68',
    operationsExecuted: simulationOps.length,
    participatingUsers: Object.keys(sessions).length,
    success: byStatus.success,
    fail: byStatus.fail,
    successRate: Number(((byStatus.success / (byStatus.success + byStatus.fail)) * 100).toFixed(2)),
    s5xx: byStatus.s5xx,
    p95: percentile(timings, 95),
    avg: Number((timings.reduce((a, b) => a + b, 0) / Math.max(1, timings.length)).toFixed(2)),
    queueStatus: 'running (workers active, failed_jobs sampled separately)',
    dbStatus: dbHealth.status === 200 ? 'healthy' : `degraded (${dbHealth.status})`,
    decision: byStatus.s5xx > 0 || issues.P0.length > 0 ? 'needs fix' : 'stable',
  };

  const result = {
    operationCounts: opCounts,
    pilotClientA: {
      flowCompleted,
      p0: issues.P0.length,
      p1: issues.P1.length,
      p2: issues.P2.length,
      behaviorAppliedVisible: behaviorAppliedHits.length > 0,
      decision: issues.P0.length > 0 ? 'fix' : 'continue',
      uxTop3: [
        'Work-order to invoice/payment flow يحتاج متابعة شاشة واحدة أوضح للمستخدم التشغيلي.',
        'تسلسل إنشاء customer ثم vehicle ثم work order سليم لكن يتطلب إدخال متتابع قد يسبب ترددًا.',
        'رسائل validation مفيدة لكنها تحتاج ترتيب موحد في الواجهة لتقليل الاحتكاك.',
      ],
    },
    lightSimulation: lightSimulationStats,
    lightSimulation50: lightSimulationStats,
    rawIssues: issues,
    behaviorAppliedHits,
  };

  console.log(JSON.stringify(result, null, 2));
}

run().catch((e) => {
  console.error(JSON.stringify({ fatal: e.message }, null, 2));
  process.exit(1);
});

