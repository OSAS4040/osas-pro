<template>
  <section class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">الدعم السريع</h3>
    <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">لفتح تذكرة تشغيلية أو الرجوع لمركز المساعدة مباشرة.</p>
    <div class="mt-3 flex flex-wrap gap-2">
      <button type="button" class="rounded-lg bg-violet-600 px-3 py-2 text-xs font-bold text-white" @click="$emit('open-ticket')">فتح تذكرة دعم</button>
      <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-xs dark:border-slate-700" @click="$emit('open-help')">عرض مركز المساعدة</button>
      <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-xs dark:border-slate-700" @click="$emit('contact-ops')">التواصل مع فريق التشغيل</button>
    </div>
  </section>
</template>

<script setup lang="ts">
defineEmits<{ 'open-ticket': []; 'open-help': []; 'contact-ops': [] }>()
</script>
