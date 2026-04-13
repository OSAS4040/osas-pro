/**
 * تحميل Google Maps JavaScript API مرة واحدة (مفتاح من VITE_GOOGLE_MAPS_API_KEY).
 */
let loadPromise: Promise<void> | null = null

export function getGoogleMapsApiKey(): string {
  return (import.meta.env.VITE_GOOGLE_MAPS_API_KEY as string | undefined)?.trim() ?? ''
}

export async function ensureGoogleMapsLoaded(): Promise<any> {
  const key = getGoogleMapsApiKey()
  if (!key) {
    return null
  }
  if (typeof window === 'undefined') {
    return null
  }
  if (window.google?.maps) {
    return window.google
  }
  if (!loadPromise) {
    const cbName = `__workshopGoogleMaps_${Math.random().toString(36).slice(2)}`
    loadPromise = new Promise<void>((resolve, reject) => {
      (window as unknown as Record<string, () => void>)[cbName] = () => {
        delete (window as unknown as Record<string, unknown>)[cbName]
        resolve()
      }
      const script = document.createElement('script')
      script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}&callback=${cbName}`
      script.async = true
      script.defer = true
      script.onerror = () => {
        delete (window as unknown as Record<string, unknown>)[cbName]
        loadPromise = null
        reject(new Error('فشل تحميل خرائط Google'))
      }
      document.head.appendChild(script)
    })
  }
  try {
    await loadPromise
  } catch {
    return null
  }
  return window.google ?? null
}
