import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

function inlineDiagramImages(html) {
  return html.replace(
    /<img\s+src="diagrams\/([^"]+)"([^>]*)>/g,
    (match, filename, rest) => {
      const abs = path.join(__dirname, 'diagrams', filename);
      if (!fs.existsSync(abs)) {
        console.warn('Missing diagram:', abs);
        return match;
      }
      const b64 = fs.readFileSync(abs).toString('base64');
      const mime = filename.toLowerCase().endsWith('.png') ? 'image/png' : 'image/jpeg';
      return `<img src="data:${mime};base64,${b64}"${rest}>`;
    }
  );
}

let body = fs.readFileSync(path.join(__dirname, 'System_Comprehensive_Report.body.html'), 'utf8');
body = inlineDiagramImages(body);
/** wkhtmltopdf chokes on relative .md links and on href="#"; unwrap to inner content for print/PDF */
body = body.replace(/<a href="\.\/[^"]+\.md">(.*?)<\/a>/gs, '$1');
const css = fs.readFileSync(path.join(__dirname, 'report-pdf.css'), 'utf8');
const html = `<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width">
<title>تقرير شامل — أسس برو</title>
<style>${css}</style>
</head>
<body>
${body}
</body>
</html>`;
fs.writeFileSync(path.join(__dirname, 'System_Comprehensive_Report.html'), html);
console.log('Wrote System_Comprehensive_Report.html');
