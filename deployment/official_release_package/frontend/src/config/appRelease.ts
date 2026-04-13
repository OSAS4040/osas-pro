/** بيانات النشر — تُحقَن عند `vite build` فقط (ثوابت في الحزمة). لا تُحسب في المتصفح. */

export const APP_VERSION = __APP_VERSION__
export const APP_BUILD_TIME = __APP_BUILD_TIME__
export const GIT_COMMIT = __GIT_COMMIT__
export const GIT_BRANCH = __GIT_BRANCH__
export const DEPLOY_ENV = __DEPLOY_ENV__

export const buildInfo = {
  version: APP_VERSION,
  buildTime: APP_BUILD_TIME,
  commit: GIT_COMMIT,
  branch: GIT_BRANCH,
  environment: DEPLOY_ENV,
} as const

export function shortVersionLabel(version: string): string {
  const parts = version.split('.').filter(Boolean)
  if (parts.length >= 2) {
    return `${parts[0]}.${parts[1]}`
  }
  return version || '—'
}

/** عرض بشري للوقت UTC (نفس سطر الـ build proof) */
export function buildTimeUtcDisplay(iso: string): string {
  try {
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return iso

    return `${d.toISOString().replace('T', ' ').slice(0, 19)} UTC`
  } catch {
    return iso
  }
}

const ENV_LABEL_AR: Record<string, string> = {
  production: 'إنتاج',
  staging: 'Staging',
  local: 'محلي',
  development: 'تطوير',
}

export function releaseEnvironmentLabel(): string {
  return ENV_LABEL_AR[DEPLOY_ENV] ?? DEPLOY_ENV
}

/** سطر واحد للنسخ وللدعم — Operational / deployment proof */
export function releaseCopyLine(): string {
  const t = buildTimeUtcDisplay(APP_BUILD_TIME)

  return `Osas Pro · v${APP_VERSION} · ${t} · commit ${GIT_COMMIT} · ${GIT_BRANCH} · ${DEPLOY_ENV}`
}
