<template>
  <div
    class="min-h-screen flex flex-col items-center justify-center bg-slate-100 p-4 dark:bg-slate-950"
    dir="rtl"
  >
    <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
      <h1 class="text-lg font-bold text-slate-900 dark:text-white">نسيت كلمة المرور</h1>
      <p class="mt-2 text-xs leading-relaxed text-slate-600 dark:text-slate-400">
        أدخل البريد المسجّل لدى منشأتك. إن وُجد حساب، ستصلك تعليمات على البريد (تحقق من البريد غير الهام أيضاً).
      </p>
      <form class="mt-5 space-y-4" @submit.prevent="submit">
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">البريد الإلكتروني</label>
          <input
            v-model="email"
            type="email"
            required
            autocomplete="email"
            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-slate-600 dark:bg-slate-900"
          >
        </div>
        <p v-if="message" class="text-xs text-emerald-700 dark:text-emerald-300">{{ message }}</p>
        <p v-if="err" class="text-xs text-red-600 dark:text-red-300">{{ err }}</p>
        <button
          type="submit"
          :disabled="loading"
          class="w-full rounded-xl bg-primary-600 py-2.5 text-sm font-bold text-white disabled:opacity-60"
        >
          {{ loading ? 'جارٍ الإرسال…' : 'إرسال رابط الاستعادة' }}
        </button>
      </form>
      <RouterLink to="/login" class="mt-4 inline-block text-xs text-primary-700 underline dark:text-primary-400">
        العودة لتسجيل الدخول
      </RouterLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import apiClient from '@/lib/apiClient'

const email = ref('')
const loading = ref(false)
const message = ref('')
const err = ref('')

async function submit(): Promise<void> {
  loading.value = true
  message.value = ''
  err.value = ''
  try {
    const { data } = await apiClient.post('/auth/forgot-password', { email: email.value.trim().toLowerCase() })
    message.value = String(data?.message ?? 'تم الطلب.')
  } catch (e: unknown) {
    const r = (e as { response?: { data?: { message?: string } } }).response
    err.value = String(r?.data?.message ?? 'تعذّر إرسال الطلب.')
  } finally {
    loading.value = false
  }
}
</script>
