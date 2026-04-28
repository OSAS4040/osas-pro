<template>
  <section
    id="admin-section-operator-commands"
    class="scroll-mt-32 mb-16"
    aria-labelledby="operator-commands-heading"
  >
    <div class="mb-4 border-b border-slate-200 pb-3 dark:border-slate-700">
      <h2 id="operator-commands-heading" class="platform-admin-section-heading">
        أوامر المشغّل
      </h2>
      <p class="mt-1 max-w-3xl text-xs leading-relaxed text-slate-600 dark:text-slate-400">
        أوامر تنفيذية على الخادم — لا تُشغَّل من المتصفح؛ انسخ الأمر إلى جلسة SSH أو حاوية التطبيق. استبدل
        <span class="font-mono" dir="ltr">app</span>
        باسم خدمة التطبيق في ملف Docker عند الحاجة.
      </p>
      <div
        class="mt-3 max-w-3xl rounded-xl border border-amber-200/90 bg-amber-50/80 px-3 py-2.5 text-[11px] leading-relaxed text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/25 dark:text-amber-100"
        role="note"
      >
        <strong class="font-bold">ممارسات آمنة:</strong>
        نفّذ الأوامر فقط في بيئة مخوّلة لديك. لا تلصق مخرجات الطرفية أو الأوامر في قنوات عامة، ولا تُدخل كلمات مرور
        أو مفاتيح من مصادر غير موثوقة. راقب تأثير الأمر على الإنتاج قبل التنفيذ الجماعي.
      </div>
    </div>
    <div class="grid gap-4 md:grid-cols-2">
      <article
        v-for="row in commands"
        :key="row.id"
        class="flex flex-col rounded-2xl border border-primary-200/90 bg-gradient-to-br from-white to-primary-50/40 shadow-sm dark:border-primary-900/45 dark:from-slate-900 dark:to-primary-900/20"
      >
        <div class="flex flex-wrap items-start justify-between gap-2 border-b border-primary-100/90 px-4 py-3 dark:border-primary-900/40">
          <div class="min-w-0">
            <h3 class="text-sm font-bold text-primary-900 dark:text-primary-100">{{ row.title }}</h3>
            <p class="mt-0.5 text-[11px] leading-snug text-slate-600 dark:text-slate-400">{{ row.hint }}</p>
          </div>
          <button
            type="button"
            class="shrink-0 rounded-lg border border-primary-300/80 bg-white px-2.5 py-1 text-[11px] font-bold text-primary-800 shadow-sm hover:bg-primary-50 dark:border-primary-700 dark:bg-slate-800 dark:text-primary-200 dark:hover:bg-primary-900/50"
            aria-label="نسخ الأمر إلى الحافظة"
            @click="emit('copy-command', row.command)"
          >
            نسخ
          </button>
        </div>
        <details class="flex flex-col border-t border-primary-100/80 dark:border-primary-900/30" open>
          <summary
            class="cursor-pointer list-none px-4 py-2 text-[11px] font-bold text-primary-800 outline-none hover:bg-primary-50/60 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-400 dark:text-primary-200 dark:hover:bg-primary-900/30 [&::-webkit-details-marker]:hidden"
          >
            عرض الأمر التنفيذي
          </summary>
          <pre
            class="m-0 flex-1 overflow-x-auto whitespace-pre-wrap break-all px-4 pb-4 pt-0 font-mono text-[11px] leading-relaxed text-slate-800 dark:text-slate-200"
            dir="ltr"
            tabindex="0"
          >{{ row.command }}</pre>
        </details>
      </article>
    </div>
  </section>
</template>

<script setup lang="ts">
interface OperatorCommandRow {
  id: string
  title: string
  hint: string
  command: string
}

defineProps<{
  commands: OperatorCommandRow[]
}>()

const emit = defineEmits<{
  'copy-command': [text: string]
}>()
</script>
