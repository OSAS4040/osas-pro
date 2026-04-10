<template>
  <div class="max-w-4xl mx-auto space-y-6" dir="rtl">
    <header class="rounded-2xl border border-emerald-200/60 dark:border-emerald-900/40 bg-gradient-to-l from-emerald-50 to-white dark:from-slate-900 dark:to-slate-900 p-6">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
        <PencilSquareIcon class="w-8 h-8 text-emerald-600" />
        التوقيع الإلكتروني
      </h1>
      <p class="text-sm text-gray-600 dark:text-slate-400 mt-2">
        مسار موحّد: رفع المستند → مرسلو الطلب → توقيع متسلسل أو متوازي → أرشفة تلقائية. جاهز للتكامل مع مزوّد توقيع معتمد عند الحاجة.
      </p>
    </header>

    <div class="grid md:grid-cols-3 gap-3">
      <div v-for="s in steps" :key="s.n" class="rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 text-center">
        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300 text-sm font-bold">{{ s.n }}</span>
        <p class="text-sm font-medium text-gray-900 dark:text-white mt-2">{{ s.label }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { PencilSquareIcon } from '@heroicons/vue/24/outline'

const steps = [
  { n: 1, label: 'إنشاء طلب توقيع' },
  { n: 2, label: 'مسار الموافقين' },
  { n: 3, label: 'إثبات وتخزين' },
]
</script>
