const fs = require('fs');
const path = require('path');

const root = 'C:/Users/nawaf/.verdent/verdent-projects/new-project-3';
const docsDir = path.join(root, 'docs/system-master-guide-ar');
const routerPath = path.join(root, 'frontend/src/router/index.ts');
const routerSource = fs.readFileSync(routerPath, 'utf8');

function stripHtml(input) {
  return input
    .replace(/<[^>]+>/g, ' ')
    .replace(/\{\{[^}]+\}\}/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function uniq(values) {
  return [...new Set(values.filter(Boolean))];
}

const routes = [];
const chunks = routerSource.split(/(?=\{\s*path:\s*')/g);
for (const chunk of chunks) {
  const pathMatch = chunk.match(/path:\s*'([^']+)'/);
  const nameMatch = chunk.match(/name:\s*'([^']+)'/);
  const compMatch = chunk.match(/component:\s*\(\)\s*=>\s*import\('([^']+)'\)/);
  if (!pathMatch || !nameMatch || !compMatch) continue;
  routes.push({
    path: pathMatch[1],
    name: nameMatch[1],
    componentImport: compMatch[1],
  });
}

const rows = [];
for (const route of routes) {
  const rel = route.componentImport.replace(/^@\//, 'frontend/src/');
  const abs = path.join(root, rel);
  if (!fs.existsSync(abs)) continue;

  const src = fs.readFileSync(abs, 'utf8');

  const headings = uniq(
    [...src.matchAll(/<h[1-3][^>]*>([\s\S]*?)<\/h[1-3]>/g)]
      .map((x) => stripHtml(x[1]))
      .filter((x) => x.length > 0)
      .slice(0, 4)
  );

  const buttonTexts = uniq(
    [...src.matchAll(/<button[^>]*>([\s\S]*?)<\/button>/g)]
      .map((x) => stripHtml(x[1]))
      .filter((x) => x.length > 0)
      .slice(0, 6)
  );

  const linkTexts = uniq(
    [...src.matchAll(/<RouterLink[^>]*>([\s\S]*?)<\/RouterLink>/g)]
      .map((x) => stripHtml(x[1]))
      .filter((x) => x.length > 0)
      .slice(0, 5)
  );

  const placeholders = uniq(
    [...src.matchAll(/placeholder=\"([^\"]+)\"/g)].map((x) => x[1]).slice(0, 8)
  );

  const tableHeaders = uniq(
    [...src.matchAll(/<th[^>]*>([\s\S]*?)<\/th>/g)]
      .map((x) => stripHtml(x[1]))
      .filter((x) => x.length > 0)
      .slice(0, 10)
  );

  rows.push({
    path: route.path,
    name: route.name,
    file: rel,
    headings,
    buttonTexts,
    linkTexts,
    placeholders,
    tableHeaders,
  });
}

const md = [];
md.push('# ملحق A3 - تفصيل الشاشات وعناصر الواجهة');
md.push('');
md.push('هذا الملحق مستخرج آليًا من `frontend/src/router/index.ts` وملفات `views` الفعلية.');
md.push('يستخدمه فريق التشغيل/الدعم لتحديد عناصر الشاشة بسرعة دون الرجوع للمطور.');
md.push('');
md.push(`إجمالي الشاشات المفهرسة: **${rows.length}**`);
md.push('');

for (const row of rows) {
  md.push(`## ${row.name} — \`${row.path}\``);
  md.push(`- الملف: \`${row.file}\``);
  md.push(`- العناوين: ${row.headings.length ? row.headings.map((x) => `\`${x}\``).join('، ') : '—'}`);
  md.push(`- الأزرار: ${row.buttonTexts.length ? row.buttonTexts.map((x) => `\`${x}\``).join('، ') : '—'}`);
  md.push(`- روابط التنقل داخل الشاشة: ${row.linkTexts.length ? row.linkTexts.map((x) => `\`${x}\``).join('، ') : '—'}`);
  md.push(`- حقول الإدخال (placeholder): ${row.placeholders.length ? row.placeholders.map((x) => `\`${x}\``).join('، ') : '—'}`);
  md.push(`- أعمدة الجداول: ${row.tableHeaders.length ? row.tableHeaders.map((x) => `\`${x}\``).join('، ') : '—'}`);
  md.push('');
}

fs.writeFileSync(path.join(docsDir, 'A3_تفصيل_الشاشات_وعناصر_الواجهة.md'), md.join('\n') + '\n', 'utf8');
console.log(`Generated A3 with ${rows.length} route-screen records.`);
