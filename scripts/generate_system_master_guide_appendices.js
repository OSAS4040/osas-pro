const fs = require('fs');
const path = require('path');

const root = 'C:/Users/nawaf/.verdent/verdent-projects/new-project-3';
const docs = path.join(root, 'docs/system-master-guide-ar');

const api = fs.readFileSync(path.join(root, 'backend/routes/api.php'), 'utf8');
const fe = fs.readFileSync(path.join(root, 'frontend/src/router/index.ts'), 'utf8');

const apiRe = /Route::(get|post|put|patch|delete)\(\s*'([^']+)'/g;
const apiResourceRe = /Route::apiResource\(\s*'([^']+)'/g;
const feRe = /path:\s*'([^']+)'(?:,\s*name:\s*'([^']+)')?/g;

const apiRows = [];
let m;
while ((m = apiRe.exec(api))) {
  apiRows.push({ method: m[1].toUpperCase(), path: m[2] });
}

while ((m = apiResourceRe.exec(api))) {
  apiRows.push({ method: 'APIRESOURCE', path: m[1] });
}

const feRows = [];
while ((m = feRe.exec(fe))) {
  feRows.push({ path: m[1], name: m[2] || '' });
}

const apiMd = [
  '# ملحق A1 - جرد مسارات API الفعلية',
  '',
  'مستخرج آليًا من `backend/routes/api.php`.',
  '',
  '| # | method | path |',
  '|---|---|---|',
];
apiRows.forEach((r, i) => {
  apiMd.push(`| ${i + 1} | \`${r.method}\` | \`${r.path}\` |`);
});

const feMd = [
  '# ملحق A2 - جرد مسارات الواجهة الفعلية',
  '',
  'مستخرج آليًا من `frontend/src/router/index.ts`.',
  '',
  '| # | path | name |',
  '|---|---|---|',
];
feRows.forEach((r, i) => {
  feMd.push(`| ${i + 1} | \`${r.path}\` | \`${r.name}\` |`);
});

const indexMd = `# الدليل المرجعي العربي الشامل للنظام

مرجع رسمي شامل مبني على قراءة الكود الفعلي في الواجهة والخلفية والتشغيل.

## محتويات الحزمة
- [01_نظرة_عامة_على_النظام.md](./01_نظرة_عامة_على_النظام.md)
- [02_الأدوار_والصلاحيات.md](./02_الأدوار_والصلاحيات.md)
- [03_رحلات_العمل_الكاملة.md](./03_رحلات_العمل_الكاملة.md)
- [04_فهرس_الشاشات_والمسارات.md](./04_فهرس_الشاشات_والمسارات.md)
- [05_نماذج_العمل_والعلاقات.md](./05_نماذج_العمل_والعلاقات.md)
- [06_الخصائص_والمزايا_الكاملة.md](./06_الخصائص_والمزايا_الكاملة.md)
- [07_المنظومة_المالية_والمحاسبية.md](./07_المنظومة_المالية_والمحاسبية.md)
- [08_المنظومة_التشغيلية_والإدارية.md](./08_المنظومة_التشغيلية_والإدارية.md)
- [09_التقنيات_والبنية_المعمارية_والتكاملات.md](./09_التقنيات_والبنية_المعمارية_والتكاملات.md)
- [10_الذكاء_الاصطناعي_والتحليلات_والتوصيات.md](./10_الذكاء_الاصطناعي_والتحليلات_والتوصيات.md)
- [11_الإشعارات_والتنبيهات_والاعتمادات.md](./11_الإشعارات_والتنبيهات_والاعتمادات.md)
- [12_سيناريوهات_النظام_الكاملة.md](./12_سيناريوهات_النظام_الكاملة.md)
- [13_السياسات_والضوابط_الحاكمة.md](./13_السياسات_والضوابط_الحاكمة.md)
- [14_الاستثناءات_والحالات_الخاصة.md](./14_الاستثناءات_والحالات_الخاصة.md)
- [15_مصفوفة_القرارات_والإجراءات.md](./15_مصفوفة_القرارات_والإجراءات.md)
- [16_دليل_الدعم_الفني_وفهم_الحالات.md](./16_دليل_الدعم_الفني_وفهم_الحالات.md)
- [17_دليل_النشر_والتشغيل_المرجعي.md](./17_دليل_النشر_والتشغيل_المرجعي.md)
- [18_المصطلحات_والمفاهيم.md](./18_المصطلحات_والمفاهيم.md)

## ملاحق الجرد الفعلي
- [A1_جرد_مسارات_API.md](./A1_جرد_مسارات_API.md)
- [A2_جرد_مسارات_الواجهة.md](./A2_جرد_مسارات_الواجهة.md)

## مخطط مرجعي

\`\`\`mermaid
graph TD
A[دخول] --> B{بوابة}
B --> C[Staff]
B --> D[Platform]
B --> E[Fleet]
B --> F[Customer]
C --> G[تشغيل/مال/محاسبة]
D --> H[إدارة منصة/ذكاء/دعم]
\`\`\`
`;

fs.writeFileSync(path.join(docs, 'A1_جرد_مسارات_API.md'), apiMd.join('\n') + '\n', 'utf8');
fs.writeFileSync(path.join(docs, 'A2_جرد_مسارات_الواجهة.md'), feMd.join('\n') + '\n', 'utf8');
fs.writeFileSync(path.join(docs, '00_الفهرس_العام.md'), indexMd, 'utf8');

console.log(`Generated appendices: API=${apiRows.length}, FE=${feRows.length}`);
