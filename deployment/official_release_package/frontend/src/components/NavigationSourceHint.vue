<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { RouterLink } from 'vue-router'
import { SignalIcon, XMarkIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline'

const route = useRoute()
const dismissed = ref(false)

const show = computed(
  () =>
    route.query.source === 'command-center' &&
    route.name !== 'internal.intelligence' &&
    !dismissed.value,
)

watch(
  () => route.fullPath,
  () => {
    dismissed.value = false
  },
)

function dismiss() {
  dismissed.value = true
}
</script>

<template>
  <div
    v-if="show"
    class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 rounded-xl border border-primary-300/70 dark:border-primary-700/50 bg-gradient-to-l from-primary-50/95 to-white dark:from-primary-950/40 dark:to-slate-900/80 px-4 py-3.5 text-sm text-primary-950 dark:text-primary-50 shadow-sm"
    role="status"
  >
    <div class="flex items-start gap-3 min-w-0">
      <div
        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-100 dark:bg-primary-900/50 text-primary-700 dark:text-primary-200"
      >
        <SignalIcon class="w-5 h-5" aria-hidden="true" />
      </div>
      <div class="min-w-0 space-y-1">
        <p class="font-semibold text-gray-900 dark:text-slate-100 leading-snug">تنقّل سياقي — المرحلة 5</p>
        <p class="text-xs text-gray-600 dark:text-slate-400 leading-relaxed">
          أتيت من <strong class="font-medium text-gray-800 dark:text-slate-300">مركز العمليات الذكي</strong> عبر رابط
          قراءة فقط. لا يُنفَّذ أي أمر على الخادم بسبب هذا الرابط؛ يمكنك متابعة عملك المعتاد في هذه الصفحة بأمان.
        </p>
        <RouterLink
          :to="{ name: 'internal.intelligence' }"
          class="inline-flex items-center gap-1 mt-2 text-xs font-bold text-primary-700 dark:text-primary-300 hover:text-primary-900 dark:hover:text-primary-100 transition-colors"
        >
          <ArrowLeftIcon class="w-3.5 h-3.5 rtl:rotate-180" aria-hidden="true" />
          العودة إلى مركز العمليات الذكي
        </RouterLink>
      </div>
    </div>
    <button
      type="button"
      class="self-start sm:self-center flex-shrink-0 p-2 rounded-lg text-gray-500 dark:text-slate-400 hover:text-gray-800 dark:hover:text-slate-200 hover:bg-gray-100/80 dark:hover:bg-slate-800/80 transition-colors"
      aria-label="إخفاء التلميح"
      @click="dismiss"
    >
      <XMarkIcon class="w-5 h-5" />
    </button>
  </div>
</template>
