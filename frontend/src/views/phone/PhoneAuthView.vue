<template>
  <div class="min-h-screen flex flex-col items-center justify-center bg-slate-100 p-4 dark:bg-slate-950" dir="rtl">
    <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
      <h1 class="text-lg font-bold text-slate-900 dark:text-white">دخول برقم الجوال</h1>
      <p class="mt-1 text-xs text-slate-500">سنرسل رمز تحقق قصيراً إلى جوالك.</p>
      <form class="mt-4 space-y-3" @submit.prevent="submit">
        <input
          v-model="phone"
          type="tel"
          required
          class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm dark:border-slate-600 dark:bg-slate-900"
          placeholder="05xxxxxxxx"
        >
        <p v-if="err" class="text-xs text-red-600">{{ err }}</p>
        <button type="submit" :disabled="loading" class="w-full rounded-xl bg-primary-600 py-2.5 text-sm font-bold text-white disabled:opacity-60">
          {{ loading ? 'جارٍ الإرسال…' : 'إرسال الرمز' }}
        </button>
      </form>
      <RouterLink to="/login" class="mt-4 inline-block text-xs text-primary-700 underline">الدخول بالبريد وكلمة المرور</RouterLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import apiClient from '@/lib/apiClient'

const router = useRouter()
const phone = ref('')
const loading = ref(false)
const err = ref('')

async function submit(): Promise<void> {
  loading.value = true
  err.value = ''
  try {
    await apiClient.post('/auth/phone/request-otp', { phone: phone.value.trim() }, { skipGlobalErrorToast: true })
    await router.push({ name: 'phone-verify', query: { phone: phone.value.trim() } })
  } catch {
    err.value = 'تعذّر إرسال الطلب.'
  } finally {
    loading.value = false
  }
}
</script>
