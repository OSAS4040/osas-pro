<template>
  <Teleport to="body">
    <Transition name="confirm-fade">
      <div
        v-if="appConfirmDialog.visible"
        data-print-chrome
        class="print:hidden fixed inset-0 z-[10000] flex items-end justify-center p-4 sm:items-center"
        :dir="locale.langInfo.value.dir"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
      >
        <div
          class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm"
          aria-hidden="true"
          @click="onBackdrop"
        />
        <div
          class="relative w-full max-w-md overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-2xl dark:border-slate-600 dark:bg-slate-800"
          @click.stop
        >
          <div
            class="flex items-start gap-3 border-b px-5 py-4"
            :class="headerBorderClass"
          >
            <div
              class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl"
              :class="iconWrapClass"
            >
              <component :is="headerIcon" class="h-5 w-5" :class="iconClass" />
            </div>
            <div class="min-w-0 flex-1 pt-0.5">
              <h2 :id="titleId" class="text-base font-bold text-gray-900 dark:text-slate-100">
                {{ appConfirmDialog.title }}
              </h2>
              <p class="mt-1.5 whitespace-pre-wrap text-sm leading-relaxed text-gray-600 dark:text-slate-300">
                {{ appConfirmDialog.message }}
              </p>
            </div>
          </div>
          <div class="flex flex-col-reverse gap-2 border-t border-gray-100 bg-gray-50/80 px-4 py-3 dark:border-slate-700 dark:bg-slate-900/50 sm:flex-row sm:justify-end sm:gap-3">
            <button
              type="button"
              class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 sm:w-auto"
              @click="completeAppConfirm(false)"
            >
              {{ appConfirmDialog.cancelLabel }}
            </button>
            <button
              type="button"
              class="w-full rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-md transition-colors sm:w-auto"
              :class="confirmBtnClass"
              @click="completeAppConfirm(true)"
            >
              {{ appConfirmDialog.confirmLabel }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue'
import { ExclamationTriangleIcon, QuestionMarkCircleIcon } from '@heroicons/vue/24/solid'
import { useLocale } from '@/composables/useLocale'
import { appConfirmDialog, completeAppConfirm } from '@/services/appConfirmDialog'

const locale = useLocale()
const titleId = 'system-confirm-title'

const headerIcon = computed(() =>
  appConfirmDialog.variant === 'danger' ? ExclamationTriangleIcon : QuestionMarkCircleIcon,
)

const headerBorderClass = computed(() =>
  appConfirmDialog.variant === 'danger'
    ? 'border-red-100 bg-red-50/60 dark:border-red-900/40 dark:bg-red-950/25'
    : 'border-primary-100 bg-primary-50/50 dark:border-primary-900/40 dark:bg-primary-950/20',
)

const iconWrapClass = computed(() =>
  appConfirmDialog.variant === 'danger'
    ? 'bg-red-100 dark:bg-red-950/50'
    : 'bg-primary-100 dark:bg-primary-950/40',
)

const iconClass = computed(() =>
  appConfirmDialog.variant === 'danger'
    ? 'text-red-600 dark:text-red-400'
    : 'text-primary-600 dark:text-primary-400',
)

const confirmBtnClass = computed(() =>
  appConfirmDialog.variant === 'danger'
    ? 'bg-red-600 hover:bg-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500'
    : 'bg-primary-600 hover:bg-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500',
)

function onBackdrop() {
  completeAppConfirm(false)
}

function onKeydown(e: KeyboardEvent) {
  if (!appConfirmDialog.visible) return
  if (e.key === 'Escape') {
    e.preventDefault()
    completeAppConfirm(false)
  }
}

onMounted(() => {
  window.addEventListener('keydown', onKeydown)
})
onUnmounted(() => {
  window.removeEventListener('keydown', onKeydown)
})
</script>

<style scoped>
.confirm-fade-enter-active,
.confirm-fade-leave-active {
  transition: opacity 0.2s ease;
}
.confirm-fade-enter-active .relative,
.confirm-fade-leave-active .relative {
  transition: transform 0.2s ease, opacity 0.2s ease;
}
.confirm-fade-enter-from,
.confirm-fade-leave-to {
  opacity: 0;
}
.confirm-fade-enter-from .relative,
.confirm-fade-leave-to .relative {
  opacity: 0;
  transform: translateY(8px) scale(0.98);
}
</style>
