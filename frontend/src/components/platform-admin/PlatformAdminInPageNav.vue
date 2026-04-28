<template>
  <nav
    v-if="items.length > 0"
    class="platform-admin-in-page-nav mb-6 rounded-2xl border border-slate-200/90 bg-white/90 px-2 py-2 shadow-sm backdrop-blur-sm dark:border-slate-700/80 dark:bg-slate-900/75"
    :aria-label="ariaLabel"
    data-testid="platform-admin-in-page-nav"
  >
    <p class="mb-2 px-2 text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
      {{ sectionHint }}
    </p>
    <ul class="flex flex-wrap gap-1.5" role="list">
      <li v-for="item in items" :key="item.id">
        <button
          :id="`platform-in-page-nav-${item.id}`"
          type="button"
          class="rounded-xl border border-transparent px-3 py-1.5 text-[11px] font-bold text-slate-700 transition-colors hover:border-primary-300 hover:bg-primary-50 hover:text-primary-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:text-slate-200 dark:hover:border-primary-700 dark:hover:bg-primary-900/40 dark:hover:text-primary-100"
          :aria-controls="item.id"
          @click="scrollToAnchor(item.id)"
        >
          {{ item.label }}
        </button>
      </li>
    </ul>
  </nav>
</template>

<script setup lang="ts">
import type { PlatformInPageNavItem } from '@/config/platformAdminInPageNav'

withDefaults(
  defineProps<{
    items: PlatformInPageNavItem[]
    /** وصف للقارئ الشاشي */
    ariaLabel?: string
    /** سطر توضيحي صغير فوق الأزرار */
    sectionHint?: string
  }>(),
  {
    ariaLabel: 'فهرس أقسام هذه الصفحة',
    sectionHint: 'انتقال سريع داخل الصفحة',
  },
)

function scrollToAnchor(id: string): void {
  const el = typeof document !== 'undefined' ? document.getElementById(id) : null
  if (!el || !(el instanceof HTMLElement)) return
  let addedTabindex = false
  if (!el.hasAttribute('tabindex')) {
    el.setAttribute('tabindex', '-1')
    addedTabindex = true
  }
  el.scrollIntoView({ behavior: 'smooth', block: 'start' })
  window.setTimeout(() => {
    try {
      el.focus({ preventScroll: true })
    } catch {
      /* متصفحات قديمة */
    }
    if (addedTabindex) el.removeAttribute('tabindex')
  }, 420)
}
</script>
