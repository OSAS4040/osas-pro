<template>
  <div class="min-h-screen flex flex-col items-center justify-center bg-slate-100 p-4 dark:bg-slate-950" dir="rtl">
    <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
      <h1 class="text-lg font-bold">نوع الحساب</h1>
      <p class="mt-1 text-xs text-slate-500">اختر فرداً أو شركة.</p>
      <div class="mt-4 grid gap-2">
        <button type="button" class="rounded-xl border border-slate-200 py-3 text-sm font-semibold hover:bg-slate-50 dark:border-slate-600" @click="pick('individual')">فرد</button>
        <button type="button" class="rounded-xl border border-slate-200 py-3 text-sm font-semibold hover:bg-slate-50 dark:border-slate-600" @click="pick('company')">شركة</button>
      </div>
      <p v-if="err" class="mt-2 text-xs text-red-600">{{ err }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()
const err = ref('')

async function pick(t: 'individual' | 'company'): Promise<void> {
  err.value = ''
  try {
    await apiClient.post('/auth/complete-account-type', { account_type: t }, { skipGlobalErrorToast: true })
    await auth.fetchRegistrationFlow().catch(() => {})
    if (t === 'individual') await router.replace('/phone/onboarding/individual')
    else await router.replace('/phone/onboarding/company')
  } catch {
    err.value = 'تعذّر الحفظ.'
  }
}
</script>
