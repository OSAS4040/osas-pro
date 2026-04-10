/**
 * تحقق سياسة المستودع: أمثلة backend/.env* لا تُفعّل SAAS_ALLOW_TENANT_PLAN_CATALOG_EDIT=true
 * تشغيل: node scripts/check-policy-env-example.mjs
 */
import { readFileSync, existsSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const files = ['backend/.env.example', 'backend/.env.staging.example']

const bad = /\bSAAS_ALLOW_TENANT_PLAN_CATALOG_EDIT\s*=\s*true\b/

for (const rel of files) {
  const p = join(root, rel)
  if (!existsSync(p)) {
    console.error(`POLICY CHECK FAIL: missing ${rel}`)
    process.exit(1)
  }
  const text = readFileSync(p, 'utf8')
  for (const line of text.split(/\r?\n/)) {
    const t = line.trim()
    if (t.startsWith('#')) continue
    if (bad.test(line)) {
      console.error(`POLICY CHECK FAIL: ${rel} must not enable tenant plan catalog edit (found: ${t})`)
      process.exit(1)
    }
  }
}

console.log('POLICY CHECK OK: SAAS_ALLOW_TENANT_PLAN_CATALOG_EDIT is not true in env examples.')
