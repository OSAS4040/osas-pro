<template>
  <section
    class="rounded-xl border border-gray-100/90 bg-white/80 dark:border-slate-700/70 dark:bg-slate-900/40"
  >
    <button
      type="button"
      class="flex w-full items-center gap-2 rounded-t-xl px-2 py-1.5 text-right transition-colors hover:bg-gray-50/90 dark:hover:bg-slate-800/50"
      :class="expanded || forceExpand ? 'rounded-b-none' : 'rounded-b-xl'"
      :aria-expanded="expanded || forceExpand"
      @click="onToggle"
    >
      <p class="min-w-0 flex-1 text-[12px] font-bold leading-snug tracking-wide text-gray-700 dark:text-slate-200">
        {{ label }}
      </p>
      <ChevronDownIcon
        class="h-3.5 w-3.5 shrink-0 text-gray-400 transition-transform duration-200 ease-out dark:text-slate-500"
        :class="expanded || forceExpand ? '-rotate-180' : ''"
        aria-hidden="true"
      />
    </button>
    <div v-show="expanded || forceExpand" class="space-y-0.5 px-1.5 pb-2 pt-0">
      <slot />
    </div>
  </section>
</template>

<script setup lang="ts">
import { ChevronDownIcon } from '@heroicons/vue/24/outline'

const props = defineProps<{
  label: string
  expanded: boolean
  /** أثناء البحث تُفتَح كل الأقسام ويُعطّل الطي */
  forceExpand?: boolean
}>()

const emit = defineEmits<{ toggle: [] }>()

function onToggle(): void {
  if (props.forceExpand) return
  emit('toggle')
}
</script>
