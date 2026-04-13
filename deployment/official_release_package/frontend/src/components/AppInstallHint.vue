<template>
  <details
    class="group rounded-xl border border-slate-200/90 bg-slate-50/90 text-[11px] leading-relaxed text-slate-700 open:shadow-sm dark:border-slate-600 dark:bg-slate-900/40 dark:text-slate-200"
    :open="defaultOpen"
    :dir="i18n.dir"
  >
    <summary
      class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-3 text-sm font-bold text-slate-800 marker:content-none dark:text-slate-100 [&::-webkit-details-marker]:hidden"
    >
      <span>{{ pi('summaryTitle') }}</span>
      <span
        class="shrink-0 rounded-lg bg-slate-200/80 px-2 py-0.5 text-[10px] font-semibold text-slate-600 group-open:hidden dark:bg-slate-700 dark:text-slate-300"
      >
        {{ pi('tapForHelp') }}
      </span>
      <span class="hidden text-[10px] font-normal text-slate-500 group-open:inline dark:text-slate-400">{{ pi('hide') }}</span>
    </summary>
    <div class="space-y-2 border-t border-slate-200/80 px-4 pb-3 pt-2 dark:border-slate-600">
      <p class="text-slate-600 dark:text-slate-300">
        {{ pi('introPart1') }}<strong>{{ pi('introStrong') }}</strong>{{ pi('introPart2') }}
      </p>
      <p class="break-all font-mono text-[10px] text-slate-500 dark:text-slate-400" dir="ltr">
        {{ originUrl || '…' }}
      </p>
      <div class="flex flex-wrap gap-2">
        <button
          type="button"
          class="min-h-11 rounded-lg border border-slate-300 bg-white px-3 py-2 text-[11px] font-semibold text-slate-800 transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
          @click="onCopy"
        >
          {{ copied ? pi('copied') : pi('copyLink') }}
        </button>
        <button
          v-if="canPromptInstall"
          type="button"
          class="min-h-11 rounded-lg bg-primary-600 px-3 py-2 text-[11px] font-semibold text-white transition hover:bg-primary-700"
          @click="doInstall"
        >
          {{ pi('installPrompt') }}
        </button>
      </div>
      <p class="border-t border-slate-200/80 pt-2 text-slate-500 dark:border-slate-600 dark:text-slate-400">
        <strong class="text-slate-700 dark:text-slate-200">{{ pi('iphoneLabel') }}</strong>
        {{ pi('iphoneHint') }}
      </p>
      <p class="text-slate-500 dark:text-slate-400">
        <strong class="text-slate-700 dark:text-slate-200">{{ pi('androidLabel') }}</strong>
        {{ pi('androidHint') }}
      </p>
    </div>
  </details>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { usePwaInstall } from '@/composables/usePwaInstall'
import { useI18nStore } from '@/stores/i18n'

withDefaults(
  defineProps<{
    /** عند true يبدأ القسم مفتوحاً (مثلاً صفحة هبوط) */
    defaultOpen?: boolean
  }>(),
  { defaultOpen: false },
)

const i18n = useI18nStore()

function pi(key: string): string {
  return i18n.t(`pwaInstall.${key}`)
}

const { originUrl, canPromptInstall, promptInstall, copyOriginUrl } = usePwaInstall()
const copied = ref(false)

let copyTimer: ReturnType<typeof setTimeout> | null = null

async function onCopy(): Promise<void> {
  const ok = await copyOriginUrl()
  copied.value = ok
  if (copyTimer) clearTimeout(copyTimer)
  copyTimer = setTimeout(() => {
    copied.value = false
    copyTimer = null
  }, 2000)
}

async function doInstall(): Promise<void> {
  await promptInstall()
}
</script>
