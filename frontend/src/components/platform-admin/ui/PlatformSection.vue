<template>
  <section
    :id="sectionId || undefined"
    :tabindex="sectionId ? -1 : undefined"
    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/70"
    :class="sectionId ? 'scroll-mt-28 outline-none' : ''"
  >
    <header v-if="title || subtitle" class="mb-3">
      <h2 v-if="title" class="text-sm font-semibold text-slate-900 dark:text-white">{{ title }}</h2>
      <p v-if="subtitle" class="mt-1 text-xs text-slate-600 dark:text-slate-400">{{ subtitle }}</p>
    </header>
    <slot />
  </section>
</template>

<script setup lang="ts">
withDefaults(
  defineProps<{
    title?: string
    subtitle?: string
    /** مرسى للتنقل داخل الصفحة + تركيز آمن بعد التمرير */
    sectionId?: string
  }>(),
  {
    title: '',
    subtitle: '',
    sectionId: '',
  },
)
</script>
