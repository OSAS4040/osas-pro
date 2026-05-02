import type { RouteRecordRaw } from 'vue-router'

export const fleetPortalRoutes: RouteRecordRaw[] = [
  // ── Fleet Portal ──
  {
    path: '/fleet-portal',
    component: () => import('@/layouts/FleetLayout.vue'),
    meta: { requiresAuth: true, portal: 'fleet' },
    children: [
      { path: '',        name: 'fleet-portal',           component: () => import('@/views/fleet-portal/FleetPortalDashboardView.vue') },
      { path: 'new-order', name: 'fleet-portal.new-order', component: () => import('@/views/fleet-portal/FleetNewOrderView.vue') },
      { path: 'top-up',  name: 'fleet-portal.top-up',   component: () => import('@/views/fleet-portal/FleetTopUpView.vue') },
      { path: 'vehicles', name: 'fleet-portal.vehicles', component: () => import('@/views/fleet-portal/FleetVehiclesView.vue') },
      { path: 'orders',  name: 'fleet-portal.orders',   component: () => import('@/views/fleet-portal/FleetOrdersView.vue') },
    ],
  },
]
