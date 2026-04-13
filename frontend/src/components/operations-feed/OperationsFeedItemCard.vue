<template>
  <article
    class="rounded-2xl border border-slate-200/80 dark:border-slate-700/60 bg-white dark:bg-slate-900/35 px-4 py-3 shadow-sm hover:border-slate-300 dark:hover:border-slate-600 transition-colors"
  >
    <div class="flex gap-3">
      <div
        class="shrink-0 w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-[10px] font-bold tracking-tight text-slate-600 dark:text-slate-300"
        aria-hidden="true"
      >
        {{ icon }}
      </div>
      <div class="min-w-0 flex-1 space-y-1">
        <div class="flex flex-wrap items-center gap-2">
          <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ item.title }}</h3>
          <span class="text-[10px] px-1.5 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">{{ item.type }}</span>
          <span
            v-if="item.attention_level !== 'normal'"
            class="text-[10px] px-1.5 py-0.5 rounded-md font-medium"
            :class="attentionClass"
          >{{ item.attention_level }}</span>
        </div>
        <p class="text-xs text-slate-600 dark:text-slate-300">{{ item.subtitle }}</p>
        <p v-if="item.description" class="text-[11px] text-slate-500 dark:text-slate-400">{{ item.description }}</p>
        <div class="flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-slate-500 dark:text-slate-400">
          <span>{{ occurred }}</span>
          <span v-if="item.company_name">{{ item.company_name }}</span>
          <span v-if="item.branch_name">{{ item.branch_name }}</span>
          <span v-if="item.customer_name">{{ item.customer_name }}</span>
          <span v-if="showAmount" class="font-medium text-slate-700 dark:text-slate-200 tabular-nums">
            {{ item.amount?.toLocaleString('ar-SA') }} {{ item.currency }}
          </span>
        </div>
        <div v-if="item.tags?.length" class="flex flex-wrap gap-1 pt-1">
          <span
            v-for="t in item.tags"
            :key="t"
            class="text-[10px] px-1.5 py-0.5 rounded bg-slate-50 dark:bg-slate-800/80 text-slate-600 dark:text-slate-300"
          >{{ t }}</span>
        </div>
        <RouterLink
          v-if="item.entity_route"
          :to="item.entity_route"
          class="inline-block mt-2 text-xs font-medium text-primary-600 hover:underline"
        >{{ detailLabel }}</RouterLink>
      </div>
    </div>
  </article>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import type { GlobalFeedItem } from '@/types/globalOperationsFeed'

const props = defineProps<{
  item: GlobalFeedItem
  detailLabel: string
  financialIncluded: boolean
}>()

const icon = computed(() => {
  switch (props.item.type) {
    case 'work_order':
      return 'WO'
    case 'invoice':
      return 'INV'
    case 'payment':
      return 'PAY'
    case 'ticket':
      return 'TKT'
    default:
      return '—'
  }
})

const occurred = computed(() => {
  if (!props.item.occurred_at) return '—'
  try {
    return new Date(props.item.occurred_at).toLocaleString('ar-SA', { dateStyle: 'medium', timeStyle: 'short' })
  } catch {
    return props.item.occurred_at
  }
})

const showAmount = computed(() => {
  if (!props.financialIncluded || props.item.amount == null) return false
  return !props.item.financial_visibility_applied
})

const attentionClass = computed(() => {
  if (props.item.attention_level === 'critical') return 'bg-rose-100 text-rose-900 dark:bg-rose-950/50 dark:text-rose-100'
  if (props.item.attention_level === 'important') return 'bg-orange-100 text-orange-900 dark:bg-orange-950/40 dark:text-orange-100'
  return 'bg-amber-100 text-amber-900 dark:bg-amber-950/40 dark:text-amber-100'
})
</script>
