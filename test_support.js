const http = require('http');
let pass = 0, fail = 0;

function req(path, method, body, token) {
  return new Promise((resolve, reject) => {
    const data = body ? JSON.stringify(body) : null;
    const opts = {
      host: 'localhost', port: 80, path: '/api/v1' + path, method,
      headers: {
        'Accept': 'application/json', 'Content-Type': 'application/json',
        ...(token ? { Authorization: 'Bearer ' + token } : {}),
        ...(data ? { 'Content-Length': Buffer.byteLength(data) } : {})
      }
    };
    const r = http.request(opts, res => {
      let b = ''; res.on('data', d => b += d);
      res.on('end', () => resolve({ s: res.statusCode, b }));
    });
    r.on('error', e => reject(e));
    if (data) r.write(data);
    r.end();
  });
}

function ok(label, s, expected) {
  const passed = expected.includes(s);
  if (passed) { pass++; console.log('  OK  [' + s + '] ' + label); }
  else { fail++; console.log('  FAIL[' + s + '] ' + label); }
  return passed;
}

async function run() {
  console.log('\n=== SUPPORT SYSTEM E2E TEST ===\n');

  const lr = await req('/auth/login', 'POST', { email: 'owner@demo.sa', password: 'password' }, null);
  const token = JSON.parse(lr.b).token;
  ok('Login', lr.s, [200]);

  // List endpoints
  const endpoints = [
    ['/support/tickets', 'GET Tickets'],
    ['/support/stats', 'GET Stats'],
    ['/support/sla-policies', 'GET SLA Policies'],
    ['/support/kb', 'GET KB Articles'],
    ['/support/kb-categories', 'GET KB Categories'],
  ];
  for (const [path, label] of endpoints) {
    const r = await req(path, 'GET', null, token);
    const d = JSON.parse(r.b);
    const count = d.data?.total ?? (Array.isArray(d.data) ? d.data.length : (d.data?.data?.length ?? '?'));
    ok(label + ' (' + count + ' records)', r.s, [200]);
  }

  // Create ticket
  const cr = await req('/support/tickets', 'POST', {
    subject: 'Support System Test Ticket',
    description: 'Automated e2e test for support module verification',
    category: 'technical', priority: 'high', channel: 'portal'
  }, token);
  ok('POST Create Ticket', cr.s, [201]);
  const ticket = JSON.parse(cr.b).data;
  console.log('    Ticket#:', ticket?.ticket_number, '| AI Cat:', ticket?.ai_category_suggestion, '| AI Pri:', ticket?.ai_priority_suggestion);

  if (ticket?.id) {
    // Get ticket detail
    const gr = await req('/support/tickets/' + ticket.id, 'GET', null, token);
    ok('GET Ticket Detail', gr.s, [200]);
    const d = JSON.parse(gr.b);
    console.log('    SLA Due:', d.data?.sla_due_at, '| Sentiment:', d.data?.ai_sentiment_score);
    console.log('    KB Suggestions:', (d.suggested_articles || []).length, 'articles');

    // Add reply
    const rr = await req('/support/tickets/' + ticket.id + '/replies', 'POST', {
      body: 'Your ticket has been received and will be reviewed within 4 hours.',
      is_internal: false
    }, token);
    ok('POST Reply', rr.s, [201]);

    // Change status
    const sr = await req('/support/tickets/' + ticket.id + '/status', 'PATCH', {
      status: 'in_progress', comment: 'Working on the issue now'
    }, token);
    ok('PATCH Status -> in_progress', sr.s, [200]);

    // Satisfaction rating
    const sat = await req('/support/tickets/' + ticket.id + '/rate', 'POST', {
      score: 5, comment: 'Excellent and fast support'
    }, token);
    ok('POST Satisfaction 5/5', sat.s, [200]);
  }

  // Stats check
  const st = await req('/support/stats', 'GET', null, token);
  ok('GET Stats final', st.s, [200]);
  const stats = JSON.parse(st.b).data;
  console.log('    Stats: total=' + stats?.total + ' overdue=' + stats?.overdue + ' avg_sat=' + stats?.avg_satisfaction);

  // Create SLA policy
  const slr = await req('/support/sla-policies', 'POST', {
    name: 'Test SLA', priority: 'critical', first_response_hours: 1,
    resolution_hours: 4, escalation_after_hours: 2
  }, token);
  ok('POST SLA Policy', slr.s, [201]);

  // Create KB article
  const kbr = await req('/support/kb', 'POST', {
    title: 'How to use Support System',
    summary: 'Step-by-step guide for raising support tickets',
    content: '<p>Navigate to Support and click New Ticket</p>',
    status: 'published', is_featured: true
  }, token);
  ok('POST KB Article', kbr.s, [201]);

  // Create KB category
  const ctr = await req('/support/kb-categories', 'POST', {
    name: 'General Help', name_ar: 'مساعدة عامة', icon: 'QuestionMarkCircleIcon', color: '#6366F1'
  }, token);
  ok('POST KB Category', ctr.s, [201]);

  // SLA breach check
  const br = await req('/support/sla/check-breaches', 'POST', {}, token);
  ok('POST SLA Breach Check', br.s, [200]);
  console.log('    Breached tickets auto-escalated:', JSON.parse(br.b).breached_count);

  console.log('\n=== RESULTS: PASS=' + pass + ' FAIL=' + fail + ' (' + Math.round(pass/(pass+fail)*100) + '%) ===\n');
}

run().catch(console.error);
