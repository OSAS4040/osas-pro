<template>
  <div
    :class="
      variant === 'flat'
        ? 'mx-0.5 mb-1.5 rounded-xl border border-gray-100/95 bg-white/90 px-2 py-1.5 shadow-sm dark:border-slate-700/80 dark:bg-slate-900/50'
        : 'mx-0.5 mb-1.5 rounded-xl border border-primary-200/80 bg-gradient-to-br from-primary-50/95 to-white px-2 py-2 shadow-sm transition-shadow hover:shadow-md dark:border-primary-900/55 dark:from-primary-950/45 dark:to-slate-900/85'
    "
  >
    <p
      :class="
        variant === 'flat'
          ? 'mb-1 px-1.5 text-[10px] font-bold tracking-wide text-gray-600 dark:text-slate-400'
          : 'mb-1.5 px-1.5 text-[10px] font-bold tracking-wide text-primary-800 dark:text-primary-200'
      "
    >
      {{ eyebrow }}
    </p>
    <slot />
    <p
      v-if="hint && hint.length > 0 && variant !== 'flat'"
      class="mt-1.5 px-1.5 text-[9px] leading-snug text-slate-600 dark:text-slate-400"
    >
      {{ hint }}
    </p>
  </div>
</template>

<script setup lang="ts">
withDefaults(
  defineProps<{
    /** عنوان القسم الصغير فوق الرابط */
    eyebrow: string
    /** سطر توضيحي تحت الرابط */
    hint?: string
    /** flat: نفس حدود أقسام بوابة فريق العمل — أقل إثقالاً بصرياً */
    variant?: 'elevated' | 'flat'
  }>(),
  { variant: 'elevated' },
)
</script>
