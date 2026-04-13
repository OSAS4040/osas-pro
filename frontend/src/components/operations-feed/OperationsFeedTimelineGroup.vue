<template>
  <section class="space-y-3">
    <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 px-1">{{ label }}</h2>
    <div class="space-y-2 border-s border-slate-200 dark:border-slate-700 ps-3">
      <OperationsFeedItemCard
        v-for="it in items"
        :key="`${it.type}-${it.id}`"
        :item="it"
        :detail-label="detailLabel"
        :financial-included="financialIncluded"
      />
    </div>
  </section>
</template>

<script setup lang="ts">
import type { GlobalFeedItem } from '@/types/globalOperationsFeed'
import OperationsFeedItemCard from './OperationsFeedItemCard.vue'

defineProps<{
  label: string
  items: GlobalFeedItem[]
  detailLabel: string
  financialIncluded: boolean
}>()
</script>
