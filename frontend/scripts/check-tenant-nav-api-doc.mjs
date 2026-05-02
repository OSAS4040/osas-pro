/**
 * يتحقق من أن مسارات الراوتر (staff / fleet / customer) مذكورة في
 * docs/Tenant_Navigation_API_Map.md داخل كتل ```nav-doc-route-anchors*```.
 *
 * المصدر: `src/router/routes/{staffPortalRoutes,fleetPortalRoutes,customerPortalRoutes}.ts`
 * (استخراج نصّي مع دعم children المتداخلة).
 *
 * تشغيل من مجلد frontend: npm run docs:nav-api-check
 */
import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const root = path.join(__dirname, '..')
const routesDir = path.join(root, 'src', 'router', 'routes')
const docPath = path.join(root, '..', 'docs', 'Tenant_Navigation_API_Map.md')

function extractFirstChildrenArraySource(source, searchFrom) {
  const tail = source.slice(searchFrom)
  const cm = tail.match(/children:\s*\[/)
  if (!cm) return null
  const i = searchFrom + tail.indexOf(cm[0])
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

/** يدمج مساراً أباً مع مقطعاً نسبياً كما في Vue Router (nested routes). */
function joinPaths(parent, segment) {
  if (segment && segment.startsWith('/')) {
    return segment.replace(/\/+/g, '/')
  }
  if (!segment || segment === '') {
    if (!parent || parent === '/') return '/'
    return parent.replace(/\/+$/, '') || '/'
  }
  const base = !parent || parent === '/' ? '' : parent.replace(/\/+$/, '')
  if (!base) return `/${segment}`.replace(/\/+/g, '/')
  return `${base}/${segment}`.replace(/\/+/g, '/')
}

/** يقسم محتوى مصفوفة مسارات إلى كائنات `{ ... }` من المستوى الأعلى (مع تجاهل الأقواس داخل النصوص). */
function splitTopLevelRouteObjects(inner) {
  const out = []
  let depth = 0
  let start = -1
  let inString = null
  let escape = false
  for (let i = 0; i < inner.length; i++) {
    const ch = inner[i]
    if (inString) {
      if (escape) {
        escape = false
        continue
      }
      if (ch === '\\' && (inString === "'" || inString === '"')) {
        escape = true
        continue
      }
      if (ch === inString) {
        inString = null
      }
      continue
    }
    if (ch === "'" || ch === '"' || ch === '`') {
      inString = ch
      continue
    }
    if (ch === '{') {
      if (depth === 0) start = i
      depth++
    } else if (ch === '}') {
      depth--
      if (depth === 0 && start >= 0) {
        out.push(inner.slice(start, i + 1))
        start = -1
      }
    }
  }
  return out
}

function collectAllPaths(arrayInner, parentFullPath) {
  const paths = new Set()
  if (!arrayInner) return paths
  for (const block of splitTopLevelRouteObjects(arrayInner)) {
    const pathMatch = block.match(/path:\s*['"]([^'"]*)['"]/)
    const segment = pathMatch ? pathMatch[1] : ''
    const full = joinPaths(parentFullPath, segment)
    paths.add(full)
    const ci = block.indexOf('children:')
    if (ci !== -1) {
      const childInner = extractFirstChildrenArraySource(block, ci)
      if (childInner) {
        for (const p of collectAllPaths(childInner, full)) {
          paths.add(p)
        }
      }
    }
  }
  return paths
}

function pathsFromPortalRouteFile(filePath, portalMarker, nestedParentPrefix) {
  const src = fs.readFileSync(filePath, 'utf8').replace(/\r\n/g, '\n')
  const mark = src.indexOf(portalMarker)
  if (mark === -1) {
    console.error(`Marker not found in ${path.basename(filePath)}: ${portalMarker}`)
    return new Set()
  }
  const inner = extractFirstChildrenArraySource(src, mark)
  return collectAllPaths(inner, nestedParentPrefix)
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

const staffPaths = pathsFromPortalRouteFile(
  path.join(routesDir, 'staffPortalRoutes.ts'),
  "portal: 'staff'",
  '',
)

const fleetPaths = pathsFromPortalRouteFile(
  path.join(routesDir, 'fleetPortalRoutes.ts'),
  "portal: 'fleet'",
  '/fleet-portal',
)

const customerPaths = pathsFromPortalRouteFile(
  path.join(routesDir, 'customerPortalRoutes.ts'),
  "portal: 'customer'",
  '/customer',
)

const doc = fs.readFileSync(docPath, 'utf8').replace(/\r\n/g, '\n')

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
