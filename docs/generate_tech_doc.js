const {
  Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell,
  Header, Footer, AlignmentType, HeadingLevel, BorderStyle, WidthType,
  ShadingType, VerticalAlign, NumberOfPages, PageBreak,
  UnderlineType, TabStopPosition, TabStopType
} = require('docx');
const fs = require('fs');

// ── Helpers ───────────────────────────────────────────────────────────────
const h1 = (text) => new Paragraph({
  text, heading: HeadingLevel.HEADING_1,
  spacing: { before: 400, after: 200 },
  shading: { type: ShadingType.SOLID, color: '1e3a5f', fill: '1e3a5f' },
  run: { color: 'FFFFFF', bold: true, size: 28 }
});

const h2 = (text) => new Paragraph({
  text, heading: HeadingLevel.HEADING_2,
  spacing: { before: 300, after: 160 },
  border: { bottom: { style: BorderStyle.SINGLE, size: 6, color: '2563eb' } }
});

const h3 = (text) => new Paragraph({
  text, heading: HeadingLevel.HEADING_3,
  spacing: { before: 200, after: 100 }
});

const p = (text, opts = {}) => new Paragraph({
  children: [new TextRun({ text, size: 22, ...opts })],
  spacing: { after: 120 }
});

const bullet = (text, lvl = 0) => new Paragraph({
  text,
  bullet: { level: lvl },
  spacing: { after: 80 },
  run: { size: 21 }
});

const kv = (key, value) => new Paragraph({
  children: [
    new TextRun({ text: key + ': ', bold: true, size: 22 }),
    new TextRun({ text: value, size: 22, color: '1e40af' })
  ],
  spacing: { after: 100 }
});

const makeTable = (headers, rows, widths) => new Table({
  width: { size: 100, type: WidthType.PERCENTAGE },
  rows: [
    new TableRow({
      tableHeader: true,
      children: headers.map((h, i) => new TableCell({
        width: widths ? { size: widths[i], type: WidthType.PERCENTAGE } : undefined,
        shading: { type: ShadingType.SOLID, color: '1e3a5f', fill: '1e3a5f' },
        verticalAlign: VerticalAlign.CENTER,
        children: [new Paragraph({
          children: [new TextRun({ text: h, bold: true, color: 'FFFFFF', size: 20 })],
          alignment: AlignmentType.CENTER
        })]
      }))
    }),
    ...rows.map((row, ri) => new TableRow({
      children: row.map((cell, ci) => new TableCell({
        shading: ri % 2 === 0
          ? { type: ShadingType.SOLID, color: 'EFF6FF', fill: 'EFF6FF' }
          : { type: ShadingType.SOLID, color: 'FFFFFF', fill: 'FFFFFF' },
        children: [new Paragraph({
          children: [new TextRun({ text: String(cell), size: 20 })],
          spacing: { after: 60 }
        })]
      }))
    }))
  ]
});

const divider = () => new Paragraph({
  border: { bottom: { style: BorderStyle.SINGLE, size: 4, color: 'CBD5E1' } },
  spacing: { before: 200, after: 200 }
});

