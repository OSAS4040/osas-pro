import { textSummary } from 'https://jslib.k6.io/k6-summary/0.0.2/index.js';
import { PROFILE_LABELS, STRESS_REPORT_CRITERIA } from '../config/acceptance.js';
import { JOURNEY_SLOS } from '../config/journeys.js';

const DEG_FAIL_RATE = STRESS_REPORT_CRITERIA.degradeFailRate;
const DEG_P95_MS = STRESS_REPORT_CRITERIA.degradeP95Ms;
const COLLAPSE_FAIL_RATE = STRESS_REPORT_CRITERIA.collapseFailRate;
const COLLAPSE_P95_MS = STRESS_REPORT_CRITERIA.collapseP95Ms;

function metricVal(m, key) {
  if (!m || !m.values) {
    return null;
  }
  return m.values[key] != null ? m.values[key] : null;
}

function thresholdResults(data) {
  const lines = [];
  const metrics = data.metrics || {};
  for (const [name, spec] of Object.entries(metrics)) {
    const thr = spec.thresholds;
    if (!thr) {
      continue;
    }
    if (typeof thr === 'object' && !Array.isArray(thr)) {
      for (const [expr, raw] of Object.entries(thr)) {
        const ok = typeof raw === 'object' && raw !== null && 'ok' in raw ? Boolean(raw.ok) : Boolean(raw);
        lines.push({
          metric: name,
          expression: expr,
          ok,
        });
      }
    }
  }
  return lines;
}

function trendStat(metrics, metricName, stat) {
  const m = metrics[metricName];
  if (!m || !m.values || m.values[stat] == null) {
    return null;
  }
  return m.values[stat];
}

/**
 * حدود منفصلة من Trend/Rate مخصّصة (تُنشر دائماً في latest-summary.json).
 * failRate هنا = «عدم تحقق التوقّع التشغيلي» (مثلاً 1 - نجاح القراءة، 1 - نجاح عزل 403).
 */
function buildWorkloadKindFromCustomMetrics(metrics) {
  const byKind = { reads: [], pos: [], sensitive: [] };
  const t50 = (n) => trendStat(metrics, n, 'p(50)');
  const t95 = (n) => trendStat(metrics, n, 'p(95)');

  if (metrics.scen_read_mix_http_ms) {
    const ro = metrics.read_mix_operational_ok ? metricVal(metrics.read_mix_operational_ok, 'rate') : null;
    byKind.reads.push({
      scenario: 'read_mix',
      failRate: ro != null ? 1 - ro : null,
      p50: t50('scen_read_mix_http_ms'),
      p95: t95('scen_read_mix_http_ms'),
    });
  }

  if (metrics.scen_pos_post_http_ms) {
    const pr = metrics.pos_sale_2xx ? metricVal(metrics.pos_sale_2xx, 'rate') : null;
    byKind.pos.push({
      scenario: 'pos_sale POST',
      failRate: pr != null ? 1 - pr : null,
      p50: t50('scen_pos_post_http_ms'),
      p95: t95('scen_pos_post_http_ms'),
    });
  }
  if (metrics.scen_pos_invoice_get_http_ms) {
    const rw = metrics.raw_read_after_write_ok ? metricVal(metrics.raw_read_after_write_ok, 'rate') : null;
    byKind.pos.push({
      scenario: 'invoice GET بعد البيع (RAW)',
      failRate: rw != null ? 1 - rw : null,
      p50: t50('scen_pos_invoice_get_http_ms'),
      p95: t95('scen_pos_invoice_get_http_ms'),
    });
  }

  if (metrics.scen_isolation_http_ms) {
    const iso = metrics.tenant_isolation_403 ? metricVal(metrics.tenant_isolation_403, 'rate') : null;
    byKind.sensitive.push({
      scenario: 'عزل cross-tenant (رفض 403/404)',
      failRate: iso != null ? 1 - iso : null,
      p50: t50('scen_isolation_http_ms'),
      p95: t95('scen_isolation_http_ms'),
    });
  }

  return byKind;
}

