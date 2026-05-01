<template>
  <div class="min-h-screen flex flex-col items-center justify-center bg-slate-100 p-4 dark:bg-slate-950" dir="rtl">
    <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
      <h1 class="text-lg font-bold">الاسم الكامل</h1>
      <form class="mt-4 space-y-3" @submit.prevent="submit">
        <input v-model="fullName" type="text" required class="w-full rounded-xl border px-3 py-2 text-sm dark:bg-slate-900">
        <p v-if="err" class="text-xs text-red-600">{{ err }}</p>
        <button type="submit" :disabled="loading" class="w-full rounded-xl bg-primary-600 py-2 text-sm font-bold text-white disabled:opacity-60">{{ loading ? '…' : 'حفظ' }}</button>
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
const fullName = ref('')
const loading = ref(false)
const err = ref('')

async function submit(): Promise<void> {
  loading.value = true
  err.value = ''
  try {
    await apiClient.post('/auth/complete-individual-profile', { full_name: fullName.value.trim() }, { skipGlobalErrorToast: true })
    await auth.fetchMe().catch(() => {})
    await router.replace('/phone/onboarding/done')
  } catch {
    err.value = 'تعذّر الحفظ.'
  } finally {
    loading.value = false
  }
}
</script>
