<template>
  <div
    class="mx-0.5 mb-1.5 rounded-xl border border-primary-200/80 bg-gradient-to-br from-primary-50/95 to-white px-2 py-2 shadow-sm transition-shadow hover:shadow-md dark:border-primary-900/55 dark:from-primary-950/45 dark:to-slate-900/85"
  >
    <p class="mb-1.5 px-1.5 text-[10px] font-bold tracking-wide text-primary-800 dark:text-primary-200">
      {{ eyebrow }}
    </p>
    <slot />
    <p v-if="hint && hint.length > 0" class="mt-1.5 px-1.5 text-[9px] leading-snug text-slate-600 dark:text-slate-400">
      {{ hint }}
    </p>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  /** عنوان القسم الصغير فوق الرابط */
  eyebrow: string
  /** سطر توضيحي تحت الرابط */
  hint?: string
}>()
</script>
