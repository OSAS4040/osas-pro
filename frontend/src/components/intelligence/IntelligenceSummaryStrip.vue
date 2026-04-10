<script setup lang="ts">
import { BoltIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'

defineProps<{
  totalNow: number
  totalNext: number
  totalWatch: number
  refreshedAt: Date | null
  traceId: string | null
  loading: boolean
  /** خلفية تحديث دون إخفاء الصفحة */
  refreshing?: boolean
  /** لم يصل بعد payload مركز القيادة — الأرقام 0 مؤقتة حتى اكتمال التحميل */
  summaryDegraded?: boolean
}>()

defineEmits<{
  refresh: []
}>()
</script>

<template>
  <div
    class="relative flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-4 rounded-2xl bg-gradient-to-l from-primary-600 to-primary-700 dark:from-primary-800 dark:to-primary-900 text-white px-5 py-4 shadow-lg overflow-hidden"
  >
    <div
      v-if="refreshing"
      class="absolute inset-x-0 top-0 h-0.5 bg-white/20"
      aria-hidden="true"
    >
      <div class="h-full w-1/3 bg-white/90 animate-[intelShimmer_1.1s_ease-in-out_infinite]" />
    </div>
    <div class="flex flex-wrap gap-6 flex-1 items-start">
      <p
        v-if="summaryDegraded"
        class="w-full text-[11px] font-medium text-primary-100/95 bg-white/10 rounded-lg px-2 py-1 border border-white/15 mb-1"
      >
        جاري تحميل أرقام المناطق (الآن / التالي / مراقبة)…
      </p>
      <div>
        <p class="text-xs font-medium text-primary-100/90 uppercase tracking-wide">الآن</p>
        <p class="text-2xl font-bold tabular-nums">{{ totalNow }}</p>
      </div>
      <div>
        <p class="text-xs font-medium text-primary-100/90 uppercase tracking-wide">التالي</p>
        <p class="text-2xl font-bold tabular-nums">{{ totalNext }}</p>
      </div>
      <div>
        <p class="text-xs font-medium text-primary-100/90 uppercase tracking-wide">مراقبة</p>
        <p class="text-2xl font-bold tabular-nums">{{ totalWatch }}</p>
      </div>
      <div class="min-w-[140px]">
        <p class="text-xs font-medium text-primary-100/90 uppercase tracking-wide">وضع القراءة</p>
        <p class="text-sm font-semibold flex items-center gap-1 mt-1">
          <BoltIcon class="w-4 h-4" aria-hidden="true" />
          قراءة فقط — لا تنفيذ تلقائي
        </p>
      </div>
    </div>

    <div class="flex flex-col sm:items-end gap-2 text-sm text-primary-100/95">
      <button
        type="button"
        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white/15 hover:bg-white/25 transition-colors text-white text-sm font-medium disabled:opacity-50"
        :disabled="loading || refreshing"
        @click="$emit('refresh')"
      >
        <ArrowPathIcon class="w-4 h-4" :class="{ 'animate-spin': loading || refreshing }" />
        تحديث
      </button>
      <p v-if="refreshedAt" class="text-xs opacity-90">
        آخر تحديث: {{ refreshedAt.toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit' }) }}
      </p>
      <p
        v-if="traceId"
        dir="ltr"
        class="text-xs font-mono opacity-75 text-left break-all max-w-[min(100%,280px)] sm:max-w-[320px]"
        :title="traceId"
      >
        تتبع: {{ traceId }}
      </p>
    </div>
  </div>
</template>

<style scoped>
@keyframes intelShimmer {
  0% {
    transform: translateX(-100%);
  }
  100% {
    transform: translateX(400%);
  }
}
</style>
