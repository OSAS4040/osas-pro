<template>
  <div class="min-h-screen flex flex-col items-center justify-center bg-amber-50 p-4 dark:bg-amber-950/30" dir="rtl">
    <div class="max-w-lg rounded-2xl border border-amber-200 bg-white p-6 text-right shadow-sm dark:border-amber-800 dark:bg-slate-900">
      <div class="mx-auto mb-4 h-1.5 w-16 rounded-full bg-amber-300/80 dark:bg-amber-700/60" aria-hidden="true" />
      <h1 class="text-center text-lg font-bold text-amber-950 dark:text-amber-100">طلبك قيد مراجعة المنصّة</h1>
      <p class="mt-3 text-sm leading-relaxed text-amber-900/90 dark:text-amber-200/90">
        تم استلام بيانات تسجيل شركتك. <strong class="font-semibold">فريق اعتماد المنصّة فقط</strong> هو المخوّل بمراجعة الطلبات الجديدة؛ لا يمكن لمستخدمي مراكز الخدمة أو العملاء تسريع هذه المرحلة من لوحاتهم.
      </p>
      <ul class="mt-4 space-y-2 text-xs leading-relaxed text-slate-700 dark:text-slate-300">
        <li class="flex gap-2">
          <span class="mt-0.5 font-bold text-primary-600">•</span>
          <span>ستصلك إشعارات أو رسائل عندما يكتمل القرار أو عند الحاجة لمعلومات إضافية.</span>
        </li>
        <li class="flex gap-2">
          <span class="mt-0.5 font-bold text-primary-600">•</span>
          <span>لا تشارك كلمات المرور أو بيانات اعتماد مصرفية مع أي طرف عبر الهاتف أو المراسلات غير الرسمية.</span>
        </li>
        <li class="flex gap-2">
          <span class="mt-0.5 font-bold text-primary-600">•</span>
          <span>لا تعتمد على روابط أو جهات تدّعي «تفعيل الحساب فوراً» خارج القنوات المعتمدة للمنصّة.</span>
        </li>
      </ul>
      <p class="mt-4 rounded-lg bg-slate-50 px-3 py-2 text-[11px] text-slate-600 dark:bg-slate-800/80 dark:text-slate-400">
        لأسباب أمنية لا يعرض هذا الطلب رابطاً داخلياً لفرق المنصّة؛ المعالجة تتم عبر أنظمة الإدارة المعزولة عن بوابة العميل.
      </p>
      <button
        type="button"
        class="mt-6 w-full rounded-xl bg-slate-800 py-2.5 text-sm font-bold text-white transition hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600"
        @click="logout"
      >
        تسجيل الخروج
      </button>
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
