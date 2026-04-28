<template>
  <div
    class="bg-white/5 hover:bg-white/[0.08] border border-white/10 hover:border-primary-500/40 rounded-2xl p-5 flex flex-col gap-3 transition-all duration-200 group cursor-pointer relative overflow-hidden"
    @click="$emit('details', plugin)"
  >
    <div v-if="isRecommended" class="absolute top-3 right-3 z-10 text-[10px] font-bold uppercase tracking-wide text-amber-200 bg-amber-500/25 border border-amber-400/40 px-2 py-0.5 rounded-full">
      موصى به
    </div>

    <div v-if="plugin.is_premium" class="absolute top-3 left-3 z-10 bg-gradient-to-r from-primary-500 to-primary-700 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">
      Pro
    </div>
    <div
      v-if="plugin.is_installed"
      class="absolute top-10 left-3 z-10 bg-green-500/20 border border-green-500/30 text-green-400 text-[10px] font-bold px-2 py-0.5 rounded-full"
    >
      مثبتة
    </div>

    <div class="flex items-start justify-between gap-2 pt-1">
      <div :class="categoryGradient" class="w-12 h-12 rounded-xl flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform shrink-0">
        <component :is="resolveIcon(plugin.icon)" class="w-6 h-6 text-white" />
      </div>
      <span class="text-[10px] text-slate-400 bg-white/5 px-2 py-0.5 rounded-full border border-white/10 whitespace-nowrap">{{ categoryLabel }}</span>
    </div>

    <div class="flex-1 space-y-1.5 min-h-[3.5rem]">
      <h3 class="text-white font-bold text-sm leading-snug">{{ plugin.name_ar }}</h3>
      <p class="text-slate-400 text-[11px] leading-relaxed line-clamp-2">{{ plugin.description_ar }}</p>
    </div>

    <div v-if="plugin.tags?.length" class="flex flex-wrap gap-1">
      <span
        v-for="t in (plugin.tags as string[]).slice(0, 4)"
        :key="t"
        class="text-[9px] px-1.5 py-0.5 rounded-md bg-primary-500/15 text-primary-200/90 border border-primary-500/20"
      >{{ tagLabel(t) }}</span>
    </div>

    <div class="flex items-end justify-between text-[11px] gap-2 pt-1 border-t border-white/10">
      <span class="text-yellow-400/90 font-medium shrink-0">⭐ {{ Number(plugin.rating).toFixed(1) }}</span>
      <div v-if="priceBreakdown.isFree" class="text-green-400 font-bold">مجاني</div>
      <div v-else class="text-end leading-tight">
        <div class="text-pink-300 font-bold">{{ formatPriceLabel }}</div>
        <div class="text-[9px] text-slate-500">شامل {{ formatSar(priceBreakdown.platformFee) }} للمنصة</div>
      </div>
    </div>

    <div class="flex gap-2 pt-1" @click.stop>
      <button
        v-if="!plugin.is_installed"
        type="button"
        class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-primary-600 py-2 text-xs font-semibold text-white transition-colors hover:bg-primary-700"
        @click="$emit('install', plugin)"
      >
        <ArrowDownTrayIcon class="w-3.5 h-3.5" />
        تثبيت
      </button>
      <button
        v-else
        type="button"
        class="flex-1 py-2 bg-green-500/20 hover:bg-green-500/30 border border-green-500/30 rounded-xl text-green-400 text-xs font-semibold transition-colors"
        @click="$emit('configure', plugin)"
      >
        إعدادات
      </button>
      <button
        v-if="plugin.is_installed"
        type="button"
        class="py-2 px-3 bg-red-500/10 hover:bg-red-500/20 border border-red-500/20 rounded-xl text-red-400 text-xs transition-colors"
        @click="$emit('uninstall', plugin)"
      >
        إزالة
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import {
  ArrowDownTrayIcon,
  CpuChipIcon,
  CurrencyDollarIcon,
  ShieldCheckIcon,
  ChatBubbleLeftRightIcon,
  CubeIcon,
  ChartBarIcon,
  FaceSmileIcon,
  BoltIcon,
  BuildingOffice2Icon,
  DocumentTextIcon,
  BellAlertIcon,
  BanknotesIcon,
  DocumentCheckIcon,
  ShieldExclamationIcon,
  Squares2X2Icon,
  DevicePhoneMobileIcon,
  MapIcon,
  PaintBrushIcon,
} from '@heroicons/vue/24/outline'
import { computed } from 'vue'
import { monthlyPricingWithPlatform, formatSar } from '@/utils/pluginPricing'

const props = defineProps<{ plugin: any }>()
defineEmits(['install', 'uninstall', 'details', 'configure'])

const priceBreakdown = computed(() => monthlyPricingWithPlatform(props.plugin.price_monthly))

const formatPriceLabel = computed(() => {
  if (priceBreakdown.value.isFree) return 'مجاني'
  return formatSar(priceBreakdown.value.totalMonthly) + ' /شهر'
})

const isRecommended = computed(() => {
  const r = Number(props.plugin.recommended_rank ?? 100)
  return r <= 8
})

const categoryGradient = computed(() => {
  const g: Record<string, string> = {
    ai: 'bg-gradient-to-br from-primary-600 to-primary-800',
    integration: 'bg-gradient-to-br from-blue-600 to-blue-800',
    analytics: 'bg-gradient-to-br from-emerald-600 to-emerald-800',
    ui: 'bg-gradient-to-br from-pink-600 to-pink-800',
  }
  return g[props.plugin.category] ?? 'bg-gradient-to-br from-slate-600 to-slate-800'
})

const categoryLabel = computed(() => {
  const l: Record<string, string> = {
    ai: 'ذكاء اصطناعي',
    integration: 'تكامل',
    analytics: 'تحليلات',
    ui: 'واجهة وتجربة',
  }
  return l[props.plugin.category] ?? props.plugin.category
})

function tagLabel(t: string): string {
  const m: Record<string, string> = {
    recommended: 'مختار',
    hr: 'موارد بشرية',
    qiwa: 'قوى',
    gosi: 'تأمينات',
    zatca: 'زاتكا',
    fleet: 'أسطول',
    alerts: 'تنبيهات',
    saudi: 'السعودية',
  }
  return m[t] ?? t
}

function resolveIcon(icon: string) {
  const m: Record<string, any> = {
    'cpu-chip': CpuChipIcon,
    'currency-dollar': CurrencyDollarIcon,
    'shield-check': ShieldCheckIcon,
    'chat-bubble-left-right': ChatBubbleLeftRightIcon,
    cube: CubeIcon,
    'chart-bar': ChartBarIcon,
    'face-smile': FaceSmileIcon,
    'chat-bubble-oval-left': ChatBubbleLeftRightIcon,
    'building-office-2': BuildingOffice2Icon,
    'document-text': DocumentTextIcon,
    'bell-alert': BellAlertIcon,
    banknotes: BanknotesIcon,
    'document-check': DocumentCheckIcon,
    'shield-exclamation': ShieldExclamationIcon,
    'squares-2x2': Squares2X2Icon,
    'device-phone-mobile': DevicePhoneMobileIcon,
    map: MapIcon,
    'paint-brush': PaintBrushIcon,
  }
  return m[icon] ?? BoltIcon
}
</script>
