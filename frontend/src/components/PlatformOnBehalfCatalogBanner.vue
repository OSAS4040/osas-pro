<template>
  <div
    v-if="show"
    class="no-print rounded-xl border border-amber-200/90 bg-amber-50/80 p-4 shadow-sm dark:border-amber-900/50 dark:bg-amber-950/30"
    :dir="langInfo.dir"
  >
    <h2 class="text-sm font-bold text-amber-950 dark:text-amber-100">
      {{ t('providerPortal.executionHub.catalogDelegationTitle') }}
    </h2>
    <p class="mt-1 text-xs text-amber-900/80 dark:text-amber-200/90">
      {{ t('providerPortal.executionHub.catalogDelegationHint') }}
    </p>
    <p class="mt-2 text-xs font-medium text-primary-800 dark:text-primary-200">
      {{
        t('providerPortal.executionHub.delegationActive', {
          name: displayName,
        })
      }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useLocale } from '@/composables/useLocale'
import { usePlatformOnBehalfCatalog } from '@/composables/usePlatformOnBehalfCatalog'

const auth = useAuthStore()
const { t, langInfo } = useLocale()
const { onBehalfId, syncFromStorage, loadPartnerCompanies, isDelegating, selectedProviderName } =
  usePlatformOnBehalfCatalog()

const show = computed(() => Boolean(auth.isPlatform) && isDelegating.value)

const displayName = computed(() => {
  const n = selectedProviderName.value
  if (n) return n
  return onBehalfId.value > 0 ? `#${onBehalfId.value}` : '—'
})

function onWindowFocus(): void {
  syncFromStorage()
}

onMounted(() => {
  syncFromStorage()
  void loadPartnerCompanies()
  window.addEventListener('focus', onWindowFocus)
})

onUnmounted(() => {
  window.removeEventListener('focus', onWindowFocus)
})
</script>