function smokeMergedLimitsFallback(metrics) {
  const d = metrics.http_req_duration ? metrics.http_req_duration.values : null;
  const httpFail = metrics.http_req_failed ? metricVal(metrics.http_req_failed, 'rate') : null;
  const op = metrics.operational_http_success ? metricVal(metrics.operational_http_success, 'rate') : null;
  const pos = metrics.pos_sale_2xx ? metricVal(metrics.pos_sale_2xx, 'rate') : null;
  const raw = metrics.raw_read_after_write_ok ? metricVal(metrics.raw_read_after_write_ok, 'rate') : null;
  const p50 = d && d['p(50)'] != null ? d['p(50)'].toFixed(0) + ' ms' : '—';
  const p95 = d && d['p(95)'] != null ? d['p(95)'].toFixed(0) + ' ms' : '—';
  const opPct = op != null ? (op * 100).toFixed(2) + '%' : '—';
  const posPct = pos != null ? (pos * 100).toFixed(2) + '%' : '—';
  const rawPct = raw != null ? (raw * 100).toFixed(2) + '%' : '—';
  const hf = httpFail != null ? (httpFail * 100).toFixed(2) + '%' : '—';
  return [
    `- **مزيج smoke (قراءات + POS + عزل — مُدمج في \`smoke_mixed\`):** زمن HTTP إجمالي p50 ${p50}، p95 ${p95}؛ \`operational_http_success\` ${opPct}؛ POS 201 ${posPct}؛ RAW بعد البيع ${rawPct}؛ \`http_req_failed\` k6 ${hf} (متوقع ارتفاعه بسبب 403 العزل).`,
    ``,
  ].join('\n');
}

function formatWorkloadLimitsBlock(profile, data) {
  const metrics = data.metrics || {};
  const byKind = buildWorkloadKindFromCustomMetrics(metrics);
  const fmtRow = (label, rows) => {
    if (!rows.length) {
      return `- **${label}:** لا بيانات بعد — تأكد من تشغيل سيناريوهات القراءة/POS/العزل في هذا الملف الشخصي.`;
    }
    const parts = rows.map((r) => {
      const fr = r.failRate != null ? (r.failRate * 100).toFixed(2) + '%' : '—';
      const p95 = r.p95 != null ? r.p95.toFixed(0) + ' ms' : '—';
      const p50 = r.p50 != null ? r.p50.toFixed(0) + ' ms' : '—';
      return `\`${r.scenario}\`: معدل عدم تحقق التوقّع ${fr}، p50 ${p50}، p95 ${p95}`;
    });
    return `- **${label}:** ${parts.join('؛ ')}`;
  };

  const head = [
    `### حدود آمنة تشغيلية مُقتَطفة من الشرائح`,
    ``,
    `*(مقارنة نسبية على **هذه** البيئة؛ الحكم النهائي يبقى على التشغيل الموثّق.)*`,
    ``,
  ];

  if (profile === 'smoke') {
    head.push(smokeMergedLimitsFallback(metrics), `- **تفصيل نوع الحمل (من عيّنات فعلية داخل \`smoke_mixed\`):**`, ``);
  }

  head.push(
    fmtRow('قراءات (مزيج API للوحة/عملاء/مخزون/…)', byKind.reads),
    fmtRow('POS وما يرتبط بالكتابة المالية', byKind.pos),
    fmtRow('مسارات حساسة (عزل tenant)', byKind.sensitive),
  );

  if (profile === 'smoke') {
    head.push(`- **مسارات \`smoke_idempotency\`:** تُراجع في العتبات و\`idempotency_409_payload_mismatch\`.`, ``);
  }

  if (profile === 'stress') {
    head.push(
      ``,
      `- **إجهاد:** مزيج القراءة يُقسّم حسب المراحل في جدول stress أدناه؛ صف \`read_mix\` المخصّص غير معروض هنا لأنه يُجمّع على كل المراحل.`,
      ``,
    );
  }

  return head.join('\n');
}

