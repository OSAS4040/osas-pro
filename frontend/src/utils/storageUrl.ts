/**
 * رابط ملف عام من الـ API (مثل Storage::disk('public')->url).
 * إذا كان مساراً نسبياً على نفس أصل الواجهة يُبنى الرابط للفتح في تبويب جديد.
 */
export function resolveStoragePublicUrl(url: string | undefined | null): string {
  if (!url) return '#'
  if (url.startsWith('http://') || url.startsWith('https://')) return url
  const path = url.startsWith('/') ? url : `/${url}`
  if (typeof window === 'undefined') return path
  return `${window.location.origin}${path}`
}
