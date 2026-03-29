<script setup lang="ts">
import type { CommandZoneItem } from '@/composables/useIntelligenceCommandCenter'
import CommandItemCard from './CommandItemCard.vue'
import EmptySignalState from './EmptySignalState.vue'

const props = defineProps<{
  title: string
  subtitle: string
  accentClass: string
  items: CommandZoneItem[]
  emptyTitle: string
  emptyHint: string
}>()
</script>

<template>
  <section
    class="rounded-2xl border border-gray-200 dark:border-slate-600 overflow-hidden bg-gray-50/80 dark:bg-slate-900/50 flex flex-col min-h-[200px]"
  >
    <header
      class="px-4 py-3 border-b border-gray-200 dark:border-slate-600 flex items-center gap-3"
      :class="accentClass"
    >
      <div class="flex-1 min-w-0">
        <h3 class="text-base font-bold text-gray-900 dark:text-slate-100">{{ title }}</h3>
        <p class="text-xs text-gray-600 dark:text-slate-400 mt-0.5">{{ subtitle }}</p>
      </div>
      <span
        class="flex h-9 min-w-[2.25rem] items-center justify-center rounded-lg bg-white/80 dark:bg-slate-800 text-sm font-bold text-gray-800 dark:text-slate-100 shadow-sm"
      >
        {{ items.length }}
      </span>
    </header>

    <div class="p-4 flex-1 flex flex-col gap-3">
      <template v-if="items.length">
        <CommandItemCard v-for="it in items" :key="`${it.source}-${it.id}`" :item="it" />
      </template>
      <EmptySignalState v-else :title="emptyTitle" :hint="emptyHint" />
    </div>
  </section>
</template>
