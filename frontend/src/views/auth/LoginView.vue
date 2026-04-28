<template>
  <div
    class="login-page flex min-h-screen flex-col bg-slate-100 transition-colors dark:bg-slate-950"
    :dir="i18n.dir"
  >
    <PlatformPromoBanner class="relative z-[12] w-full shrink-0" />
    <div
      class="relative flex flex-1 flex-col items-center justify-center overflow-hidden p-4 sm:p-6"
    >
      <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
        <div
          class="absolute -top-40 -right-40 h-[28rem] w-[28rem] rounded-full bg-primary-500 opacity-[0.14] blur-3xl"
        />
        <div
          class="absolute -bottom-48 -left-32 h-[24rem] w-[24rem] rounded-full bg-teal-500 opacity-[0.1] blur-3xl"
        />
        <div
          class="absolute top-20 left-1/2 h-40 w-40 -translate-x-1/2 rounded-full bg-indigo-500 opacity-[0.08] blur-3xl"
        />
        <div
          class="absolute inset-0 opacity-[0.35] dark:opacity-[0.12]"
          style="background-image: linear-gradient(rgba(15, 118, 110, 0.06) 1px, transparent 1px),
          linear-gradient(90deg, rgba(15, 118, 110, 0.06) 1px, transparent 1px);
          background-size: 56px 56px;"
        />
      </div>

      <div class="relative z-10 mx-auto w-full max-w-[560px] login-page-unified-v2">
        <div
          v-if="isDevBuild"
          class="mb-4 rounded-xl border border-amber-300/90 bg-amber-50 px-3 py-2.5 text-center text-[10px] leading-relaxed text-amber-950 dark:border-amber-700 dark:bg-amber-950/50 dark:text-amber-50"
          role="status"
        >
          <strong>{{ lt('devModeTitle') }}</strong>
          <!-- eslint-disable-next-line vue/no-v-html -- locale-controlled markup -->
          <span class="block [&_code]:text-[10px]" v-html="lt('devBuild')" />
        </div>
        <div class="mb-5 text-center">
          <h1 class="text-3xl font-black tracking-tight text-slate-900 dark:text-slate-100">{{ portalHeroTitle }}</h1>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ portalHeroTagline }}</p>
          <p v-if="isUnifiedLoginRoute" class="mt-2 text-[11px] leading-relaxed text-slate-600 dark:text-slate-300">
            {{ lt('unifiedIntro') }}
            <RouterLink
              to="/register"
              class="ms-1 font-semibold text-primary-700 underline underline-offset-2 dark:text-primary-400"
            >
              {{ lt('linkRegister') }}
            </RouterLink>
            <span class="text-slate-400"> · </span>
            <RouterLink
              to="/phone"
              class="ms-1 font-semibold text-primary-700 underline underline-offset-2 dark:text-primary-400"
            >
              {{ lt('linkPhoneOtp') }}
            </RouterLink>
          </p>
          <p v-else class="mt-2 text-[11px] leading-relaxed text-slate-600 dark:text-slate-300">
            {{ portalHeaderHint }}
          </p>
          <div class="mt-3 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-[11px]">
            <RouterLink
              to="/landing"
              class="text-primary-700 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300 underline underline-offset-2"
            >
              {{ lt('linkLanding') }}
            </RouterLink>
            <span class="text-slate-300 dark:text-slate-600" aria-hidden="true">·</span>
            <RouterLink
              :to="isUnifiedLoginRoute ? '/platform/login' : '/login'"
              class="text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 underline underline-offset-2"
            >
              {{ isUnifiedLoginRoute ? lt('linkPlatformAdmin') : 'الدخول الموحد' }}
            </RouterLink>
          </div>
          <div
            v-if="isUnifiedLoginRoute"
            class="mt-3 grid w-full grid-cols-1 gap-2 sm:grid-cols-3"
          >
            <RouterLink
              to="/platform/login"
              class="rounded-xl border border-slate-200 bg-white/85 px-3 py-2 text-center text-[11px] font-semibold text-slate-700 transition-colors hover:border-primary-300 hover:bg-primary-50 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-200 dark:hover:border-primary-700 dark:hover:bg-primary-950/30"
            >
              إدارة المنصة
            </RouterLink>
            <RouterLink
              to="/customer/login"
              class="rounded-xl border border-slate-200 bg-white/85 px-3 py-2 text-center text-[11px] font-semibold text-slate-700 transition-colors hover:border-primary-300 hover:bg-primary-50 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-200 dark:hover:border-primary-700 dark:hover:bg-primary-950/30"
            >
              عميل
            </RouterLink>
            <RouterLink
              to="/staff/login"
              class="rounded-xl border border-slate-200 bg-white/85 px-3 py-2 text-center text-[11px] font-semibold text-slate-700 transition-colors hover:border-primary-300 hover:bg-primary-50 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-200 dark:hover:border-primary-700 dark:hover:bg-primary-950/30"
            >
              مزود خدمة
            </RouterLink>
          </div>
          <div class="mt-4 flex flex-col items-center gap-2">
            <span class="text-[10px] font-medium text-slate-500 dark:text-slate-400">{{ lt('langLabel') }}</span>
            <div class="flex max-w-full flex-wrap justify-center gap-1 rounded-xl border border-slate-200 bg-white/90 p-1 dark:border-slate-700 dark:bg-slate-900/70">
              <button
                v-for="lang in i18n.SUPPORTED_LANGUAGES"
                :key="lang.code"
                type="button"
                class="rounded-lg px-2 py-1 text-[10px] font-semibold transition-colors"
                :class="
                  i18n.currentLang === lang.code
                    ? 'bg-primary-600 text-white shadow-sm'
                    : 'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700/80'
                "
                @click="i18n.setLang(lang.code)"
              >
                {{ lang.flag }} {{ lang.label }}
              </button>
            </div>
          </div>
        </div>

        <div class="mb-4 grid grid-cols-3 gap-2">
          <div class="rounded-xl border border-slate-200 bg-white/85 px-2 py-2 text-center dark:border-slate-700 dark:bg-slate-900/60">
            <p class="text-[10px] font-bold text-slate-700 dark:text-slate-200">دخول آمن</p>
            <p class="mt-0.5 text-[10px] text-slate-500 dark:text-slate-400">تحقق ثنائي</p>
          </div>
          <div class="rounded-xl border border-slate-200 bg-white/85 px-2 py-2 text-center dark:border-slate-700 dark:bg-slate-900/60">
            <p class="text-[10px] font-bold text-slate-700 dark:text-slate-200">سريع</p>
            <p class="mt-0.5 text-[10px] text-slate-500 dark:text-slate-400">خطوتين فقط</p>
          </div>
          <div class="rounded-xl border border-slate-200 bg-white/85 px-2 py-2 text-center dark:border-slate-700 dark:bg-slate-900/60">
            <p class="text-[10px] font-bold text-slate-700 dark:text-slate-200">{{ portalFeatureTitle }}</p>
            <p class="mt-0.5 text-[10px] text-slate-500 dark:text-slate-400">{{ portalFeatureHint }}</p>
          </div>
        </div>

        <div
          v-if="portalDisabledNotice"
          class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-center text-xs leading-relaxed text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100"
        >
          {{ portalDisabledNotice }}
        </div>

        <p
          v-if="resetOkBanner"
          class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-center text-xs text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-100"
        >
          {{ lt('resetPasswordOk') }}
        </p>

        <div class="overflow-hidden rounded-3xl border border-gray-200/90 bg-white/95 shadow-2xl shadow-slate-900/10 backdrop-blur-sm dark:border-slate-700 dark:bg-slate-800/95 dark:shadow-black/35">
          <div class="border-b border-white/10 px-6 pb-4 pt-5" :class="portalCardHeaderClass">
            <div>
              <h2 class="text-sm font-bold text-white">
                {{ otpStep ? lt('cardTitleOtp') : portalCardTitle }}
              </h2>
              <p class="text-xs text-white/80">
                {{ otpStep ? lt('cardSubtitleOtp') : portalCardSubtitle }}
              </p>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-2 text-[11px]">
              <div
                class="rounded-xl border px-2.5 py-1.5 text-center transition-colors"
                :class="!otpStep ? 'border-white/40 bg-white/15 text-white' : 'border-white/20 bg-transparent text-white/75'"
              >
                1) بيانات الدخول
              </div>
              <div
                class="rounded-xl border px-2.5 py-1.5 text-center transition-colors"
                :class="otpStep ? 'border-white/40 bg-white/15 text-white' : 'border-white/20 bg-transparent text-white/75'"
              >
                2) رمز التحقق
              </div>
            </div>
          </div>

          <form class="space-y-4 px-6 py-6" @submit.prevent="handleLogin">
            <div
              class="rounded-xl border border-slate-200/80 bg-slate-50/80 px-3.5 py-2.5 text-[11px] text-slate-600 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-300"
              role="note"
            >
              <p class="font-semibold text-slate-700 dark:text-slate-200">
                {{ otpStep ? lt('cardTitleOtp') : lt('cardTitleUnified') }}
              </p>
              <p class="mt-0.5">
                {{ otpStep ? lt('otpStepHint') : lt('loginStepHint') }}
              </p>
            </div>

            <details
              v-if="showLoginDemo && !otpStep"
              class="group rounded-2xl border border-primary-100 bg-primary-50/80 dark:border-primary-900/40 dark:bg-primary-950/25"
            >
              <summary
                class="cursor-pointer list-none px-4 py-3 text-xs font-semibold text-primary-900 dark:text-primary-200 [&::-webkit-details-marker]:hidden"
              >
                <span class="flex items-center justify-between gap-2">
                  {{ lt('demoSummary') }}
                  <span class="text-[10px] font-normal text-primary-600/80 dark:text-primary-400/80 group-open:hidden">{{ lt('demoShow') }}</span>
                  <span class="hidden text-[10px] font-normal text-primary-600/80 group-open:inline">{{ lt('demoHide') }}</span>
                </span>
              </summary>
              <div class="space-y-2 border-t border-primary-100/80 px-4 pb-3 pt-2 dark:border-primary-900/40">
                <p class="text-[10px] leading-relaxed text-primary-800/90 dark:text-primary-300/90">
                  {{ lt('demoHintBefore') }}
                  <RouterLink to="/platform/login" class="font-medium underline">{{ lt('linkPlatformAdmin') }}</RouterLink>{{ lt('demoHintAfter') }}
                </p>
                <div class="flex flex-wrap gap-1.5">
                  <button
                    v-for="d in allDemos"
                    :key="d.email + d.label"
                    type="button"
                    class="rounded-lg border border-primary-200/80 bg-white px-2 py-1 text-[10px] font-mono text-primary-800 transition-colors hover:bg-primary-50 dark:border-primary-800 dark:bg-slate-900 dark:text-primary-200 dark:hover:bg-primary-950/50"
                    @click="fillDemo(d)"
                  >
                    {{ d.label }}
                  </button>
                </div>
              </div>
            </details>

            <template v-if="!otpStep">
              <div>
                <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400">
                  {{ lt('loginIdLabel') }} <span class="font-normal text-slate-400">{{ lt('loginIdHint') }}</span>
                </label>
                <input
                  ref="emailInputRef"
                  v-model="form.loginId"
                  type="text"
                  required
                  autocomplete="username"
                  inputmode="text"
                  class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-base text-slate-900 transition-all placeholder:text-slate-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/25 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 sm:text-sm"
                  :placeholder="lt('loginIdPlaceholder')"
                >
                <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                  {{ lt('loginIdHelper') }}
                </p>
              </div>

              <div>
                <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400">{{ lt('password') }}</label>
                <div class="relative">
                  <input
                    v-model="form.password"
                    :type="showPass ? 'text' : 'password'"
                    required
                    autocomplete="current-password"
                    class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 pe-11 text-base text-slate-900 transition-all focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/25 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 sm:text-sm"
                    placeholder="••••••••"
                  >
                  <button
                    type="button"
                    class="absolute end-3 top-1/2 -translate-y-1/2 text-slate-400 transition-colors hover:text-slate-600 dark:hover:text-slate-300"
                    @click="showPass = !showPass"
                  >
                    <EyeIcon v-if="!showPass" class="h-4 w-4" />
                    <EyeSlashIcon v-else class="h-4 w-4" />
                  </button>
                </div>
                <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                  {{ lt('passwordHelper') }}
                </p>
              </div>

              <label
                class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-slate-100 bg-slate-50/80 px-3 py-2.5 text-[11px] leading-snug text-slate-600 dark:border-slate-700 dark:bg-slate-900/30 dark:text-slate-300"
              >
                <input
                  v-model="rememberLoginId"
                  type="checkbox"
                  class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-primary-600 focus:ring-primary-500 dark:border-slate-600"
                >
                <span>{{ lt('rememberLoginIdLead') }}<strong class="font-semibold text-slate-700 dark:text-slate-200">{{ lt('rememberLoginIdNever') }}</strong>{{ lt('rememberLoginIdTail') }}</span>
              </label>

              <div class="flex flex-wrap items-center justify-between gap-2 text-[11px]">
                <RouterLink
                  to="/register"
                  class="text-primary-700 underline underline-offset-2 hover:text-primary-800 dark:text-primary-400"
                >
                  {{ lt('linkRegister') }}
                </RouterLink>
                <RouterLink
                  to="/forgot-password"
                  class="text-primary-700 underline underline-offset-2 hover:text-primary-800 dark:text-primary-400"
                >
                  {{ lt('forgotPassword') }}
                </RouterLink>
                <button
                  type="button"
                  class="text-slate-500 underline underline-offset-2 hover:text-slate-800 dark:text-slate-400"
                  @click="showUsernameHelp = true"
                >
                  {{ lt('forgotUsername') }}
                </button>
              </div>
            </template>

            <template v-else>
              <p v-if="otpMessage" class="text-xs text-slate-600 dark:text-slate-300">{{ otpMessage }}</p>
              <div>
                <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400">{{ lt('otpCode') }}</label>
                <input
                  ref="otpInputRef"
                  v-model="otpCode"
                  type="text"
                  inputmode="numeric"
                  maxlength="8"
                  autocomplete="one-time-code"
                  class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-center font-mono text-lg tracking-[0.35em] text-slate-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/25 dark:border-slate-600 dark:bg-slate-900"
                  placeholder="••••••"
                >
                <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                  {{ lt('otpHelper') }}
                </p>
              </div>
              <button
                type="button"
                class="text-[11px] text-slate-500 underline"
                @click="clearOtpStep"
              >
                {{ lt('otpBack') }}
              </button>
            </template>

            <Transition name="shake">
              <div
                v-if="error"
                role="alert"
                aria-live="polite"
                class="flex items-start gap-2 rounded-xl border border-red-100 bg-red-50 px-3.5 py-2.5 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-200"
              >
                <ExclamationCircleIcon class="mt-0.5 h-4 w-4 flex-shrink-0" aria-hidden="true" />
                <span class="flex-1 break-words whitespace-pre-line">{{ error }}</span>
              </div>
            </Transition>

            <button
              type="submit"
              :disabled="loading"
              class="flex min-h-12 w-full items-center justify-center rounded-xl bg-gradient-to-r from-primary-600 to-primary-700 px-4 py-3 text-sm font-extrabold text-white shadow-lg shadow-primary-900/20 transition-all hover:-translate-y-0.5 hover:from-primary-500 hover:to-primary-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 disabled:opacity-60 dark:focus-visible:ring-offset-slate-900"
            >
              <span v-if="!loading">{{ otpStep ? lt('submitOtp') : lt('submit') }}</span>
              <span v-else class="flex items-center justify-center gap-2">
                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                {{ lt('verifying') }}
              </span>
            </button>
          </form>

          <div class="border-t border-gray-100 px-6 py-4 dark:border-slate-700">
            <p class="text-[11px] font-semibold text-slate-700 dark:text-slate-200">{{ lt('supportTitle') }}</p>
            <ul class="mt-2 space-y-1.5 text-[11px] text-slate-600 dark:text-slate-400">
              <li v-if="support.email">
                <a :href="'mailto:' + support.email" class="text-primary-700 underline dark:text-primary-400">{{ support.email }}</a>
              </li>
              <li v-if="support.phone">
                <a :href="'tel:' + support.phone.replace(/\s/g, '')" class="underline">{{ support.phone }}</a>
              </li>
              <li v-if="support.waHref">
                <a :href="support.waHref" target="_blank" rel="noopener noreferrer" class="text-emerald-700 underline dark:text-emerald-400">
                  {{ lt('supportWhatsApp') }}
                </a>
              </li>
              <li v-if="!support.phone && !support.waHref && support.email === 'sales@asaspro.sa'">
                {{ lt('supportTechFallback') }}
              </li>
            </ul>
          </div>
        </div>

        <AppInstallHint class="mt-4" />

        <p class="mt-4 text-center text-[10px] text-slate-400 dark:text-slate-600">
          {{ displayAppName }} · v{{ appVersion }} · {{ new Date().getFullYear() }}
        </p>
      </div>

      <Teleport to="body">
        <div
          v-if="showUsernameHelp"
          class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
          role="dialog"
          aria-modal="true"
          @click.self="showUsernameHelp = false"
        >
          <div class="max-w-sm rounded-2xl bg-white p-5 shadow-xl dark:bg-slate-800" :dir="i18n.dir">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ lt('usernameHelpTitle') }}</h3>
            <!-- eslint-disable-next-line vue/no-v-html -- locale-controlled -->
            <p class="mt-2 text-xs leading-relaxed text-slate-600 dark:text-slate-300" v-html="lt('usernameHelpBodyHtml')" />
            <button
              type="button"
              class="mt-4 w-full rounded-xl bg-slate-100 py-2 text-xs font-semibold text-slate-800 dark:bg-slate-700 dark:text-slate-100"
              @click="showUsernameHelp = false"
            >
              {{ lt('usernameHelpOk') }}
            </button>
          </div>
        </div>
      </Teleport>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRouter, useRoute, RouterLink } from 'vue-router'
