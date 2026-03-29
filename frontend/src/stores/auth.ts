import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import apiClient from '@/lib/apiClient'
import { useSubscriptionStore } from '@/stores/subscription'

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
}

export const useAuthStore = defineStore('auth', () => {
  const user  = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem('auth_token'))

  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const isOwner         = computed(() => user.value?.role === 'owner')
  const isManager       = computed(() => ['owner', 'manager'].includes(user.value?.role ?? ''))

  const isFleet    = computed(() => ['fleet_contact', 'fleet_manager'].includes(user.value?.role ?? ''))
  const isCustomer = computed(() => user.value?.role === 'customer')
  const isStaff    = computed(() => ['owner', 'manager', 'cashier', 'technician'].includes(user.value?.role ?? ''))

  const portalHome  = computed(() => isFleet.value ? '/fleet-portal' : isCustomer.value ? '/customer' : '/')
  const portalLogin = computed(() => isFleet.value ? 'fleet-login' : isCustomer.value ? 'customer-login' : 'login')

  async function login(email: string, password: string): Promise<void> {
    const { data } = await apiClient.post('/auth/login', { email, password })
    token.value = data.token
    user.value  = data.user
    localStorage.setItem('auth_token', data.token)
  }

  async function logout(): Promise<void> {
    await apiClient.post('/auth/logout').catch(() => {})
    const sub = useSubscriptionStore()
    sub.reset()
    token.value = null
    user.value  = null
    localStorage.removeItem('auth_token')
  }

  async function fetchMe(): Promise<void> {
    if (!token.value) return
    const { data } = await apiClient.get('/auth/me')
    user.value = data.data
  }

  return {
    user, token,
    isAuthenticated, isOwner, isManager,
    isFleet, isCustomer, isStaff,
    portalHome, portalLogin,
    login, logout, fetchMe,
  }
})
