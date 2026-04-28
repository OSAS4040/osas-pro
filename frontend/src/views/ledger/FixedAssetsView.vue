<template>
  <div class="p-6 space-y-4">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-800 dark:text-slate-100">الأصول الثابتة</h1>
      <button class="cursor-not-allowed rounded-lg bg-indigo-600 px-3 py-2 text-xs font-medium text-white opacity-80" disabled>
        إضافة أصل
      </button>
    </div>
    <p class="text-xs text-gray-500 dark:text-slate-400">
      ملف النشاط المسجّل:
      <span class="font-medium text-gray-700 dark:text-slate-300">{{ businessTypeLabelAr(biz.businessType) }}</span>
      — {{ activityContextLine }}
    </p>

    <div
      v-if="auth.isOwner && !featureOn"
      class="rounded-xl border border-amber-200 bg-amber-50/90 p-4 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100"
    >
      <p class="font-semibold">الميزة معطّلة في ملف نشاط المنشأة</p>
      <p class="mt-1 text-xs opacity-90">
        عند التفعيل يظهر الرابط للفريق ذي صلاحية المحاسبة؛ التنفيذ الفني (API وسجل الإهلاك) يُكمّل على مراحل وليس مرتبطاً بترقية باقة تلقائياً.
      </p>
      <RouterLink
        :to="{ path: '/settings', query: { tab: 'profile' } }"
        class="mt-3 inline-block rounded-lg bg-amber-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-800 dark:bg-amber-600 dark:hover:bg-amber-500"
      >
        فتح إعدادات ملف النشاط
      </RouterLink>
    </div>

    <div
      v-else-if="featureOn"
      class="rounded-xl border border-slate-200 bg-slate-50/90 p-4 text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-900/40 dark:text-slate-200"
    >
      <p class="font-semibold">واجهة أولية — الخلفية قيد الإعداد</p>
      <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">
        المسار والقائمة مفعّلان حسب ملف النشاط والصلاحية؛ الجداول والقيود والإهلاك الآلي يُربَطان لاحقاً بـ API وفق خطة الإطلاق المرحلية.
      </p>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-slate-700 dark:bg-slate-800">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 dark:bg-slate-900/60 dark:text-slate-400">
          <tr>
            <th class="px-3 py-2 text-right">الحالة</th>
            <th class="px-3 py-2 text-right">رقم الأصل</th>
            <th class="px-3 py-2 text-right">الاسم</th>
            <th class="px-3 py-2 text-right">تاريخ الشراء</th>
            <th class="px-3 py-2 text-right">القيمة الدفترية</th>
            <th class="px-3 py-2 text-right">المجمع</th>
            <th class="px-3 py-2 text-right">معدل الإهلاك</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td colspan="7" class="px-3 py-10 text-center text-gray-400 dark:text-slate-500">لا توجد سجلات بعد.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useBusinessProfileStore } from '@/stores/businessProfile'
import { businessTypeLabelAr } from '@/config/businessFeatureProfileDefaults'

const auth = useAuthStore()
const biz = useBusinessProfileStore()

const featureOn = computed(() => {
  void biz.loaded
  void biz.businessType
  return biz.isEnabled('fixed_assets')
})

const activityContextLine = computed(() => {
  void biz.loaded
  const t = biz.businessType
  if (t === 'retail') {
    return 'التجزئة: غالباً محور الاهتمام المخزون والمحاسبة؛ يُفعّل سجل الأصول عند وجود معدات أو محلات مملوكة تحتاج إهلاكاً منظماً.'
  }
  if (t === 'fleet_operator') {
    return 'مشغّل الأسطول: تركيز المنصة على المركبات والعقود؛ الأصول الثابتة مناسبة عند تسجيل معدات ثقيلة أو بنى تحتية مرتبطة بالأسطول.'
  }
  return 'مركز الخدمة والورشة: معدات وأدوات تشغيلية — غالباً أعلى احتمالية لحاجة سجل أصول وإهلاك عند التفعيل.'
})
</script>