import { EyeIcon, EyeSlashIcon, ExclamationCircleIcon } from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'
import { useI18nStore } from '@/stores/i18n'
import { resolvePostLoginTarget } from '@/utils/postLoginRedirect'
import { loginErrorMessageFromPayload, normalizeApiMessageText } from '@/utils/loginApiErrors'
import { enabledPortals } from '@/config/portalAccess'
import { useSupportContact } from '@/composables/useSupportContact'
import AppInstallHint from '@/components/AppInstallHint.vue'
import PlatformPromoBanner from '@/components/PlatformPromoBanner.vue'

/** معرف الدخول (جوال أو بريد) محفوظ محلياً — يُرسل للخادم فقط عند تسجيل الدخول */
const REMEMBER_LOGIN_ID_KEY = 'asaspro_saved_login_id'

const auth = useAuthStore()
const i18n = useI18nStore()
const router = useRouter()
const route = useRoute()
const support = useSupportContact()

function lt(key: string): string {
  return i18n.t(`login.${key}`)
}

const appVersion = __APP_VERSION__
const displayAppName = computed(() => import.meta.env.VITE_APP_NAME?.trim() || lt('brand'))
const isDevBuild = import.meta.env.DEV
const props = withDefaults(
  defineProps<{
    portalVariant?: 'staff' | 'fleet' | 'customer'
  }>(),
  {
    portalVariant: 'staff',
  },
)

