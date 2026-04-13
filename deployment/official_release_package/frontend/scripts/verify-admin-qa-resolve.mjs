/**
 * يتحقق من تسجيل المسار /admin/qa في vue-router (نفس ترتيب المستوى الأعلى في router/index.ts).
 * تشغيل: node scripts/verify-admin-qa-resolve.mjs
 */
import { createRouter, createMemoryHistory } from 'vue-router'

const stub = { render: () => null }

const routes = [
  { path: '/login', name: 'login', component: stub, meta: { guest: true } },
  { path: '/fleet/login', redirect: '/login' },
  { path: '/customer/login', redirect: '/login' },
  {
    path: '/admin/qa',
    name: 'AdminQA',
    component: stub,
    meta: { requiresAuth: true, portal: 'staff', title: 'QA Dashboard' },
  },
  {
    path: '/admin',
    name: 'admin',
    component: stub,
    meta: { requiresAuth: true, portal: 'staff', requiresOwner: true },
  },
  {
    path: '/',
    component: stub,
    meta: { requiresAuth: true, portal: 'staff' },
    children: [{ path: '', name: 'dashboard', component: stub }],
  },
  {
    path: '/fleet-portal',
    component: stub,
    meta: { requiresAuth: true, portal: 'fleet' },
    children: [{ path: '', name: 'fleet-portal', component: stub }],
  },
  {
    path: '/customer',
    component: stub,
    meta: { requiresAuth: true, portal: 'customer' },
    children: [{ path: 'dashboard', name: 'customer.dashboard', component: stub }],
  },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: stub },
]

const router = createRouter({
  history: createMemoryHistory(),
  routes,
})

const r = router.resolve('/admin/qa')
const last = r.matched[r.matched.length - 1]
console.log(
  JSON.stringify(
    {
      matchedLength: r.matched.length,
      matchedNames: r.matched.map((m) => m.name),
      name: r.name,
      lastRecordName: last?.name ?? null,
      lastRecordPath: last?.path ?? null,
      fullPath: r.fullPath,
    },
    null,
    2,
  ),
)
