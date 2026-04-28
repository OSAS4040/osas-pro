<template>
  <article
    v-if="isSurface"
    class="rounded-xl border p-3.5 shadow-sm transition-colors duration-200 dark:shadow-none"
    :class="surfaceShellClass"
  >
    <div class="flex flex-wrap items-start justify-between gap-2">
      <div class="min-w-0 flex-1">
        <p v-if="eyebrow" class="text-[10px] font-medium text-slate-500 dark:text-slate-400">{{ eyebrow }}</p>
        <h4 class="text-sm font-semibold leading-snug text-slate-900 dark:text-white">{{ title }}</h4>
      </div>
      <span
        v-if="badge"
        class="shrink-0 rounded-md px-2 py-0.5 text-[10px] font-medium tabular-nums"
        :class="badgeClass"
      >{{ badge }}</span>
    </div>
    <dl class="mt-3 space-y-2 text-[11px] leading-relaxed">
      <div v-if="why">
        <dt class="text-[10px] font-medium text-slate-500 dark:text-slate-400">السبب</dt>
        <dd class="mt-0.5 text-slate-700 dark:text-slate-300">{{ why }}</dd>
      </div>
      <div v-if="meaning">
        <dt class="text-[10px] font-medium text-slate-500 dark:text-slate-400">الدلالة</dt>
        <dd class="mt-0.5 text-slate-700 dark:text-slate-300">{{ meaning }}</dd>
      </div>
      <div v-if="recommendation">
        <dt class="text-[10px] font-medium text-slate-500 dark:text-slate-400">التوصية</dt>
        <dd class="mt-0.5 text-slate-800 dark:text-slate-200">{{ recommendation }}</dd>
      </div>
    </dl>
    <div v-if="ctaLabel && ctaTo" class="mt-3 flex justify-end border-t border-slate-100/90 pt-3 dark:border-slate-700/80">
      <RouterLink
        :to="ctaTo"
        class="inline-flex items-center rounded-lg bg-primary-600 px-3 py-1.5 text-[11px] font-medium text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-400 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
      >
        {{ ctaLabel }}
      </RouterLink>
    </div>
    <div v-else-if="$slots.footer" class="mt-3 border-t border-slate-100/90 pt-3 dark:border-slate-700/80">
      <slot name="footer" />
    </div>
  </article>

  <details
    v-else
    class="group rounded-xl border border-slate-200/80 bg-white/90 p-3.5 shadow-sm transition-colors open:border-slate-300/90 dark:border-slate-700 dark:bg-slate-900/60 dark:open:border-slate-600"
  >
    <summary
      class="flex cursor-pointer list-none items-start justify-between gap-2 text-[12px] leading-relaxed marker:hidden [&::-webkit-details-marker]:hidden"
    >
      <span class="inline-flex min-w-0 gap-2">
        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full" :class="dotClass" />
        <span class="font-semibold text-slate-800 dark:text-slate-100">{{ title }}</span>
      </span>
      <span v-if="badge" class="shrink-0 rounded-md px-2 py-0.5 text-[10px] font-medium" :class="badgeClass">{{ badge }}</span>
    </summary>
    <div class="mt-3 space-y-2 border-t border-slate-100 pt-3 text-[11px] leading-relaxed text-slate-700 dark:border-slate-700 dark:text-slate-300">
      <slot />
    </div>
  </details>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

const props = withDefaults(
  defineProps<{
    title: string
    eyebrow?: string
    badge?: string
    tone?: 'default' | 'positive' | 'warning' | 'action'
    /** تخطيط «سطح ذكاء»: سبب / دلالة / توصية + CTA */
    why?: string
    meaning?: string
    recommendation?: string
    ctaLabel?: string
    ctaTo?: string
  }>(),
  {
    eyebrow: '',
    badge: '',
    why: '',
    meaning: '',
    recommendation: '',
    ctaLabel: '',
    ctaTo: '',
  },
)

const isSurface = computed(() =>
  Boolean(props.why || props.meaning || props.recommendation || (props.ctaLabel && props.ctaTo)),
)

const dotClass = computed(() => {
  if (props.tone === 'positive') return 'bg-emerald-500'
  if (props.tone === 'warning' || props.tone === 'action') return 'bg-amber-500'
  return 'bg-slate-400'
})

const badgeClass = computed(() => {
  if (props.tone === 'positive') return 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-200'
  if (props.tone === 'warning' || props.tone === 'action') return 'bg-amber-100 text-amber-950 dark:bg-amber-950/40 dark:text-amber-100'
  return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'
})

const surfaceShellClass = computed(() => {
  if (props.tone === 'positive') {
    return 'border-emerald-200/85 bg-emerald-50/45 dark:border-emerald-900/45 dark:bg-emerald-950/20'
  }
  if (props.tone === 'warning') {
    return 'border-amber-200/85 bg-amber-50/40 dark:border-amber-900/45 dark:bg-amber-950/20'
  }
  if (props.tone === 'action') {
    return 'border-rose-200/80 bg-rose-50/40 dark:border-rose-900/45 dark:bg-rose-950/20'
  }
  return 'border-slate-200/85 bg-white/95 dark:border-slate-700 dark:bg-slate-900/65'
})
</script>
