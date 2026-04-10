<template>
  <div class="app-shell-page max-w-lg space-y-6" :dir="isAr ? 'rtl' : 'ltr'">
    <div class="rounded-2xl border border-amber-200/90 bg-amber-50/90 dark:border-amber-800/50 dark:bg-amber-950/30 px-5 py-6 shadow-sm">
      <div class="flex items-start gap-3">
        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-amber-200/80 text-amber-900 dark:bg-amber-900/60 dark:text-amber-100 text-lg font-bold" aria-hidden="true">!</span>
        <div class="min-w-0 space-y-2">
          <h1 class="text-lg font-bold text-amber-950 dark:text-amber-50">
            {{ heading }}
          </h1>
          <p class="text-sm text-amber-900/90 dark:text-amber-100/90 leading-relaxed">
            {{ body }}
          </p>
          <p v-if="fromPath" class="text-xs font-mono text-amber-800/80 dark:text-amber-200/80 break-all">
            {{ lt('attempted') }}: {{ fromPath }}
          </p>
        </div>
      </div>
    </div>

    <div class="flex flex-wrap gap-3">
      <RouterLink
        to="/"
        class="inline-flex items-center justify-center rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-700 transition-colors"
      >
        {{ lt('home') }}
      </RouterLink>
      <RouterLink
        v-if="auth.isStaff && !auth.isFleet && !auth.isCustomer"
        to="/about/capabilities"
        class="inline-flex items-center justify-center rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm font-semibold text-gray-800 dark:text-slate-200 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors"
      >
        {{ lt('capabilities') }}
      </RouterLink>
    </div>

    <p class="text-xs text-gray-500 dark:text-slate-400 leading-relaxed">
      {{ lt('footnote') }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useLocale } from '@/composables/useLocale'

const route = useRoute()
const auth = useAuthStore()
const locale = useLocale()

const isAr = computed(() => locale.lang.value === 'ar' || locale.lang.value.startsWith('ar'))

type Reason = 'manager' | 'owner' | 'permission' | 'feature' | 'portal' | 'preview' | 'inactive'

const reason = computed((): Reason => {
  const r = String(route.query.reason ?? '').toLowerCase()
  if (
    r === 'manager' ||
    r === 'owner' ||
    r === 'permission' ||
    r === 'feature' ||
    r === 'portal' ||
    r === 'preview' ||
    r === 'inactive'
  ) {
    return r
  }
  return 'permission'
})

const fromPath = computed(() => {
  const f = route.query.from
  if (typeof f !== 'string' || f.length > 512) return null
  if (!f.startsWith('/') || f.startsWith('//')) return null
  return f
})

function lt(key: 'attempted' | 'home' | 'capabilities' | 'footnote'): string {
  const ar: Record<string, string> = {
    attempted: 'المسار المطلوب',
    home: 'الرئيسية',
    capabilities: 'قدرات النظام',
    footnote: 'التحكم الفعلي بالوصول يبقى على الخادم. إن كان ذلك خطأ، راجع مدير منشأتك.',
  }
  const en: Record<string, string> = {
    attempted: 'Requested path',
    home: 'Home',
    capabilities: 'System capabilities',
    footnote: 'Access is ultimately enforced on the server. If this looks wrong, contact your administrator.',
  }
  return isAr.value ? ar[key] : en[key]
}

const heading = computed(() => {
  const ar: Record<Reason, string> = {
    manager: 'يتطلب صلاحية إدارية',
    owner: 'يتطلب صلاحية مالك/منصة',
    permission: 'لا تملك صلاحية هذا القسم',
    feature: 'هذه الميزة غير مفعّلة في إعداد البناء الحالي',
    portal: 'هذه البوابة غير متاحة في الإعداد الحالي',
    preview: 'هذا المسار غير متاح في الإصدار الحالي',
    inactive: 'الميزة غير مفعلة حالياً',
  }
  const en: Record<Reason, string> = {
    manager: 'Manager access required',
    owner: 'Owner or platform access required',
    permission: 'You do not have access to this area',
    feature: 'This feature is disabled in the current build configuration',
    portal: 'This portal is not enabled in the current configuration',
    preview: 'This path is not available in the current release',
    inactive: 'This feature is not enabled right now',
  }
  return isAr.value ? ar[reason.value] : en[reason.value]
})

const body = computed(() => {
  const ar: Record<Reason, string> = {
    manager: 'هذه الصفحة مخصصة لمديري المنشأة أو أعلى. يمكنك العودة للرئيسية أو مراجعة ما تسمح به منشأتك في «قدرات النظام».',
    owner: 'هذه الصفحة مخصصة لمالك الشركة أو دور إداري على مستوى المنصة حسب السياسة.',
    permission: 'دورك الحالي لا يشمل فتح هذا المسار. اطلب من مديرك منح الصلاحية المناسبة إن كان ذلك مطلوباً.',
    feature: 'واجهة «مركز العمليات الذكي» أو ميزة مرتبطة قد تكون معطّلة في هذا البناء. راجع مشرف النظام.',
    portal: 'بوابة الأسطول أو العملاء أو لوحة المنصة قد تكون معطّلة في إعداد الواجهة لهذا البناء.',
    preview:
      'لا يُفتح هذا المسار من الواجهة في الإصدار الحالي. راجع «قدرات النظام» لمعرفة ما هو مفعّل فعلياً لمنشأتك.',
    inactive:
      'لا يتوفر تشغيل هذا المسار من الواجهة في الإعداد الحالي. راجع «قدرات النظام» أو تواصل مع مشرف المنشأة إن احتجت تفعيلاً لاحقاً.',
  }
  const en: Record<Reason, string> = {
    manager: 'This page is limited to managers or higher. Return home or review what your tenant permits under System capabilities.',
    owner: 'This page requires a company owner or designated platform administration role.',
    permission: 'Your current role does not include this path. Ask an administrator if you need expanded access.',
    feature: 'This UI capability may be turned off in the current frontend build. Contact your administrator.',
    portal: 'The fleet, customer, or admin portal may be disabled in this build configuration.',
    preview:
      'This path is not opened from the UI in the current release. Use System capabilities to see what is enabled for your tenant.',
    inactive:
      'This path is not operational in the current UI configuration. Review System capabilities or contact your administrator if you need it enabled.',
  }
  return isAr.value ? ar[reason.value] : en[reason.value]
})
</script>