function errorTaxonomyBlock(metrics) {
  const pct = (name) => {
    const m = metrics[name];
    const r = m ? metricVal(m, 'rate') : null;
    return r != null ? (r * 100).toFixed(3) + '% من الطلبات' : '—';
  };
  return [
    `### تصنيف الأخطاء (لا يُكتفى بمؤشر فشل عام واحد)`,
    ``,
    `| الصنف | نسبة الطلبات (تقريباً) | ملاحظة |`,
    `|--------|------------------------|--------|`,
    `| **شبكة / مهلة / بدون استجابة** | ${pct('error_type_network')} | status = 0 |`,
    `| **5xx خادم** | ${pct('error_type_server_5xx')} | يطابق مؤشر server_errors_5xx تقريباً |`,
    `| **4xx عميل** | ${pct('error_type_client_4xx')} | يشمل 403/404 على مسار العزل — **متوقع**؛ مؤشر \`http_req_failed\` في k6 يعدّها «فشلاً» بينما \`operational_http_success\` يعتبرها نجاحاً تشغيلياً |`,
    `| **اتساق قراءة بعد كتابة (RAW)** | انظر القسم التالي | معدل نجاح لسلسلة POS→GET فقط |`,
    ``,
  ].join('\n');
}

function rawConsistencyBlock(metrics) {
  const raw = metrics.raw_read_after_write_ok ? metricVal(metrics.raw_read_after_write_ok, 'rate') : null;
  const invTrend = metrics.invoice_follow_ms ? metrics.invoice_follow_ms.values : null;
  const posOk = metrics.pos_sale_2xx ? metricVal(metrics.pos_sale_2xx, 'rate') : null;
  const rawPct = raw != null ? (raw * 100).toFixed(2) + '%' : '—';
  const posPct = posOk != null ? (posOk * 100).toFixed(2) + '%' : '—';
  const p95Follow = invTrend && invTrend['p(95)'] != null ? invTrend['p(95)'].toFixed(0) + ' ms' : '—';
  return [
    `### اتساق read-after-write بعد POS / فاتورة`,
    ``,
    `| المؤشر | القيمة |`,
    `|--------|--------|`,
    `| **نجاح RAW** (GET /invoices/{id} يطابق البيع بعد 201) | **${rawPct}** |`,
    `| **مبيعات POS بنجاح (2xx/201)** | ${posPct} |`,
    `| **زمن متابعة الفاتورة** invoice_follow_ms p95 | ${p95Follow} |`,
    ``,
    `- **قراءة تنفيذية:** إذا انخفض نجاح RAW عن عتبة الملف الشخصي، لا يُعتبر مسار البيع→الفاتورة جاهزاً للذروة دون تحقيق سبب (تأخير نشر، replica، تعارض، إلخ).`,
    ``,
  ].join('\n');
}

