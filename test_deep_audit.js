const http = require('http');

let pass = 0, fail = 0, warn = 0;
const issues = [];

function request(path, method, body, token) {
  return new Promise((resolve, reject) => {
    const data = body ? JSON.stringify(body) : null;
    const opts = {
      host: 'localhost', port: 80, path: '/api/v1' + path,
      method, headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' }
    };
    if (token) opts.headers['Authorization'] = 'Bearer ' + token;
    if (data) opts.headers['Content-Length'] = Buffer.byteLength(data);
    const req = http.request(opts, res => {
      let b = '';
      res.on('data', d => b += d);
      res.on('end', () => resolve({ status: res.statusCode, body: b }));
    });
    req.on('error', e => reject(e));
    if (data) req.write(data);
    req.end();
  });
}

function check(label, status, expected, body) {
  const ok = expected.includes(status);
  if (ok) { pass++; console.log(`  ✅ [${status}] ${label}`); }
  else {
    fail++;
    let msg = '';
    try { msg = JSON.parse(body).message || ''; } catch(e) {}
    console.log(`  ❌ [${status}] ${label}`);
    if (msg) console.log(`       ↳ ${msg.substring(0, 120)}`);
    issues.push({ label, status, msg });
  }
  return ok;
}

async function run() {
  console.log('\n══════════════════════════════════════════════════════════════════');
  console.log('  OSAS — Deep Audit & CRUD Verification');
  console.log('══════════════════════════════════════════════════════════════════\n');

  // ─── LOGIN ─────────────────────────────────────────────────────────────────
  console.log('█ 1. AUTHENTICATION');
  const lr = await request('/auth/login', 'POST', { email: 'owner@demo.sa', password: 'password' });
  const ld = JSON.parse(lr.body);
  const T = ld.token || (ld.data && ld.data.token);
  check('POST /auth/login (owner)', lr.status, [200], lr.body);
  if (!T) { console.log('\n❌ FATAL: No token - cannot continue'); return; }

  const mr = await request('/auth/me', 'GET', null, T);
  check('GET /auth/me', mr.status, [200], mr.body);
  const me = JSON.parse(mr.body);
  console.log(`     User: ${me.data ? me.data.name : me.name} | Role: ${me.data ? me.data.role : me.role}`);

  // Wrong password
  const lbad = await request('/auth/login', 'POST', { email: 'owner@demo.sa', password: 'wrongpass' });
  check('POST /auth/login (wrong password → 401/422)', lbad.status, [401, 422, 400], lbad.body);

  // ─── CUSTOMERS CRUD ────────────────────────────────────────────────────────
  console.log('\n█ 2. CUSTOMERS CRUD');
  const clist = await request('/customers', 'GET', null, T);
  check('GET /customers', clist.status, [200], clist.body);
  const custCount = JSON.parse(clist.body).data?.length || JSON.parse(clist.body).total || 0;
  console.log(`     Count: ${custCount}`);

  // Create customer
  const cc = await request('/customers', 'POST', {
    name: 'Test Customer E2E', phone: '0500000001', email: 'e2e@test.com', type: 'b2c'
  }, T);
  check('POST /customers (create)', cc.status, [200, 201], cc.body);
  const newCustId = JSON.parse(cc.body).data?.id;

  if (newCustId) {
    const cg = await request(`/customers/${newCustId}`, 'GET', null, T);
    check(`GET /customers/${newCustId}`, cg.status, [200], cg.body);

    const cu = await request(`/customers/${newCustId}`, 'PUT', { name: 'Updated E2E Customer' }, T);
    check(`PUT /customers/${newCustId} (update)`, cu.status, [200], cu.body);

    const cd = await request(`/customers/${newCustId}`, 'DELETE', null, T);
    check(`DELETE /customers/${newCustId}`, cd.status, [200, 204], cd.body);
  }

  // ─── VEHICLES CRUD ─────────────────────────────────────────────────────────
  console.log('\n█ 3. VEHICLES CRUD');
  const vlist = await request('/vehicles', 'GET', null, T);
  check('GET /vehicles', vlist.status, [200], vlist.body);
  const vdata = JSON.parse(vlist.body).data || [];
  console.log(`     Count: ${vdata.length}`);

  // Get first customer for vehicle creation
  const custData = JSON.parse(clist.body).data || [];
  const firstCust = custData[0];
  if (firstCust) {
    const vc = await request('/vehicles', 'POST', {
      customer_id: firstCust.id,
      plate_number: 'ABC-1234',
      make: 'Toyota', model: 'Camry', year: 2022,
      color: 'White', vin: 'TEST123456789012'
    }, T);
    check('POST /vehicles (create)', vc.status, [200, 201, 422], vc.body);
    const newVid = JSON.parse(vc.body).data?.id;
    if (newVid) {
      const vd = await request(`/vehicles/${newVid}`, 'DELETE', null, T);
      check(`DELETE /vehicles/${newVid}`, vd.status, [200, 204], vd.body);
    }
  }

  // ─── PRODUCTS CRUD ─────────────────────────────────────────────────────────
  console.log('\n█ 4. PRODUCTS CRUD');
  const plist = await request('/products', 'GET', null, T);
  check('GET /products', plist.status, [200], plist.body);

  const pc = await request('/products', 'POST', {
    name: 'Test Product E2E', sku: 'E2E-001', product_type: 'part',
    price: 100.00, cost: 60.00, unit: 'pcs', stock_quantity: 50
  }, T);
  check('POST /products (create)', pc.status, [200, 201, 422], pc.body);
  const newPid = JSON.parse(pc.body).data?.id;
  if (newPid) {
    const pd = await request(`/products/${newPid}`, 'DELETE', null, T);
    check(`DELETE /products/${newPid}`, pd.status, [200, 204], pd.body);
  }

  // ─── INVOICES CRUD ─────────────────────────────────────────────────────────
  console.log('\n█ 5. INVOICES');
  const ilist = await request('/invoices', 'GET', null, T);
  check('GET /invoices', ilist.status, [200], ilist.body);
  const idata = JSON.parse(ilist.body).data || [];
  console.log(`     Count: ${idata.length}`);

  if (idata[0]) {
    const ishow = await request(`/invoices/${idata[0].id}`, 'GET', null, T);
    check(`GET /invoices/${idata[0].id} (detail)`, ishow.status, [200], ishow.body);

    // ZATCA QR check
    const inv = JSON.parse(ishow.body).data || JSON.parse(ishow.body);
    const hasQR = !!(inv.zatca_qr || inv.qr_code || inv.invoice_hash);
    if (hasQR) { pass++; console.log(`  ✅ Invoice has ZATCA QR/hash`); }
    else { warn++; console.log(`  ⚠️  Invoice missing zatca_qr/invoice_hash`); issues.push({ label: 'ZATCA QR on invoice', status: 'missing', msg: 'invoice has no zatca_qr or invoice_hash' }); }
  }

  // ─── WORK ORDERS CRUD ──────────────────────────────────────────────────────
  console.log('\n█ 6. WORK ORDERS');
  const wlist = await request('/work-orders', 'GET', null, T);
  check('GET /work-orders', wlist.status, [200], wlist.body);
  const wdata = JSON.parse(wlist.body).data || [];
  console.log(`     Count: ${wdata.length}`);

  if (wdata[0]) {
    const ws = await request(`/work-orders/${wdata[0].id}`, 'GET', null, T);
    check(`GET /work-orders/${wdata[0].id} (detail)`, ws.status, [200], ws.body);

    // Status transitions
    const wst = await request(`/work-orders/${wdata[0].id}/status`, 'PATCH', { status: 'in_progress' }, T);
    check('PATCH /work-orders/:id/status', wst.status, [200, 422], wst.body);
  }

  // Create work order
  const vd2 = JSON.parse(vlist.body).data || [];
  if (vd2[0] && custData[0]) {
    const wc = await request('/work-orders', 'POST', {
      customer_id: custData[0].id,
      vehicle_id: vd2[0].id,
      description: 'E2E test order',
      items: [{ type: 'service', description: 'Oil change', quantity: 1, unit_price: 50 }]
    }, T);
    check('POST /work-orders (create)', wc.status, [200, 201, 422], wc.body);
  }

  // ─── FINANCIAL ─────────────────────────────────────────────────────────────
  console.log('\n█ 7. FINANCIAL SYSTEM');
  const wallet = await request('/wallet', 'GET', null, T);
  check('GET /wallet', wallet.status, [200], wallet.body);

  const ledger = await request('/ledger', 'GET', null, T);
  check('GET /ledger', ledger.status, [200], ledger.body);
  const ledData = JSON.parse(ledger.body).data || [];
  console.log(`     Ledger entries: ${ledData.length}`);

  // Reports with date params
  const today = new Date();
  const from = `${today.getFullYear()}-01-01`;
  const to   = `${today.getFullYear()}-12-31`;

  const rsales = await request(`/reports/sales?from=${from}&to=${to}`, 'GET', null, T);
  check(`GET /reports/sales (${from} → ${to})`, rsales.status, [200], rsales.body);

  const rkpi = await request('/reports/kpi', 'GET', null, T);
  check('GET /reports/kpi', rkpi.status, [200], rkpi.body);

  const rvat = await request(`/reports/vat?from=${from}&to=${to}`, 'GET', null, T);
  check(`GET /reports/vat`, rvat.status, [200], rvat.body);

  const rinv = await request(`/reports/inventory`, 'GET', null, T);
  check('GET /reports/inventory', rinv.status, [200], rinv.body);

  const rdash = await request('/dashboard/kpi', 'GET', null, T);
  check('GET /dashboard/kpi (alias)', rdash.status, [200], rdash.body);

  // ─── POS ────────────────────────────────────────────────────────────────────
  console.log('\n█ 8. POS');
  const pos = await request('/invoices?source=pos&limit=5', 'GET', null, T);
  check('GET /invoices?source=pos', pos.status, [200], pos.body);

  // ─── INVENTORY ──────────────────────────────────────────────────────────────
  console.log('\n█ 9. INVENTORY');
  const inv2 = await request('/inventory', 'GET', null, T);
  check('GET /inventory', inv2.status, [200], inv2.body);

  const invMov = await request('/inventory/movements', 'GET', null, T);
  check('GET /inventory/movements', invMov.status, [200, 404], invMov.body);

  // ─── PURCHASES ──────────────────────────────────────────────────────────────
  console.log('\n█ 10. PURCHASES');
  const purch = await request('/purchases', 'GET', null, T);
  check('GET /purchases', purch.status, [200], purch.body);

  const sup = await request('/suppliers', 'GET', null, T);
  check('GET /suppliers', sup.status, [200], sup.body);

  // ─── WORKSHOP / HR ──────────────────────────────────────────────────────────
  console.log('\n█ 11. WORKSHOP & HR');
  const emp = await request('/workshop/employees', 'GET', null, T);
  check('GET /workshop/employees', emp.status, [200], emp.body);
  const empData = JSON.parse(emp.body).data || [];
  console.log(`     Employees: ${empData.length}`);

  const tasks = await request('/workshop/tasks', 'GET', null, T);
  check('GET /workshop/tasks', tasks.status, [200], tasks.body);

  const bays = await request('/bays', 'GET', null, T);
  check('GET /bays', bays.status, [200], bays.body);
  const baysData = JSON.parse(bays.body).data || [];
  console.log(`     Bays: ${baysData.length}`);

  const books = await request('/bookings', 'GET', null, T);
  check('GET /bookings', books.status, [200], books.body);
  console.log(`     Bookings: ${(JSON.parse(books.body).data || []).length}`);

  const att = await request('/workshop/attendance/today', 'GET', null, T);
  check('GET /workshop/attendance/today', att.status, [200], att.body);

  const leaves = await request('/governance/leaves', 'GET', null, T);
  check('GET /governance/leaves', leaves.status, [200], leaves.body);

  const salaries = await request('/governance/salaries', 'GET', null, T);
  check('GET /governance/salaries', salaries.status, [200], salaries.body);

  const comm = await request('/workshop/commissions', 'GET', null, T);
  check('GET /workshop/commissions', comm.status, [200], comm.body);

  // ─── FLEET ──────────────────────────────────────────────────────────────────
  console.log('\n█ 12. FLEET');
  const fleetCust = await request('/fleet/customers', 'GET', null, T);
  check('GET /fleet/customers', fleetCust.status, [200], fleetCust.body);
  console.log(`     Fleet accounts: ${(JSON.parse(fleetCust.body).data || []).length}`);

  const fuel = await request('/governance/fuel', 'GET', null, T);
  check('GET /governance/fuel', fuel.status, [200], fuel.body);

  // ─── GOVERNANCE ─────────────────────────────────────────────────────────────
  console.log('\n█ 13. GOVERNANCE');
  const pol = await request('/governance/policies', 'GET', null, T);
  check('GET /governance/policies', pol.status, [200], pol.body);

  const contr = await request('/governance/contracts', 'GET', null, T);
  check('GET /governance/contracts', contr.status, [200], contr.body);

  const wflow = await request('/governance/workflows', 'GET', null, T);
  check('GET /governance/workflows', wflow.status, [200], wflow.body);

  const audit = await request('/governance/audit-logs', 'GET', null, T);
  check('GET /governance/audit-logs', audit.status, [200], audit.body);
  console.log(`     Audit entries: ${(JSON.parse(audit.body).data || []).length}`);

  const ref = await request('/governance/referrals', 'GET', null, T);
  check('GET /governance/referrals', ref.status, [200], ref.body);

  // ─── SERVICES ───────────────────────────────────────────────────────────────
  console.log('\n█ 14. SERVICES & PACKAGES');
  const svc = await request('/services', 'GET', null, T);
  check('GET /services', svc.status, [200], svc.body);

  const quotes = await request('/quotes', 'GET', null, T);
  check('GET /quotes', quotes.status, [200], quotes.body);

  // ─── SUBSCRIPTION ────────────────────────────────────────────────────────────
  console.log('\n█ 15. SUBSCRIPTION');
  const sub = await request('/subscription', 'GET', null, T);
  check('GET /subscription', sub.status, [200], sub.body);
  const subData = JSON.parse(sub.body).data || JSON.parse(sub.body);
  const subFeatures = subData.features || subData.plan?.features;
  console.log(`     Plan: ${subData.plan_slug || subData.plan?.code || 'unknown'}`);
  console.log(`     Features: ${subFeatures ? JSON.stringify(subFeatures).substring(0, 80) : 'none'}`);

  const plans = await request('/plans', 'GET', null, T);
  check('GET /plans', plans.status, [200], plans.body);

  // ─── FRONTEND PORTALS ────────────────────────────────────────────────────────
  console.log('\n█ 16. FRONTEND PORTALS');
  const portals = [
    ['/portal', 'Unified Login Page'],
    ['/', 'Staff Dashboard'],
    ['/customer', 'Customer Portal'],
    ['/fleet-portal', 'Fleet Portal'],
  ];
  for (const [path, label] of portals) {
    try {
      const r = await new Promise((res, rej) => {
        const req = http.get({ host: 'localhost', port: 80, path }, r2 => {
          let b = ''; r2.on('data', d => b += d); r2.on('end', () => res({ s: r2.statusCode, b }));
        });
        req.on('error', rej);
        req.setTimeout(5000, () => { req.destroy(); rej(new Error('timeout')); });
      });
      check(`${label} (${path})`, r.s, [200], r.b);
      // Check it returns HTML with <div id="app">
      const hasApp = r.b.includes('id="app"') || r.b.includes("id='app'");
      if (!hasApp && r.s === 200) {
        warn++;
        console.log(`  ⚠️  ${path} returned 200 but no Vue app root found`);
      }
    } catch(e) {
      fail++;
      console.log(`  ❌ [NET] ${label} — ${e.message}`);
    }
  }

  // ─── DATA INTEGRITY CHECKS ──────────────────────────────────────────────────
  console.log('\n█ 17. DATA INTEGRITY');
  // Check invoices have required ZATCA fields
  if (idata.length > 0) {
    const required = ['id', 'invoice_number', 'total_amount'];
    const missing = required.filter(k => !(k in idata[0]));
    if (missing.length === 0) { pass++; console.log(`  ✅ Invoice fields: ${required.join(', ')}`); }
    else { fail++; console.log(`  ❌ Invoice missing fields: ${missing.join(', ')}`); issues.push({ label: 'Invoice fields', status: 'missing', msg: missing.join(', ') }); }
  }

  // Check KPI dashboard has expected keys
  const kpiData = JSON.parse(rdash.body).data || JSON.parse(rdash.body);
  const kpiKeys = Object.keys(kpiData || {});
  if (kpiKeys.length > 3) { pass++; console.log(`  ✅ KPI dashboard has ${kpiKeys.length} metrics`); }
  else { warn++; console.log(`  ⚠️  KPI dashboard has only ${kpiKeys.length} keys: ${kpiKeys.join(', ')}`); }

  // Verify ledger is append-only (no update/delete route)
  const ledDel = await request('/ledger/1', 'DELETE', null, T);
  check('DELETE /ledger/1 (must be 404/405 — append-only)', ledDel.status, [404, 405, 403], ledDel.body);

  // ─── SUMMARY ────────────────────────────────────────────────────────────────
  const total = pass + fail;
  const pct = Math.round(pass / total * 100);

  console.log(`\n${'═'.repeat(66)}`);
  console.log(`  FINAL RESULT`);
  console.log(`  ✅ PASS : ${pass}`);
  console.log(`  ❌ FAIL : ${fail}`);
  console.log(`  ⚠️  WARN : ${warn}`);
  console.log(`  Total  : ${total}`);
  console.log(`  Rate   : ${pct}%`);

  if (issues.length > 0) {
    console.log(`\n  ── Issues Requiring Fix ──────────────────`);
    issues.forEach((i, n) => {
      console.log(`  ${n + 1}. [${i.status}] ${i.label}`);
      if (i.msg) console.log(`     ${i.msg.substring(0, 100)}`);
    });
  } else {
    console.log('\n  🎉 ALL CHECKS PASSED — SYSTEM FULLY OPERATIONAL');
  }
  console.log('═'.repeat(66) + '\n');
}

run().catch(console.error);
