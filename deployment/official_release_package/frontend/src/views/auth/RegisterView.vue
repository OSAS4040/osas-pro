<template>
  <div
    class="register-page flex min-h-screen flex-col bg-slate-100 transition-colors dark:bg-slate-950"
    :dir="i18n.dir"
  >
    <PlatformPromoBanner class="relative z-[12] w-full shrink-0" />
    <div class="relative flex flex-1 flex-col items-center justify-center overflow-hidden p-4 sm:p-6">
      <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
        <div
          class="absolute -top-40 -right-40 h-[28rem] w-[28rem] rounded-full bg-primary-500 opacity-[0.12] blur-3xl"
        />
        <div
          class="absolute -bottom-48 -left-32 h-[24rem] w-[24rem] rounded-full bg-teal-500 opacity-[0.08] blur-3xl"
        />
      </div>

      <div class="relative z-10 w-full max-w-md">
        <div class="mb-6 text-center">
          <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ rt('title') }}</h1>
          <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ rt('subtitle') }}</p>
        </div>

        <div
          class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:shadow-none"
        >
          <div class="border-b border-white/10 bg-gradient-to-l from-primary-800 to-primary-600 px-6 pb-4 pt-5">
            <h2 class="text-sm font-bold text-white">{{ rt('cardTitle') }}</h2>
            <p class="mt-1 text-xs text-white/80">{{ rt('cardSubtitle') }}</p>
          </div>

          <form class="space-y-3.5 px-6 py-6" @submit.prevent="submit">
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">{{ rt('companyName') }}</label>
              <input
                v-model="form.company_name"
                type="text"
                required
                maxlength="255"
                autocomplete="organization"
                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
              >
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">{{ rt('ownerName') }}</label>
              <input
                v-model="form.name"
                type="text"
                required
                maxlength="255"
                autocomplete="name"
                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
              >
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">
                {{ rt('phone') }} <span class="font-normal text-slate-400">{{ rt('phoneHint') }}</span>
              </label>
              <input
                v-model="form.phone"
                type="tel"
                required
                autocomplete="tel"
                inputmode="tel"
                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                placeholder="05xxxxxxxx"
              >
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">
                {{ rt('email') }} <span class="font-normal text-slate-400">{{ rt('emailHint') }}</span>
              </label>
              <input
                v-model="form.email"
                type="email"
                required
                autocomplete="email"
                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                placeholder="you@company.com"
              >
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">{{ rt('password') }}</label>
              <input
                v-model="form.password"
                type="password"
                required
                minlength="8"
                autocomplete="new-password"
                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
              >
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">{{ rt('passwordConfirm') }}</label>
              <input
                v-model="form.password_confirmation"
                type="password"
                required
                minlength="8"
                autocomplete="new-password"
                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
              >
            </div>

            <p v-if="err" role="alert" class="rounded-xl border border-red-100 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-200">
              {{ err }}
            </p>

            <button
              type="submit"
              :disabled="loading"
              class="flex min-h-11 w-full items-center justify-center rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition-all hover:bg-primary-700 disabled:opacity-60"
            >
              {{ loading ? rt('loading') : rt('submit') }}
            </button>
          </form>

          <div class="border-t border-gray-100 px-6 py-4 text-center text-[11px] dark:border-slate-700">
            <span class="text-slate-600 dark:text-slate-400">{{ rt('haveAccount') }}</span>
            <RouterLink to="/login" class="ms-1 font-semibold text-primary-700 underline dark:text-primary-400">
              {{ rt('goLogin') }}
            </RouterLink>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter, useRoute, RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useI18nStore } from '@/stores/i18n'
import { resolvePostLoginTarget } from '@/utils/postLoginRedirect'
import PlatformPromoBanner from '@/components/PlatformPromoBanner.vue'

const i18n = useI18nStore()
const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

function rt(key: string): string {
  return i18n.t(`register.${key}`)
}

const form = ref({
  company_name: '',
  name: '',
  phone: '',
  email: '',
  password: '',
  password_confirmation: '',
})
const loading = ref(false)
const err = ref('')

onMounted(() => {
  const refCode = typeof route.query.ref === 'string' ? route.query.ref.trim() : ''
  if (refCode) {
    try {
      sessionStorage.setItem('referral_code', refCode)
    } catch {
      /* ignore */
    }
  }
})

function flattenApiValidationErrors(errs: unknown): string[] {
  if (!errs || typeof errs !== 'object') return []
  const out: string[] = []
  const walk = (node: unknown): void => {
    if (node == null) return
    if (typeof node === 'string' && node.trim() !== '') {
      out.push(node)
      return
    }
    if (Array.isArray(node)) {
      for (const x of node) walk(x)
      return
    }
    if (typeof node === 'object') {
      for (const v of Object.values(node as Record<string, unknown>)) walk(v)
    }
  }
  for (const v of Object.values(errs as Record<string, unknown>)) walk(v)
  return out
}

async function submit(): Promise<void> {
  loading.value = true
  err.value = ''
  try {
    const tz = Intl.DateTimeFormat().resolvedOptions().timeZone
    await auth.register({
      company_name: form.value.company_name.trim(),
      name: form.value.name.trim(),
      phone: form.value.phone.trim(),
      email: form.value.email.trim().toLowerCase(),
      password: form.value.password,
      password_confirmation: form.value.password_confirmation,
      timezone: typeof tz === 'string' && tz !== '' ? tz : undefined,
    })
    const target = resolvePostLoginTarget({
      accountContext: auth.accountContext,
      registrationFlow: auth.registrationFlow,
      registrationStage: auth.user?.registration_stage,
      accountType: auth.user?.account_type,
      portalHomeFromRole: auth.portalHome,
      redirectQuery: route.query.redirect,
    })
    await router.push(target)
  } catch (e: unknown) {
    const res = (e as { response?: { status?: number; data?: { message?: string; errors?: unknown } } }).response
    if (!res) {
      err.value = rt('errNetwork')
      return
    }
    const fromFields = flattenApiValidationErrors(res.data?.errors)
    if (fromFields.length > 0) {
      err.value = fromFields.join(' — ')
      return
    }
    err.value = String(res.data?.message ?? rt('errGeneric'))
  } finally {
    loading.value = false
  }
}
</script>
