<template>
  <div class="max-w-3xl mx-auto space-y-6" dir="rtl">
    <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">مكتبة أنظمة العمل المرجعية</h1>
        <p class="text-sm text-gray-600 dark:text-slate-400 mt-1">
          ملخصات عملية لأهم المواضيع — <span class="font-medium text-primary-600">آخر مراجعة للواجهة: {{ lastReview }}</span>
        </p>
      </div>
      <button type="button" class="text-xs px-3 py-2 rounded-lg border border-gray-200 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-800" @click="bumpReview">
        تحديث تاريخ المراجعة (محلي)
      </button>
    </header>

    <div class="space-y-3">
      <article v-for="(a, i) in articles" :key="i" class="rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
        <h2 class="font-semibold text-gray-900 dark:text-white">{{ a.title }}</h2>
        <p class="text-sm text-gray-600 dark:text-slate-400 mt-2 leading-relaxed">{{ a.body }}</p>
      </article>
    </div>

    <p class="text-xs text-gray-500 dark:text-slate-500 leading-relaxed">
      المحتوى للإرشاد الداخلي فقط وليس استشارة قانونية. للتحديثات الرسمية راجع الجهات المختصة في المملكة العربية السعودية.
    </p>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'

const KEY = 'labor_law_last_review'
const lastReview = ref('—')

const articles = [
  { title: 'عقد العمل وساعات العمل', body: 'تحديد نوع العقد، فترة التجربة، وساعات العمل والعمل الإضافي وفق الأنظمة المعمول بها.' },
  { title: 'الإجازات والأجور أثناءها', body: 'تمييز الإجازة السنوية والمرضية والاستثناءات؛ أثرها على الراتب ومسير الرواتب.' },
  { title: 'إنهاء الخدمة والمستحقات', body: 'مكافأة نهاية الخدمة، فترة الإشعار، والتسوية مع حماية الأجور عند الصرف.' },
  { title: 'السلامة المهنية في ورش المركبات', body: 'التزامات صاحب العمل تجاه بيئة العمل والتدريب على معدات الرفع والمواد الكيميائية.' },
]

function bumpReview() {
  lastReview.value = new Date().toLocaleDateString('ar-SA', { dateStyle: 'medium' })
  try {
    localStorage.setItem(KEY, lastReview.value)
  } catch { /* */ }
}

onMounted(() => {
  try {
    const s = localStorage.getItem(KEY)
    if (s) lastReview.value = s
    else bumpReview()
  } catch {
    lastReview.value = new Date().toLocaleDateString('ar-SA', { dateStyle: 'medium' })
  }
})
</script>
