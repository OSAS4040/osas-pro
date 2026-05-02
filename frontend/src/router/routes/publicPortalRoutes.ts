import type { RouteRecordRaw } from 'vue-router'

export const publicPortalRoutes: RouteRecordRaw[] = [
  {
    path: '/landing',
    name: 'landing',
    component: () => import('@/views/marketing/LandingView.vue'),
    alias: ['/asas-pro', '/asaspro'],
    meta: { publicPage: true /** صفحة عامة — لا يوجد حارس في beforeEach يعتمد على هذا المفتاح حالياً */ },
  },
  {
    path: '/v/:token',
    name: 'vehicle-identity-public',
    component: () => import('@/views/public/VehicleIdentityPublicView.vue'),
    meta: { publicPage: true },
  },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@/views/NotFoundView.vue') },
]
