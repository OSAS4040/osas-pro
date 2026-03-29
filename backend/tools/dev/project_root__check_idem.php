const http = require('http');
function req(path, method, body, token) {
  return new Promise((resolve) => {
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
    r.on('error', e => resolve({ s: 0, b: e.message }));
    if (data) r.write(data);
    r.end();
  });
}

async function diagnose() {
  const lr = await req('/auth/login', 'POST', { email: 'owner@demo.sa', password: 'password' }, null);
  const t = JSON.parse(lr.b).token;
  const r1 = await req('/support/tickets/1', 'GET', null, t);
  console.log('showTicket [' + r1.s + ']:', r1.b.substring(0, 500));
}
diagnose().catch(console.error);
