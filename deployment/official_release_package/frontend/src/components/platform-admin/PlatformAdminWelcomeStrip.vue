<template>
  <div
    v-if="showWelcomeStrip"
    data-testid="platform-admin-welcome-strip"
    class="mb-6 rounded-2xl border border-primary-200/90 bg-gradient-to-br from-primary-50/95 via-white to-slate-50/80 p-4 shadow-sm dark:border-primary-900/45 dark:from-primary-900/35 dark:via-slate-900 dark:to-slate-950/90"
    role="region"
    aria-labelledby="platform-welcome-heading"
  >
    <div class="flex gap-3">
      <InformationCircleIcon
        class="h-6 w-6 shrink-0 text-primary-600 dark:text-primary-400"
        aria-hidden="true"
      />
      <div class="min-w-0 flex-1 text-right">
        <h2 id="platform-welcome-heading" class="text-sm font-bold text-slate-900 dark:text-white">
          مرحباً — أنت في إدارة المنصة
        </h2>
        <ul class="mt-2 space-y-1.5 text-[12px] leading-relaxed text-slate-600 dark:text-slate-400">
          <li class="flex gap-2">
            <span class="text-primary-500 dark:text-primary-400" aria-hidden="true">•</span>
            <span>هذا المسار لمشغّلي المنصة: قراءة مجمّعة عبر المشتركين والباقات والتشغيل — وليس لإعدادات شركة واحدة كمستأجر.</span>
          </li>
          <li class="flex gap-2">
            <span class="text-primary-500 dark:text-primary-400" aria-hidden="true">•</span>
            <span>استخدم الأقسام من الشريط الجانبي أو التنقل السريع؛ كل قسم يحمّل بياناته عند الحاجة.</span>
          </li>
          <li class="flex gap-2">
            <span class="text-primary-500 dark:text-primary-400" aria-hidden="true">•</span>
            <span>للعودة إلى واجهة فريق عمل شركة (فواتير، تقارير، …) استخدم روابط «فريق العمل» أو «بوابة فريق العمل» — ستُعلَم قبل مغادرة سياق المنصة.</span>
          </li>
        </ul>
      </div>
      <button
        type="button"
        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-200/80 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:hover:bg-slate-800 dark:hover:text-slate-200"
        aria-label="إخفاء الملاحظة (يُحفظ في هذا المتصفح)"
        title="إخفاء الملاحظة (يُحفظ في هذا المتصفح)"
        @click="dismiss"
      >
        <XMarkIcon class="h-5 w-5" aria-hidden="true" />
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { InformationCircleIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import { usePlatformWelcomeHint } from '@/composables/usePlatformWelcomeHint'

const { showWelcomeStrip, dismiss } = usePlatformWelcomeHint()
</script>
