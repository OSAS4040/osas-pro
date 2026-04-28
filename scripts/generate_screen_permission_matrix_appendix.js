const fs = require('fs');
const path = require('path');

const root = 'C:/Users/nawaf/.verdent/verdent-projects/new-project-3';
const docsDir = path.join(root, 'docs/system-master-guide-ar');
const routerPath = path.join(root, 'frontend/src/router/index.ts');
const src = fs.readFileSync(routerPath, 'utf8');

function readString(chunk, key) {
  const m = chunk.match(new RegExp(`${key}:\\s*'([^']+)'`));
  return m ? m[1] : '';
}

function readBool(chunk, key) {
  const m = chunk.match(new RegExp(`${key}:\\s*(true|false)`));
  return m ? m[1] : '';
}

function readArray(chunk, key) {
  const m = chunk.match(new RegExp(`${key}:\\s*\\[([\\s\\S]*?)\\]`));
  if (!m) return [];
  const vals = [];
  const re = /'([^']+)'/g;
  let x;
  while ((x = re.exec(m[1]))) vals.push(x[1]);
  return vals;
}

function portalFromPath(p) {
  if (p.startsWith('/platform') || p.startsWith('/admin')) return 'admin';
  if (p.startsWith('/fleet-portal')) return 'fleet';
  if (p.startsWith('/customer')) return 'customer';
  return 'staff';
}

const chunks = src.split(/(?=\{\s*path:\s*')/g);
const rows = [];

for (const chunk of chunks) {
  const pathMatch = chunk.match(/path:\s*'([^']+)'/);
  const nameMatch = chunk.match(/name:\s*'([^']+)'/);
  const compMatch = chunk.match(/component:\s*\(\)\s*=>\s*import\('([^']+)'\)/);
  if (!pathMatch || !nameMatch || !compMatch) continue;

  const routePath = pathMatch[1];
  const routeName = nameMatch[1];
  const file = compMatch[1].replace(/^@\//, 'frontend/src/');

  const portal = readString(chunk, 'portal') || portalFromPath(routePath);
  const requiresAuth = readBool(chunk, 'requiresAuth');
  const guest = readBool(chunk, 'guest');
  const requiresPlatformAdmin = readBool(chunk, 'requiresPlatformAdmin');
  const requiresManager = readBool(chunk, 'requiresManager');
  const requiresOwner = readBool(chunk, 'requiresOwner');
  const requiresPermission = readString(chunk, 'requiresPermission');
  const requiresBusinessFeature = readString(chunk, 'requiresBusinessFeature');
  const anyPermissions = readArray(chunk, 'requiresAnyPermission');
  const allPermissions = readArray(chunk, 'requiresAllPermissions');
  const intelligenceCommandCenter = readBool(chunk, 'intelligenceCommandCenter');
  const staffIntelligenceBi = readBool(chunk, 'staffIntelligenceBi');
  const featureInactive = readBool(chunk, 'featureInactive');
  const unavailablePreview = readBool(chunk, 'unavailablePreview');
  const electronicArchive = readBool(chunk, 'electronicArchive');

  rows.push({
    routeName,
    routePath,
    file,
    portal,
    requiresAuth,
    guest,
    requiresPlatformAdmin,
    requiresManager,
    requiresOwner,
    requiresPermission,
    anyPermissions,
    allPermissions,
    requiresBusinessFeature,
    intelligenceCommandCenter,
    staffIntelligenceBi,
    featureInactive,
    unavailablePreview,
    electronicArchive,
  });
}

rows.sort((a, b) => a.routePath.localeCompare(b.routePath));

const md = [];
md.push('# ملحق A4 - مصفوفة صلاحية الشاشات');
md.push('');
md.push('مستخرج آليًا من `frontend/src/router/index.ts` (meta guards + route config).');
md.push('هذا الملحق مرجع تشغيلي لفِرق الدعم والتدريب والتشغيل لتحديد سبب الظهور/المنع لكل شاشة.');
md.push('');
md.push(`إجمالي السجلات: **${rows.length}**`);
md.push('');
md.push('| # | route name | path | portal | requiresAuth | guest | requiresPlatformAdmin | requiresManager | requiresOwner | requiresPermission | requiresAnyPermission | requiresAllPermissions | requiresBusinessFeature | feature states | file |');
md.push('|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|');

rows.forEach((r, i) => {
  const featureStates = [
    r.intelligenceCommandCenter === 'true' ? 'intelligenceCommandCenter' : '',
    r.staffIntelligenceBi === 'true' ? 'staffIntelligenceBi' : '',
    r.featureInactive === 'true' ? 'featureInactive' : '',
    r.unavailablePreview === 'true' ? 'unavailablePreview' : '',
    r.electronicArchive === 'true' ? 'electronicArchive' : '',
  ].filter(Boolean).join(',');

  md.push(
    `| ${i + 1} | \`${r.routeName}\` | \`${r.routePath}\` | \`${r.portal || ''}\` | \`${r.requiresAuth || ''}\` | \`${r.guest || ''}\` | \`${r.requiresPlatformAdmin || ''}\` | \`${r.requiresManager || ''}\` | \`${r.requiresOwner || ''}\` | \`${r.requiresPermission || ''}\` | \`${r.anyPermissions.join(',')}\` | \`${r.allPermissions.join(',')}\` | \`${r.requiresBusinessFeature || ''}\` | \`${featureStates}\` | \`${r.file}\` |`
  );
});

fs.writeFileSync(path.join(docsDir, 'A4_مصفوفة_صلاحيات_الشاشات.md'), md.join('\n') + '\n', 'utf8');
console.log(`Generated A4 with ${rows.length} route permission rows.`);