function journeySloBlock(metrics) {
  const row = (label, p95, p95Lim, okP95, suc, sucLim, okSuc) =>
    `| ${label} | ${p95} / ${p95Lim} | ${okP95 ? 'نعم' : 'لا'} | ${suc} / ${sucLim} | ${okSuc ? 'نعم' : 'لا'} |`;

  const lines = [
    `### SLO حسب الرحلة (دقة تشغيلية أعلى)`,
    ``,
    `| الرحلة | p95 (فعلي/حد) | p95 ضمن SLO؟ | النجاح (فعلي/حد) | نجاح ضمن SLO؟ |`,
    `|--------|----------------|----------------|-------------------|----------------|`,
  ];

  const hotspot = [];
  for (const j of JOURNEY_SLOS) {
    const p95Val = trendStat(metrics, j.p95Metric, 'p(95)');
    const successVal = metricVal(metrics[j.successMetric], 'rate');
    const p95Txt = p95Val != null ? `${p95Val.toFixed(0)} ms` : '—';
    const p95LimTxt = `${j.p95LimitMs} ms`;
    const sucTxt = successVal != null ? `${(successVal * 100).toFixed(2)}%` : '—';
    const sucLimTxt = `${(j.successMinRate * 100).toFixed(2)}%`;
    const okP95 = p95Val != null ? p95Val <= j.p95LimitMs : false;
    const okSuc = successVal != null ? successVal >= j.successMinRate : false;
    lines.push(row(j.label, p95Txt, p95LimTxt, okP95, sucTxt, sucLimTxt, okSuc));

    if (!okP95 || !okSuc) {
      hotspot.push(`- ${j.label}: راجع ${j.p95Metric} و${j.successMetric}.`);
    }
  }

  lines.push('', `**مناطق اختناق مرشحة:**`);
  if (!hotspot.length) {
    lines.push('- لا اختناق واضح ضمن حدود SLO المعرفة لهذا التشغيل.');
  } else {
    lines.push(...hotspot);
  }
  lines.push('');
  return lines.join('\n');
}

function analyzeSoakSegments(metrics) {
  const defs = [
    { key: 'soak_read_seg1_http_ms', scenario: 'soak_read_seg1' },
    { key: 'soak_read_seg2_http_ms', scenario: 'soak_read_seg2' },
    { key: 'soak_read_seg3_http_ms', scenario: 'soak_read_seg3' },
  ];
  const segs = defs.map(({ key, scenario }) => ({
    scenario,
    p95: trendStat(metrics, key, 'p(95)'),
    p50: trendStat(metrics, key, 'p(50)'),
    failRate: null,
  }));
  const valid = segs.filter((s) => s.p95 != null);
  if (valid.length < 2) {
    return {
      segs,
      verdict: 'غير مُحتسب — بيانات شرائح النقع (`soak_read_seg*_http_ms`) غير متوفرة في هذا التشغيل.',
      gradualP95: false,
      gradualFail: false,
    };
  }
  const p95s = valid.map((s) => s.p95).filter((x) => x != null);
  let gradualP95 = false;
  const gradualFail = false;
  if (p95s.length >= 2) {
    const first = p95s[0];
    const last = p95s[p95s.length - 1];
    gradualP95 = last > first * 1.35;
  }
  const verdict = gradualP95 || gradualFail
    ? '**نعم — يُلاحظ تدهور تدريجي** بين الشرائح الزمنية (ارتفاع p95 للقراءة من الشريحة الأولى إلى الأخيرة).'
    : '**لا — لا دلالة قوية على تدهور تدريجي** في p95 قراءة النقع (مقارنة أول/وسط/آخر ~30 دقيقة).';
  return { segs, verdict, gradualP95, gradualFail };
}

function soakTimeEvolutionBlock(profile, data) {
  if (profile !== 'soak') {
    return '';
  }
  const { segs, verdict, gradualP95, gradualFail } = analyzeSoakSegments(data.metrics || {});
  const rows = segs.map((s) => {
    const p95 = s.p95 != null ? s.p95.toFixed(0) : '—';
    const p50 = s.p50 != null ? s.p50.toFixed(0) : '—';
    return `| \`${s.scenario}\` | ${p50} | ${p95} |`;
  });
  return [
    `## نقع (soak): تدهور تدريجي عبر الزمن؟`,
    ``,
    `يُقارن **زمن طلبات مزيج القراءة** في ثلاث شرائح متتالية (~30 دقيقة لكل منها) عبر المؤشرات \`soak_read_seg*_http_ms\`.`,
    ``,
    `| الشريحة | p50 (ms) | p95 (ms) |`,
    `|---------|----------|----------|`,
    ...rows,
    ``,
    `- **الخلاصة:** ${verdict}`,
    `- **مؤشر مساعد:** ارتفاع p95 بين أول وآخر شريحة >35% → ${gradualP95 ? 'مُلاحظ (تدهور زمني محتمل)' : 'غير مُلاحظ'}.`,
    ``,
  ].join('\n');
}

