<template>
  <div class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden transition-all duration-700"
    :style="{ background: activePortal.gradient }">

    <!-- Animated bg blobs -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
      <div class="absolute -top-32 -right-32 w-96 h-96 rounded-full opacity-20 blur-3xl transition-all duration-700"
        :style="{ background: activePortal.blob1 }"></div>
      <div class="absolute -bottom-32 -left-32 w-96 h-96 rounded-full opacity-20 blur-3xl transition-all duration-700"
        :style="{ background: activePortal.blob2 }"></div>
    </div>

    <div class="w-full max-w-md relative z-10">

      <!-- ══ Platform Brand ══ -->
      <div class="text-center mb-6">
        <div class="w-14 h-14 bg-white/15 rounded-2xl flex items-center justify-center mx-auto mb-3 backdrop-blur-sm border border-white/20">
          <WrenchScrewdriverIcon class="w-8 h-8 text-white" />
        </div>
        <h1 class="text-2xl font-black text-white tracking-tight">WorkshopOS</h1>
        <p class="text-white/60 text-xs mt-0.5">نظام إدارة الورشة الذكي</p>
      </div>

      <!-- ══ Portal Selector ══ -->
      <div class="grid grid-cols-3 gap-2 mb-4">
        <button v-for="p in portals" :key="p.id" @click="selectPortal(p.id)"
          class="flex flex-col items-center gap-1.5 py-3 px-2 rounded-xl border transition-all duration-300"
          :class="selectedPortal === p.id
            ? 'bg-white/20 border-white/50 shadow-lg scale-105 backdrop-blur-sm'
            : 'bg-white/5 border-white/15 hover:bg-white/10 hover:border-white/30'">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center transition-colors"
            :class="selectedPortal === p.id ? 'bg-white/25' : 'bg-white/10'">
            <component :is="p.icon" class="w-5 h-5 text-white" />
          </div>
          <span class="text-xs font-semibold text-white/90 text-center leading-tight">{{ p.label }}</span>
          <span v-if="selectedPortal === p.id"
            class="w-1.5 h-1.5 rounded-full bg-white"></span>
        </button>
      </div>

      <!-- ══ Login Card ══ -->
      <div class="bg-white/95 dark:bg-slate-900/95 backdrop-blur-xl rounded-2xl shadow-2xl overflow-hidden">

        <!-- Card Header -->
        <div class="px-7 pt-6 pb-4 border-b border-gray-100 dark:border-slate-700 transition-all duration-500"
          :style="{ background: `linear-gradient(135deg, ${activePortal.cardHeader})` }">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center"
              :style="{ background: 'rgba(255,255,255,0.2)' }">
              <component :is="activePortal.icon" class="w-5 h-5 text-white" />
            </div>
            <div>
              <h2 class="font-bold text-white text-sm">{{ activePortal.title }}</h2>
              <p class="text-white/70 text-xs">{{ activePortal.subtitle }}</p>
            </div>
          </div>
        </div>

        <!-- Form -->
        <form @submit.prevent="handleLogin" class="px-7 py-6 space-y-4">
          <!-- Demo hint -->
          <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800 rounded-xl px-3 py-2.5">
            <p class="text-xs font-medium text-blue-700 dark:text-blue-300 mb-1.5">بيانات تجريبية سريعة:</p>
            <div class="flex flex-wrap gap-1.5">
              <button v-for="d in activePortal.demos" :key="d.email" type="button"
                @click="fillDemo(d)"
                class="text-[10px] px-2 py-1 bg-white dark:bg-slate-800 border border-blue-200 dark:border-blue-700 rounded-lg text-blue-700 dark:text-blue-300 hover:bg-blue-50 dark:hover:bg-blue-900/40 transition-colors font-mono">
                {{ d.label }}
              </button>
            </div>
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1.5">البريد الإلكتروني</label>
            <input v-model="form.email" type="email" required autocomplete="email"
              class="w-full px-3.5 py-2.5 border border-gray-200 dark:border-slate-600 rounded-xl text-sm focus:outline-none focus:ring-2 dark:bg-slate-800 dark:text-slate-100 transition-all"
              :class="`focus:ring-[${activePortal.accent}]`"
              placeholder="email@example.com" />
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1.5">كلمة المرور</label>
            <div class="relative">
              <input v-model="form.password" :type="showPass ? 'text' : 'password'" required autocomplete="current-password"
                class="w-full px-3.5 py-2.5 border border-gray-200 dark:border-slate-600 rounded-xl text-sm focus:outline-none focus:ring-2 dark:bg-slate-800 dark:text-slate-100 transition-all"
                placeholder="••••••••" />
              <button type="button" @click="showPass = !showPass"
                class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                <EyeIcon v-if="!showPass" class="w-4 h-4" />
                <EyeSlashIcon v-else class="w-4 h-4" />
              </button>
            </div>
          </div>

          <!-- Error -->
          <Transition name="shake">
            <div v-if="error"
              class="flex items-center gap-2 text-sm text-red-600 bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-800 px-3.5 py-2.5 rounded-xl">
              <ExclamationCircleIcon class="w-4 h-4 flex-shrink-0" />
              {{ error }}
            </div>
          </Transition>

          <!-- Submit -->
          <button type="submit" :disabled="loading"
            class="w-full py-3 rounded-xl text-sm font-bold text-white transition-all duration-300 disabled:opacity-60 relative overflow-hidden"
            :style="{ background: `linear-gradient(135deg, ${activePortal.btnGradient})` }">
            <span v-if="!loading">دخول {{ activePortal.title }}</span>
            <span v-else class="flex items-center justify-center gap-2">
              <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
              جارٍ التحقق...
            </span>
          </button>
        </form>

        <!-- Footer info -->
        <div class="px-7 pb-5 text-center">
          <p class="text-xs text-gray-400 dark:text-slate-500">
            {{ activePortal.footerNote }}
          </p>
        </div>
      </div>

      <!-- Version tag -->
      <p class="text-center text-white/30 text-[10px] mt-4">WorkshopOS v2.5 · {{ new Date().getFullYear() }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import {
  WrenchScrewdriverIcon, TruckIcon, UserIcon,
  EyeIcon, EyeSlashIcon, ExclamationCircleIcon,
} from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'
import { useSubscriptionStore } from '@/stores/subscription'

const auth   = useAuthStore()
const sub    = useSubscriptionStore()
const router = useRouter()
const route  = useRoute()

// ── Portal Definitions ────────────────────────────────────────────────
const portals = [
  {
    id:       'staff',
    label:    'فريق العمل',
    title:    'بوابة فريق العمل',
    subtitle: 'مدير · كاشير · فني',
    icon:     WrenchScrewdriverIcon,
    gradient: 'linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%)',
    blob1:    '#3b82f6',
    blob2:    '#6366f1',
    cardHeader: '#1d4ed8, #1e40af',
    btnGradient: '#1d4ed8, #1e40af',
    accent:   '#3b82f6',
    footerNote: 'للمشاكل التقنية تواصل مع مدير النظام',
    demos: [
      { label: 'owner',   email: 'owner@demo.sa',   password: 'password' },
      { label: 'manager', email: 'manager@demo.sa',  password: 'password' },
      { label: 'cashier', email: 'cashier@demo.sa',  password: 'password' },
    ],
  },
  {
    id:       'fleet',
    label:    'إدارة الأسطول',
    title:    'بوابة الأسطول',
    subtitle: 'متابعة المركبات والحسابات',
    icon:     TruckIcon,
    gradient: 'linear-gradient(135deg, #134e4a 0%, #0f2f2e 100%)',
    blob1:    '#14b8a6',
    blob2:    '#10b981',
    cardHeader: '#0d9488, #0f766e',
    btnGradient: '#0d9488, #0f766e',
    accent:   '#14b8a6',
    footerNote: 'خاص بمديري ومسؤولي الأساطيل',
    demos: [
      { label: 'fleet.contact', email: 'fleet.contact@demo.sa', password: 'password' },
      { label: 'fleet.manager', email: 'fleet.manager@demo.sa', password: 'password' },
    ],
  },
  {
    id:       'customer',
    label:    'العملاء',
    title:    'بوابة العملاء',
    subtitle: 'تتبع سيارتك وفواتيرك',
    icon:     UserIcon,
    gradient: 'linear-gradient(135deg, #92400e 0%, #1c1008 100%)',
    blob1:    '#f59e0b',
    blob2:    '#ef4444',
    cardHeader: '#d97706, #b45309',
    btnGradient: '#d97706, #b45309',
    accent:   '#f59e0b',
    footerNote: 'للعملاء فقط — تواصل مع الورشة للتسجيل',
    demos: [
      { label: 'customer', email: 'customer@demo.sa', password: 'password' },
    ],
  },
]

const selectedPortal = ref('staff')
const activePortal   = computed(() => portals.find(p => p.id === selectedPortal.value)!)

// ── Form State ────────────────────────────────────────────────────────
const form     = ref({ email: '', password: '' })
const loading  = ref(false)
const error    = ref('')
const showPass = ref(false)

function selectPortal(id: string) {
  selectedPortal.value = id
  form.value = { email: '', password: '' }
  error.value = ''
}

function fillDemo(d: { email: string; password: string }) {
  form.value.email    = d.email
  form.value.password = d.password
  error.value = ''
}

// ── Login ─────────────────────────────────────────────────────────────
async function handleLogin() {
  loading.value = true
  error.value   = ''
  try {
    await auth.login(form.value.email, form.value.password)
    await sub.loadSubscription()
    const redirect = route.query.redirect as string | undefined
    await router.push(redirect ?? auth.portalHome)
  } catch (e: any) {
    error.value = e.response?.data?.message ?? 'البريد أو كلمة المرور غير صحيحة'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.shake-enter-active { animation: shake 0.4s ease; }
@keyframes shake {
  0%, 100% { transform: translateX(0); }
  20%       { transform: translateX(-6px); }
  40%       { transform: translateX(6px); }
  60%       { transform: translateX(-4px); }
  80%       { transform: translateX(4px); }
}
</style>
