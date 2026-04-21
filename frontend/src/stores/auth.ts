import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import apiClient from '@/lib/apiClient'
import { useSubscriptionStore } from '@/stores/subscription'
import { useBusinessProfileStore } from '@/stores/businessProfile'
import { parseLoginAccountContext, type LoginAccountContextPayload } from '@/types/accountContext'
import type { NavVisibilityPolicy } from '@/config/navigationVisibility'

export interface User {
  id: number
  uuid: string
  name: string
  email: string | null
  role: string
  company_id: number | null
  branch_id: number | null
  account_type?: string | null
  registration_stage?: string | null
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
  /** من الـ API — مشغّل منصة مستقل */
  is_platform_user?: boolean
  /** Mirrored from account_context.principal_kind === platform_employee after login /me */
  is_platform?: boolean
  /** IAM role from account_context when platform */
  platform_role?: string | null
  navigation_visibility?: NavVisibilityPolicy
}

export type LoginOutcome =
  | { kind: 'success' }
  | { kind: 'otp_required'; challengeId: string; expiresIn: number; message: string }

export interface RegistrationFlowState {
  onboarding_active: boolean
  needs_account_type: boolean
  needs_basic_profile: boolean
  company_pending_review: boolean
  registration_stage?: string | null
  account_type?: string | null
}

export interface RegisterPayload {
  company_name: string
  name: string
  email: string
  phone: string
  password: string
  password_confirmation: string
  timezone?: string
}

