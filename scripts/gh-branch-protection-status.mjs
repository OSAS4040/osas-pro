#!/usr/bin/env node
/**
 * يقرأ إعداد حماية الفرع `main` عبر GitHub API (قراءة فقط).
 * متطلبات: GitHub CLI مثبتة ومسجّلة (`gh auth login`)، وتنفيذ من داخل المستودع.
 *
 * الاستخدام: node scripts/gh-branch-protection-status.mjs
 * أو: make github-branch-protection-status
 */

import { execFileSync } from 'node:child_process';

const REQUIRED_HINT = 'policy-env-example';

function gh(args, { throwOnError = true } = {}) {
  try {
    const out = execFileSync('gh', args, {
      encoding: 'utf8',
      maxBuffer: 12 * 1024 * 1024,
      stdio: ['ignore', 'pipe', 'pipe'],
    });
    return { ok: true, out: out.trim() };
  } catch (e) {
    const stderr = e.stderr?.toString?.()?.trim() || '';
    const stdout = e.stdout?.toString?.()?.trim() || '';
    if (throwOnError) {
      const msg = stderr || stdout || e.message || 'gh command failed';
      const err = new Error(msg);
      err.status = e.status;
      throw err;
    }
    return { ok: false, status: e.status, stderr, stdout };
  }
}

function ensureGh() {
  const v = gh(['--version'], { throwOnError: false });
  if (!v.ok) {
    console.error(
      'GitHub CLI غير متاح. ثبّتها من https://cli.github.com/ ثم نفّذ: gh auth login',
    );
    process.exit(2);
  }
}

function repoSlug() {
  const env = process.env.GH_REPO || process.env.GITHUB_REPOSITORY;
  if (env && env.includes('/')) {
    return env.trim();
  }
  const r = gh(['repo', 'view', '--json', 'nameWithOwner', '-q', '.nameWithOwner']);
  return r.out;
}

function collectRequiredContexts(protectionJson) {
  const out = new Set();
  const rsc = protectionJson.required_status_checks;
  if (!rsc) return out;
  if (Array.isArray(rsc.contexts)) {
    for (const c of rsc.contexts) {
      if (c) out.add(String(c));
    }
  }
  if (Array.isArray(rsc.checks)) {
    for (const c of rsc.checks) {
      const ctx = c?.context;
      if (ctx) out.add(String(ctx));
    }
  }
  return out;
}

function mentionsPolicyGate(contexts) {
  const lower = [...contexts].map((c) => c.toLowerCase());
  return lower.some(
    (c) =>
      c.includes(REQUIRED_HINT) ||
      (c.includes('policy') && c.includes('env') && c.includes('pr')),
  );
}

function rulesetCoversMain(ruleset) {
  const inc = ruleset?.conditions?.ref_name?.include;
  if (!Array.isArray(inc)) return false;
  return inc.some(
    (p) =>
      p === 'refs/heads/main' ||
      p === 'main' ||
      p === '~DEFAULT_BRANCH' ||
      (typeof p === 'string' && p.endsWith('main')),
  );
}

function summarizeRulesets(json) {
  if (!Array.isArray(json)) return { candidates: [], names: [] };
  const candidates = [];
  const names = [];
  for (const rs of json) {
    if (!rs || typeof rs !== 'object') continue;
    names.push(rs.name || `(ruleset #${rs.id})`);
    if (rs.enforcement !== 'active') continue;
    if (rs.target === 'tag') continue;
    if (!rulesetCoversMain(rs)) continue;
    candidates.push(rs);
  }
  return { candidates, names };
}

function collectContextsFromRulesetRules(rules) {
  const contexts = new Set();
  if (!Array.isArray(rules)) return contexts;
  for (const rule of rules) {
    if (rule?.type !== 'required_status_checks') continue;
    const req = rule.parameters?.required_status_checks;
    if (!Array.isArray(req)) continue;
    for (const check of req) {
      const ctx = check?.context;
      if (ctx) contexts.add(String(ctx));
    }
  }
  return contexts;
}

