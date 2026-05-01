<template>
  <div class="min-h-screen flex flex-col items-center justify-center bg-emerald-50 p-4 dark:bg-emerald-950/20" dir="rtl">
    <div class="max-w-md rounded-2xl border border-emerald-200 bg-white p-6 text-center dark:border-emerald-800 dark:bg-slate-900">
      <h1 class="text-lg font-bold text-emerald-900 dark:text-emerald-100">تم توثيق حسابك</h1>
      <p class="mt-3 text-right text-sm leading-relaxed text-emerald-900/85 dark:text-emerald-100/85">
        أنت الآن على مسار <span class="font-semibold">هوية فردية</span> بدون شركة تشغيلية على المنصة.
        للوصول إلى لوحة الورشة والعمليات والمالية يلزم إنشاء شركة جديدة أو الدخول بحساب مرتبط بدعوة من شركة قائمة.
      </p>
      <div class="mt-5 space-y-2 text-right text-xs text-slate-600 dark:text-slate-400">
        <p class="font-medium text-slate-800 dark:text-slate-200">الخطوة التالية (اختر واحداً):</p>
        <ul class="list-disc pr-4 space-y-1">
          <li>إنشاء نشاط تجاري جديد عبر تسجيل الشركة القياسي.</li>
          <li>أو تسجيل الدخول بحساب ورشة إن وُجدت دعوة لك.</li>
        </ul>
      </div>
      <button
        type="button"
        class="mt-5 w-full rounded-xl bg-emerald-700 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-emerald-800"
        :disabled="busy"
        @click="goRegisterCompany"
      >
        {{ busy ? 'جارٍ التحضير…' : 'تسجيل شركة جديدة' }}
      </button>
      <button
        type="button"
        class="mt-2 w-full rounded-xl border border-slate-300 bg-white py-2.5 text-sm font-bold text-slate-800 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
        :disabled="busy"
        @click="goStaffLogin"
      >
        لدي حساب شركة — تسجيل الدخول
      </button>
      <button type="button" class="mt-3 w-full rounded-xl bg-slate-800 py-2 text-sm font-bold text-white" :disabled="busy" @click="logoutOnly">
        تسجيل الخروج فقط
      </button>
      <button
        type="button"
        data-testid="phone-onboarding-explore-landing"
        class="mt-3 w-full rounded-xl border border-emerald-300/80 bg-emerald-50/80 py-2 text-xs font-semibold text-emerald-900 hover:bg-emerald-100/90 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100 dark:hover:bg-emerald-900/50"
        :disabled="busy"
        @click="goExploreLanding"
      >
        استكشف الصفحة التعريفية للمنصة
      </button>
      <p class="mt-2 text-center text-[11px] text-slate-500 dark:text-slate-400">
        من الصفحة التعريفية يمكنك العودة هنا عبر شريط «مسار الجوال» أعلى الصفحة.
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const busy = ref(false)

async function logoutOnly(): Promise<void> {
  busy.value = true
  try {
    await auth.logout()
    await router.replace('/login')
  } finally {
    busy.value = false
  }
}

async function goRegisterCompany(): Promise<void> {
  busy.value = true
  try {
    await auth.logout()
    await router.replace('/register')
  } finally {
    busy.value = false
  }
}

async function goStaffLogin(): Promise<void> {
  busy.value = true
  try {
    await auth.logout()
    await router.replace('/login')
  } finally {
    busy.value = false
  }
}

function goExploreLanding(): void {
  void router.push({ name: 'landing' })
}
</script>