const loginPortalVariant = computed<'staff' | 'fleet' | 'customer'>(() => {
  if (props.portalVariant !== 'staff') return props.portalVariant
  if (route.name === 'fleet-login') return 'fleet'
  if (route.name === 'customer-login') return 'customer'
  return 'staff'
})
const isUnifiedLoginRoute = computed(() => loginPortalVariant.value === 'staff')
const portalHeroTitle = computed(() => {
  if (loginPortalVariant.value === 'fleet') return 'بوابة الأسطول'
  if (loginPortalVariant.value === 'customer') return 'بوابة العميل'
  return lt('brand')
})
const portalHeroTagline = computed(() => {
  if (loginPortalVariant.value === 'fleet') return 'دخول مخصص لمشرفي وموظفي الأساطيل.'
  if (loginPortalVariant.value === 'customer') return 'دخول مخصص لعملاء المنصة.'
  return lt('tagline')
})
const portalHeaderHint = computed(() => {
  if (loginPortalVariant.value === 'fleet') return 'تسجيل دخول مخصص لبوابة الأسطول.'
  if (loginPortalVariant.value === 'customer') return 'تسجيل دخول مخصص لبوابة العميل.'
  return ''
})
const portalFeatureTitle = computed(() => {
  if (loginPortalVariant.value === 'fleet') return 'أسطول'
  if (loginPortalVariant.value === 'customer') return 'عملاء'
  return 'موحّد'
})
const portalFeatureHint = computed(() => {
  if (loginPortalVariant.value === 'fleet') return 'بوابة تشغيل الأسطول'
  if (loginPortalVariant.value === 'customer') return 'بوابة خدمات العميل'
  return 'لكل البوابات'
})
const portalCardTitle = computed(() => {
  if (loginPortalVariant.value === 'fleet') return 'دخول بوابة الأسطول'
  if (loginPortalVariant.value === 'customer') return 'دخول بوابة العميل'
  return lt('cardTitleUnified')
})
const portalCardSubtitle = computed(() => {
  if (loginPortalVariant.value === 'fleet') return 'استخدم حساب الأسطول لتفعيل جلسة مخصصة.'
  if (loginPortalVariant.value === 'customer') return 'استخدم حساب العميل للوصول إلى خدماتك.'
  return lt('cardSubtitleUnified')
})
const portalCardHeaderClass = computed(() => {
  if (loginPortalVariant.value === 'fleet') return 'bg-gradient-to-l from-teal-800 via-teal-700 to-cyan-600'
  if (loginPortalVariant.value === 'customer') return 'bg-gradient-to-l from-amber-700 via-orange-600 to-amber-500'
  return 'bg-gradient-to-l from-primary-800 via-primary-700 to-primary-600'
})

