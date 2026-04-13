<template>
  <div class="min-h-screen flex flex-col items-center justify-center bg-slate-100 p-4 dark:bg-slate-950" dir="rtl">
    <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
      <h1 class="text-lg font-bold">بيانات الشركة</h1>
      <form class="mt-4 space-y-3" @submit.prevent="submit">
        <div>
          <label class="text-xs text-slate-500">اسم المنشأة</label>
          <input v-model="companyName" type="text" required class="mt-1 w-full rounded-xl border px-3 py-2 text-sm dark:bg-slate-900">
        </div>
        <div>
          <label class="text-xs text-slate-500">اسم المسؤول</label>
          <input v-model="contactName" type="text" required class="mt-1 w-full rounded-xl border px-3 py-2 text-sm dark:bg-slate-900">
        </div>
        <p v-if="err" class="text-xs text-red-600">{{ err }}</p>
        <button type="submit" :disabled="loading" class="w-full rounded-xl bg-primary-600 py-2 text-sm font-bold text-white disabled:opacity-60">{{ loading ? '…' : 'إرسال للمراجعة' }}</button>
      </form>
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
const companyName = ref('')
const contactName = ref('')
const loading = ref(false)
const err = ref('')

async function submit(): Promise<void> {
  loading.value = true
  err.value = ''
  try {
    await apiClient.post(
      '/auth/complete-company-profile',
      { company_name: companyName.value.trim(), contact_name: contactName.value.trim() },
      { skipGlobalErrorToast: true },
    )
    await auth.fetchRegistrationFlow().catch(() => {})
    await router.replace('/phone/onboarding/pending-review')
  } catch {
    err.value = 'تعذّر الإرسال.'
  } finally {
    loading.value = false
  }
}
</script>
