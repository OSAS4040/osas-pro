<template>
  <div
    class="min-h-screen flex flex-col items-center justify-center bg-slate-100 p-4 dark:bg-slate-950"
    dir="rtl"
  >
    <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
      <h1 class="text-lg font-bold text-slate-900 dark:text-white">تعيين كلمة مرور جديدة</h1>
      <form v-if="tokenReady" class="mt-5 space-y-4" @submit.prevent="submit">
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">البريد الإلكتروني</label>
          <input
            v-model="email"
            type="email"
            required
            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-slate-600 dark:bg-slate-900"
          >
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">كلمة المرور الجديدة</label>
          <input
            v-model="password"
            type="password"
            required
            minlength="8"
            autocomplete="new-password"
            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-slate-600 dark:bg-slate-900"
          >
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">تأكيد كلمة المرور</label>
          <input
            v-model="passwordConfirmation"
            type="password"
            required
            minlength="8"
            autocomplete="new-password"
            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-slate-600 dark:bg-slate-900"
          >
        </div>
        <p v-if="err" class="text-xs text-red-600 dark:text-red-300">{{ err }}</p>
        <button
          type="submit"
          :disabled="loading"
          class="w-full rounded-xl bg-primary-600 py-2.5 text-sm font-bold text-white disabled:opacity-60"
        >
          {{ loading ? 'جارٍ الحفظ…' : 'حفظ كلمة المرور' }}
        </button>
      </form>
      <p v-else class="mt-4 text-xs text-amber-700 dark:text-amber-300">
        الرابط غير مكتمل. افتح الرابط من البريد أو اطلب رابطاً جديداً من «نسيت كلمة المرور».
      </p>
      <RouterLink to="/login" class="mt-4 inline-block text-xs text-primary-700 underline dark:text-primary-400">
        تسجيل الدخول
      </RouterLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import apiClient from '@/lib/apiClient'

const route = useRoute()
const router = useRouter()

const token = ref(String(route.query.token ?? ''))
const email = ref(String(route.query.email ?? ''))
const password = ref('')
const passwordConfirmation = ref('')
const loading = ref(false)
const err = ref('')

const tokenReady = computed(() => token.value.length > 8 && email.value.includes('@'))

async function submit(): Promise<void> {
  if (password.value !== passwordConfirmation.value) {
    err.value = 'كلمتا المرور غير متطابقتين.'
    return
  }
  loading.value = true
  err.value = ''
  try {
    await apiClient.post('/auth/reset-password', {
      token: token.value,
      email: email.value.trim().toLowerCase(),
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    await router.push({ path: '/login', query: { reset: 'ok' } })
  } catch (e: unknown) {
    const r = (e as { response?: { data?: { message?: string } } }).response
    err.value = String(r?.data?.message ?? 'تعذّر إعادة التعيين.')
  } finally {
    loading.value = false
  }
}
</script>
