/**
 * يتحقق من أن مسارات الراوتر (staff / fleet / customer) مذكورة في
 * docs/Tenant_Navigation_API_Map.md داخل كتل ```nav-doc-route-anchors*```.
 *
 * تشغيل من مجلد frontend: npm run docs:nav-api-check
 */
import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const root = path.join(__dirname, '..')
const routerPath = path.join(root, 'src', 'router', 'index.ts')
const docPath = path.join(root, '..', 'docs', 'Tenant_Navigation_API_Map.md')

function extractFirstChildrenArraySource(source, searchFrom) {
  const i = source.indexOf('children: [', searchFrom)
  if (i === -1) return null
  const start = source.indexOf('[', i)
  let depth = 0
  for (let p = start; p < source.length; p++) {
    const ch = source[p]
    if (ch === '[') depth++
    else if (ch === ']') {
      depth--
      if (depth === 0) {
        return source.slice(start + 1, p)
      }
    }
  }
  return null
}

function pathsFromInner(inner, basePrefix) {
  const out = new Set()
  if (!inner) return out
  const base = basePrefix.replace(/\/$/, '') || '/'
  const re = /path:\s*['"]([^'"]*)['"]/g
  let m
  while ((m = re.exec(inner)) !== null) {
    const p = m[1]
    if (p.startsWith('/')) {
      out.add(p)
      continue
    }
    if (p === '') {
      out.add(base === '' ? '/' : base)
      continue
    }
    const full = `${base}/${p}`.replace(/\/+/g, '/')
    out.add(full)
  }
  return out
}

function readAnchors(doc, fenceId) {
  const re = new RegExp('```' + fenceId + '\\s*\\n([\\s\\S]*?)\\n```', 'm')
  const m = doc.match(re)
  if (!m) return null
  return new Set(
    m[1]
      .split('\n')
      .map((l) => l.trim())
      .filter((l) => l.length > 0 && !l.startsWith('#')),
  )
}

const router = fs.readFileSync(routerPath, 'utf8').replace(/\r\n/g, '\n')
const doc = fs.readFileSync(docPath, 'utf8').replace(/\r\n/g, '\n')

const staffMark = router.indexOf("meta: { requiresAuth: true, portal: 'staff' }")
const staffInner = extractFirstChildrenArraySource(router, staffMark)
const staffPaths = pathsFromInner(staffInner, '/')

const fleetMark = router.indexOf("meta: { requiresAuth: true, portal: 'fleet' }")
const fleetInner = extractFirstChildrenArraySource(router, fleetMark)
const fleetPaths = pathsFromInner(fleetInner, '/fleet-portal')

const customerMark = router.indexOf("meta: { requiresAuth: true, portal: 'customer' }")
const customerInner = extractFirstChildrenArraySource(router, customerMark)
const customerPaths = pathsFromInner(customerInner, '/customer')

const anchorsStaff = readAnchors(doc, 'nav-doc-route-anchors')
const anchorsFleet = readAnchors(doc, 'nav-doc-route-anchors-fleet-portal')
const anchorsCustomer = readAnchors(doc, 'nav-doc-route-anchors-customer')

let failed = false
function check(set, anchors, label) {
  if (!anchors) {
    console.error(`Missing fenced block \`\`\`... for ${label}`)
    failed = true
    return
  }
  const missing = [...set].filter((p) => !anchors.has(p)).sort()
  const extra = [...anchors].filter((p) => !set.has(p)).sort()
  if (missing.length || extra.length) {
    console.error(`\n=== ${label} mismatch ===`)
    if (missing.length) console.error('In router but not in doc:\n', missing.join('\n'))
    if (extra.length) console.error('In doc but not in router:\n', extra.join('\n'))
    failed = true
  } else {
    console.log(`${label}: OK (${set.size} routes)`)
  }
}

if (process.argv.includes('--dump')) {
  console.log('--- staff ---\n' + [...staffPaths].sort().join('\n'))
  console.log('\n--- fleet ---\n' + [...fleetPaths].sort().join('\n'))
  console.log('\n--- customer ---\n' + [...customerPaths].sort().join('\n'))
  process.exit(0)
}

check(staffPaths, anchorsStaff, 'Staff (AppLayout)')
check(fleetPaths, anchorsFleet, 'Fleet portal')
check(customerPaths, anchorsCustomer, 'Customer portal')

if (failed) {
  console.error('\nUpdate docs/Tenant_Navigation_API_Map.md fenced blocks.')
  process.exit(1)
}
