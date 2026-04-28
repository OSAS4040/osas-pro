/**
 * تذكير ترتيبي بعد نجاح policy-env-example — لا يغيّر GitHub ولا Docker.
 * تشغيل: node scripts/execution-order-local-hint.mjs
 * أو: make execution-order-local
 * Windows بدون make: pwsh -File scripts/execution-order-local.ps1 (أو powershell -File ...)
 */
import { execFileSync } from 'node:child_process'

function ghOk() {
  try {
    execFileSync('gh', ['auth', 'status'], {
      encoding: 'utf8',
      stdio: ['ignore', 'pipe', 'pipe'],
    })
    return true
  } catch {
    return false
  }
}

function ghVersion() {
  try {
    const v = execFileSync('gh', ['--version'], {
      encoding: 'utf8',
      stdio: ['ignore', 'pipe', 'pipe'],
    })
    return v.trim().split('\n')[0] || 'gh'
  } catch {
    return null
  }
}

console.log('')
console.log('=== Execution Order — توجيهات المراحل (محلي) ===')
console.log('')
console.log('المرحلة 0: تم تشغيل policy-env-example قبل هذا السطر (يجب أن يكون ناجحاً).')
console.log('')
const gv = ghVersion()
if (!gv) {
  console.log('المرحلة 1 (GitHub): GitHub CLI غير متاح — ثبّت https://cli.github.com/ ثم: gh auth login')
  console.log('            ثم: make github-branch-protection-status')
} else if (!ghOk()) {
  console.log('المرحلة 1 (GitHub):', gv, '— نفّذ: gh auth login')
  console.log('            ثم: make github-branch-protection-status')
} else {
  console.log('المرحلة 1 (GitHub):', gv, '— مسجّل. نفّذ: make github-branch-protection-status')
}
console.log('            المرجع: docs/GitHub_Branch_Protection_Setup.md')
console.log('')
console.log('المرحلة 2: أبلغ الفريق أن المرجع هو docs/Execution_Order_Asas_Pro.md + السياسات المرتبطة.')
console.log('')
console.log('المرحلة 3: Staging حقيقي — docs/Staging_Execution_Now.md (بعد docker compose up -d: make staging-gate)')
console.log('')
console.log('المرحلة 4: الإنتاج بعد PASS على Staging — نفس القيود، بدون SAAS_ALLOW_TENANT_PLAN_CATALOG_EDIT=true')
console.log('')
console.log('المرحلة 5: CODEOWNERS على GitHub + make install-git-hooks (اختياري)')
console.log('')
console.log('بدون GNU Make على Windows: pwsh -File scripts/execution-order-local.ps1 أو powershell -File ...')
console.log('')