const showLoginDemo = computed(
  () => isUnifiedLoginRoute.value && (import.meta.env.DEV || import.meta.env.VITE_SHOW_LOGIN_DEMO_HINT === 'true'),
)

const PORTAL_DEFS = [
  {
    id: 'staff',
    demos: [
      { label: 'admin (أسس برو)', email: 'admin@osas.sa', password: '12345678' },
      { label: 'owner', email: 'owner@demo.sa', password: 'password' },
      { label: 'manager', email: 'manager@demo.sa', password: 'password' },
      { label: 'cashier', email: 'cashier@demo.sa', password: 'password' },
      { label: 'tech', email: 'tech@demo.sa', password: 'password' },
      { label: 'simulation', email: 'simulation.owner@demo.local', password: 'SimulationDemo123!' },
    ],
  },
  {
    id: 'fleet',
    demos: [
      { label: 'fleet.contact', email: 'fleet.contact@demo.sa', password: 'password' },
      { label: 'fleet.manager', email: 'fleet.manager@demo.sa', password: 'password' },
    ],
  },
  {
    id: 'customer',
    demos: [{ label: 'customer', email: 'customer@demo.sa', password: 'password' }],
  },
] as const

const allDemos = computed(() => {
  const out: { label: string; email: string; password: string }[] = []
  for (const p of PORTAL_DEFS) {
    if (p.id === 'fleet' && !enabledPortals.fleet) continue
    if (p.id === 'customer' && !enabledPortals.customer) continue
    for (const d of p.demos) out.push(d)
  }
  return out
})

