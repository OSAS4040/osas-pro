const fs = require('fs');
const path = require('path');
const { marked } = require('../frontend/node_modules/marked');
const { chromium } = require('../frontend/node_modules/playwright');

const root = 'C:/Users/nawaf/.verdent/verdent-projects/new-project-3';
const docsDir = path.join(root, 'docs/system-master-guide-ar');

function sortDocs(a, b) {
  const rank = (name) => {
    if (/^\d+_/.test(name)) return [0, Number(name.split('_')[0]), name];
    if (/^A\d+_/.test(name)) return [1, Number(name.slice(1).split('_')[0]), name];
    if (/^99_/.test(name)) return [2, 99, name];
    return [3, 999, name];
  };
  const ra = rank(a);
  const rb = rank(b);
  if (ra[0] !== rb[0]) return ra[0] - rb[0];
  if (ra[1] !== rb[1]) return ra[1] - rb[1];
  return ra[2].localeCompare(rb[2], 'ar');
}

async function run() {
  const files = fs
    .readdirSync(docsDir)
    .filter((f) => f.toLowerCase().endsWith('.md'))
    .sort(sortDocs);

  const mergedMdParts = [];
  for (const file of files) {
    const content = fs.readFileSync(path.join(docsDir, file), 'utf8');
    mergedMdParts.push(`\n\n---\n\n# ${file}\n\n${content}\n`);
  }
  const mergedMd = mergedMdParts.join('\n');
  const mergedMdPath = path.join(docsDir, 'system-master-guide-ar.merged.md');
  fs.writeFileSync(mergedMdPath, mergedMd, 'utf8');

  const htmlBody = marked.parse(mergedMd);
  const html = `<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8" />
  <title>الدليل المرجعي العربي الشامل للنظام</title>
  <style>
    body { font-family: "Segoe UI", Tahoma, Arial, sans-serif; margin: 24px; line-height: 1.7; color: #111; }
    h1, h2, h3 { color: #0f172a; page-break-after: avoid; }
    h1 { border-bottom: 1px solid #ddd; padding-bottom: 6px; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; font-size: 12px; }
    th, td { border: 1px solid #cbd5e1; padding: 6px; vertical-align: top; text-align: right; }
    code { background: #f1f5f9; padding: 1px 4px; border-radius: 4px; font-family: Consolas, monospace; direction: ltr; unicode-bidi: bidi-override; }
    pre { background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; overflow: auto; direction: ltr; }
    hr { border: 0; border-top: 2px dashed #cbd5e1; margin: 30px 0; }
    @page { size: A4; margin: 14mm; }
  </style>
</head>
<body>
${htmlBody}
</body>
</html>`;

  const htmlPath = path.join(docsDir, 'system-master-guide-ar.print.html');
  fs.writeFileSync(htmlPath, html, 'utf8');

  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.setContent(html, { waitUntil: 'networkidle' });
  const pdfPath = path.join(docsDir, 'system-master-guide-ar.pdf');
  await page.pdf({
    path: pdfPath,
    format: 'A4',
    printBackground: true,
    margin: { top: '12mm', right: '12mm', bottom: '12mm', left: '12mm' },
  });
  await browser.close();

  console.log(`Exported:\n- ${mergedMdPath}\n- ${htmlPath}\n- ${pdfPath}`);
}

run().catch((err) => {
  console.error(err);
  process.exit(1);
});
