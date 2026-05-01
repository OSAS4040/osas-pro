<template>
  <section class="rounded-2xl border border-violet-200 bg-gradient-to-br from-violet-50 to-white p-4 dark:border-violet-900/50 dark:from-violet-950/20 dark:to-slate-900">
    <h2 class="text-base font-black text-violet-900 dark:text-violet-200">ذكاء المنصة</h2>
    <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">تحليلات ذكية وتوصيات تشغيلية مرتبة بالأولوية.</p>
    <div class="mt-3 space-y-2">
      <article v-for="(r, idx) in recommendations" :key="idx" class="rounded-xl border border-violet-200/70 bg-white/80 p-3 dark:border-violet-900/60 dark:bg-slate-900/50">
        <p class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ r.what }}</p>
        <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">{{ r.why }}</p>
        <p class="mt-1 text-xs text-violet-700 dark:text-violet-300">الأثر المتوقع: {{ r.impact }}</p>
        <button
          type="button"
          class="mt-2 rounded-lg bg-violet-600 px-2.5 py-1.5 text-xs font-bold text-white"
          @click="$emit('action', idx)"
        >
          {{ r.action }}
        </button>
      </article>
      <p v-if="recommendations.length === 0" class="text-xs text-slate-500">لا توصيات حرجة حالياً.</p>
    </div>
  </section>
</template>

<script setup lang="ts">
defineProps<{
  recommendations: Array<{ what: string; why: string; impact: string; action: string }>
}>()
defineEmits<{ action: [index: number] }>()
</script>
