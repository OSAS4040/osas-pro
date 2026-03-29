import { ref } from 'vue'

export interface GeoPosition {
  lat: number
  lng: number
  accuracy: number
  timestamp: number
}

export function useGeolocation() {
  const position = ref<GeoPosition | null>(null)
  const error    = ref<string | null>(null)
  const loading  = ref(false)

  async function getCurrentPosition(): Promise<GeoPosition | null> {
    if (!navigator.geolocation) {
      error.value = 'الجهاز لا يدعم تحديد الموقع'
      return null
    }
    loading.value = true
    error.value = null
    return new Promise(resolve => {
      navigator.geolocation.getCurrentPosition(
        pos => {
          const p: GeoPosition = {
            lat: pos.coords.latitude,
            lng: pos.coords.longitude,
            accuracy: pos.coords.accuracy,
            timestamp: pos.timestamp,
          }
          position.value = p
          loading.value = false
          resolve(p)
        },
        err => {
          const msgs: Record<number, string> = {
            1: 'تم رفض الإذن — يرجى تفعيل الموقع',
            2: 'تعذّر تحديد الموقع',
            3: 'انتهت مهلة تحديد الموقع',
          }
          error.value = msgs[err.code] ?? 'خطأ في تحديد الموقع'
          loading.value = false
          resolve(null)
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
      )
    })
  }

  function isWithinGeofence(lat: number, lng: number, centerLat: number, centerLng: number, radiusMeters: number): boolean {
    const R = 6371000
    const dLat = ((lat - centerLat) * Math.PI) / 180
    const dLng = ((lng - centerLng) * Math.PI) / 180
    const a = Math.sin(dLat / 2) ** 2 + Math.cos((centerLat * Math.PI) / 180) * Math.cos((lat * Math.PI) / 180) * Math.sin(dLng / 2) ** 2
    const dist = R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
    return dist <= radiusMeters
  }

  return { position, error, loading, getCurrentPosition, isWithinGeofence }
}
