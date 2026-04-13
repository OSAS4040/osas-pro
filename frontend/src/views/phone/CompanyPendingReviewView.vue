<template>
  <div class="min-h-screen flex flex-col items-center justify-center bg-amber-50 p-4 dark:bg-amber-950/30" dir="rtl">
    <div class="max-w-md rounded-2xl border border-amber-200 bg-white p-6 text-center dark:border-amber-800 dark:bg-slate-900">
      <h1 class="text-lg font-bold text-amber-900 dark:text-amber-100">قيد مراجعة المنصة</h1>
      <p class="mt-2 text-sm text-amber-800/90 dark:text-amber-200/90">
        تم استلام طلب تسجيل شركتك. فريق المنصة يراجع البيانات ويتواصل معك عند اكتمال المراجعة.
      </p>
      <button type="button" class="mt-6 w-full rounded-xl bg-slate-800 py-2 text-sm font-bold text-white dark:bg-slate-700" @click="logout">تسجيل الخروج</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
async function logout(): Promise<void> {
  await auth.logout()
  window.location.href = '/login'
}
</script>
