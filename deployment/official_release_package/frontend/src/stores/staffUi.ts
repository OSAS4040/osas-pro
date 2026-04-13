import { defineStore } from 'pinia'
import { ref, watch } from 'vue'
import { featureFlags } from '@/config/featureFlags'

const STORAGE_KEY = 'staff_compact_ui'

function readStored(): boolean | null {
  try {
    const v = localStorage.getItem(STORAGE_KEY)
    if (v === '1') return true
    if (v === '0') return false
  } catch {
    /* ignore */
  }
  return null
}

export const useStaffUiStore = defineStore('staffUi', () => {
  const stored = readStored()
  const compactMode = ref<boolean>(stored !== null ? stored : featureFlags.staffCompactUiDefaultOn)

  watch(
    compactMode,
    (v) => {
      try {
        localStorage.setItem(STORAGE_KEY, v ? '1' : '0')
      } catch {
        /* ignore */
      }
    },
    { flush: 'post' },
  )

  function toggleCompact(): void {
    compactMode.value = !compactMode.value
  }

  function setCompact(v: boolean): void {
    compactMode.value = v
  }

  return { compactMode, toggleCompact, setCompact }
})