function main() {
  ensureGh();
  let slug;
  try {
    slug = repoSlug();
  } catch {
    console.error(
      'تعذر تحديد المستودع. نفّذ من مجلد git للمشروع أو عيّن GH_REPO=owner/name',
    );
    process.exit(2);
  }
  const [owner, repo] = slug.split('/');
  if (!owner || !repo) {
    console.error('صيغة المستودع غير صالحة:', slug);
    process.exit(2);
  }

  const pathClassic = `repos/${owner}/${repo}/branches/main/protection`;
  const classic = gh(['api', pathClassic, '-H', 'Accept: application/vnd.github+json'], {
    throwOnError: false,
  });

  if (classic.ok) {
    let data;
    try {
      data = JSON.parse(classic.out);
    } catch {
      console.error('استجابة غير متوقعة من GitHub API (حماية كلاسيكية).');
      process.exit(1);
    }
    const contexts = collectRequiredContexts(data);
    console.log('=== حماية كلاسيكية على refs/heads/main ===');
    console.log(
      'يتطلب مراجعة PR:',
      data.required_pull_request_reviews ? 'نعم' : 'لا (راجع الإعدادات)',
    );
    console.log(
      'يتطلب تحديث الفرع قبل الدمج:',
      data.required_status_checks?.strict === true ? 'نعم' : 'لا أو غير محدد',
    );
    const fp = data.allow_force_pushes;
    console.log(
      'منع force push:',
      fp?.enabled === false ? 'نعم' : 'غير مضبوط أو مسموح (مراجعة مطلوبة)',
    );
    if (contexts.size === 0) {
      console.log('الفحوص المطلوبة: (لا شيء مضاف — أضف Policy env على الأقل)');
    } else {
      console.log('الفحوص المطلوبة:');
      for (const c of [...contexts].sort()) console.log(`  - ${c}`);
    }
    if (!mentionsPolicyGate(contexts)) {
      console.log(
        '\nتحذير: لم يظهر فحص يطابق «Policy env on PR / policy-env-example» في القائمة أعلاه.',
      );
      console.log('         أضفه من: Settings → Branches → حماية main → Required status checks.');
      process.exit(1);
    }
    console.log(
      '\nحالة: يبدو أن فحص السياسة مضمن (تحقق بصرياً أن الاسم يطابق واجهة GitHub بالضبط).',
    );
    process.exit(0);
  }

  const rsOut = gh(['api', `repos/${owner}/${repo}/rulesets`], { throwOnError: false });
  if (!rsOut.ok) {
    console.error('لا حماية كلاسيكية ظاهرة على main، وتعذر قراءة rulesets:');
    console.error(rsOut.stderr || classic.stderr || 'خطأ غير معروف');
    process.exit(1);
  }

  let rulesets;
  try {
    rulesets = JSON.parse(rsOut.out);
  } catch {
    console.error('استجابة rulesets غير صالحة.');
    process.exit(1);
  }

  const { candidates, names } = summarizeRulesets(rulesets);
  console.log('=== قواعد المستودع (Rulesets) ===');
  console.log('عدد القواعد:', Array.isArray(rulesets) ? rulesets.length : 0);
  if (names.length) console.log('الأسماء:', names.join(', '));

  if (candidates.length === 0) {
    console.log(
      '\nلا توجد ruleset نشطة واضحة تغطي main/default، أو الحماية غير مفعّلة.',
    );
    console.log('انظر: docs/GitHub_Branch_Protection_Setup.md');
    process.exit(1);
  }

  const allContexts = new Set();
  for (const rs of candidates) {
    const id = rs.id;
    if (id == null) continue;
    const detail = gh(
      ['api', `repos/${owner}/${repo}/rulesets/${id}`, '-H', 'Accept: application/vnd.github+json'],
      { throwOnError: false },
    );
    if (!detail.ok) {
      console.error(`تعذر جلب تفاصيل ruleset #${id}`);
      continue;
    }
    let data;
    try {
      data = JSON.parse(detail.out);
    } catch {
      continue;
    }
    for (const c of collectContextsFromRulesetRules(data.rules)) {
      allContexts.add(c);
    }
  }

  if (allContexts.size) {
    console.log('\nالفحوص المطلوبة (من rulesets):');
    for (const c of [...allContexts].sort()) console.log(`  - ${c}`);
  } else {
    console.log(
      '\nلم تُستخرج فحوص من rulesets (قد لا تتضمن required_status_checks أو يحتاج صلاحية أوسع).',
    );
  }

  console.log('\nRulesets نشطة تغطي main/default:', candidates.length);
  if (!mentionsPolicyGate(allContexts)) {
    console.log(
      '\nتحذير: أضف فحصاً إلزامياً يطابق «Policy env on PR / policy-env-example» في ruleset الفرع main.',
    );
    process.exit(1);
  }
  console.log('\nحالة: يبدو أن فحص السياسة مضمن في rulesets (راجع الاسم في GitHub).');
  process.exit(0);
}

main();
