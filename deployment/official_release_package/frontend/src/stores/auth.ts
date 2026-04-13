import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import apiClient from '@/lib/apiClient'
import { useSubscriptionStore } from '@/stores/subscription'
import { useBusinessProfileStore } from '@/stores/businessProfile'

export interface User {
  id: number
  uuid: string
  name: string
  email: string
  role: string
  company_id: number
  branch_id: number | null
  /** Set for customer / fleet portal accounts — used for scoped wallet APIs */
  customer_id?: number | null
  company?: { plan?: string }
  permissions?: string[]
  subscription?: {
    plan: string | null
    status: string | null
    billing_state: string
    ends_at: string | null
    grace_ends_at: string | null
    grace_read_only: boolean
    max_branches?: number | null
    max_users?: number | null
  } | null
}

export type LoginOutcome =
  | { kind: 'success' }
  | { kind: 'otp_required'; challengeId: string; expiresIn: number; message: string }

export const useAuthStore = defineStore('auth', () => {
  const user  = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem('auth_token'))
  const permissions = ref<string[]>([])
  const ownerRoles = ['owner', 'admin', 'super_admin', 'super-admin', 'system_admin', 'system-admin']
  const managerRoles = [...ownerRoles, 'manager']
  const staffRoles = [...managerRoles, 'staff', 'cashier', 'technician', 'accountant', 'viewer']
  const roleKey = computed(() => String(user.value?.role ?? '').trim().toLowerCase())

  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const isOwner         = computed(() => ownerRoles.includes(roleKey.value))
  const isManager       = computed(() => managerRoles.includes(roleKey.value))

  const isFleet    = computed(() => ['fleet_contact', 'fleet_manager'].includes(roleKey.value))
  const isCustomer = computed(() => roleKey.value === 'customer')
  const isStaff    = computed(() => staffRoles.includes(roleKey.value))

  const portalHome  = computed(() => isFleet.value ? '/fleet-portal' : isCustomer.value ? '/customer' : '/')
  const portalLogin = computed(() => isFleet.value ? 'fleet-login' : isCustomer.value ? 'customer-login' : 'login')

  async function login(
    email: string,
    password: string,
    otpPayload?: { challengeId: string; otp: string },
  ): Promise<LoginOutcome> {
    const body: Record<string, string> = { email, password }
    if (otpPayload) {
      body.otp_challenge = otpPayload.challengeId
      body.otp = otpPayload.otp
    }
    const { data } = await apiClient.post('/auth/login', body, { skipGlobalErrorToast: true })
    if (data.otp_required === true) {
      return {
        kind: 'otp_required',
        challengeId: String(data.challenge_id ?? ''),
        expiresIn: Number(data.expires_in ?? 300),
        message: String(data.message ?? ''),
      }
    }
    token.value = data.token
    user.value = data.user
    permissions.value = Array.isArray(data.permissions) ? data.permissions : []
    localStorage.setItem('auth_token', data.token)
    const subStore = useSubscriptionStore()
    subStore.reset()
    subStore.hydrateFromAuthUser(data.user)
    useBusinessProfileStore().reset()
    return { kind: 'success' }
  }

  async function logout(): Promise<void> {
    await apiClient.post('/auth/logout', {}, { skipGlobalErrorToast: true }).catch(() => {})
    const sub = useSubscriptionStore()
    const profile = useBusinessProfileStore()
    sub.reset()
    profile.reset()
    token.value = null
    user.value  = null
    permissions.value = []
    localStorage.removeItem('auth_token')
  }

  async function fetchMe(): Promise<void> {
    if (!token.value) return
    const { data } = await apiClient.get('/auth/me')
    user.value = data.data
    permissions.value = Array.isArray(data.permissions) ? data.permissions : []
    useSubscriptionStore().hydrateFromAuthUser(data.data)
  }

  function hasPermission(permission: string): boolean {
    if (isOwner.value) return true
    // UI fallback: manager can always see reports tabs; API still enforces true authorization.
    if (isManager.value && permission.startsWith('reports.')) return true
    return permissions.value.includes(permission)
  }

  return {
    user, token, permissions,
    isAuthenticated, isOwner, isManager,
    isFleet, isCustomer, isStaff,
    portalHome, portalLogin,
    login, logout, fetchMe, hasPermission,
  }
})
