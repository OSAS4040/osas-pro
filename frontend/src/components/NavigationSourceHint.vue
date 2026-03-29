<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { SignalIcon } from '@heroicons/vue/24/outline'

const route = useRoute()

const show = computed(
  () =>
    route.query.source === 'command-center' && route.name !== 'internal.intelligence',
)
</script>

<template>
  <div
    v-if="show"
    class="flex items-start gap-3 rounded-xl border border-primary-200 dark:border-primary-800/60 bg-primary-50/90 dark:bg-primary-950/30 px-4 py-3 text-sm text-primary-900 dark:text-primary-100"
    role="status"
  >
    <SignalIcon class="w-5 h-5 flex-shrink-0 text-primary-600 dark:text-primary-400 mt-0.5" aria-hidden="true" />
    <div>
      <p class="font-semibold">عرض سياقي من مركز العمليات الذكي</p>
      <p class="text-xs text-primary-800/85 dark:text-primary-200/80 mt-1 leading-relaxed">
        وصلت من لوحة قراءة فقط — لا يتم تنفيذ أي إجراء تلقائياً من الرابط.
      </p>
    </div>
  </div>
</template>
