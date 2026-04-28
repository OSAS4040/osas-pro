const fs = require('fs');
const path = require('path');

const root = 'C:/Users/nawaf/.verdent/verdent-projects/new-project-3';
const docsDir = path.join(root, 'docs/system-master-guide-ar');
const frontendRoot = path.join(root, 'frontend/src');
const backendApiPath = path.join(root, 'backend/routes/api.php');

function walk(dir, out) {
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  for (const e of entries) {
    const full = path.join(dir, e.name);
    if (e.isDirectory()) walk(full, out);
    else out.push(full);
  }
}

function rel(p) {
  return p.replaceAll('\\', '/').replace(root.replaceAll('\\', '/') + '/', '');
}

function normalizePath(p) {
  if (!p) return '';
  return p
    .replace(/^\/api\/v1/, '')
    .replace(/\$\{[^}]+\}/g, '{param}')
    .replace(/\/+$/, '')
    .replace(/\/:[^/]+/g, '/{param}')
    .replace(/\{[^/]+\}/g, '{param}')
    .trim();
}

function splitSeg(p) {
  return p.split('/').filter(Boolean);
}

function strongMatch(frontNorm, backNorm) {
  const a = splitSeg(frontNorm);
  const b = splitSeg(backNorm);
  if (a.length !== b.length) return false;
  for (let i = 0; i < a.length; i += 1) {
    if (a[i] === b[i]) continue;
    // نسمح فقط عندما الخلفية هي المعلّمة كوسيط.
    // إذا الواجهة وسيط والخلفية ثابتة فهذا غالبًا ليس نفس المسار.
    if (b[i] === '{param}') continue;
    return false;
  }
  return true;
}

function similarity(frontNorm, backNorm) {
  const a = splitSeg(frontNorm);
  const b = splitSeg(backNorm);
  const len = Math.max(a.length, b.length);
  if (len === 0) return 0;
  let score = 0;
  for (let i = 0; i < Math.min(a.length, b.length); i += 1) {
    if (a[i] === b[i]) score += 1;
    else if (a[i] === '{param}' || b[i] === '{param}') score += 0.5;
  }
  return score / len;
}

