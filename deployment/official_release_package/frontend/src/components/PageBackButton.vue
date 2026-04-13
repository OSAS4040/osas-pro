<template>
  <button
    type="button"
    class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 -mr-1 text-sm font-medium text-slate-600 transition-colors hover:bg-gray-100 hover:text-primary-700 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-primary-400"
    @click="goBack"
  >
    <ChevronRightIcon class="h-4 w-4 opacity-80 rtl:rotate-0 ltr:rotate-180" />
    رجوع
  </button>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import { ChevronRightIcon } from '@heroicons/vue/24/outline'

const props = withDefaults(
  defineProps<{
    /** المستوى الأعلى في مسار التنقل (من AppLayout عبر فتات الخبز) */
    fallbackTo?: string
  }>(),
  { fallbackTo: '/' },
)

const router = useRouter()

function goBack() {
  router.push(props.fallbackTo || '/')
}

</script>