export const useAuthStore = defineStore('auth', () => {
  const user  = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem('auth_token'))
  /**
   * يصبح true بعد أول حسم كامل للجلسة (بما في ذلك /auth/me عند وجود token)،
   * لمنع رسم القوائم على فرضيات قبل وصول account_context وصلاحيات المستخدم.
   */
  const sessionResolved = ref(!localStorage.getItem('auth_token'))
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
  const isPhoneOnboarding = computed(() => roleKey.value === 'phone_onboarding')

  const registrationFlow = ref<RegistrationFlowState | null>(null)
  const accountContext = ref<LoginAccountContextPayload | null>(null)

  const isPlatform = computed(() => {
    if (accountContext.value?.principal_kind === 'platform_employee') {
      return true
    }
    const u = user.value
    if (!u) {
      return false
    }
    /** مشغّل منصة مرتبط بشركة (هجين) — يُعرَف في الخلفية بـ is_platform_user حتى لو تأخّر account_context لإطار */
    if (Boolean(u.is_platform_user)) {
      return true
    }
    if (u.company_id != null) {
      return false
    }

    return false
  })

  function applyAccountContextAndPlatformFlag(raw: unknown): void {
    accountContext.value = parseLoginAccountContext(raw)
    const u = user.value
    if (!u) return
    const fromContext = accountContext.value?.principal_kind === 'platform_employee'
    const fromUser = u.company_id == null && Boolean(u.is_platform_user)
    const flag = fromContext || fromUser
    user.value = {
      ...u,
      is_platform: Boolean(flag),
      platform_role: accountContext.value?.platform_role ?? u.platform_role ?? null,
    }
  }

  const portalHome  = computed(() =>
    isPhoneOnboarding.value
      ? '/phone/onboarding'
      : isPlatform.value
        ? (typeof user.value?.company_id === 'number' && user.value.company_id > 0 ? '/' : '/admin')
        : isFleet.value
          ? '/fleet-portal'
          : isCustomer.value
            ? '/customer'
            : '/',
  )
  const portalLogin = computed(() => isFleet.value ? 'fleet-login' : isCustomer.value ? 'customer-login' : 'login')

  async function login(
    loginId: string,
    password: string,
    otpPayload?: { challengeId: string; otp: string },
  ): Promise<LoginOutcome> {
    const trimmed = loginId.trim()
    const body: Record<string, string> = { password }
    if (trimmed.includes('@')) {
      body.email = trimmed.toLowerCase()
    } else {
      body.identifier = trimmed
      body.device_name = 'spa-web'
      body.device_type = 'unknown'
    }
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
    applyAccountContextAndPlatformFlag(data.account_context)
    localStorage.setItem('auth_token', data.token)
    sessionResolved.value = true
    const subStore = useSubscriptionStore()
    subStore.reset()
    subStore.hydrateFromAuthUser(data.user)
    useBusinessProfileStore().reset()
    return { kind: 'success' }
  }

  async function register(payload: RegisterPayload): Promise<void> {
    const { data } = await apiClient.post('/auth/register', payload, { skipGlobalErrorToast: true })
    token.value = data.token
    user.value = data.user
    permissions.value = Array.isArray(data.permissions) ? data.permissions : []
    applyAccountContextAndPlatformFlag(data.account_context)
    localStorage.setItem('auth_token', data.token)
    sessionResolved.value = true
    const subStore = useSubscriptionStore()
    subStore.reset()
    subStore.hydrateFromAuthUser(data.user)
    useBusinessProfileStore().reset()
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
    registrationFlow.value = null
    accountContext.value = null
    localStorage.removeItem('auth_token')
    sessionResolved.value = true
  }

  async function fetchMe(): Promise<void> {
    if (!token.value) {
      sessionResolved.value = true
      return
    }
    try {
      const { data } = await apiClient.get('/auth/me', { skipGlobalErrorToast: true })
      user.value = data.data
      permissions.value = Array.isArray(data.permissions) ? data.permissions : []
      applyAccountContextAndPlatformFlag(data.account_context)
      useSubscriptionStore().hydrateFromAuthUser(data.data)
      if (String(data.data?.role ?? '').toLowerCase() === 'phone_onboarding') {
        await fetchRegistrationFlow().catch(() => {})
      }
    } catch (err: unknown) {
      const status = (err as { response?: { status?: number } })?.response?.status
      if (status === 401) {
        token.value = null
        user.value = null
        permissions.value = []
        accountContext.value = null
        localStorage.removeItem('auth_token')
      }
    } finally {
      sessionResolved.value = true
    }
  }

  async function fetchRegistrationFlow(): Promise<void> {
    if (!token.value) return
    const { data } = await apiClient.get('/auth/registration-status')
    registrationFlow.value = data.data as RegistrationFlowState
  }

  function hydrateFromPhoneVerifyResponse(data: Record<string, unknown>): void {
    token.value = String(data.token ?? '')
    user.value = data.user as User
    permissions.value = Array.isArray(data.permissions) ? (data.permissions as string[]) : []
    localStorage.setItem('auth_token', token.value)
    const subStore = useSubscriptionStore()
    subStore.reset()
    subStore.hydrateFromAuthUser(data.user as User)
    useBusinessProfileStore().reset()
    applyAccountContextAndPlatformFlag(data.account_context)
    sessionResolved.value = true
    registrationFlow.value = {
      onboarding_active: Boolean(data.onboarding_active),
      needs_account_type: Boolean(data.needs_account_type),
      needs_basic_profile: Boolean(data.needs_basic_profile),
      company_pending_review: Boolean(data.company_pending_review),
      registration_stage: (data.user as User)?.registration_stage ?? null,
      account_type: (data.user as User)?.account_type ?? null,
    }
  }

  function hasPermission(permission: string): boolean {
    if (isOwner.value) return true
    // UI fallback: manager can always see reports tabs; API still enforces true authorization.
    if (isManager.value && permission.startsWith('reports.')) return true
    return permissions.value.includes(permission)
  }

  return {
    user, token, permissions, registrationFlow, accountContext, sessionResolved,
    isAuthenticated, isOwner, isManager, isPlatform,
    isFleet, isCustomer, isStaff, isPhoneOnboarding,
    portalHome, portalLogin,
    login, register, logout, fetchMe, fetchRegistrationFlow, hydrateFromPhoneVerifyResponse, hasPermission,
  }
})
