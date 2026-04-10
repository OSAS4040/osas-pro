/**
 * يحوّل مسارات الملفات العامة (مثل /storage/...) إلى عنوان يصل للمتصفح حتى مع تشغيل Vite على :5173.
 * يستند إلى أصل VITE_API_BASE_URL عند تعيينه (مثل http://localhost/api/v1 → http://localhost).
 */
export function resolveApiOrigin(): string {
  const base = typeof import.meta.env.VITE_API_BASE_URL === 'string' ? import.meta.env.VITE_API_BASE_URL.trim() : ''
  if (base.startsWith('http://') || base.startsWith('https://')) {
    try {
      return new URL(base).origin
    } catch {
      /* fall through */
    }
  }
  if (typeof window !== 'undefined') {
    return window.location.origin
  }
  return ''
}

export function resolvePublicAssetUrl(url: string | null | undefined): string {
  if (url == null || url === '') return ''
  const s = String(url).trim()
  if (
    s.startsWith('http://') ||
    s.startsWith('https://') ||
    s.startsWith('data:') ||
    s.startsWith('blob:')
  ) {
    return s
  }
  if (s.startsWith('//')) {
    if (typeof window !== 'undefined') {
      return `${window.location.protocol}${s}`
    }
    return s
  }
  const origin = resolveApiOrigin()
  if (s.startsWith('/') && origin) {
    return `${origin}${s}`
  }
  return s
}
