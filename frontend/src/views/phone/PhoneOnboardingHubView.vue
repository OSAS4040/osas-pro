<template>
  <div class="min-h-screen bg-slate-100 p-4 dark:bg-slate-950" dir="rtl">
    <div class="mx-auto max-w-md py-8">
      <p class="text-center text-sm text-slate-600 dark:text-slate-300">جارٍ توجيهك للخطوة المناسبة…</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { resolvePhoneOnboardingPath } from '@/utils/phoneOnboardingRedirect'

const router = useRouter()
const auth = useAuthStore()

onMounted(async () => {
  await auth.fetchRegistrationFlow().catch(() => {})
  const path = resolvePhoneOnboardingPath(
    auth.registrationFlow ?? undefined,
    auth.user?.registration_stage,
    auth.user?.account_type,
  )
  await router.replace(path)
})
</script>