function stressSubMetric(metrics, scenario, prefix) {
  const want = [`${prefix}{scen:${scenario}}`, `${prefix}{scenario:${scenario}}`];
  for (const k of want) {
    if (metrics[k]) {
      return metrics[k];
    }
  }
  const alt = Object.keys(metrics).find((k) => k.startsWith(prefix) && (k.includes(`scen:${scenario}`) || k.includes(`scenario:${scenario}`)));
  return alt ? metrics[alt] : null;
}

function analyzeStress(metrics) {
  const stages = [15, 30, 50, 70, 100, 130];
  const rows = [];
  let firstDegrade = null;
  let firstCollapse = null;

  for (const n of stages) {
    const scenario = `stress_s${n}`;
    const mFail = stressSubMetric(metrics, scenario, 'http_req_failed');
    const mDur = stressSubMetric(metrics, scenario, 'http_req_duration');
    let fr = mFail ? metricVal(mFail, 'rate') : null;
    let p95 = mDur ? metricVal(mDur, 'p(95)') : null;

    const degraded = (fr != null && fr > DEG_FAIL_RATE) || (p95 != null && p95 > DEG_P95_MS);
    const collapsed =
      (fr != null && fr > COLLAPSE_FAIL_RATE) || (p95 != null && p95 > COLLAPSE_P95_MS);

    rows.push({ vu: n, failRate: fr, p95, degraded, collapsed });

    if (degraded && !firstDegrade) {
      firstDegrade = { vu: n, failRate: fr, p95_ms: p95 };
    }
    if (collapsed && !firstCollapse) {
      firstCollapse = { vu: n, failRate: fr, p95_ms: p95 };
    }
  }

  return { rows, firstDegrade, firstCollapse };
}

function safeLimitRecommendation(profile, data, stressAnalysis) {
  const m = data.metrics || {};
  const globalFail = m.http_req_failed ? metricVal(m.http_req_failed, 'rate') : null;
  const p95 = m.http_req_duration ? metricVal(m.http_req_duration, 'p(95)') : null;
  const s5 = m.server_errors_5xx ? metricVal(m.server_errors_5xx, 'rate') : null;

  if (profile === 'capacity_pos') {
    return 'تشغيل استكشافي لسعة POS — راقب `scen_pos_post_http_ms` p99 و`pos_sale_2xx` و`dropped_iterations`. قارِن عدة تشغيلات بمعدلات وصول مختلفة (مثلاً 3 / 5 / 7/sec عبر `K6_CAPACITY_POS_RATE`).';
  }

  if (profile === 'stress') {
    if (stressAnalysis.firstCollapse) {
      return `الحد الآمن التقريبي أقل من **~${stressAnalysis.firstCollapse.vu} VU** (ظهر انهيار/تدهور شديد عند هذه المرحلة). راجع جدول مراحل الإجهاد أدناه.`;
    }
    if (stressAnalysis.firstDegrade) {
      return `أول تدهور غير مقبول (معايير مُعرّفة: fail>${DEG_FAIL_RATE * 100}% أو p95>${DEG_P95_MS}ms) عند **~${stressAnalysis.firstDegrade.vu} VU**. التشغيل المستقر الموصى به دون تحسين بنية: أقل من هذا المستوى.`;
    }
    return 'لم يُكتشف تدهور ضمن مراحل الإجهاد الحالية — يمكن رفع السقف أو إطالة المراحل.';
  }

  const opOk = m.operational_http_success ? metricVal(m.operational_http_success, 'rate') : null;
  const opsAcceptable = opOk != null ? opOk >= 0.97 : globalFail != null && globalFail < 0.02;

  if (opsAcceptable && (s5 == null || s5 < 0.01) && p95 != null && p95 < 4000) {
    const map = {
      smoke: 'Smoke يمر — جرّب **Normal** ثم **Peak** على staging.',
      normal: 'Normal يمر — الحد الآمن الحالي على هذه البيئة: **حتى ~30 VU** لهذا المزيج من السيناريوهات.',
      peak: 'Peak يمر — الحد الآمن التقريبي: **حتى ~75 VU** قراءة لهذا المزيج (راقب 5xx وقاعدة البيانات).',
      spike: 'Spike يمر — النظام يتحمل قفزة مؤقتة إلى ~95 VU قراءة.',
      soak: 'Soak يمر — استقرار زمني جيد عند ~22 VU على مدى التشغيل.',
    };
    return map[profile] || 'انظر المقاييس التفصيلية.';
  }

  return `لم يتحقق خط الأساس **${profile}** بالكامل — انظر فشل العتبات أدناه قبل رفع الحموضة في الإنتاج.`;
}