const portalDisabledNotice = computed(() => {
  void i18n.currentLang
  if (route.query.notice !== 'portal_disabled') return ''
  const p = String(route.query.portal || '')
  if (p === 'fleet') return i18n.t('login.portalFleetDisabled')
  if (p === 'customer') return i18n.t('login.portalCustomerDisabled')
  return i18n.t('login.portalGeneric')
})

const resetOkBanner = computed(() => route.query.reset === 'ok')

const form = ref({ loginId: '', password: '' })
const loading = ref(false)
const error = ref('')
const showPass = ref(false)
const otpStep = ref(false)
const otpChallengeId = ref('')
const otpCode = ref('')
const otpMessage = ref('')
const showUsernameHelp = ref(false)
const rememberLoginId = ref(false)
const emailInputRef = ref<HTMLInputElement | null>(null)
const otpInputRef = ref<HTMLInputElement | null>(null)

onMounted(() => {
  try {
    const saved = localStorage.getItem(REMEMBER_LOGIN_ID_KEY)
    if (saved && saved.trim() !== '') {
      form.value.loginId = saved
      rememberLoginId.value = true
    }
  } catch {
    /* وضع خاص للمتصفح */
  }
  void nextTick(() => {
    if (!otpStep.value) emailInputRef.value?.focus()
  })
})

