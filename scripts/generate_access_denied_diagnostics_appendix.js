const fs = require('fs');
const path = require('path');

const root = 'C:/Users/nawaf/.verdent/verdent-projects/new-project-3';
const docsDir = path.join(root, 'docs/system-master-guide-ar');
const routerPath = path.join(root, 'frontend/src/router/index.ts');
const deniedViewPath = path.join(root, 'frontend/src/views/auth/AccessDeniedView.vue');

const routerSrc = fs.readFileSync(routerPath, 'utf8');
const deniedSrc = fs.readFileSync(deniedViewPath, 'utf8');

const denyReasons = ['manager', 'owner', 'permission', 'feature', 'portal', 'preview', 'inactive'];

function extractConditionBlock(reason) {
  const re = new RegExp(`deny\\('${reason}'\\)`, 'g');
  const indices = [];
  let m;
  while ((m = re.exec(routerSrc))) indices.push(m.index);
  return indices.map((idx) => {
    const start = Math.max(0, idx - 220);
    const end = Math.min(routerSrc.length, idx + 140);
    return routerSrc.slice(start, end).replace(/\s+/g, ' ').trim();
  });
}

function extractUiMessage(reason) {
  const headingRe = new RegExp(`${reason}:\\s*'([^']+)'`, 'g');
  const matches = [];
  let m;
  while ((m = headingRe.exec(deniedSrc))) matches.push(m[1]);
  return [...new Set(matches)];
}

const rows = denyReasons.map((reason) => {
  const conditions = extractConditionBlock(reason);
  const uiMessages = extractUiMessage(reason);
  return { reason, conditions, uiMessages };
});

const md = [];
md.push('# ملحق A6 - تشخيص حالات المنع Access Denied');
md.push('');
md.push('مستخرج من:');
md.push('- `frontend/src/router/index.ts`');
md.push('- `frontend/src/views/auth/AccessDeniedView.vue`');
md.push('');
md.push('## أسباب المنع المعتمدة');
md.push('');
md.push('| reason | متى يظهر (من الحارس) | نص الواجهة (عربي) | خطوة دعم أولية |');
md.push('|---|---|---|---|');

const supportStep = {
  manager: 'تحقق من `auth.isManager` ودور المستخدم الحالي.',
  owner: 'تحقق من `auth.isOwner` أو دور منصة مكافئ.',
  permission: 'راجع `requiresPermission`/`requiresAnyPermission`/`requiresAllPermissions` و`auth.hasPermission`.',
  feature: 'راجع `featureFlags` و`businessProfile.isEnabled(...)` لمسار الميزة.',
  portal: 'راجع `enabledPortals` (admin/fleet/customer) في إعداد البناء.',
  preview: 'المسار موسوم `unavailablePreview` وغير متاح بالإصدار الحالي.',
  inactive: 'المسار موسوم `featureInactive` أو تم تعطيله وظيفيًا.',
};

rows.forEach((r) => {
  const whenText = r.conditions.length
    ? r.conditions.map((x) => x.slice(0, 180)).join(' <br/> ')
    : '—';
  const uiText = r.uiMessages.length ? r.uiMessages.filter((x) => /[\u0600-\u06FF]/.test(x)).join(' / ') : '—';
  md.push(`| \`${r.reason}\` | ${whenText} | ${uiText || '—'} | ${supportStep[r.reason]} |`);
});

md.push('');
md.push('## خريطة قرار تشخيص سريعة');
md.push('');
md.push('```mermaid');
md.push('flowchart TD');
md.push('A[وصول المستخدم لشاشة غير متاحة] --> B{هل تم التحويل إلى /access-denied؟}');
md.push('B -->|لا| C[تحقق من إعادة توجيه بوابة الدور portal routing]');
md.push('B -->|نعم| D[اقرأ reason من query]');
md.push('D --> E{reason}');
md.push("E -->|permission| P[افحص صلاحيات الدور في auth + route meta]");
md.push("E -->|manager/owner| R[افحص مستوى الدور]");
md.push("E -->|feature/preview/inactive| F[افحص feature flags + feature profile]");
md.push("E -->|portal| G[افحص enabledPortals]");
md.push('P --> Z[قرار دعم: منح صلاحية أو توجيه صحيح]');
md.push('R --> Z');
md.push('F --> Z');
md.push('G --> Z');
md.push('```');
md.push('');
md.push('## قائمة تحقق الدعم الفني');
md.push('- جمع `from` و`reason` من query في شاشة المنع.');
md.push('- التحقق من role context (staff/platform/fleet/customer).');
md.push('- مطابقة route meta في الراوتر مع صلاحيات المستخدم الفعلية.');
md.push('- التحقق من `enabledPortals` و`featureFlags` و`business profile`.');
md.push('- عند الاشتباه بخطأ صلاحيات خلفي: الربط مع `A5_ربط_الشاشات_API_الخلفية.md`.');

fs.writeFileSync(path.join(docsDir, 'A6_تشخيص_حالات_المنع_Access_Denied.md'), md.join('\n') + '\n', 'utf8');
console.log(`Generated A6 for ${rows.length} deny reasons.`);
