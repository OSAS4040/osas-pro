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

async function run() {
  // 1. Login
  const loginR = await request('/auth/login', 'POST', { email: 'owner@demo.sa', password: 'password' });
  const raw = JSON.parse(loginR.body);
  console.log('Login status:', loginR.status);
  console.log('Login response keys:', Object.keys(raw));
  console.log('data keys:', raw.data ? Object.keys(raw.data) : 'no data');
  console.log('Full response (truncated):', JSON.stringify(raw).substring(0, 500));
}

run().catch(console.error);
