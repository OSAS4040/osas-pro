/** @vitest-environment happy-dom */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import apiClient from '@/lib/apiClient'
import { usePlatformNotifications } from '@/composables/platform-admin/usePlatformNotifications'

vi.mock('@/lib/apiClient', () => ({
  default: {
    get: vi.fn(),
  },
}))

describe('usePlatformNotifications', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    localStorage.clear()
  })

  it('builds unified notifications and counts unread/requires_action', async () => {
    vi.mocked(apiClient.get).mockResolvedValueOnce({
      data: {
        data: [
          {
            notification_id: 'n1',
            notification_type: 'support',
            title: 't1',
            summary: 's1',
            priority: 'high',
            status: 'new',
            created_at: '2026-04-16T10:00:00Z',
            is_read: false,
            target_type: 'support_ticket',
            target_id: '1',
            target_route: '/platform/support',
            target_params: { ticket: '1' },
            cta_label: 'فتح التذكرة',
            group_key: 'g1',
            requires_action: true,
          },
          {
            notification_id: 'n2',
            notification_type: 'operational',
            title: 't2',
            summary: 's2',
            priority: 'medium',
            status: 'new',
            created_at: '2026-04-16T09:00:00Z',
            is_read: false,
            target_type: 'platform_incident',
            target_id: 'ic-1',
            target_route: '/platform/intelligence/incidents/ic-1',
            target_params: {},
            cta_label: 'عرض الحادث',
            group_key: 'g2',
            requires_action: false,
          },
        ],
      },
    } as never)

    const s = usePlatformNotifications()
    await s.fetchNotifications({ limit: 20 })

    expect(s.items.value).toHaveLength(2)
    expect(s.unreadCount.value).toBe(2)
    expect(s.requiresActionCount.value).toBe(1)
    expect(s.attentionNow.value[0]?.notification_id).toBe('n1')
  })

  it('supports mark as read and mark all as read', async () => {
    vi.mocked(apiClient.get).mockResolvedValueOnce({
      data: {
        data: [
          {
            notification_id: 'n1',
            notification_type: 'support',
            title: 't1',
            summary: 's1',
            priority: 'high',
            status: 'new',
            created_at: '2026-04-16T10:00:00Z',
            is_read: false,
            target_type: 'support_ticket',
            target_id: '1',
            target_route: '/platform/support',
            target_params: { ticket: '1' },
            cta_label: 'فتح',
            group_key: null,
            requires_action: true,
          },
        ],
      },
    } as never)

    const s = usePlatformNotifications()
    await s.fetchNotifications()
    s.markAsRead('n1')
    expect(s.unreadCount.value).toBe(0)

    // add one more and mark all
    s.items.value.push({
      notification_id: 'n2',
      notification_type: 'support',
      title: 't2',
      summary: 's2',
      priority: 'low',
      status: 'new',
      created_at: '2026-04-16T11:00:00Z',
      is_read: false,
      target_type: 'support_ticket',
      target_id: '2',
      target_route: '/platform/support',
      target_params: { ticket: '2' },
      cta_label: 'فتح',
      group_key: null,
      requires_action: false,
    })
    expect(s.unreadCount.value).toBe(1)
    s.markAllAsRead()
    expect(s.unreadCount.value).toBe(0)
  })
})

