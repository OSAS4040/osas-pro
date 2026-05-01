<template>
  <div class="mt-4 flex flex-wrap items-center gap-2">
    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-bold" :class="chipClass">
      <span class="h-2 w-2 rounded-full" :class="dotClass" />
      {{ label }}
    </span>
    <span class="text-[11px] text-slate-600 dark:text-slate-400">
      قاعدة البيانات: {{ health.api === 'ok' ? 'سليم' : 'منخفض' }} · الطابور: {{ health.queue === 'ok' ? 'سليم' : 'ضغط' }}
      <template v-if="health.redis_ok !== undefined">
        · ذاكرة التخزين المؤقت: {{ health.redis_ok ? 'متصل' : 'غير متاح' }}
      </template>
      <template v-if="health.queue_pending_count != null">
        · مهام في الانتظار: {{ health.queue_pending_count.toLocaleString('ar-SA') }}
      </template>
      <template v-if="health.failed_jobs != null">
        · فاشلة: {{ health.failed_jobs.toLocaleString('ar-SA') }}
      </template>
    </span>
  </div>
  <p v-if="health.scheduler_note_ar" class="mt-2 text-[11px] leading-relaxed text-slate-600 dark:text-slate-400">
    الجدولة:
    {{ health.scheduler_last_run_at ? health.scheduler_last_run_at : 'آخر تشغيل غير مسجّل في التطبيق' }}
    — {{ health.scheduler_note_ar }}
  </p>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { PlatformAdminOverviewHealth } from '@/types/platformAdminOverview'

const props = defineProps<{ health: PlatformAdminOverviewHealth }>()

const label = computed(() =>
  props.health.trend === 'degraded' ? 'يتطلّب انتباهاً تشغيلياً' : 'الحالة التشغيلية مستقرة',
)
const chipClass = computed(() =>
  props.health.trend === 'degraded'
    ? 'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100'
    : 'border-emerald-200 bg-emerald-50 text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100',
)
const dotClass = computed(() => (props.health.trend === 'degraded' ? 'bg-amber-500' : 'bg-emerald-500'))
</script>
