const fs = require('fs');
const path = require('path');

function escapePdfText(s) {
  return s.replace(/\\/g, '\\\\').replace(/\(/g, '\\(').replace(/\)/g, '\\)');
}

function wrapLines(text, maxChars = 95) {
  const out = [];
  const lines = text.replace(/\r\n/g, '\n').split('\n');
  for (const line of lines) {
    if (line.length <= maxChars) {
      out.push(line);
      continue;
    }
    let rest = line;
    while (rest.length > maxChars) {
      let cut = rest.lastIndexOf(' ', maxChars);
      if (cut < 20) cut = maxChars;
      out.push(rest.slice(0, cut));
      rest = rest.slice(cut).trimStart();
    }
    out.push(rest);
  }
  return out;
}

function buildPdf(lines) {
  const pageWidth = 595;
  const pageHeight = 842;
  const marginLeft = 40;
  const marginTop = 40;
  const fontSize = 10;
  const leading = 14;
  const linesPerPage = Math.floor((pageHeight - marginTop * 2) / leading);

  const pages = [];
  for (let i = 0; i < lines.length; i += linesPerPage) {
    pages.push(lines.slice(i, i + linesPerPage));
  }

  const objects = [];
  const addObj = (body) => {
    objects.push(body);
    return objects.length;
  };

  const fontObj = addObj('<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>');

  const pageObjs = [];
  for (const pageLines of pages) {
    let y = pageHeight - marginTop;
    const textOps = [];
    textOps.push('BT');
    textOps.push(`/F1 ${fontSize} Tf`);
    for (const line of pageLines) {
      const safe = escapePdfText(line);
      textOps.push(`1 0 0 1 ${marginLeft} ${y} Tm (${safe}) Tj`);
      y -= leading;
    }
    textOps.push('ET');
    const stream = textOps.join('\n');
    const contentObj = addObj(`<< /Length ${Buffer.byteLength(stream, 'utf8')} >>\nstream\n${stream}\nendstream`);
    pageObjs.push({ contentObj, objNum: 0 });
  }

  const pagesObjPlaceholder = addObj('');
  for (const p of pageObjs) {
    p.objNum = addObj(
      `<< /Type /Page /Parent ${pagesObjPlaceholder} 0 R /MediaBox [0 0 ${pageWidth} ${pageHeight}] /Resources << /Font << /F1 ${fontObj} 0 R >> >> /Contents ${p.contentObj} 0 R >>`
    );
  }

  const kids = pageObjs.map((p) => `${p.objNum} 0 R`).join(' ');
  objects[pagesObjPlaceholder - 1] = `<< /Type /Pages /Count ${pageObjs.length} /Kids [ ${kids} ] >>`;

  const catalogObj = addObj(`<< /Type /Catalog /Pages ${pagesObjPlaceholder} 0 R >>`);

  let pdf = '%PDF-1.4\n';
  const offsets = [0];
  for (let i = 0; i < objects.length; i++) {
    offsets.push(Buffer.byteLength(pdf, 'utf8'));
    pdf += `${i + 1} 0 obj\n${objects[i]}\nendobj\n`;
  }

  const xrefPos = Buffer.byteLength(pdf, 'utf8');
  pdf += `xref\n0 ${objects.length + 1}\n`;
  pdf += '0000000000 65535 f \n';
  for (let i = 1; i < offsets.length; i++) {
    pdf += `${String(offsets[i]).padStart(10, '0')} 00000 n \n`;
  }
  pdf += `trailer\n<< /Size ${objects.length + 1} /Root ${catalogObj} 0 R >>\nstartxref\n${xrefPos}\n%%EOF\n`;
  return pdf;
}

function main() {
  const input = process.argv[2];
  const output = process.argv[3];
  if (!input || !output) {
    console.error('Usage: node scripts/md-to-simple-pdf.js <input.md> <output.pdf>');
    process.exit(1);
  }
  const inPath = path.resolve(input);
  const outPath = path.resolve(output);
  const text = fs.readFileSync(inPath, 'utf8');
  const lines = wrapLines(text, 95);
  const pdf = buildPdf(lines);
  fs.writeFileSync(outPath, pdf, 'binary');
  console.log(`PDF_CREATED=${outPath}`);
}

main();

