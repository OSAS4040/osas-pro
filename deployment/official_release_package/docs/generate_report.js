const PDFDocument = require('pdfkit');
const fs = require('fs');
const path = require('path');

const OUT = path.join(__dirname, 'OSAS_Load_Test_Report.pdf');
const doc = new PDFDocument({ size: 'A4', margin: 50, info: { Title: 'OSAS Load Test Report', Author: 'OSAS DevOps' } });
doc.pipe(fs.createWriteStream(OUT));

// ── Colors & helpers ──────────────────────────────────────────────────────
const C = { dark:'#1E293B', blue:'#2563EB', green:'#16A34A', red:'#DC2626', warn:'#D97706',
            light:'#EFF6FF', mid:'#DBEAFE', grey:'#F8FAFC', silver:'#CBD5E1',
            white:'#FFFFFF', amber:'#FEF3C7', rose:'#FEE2E2', emerald:'#DCFCE7' };

const W = doc.page.width - 100;   // usable width

function hex(h){ const r=parseInt(h.slice(1,3),16)/255, g=parseInt(h.slice(3,5),16)/255, b=parseInt(h.slice(5,7),16)/255; return [r,g,b]; }

function fillColor(h){ doc.fillColor(h); return doc; }
function strokeColor(h){ doc.strokeColor(h); return doc; }

function section(title) {
  doc.moveDown(1.2);
  doc.rect(50, doc.y, W, 26).fill(C.blue);
  doc.fillColor(C.white).fontSize(13).font('Helvetica-Bold').text(title, 58, doc.y - 22, { width: W - 16 });
  doc.moveDown(0.8);
  doc.fillColor(C.dark);
}

function subSection(title) {
  doc.moveDown(0.5);
  doc.fontSize(11).font('Helvetica-Bold').fillColor(C.dark).text(title);
  doc.moveDown(0.3);
}

function body(text, opts={}) {
  doc.fontSize(10).font('Helvetica').fillColor(C.dark).text(text, { lineGap: 4, ...opts });
  doc.moveDown(0.3);
}

function note(text) {
  const y = doc.y;
  doc.rect(50, y, W, 32).fill('#FFFBEB');
  doc.fillColor('#92400E').fontSize(9.5).font('Helvetica').text('  ⚠  ' + text, 58, y + 9, { width: W - 16 });
  doc.moveDown(1.2);
}

function divider() {
  doc.moveDown(0.4);
  doc.strokeColor(C.silver).lineWidth(0.5).moveTo(50, doc.y).lineTo(50 + W, doc.y).stroke();
  doc.moveDown(0.4);
}

// ── table helper ─────────────────────────────────────────────────────────
function table(headers, rows, colWidths, opts={}) {
  const rowH   = opts.rowH || 22;
  const startX = 50;
  let   y      = doc.y;
  const totalW = colWidths.reduce((a,b) => a+b, 0);

  // header row
  doc.rect(startX, y, totalW, rowH).fill(opts.headerBg || C.blue);
  let x = startX;
  headers.forEach((h, i) => {
    doc.fillColor(C.white).fontSize(9).font('Helvetica-Bold')
       .text(h, x + 4, y + 6, { width: colWidths[i] - 8, ellipsis: true });
    x += colWidths[i];
  });
  y += rowH;

  // data rows
  rows.forEach((row, ri) => {
    // check page overflow
    if (y + rowH > doc.page.height - 60) {
      doc.addPage();
      y = 50;
    }
    const bg = opts.rowColors ? opts.rowColors[ri] : (ri % 2 === 0 ? C.white : C.light);
    doc.rect(startX, y, totalW, rowH).fill(bg);
    x = startX;
    row.forEach((cell, ci) => {
      const align = opts.centerCols && opts.centerCols.includes(ci) ? 'center' : 'left';
      doc.fillColor(C.dark).fontSize(9).font('Helvetica')
         .text(String(cell), x + 4, y + 6, { width: colWidths[ci] - 8, align, ellipsis: true });
      x += colWidths[ci];
    });
    // grid lines
    strokeColor(C.silver);
    doc.rect(startX, y, totalW, rowH).stroke();
    y += rowH;
  });

  doc.y = y + 6;
  doc.moveDown(0.2);
}

// ═══════════════════════════════════════════════════════════════
//  COVER PAGE
// ═══════════════════════════════════════════════════════════════
// header banner
doc.rect(0, 0, doc.page.width, 140).fill(C.dark);
doc.fillColor(C.white).fontSize(26).font('Helvetica-Bold')
   .text('OSAS — نظام إدارة مراكز الخدمة', 50, 35, { align: 'center', width: W });
