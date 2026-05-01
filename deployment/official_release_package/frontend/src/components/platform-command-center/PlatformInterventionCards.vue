<template>
  <section>
    <h2 class="mb-3 text-base font-black text-slate-900 dark:text-slate-100">يحتاج تدخل الآن</h2>
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
      <article
        v-for="item in items"
        :key="item.key"
        class="rounded-2xl border p-4"
        :class="item.severity === 'high' ? 'border-red-200 bg-red-50/80 dark:border-red-900/60 dark:bg-red-950/20' : item.severity === 'medium' ? 'border-amber-200 bg-amber-50/80 dark:border-amber-900/60 dark:bg-amber-950/20' : 'border-sky-200 bg-sky-50/80 dark:border-sky-900/60 dark:bg-sky-950/20'"
      >
        <p class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ item.title }}</p>
        <p class="mt-1 text-xl font-black">{{ item.count }}</p>
        <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">{{ item.desc }}</p>
        <button
          type="button"
          class="mt-3 rounded-lg bg-slate-900 px-2.5 py-1.5 text-xs font-bold text-white dark:bg-slate-100 dark:text-slate-900"
          @click="$emit('action', item.key)"
        >
          {{ item.cta }}
        </button>
      </article>
    </div>
  </section>
</template>

<script setup lang="ts">
defineProps<{
  items: Array<{ key: string; title: string; count: string; desc: string; cta: string; severity: 'high' | 'medium' | 'low' }>
}>()
defineEmits<{ action: [key: string] }>()
</script>
