<template>
  <Teleport to="body">
    <div class="fixed top-5 left-5 z-[9999] flex flex-col gap-2 pointer-events-none" dir="rtl">
      <TransitionGroup name="toast">
        <div
          v-for="t in toasts"
          :key="t.id"
          class="pointer-events-auto flex items-start gap-3 min-w-[300px] max-w-sm px-4 py-3.5 rounded-xl shadow-lg border backdrop-blur-sm cursor-pointer select-none"
          :class="styles[t.type].container"
          @click="dismiss(t.id)"
        >
          <component :is="icons[t.type]" class="w-5 h-5 mt-0.5 flex-shrink-0" :class="styles[t.type].icon" />
          <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold" :class="styles[t.type].title">{{ t.title }}</p>
            <p v-if="t.message" class="text-xs mt-0.5 opacity-80" :class="styles[t.type].title">{{ t.message }}</p>
          </div>
          <button class="opacity-50 hover:opacity-100 transition-opacity text-xs mt-0.5" :class="styles[t.type].title">✕</button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import {
  CheckCircleIcon, XCircleIcon, ExclamationTriangleIcon, InformationCircleIcon,
} from '@heroicons/vue/24/solid'
import { useToast } from '@/composables/useToast'

const { toasts, dismiss } = useToast()

const icons: Record<string, any> = {
  success: CheckCircleIcon,
  error:   XCircleIcon,
  warning: ExclamationTriangleIcon,
  info:    InformationCircleIcon,
}

const styles: Record<string, Record<string, string>> = {
  success: { container: 'bg-green-50 border-green-200',   icon: 'text-green-500', title: 'text-green-900' },
  error:   { container: 'bg-red-50 border-red-200',       icon: 'text-red-500',   title: 'text-red-900'   },
  warning: { container: 'bg-amber-50 border-amber-200',   icon: 'text-amber-500', title: 'text-amber-900' },
  info:    { container: 'bg-blue-50 border-blue-200',     icon: 'text-blue-500',  title: 'text-blue-900'  },
}
</script>

<style scoped>
.toast-enter-active  { transition: all 0.35s cubic-bezier(0.22, 1, 0.36, 1); }
.toast-leave-active  { transition: all 0.25s ease-in; }
.toast-enter-from    { opacity: 0; transform: translateY(-12px) scale(0.96); }
.toast-leave-to      { opacity: 0; transform: translateX(-20px) scale(0.96); }
.toast-move          { transition: transform 0.3s ease; }
</style>
