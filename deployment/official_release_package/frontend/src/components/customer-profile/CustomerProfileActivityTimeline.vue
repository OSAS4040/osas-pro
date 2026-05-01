<template>
  <ul class="space-y-0">
    <li v-for="(ev, idx) in events" :key="ev.key" class="flex gap-3">
      <div class="flex flex-col items-center w-3 shrink-0">
        <span class="h-3 w-3 rounded-full bg-primary-500 border-2 border-white dark:border-slate-900 z-[1]" />
        <span v-if="idx < events.length - 1" class="w-0.5 flex-1 min-h-[1.25rem] bg-slate-200 dark:bg-slate-700" />
      </div>
      <div class="pb-4 flex-1 min-w-0">
        <div class="rounded-xl border border-slate-100 dark:border-slate-800 bg-white/80 dark:bg-slate-900/30 px-3 py-2">
          <div class="flex flex-wrap justify-between gap-2 font-medium text-slate-800 dark:text-slate-100">
            <span>{{ ev.title }}</span>
            <time class="text-xs text-slate-500 tabular-nums" :datetime="ev.iso ?? undefined">{{ ev.when }}</time>
          </div>
          <p class="text-xs text-slate-600 dark:text-slate-300 mt-1">{{ ev.detail }}</p>
        </div>
      </div>
    </li>
    <li v-if="!events.length" class="text-xs text-slate-500 ps-6">{{ emptyLabel }}</li>
  </ul>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { CustomerProfileActivityItem } from '@/types/customerProfile'

const props = defineProps<{
  items: { kind: string; label: string; item: CustomerProfileActivityItem | null }[]
  emptyLabel: string
  localeLang: string
}>()

const events = computed(() => {
  const rows: { key: string; title: string; when: string; detail: string; iso: string | null }[] = []
  for (const { kind, label, item } of props.items) {
    if (!item?.occurred_at) continue
    const when = new Date(item.occurred_at).toLocaleString(props.localeLang === 'ar' ? 'ar-SA' : undefined)
    const detail = [item.reference, item.status, item.subtitle].filter(Boolean).join(' · ')
    rows.push({
      key: `${kind}-${item.id}`,
      title: label,
      when,
      detail: detail || '—',
      iso: item.occurred_at,
    })
  }
  rows.sort((a, b) => {
    if (!a.iso || !b.iso) return 0
    return a.iso < b.iso ? 1 : a.iso > b.iso ? -1 : 0
  })
  return rows
})
</script>
