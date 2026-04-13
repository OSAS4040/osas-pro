<template>
  <div class="platform-login min-h-screen flex flex-col lg:flex-row">
    <!-- لوحة تعريفية — جانب أو أعلى على الجوال -->
    <div
      class="relative flex flex-col justify-between px-8 py-10 lg:w-[42%] lg:min-h-screen overflow-hidden
             bg-gradient-to-br from-slate-950 via-violet-950/90 to-slate-900 text-white"
    >
      <div class="absolute inset-0 opacity-[0.07] pointer-events-none"
           style="background-image: linear-gradient(rgba(255,255,255,.15) 1px, transparent 1px),
                  linear-gradient(90deg, rgba(255,255,255,.15) 1px, transparent 1px);
                  background-size: 48px 48px;"
      />
      <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-violet-500/25 blur-3xl pointer-events-none" />
      <div class="absolute bottom-0 left-0 w-96 h-96 rounded-full bg-emerald-500/10 blur-3xl pointer-events-none" />

      <header class="relative z-10 flex items-center gap-3">
        <div class="w-12 h-12 rounded-2xl bg-white/10 border border-white/15 flex items-center justify-center backdrop-blur-md">
          <ShieldCheckIcon class="w-7 h-7 text-emerald-300" />
        </div>
        <div>
          <p class="text-[10px] uppercase tracking-[0.2em] text-violet-200/80 font-semibold">Platform</p>
          <h1 class="text-lg font-bold tracking-tight">مشغّل المنصة</h1>
        </div>
      </header>

      <div class="relative z-10 my-10 lg:my-0 lg:flex-1 lg:flex lg:flex-col lg:justify-center space-y-5 max-w-md">
        <h2 class="text-2xl lg:text-3xl font-black leading-tight text-white">
          بوابة آمنة لإدارة الاشتراكات والباقات على مستوى المنصة
        </h2>
        <p class="text-sm text-slate-300/90 leading-relaxed">
          يُسمح بدخول لوحة المنصة لحسابات <strong class="text-white">بلا شركة</strong> مع
          <span class="font-mono text-xs text-violet-200" dir="ltr">is_platform_user</span>
          أو بريد/جوال مدرج في إعدادات المنصة، ودور <strong class="text-white">مالك (Owner)</strong> في النظام.
        </p>
        <ul class="text-xs text-slate-400 space-y-2 border-r-2 border-violet-500/40 pr-3">
          <li>لا تستخدم نفس كلمة مرور الإنتاج في بيئات تجريبية مكشوفة.</li>
          <li>لوحة الواجهة: <span class="font-mono text-slate-300" dir="ltr">/admin</span> بعد نجاح الدخول.</li>
        </ul>
      </div>

      <footer class="relative z-10 text-[10px] text-slate-500 flex flex-wrap gap-x-4 gap-y-1">
        <RouterLink to="/login" class="text-violet-300 hover:text-white transition-colors">← دخول فريق العمل والبوابات</RouterLink>
        <RouterLink to="/landing" class="text-slate-500 hover:text-slate-300 transition-colors">الصفحة التعريفية</RouterLink>
      </footer>
    </div>

    <!-- نموذج الدخول -->
    <div class="flex-1 flex items-center justify-center p-6 lg:p-12 bg-slate-100 dark:bg-slate-950">
      <div class="w-full max-w-md">
        <div
          class="rounded-3xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xl shadow-slate-900/5 dark:shadow-black/40 overflow-hidden"
        >
          <div class="px-8 pt-8 pb-2">
            <div class="flex items-center gap-2 text-violet-600 dark:text-violet-400 mb-1">
              <CpuChipIcon class="w-5 h-5" />
              <span class="text-xs font-bold uppercase tracking-wider">تسجيل الدخول</span>
            </div>
            <h3 class="text-xl font-bold text-slate-900 dark:text-white">
              {{ otpStep ? 'التحقق بخطوتين' : 'دخول مشغّل المنصة' }}
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
              {{ otpStep ? 'أدخل الرمز المرسل إلى بريدك' : 'حساب مالك مُصرَّح له على مستوى المنصة' }}
            </p>
          </div>

          <form class="px-8 pb-8 pt-4 space-y-4" @submit.prevent="handleLogin">
            <div
              v-if="showDemoHint && !otpStep"
              class="rounded-2xl border border-violet-200 dark:border-violet-900/60 bg-violet-50/90 dark:bg-violet-950/40 px-4 py-3 space-y-2"
            >
              <p class="text-[11px] font-semibold text-violet-900 dark:text-violet-200">بيانات تجريبية (محلي / بعد الـ seed)</p>
              <p class="text-[10px] text-violet-800/90 dark:text-violet-300/80 leading-relaxed">
                من seeder: <span class="font-mono" dir="ltr">DemoPlatformAdminSeeder</span> — يُشغَّل مع
                <span class="font-mono" dir="ltr">php artisan db:seed</span>
                أو
                <span class="font-mono" dir="ltr">php artisan db:seed --class=Database\\Seeders\\DemoPlatformAdminSeeder</span>.
                لا حاجة لـ <span class="font-mono" dir="ltr">SAAS_PLATFORM_ADMIN_EMAILS</span> لأن الحساب
                <span class="font-mono" dir="ltr">is_platform_user</span>.
              </p>
              <button
                type="button"
                class="w-full text-left text-[11px] py-2 px-3 rounded-xl bg-white dark:bg-slate-800 border border-violet-200 dark:border-violet-800 font-mono text-violet-800 dark:text-violet-200 hover:bg-violet-100/50 dark:hover:bg-violet-900/30 transition-colors"
                @click="fillDemo"
              >
                تعبئة: {{ DEMO_EMAIL }} / {{ DEMO_PASSWORD }}
              </button>
            </div>

            <div
              v-else-if="!otpStep"
              class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 px-4 py-3 text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed"
            >
              أدخل بريداً مدرجاً في <span class="font-mono" dir="ltr">SAAS_PLATFORM_ADMIN_EMAILS</span> مع حساب دور
              <strong>Owner</strong>. لا تُعرض بيانات تجريبية في هذا النشر.
            </div>

            <template v-if="!otpStep">
              <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1.5">البريد الإلكتروني</label>
                <input
                  v-model="form.email"
                  type="email"
                  required
                  autocomplete="email"
                  class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500"
                  placeholder="ops@yourcompany.com"
                />
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1.5">كلمة المرور</label>
                <div class="relative">
                  <input
                    v-model="form.password"
                    :type="showPass ? 'text' : 'password'"
                    required
                    autocomplete="current-password"
                    class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500 pl-11"
                    placeholder="••••••••"
                  />
                  <button
                    type="button"
                    class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300"
                    @click="showPass = !showPass"
                  >
                    <EyeIcon v-if="!showPass" class="w-4 h-4" />
                    <EyeSlashIcon v-else class="w-4 h-4" />
                  </button>
                </div>
              </div>
            </template>

            <template v-else>
              <p v-if="otpMessage" class="text-xs text-slate-600 dark:text-slate-300">{{ otpMessage }}</p>
              <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1.5">رمز التحقق</label>
                <input
                  v-model="otpCode"
                  type="text"
                  inputmode="numeric"
                  maxlength="8"
                  autocomplete="one-time-code"
                  class="w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-2.5 text-center font-mono text-lg tracking-widest text-slate-900 dark:text-slate-100"
                  placeholder="••••••"
                />
              </div>
              <button
                type="button"
                class="text-[11px] text-slate-500 underline"
                @click="clearOtpStep"
              >
                العودة لتعديل البريد وكلمة المرور
              </button>
            </template>

            <Transition name="shake">
              <div
                v-if="error"
                class="flex items-start gap-2 text-sm text-red-700 dark:text-red-300 bg-red-50 dark:bg-red-950/40 border border-red-100 dark:border-red-900 px-3.5 py-2.5 rounded-xl"
              >
                <ExclamationCircleIcon class="w-4 h-4 flex-shrink-0 mt-0.5" />
                <span class="break-words flex-1">{{ error }}</span>
              </div>
            </Transition>

            <button
              type="submit"
              :disabled="loading"
              class="w-full py-3 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 disabled:opacity-60 shadow-lg shadow-violet-900/20 transition-all"
            >
              <span v-if="!loading">{{ otpStep ? 'تأكيد الرمز والدخول' : 'دخول لوحة المنصة' }}</span>
              <span v-else class="flex items-center justify-center gap-2">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                جارٍ التحقق...
              </span>
            </button>
          </form>
        </div>

        <p class="text-center text-[10px] text-slate-400 dark:text-slate-600 mt-4">
          {{ appVersion }} · {{ new Date().getFullYear() }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { RouterLink } from 'vue-router'
import { ShieldCheckIcon, CpuChipIcon, EyeIcon, EyeSlashIcon, ExclamationCircleIcon } from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'
import { enabledPortals } from '@/config/portalAccess'
import { useI18nStore } from '@/stores/i18n'
import { resolvePostLoginTarget } from '@/utils/postLoginRedirect'
import { loginErrorMessageFromPayload } from '@/utils/loginApiErrors'

/** يطابق DemoPlatformAdminSeeder — للعرض التجريبي فقط */
const DEMO_EMAIL = 'platform-demo@osas.sa'
const DEMO_PASSWORD = '12345678'

/** عند 401 إذا لم يُرجع الـ API platform_demo_hint (مثلاً APP_ENV=production على الخادم) */
const FALLBACK_401_PLATFORM_SEED_HINT =
  'تأكد أن المستخدم موجود في نفس قاعدة بيانات الـ API: docker compose exec app php artisan db:seed --class=Database\\Seeders\\DemoPlatformAdminSeeder — إن كان APP_ENV=production فعّل APP_DEMO_PLATFORM_ADMIN=true في backend/.env ثم أعد الـ seed.'

const appVersion = __APP_VERSION__

const auth = useAuthStore()
const router = useRouter()
const i18n = useI18nStore()

const showDemoHint = computed(
  () => import.meta.env.DEV || import.meta.env.VITE_SHOW_PLATFORM_LOGIN_HINT === 'true',
)

const form = ref({ email: '', password: '' })
const loading = ref(false)
const error = ref('')
const showPass = ref(false)
const otpStep = ref(false)
const otpChallengeId = ref('')
const otpCode = ref('')
const otpMessage = ref('')

function stripZeroWidth(s: string): string {
  return s.replace(/[\u200B-\u200D\uFEFF]/g, '')
}

function fillDemo() {
  form.value.email = DEMO_EMAIL
  form.value.password = DEMO_PASSWORD
  error.value = ''
}

function clearOtpStep() {
  otpStep.value = false
  otpChallengeId.value = ''
  otpCode.value = ''
  otpMessage.value = ''
  error.value = ''
}

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

async function handleLogin() {
  loading.value = true
  error.value = ''
  const email = stripZeroWidth(form.value.email).trim().toLowerCase()
  const password = stripZeroWidth(form.value.password)

  try {
    if (otpStep.value) {
      const out = await auth.login(email, password, {
        challengeId: otpChallengeId.value,
        otp: otpCode.value.replace(/\D/g, ''),
      })
      if (out.kind === 'otp_required') {
        otpChallengeId.value = out.challengeId
        otpMessage.value = out.message
        error.value = 'يرجى إدخال الرمز الجديد المرسل إلى بريدك.'
        return
      }
    } else {
      const out = await auth.login(email, password)
      if (out.kind === 'otp_required') {
        otpStep.value = true
        otpChallengeId.value = out.challengeId
        otpMessage.value = out.message
        otpCode.value = ''
        return
      }
    }

    if (!auth.isPlatform) {
      await auth.logout()
      error.value =
        'هذا الحساب ليس مشغّل منصة (حساب بلا شركة ومفعّل is_platform_user). استخدم /login لفريق العمل، أو شغّل DemoPlatformAdminSeeder.'
      return
    }

    if (!enabledPortals.admin) {
      await auth.logout()
      error.value =
        'لوحة المنصة معطّلة في بناء الواجهة الحالي. راجع VITE_ENABLED_PORTALS (يجب تضمين admin).'
      return
    }

    const target = resolvePostLoginTarget({
      accountContext: auth.accountContext,
      registrationFlow: null,
      registrationStage: undefined,
      accountType: undefined,
      portalHomeFromRole: '/admin',
      redirectQuery: undefined,
    })
    await router.push(target)
  } catch (e: unknown) {
    const res = (e as { response?: { status?: number; data?: { message?: string; errors?: unknown } } }).response
    if (!res) {
      error.value = i18n.t('login.errNetwork')
      return
    }
    if (res.status === 401) {
      let msg = loginErrorMessageFromPayload((res.data ?? {}) as Record<string, unknown>, i18n.t)
      const apiHint = (res.data as { platform_demo_hint?: string } | undefined)?.platform_demo_hint
      if (typeof apiHint === 'string' && apiHint.trim() !== '') {
        msg += `\n\n${apiHint.trim()}`
      } else if (import.meta.env.DEV) {
        const dh = (res.data as { dev_hint?: Record<string, unknown> } | undefined)?.dev_hint
        const step = dh && typeof dh.platform_demo_next_step === 'string' ? dh.platform_demo_next_step.trim() : ''
        if (step !== '') {
          msg += `\n\n${step}`
        }
      }
      if (
        email === DEMO_EMAIL &&
        (typeof apiHint !== 'string' || apiHint.trim() === '') &&
        !msg.includes('db:seed')
      ) {
        msg += `\n\n${FALLBACK_401_PLATFORM_SEED_HINT}`
      }
      error.value = msg
      return
    }
    if (res.status === 402 && res.data?.message) {
      error.value = String(res.data.message)
      return
    }
    if (res.status === 403) {
      error.value = loginErrorMessageFromPayload((res.data ?? {}) as Record<string, unknown>, i18n.t)
      return
    }
    if (res.status === 503 && res.data?.message) {
      error.value = String(res.data.message)
      return
    }
    if (res.status && res.status >= 500) {
      const raw = res.data
      const msg =
        raw && typeof raw === 'object' && raw !== null && 'message' in raw
          ? String((raw as { message?: unknown }).message ?? '').trim()
          : ''
      error.value = msg || `خطأ خادم (${res.status}).`
      return
    }
    const fromFields = flattenApiValidationErrors(res.data?.errors)
    if (fromFields.length > 0) {
      error.value = fromFields.join(' — ')
      return
    }
    if (res.data?.message && String(res.data.message).trim() !== '') {
      error.value = String(res.data.message)
      return
    }
    error.value = `فشل الدخول (HTTP ${res.status ?? '?'})`
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.shake-enter-active {
  animation: shake 0.4s ease;
}
@keyframes shake {
  0%,
  100% {
    transform: translateX(0);
  }
  20% {
    transform: translateX(-6px);
  }
  40% {
    transform: translateX(6px);
  }
  60% {
    transform: translateX(-4px);
  }
  80% {
    transform: translateX(4px);
  }
}
</style>
