<template>
  <div class="min-h-screen flex flex-col items-center justify-center bg-slate-100 p-4 dark:bg-slate-950" :dir="i18n.dir">
    <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
      <h1 class="text-lg font-bold text-slate-900 dark:text-white">{{ i18n.t('phoneOtpVerify.title') }}</h1>
      <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
        {{ subtitle }}
      </p>
      <form class="mt-4 space-y-3" @submit.prevent="submit">
        <input
          v-model="otp"
          type="text"
          inputmode="numeric"
          maxlength="8"
          required
          :disabled="loading"
          class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-center font-mono text-lg tracking-widest dark:border-slate-600 dark:bg-slate-900 disabled:opacity-60"
        >
        <p
          v-if="err"
          role="alert"
          class="rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-200"
        >
          {{ err }}
        </p>
        <button
          type="submit"
          :disabled="loading"
          class="w-full rounded-xl bg-primary-600 py-2.5 text-sm font-bold text-white disabled:opacity-60"
        >
          {{ loading ? i18n.t('phoneOtpVerify.loading') : i18n.t('phoneOtpVerify.submit') }}
        </button>
      </form>
      <RouterLink :to="{ name: 'phone-auth' }" class="mt-4 inline-block text-xs underline text-primary-700 dark:text-primary-400">
        {{ i18n.t('phoneOtpVerify.backEdit') }}
      </RouterLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import axios from 'axios'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'
import { useI18nStore } from '@/stores/i18n'
import { resolvePostLoginTarget } from '@/utils/postLoginRedirect'
import { loginErrorMessageFromPayload } from '@/utils/loginApiErrors'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const i18n = useI18nStore()

const phone = ref('')
const otp = ref('')
const loading = ref(false)
const err = ref('')

const subtitle = computed(() =>
  i18n.t('phoneOtpVerify.subtitle').replace(/\{phone\}/g, phone.value || '—'),
)

onMounted(() => {
  phone.value = typeof route.query.phone === 'string' ? route.query.phone : ''
  if (!phone.value) void router.replace({ name: 'phone-auth' })
})

async function submit(): Promise<void> {
  loading.value = true
  err.value = ''
  try {
    const { data } = await apiClient.post(
      '/auth/phone/verify-otp',
      { phone: phone.value, otp: otp.value },
      { skipGlobalErrorToast: true },
    )
    auth.hydrateFromPhoneVerifyResponse(data as Record<string, unknown>)
    await auth.fetchRegistrationFlow().catch(() => {})
    const target = resolvePostLoginTarget({
      accountContext: auth.accountContext,
      registrationFlow: auth.registrationFlow,
      registrationStage: auth.user?.registration_stage,
      accountType: auth.user?.account_type,
      portalHomeFromRole: auth.portalHome,
      redirectQuery: route.query.redirect,
    })
    await router.replace(target)
  } catch (e: unknown) {
    if (axios.isAxiosError(e) && e.response) {
      const st = e.response.status
      const body = (e.response.data ?? {}) as Record<string, unknown>
      if (st === 403 || st === 401) {
        err.value = loginErrorMessageFromPayload(body, i18n.t)
        return
      }
      if (typeof body.message === 'string' && body.message.trim() !== '') {
        err.value = body.message
        return
      }
    }
    err.value = i18n.t('phoneOtpVerify.errGeneric')
  } finally {
    loading.value = false
  }
}
</script>
