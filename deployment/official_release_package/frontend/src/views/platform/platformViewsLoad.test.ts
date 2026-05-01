// @vitest-environment happy-dom
import { describe, expect, it } from 'vitest'

/**
 * يضمن أن كل مسار /platform/* يربط بملف View مستقل (Phase 2 — تحميل الوحدة).
 * بيئة happy-dom مطلوبة لأن استيراد مكوّنات Vue يحمّل runtime-dom (document.createElement).
 */
describe('platform admin route views', () => {
  it('dynamic-imports every platform view entry', async () => {
    const loaders = [
      () => import('./PlatformOverviewView.vue'),
      () => import('./PlatformGovernanceView.vue'),
      () => import('./PlatformOpsView.vue'),
      () => import('./PlatformCompaniesView.vue'),
      () => import('./PlatformCustomersView.vue'),
      () => import('./PlatformPlansView.vue'),
      () => import('./PlatformOperatorCommandsView.vue'),
      () => import('./PlatformAuditView.vue'),
      () => import('./PlatformFinanceView.vue'),
      () => import('./PlatformCancellationsView.vue'),
      () => import('./PlatformSupportView.vue'),
      () => import('./PlatformNotificationsView.vue'),
      () => import('./PlatformAnnouncementsView.vue'),
      () => import('./pricing/PlatformPricingModulePlaceholder.vue'),
      () => import('@/components/platform-admin/PlatformAdminOverviewHub.vue'),
      () => import('./PlatformAdminDashboardPage.vue'),
      () => import('@/components/platform-admin/sections/PlatformAdminOverviewSection.vue'),
      () => import('@/components/platform-admin/sections/PlatformAdminGovernanceSection.vue'),
      () => import('@/components/platform-admin/sections/PlatformAdminOpsSection.vue'),
      () => import('@/components/platform-admin/sections/PlatformAdminCompaniesSection.vue'),
      () => import('@/components/platform-admin/sections/PlatformAdminOperatorCommandsSection.vue'),
      () => import('@/components/platform-admin/sections/PlatformAdminAuditSection.vue'),
      () => import('@/components/platform-admin/sections/PlatformAdminFinanceSection.vue'),
      () => import('@/components/platform-admin/sections/PlatformAttentionNowSection.vue'),
    ]
    for (const load of loaders) {
      const mod = await load()
      expect(mod.default).toBeDefined()
    }
  }, 20_000)
})