// ────────────────────────────────────────────────────────────────────────────
const doc = new Document({
  creator: 'OSAS System',
  title: 'OSAS Technical Reference Documentation',
  description: 'Comprehensive technical documentation for the OSAS platform',
  styles: {
    default: {
      document: { run: { font: 'Calibri', size: 22 } }
    },
    paragraphStyles: [
      {
        id: 'Heading1', name: 'Heading 1',
        run: { bold: true, color: 'FFFFFF', size: 28, font: 'Calibri' },
        paragraph: {
          shading: { type: ShadingType.SOLID, fill: '1e3a5f' },
          spacing: { before: 400, after: 200 }
        }
      },
      {
        id: 'Heading2', name: 'Heading 2',
        run: { bold: true, color: '1e3a5f', size: 26, font: 'Calibri' },
        paragraph: { spacing: { before: 300, after: 160 } }
      },
      {
        id: 'Heading3', name: 'Heading 3',
        run: { bold: true, color: '374151', size: 24, font: 'Calibri' },
        paragraph: { spacing: { before: 200, after: 120 } }
      }
    ]
  },
  sections: [{
    properties: {
      page: {
        margin: { top: 1200, right: 900, bottom: 1200, left: 900 }
      }
    },
    headers: {
      default: new Header({
        children: [new Paragraph({
          children: [
            new TextRun({ text: 'OSAS — Technical Reference Documentation', color: '64748b', size: 18 }),
            new TextRun({ text: '   |   v1.0   |   2026-03-27', color: '94a3b8', size: 16 })
          ],
          alignment: AlignmentType.RIGHT,
          border: { bottom: { style: BorderStyle.SINGLE, size: 4, color: 'CBD5E1' } }
        })]
      })
    },
    footers: {
      default: new Footer({
        children: [new Paragraph({
          children: [
            new TextRun({ text: 'OSAS Platform — Confidential Technical Documentation', color: '94a3b8', size: 16 }),
            new TextRun({ text: '    Page ', size: 16, color: '64748b' }),
          ],
          alignment: AlignmentType.CENTER,
          border: { top: { style: BorderStyle.SINGLE, size: 4, color: 'CBD5E1' } }
        })]
      })
    },
    children: [

      // ══════════ COVER ══════════
      new Paragraph({
        children: [
          new TextRun({ text: 'OSAS', bold: true, size: 96, color: '1e3a5f', font: 'Calibri' }),
        ],
        alignment: AlignmentType.CENTER,
        spacing: { before: 1200, after: 200 }
      }),
      new Paragraph({
        children: [new TextRun({ text: 'Operations & Service Automation System', size: 40, color: '374151', font: 'Calibri' })],
        alignment: AlignmentType.CENTER,
        spacing: { after: 200 }
      }),
      new Paragraph({
        children: [new TextRun({ text: 'التوثيق التقني الشامل — Technical Reference Documentation', size: 28, color: '6b7280', font: 'Calibri' })],
        alignment: AlignmentType.CENTER,
        spacing: { after: 120 }
      }),
      new Paragraph({
        children: [new TextRun({ text: 'v1.0 — 27 مارس 2026', size: 24, color: '9ca3af' })],
        alignment: AlignmentType.CENTER,
        spacing: { after: 800 }
      }),
      makeTable(
        ['البيان', 'القيمة'],
        [
          ['إصدار النظام', 'v1.0.0'],
          ['تاريخ التوثيق', '2026-03-27'],
          ['مستوى السرية', 'سري — للاستخدام الداخلي'],
          ['المعدّ', 'فريق تطوير OSAS'],
          ['الغرض', 'مرجع تقني شامل للتقييم والتطوير']
        ],
        [30, 70]
      ),
      new Paragraph({ children: [new PageBreak()] }),

      // ══════════ 1. OVERVIEW ══════════
      new Paragraph({ text: '1. نظرة عامة على النظام', heading: HeadingLevel.HEADING_1, spacing: { before: 400, after: 200 } }),
      p('OSAS (Operations & Service Automation System) هو نظام SaaS متعدد المستأجرين (Multi-Tenant) مصمم لإدارة مراكز الخدمة، منافذ البيع، وأساطيل المركبات في المملكة العربية السعودية. يجمع بين نظام محاسبي متكامل، إدارة تشغيلية، ورشة ذكية، وإدارة موارد بشرية في منصة واحدة.'),
      divider(),
      makeTable(
        ['المحور', 'التفاصيل'],
        [
          ['نوع البنية', 'Monolithic API + SPA Frontend (قابل للتطور نحو Microservices)'],
          ['نمط التصميم', 'RESTful API مع Tenant Isolation عبر company_id'],
          ['عدد المسارات (Routes)', '257 مسار API نشط'],
          ['عدد جداول DB', '30+ جدول (294 index مُهيأ)'],
          ['عدد البوابات', '3 بوابات: الإدارة، منفذ البيع/مركز الخدمة، العميل'],
          ['دعم اللغات', 'عربي + إنجليزي (RTL/LTR)'],
          ['الامتثال الضريبي', 'ZATCA Phase 2 — QR + Hash']
        ],
        [30, 70]
      ),

      // ══════════ 2. CORE STACK ══════════
      new Paragraph({ text: '2. اللغات وأطر العمل (Core Stack)', heading: HeadingLevel.HEADING_1, spacing: { before: 400, after: 200 } }),

      new Paragraph({ text: '2.1 Backend', heading: HeadingLevel.HEADING_2, spacing: { before: 280, after: 160 } }),
      makeTable(
        ['المكون', 'الإصدار', 'الاستخدام'],
        [
          ['PHP', '8.3.30', 'لغة البرمجة الأساسية'],
          ['Laravel Framework', '^11.0', 'إطار العمل الرئيسي — MVC + Eloquent ORM'],
          ['Laravel Sanctum', '^4.0', 'مصادقة API عبر Tokens'],
          ['predis/predis', '^2.0', 'تعامل مع Redis (PHP client)'],
          ['phpredis extension', 'مُثبّت', 'Native Redis extension (أسرع من predis)'],
          ['guzzlehttp/guzzle', '^7.0', 'HTTP Client للتكاملات الخارجية'],
          ['darkaonline/l5-swagger', '^8.0', 'توثيق API تلقائي (Swagger/OpenAPI)'],
          ['sentry/sentry-laravel', '^4.0', 'مراقبة الأخطاء (Error Monitoring)'],
          ['aws/aws-sdk-php', '^3.0', 'تكامل خدمات AWS (S3, SES)'],
          ['phpunit/phpunit', '^11.0', 'اختبارات الوحدة (Unit Testing)'],
        ],
        [25, 20, 55]
      ),

      new Paragraph({ text: '2.2 Frontend', heading: HeadingLevel.HEADING_2, spacing: { before: 280, after: 160 } }),
      makeTable(
        ['المكون', 'الإصدار', 'الاستخدام'],
        [
          ['Vue.js', '^3.4.0', 'إطار واجهة المستخدم (Composition API)'],
          ['TypeScript', '^5.4.0', 'نظام الأنواع الثابتة'],
          ['Vite', '^5.2.0', 'Build Tool + Dev Server (HMR)'],
          ['Pinia', '^2.1.0', 'State Management'],
          ['Vue Router', '^4.3.0', 'SPA Routing مع Navigation Guards'],
          ['Tailwind CSS', '^3.4.0', 'Utility-First CSS Framework'],
          ['@headlessui/vue', '^1.7.0', 'مكونات UI بدون تصميم مسبق'],
          ['@heroicons/vue', '^2.1.0', 'أيقونات SVG'],
          ['axios', '^1.6.0', 'HTTP Client مع Interceptors'],
          ['jspdf + autotable', '^4.2.1 / ^5.0.7', 'توليد ملفات PDF في المتصفح'],
          ['xlsx', '^0.18.5', 'قراءة/كتابة ملفات Excel'],
          ['html2canvas', '^1.4.1', 'تحويل HTML إلى صورة'],
          ['@vueuse/core', '^10.9.0', 'مجموعة Composables جاهزة'],
          ['@sentry/vue', '^8.0.0', 'تتبع الأخطاء في Frontend'],
        ],
        [25, 20, 55]
      ),

      new Paragraph({ text: '2.3 بنية النظام (Architecture Pattern)', heading: HeadingLevel.HEADING_2, spacing: { before: 280, after: 160 } }),
      makeTable(
        ['الطبقة', 'التفاصيل'],
        [
          ['API Layer', 'RESTful JSON API — prefix: /api/v1 — 257 مسار'],
          ['Auth Middleware', 'auth:sanctum → tenant → branch.scope → subscription'],
          ['Tenant Isolation', 'TenantScopeMiddleware يحقن company_id + branch_id في كل طلب'],
          ['Queue Architecture', '4 workers مستقلة: high_priority, default, low_priority + scheduler'],
          ['Cache Strategy', 'Redis multi-database (DB0-4) مع persistent connections'],
          ['Permission System', 'Hybrid: Role-based (config) + Polymorphic permissions (DB)'],
          ['Event System', 'Laravel Events + Queued Listeners للعمليات المالية'],
          ['File Storage', 'Local + AWS S3 (via aws-sdk-php)'],
        ],
        [30, 70]
      ),

      // ══════════ 3. DATABASE & CACHE ══════════
      new Paragraph({ text: '3. قواعد البيانات والـ Cache', heading: HeadingLevel.HEADING_1, spacing: { before: 400, after: 200 } }),

      new Paragraph({ text: '3.1 PostgreSQL — قاعدة البيانات الرئيسية', heading: HeadingLevel.HEADING_2, spacing: { before: 280, after: 160 } }),
      makeTable(
        ['البيان', 'القيمة'],
        [
          ['الإصدار', 'PostgreSQL 16.13 (Alpine Linux 3.x, GCC 15.2.0, 64-bit)'],
          ['المحرك', 'pgsql (PDO_PGSQL)'],
          ['الاتصال المستمر', 'PDO::ATTR_PERSISTENT = true (إعادة استخدام الاتصالات)'],
          ['مهلة الاتصال', '5 ثوانٍ (PDO::ATTR_TIMEOUT)'],
          ['Prepared Statements', 'PDO::ATTR_EMULATE_PREPARES = false (native statements)'],
          ['SSL Mode', 'prefer (مرن — آمن عند التوفر)'],
          ['عدد الجداول النشطة', '30+ جدول'],
          ['إجمالي الـ Indexes', '294 index مُهيأ'],
          ['التخزين', 'Docker Volume مستمر (postgres_data)'],
          ['إجراء الـ Backup', 'docker volume + pg_dump دوري'],
        ],
        [35, 65]
      ),

      new Paragraph({ text: '3.2 استراتيجية الـ Indexing', heading: HeadingLevel.HEADING_2, spacing: { before: 280, after: 160 } }),
      p('تم إضافة 14 Composite Index على المسارات الساخنة (Hot Query Paths) استناداً إلى تحليل أداء الاستعلامات:'),
      makeTable(
        ['الجدول', 'الـ Index', 'الهدف'],
        [
          ['invoices', '(company_id, status, created_at)', 'KPI Dashboard + التقارير المالية'],
          ['invoices', '(customer_id)', 'استعلامات فواتير العميل'],
          ['invoices', '(uuid)', 'بحث ZATCA + QR Lookup'],
          ['work_orders', '(company_id, status, created_at)', 'لوحة التحكم التشغيلية'],
          ['work_orders', '(vehicle_id)', 'سجل المركبة'],
          ['work_orders', '(customer_id)', 'سجل العميل'],
          ['work_orders', '(assigned_technician_id)', 'مهام الفني'],
          ['customers', '(company_id, phone)', 'بحث سريع بالجوال'],
          ['customers', '(company_id, name)', 'البحث النصي'],
          ['vehicles', '(plate_number, company_id)', 'مسح لوحة المركبة'],
          ['vehicles', '(customer_id)', 'مركبات العميل'],
          ['products', '(company_id, sku)', 'بحث المنتج في POS'],
          ['bookings', '(company_id, starts_at, status)', 'استعلامات الحجوزات اليومية'],
          ['users', '(company_id, role)', 'فلترة الموظفين حسب الدور'],
        ],
        [22, 38, 40]
      ),

      new Paragraph({ text: '3.3 Redis — Cache & Queue & Sessions', heading: HeadingLevel.HEADING_2, spacing: { before: 280, after: 160 } }),
      makeTable(
        ['البيان', 'القيمة'],
        [
          ['الإصدار', 'Redis 7.2.13 (Alpine)'],
          ['الذاكرة المحجوزة', '384 MB (من 512 MB حد Docker)'],
          ['سياسة الطرد', 'allkeys-lru (أكثر الـ keys قِدَماً يُطرد عند امتلاء الذاكرة)'],
          ['الاستدامة (Persistence)', 'AOF (Append-Only File) مفعّل — إعادة كتابة تدريجية مفعّلة'],
          ['الاتصالات المستمرة', 'Persistent Connections (5 اتصالات مستمرة مستقلة)'],
          ['الضغط', 'LZF Compression (fallback: لا ضغط إذا غير متاح)'],
          ['التسلسل', 'PHP Serializer (fallback من igbinary غير المثبّت)'],
          ['زمن استجابة Raw', '~0.138 ms/op (200 عملية في 27ms)'],
          ['زمن استجابة Laravel Cache', '~2.15 ms/op (يشمل Serialization overhead)'],
          ['TCP Keepalive', '60 ثانية'],
          ['HZ (Clock ticks)', '20 (من 10 الافتراضي — استجابة أسرع)'],
          ['Lazy Freeing', 'مفعّل لجميع العمليات (lazyfree-lazy-*)'],
        ],
        [35, 65]
      ),

      new Paragraph({ text: '3.4 Redis Database Segmentation', heading: HeadingLevel.HEADING_2, spacing: { before: 280, after: 160 } }),
      makeTable(
        ['DB', 'الاستخدام', 'Timeout', 'Persistent ID'],
        [
          ['DB 0', 'Sessions — جلسات المستخدمين', '1.0s', 'osas_session'],
          ['DB 1', 'General Cache — التخزين المؤقت العام', '0.5s', 'osas_cache'],
          ['DB 2', 'KPI Cache — بيانات لوحة التحكم', '1.0s', 'osas_kpi'],
          ['DB 3', 'Queue — طابور المهام', '2.0s', 'osas_queue'],
          ['DB 4', 'Rate Limiting — حماية الـ API', '0.3s', 'osas_ratelimit'],
        ],
        [10, 40, 15, 35]
      ),

      // ══════════ 4. PHP RUNTIME ══════════
      new Paragraph({ text: '4. بيئة تشغيل PHP', heading: HeadingLevel.HEADING_1, spacing: { before: 400, after: 200 } }),
      makeTable(
        ['الإعداد', 'القيمة', 'الأثر'],
        [
          ['PHP Version', '8.3.30', 'آخر إصدار مستقر من PHP 8.3'],
          ['memory_limit', '512M', 'يدعم معالجة الملفات الكبيرة والاستعلامات المعقدة'],
          ['max_execution_time', '120s (FPM) / ∞ (CLI)', 'يمنع التعليق في الطلبات الطويلة'],
          ['OPcache', 'مفعّل (FPM)', 'تخزين bytecode مُصرَّف — يلغي إعادة تفسير الكود'],
          ['OPcache Memory', '256 MB', 'يتسع لـ 20,000 ملف PHP مُصرَّف'],
          ['validate_timestamps', '0 (production mode)', 'يلغي syscall stat() لكل ملف — تحسين كبير'],
          ['JIT Mode', 'tracing', 'يُحسّن الحلقات الساخنة (hot loops) في Laravel'],
          ['JIT Buffer', '64 MB', 'ذاكرة مخصصة للكود المُحسَّن JIT'],
          ['realpath_cache', '4096 KB / 600s', 'يخزّن مسارات الملفات المحلولة'],
          ['Interned Strings', '16 MB', 'يُقلل تكرار تخزين النصوص المتشابهة'],
          ['Timezone', 'Asia/Riyadh', 'التوقيت الرسمي للمملكة العربية السعودية'],
          ['Error Logging', '/var/log/php/error.log', 'تسجيل الأخطاء بدون عرضها للمستخدم'],
        ],
        [25, 25, 50]
      ),

      // ══════════ 5. SECURITY ══════════
      new Paragraph({ text: '5. الأمن والحماية', heading: HeadingLevel.HEADING_1, spacing: { before: 400, after: 200 } }),

      makeTable(
        ['الطبقة', 'الآلية', 'التفاصيل'],
        [
          ['كلمات المرور', 'Bcrypt (Laravel default)', 'bcrypt_rounds = 12 (من إعدادات Sanctum)'],
          ['المصادقة', 'Laravel Sanctum v4', 'Token-based (SHA-256 hash مخزّن في DB)'],
          ['تسريب Token', 'Hash فقط في DB', 'لا يُخزّن الـ token الأصلي أبداً — فقط hash'],
          ['CSRF Protection', 'Sanctum CSRF Cookie', 'Double Submit Cookie للـ SPA'],
          ['Tenant Isolation', 'TenantScopeMiddleware', 'يمنع الوصول عبر الـ Tenants (company_id injection)'],
          ['Rate Limiting', 'Redis DB4', 'حد الطلبات لكل IP/User لمنع Brute Force'],
          ['SQL Injection', 'Eloquent ORM + PDO Bindings', 'لا استعلامات Raw إلا مع الـ Bindings'],
          ['Authorization', 'Hybrid RBAC', 'Config-based + Polymorphic permissions في DB'],
          ['Subscription Check', 'subscription middleware', 'يمنع الوصول لـ features خارج الباقة'],
          ['Company Suspension', 'TenantScopeMiddleware', 'يوقف الوصول فوراً عند تعليق الشركة'],
          ['Error Monitoring', 'Sentry (Backend + Frontend)', 'تتبع الأخطاء في الإنتاج بـ DSN منفصل'],
          ['Secrets Management', 'Environment Variables', 'APP_KEY, DB_PASSWORD, REDIS_PASSWORD في .env'],
        ],
        [20, 25, 55]
      ),

      // ══════════ 6. FINANCIAL & ACCOUNTING ══════════
      new Paragraph({ text: '6. النظام المالي والمحاسبي', heading: HeadingLevel.HEADING_1, spacing: { before: 400, after: 200 } }),

      new Paragraph({ text: '6.1 بنية قاعدة البيانات المحاسبية', heading: HeadingLevel.HEADING_2, spacing: { before: 280, after: 160 } }),
      makeTable(
        ['الجدول', 'النوع', 'الوصف'],
        [
          ['invoices', 'الجدول الرئيسي', 'الفواتير مع uuid, invoice_hash, VAT fields'],
          ['journal_entries', 'Append-Only Ledger', 'قيود محاسبية لا تُحذف — 36 قيد نشط'],
          ['chart_of_accounts', 'شجرة الحسابات', 'Double-entry accounting accounts'],
          ['wallets', 'محافظ العملاء', 'رصيد العملاء + الأساطيل'],
          ['payments', 'المدفوعات', 'مرتبطة بـ invoices مع partial payment support'],
          ['purchases', 'المشتريات', 'أوامر الشراء + استلام البضاعة (goods_receipts)'],
        ],
        [25, 20, 55]
      ),

      new Paragraph({ text: '6.2 امتثال ZATCA Phase 2', heading: HeadingLevel.HEADING_2, spacing: { before: 280, after: 160 } }),
      p('يتبع النظام مواصفات هيئة الزكاة والضريبة والجمارك لإصدار الفواتير الإلكترونية:'),
      makeTable(
        ['المتطلب', 'التنفيذ'],
        [
          ['UUID فريد', 'Ramsey UUID v4 لكل فاتورة — حقل uuid في جدول invoices'],
          ['invoice_hash', 'SHA-256 لمحتوى الفاتورة (TLV encoded)'],
          ['previous_invoice_hash', 'سلسلة متصلة (Chain) من الفواتير — Immutable'],
          ['QR Code (TLV)', 'Tags: 1=Seller, 2=VAT#, 3=DateTime, 4=Total, 5=VATAmount'],
          ['QR Encoding', 'Base64(TLV Bytes) — يُعرض كـ QR في الفاتورة'],
          ['VAT Engine', 'Inclusive / Exclusive + Zero-Rated + Exempt'],
          ['Phase 2 Clearance', 'API endpoints جاهزة: POST /external/v1/invoices'],
          ['Audit Trail', 'كل فاتورة مرتبطة بـ journal_entry عبر source_type/source_id'],
        ],
        [30, 70]
      ),

      new Paragraph({ text: '6.3 نظام المحافظ (Multi-Wallet)', heading: HeadingLevel.HEADING_2, spacing: { before: 280, after: 160 } }),
      makeTable(
        ['الخاصية', 'التفاصيل'],
        [
          ['أنواع المحافظ', 'Cash / Promotional / Reserved / Credit (Fleet)'],
          ['محفظة الأسطول', 'محفظة رئيسية (Fleet) + محافظ مركبات فردية'],
          ['التحويل', 'POST /api/v1/wallet/transfer — موثّق في Ledger'],
          ['الشحن', 'POST /api/v1/wallet/top-up/fleet و individual'],
          ['Idempotency', 'idempotency_key لمنع التكرار في العمليات'],
          ['Reversal', 'POST /api/v1/wallet/reversal — Append-Only (لا DELETE)'],
          ['Ledger Backing', 'كل عملية محفظة → Journal Entry مقابلة'],
          ['Credit Limits', 'credit_limit في جدول customers + wallets'],
        ],
        [30, 70]
      ),

      // ══════════ 7. INFRASTRUCTURE ══════════
      new Paragraph({ text: '7. البنية التحتية (Infrastructure)', heading: HeadingLevel.HEADING_1, spacing: { before: 400, after: 200 } }),

      new Paragraph({ text: '7.1 حاويات Docker', heading: HeadingLevel.HEADING_2, spacing: { before: 280, after: 160 } }),
      makeTable(
        ['الخدمة', 'الصورة', 'Container Name', 'الدور'],
        [
          ['Backend App', 'PHP 8.3-FPM (Dockerfile مخصص)', 'saas_app', 'خادم التطبيق الرئيسي'],
          ['Queue High', 'نفس Backend', 'saas_queue_high', 'معالج الأولوية العالية (high_priority)'],
          ['Queue Default', 'نفس Backend', 'saas_queue_default', 'معالج الأولوية العادية'],
          ['Queue Low', 'نفس Backend', 'saas_queue_low', 'معالج الأولوية المنخفضة'],
          ['Scheduler', 'نفس Backend', 'saas_scheduler', 'مجدول المهام الدورية (schedule:work)'],
          ['Nginx', 'nginx:1.25-alpine', 'saas_nginx', 'Web Server + Reverse Proxy (Port 80/443)'],
          ['Frontend', 'Node.js/Vite (Dockerfile مخصص)', 'saas_frontend', 'Vite Dev Server (Port 5173)'],
          ['PostgreSQL', 'postgres:16-alpine', 'saas_postgres', 'قاعدة البيانات (Port 5432)'],
          ['Redis', 'redis:7.2-alpine', 'saas_redis', 'Cache + Queue + Sessions (Port 6379)'],
        ],
        [20, 28, 22, 30]
      ),

      new Paragraph({ text: '7.2 Queue Architecture — تفاصيل معالج الطابور', heading: HeadingLevel.HEADING_2, spacing: { before: 280, after: 160 } }),
      makeTable(
        ['الطابور', 'الأولوية', 'Sleep', 'Tries', 'Timeout', 'الاستخدام'],
        [
          ['high_priority', '1 (أعلى)', '3s', '3', '90s', 'الدفع، الفواتير، الإشعارات الفورية'],
          ['default', '2', '3s', '3', '60s', 'البريد، التقارير، الحجوزات'],
          ['low_priority', '3 (أدنى)', '5s', '2', '120s', 'التنظيف، الإحصاءات، السجلات'],
        ],
        [18, 15, 12, 12, 13, 30]
      ),

      new Paragraph({ text: '7.3 إعدادات الشبكة', heading: HeadingLevel.HEADING_2, spacing: { before: 280, after: 160 } }),
      makeTable(
        ['البيان', 'القيمة'],
        [
          ['شبكة Docker', 'saas_network (Bridge Driver — شبكة خاصة معزولة)'],
          ['المنافذ الخارجية', '80 (HTTP), 443 (HTTPS), 5173 (Vite Dev), 5432 (PG), 6379 (Redis)'],
          ['Health Checks', 'PostgreSQL: pg_isready (interval 10s), Redis: ping (interval 10s)'],
          ['إعادة التشغيل', 'unless-stopped لجميع الخدمات'],
          ['Dependency Order', 'app ← postgres(healthy) + redis(healthy)'],
        ],
        [30, 70]
      ),

      // ══════════ 8. API ARCHITECTURE ══════════
      new Paragraph({ text: '8. بنية الـ API (257 مساراً)', heading: HeadingLevel.HEADING_1, spacing: { before: 400, after: 200 } }),
      makeTable(
        ['المجموعة', 'عدد المسارات', 'البادئة'],
        [
          ['Auth (تسجيل الدخول/الخروج)', '4', '/api/v1/auth/'],
          ['Companies & Branches', '10', '/api/v1/companies/, branches/'],
          ['Users & Roles & Permissions', '12', '/api/v1/users/, roles/, permissions/'],
          ['Customers & Vehicles', '12', '/api/v1/customers/, vehicles/'],
          ['Products & Services & Units', '16', '/api/v1/products/, services/, units/'],
          ['Invoices & Quotes', '12', '/api/v1/invoices/, quotes/'],
          ['Work Orders', '8', '/api/v1/work-orders/'],
          ['POS (نقطة البيع)', '1', '/api/v1/pos/sale'],
          ['Financial (Payments/Wallet/Ledger)', '22', '/api/v1/payments/, wallet/, wallets/, ledger/'],
          ['Purchases & Inventory', '14', '/api/v1/purchases/, inventory/'],
          ['Reports', '9', '/api/v1/reports/'],
          ['Governance (Policies/Contracts/Audit)', '20', '/api/v1/governance/'],
          ['Workshop (HR/Tasks/Attendance)', '14', '/api/v1/workshop/'],
          ['Bookings & Bays', '9', '/api/v1/bookings/, bays/'],
          ['Fleet Portal', '10', '/api/v1/fleet-portal/, fleet/'],
          ['SaaS (Plans/Subscriptions)', '7', '/api/v1/plans/, subscription/'],
          ['External (ZATCA)', '2', '/api/v1/external/v1/'],
          ['Webhooks & Notifications', '7', '/api/v1/webhooks/, notifications/'],
          ['Health & Docs', '3', '/api/v1/health, /api/documentation'],
          ['Other (OCR/Loyalty/Fuel/NPS)', '18', 'موزعة'],
        ],
        [45, 20, 35]
      ),

      // ══════════ 9. PERFORMANCE ══════════
      new Paragraph({ text: '9. تحليل الأداء (Performance Metrics)', heading: HeadingLevel.HEADING_1, spacing: { before: 400, after: 200 } }),

      makeTable(
        ['المقياس', 'القيمة المقيسة', 'الهدف', 'الحالة'],
        [
          ['Raw Redis latency', '0.138 ms/op', '< 1ms', '✅ ممتاز'],
          ['Laravel Cache (read+write)', '2.15 ms/op', '< 10ms', '✅ جيد'],
          ['DB Indexes', '294 index', 'N/A', '✅ مُهيأ'],
          ['OPcache (FPM)', 'مفعّل (tracing JIT)', 'مفعّل', '✅ نشط'],
          ['Config Cache', 'مفعّل', 'مفعّل', '✅ نشط'],
          ['Route Cache', '257 مسار محفوظ', 'مفعّل', '✅ نشط'],
          ['Queue Workers', '3 workers + scheduler', 'N/A', '✅ نشط'],
          ['Redis Memory', '1.29 MB مستخدم / 384 MB', 'N/A', '✅ صحي'],
        ],
        [30, 20, 15, 35]
      ),

      new Paragraph({ text: '9.1 كيف وصل النظام لسرعة KPI < 10ms؟', heading: HeadingLevel.HEADING_2, spacing: { before: 280, after: 160 } }),
      p('يعتمد النظام على الاستراتيجية التالية لتحقيق زمن استجابة سريع لبيانات KPI:'),
      bullet('طبقة 1 — Redis KPI Cache (DB2): بيانات لوحة التحكم تُخزَّن في Redis DB2 بـ TTL قابل للتهيئة'),
      bullet('طبقة 2 — Composite Indexes: استعلامات (company_id, status, created_at) على invoices و work_orders تعمل بدون Full Table Scan'),
      bullet('طبقة 3 — Persistent DB Connections: PDO::ATTR_PERSISTENT يلغي تكلفة الاتصال (TCP handshake ~5ms)'),
      bullet('طبقة 4 — OPcache + JIT: الكود مُصرَّف مسبقاً + JIT tracing يُحسّن الحلقات الساخنة في Laravel'),
      bullet('طبقة 5 — Route Cache: 257 مسار محفوظة — Laravel لا يُعيد فهرسة الـ Routes في كل طلب'),

      // ══════════ 10. INTEGRATIONS ══════════
      new Paragraph({ text: '10. التكاملات الخارجية (Integrations)', heading: HeadingLevel.HEADING_1, spacing: { before: 400, after: 200 } }),
      makeTable(
        ['الجهة', 'البروتوكول', 'الحالة', 'الاستخدام'],
        [
          ['ZATCA Phase 2', 'REST API (HTTPS)', 'جاهز', 'Clearance + Reporting للفواتير الإلكترونية'],
          ['AWS S3', 'AWS SDK v3 (REST)', 'مُعدّ', 'تخزين الملفات، الصور، المستندات'],
          ['AWS SES', 'AWS SDK v3 (SMTP)', 'مُعدّ', 'إرسال البريد الإلكتروني'],
          ['WhatsApp', 'REST API (Webhook)', 'قابل للتهيئة', 'إشعارات العملاء + مشاركة الفواتير'],
          ['بوابات الدفع', 'REST (Guzzle)', 'جاهز للربط', 'Apple Pay / Google Pay / MADA / Visa'],
          ['Sentry', 'SDK (HTTP)', 'مفعّل', 'مراقبة الأخطاء في Backend + Frontend'],
          ['Swagger/OpenAPI', 'l5-swagger v8', 'مفعّل', 'توثيق API على /api/documentation'],
          ['Webhooks', 'REST (Outbound)', 'مُطبَّق', 'POST /api/v1/webhooks — إشعارات للأنظمة الخارجية'],
        ],
        [20, 20, 15, 45]
      ),

      // ══════════ 11. MULTI-TENANT ══════════
      new Paragraph({ text: '11. نظام Multi-Tenant والباقات', heading: HeadingLevel.HEADING_1, spacing: { before: 400, after: 200 } }),
      makeTable(
        ['الخاصية', 'التفاصيل'],
        [
          ['نموذج العزل', 'Shared Database — Row-Level Isolation عبر company_id'],
          ['Middleware', 'TenantScopeMiddleware يحقن company_id + branch_id في كل طلب'],
          ['HasTenantScope Trait', 'تُطبَّق على جميع النماذج — global scope تلقائي'],
          ['الباقات المتاحة', 'trial / basic / professional / enterprise'],
          ['Features تُخزَّن', 'JSON في جدول plans.features — تُقرأ عند كل طلب API'],
          ['subscription Middleware', 'يتحقق من الباقة قبل كل طلب محمي'],
          ['Feature Gating (Frontend)', 'subscription store (Pinia) + hasFeature() + locked 🔒'],
          ['بوابات الدخول', '3 بوابات: /login (موظف) /fleet/login (أسطول) /customer/login (عميل)'],
        ],
        [30, 70]
      ),

      new Paragraph({ text: '11.1 خصائص الباقات', heading: HeadingLevel.HEADING_2, spacing: { before: 280, after: 160 } }),
      makeTable(
        ['الخاصية', 'trial', 'basic', 'professional', 'enterprise'],
        [
          ['POS + الفواتير', '✅', '✅', '✅', '✅'],
          ['أوامر العمل', '❌', '✅', '✅', '✅'],
          ['التقارير', '❌', '✅', '✅', '✅'],
          ['نظام الأسطول', '❌', '❌', '✅', '✅'],
          ['API Access', '❌', '❌', '✅', '✅'],
          ['ZATCA Phase 2', '❌', '❌', '✅', '✅'],
          ['متعدد الفروع', '❌', '❌', '✅', '✅'],
        ],
        [30, 18, 13, 20, 19]
      ),

      // ══════════ 12. DATA DICTIONARY ══════════
      new Paragraph({ text: '12. جداول قاعدة البيانات النشطة (بيانات حالية)', heading: HeadingLevel.HEADING_1, spacing: { before: 400, after: 200 } }),
      makeTable(
        ['الجدول', 'عدد السجلات', 'الوصف'],
        [
          ['users', '7', 'مستخدمو النظام (Staff + Fleet + Customer)'],
          ['companies', '1', 'الشركة/المستأجر الحالي'],
          ['customers', '23', 'عملاء مركز الخدمة'],
          ['vehicles', '22', 'المركبات المسجلة'],
          ['invoices', '20', 'الفواتير الصادرة'],
          ['work_orders', '57', 'أوامر العمل'],
          ['products', '34', 'المنتجات والخدمات'],
          ['journal_entries', '36', 'القيود المحاسبية (Append-Only)'],
          ['bookings', '19', 'حجوزات الورشة'],
          ['plans', '5', 'باقات الاشتراك'],
          ['subscriptions', '1', 'الاشتراك النشط'],
          ['wallets', '0', 'محافظ العملاء (يتطلب تفعيل)'],
        ],
        [30, 20, 50]
      ),

      // ══════════ 13. ACCESS CREDENTIALS ══════════
      new Paragraph({ text: '13. بيانات الدخول للاختبار', heading: HeadingLevel.HEADING_1, spacing: { before: 400, after: 200 } }),
      new Paragraph({
        children: [
          new TextRun({ text: '⚠️ تنبيه أمني: ', bold: true, color: 'DC2626', size: 22 }),
          new TextRun({ text: 'هذه البيانات للبيئة التجريبية فقط. يجب تغييرها قبل الإنتاج.', size: 22, color: '374151' })
        ],
        shading: { type: ShadingType.SOLID, fill: 'FEF2F2' },
        border: { left: { style: BorderStyle.SINGLE, size: 12, color: 'DC2626' } },
        spacing: { before: 120, after: 160 },
        indent: { left: 200 }
      }),
      makeTable(
        ['البوابة', 'الرابط', 'الدور', 'الوصف'],
        [
          ['إدارة النظام', 'http://localhost/login', 'owner', 'المدير العام — صلاحيات كاملة'],
          ['مركز الخدمة', 'http://localhost/login', 'cashier / manager', 'محاسب + مدير الفرع'],
          ['بوابة الأسطول', 'http://localhost/fleet/login', 'fleet_contact / fleet_manager', 'إدارة الأسطول والمركبات'],
          ['بوابة العميل', 'http://localhost/customer/login', 'customer', 'بوابة العميل النهائي'],
          ['API Health Check', 'http://localhost/api/v1/health', 'N/A', 'فحص حالة النظام'],
          ['Swagger Docs', 'http://localhost/api/documentation', 'N/A', 'توثيق API التفاعلي'],
        ],
        [18, 30, 25, 27]
      ),

      // ══════════ 14. DEPLOYMENT ══════════
      new Paragraph({ text: '14. خطوات نشر النظام (Deployment Checklist)', heading: HeadingLevel.HEADING_1, spacing: { before: 400, after: 200 } }),
      makeTable(
        ['الخطوة', 'الأمر', 'ملاحظة'],
        [
          ['1. بدء الخدمات', 'docker compose up -d', 'يُشغّل جميع الـ 9 خدمات'],
          ['2. تشغيل الـ Migrations', 'docker exec saas_app php artisan migrate --force', 'يُنشئ جميع الجداول'],
          ['3. تعبئة البيانات الأولية', 'docker exec saas_app php artisan db:seed', 'الباقات + المستخدمين التجريبيين'],
          ['4. تفعيل Config Cache', 'docker exec saas_app php artisan config:cache', 'مطلوب في الإنتاج'],
          ['5. تفعيل Route Cache', 'docker exec saas_app php artisan route:cache', 'يُسرّع الـ routing'],
          ['6. تفعيل Event Cache', 'docker exec saas_app php artisan event:cache', 'يُسرّع الـ events'],
          ['7. تحديث الـ autoloader', 'docker exec saas_app composer dump-autoload -o', 'Optimized classmap'],
          ['8. إنشاء Storage Link', 'docker exec saas_app php artisan storage:link', 'للملفات العامة'],
        ],
        [10, 50, 40]
      ),

      // ══════════ 15. SCALING ROADMAP ══════════
      new Paragraph({ text: '15. خارطة طريق التوسع (Scaling Roadmap)', heading: HeadingLevel.HEADING_1, spacing: { before: 400, after: 200 } }),
      makeTable(
        ['المرحلة', 'الهدف', 'الإجراء المقترح'],
        [
          ['الآن (Local/Dev)', '< 100 مستخدم متزامن', 'Docker Compose — الإعداد الحالي'],
          ['القريب (Staging)', '100-1000 مستخدم', 'Docker Swarm + Load Balancer + Read Replica PG'],
          ['الإنتاج المبكر', '1000-10,000 مستخدم', 'Kubernetes (K8s) + Redis Cluster + PG Primary/Replica'],
          ['التوسع الكامل', '10,000-100,000 مستخدم', 'Microservices + Event Streaming (Kafka) + CDN'],
          ['Enterprise Scale', '100,000+ مستخدم', 'Multi-Region Deployment + Sharding + CQRS'],
        ],
        [20, 25, 55]
      ),
      p('الأساس المعماري الحالي يدعم الانتقال لـ Microservices بدون إعادة كتابة كاملة بفضل:'),
      bullet('Tenant Isolation المُطبَّق بشكل صحيح (كل عملية مرتبطة بـ company_id)'),
      bullet('Queue Architecture المنفصلة (3 workers مستقلة)'),
      bullet('Redis كـ Message Broker جاهز للتوسع'),
      bullet('API-first Design (Frontend منفصل تماماً عن Backend)'),

      // ══════════ FOOTER ══════════
      divider(),
      new Paragraph({
        children: [
          new TextRun({ text: 'OSAS Technical Reference — v1.0 — 2026-03-27', size: 18, color: '94a3b8' })
        ],
        alignment: AlignmentType.CENTER,
        spacing: { before: 200 }
      }),
    ]
  }]
});

Packer.toBuffer(doc).then(buffer => {
  fs.writeFileSync('OSAS_Technical_Documentation.docx', buffer);
  console.log('✅ OSAS_Technical_Documentation.docx created successfully');
}).catch(e => console.error('Error:', e));