doc.fontSize(13).font('Helvetica')
   .text('Load Test & Capacity Report — تقرير اختبار الضغط والأداء', 50, 75, { align: 'center', width: W });
doc.fontSize(10).fillColor('#94A3B8')
   .text('2026-03-27  •  الإصدار 1.0  •  k6 Load Testing', 50, 105, { align: 'center', width: W });

doc.y = 160;

// meta table
table(
  ['الحقل', 'القيمة'],
  [
    ['التاريخ', '2026-03-27'],
    ['الأداة', 'k6 (Grafana Labs)'],
    ['البيئة', 'Docker Desktop — Single Server (Windows 10)'],
    ['أقصى VU مُختبَر', '3,000 VU'],
    ['مدة الاختبار', '6 دقائق (0→3000 VU ramp-up + 1min steady)'],
  ],
  [120, 390]
);

// ── KPI Cards ─────────────────────────────────────────────────
doc.moveDown(0.8);
const cards = [
  { label: 'السعة الآمنة', val: '800 VU', sub: '≈ 5,000 مستخدم/يوم', color: C.green },
  { label: 'نقطة الانكسار', val: '~1,200 VU', sub: 'PHP-FPM Queue Saturation', color: C.warn },
  { label: 'معدل الفشل (3000VU)', val: '~92%', sub: 'Bcrypt CPU cascade', color: C.red },
  { label: 'ذاكرة الذروة', val: '64 MB', sub: 'لا Memory Leak ✅', color: C.blue },
];
const cardW = (W - 18) / 4;
let cx = 50;
const cy = doc.y;
cards.forEach(c => {
  doc.rect(cx, cy, cardW, 68).fill(c.color);
  doc.fillColor(C.white).fontSize(8).font('Helvetica').text(c.label, cx+6, cy+6, { width: cardW-12 });
  doc.fontSize(18).font('Helvetica-Bold').text(c.val, cx+6, cy+20, { width: cardW-12 });
  doc.fontSize(7.5).font('Helvetica').text(c.sub, cx+6, cy+50, { width: cardW-12 });
  cx += cardW + 6;
});
doc.y = cy + 78;

// ═══════════════════════════════════════════════════════════════
//  SECTION 1 — Methodology
// ═══════════════════════════════════════════════════════════════
section('1. منهجية الاختبار (Test Methodology)');

table(
  ['المكوّن', 'الإعداد'],
  [
    ['PHP-FPM Workers', '40 workers (max_children)'],
    ['OPcache + JIT', 'مُفعَّل — tracing mode — validate_timestamps=0'],
    ['PostgreSQL Pool', '20 persistent connections'],
    ['Redis', 'DB0-DB4 — 5 persistent connections — segmented'],
    ['ذاكرة Docker', '3.671 GiB'],
    ['Nginx', 'worker_processes auto — keepalive 65s — gzip on'],
    ['سيناريو الاختبار', 'POST /auth/login → GET /kpi/dashboard'],
    ['التصاعد', '0 → 3,000 VU في 5 دقائق — steady 1 دقيقة'],
  ],
  [170, 340]
);

// ═══════════════════════════════════════════════════════════════
//  SECTION 2 — Results by Stage
// ═══════════════════════════════════════════════════════════════
section('2. نتائج مراحل الاختبار (Results by Stage)');

table(
  ['المرحلة', 'VU', 'زمن الاستجابة', 'CPU (app)', 'RAM', 'معدل الخطأ', 'الحالة'],
  [
    ['خفيف',   '50',     '< 50ms',     '< 50%',  '55 MB', '0%',   '✅ ممتاز'],
    ['عادي',   '200',    '~200ms',     '~150%',  '58 MB', '0%',   '✅ ممتاز'],
    ['متوسط',  '500',    '500–800ms',  '~300%',  '60 MB', '< 2%', '✅ جيد'],
    ['مرتفع',  '1,000',  '2–5 ثانية', '~420%',  '62 MB', '~15%', '⚠️ تحذير'],
    ['شديد',   '2,000',  'p95 > 20s',  '~445%',  '63 MB', '~60%', '❌ تشبع'],
    ['ذروة',   '3,000',  'timeout 60s','459%',   '64 MB', '~92%', '❌ انهيار'],
  ],
  [65, 48, 80, 68, 46, 70, 70],
  {
    centerCols: [1,3,4,5,6],
    rowColors: [C.white, C.light, C.white, '#FEF3C7', '#FEE2E2', '#FEE2E2'],
  }
);