watch(otpStep, async (on) => {
  await nextTick()
  if (on) otpInputRef.value?.focus()
  else emailInputRef.value?.focus()
})

watch(
  () => route.fullPath,
  () => {
    error.value = ''
  },
)

function fillDemo(d: { email: string; password: string }) {
  form.value.loginId = d.email
  form.value.password = d.password
  error.value = ''
}

function clearOtpStep() {
  otpStep.value = false
  otpChallengeId.value = ''
  otpCode.value = ''
  otpMessage.value = ''
  error.value = ''
  void nextTick(() => emailInputRef.value?.focus())
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

function stripZeroWidth(s: string): string {
  return s.replace(/[\u200B-\u200D\uFEFF]/g, '')
}

function normalizeLoginId(raw: string): string {
  const s = stripZeroWidth(raw).trim()
  if (s.includes('@')) {
    return s.toLowerCase()
  }

  return s
}

function normalizeLoginPassword(raw: string): string {
  return stripZeroWidth(raw)
}

/** يطابق DemoPlatformAdminSeeder — عند إدخاله من /login نُظهر توجيهاً خاصاً بدل التشخيص العام */
const PLATFORM_DEMO_EMAIL = 'platform-demo@osas.sa'

async function handleLogin() {
  loading.value = true
  error.value = ''
  const loginId = normalizeLoginId(form.value.loginId)
  const password = normalizeLoginPassword(form.value.password)
  try {
    if (otpStep.value) {
      const out = await auth.login(loginId, password, {
        challengeId: otpChallengeId.value,
        otp: otpCode.value.replace(/\D/g, ''),
      })
      if (out.kind === 'otp_required') {
        otpChallengeId.value = out.challengeId
        otpMessage.value = out.message
        error.value = i18n.t('login.errOtpRefresh')
        return
      }
    } else {
      const out = await auth.login(loginId, password)
      if (out.kind === 'otp_required') {
        otpStep.value = true
        otpChallengeId.value = out.challengeId
        otpMessage.value = out.message
        otpCode.value = ''
        return
      }
    }

    if (auth.isPhoneOnboarding) {
      await auth.fetchRegistrationFlow().catch(() => {})
    }

    const target = resolvePostLoginTarget({
      accountContext: auth.accountContext,
      registrationFlow: auth.registrationFlow,
      registrationStage: auth.user?.registration_stage,
      accountType: auth.user?.account_type,
      portalHomeFromRole: auth.portalHome,
      redirectQuery: route.query.redirect,
    })

    if (loginPortalVariant.value === 'fleet' && !auth.isFleet) {
      await auth.logout()
      error.value = 'هذه صفحة بوابة الأسطول فقط. استخدم صفحة الدخول الموحد.'
      return
    }
    if (loginPortalVariant.value === 'customer' && !auth.isCustomer) {
      await auth.logout()
      error.value = 'هذه صفحة بوابة العميل فقط. استخدم صفحة الدخول الموحد.'
      return
    }

    if (auth.isFleet && !enabledPortals.fleet) {
      await auth.logout()
      error.value = i18n.t('login.errFleetPortal')
      return
    }
    if (auth.isCustomer && !enabledPortals.customer) {
      await auth.logout()
      error.value = i18n.t('login.errCustomerPortal')
      return
    }

    try {
      if (rememberLoginId.value) {
        localStorage.setItem(REMEMBER_LOGIN_ID_KEY, loginId)
      } else {
        localStorage.removeItem(REMEMBER_LOGIN_ID_KEY)
      }
    } catch {
      /* تجاهل */
    }

    await router.push(target)
  } catch (e: unknown) {
    const res = (
      e as {
        response?: {
          status?: number
          data?: { message?: string; errors?: unknown; dev_hint?: unknown }
        }
      }
    ).response
    if (!res) {
      error.value = i18n.t('login.errNetwork')
      return
    }
    if (res.status === 401) {
      const payload = (res.data ?? {}) as Record<string, unknown>
      let msg = loginErrorMessageFromPayload(payload, i18n.t)
      const apiPlatformHintRaw = (res.data as { platform_demo_hint?: string } | undefined)?.platform_demo_hint
      const apiPlatformHint = typeof apiPlatformHintRaw === 'string'
        ? normalizeApiMessageText(apiPlatformHintRaw)
        : ''
      const isPlatformDemoAttempt =
        loginId.includes('@') && loginId.toLowerCase() === PLATFORM_DEMO_EMAIL

      if (apiPlatformHint !== '') {
        msg += '\n\n' + apiPlatformHint
      } else if (isPlatformDemoAttempt) {
        msg +=
          '\n\n' +
          'هذا البريد لمشغّل المنصة التجريبي: سجّل الدخول من صفحة /platform/login (وليس /login). أنشئ المستخدم على نفس خادم الـ API: php artisan db:seed --class=Database\\Seeders\\DemoPlatformAdminSeeder'
      } else if (import.meta.env.DEV) {
        msg += i18n.t('login.errBadCredentialsDevHint')
        const dh = res.data?.dev_hint as
          | {
              users_in_db?: number | null
              database_error?: boolean
              platform_demo_next_step?: string
            }
          | undefined
        if (dh?.database_error) {
          msg += ' ' + i18n.t('login.errDevDbUnreachable')
        } else if (typeof dh?.platform_demo_next_step === 'string' && dh.platform_demo_next_step.trim() !== '') {
          msg += '\n\n' + dh.platform_demo_next_step.trim()
        } else if (dh?.users_in_db === 0) {
          msg += ' ' + i18n.t('login.errDevNoUsers')
        } else if (typeof dh?.users_in_db === 'number' && dh.users_in_db > 0) {
          msg += ' ' + i18n.t('login.errDevUsersButRejected')
        }
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
    if (res.status === 422) {
      const m = String(res.data?.message ?? '')
      if (m.includes('جلسة التحقق') || m.includes('رمز التحقق')) {
        error.value = m
        return
      }
    }
    if (res.status === 429 && res.data?.message) {
      error.value = String(res.data.message)
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
      if (msg && msg !== 'Validation failed.') {
        error.value = msg
        return
      }
      error.value = i18n.t('login.errServer').replace(/\{status\}/g, String(res.status))
      return
    }

    const fromFields = flattenApiValidationErrors(res.data?.errors)
    if (fromFields.length > 0) {
      error.value = fromFields.join(' — ')
      return
    }

    if (res.data?.message && String(res.data.message).trim() !== '' && String(res.data.message) !== 'Validation failed.') {
      error.value = String(res.data.message)
      return
    }

    if (res.status === 422) {
      error.value = i18n.t('login.errValidatePassword')
      return
    }
    error.value = i18n.t('login.errHttp').replace(/\{status\}/g, String(res.status ?? ''))
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