function extractFrontendCalls() {
  const files = [];
  walk(frontendRoot, files);
  const target = files.filter((f) => /\.(vue|ts|tsx|js)$/.test(f));
  const rows = [];

  // Patterns for common API clients
  const patterns = [
    /(?:api|http|client)\.(get|post|put|patch|delete)\(\s*['"`]([^'"`]+)['"`]/g,
    /fetch\(\s*['"`]([^'"`]+)['"`]/g,
  ];

  for (const file of target) {
    const src = fs.readFileSync(file, 'utf8');
    let m;
    while ((m = patterns[0].exec(src))) {
      const method = m[1].toUpperCase();
      const url = m[2];
      if (!url.startsWith('/')) continue;
      rows.push({
        sourceFile: rel(file),
        method,
        endpointRaw: url,
        endpointNormalized: normalizePath(url),
      });
    }
    while ((m = patterns[1].exec(src))) {
      const url = m[1];
      if (!url.startsWith('/')) continue;
      rows.push({
        sourceFile: rel(file),
        method: 'FETCH',
        endpointRaw: url,
        endpointNormalized: normalizePath(url),
      });
    }
  }

  // unique by file+method+raw
  const map = new Map();
  for (const r of rows) {
    const k = `${r.sourceFile}|${r.method}|${r.endpointRaw}`;
    if (!map.has(k)) map.set(k, r);
  }
  return [...map.values()];
}

function extractBackendRoutes() {
  const src = fs.readFileSync(backendApiPath, 'utf8');
  const rows = [];

  // captures full route statement chunk until semicolon
  const routeRe = /Route::(get|post|put|patch|delete)\(\s*'([^']+)'[\s\S]*?\);/g;
  let m;
  while ((m = routeRe.exec(src))) {
    const block = m[0];
    const method = m[1].toUpperCase();
    const routePath = m[2];
    const normalized = normalizePath(routePath);

    const middlewareMatches = [...block.matchAll(/middleware\(([^)]+)\)/g)].map((x) =>
      x[1].replace(/\s+/g, ' ').trim()
    );
    const permissionTags = [];
    for (const mw of middlewareMatches) {
      const p = [...mw.matchAll(/'(permission:[^']+|platform\.permission:[^']+)'/g)];
      for (const item of p) permissionTags.push(item[1]);
    }

    // المسارات التي تبدأ مباشرة بـ /{...} تكون غالبًا داخل prefix group
    // ولا يمكن ربطها بثقة عالية دون محلل AST كامل لهيكل المجموعات.
    if (routePath.startsWith('/{')) {
      continue;
    }

    rows.push({
      method,
      routePath,
      normalized,
      middleware: middlewareMatches.join(' ; '),
      permissions: [...new Set(permissionTags)].join(', '),
    });
  }
  return rows;
}

function matchFrontendToBackend(frontRows, backRows) {
  const out = [];
  for (const f of frontRows) {
    const candidates = backRows.filter((b) => b.method === f.method);
    const exact = candidates.filter((b) => strongMatch(f.endpointNormalized, b.normalized));

    if (exact.length > 0) {
      const b = exact[0];
      out.push({
        ...f,
        backendMethod: b.method,
        backendPath: b.routePath,
        backendMiddleware: b.middleware,
        backendPermissions: b.permissions,
        confidence: 'high',
      });
    } else {
      let best = null;
      let bestScore = 0;
      for (const b of candidates) {
        const s = similarity(f.endpointNormalized, b.normalized);
        if (s > bestScore) {
          bestScore = s;
          best = b;
        }
      }
      if (best && bestScore >= 0.6) {
        out.push({
          ...f,
          backendMethod: best.method,
          backendPath: best.routePath,
          backendMiddleware: best.middleware,
          backendPermissions: best.permissions,
          confidence: 'medium',
        });
      } else {
        out.push({
          ...f,
          backendMethod: '',
          backendPath: '',
          backendMiddleware: '',
          backendPermissions: '',
          confidence: 'unmatched',
        });
      }
    }
  }
  return out;
}

const front = extractFrontendCalls();
const back = extractBackendRoutes();
const matched = matchFrontendToBackend(front, back);

const md = [];
md.push('# ملحق A5 - مصفوفة ربط الشاشة مع API والخلفية');
md.push('');
md.push('مستخرج آليًا من `frontend/src` و`backend/routes/api.php`.');
md.push('الغرض: تمكين الدعم والتشغيل من تتبع رحلة الطلب من الشاشة حتى قيود الخلفية.');
md.push('');
md.push(`- استدعاءات واجهة مفهرسة: **${front.length}**`);
md.push(`- مسارات خلفية مفهرسة: **${back.length}**`);
md.push(`- روابط ناتجة: **${matched.length}**`);
md.push('');
md.push('> `confidence=high`: تطابق قوي بالمسار/المنهج، `medium`: تطابق تقريبي، `unmatched`: لم يوجد ربط واضح آليًا.');
md.push('');
md.push('| # | frontend file | method | frontend endpoint | backend route | backend middleware/permissions | confidence |');
md.push('|---|---|---|---|---|---|---|');

matched.forEach((r, i) => {
  const backCell = r.backendPath ? `\`${r.backendMethod} ${r.backendPath}\`` : '—';
  const mw = [r.backendMiddleware, r.backendPermissions].filter(Boolean).join(' || ');
  md.push(
    `| ${i + 1} | \`${r.sourceFile}\` | \`${r.method}\` | \`${r.endpointRaw}\` | ${backCell} | \`${mw || '—'}\` | \`${r.confidence}\` |`
  );
});

fs.writeFileSync(path.join(docsDir, 'A5_ربط_الشاشات_API_الخلفية.md'), md.join('\n') + '\n', 'utf8');
console.log(`Generated A5: front=${front.length}, back=${back.length}, links=${matched.length}`);
