<script setup lang="ts">
import { defineAsyncComponent } from 'vue'
import { usePlatformExecutionPartner } from '@/composables/usePlatformExecutionPartner'
import ProviderOwnWalletView from './ProviderOwnWalletView.vue'

const { active: executionPartner } = usePlatformExecutionPartner()
const StaffWalletManagement = defineAsyncComponent(() => import('./WalletView.vue'))
</script>

<template>
  <ProviderOwnWalletView v-if="executionPartner" />
  <StaffWalletManagement v-else />
</template>