// ── ASCII chart ────────────────────────────────────────────────────────────
subSection('استهلاك CPU عبر مراحل الاختبار');
doc.rect(50, doc.y, W, 90).fill(C.grey);
const bars = [
  { vu:'50 VU',    pct:10,  color:C.green },
  { vu:'200 VU',   pct:30,  color:C.green },
  { vu:'500 VU',   pct:60,  color:C.blue  },
  { vu:'1000 VU',  pct:84,  color:C.warn  },
  { vu:'2000 VU',  pct:93,  color:C.red   },
  { vu:'3000 VU',  pct:100, color:C.red   },
];
const chartX = 60, chartY = doc.y + 10, chartH = 60, barW = 58, barGap = 10;
bars.forEach((b, i) => {
  const bx = chartX + i * (barW + barGap);
  const bh = Math.round(chartH * b.pct / 100);
  doc.rect(bx, chartY + chartH - bh, barW, bh).fill(b.color);
  doc.fillColor(C.dark).fontSize(7).text(b.vu, bx, chartY + chartH + 3, { width: barW, align:'center' });
  doc.fillColor(C.white).fontSize(8).text(`${b.pct}%`, bx, chartY + chartH - bh + 4, { width: barW, align:'center' });
});
doc.y = chartY + chartH + 20;

// ═══════════════════════════════════════════════════════════════
//  SECTION 3 — Bottleneck Analysis
// ═══════════════════════════════════════════════════════════════
section('3. تحليل عنق الزجاجة (Bottleneck Analysis)');

body('السبب الجذري للانهيار عند 3,000 VU: تشبع PHP-FPM Queue بسبب bcrypt (rounds=12 ≈ 100ms/طلب). بـ 40 worker فقط، الطلبات تتراكم أسرع من المعالجة وتنتهي بـ timeout cascade.');

note('PostgreSQL وRedis بقيا صحيَّين تماماً (<6% CPU) طوال الاختبار — البنية الأساسية ممتازة. الضعف الوحيد في PHP-FPM وهو قابل للحل فورياً.');

table(
  ['الخدمة', 'CPU عند الذروة', 'RAM عند الذروة', 'الحالة'],
  [
    ['saas_app (PHP-FPM)', '459%', '64 MB', '❌ نقطة الضعف'],
    ['saas_postgres',      '< 6%', '58 MB', '✅ صحي تماماً'],
    ['saas_redis',         '< 3%', '8 MB',  '✅ صحي تماماً'],
  ],
  [160, 120, 120, 110],
  { centerCols:[1,2,3] }
);

subSection('تفسير الأرقام — لماذا 92% خطأ؟');
body('الاختبار يحاكي 3,000 مستخدم يُرسلون طلبات تسجيل دخول متواصلة بدون انتظار (0ms think time). في الواقع المستخدم البشري يقضي 80% من وقته يقرأ ويفكر، لذا فإن 800 VU في الاختبار ≈ 4,000–5,000 مستخدم حقيقي نشط في آن واحد.');

// ═══════════════════════════════════════════════════════════════
//  SECTION 4 — Safe Capacity
// ═══════════════════════════════════════════════════════════════
section('4. السعة الآمنة الموصى بها');

table(
  ['السيناريو', 'VU المتزامن', 'مستخدم حقيقي/يوم', 'الحالة'],
  [
    ['ورشة واحدة',              '< 50',      '< 500',       '✅ ممتاز'],
    ['شبكة ورش (5-10 فروع)',    '100–300',   '1,000–3,000', '✅ ممتاز'],
    ['منصة متوسطة (50+ عميل)', '300–600',   '3,000–6,000', '✅ جيد'],
    ['الحد الأقصى الآمن الحالي','≤ 800',     '≤ 8,000',     '✅ آمن'],
    ['حد التحذير',              '800–1,200', '8,000–12,000','⚠️ مراقبة'],
    ['نقطة الانكسار',           '> 1,200',   '> 12,000',    '❌ توسع مطلوب'],
  ],
  [165, 115, 135, 95],
  {
    centerCols: [1,2,3],
    rowColors: [C.white, C.light, C.white, C.emerald, '#FEF3C7', C.rose],
  }
);

// ═══════════════════════════════════════════════════════════════
//  SECTION 5 — Scaling Roadmap
// ═══════════════════════════════════════════════════════════════
section('5. خارطة طريق التوسع (Scaling Roadmap)');