export function buildHandleSummary(profileRaw) {
  const profile = (profileRaw || 'smoke').toLowerCase();

  return function handleSummary(data) {
    const ts = new Date().toISOString().replace(/[:.]/g, '-');
    const thrRows = thresholdResults(data);
    const passed = thrRows.filter((t) => t.ok);
    const failed = thrRows.filter((t) => !t.ok);

    const stressAnalysis =
      profile === 'stress' ? analyzeStress(data.metrics || {}) : { rows: [], firstDegrade: null, firstCollapse: null };

    const md = [
      `# تقرير اختبار التحميل — WorkshopOS`,
      ``,
      `| البند | القيمة |`,
      `|--------|--------|`,
      `| **الملف الشخصي (K6_PROFILE)** | \`${profile}\` |`,
      `| **وصف خط الأساس المعتمد** | ${PROFILE_LABELS[profile] || profile} |`,
      `| **زمن التقرير** | ${ts} |`,
      ``,
      `## معايير القبول (thresholds) المعتمدة في k6`,
      ``,
      thrRows.length
        ? thrRows.map((t) => `- \`${t.metric}\` → \`${t.expression}\` → **${t.ok ? 'نجح' : 'فشل'}**`).join('\n')
        : '(لا توجد عتبات مفصّلة في ملخص k6 — قد يعتمد الإصدار على تنسيق مختلف؛ راجع `latest-summary.json`.)',
      ``,
      `| ملخص | العدد |`,
      `|------|------|`,
      `| عتبات ناجحة | ${passed.length} |`,
      `| عتبات فاشلة | ${failed.length} |`,
      ``,
      `## مؤشرات رئيسية (عامة)`,
      ``,
      formatCoreMetrics(data.metrics || {}),
      ``,
      errorTaxonomyBlock(data.metrics || {}),
      rawConsistencyBlock(data.metrics || {}),
      journeySloBlock(data.metrics || {}),
      formatWorkloadLimitsBlock(profile, data),
      ...(profile === 'soak' ? [soakTimeEvolutionBlock(profile, data), ``] : []),
      ...(profile === 'stress'
        ? [
            `## تحليل إجهاد مرحلي (stress_s15 … stress_s130)`,
            ``,
            `- **تدهور غير مقبول:** معدل فشل HTTP > ${DEG_FAIL_RATE * 100}% أو p95 > ${DEG_P95_MS} ms لمرحلة.`,
            `- **انهيار تقريبي:** فشل > ${COLLAPSE_FAIL_RATE * 100}% أو p95 > ${COLLAPSE_P95_MS} ms.`,
            ``,
            `| VU | fail rate | p95 (ms) | تدهور؟ | انهيار؟ |`,
            `|----|-----------|----------|--------|--------|`,
            ...stressAnalysis.rows.map(
              (r) =>
                `| ${r.vu} | ${r.failRate != null ? (r.failRate * 100).toFixed(2) + '%' : '—'} | ${r.p95 != null ? r.p95.toFixed(0) : '—'} | ${r.degraded ? 'نعم' : 'لا'} | ${r.collapsed ? 'نعم' : 'لا'} |`,
            ),
            ``,
            `- **أول تدهور:** ${stressAnalysis.firstDegrade ? `~${stressAnalysis.firstDegrade.vu} VU (fail ${stressAnalysis.firstDegrade.failRate != null ? (stressAnalysis.firstDegrade.failRate * 100).toFixed(1) : '—'}%, p95 ${stressAnalysis.firstDegrade.p95_ms != null ? stressAnalysis.firstDegrade.p95_ms.toFixed(0) : '—'} ms)` : 'لم يُلاحظ ضمن العتبات أعلاه'}`,
            `- **أول انهيار:** ${stressAnalysis.firstCollapse ? `~${stressAnalysis.firstCollapse.vu} VU` : 'لم يُلاحظ'}`,
            ``,
          ]
        : []),
      `## ما نجح / ما فشل (ملخص تنفيذي)`,
      ``,
      `- **نجح:** ${passed.length} من ${thrRows.length || '؟'} عتبة معرّفة في التقرير.`,
      `- **فشل:** ${failed.length} عتبة — راجع الجدول أعلاه والـ JSON.`,
      `- **سلامة بيانات / تكرار مالي:** يتطلب تدقيق DB خارج k6؛ طبقياً: مفاتيح Idempotency فريدة للبيع، وسيناريو 409 لنفس المفتاح بجسم مختلف، وفحص عزل \`/companies/{id}\`.`,
      ``,
      `## الحد الآمن الحالي (تقدير من هذا التشغيل)`,
      ``,
      safeLimitRecommendation(profile, data, stressAnalysis),
      ``,
      `## البيئة`,
      ``,
      `- Sentry غير المضبوط → تحذيرات compose فقط.`,
      `- \`version\` في docker-compose مهمل.`,
      `- Laravel tests عبر Docker.`,
      ``,
      `---`,
      `*مُولّد من k6 handleSummary — الملف الشخصي: ${profile}*`,
      ``,
    ].join('\n');

    return {
      [`../reports/latest.md`]: md,
      [`../reports/run-${ts}.md`]: md,
      [`../reports/latest-summary.json`]: JSON.stringify(data, null, 2),
      stdout: textSummary(data, { indent: ' ', enableColors: false }),
    };
  };
}

