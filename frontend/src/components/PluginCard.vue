<template>
  <div class="bg-white/5 hover:bg-white/8 border border-white/10 hover:border-purple-500/40 rounded-2xl p-5 flex flex-col gap-4 transition-all duration-200 group cursor-pointer relative overflow-hidden"
       @click="$emit('details', plugin)">
    <!-- Premium badge -->
    <div v-if="plugin.is_premium" class="absolute top-3 left-3 bg-gradient-to-r from-pink-500 to-purple-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">
      PRO
    </div>
    <!-- Installed badge -->
    <div v-if="plugin.is_installed" class="absolute top-3 left-3 bg-green-500/20 border border-green-500/30 text-green-400 text-xs font-bold px-2 py-0.5 rounded-full">
      ✓ مثبتة
    </div>

    <!-- Icon + Category -->
    <div class="flex items-start justify-between">
      <div :class="categoryGradient" class="w-12 h-12 rounded-xl flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform">
        <component :is="resolveIcon(plugin.icon)" class="w-6 h-6 text-white" />
      </div>
      <span class="text-xs text-slate-500 bg-white/5 px-2 py-0.5 rounded-full border border-white/10">{{ categoryLabel }}</span>
    </div>

    <!-- Name + Description -->
    <div class="flex-1 space-y-1.5">
      <h3 class="text-white font-bold text-base leading-tight">{{ plugin.name_ar }}</h3>
      <p class="text-slate-400 text-xs leading-relaxed line-clamp-2">{{ plugin.description_ar }}</p>
    </div>

    <!-- Rating + Price -->
    <div class="flex items-center justify-between text-xs">
      <span class="text-yellow-400 font-medium">⭐ {{ plugin.rating }}</span>
      <span :class="plugin.price_monthly > 0 ? 'text-pink-400' : 'text-green-400'" class="font-bold">
        {{ plugin.price_monthly > 0 ? plugin.price_monthly + ' ﷼/شهر' : 'مجاني' }}
      </span>
    </div>

    <!-- Actions -->
    <div class="flex gap-2 pt-1" @click.stop>
      <button v-if="!plugin.is_installed"
        @click="$emit('install', plugin)"
        class="flex-1 py-2 bg-purple-600 hover:bg-purple-700 rounded-xl text-white text-xs font-semibold transition-colors flex items-center justify-center gap-1.5">
        <ArrowDownTrayIcon class="w-3.5 h-3.5" />
        تثبيت
      </button>
      <button v-else
        @click="$emit('configure', plugin)"
        class="flex-1 py-2 bg-green-500/20 hover:bg-green-500/30 border border-green-500/30 rounded-xl text-green-400 text-xs font-semibold transition-colors">
        ⚙ إعدادات
      </button>
      <button v-if="plugin.is_installed"
        @click="$emit('uninstall', plugin)"
        class="py-2 px-3 bg-red-500/10 hover:bg-red-500/20 border border-red-500/20 rounded-xl text-red-400 text-xs transition-colors">
        إزالة
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import {
  ArrowDownTrayIcon,
  CpuChipIcon, CurrencyDollarIcon, ShieldCheckIcon, ChatBubbleLeftRightIcon,
  CubeIcon, ChartBarIcon, FaceSmileIcon, BoltIcon
} from '@heroicons/vue/24/outline'
import { computed } from 'vue'

const props = defineProps<{ plugin: any }>()
defineEmits(['install','uninstall','details','configure'])

const categoryGradient = computed(() => {
  const g: Record<string, string> = {
    ai: 'bg-gradient-to-br from-purple-600 to-purple-800',
    integration: 'bg-gradient-to-br from-blue-600 to-blue-800',
    analytics: 'bg-gradient-to-br from-green-600 to-green-800',
    ui: 'bg-gradient-to-br from-pink-600 to-pink-800',
  }
  return g[props.plugin.category] ?? 'bg-gradient-to-br from-slate-600 to-slate-800'
})

const categoryLabel = computed(() => {
  const l: Record<string, string> = {
    ai: '🧠 ذكاء اصطناعي', integration: '🔌 تكامل',
    analytics: '📊 تحليلات', ui: '🎨 واجهة',
  }
  return l[props.plugin.category] ?? props.plugin.category
})

function resolveIcon(icon: string) {
  const m: Record<string, any> = {
    'cpu-chip': CpuChipIcon, 'currency-dollar': CurrencyDollarIcon,
    'shield-check': ShieldCheckIcon, 'chat-bubble-left-right': ChatBubbleLeftRightIcon,
    'cube': CubeIcon, 'chart-bar': ChartBarIcon,
    'face-smile': FaceSmileIcon, 'chat-bubble-oval-left': ChatBubbleLeftRightIcon,
  }
  return m[icon] ?? BoltIcon
}
</script>