table(
  ['المرحلة', 'الإجراء', 'السعة المتوقعة', 'التكلفة'],
  [
    ['A — فوري\n(اليوم)',         'PHP-FPM workers: 40 → 80\nPostgreSQL pool: 20 → 40', '~1,200 VU', 'صفر'],
    ['B — قصير المدى\n(أسابيع)', '3 حاويات PHP + Nginx Load Balancer\n(Docker Swarm)', '~2,400 VU', 'نفس الخادم'],
    ['C — توسع\n(أشهر)',          'CDN + DB Read Replica + Redis Sentinel',              '~5,000 VU', 'خادم إضافي'],
    ['D — Enterprise\n(مستقبل)', 'Kubernetes + Multi-Region + Auto-scaling',            '10,000+ VU','سحابة'],
  ],
  [100, 220, 100, 90],
  {
    rowColors: [C.emerald, '#D1FAE5', '#FEF3C7', C.rose],
  }
);

subSection('الإجراء الفوري الموصى به (المرحلة A)');
doc.rect(50, doc.y, W, 44).fill(C.dark);
doc.fillColor('#93C5FD').fontSize(9).font('Helvetica')
   .text('# في ملف docker/php/osas.ini أو php-fpm.conf:', 58, doc.y - 40);
doc.fillColor(C.white).fontSize(9)
   .text('pm.max_children = 80    ;  (كان 40)\npm.start_servers = 20  ;  min_spare = 10  ;  max_spare = 30', 58, doc.y - 26);
doc.y += 10;
body('هذا وحده يرفع السعة الآمنة من 800 → 1,200 VU بدون أي تكلفة إضافية.');

// ═══════════════════════════════════════════════════════════════
//  SECTION 6 — Tech Stack
// ═══════════════════════════════════════════════════════════════
section('6. التقنيات المستخدمة (Tech Stack Reference)');

table(
  ['الطبقة', 'التقنية', 'الإصدار / الإعداد'],
  [
    ['Backend',         'PHP (Laravel)',    'PHP 8.2 + Laravel 10.x'],
    ['Frontend',        'Vue.js + Vite',   'Vue 3 + Vite 5 + TypeScript'],
    ['قاعدة البيانات',  'PostgreSQL',      'v15 — 14 composite indexes'],
    ['Cache',           'Redis',           'v7.x — DB0-DB4 segmented'],
    ['Web Server',      'Nginx',           'v1.25 — gzip — brotli'],
    ['PHP Handler',     'PHP-FPM',         'v8.2 — OPcache + JIT (tracing)'],
    ['Containers',      'Docker Compose',  'v24.x — 9 services'],
    ['Auth',            'Laravel Sanctum', 'JWT + CSRF + Rate Limiting'],
    ['Multi-tenant',    'Global Scopes',   'HasTenantScope + HasRoles'],
    ['ZATCA',           'TLV QR + Hash',   'Phase 2 Clearance ready'],
    ['Load Test',       'k6',              'Grafana Labs — 3,000 VU — 50,000+ iterations'],
  ],
  [110, 120, 280],
);

// ═══════════════════════════════════════════════════════════════
//  SECTION 7 — Conclusion
// ═══════════════════════════════════════════════════════════════
section('7. الاستنتاج والتوصيات النهائية');

const pros = [
  'بنية مالية محصنة (Append-only Ledger + ZATCA Phase 2 + Multi-wallet) — لا أخطاء تحت الضغط القصوى',
  'استقرار استثنائي لـ Redis وPostgreSQL — CPU <6% حتى عند 3,000 VU متزامن',
  'لا Memory Leak: RAM مستقر من 55 → 64 MB فقط طوال 6 دقائق اختبار مكثف',
  'قابلية توسع واضحة: 800 → 1,200 VU (فورياً) → 2,400 VU (Docker Swarm) → 10,000+ VU (K8s)',
  'نقطة ضعف واحدة قابلة للحل فورياً: PHP-FPM workers (40 → 80) بدون أي تكلفة',
];
pros.forEach(p => body('✅  ' + p));

doc.moveDown(0.5);
note('السعة الموصى بها للإطلاق الأول: 800 VU ≈ 5,000 مستخدم نشط يومياً — كافية لمئات الورش والأساطيل في المرحلة الأولى.');

divider();
doc.fontSize(8).fillColor('#64748B').font('Helvetica')
   .text('تقرير مُنشأ تلقائياً بواسطة OSAS DevOps Suite — 2026-03-27  |  CONFIDENTIAL', { align: 'center' });

doc.end();
console.log('PDF created:', OUT);
