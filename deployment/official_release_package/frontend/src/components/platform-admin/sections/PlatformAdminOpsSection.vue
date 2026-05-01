<template>
  <section id="admin-section-ops" class="scroll-mt-32 mb-16">
    <div class="mb-4 border-b border-slate-200 pb-3 dark:border-slate-700">
      <h2 class="platform-admin-section-heading">التشغيل العام للمنصة</h2>
      <p class="mt-1 max-w-3xl text-xs leading-relaxed text-slate-600 dark:text-slate-400">
        مؤشرات قراءة فقط من الخادم (قاعدة البيانات، ذاكرة التخزين المؤقت، الطوابير). لا تُعرض أسرار أو تفاصيل بنية داخلية حساسة.
      </p>
    </div>
    <p class="mb-4 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
      للتحقق من الواجهات استخدم
      <button
        type="button"
        class="font-semibold text-primary-600 underline underline-offset-2 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:text-primary-400 dark:hover:text-primary-300"
        @click="emit('open-qa')"
      >
        اختبار النظام (ضمان الجودة)
      </button>
      ؛ ولقائمة أوامر
      <span class="font-mono text-xs text-slate-300" dir="ltr">artisan</span>
      الجاهزة للنسخ انتقل إلى
      <button type="button" class="font-medium text-primary-400 underline underline-offset-2 hover:text-primary-300" @click="emit('go-operator-commands')">
        أوامر المشغّل
      </button>
      .
    </p>
    <div v-if="opsLoading" class="space-y-2 py-8" aria-busy="true">
      <div class="mx-auto h-3 max-w-md animate-pulse rounded bg-slate-200 dark:bg-slate-700" />
      <p class="text-center text-sm text-slate-500 dark:text-slate-400">جاري تحميل ملخص التشغيل…</p>
    </div>
    <div v-else-if="opsError" class="rounded-xl border border-red-200 bg-red-50 px-4 py-6 text-center text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-100">
      تعذّر تحميل ملخص التشغيل من الخادم. تحقق من الاتصال وصلاحيات مشغّل المنصة، ثم اضغط «تحديث الملخص».
    </div>
    <div v-else-if="opsSummary === null && !opsLoading" class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-400">
      لا تتوفر بيانات تشغيل بعد — استخدم «تحديث الملخص» لجلب المؤشرات من الخادم.
    </div>
    <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
        <div class="mb-1 text-xs font-semibold text-slate-500 dark:text-slate-400">إجمالي المهام الفاشلة المتراكمة</div>
        <div class="font-mono text-2xl font-semibold tabular-nums text-slate-900 dark:text-white">{{ opsSummary?.failed_jobs_count ?? '—' }}</div>
      </div>
      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
        <div class="mb-1 text-xs font-semibold text-slate-500 dark:text-slate-400">ذاكرة التخزين المؤقت</div>
        <div class="text-lg font-bold" :class="opsSummary?.redis_ok ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
          {{ opsSummary?.redis_ok ? 'متصل' : 'غير متاح' }}
        </div>
      </div>
      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
        <div class="mb-1 text-xs font-semibold text-slate-500 dark:text-slate-400">قاعدة البيانات</div>
        <div class="text-lg font-bold" :class="opsSummary?.database_ok ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
          {{ opsSummary?.database_ok ? 'متصل' : 'خطأ' }}
        </div>
      </div>
      <div class="flex flex-col justify-center rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
        <button type="button" class="text-sm font-semibold text-primary-700 hover:text-primary-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:text-primary-400 dark:hover:text-primary-300" @click="emit('refresh-ops-summary')">تحديث الملخص</button>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
/**
 * Phase 3 — إغلاق: عرض قسم التشغيل العام فقط.
 * `loadOpsSummary` والتوجيه و`toast` عند فشل التحميل تبقى في الصفحة الأم.
 */
defineProps<{
  opsLoading: boolean
  opsError: boolean
  opsSummary: {
    failed_jobs_count: number | null
    redis_ok: boolean
    database_ok: boolean
    integrity_hint?: string
    queue_pending_count?: number | null
  } | null
}>()

const emit = defineEmits<{
  'go-operator-commands': []
  'open-qa': []
  'refresh-ops-summary': []
}>()
</script>
