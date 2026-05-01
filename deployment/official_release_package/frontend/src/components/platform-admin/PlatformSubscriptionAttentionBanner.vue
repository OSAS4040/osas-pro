<template>
  <div
    v-if="auth.hasPermission('platform.subscription.manage') && injected && total > 0"
    class="mb-6 rounded-xl border border-amber-300/80 bg-amber-50/90 px-4 py-3 shadow-sm dark:border-amber-800/60 dark:bg-amber-950/40"
  >
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h2 class="text-sm font-bold text-amber-950 dark:text-amber-100">تنبيهات طلبات الاشتراكات</h2>
        <p class="mt-1 text-xs leading-relaxed text-amber-900/90 dark:text-amber-100/90">
          يوجد {{ total }} طلباً أو حالة تحتاج تدخلاً من مشغّل المنصة الآن.
        </p>
        <ul class="mt-2 flex flex-wrap gap-3 text-[11px] font-semibold text-amber-950 dark:text-amber-50">
          <li v-if="s?.awaiting_review">بانتظار المراجعة: {{ s.awaiting_review }}</li>
          <li v-if="s?.matched_pending_final_approval">بانتظار الموافقة النهائية: {{ s.matched_pending_final_approval }}</li>
          <li v-if="s?.pending_transfer_with_submission">تحويل مبدئي ببيانات: {{ s.pending_transfer_with_submission }}</li>
        </ul>
      </div>
      <div class="flex flex-col gap-2">
        <RouterLink
          to="/admin/subscriptions"
          class="rounded-lg bg-primary-600 px-3 py-2 text-center text-xs font-bold text-white shadow hover:bg-primary-700"
        >
          فتح طابور الطلبات
        </RouterLink>
        <RouterLink
          to="/admin/subscriptions/control"
          class="rounded-lg border border-amber-700/40 px-3 py-2 text-center text-xs font-bold text-amber-950 hover:bg-amber-100 dark:border-amber-400/40 dark:text-amber-100 dark:hover:bg-amber-900/40"
        >
          لوحة المؤشرات
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, inject } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { platformSubscriptionAttentionKey } from './PlatformSubscriptionAttentionInjectKey'

const auth = useAuthStore()
const injected = inject(platformSubscriptionAttentionKey, null)

const s = computed(() => injected?.summary.value ?? null)
const total = computed(() => Number(injected?.badgeCount.value ?? 0))
</script>
