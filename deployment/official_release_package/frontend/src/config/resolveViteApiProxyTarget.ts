/**
 * هدف بروكسي Vite لـ /api و /storage (يُستورد من vite.config.ts).
 */

function trimEnv(v: string | undefined): string {
  return typeof v === 'string' ? v.trim() : ''
}

export function resolveViteApiProxyTarget(
  insideDocker: boolean,
  fileEnv: Record<string, string>,
  procEnv: NodeJS.ProcessEnv,
): string {
  const fromFile = (k: string) => trimEnv(fileEnv[k])
  const fromProc = (k: string) => trimEnv(procEnv[k])

  let target: string

  if (insideDocker) {
    target =
      fromProc('VITE_DEV_PROXY_TARGET') ||
      fromProc('VITE_API_URL') ||
      fromFile('VITE_DEV_PROXY_TARGET') ||
      fromFile('VITE_API_URL') ||
      'http://nginx'
  } else {
    target =
      fromFile('VITE_DEV_PROXY_TARGET') ||
      fromFile('VITE_API_URL') ||
      fromProc('VITE_DEV_PROXY_TARGET') ||
      fromProc('VITE_API_URL') ||
      // Default: Docker Compose serves API on nginx :80. Use :8000 only with php artisan serve (set in .env).
      'http://127.0.0.1'
  }

  if (insideDocker && /127\.0\.0\.1|localhost/i.test(target) && !/host\.docker\.internal/i.test(target)) {
    return 'http://nginx'
  }

  return target
}