function formatCoreMetrics(metrics) {
  const httpFail = metrics.http_req_failed ? metricVal(metrics.http_req_failed, 'rate') : null;
  const opOk = metrics.operational_http_success ? metricVal(metrics.operational_http_success, 'rate') : null;
  const s5 = metrics.server_errors_5xx ? metricVal(metrics.server_errors_5xx, 'rate') : null;
  const to = metrics.client_timeout_or_network ? metricVal(metrics.client_timeout_or_network, 'rate') : null;
  const d = metrics.http_req_duration ? metrics.http_req_duration.values : null;
  const row = (k, v) => `| ${k} | ${v} |`;

  const out = [
    '| مؤشر | قيمة |',
    '|------|------|',
    row('operational_http_success', opOk != null ? (opOk * 100).toFixed(3) + '%' : '—'),
    row('http_req_failed (k6، يشمل 4xx)', httpFail != null ? (httpFail * 100).toFixed(3) + '%' : '—'),
    row('server_errors_5xx', s5 != null ? (s5 * 100).toFixed(3) + '%' : '—'),
    row('client_timeout_or_network', to != null ? (to * 100).toFixed(3) + '%' : '—'),
  ];
  if (d) {
    out.push(row('p50 ms', d['p(50)'] != null ? d['p(50)'].toFixed(0) : '—'));
    out.push(row('p95 ms', d['p(95)'] != null ? d['p(95)'].toFixed(0) : '—'));
    out.push(row('p99 ms', d['p(99)'] != null ? d['p(99)'].toFixed(0) : '—'));
  }
  return out.join('\n');
}
