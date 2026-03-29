/**
 * Build-time feature flags (Vite). Default off unless explicitly enabled.
 */
export const featureFlags = {
  /** Phase 4 Smart Command Center — requires backend INTELLIGENT_COMMAND_CENTER_ENABLED + Phase 2 internal APIs. */
  intelligenceCommandCenter: import.meta.env.VITE_INTELLIGENCE_COMMAND_CENTER === 'true',
}
