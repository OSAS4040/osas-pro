<template>
  <section
    id="admin-section-governance"
    class="scroll-mt-32 mb-16 rounded-2xl border border-slate-300/90 bg-gradient-to-br from-slate-50 via-white to-primary-50/50 p-4 shadow-sm dark:border-slate-600 dark:from-slate-900 dark:via-slate-900 dark:to-primary-900/20"
    dir="rtl"
    aria-labelledby="platform-governance-heading"
  >
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200/80 pb-3 dark:border-slate-700">
      <div>
        <p class="text-[10px] font-bold tracking-wide text-primary-600 dark:text-primary-400">سياق المنصة</p>
        <h2 id="platform-governance-heading" class="text-base font-semibold text-slate-900 dark:text-white sm:text-lg">
          الحوكمة والتحقق من البيئة
        </h2>
        <p class="mt-1 max-w-2xl text-[11px] leading-relaxed text-slate-600 dark:text-slate-400">
          هذه الصفحة لـ<strong class="text-slate-800 dark:text-slate-200">مشغّل المنصة</strong> وليست لوحة شركة أو فرع.
          البيانات التشغيلية للفواتير والعملاء التفصيلية تُدار من بوابة فريق العمل لكل مستأجر.
        </p>
      </div>
      <span
        class="shrink-0 rounded-full border border-primary-300/80 bg-primary-600/10 px-3 py-1 text-[10px] font-bold text-primary-800 dark:border-primary-700 dark:bg-primary-900/40 dark:text-primary-200"
      >
        قراءة تشغيلية + إدارة محكومة
      </span>
    </div>

    <dl class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <div class="rounded-xl border border-white/80 bg-white/90 p-3 shadow-sm dark:border-slate-700 dark:bg-slate-800/60">
        <dt class="text-[10px] font-semibold text-slate-500 dark:text-slate-400">المستخدم الحالي</dt>
        <dd class="mt-0.5 text-sm font-bold text-slate-900 dark:text-white">{{ user?.name ?? '—' }}</dd>
        <dd class="mt-1 font-mono text-[10px] text-slate-600 dark:text-slate-400" dir="ltr">{{ user?.email ?? '—' }}</dd>
      </div>
      <div class="rounded-xl border border-white/80 bg-white/90 p-3 shadow-sm dark:border-slate-700 dark:bg-slate-800/60">
        <dt class="text-[10px] font-semibold text-slate-500 dark:text-slate-400">نوع الدخول</dt>
        <dd class="mt-0.5 text-sm font-bold text-slate-900 dark:text-white">{{ principalLabel }}</dd>
        <dd class="mt-1 text-[10px] text-slate-600 dark:text-slate-400">
          منصة: {{ user?.is_platform_user ? 'نعم — مستخدم منصة' : '—' }}
          <span v-if="platformRole" class="mr-1 font-mono text-[10px]" dir="ltr">· {{ platformRole }}</span>
        </dd>
      </div>
      <div class="rounded-xl border border-white/80 bg-white/90 p-3 shadow-sm dark:border-slate-700 dark:bg-slate-800/60">
        <dt class="text-[10px] font-semibold text-slate-500 dark:text-slate-400">صلاحيات محمّلة (واجهة)</dt>
        <dd class="mt-0.5 text-lg font-semibold tabular-nums text-primary-700 dark:text-primary-300">{{ permissionsCount }}</dd>
        <dd class="mt-1 text-[10px] text-slate-500 dark:text-slate-400">التحقق النهائي من السياسات يتم على الخادم.</dd>
      </div>
      <div class="rounded-xl border border-white/80 bg-white/90 p-3 shadow-sm dark:border-slate-700 dark:bg-slate-800/60 sm:col-span-2 lg:col-span-1">
        <dt class="text-[10px] font-semibold text-slate-500 dark:text-slate-400">عنوان الخادم (واجهة البرمجة) لهذه الواجهة</dt>
        <dd class="mt-0.5 break-all font-mono text-[11px] text-slate-800 dark:text-slate-200" dir="ltr">{{ apiBaseDisplay }}</dd>
      </div>
      <div class="rounded-xl border border-white/80 bg-white/90 p-3 shadow-sm dark:border-slate-700 dark:bg-slate-800/60 sm:col-span-2">
        <dt class="text-[10px] font-semibold text-slate-500 dark:text-slate-400">نسخة الخادم (مسار الإصدار)</dt>
        <dd v-if="versionError" class="mt-1 text-xs text-amber-800 dark:text-amber-200">{{ versionError }}</dd>
        <dd v-else-if="versionLoading" class="mt-1 text-xs text-slate-500">جاري التحميل…</dd>
        <dd v-else class="mt-1 space-y-1 text-[11px] text-slate-800 dark:text-slate-200">
          <div><span class="text-slate-500 dark:text-slate-400">بيئة التشغيل:</span> {{ versionPayload?.environment ?? '—' }} · <span class="text-slate-500 dark:text-slate-400">رقم الإصدار:</span> {{ versionPayload?.version ?? '—' }}</div>
          <div dir="ltr" class="font-mono text-[10px] text-slate-700 dark:text-slate-300">دمج: {{ versionPayload?.commit || '—' }} · فرع: {{ versionPayload?.branch || '—' }}</div>
        </dd>
        <p class="mt-2 text-[10px] leading-snug text-slate-500 dark:text-slate-400">
          لمطابقة الواجهة مع النشر: قارِن زمن بناء الواجهة مع رمز الدمج الموثّق في خط الإصدار إن وُجد.
        </p>
      </div>
    </dl>

    <div
      v-if="isDismissed"
      class="mt-4 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200/90 bg-slate-50/90 px-3 py-2.5 dark:border-slate-700 dark:bg-slate-800/50"
    >
      <p class="text-[11px] leading-relaxed text-slate-600 dark:text-slate-400">
        أُخفيتَ نصيحة الترحيب أعلى لوحة المنصة. يمكنك إظهارها مجدداً من هنا دون إعادة تثبيت التطبيق.
      </p>
      <button
        type="button"
        class="shrink-0 rounded-lg border border-primary-300/80 bg-white px-3 py-1.5 text-[11px] font-bold text-primary-800 shadow-sm hover:bg-primary-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-primary-700 dark:bg-slate-900 dark:text-primary-200 dark:hover:bg-primary-900/40"
        @click="resetHint"
      >
        إظهار نصيحة الترحيب
      </button>
    </div>
  </section>
</template>

<script setup lang="ts">
import { usePlatformWelcomeHint } from '@/composables/usePlatformWelcomeHint'

const { isDismissed, resetHint } = usePlatformWelcomeHint()

/** عرض حوكمة المنصة فقط — البيانات والـ API من الصفحة الأم */

interface GovernanceUserDisplay {
  name?: string | null
  email?: string | null
  is_platform_user?: boolean
  platform_role?: string | null
}

interface GovernanceVersionPayload {
  version: string
  commit: string
  branch: string
  environment: string
  build_time?: string | null
}

defineProps<{
  user: GovernanceUserDisplay | null
  platformRole: string | null
  principalLabel: string
  permissionsCount: number
  apiBaseDisplay: string
  versionLoading: boolean
  versionError: string
  versionPayload: GovernanceVersionPayload | null
}>()
</script>
