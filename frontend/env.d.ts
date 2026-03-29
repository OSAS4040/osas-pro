/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_API_BASE_URL: string
  readonly VITE_APP_NAME: string
  /** Phase 4 Smart Command Center UI (must match backend intelligence flags). */
  readonly VITE_INTELLIGENCE_COMMAND_CENTER?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}

export {}

declare module 'vue-router' {
  interface RouteMeta {
    intelligenceCommandCenter?: boolean
  }
}
