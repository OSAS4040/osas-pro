<template>
  <div class="max-w-3xl mx-auto space-y-6" dir="rtl">
    <header>
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
        <ShieldCheckIcon class="w-8 h-8 text-emerald-600" />
        حماية الأجور (WPS) — إطار التكامل
      </h1>
      <p class="text-sm text-gray-600 dark:text-slate-400 mt-2 leading-relaxed">
        تجهيز المنشأة لربط مسير الرواتب مع متطلبات <strong>نظام حماية الأجور</strong> في المملكة: ملفات SIF، أرقام منشآت، ومواءمة مع مزوّد معتمد.
      </p>
    </header>

    <div class="space-y-3">
      <div v-for="(s, i) in steps" :key="i" class="flex gap-3 rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
        <span class="flex-shrink-0 w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300 font-bold flex items-center justify-center text-sm">{{ i + 1 }}</span>
        <div>
          <p class="font-semibold text-gray-900 dark:text-white text-sm">{{ s.t }}</p>
          <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">{{ s.d }}</p>
        </div>
      </div>
    </div>

    <div class="rounded-xl border border-dashed border-gray-300 dark:border-slate-600 p-4 text-xs text-gray-500 dark:text-slate-400">
      <strong class="text-gray-700 dark:text-slate-300">التفعيل الفني:</strong> يتطلب مفاتيح API من البنك أو الوسيط المعتمد، وقوالب ملف SIF، وجدولة رفع تلقائي — يُضاف عند توفر اعتماد المزوّد.
    </div>

    <RouterLink to="/workshop/salaries" class="inline-flex text-sm font-medium text-primary-600 hover:underline">← الانتقال إلى مسير الرواتب</RouterLink>
  </div>
</template>

<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { ShieldCheckIcon } from '@heroicons/vue/24/outline'

const steps = [
  { t: 'تعريف المنشأة والبنك', d: 'رقم المنشأة في وزارة الموارد، رقم حساب الرواتب، ومرجع البنك المعتمد.' },
  { t: 'تجهيز ملفات الأجور (SIF)', d: 'توليد ملف وفق تنسيق WPS: هوية العامل، الأجر الأساسي، الاستقطاعات، صافي الحوالة.' },
  { t: 'التحقق والإرسال', d: 'مراجعة التطابق مع العقود والحضور ثم الرفع عبر بوابة البنك أو الوسيط.' },
  { t: 'التسوية والإيصالات', d: 'أرشفة إيصالات الإيداع وربطها بقيد محاسبي في النظام.' },
]
</script>
