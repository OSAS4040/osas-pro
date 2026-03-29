<template>
  <div v-if="auth.isStaff && sub.hasFeature('fleet')" class="relative" ref="container">
    <button @click="open = !open"
      class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors">
      <ArrowsRightLeftIcon class="w-4 h-4" />
      تبديل البوابة
      <ChevronDownIcon class="w-3 h-3" :class="open ? 'rotate-180' : ''" />
    </button>

    <Transition
      enter-active-class="transition ease-out duration-100"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition ease-in duration-75"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95">
      <div v-if="open"
        class="absolute left-0 top-10 w-44 bg-white rounded-xl shadow-lg border border-gray-200 py-1 z-50">
        <button @click="switchTo('/')"
          class="w-full text-right flex items-center gap-2.5 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
          <WrenchScrewdriverIcon class="w-4 h-4 text-blue-600" />
          بوابة فريق العمل
        </button>
        <button @click="switchTo('/fleet-portal')"
          class="w-full text-right flex items-center gap-2.5 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
          <TruckIcon class="w-4 h-4 text-teal-600" />
          بوابة الأسطول
        </button>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { TruckIcon, WrenchScrewdriverIcon, ArrowsRightLeftIcon, ChevronDownIcon } from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'
import { useSubscriptionStore } from '@/stores/subscription'

const auth   = useAuthStore()
const sub    = useSubscriptionStore()
const router = useRouter()
const open   = ref(false)
const container = ref<HTMLElement | null>(null)

function switchTo(path: string) {
  open.value = false
  router.push(path)
}

function handleClickOutside(e: MouseEvent) {
  if (container.value && !container.value.contains(e.target as Node)) {
    open.value = false
  }
}

onMounted(() => document.addEventListener('click', handleClickOutside))
onUnmounted(() => document.removeEventListener('click', handleClickOutside))
</script>
