const http = require('http');

function request(path, method, body, token) {
  return new Promise((resolve, reject) => {
    const data = body ? JSON.stringify(body) : null;
    const opts = {
      host: 'localhost', port: 80, path: '/api/v1' + path,
      method, headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' }
    };
    if (token) opts.headers['Authorization'] = 'Bearer ' + token;
    if (data)  opts.headers['Content-Length'] = Buffer.byteLength(data);
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

function get(path) { return request(path, 'GET', null, null); }

async function run() {
  console.log('\n══════════════════════════════════════════════════════════════');
  console.log('    OSAS — Full System E2E Verification Report');
  console.log('══════════════════════════════════════════════════════════════\n');

  // ── PORTAL 1: Login as OWNER (Staff Portal) ──────────────────────────────
  console.log('▌ PORTAL 1: Staff / Owner');
  const ownerLogin = await request('/auth/login', 'POST', { email: 'owner@demo.sa', password: 'password' });
  const ownerData = JSON.parse(ownerLogin.body);
  const ownerToken = ownerData.token || (ownerData.data && ownerData.data.token);
  console.log(`  Login: [${ownerLogin.status}] ${ownerToken ? '✅ owner@demo.sa' : '❌ NO TOKEN'}`);

  // ── PORTAL 2: Login as FLEET user ────────────────────────────────────────
  console.log('▌ PORTAL 2: Fleet');
  const fleetLogin = await request('/auth/login', 'POST', { email: 'fleet.manager@demo.sa', password: 'password' });
  const fleetData = JSON.parse(fleetLogin.body);
  const fleetToken = fleetData.token || (fleetData.data && fleetData.data.token);
  console.log(`  Login: [${fleetLogin.status}] ${fleetToken ? '✅ fleet.manager@demo.sa' : '⚠️ fleet user not found'}`);

  // ── PORTAL 3: Login as CUSTOMER ──────────────────────────────────────────
  console.log('▌ PORTAL 3: Customer');
  const custLogin = await request('/auth/login', 'POST', { email: 'customer@demo.sa', password: 'password' });
  const custData = JSON.parse(custLogin.body);
  const custToken = custData.token || (custData.data && custData.data.token);
  console.log(`  Login: [${custLogin.status}] ${custToken ? '✅ customer@demo.sa' : '⚠️ customer user not found'}\n`);

  if (!ownerToken) { console.log('Cannot proceed without owner token'); return; }
  const T = ownerToken;

  const tests = [
    // ── AUTH ──────────────────────────────────────────────────────────────
    { g: 'AUTH',            m: 'GET',  p: '/auth/me' },

    // ── CORE BUSINESS ─────────────────────────────────────────────────────
    { g: 'CUSTOMERS',       m: 'GET',  p: '/customers' },
    { g: 'VEHICLES',        m: 'GET',  p: '/vehicles' },
    { g: 'WORK ORDERS',     m: 'GET',  p: '/work-orders' },
    { g: 'INVOICES',        m: 'GET',  p: '/invoices' },
    { g: 'PRODUCTS',        m: 'GET',  p: '/products' },
    { g: 'INVENTORY',       m: 'GET',  p: '/inventory' },
    { g: 'SUPPLIERS',       m: 'GET',  p: '/suppliers' },
    { g: 'PURCHASES',       m: 'GET',  p: '/purchases' },
    { g: 'SERVICES',        m: 'GET',  p: '/services' },
    { g: 'QUOTES/CRM',      m: 'GET',  p: '/quotes' },
    { g: 'POS',             m: 'GET',  p: '/invoices?type=pos&limit=1' },

    // ── FINANCIAL ─────────────────────────────────────────────────────────
    { g: 'WALLET',          m: 'GET',  p: '/wallet' },
    { g: 'LEDGER',          m: 'GET',  p: '/ledger' },
    { g: 'REPORT: Sales',   m: 'GET',  p: '/reports/sales' },
    { g: 'REPORT: KPI',     m: 'GET',  p: '/reports/kpi' },
    { g: 'REPORT: Inventory',m:'GET',  p: '/reports/inventory' },
    { g: 'REPORT: VAT',     m: 'GET',  p: '/reports/vat' },
    { g: 'DASHBOARD KPI',   m: 'GET',  p: '/dashboard/kpi' },

    // ── WORKSHOP / HR ──────────────────────────────────────────────────────
    { g: 'EMPLOYEES',       m: 'GET',  p: '/workshop/employees' },
    { g: 'TASKS',           m: 'GET',  p: '/workshop/tasks' },
    { g: 'COMMISSIONS',     m: 'GET',  p: '/workshop/commissions' },
    { g: 'ATTENDANCE',      m: 'GET',  p: '/workshop/attendance/today' },
    { g: 'BAYS/LIFTS',      m: 'GET',  p: '/bays' },
    { g: 'BOOKINGS',        m: 'GET',  p: '/bookings' },
    { g: 'LEAVES',          m: 'GET',  p: '/governance/leaves' },
    { g: 'SALARIES',        m: 'GET',  p: '/governance/salaries' },

    // ── FLEET ──────────────────────────────────────────────────────────────
    { g: 'FLEET ACCOUNTS',  m: 'GET',  p: '/fleet/customers' },
    { g: 'FUEL MGMT',       m: 'GET',  p: '/governance/fuel' },

    // ── GOVERNANCE ─────────────────────────────────────────────────────────
    { g: 'POLICIES',        m: 'GET',  p: '/governance/policies' },
    { g: 'CONTRACTS',       m: 'GET',  p: '/governance/contracts' },
    { g: 'WORKFLOWS',       m: 'GET',  p: '/governance/workflows' },
    { g: 'AUDIT LOGS',      m: 'GET',  p: '/governance/audit-logs' },
    { g: 'REFERRALS',       m: 'GET',  p: '/governance/referrals' },

    // ── SUBSCRIPTION ───────────────────────────────────────────────────────
    { g: 'SUBSCRIPTION',    m: 'GET',  p: '/subscription' },
    { g: 'PLANS',           m: 'GET',  p: '/plans' },
  ];

  let pass = 0, fail = 0;
  let lastGroup = '';
  for (const t of tests) {
    if (t.g !== lastGroup) { console.log(`\n  ── ${t.g}`); lastGroup = t.g; }
    try {
      const r = await request(t.p, t.m, t.body || null, T);
      const ok = r.status < 400;
      if (ok) pass++; else fail++;
      let detail = '';
      try {
        const j = JSON.parse(r.body);
        const count = j.total || (j.data && Array.isArray(j.data) ? j.data.length : null);
        if (count !== null) detail = ` (${count} records)`;
      } catch(e) {}
      console.log(`    ${ok ? '✅' : '❌'} [${r.status}] ${t.m} ${t.p}${detail}`);
      if (!ok) {
        try { console.log(`       ↳ ${JSON.parse(r.body).message || ''}`.substring(0, 100)); } catch(e){}
      }
    } catch(e) {
      fail++;
      console.log(`    ❌ [NET] ${t.m} ${t.p} — ${e.message}`);
    }
  }

  // ── Frontend portals ────────────────────────────────────────────────────
  console.log('\n  ── FRONTEND PORTALS');
  const portals = [
    ['/', 'Staff Portal'],
    ['/customer', 'Customer Portal'],
    ['/fleet-portal', 'Fleet Portal'],
    ['/portal', 'Unified Login'],
  ];
  for (const [path, label] of portals) {
    try {
      const r = await new Promise((res, rej) => {
        const req = http.get({ host: 'localhost', port: 80, path }, r2 => {
          let b = ''; r2.on('data', d => b += d); r2.on('end', () => res({ s: r2.statusCode, b }));
        });
        req.on('error', rej);
      });
      const ok = r.s < 400;
      if (ok) pass++; else fail++;
      console.log(`    ${ok ? '✅' : '❌'} [${r.s}] ${label} (${path})`);
    } catch(e) {
      fail++;
      console.log(`    ❌ [NET] ${label} — ${e.message}`);
    }
  }

  console.log(`\n══════════════════════════════════════════════════════════════`);
  const total = pass + fail;
  const pct = Math.round(pass / total * 100);
  console.log(`  ✅ PASS: ${pass}   ❌ FAIL: ${fail}   Total: ${total}   Rate: ${pct}%`);
  if (fail === 0) console.log('\n  🎉 ALL TESTS PASSED — SYSTEM IS 100% OPERATIONAL');
  else console.log('\n  ⚠️  Some tests failed — see details above');
  console.log('══════════════════════════════════════════════════════════════\n');
}

run().catch(console.error);
