<template>
  <div class="space-y-4" dir="rtl">
    <div class="rounded-2xl border border-gray-200/80 dark:border-slate-700 bg-white/80 dark:bg-slate-900/60 px-4 py-3">
      <h2 class="text-lg font-bold text-gray-900 dark:text-slate-100 flex items-center gap-2">
        <MapPinIcon class="w-5 h-5 text-primary-600" />
        مواقع التغطية
      </h2>
      <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">
        عرض مزودي الخدمة المتاحين للعميل مع التصنيف حسب الخدمات المفعلة، والإحداثيات، وخيارات التنقل والمشاركة.
      </p>
    </div>

    <div class="grid gap-3 md:grid-cols-3">
      <div class="md:col-span-2">
        <input
          v-model.trim="searchText"
          type="text"
          class="field-sm"
          placeholder="ابحث باسم المزود أو المدينة أو العنوان"
        >
      </div>
      <div>
        <select v-model="selectedServiceId" class="field-sm">
          <option value="">كل الخدمات المفعلة</option>
          <option v-for="svc in allowedServices" :key="svc.id" :value="String(svc.id)">
            {{ svc.name }}
          </option>
        </select>
      </div>
    </div>

    <div class="grid gap-2 sm:grid-cols-2">
      <div class="rounded-xl border border-violet-200/70 dark:border-violet-800/60 bg-violet-50/70 dark:bg-violet-900/20 px-3 py-2">
        <p class="text-[11px] text-violet-700 dark:text-violet-200">المزودون الظاهرون</p>
        <p class="text-sm font-extrabold text-violet-800 dark:text-violet-100">{{ filteredProviders.length }}</p>
      </div>
      <div class="rounded-xl border border-primary-200/70 dark:border-primary-800/60 bg-primary-50/70 dark:bg-primary-900/20 px-3 py-2">
        <p class="text-[11px] text-primary-700 dark:text-primary-200">الخدمات لدى المزودين (بعد الفلترة)</p>
        <p class="text-sm font-extrabold text-primary-800 dark:text-primary-100">{{ filteredServiceCount }}</p>
      </div>
    </div>

    <div class="rounded-2xl overflow-hidden border border-gray-200 dark:border-slate-700 bg-gray-100 dark:bg-slate-900 min-h-[300px] relative">
      <div v-if="mapLoading" class="absolute inset-0 z-10 bg-white/70 dark:bg-slate-900/70 flex items-center justify-center">
        <div class="w-8 h-8 rounded-full border-2 border-primary-500 border-t-transparent animate-spin" />
      </div>
      <div v-if="mapError" class="absolute inset-0 z-10 bg-white/90 dark:bg-slate-900/90 flex items-center justify-center text-sm text-red-600 px-4 text-center">
        {{ mapError }}
      </div>
      <div ref="mapEl" class="w-full h-[320px] md:h-[420px]" />
    </div>

    <div class="grid gap-3 lg:grid-cols-2">
      <article
        v-for="provider in filteredProviders"
        :key="provider.id"
        class="rounded-2xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900/60 p-4 space-y-2"
      >
        <div class="flex items-start justify-between gap-3">
          <div>
            <h3 class="text-sm font-bold text-gray-900 dark:text-slate-100">{{ provider.name }}</h3>
            <p class="text-xs text-gray-500 dark:text-slate-400">{{ [provider.city, provider.address].filter(Boolean).join(' — ') || 'بدون عنوان مفصل' }}</p>
          </div>
          <button type="button" class="btn btn-secondary !py-1.5 !px-2.5 !text-xs" @click="focusProvider(provider)">
            عرض على الخريطة
          </button>
        </div>

        <div class="rounded-xl border border-gray-200/80 dark:border-slate-700 p-2.5 space-y-2">
          <div class="flex items-center justify-between">
            <p class="text-[11px] font-bold text-gray-700 dark:text-slate-200">الخدمات المفعلة لهذا المزود</p>
            <span class="text-[11px] px-2 py-0.5 rounded-full bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-300">
              {{ provider.services.length }} خدمة
            </span>
          </div>
          <div v-if="provider.services.length" class="space-y-2">
            <div
              v-for="group in groupedServices(provider.services)"
              :key="`${provider.id}-${group.name}`"
              class="rounded-lg border border-gray-100 dark:border-slate-700/70 p-2"
            >
              <div class="flex items-center justify-between pb-1">
                <p class="text-[11px] font-bold text-gray-700 dark:text-slate-200">{{ group.name }}</p>
                <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-300">
                  {{ group.items.length }}
                </span>
              </div>
              <div class="flex flex-wrap gap-1.5">
                <span
                  v-for="svc in group.items"
                  :key="`${provider.id}-${group.name}-${svc.id}`"
                  class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-violet-100 text-violet-800 dark:bg-violet-900/35 dark:text-violet-200"
                >
                  {{ svc.name }}
                </span>
              </div>
            </div>
          </div>
          <span v-else class="text-[11px] text-gray-400">لا يوجد ربط خدمات تفصيلي لهذا المزود</span>
        </div>

        <div class="text-xs text-gray-600 dark:text-slate-300 space-y-1">
          <p>
            <span class="font-semibold">الإحداثيات:</span>
            {{ formatCoord(provider.latitude) }}, {{ formatCoord(provider.longitude) }}
          </p>
          <p v-if="provider.phone">
            <span class="font-semibold">الهاتف:</span> {{ provider.phone }}
          </p>
        </div>

        <div class="flex flex-wrap gap-2 pt-1">
          <button type="button" class="btn btn-secondary !py-1.5 !px-2.5 !text-xs" @click="copyCoordinates(provider)">
            نسخ الإحداثيات
          </button>
          <button type="button" class="btn btn-secondary !py-1.5 !px-2.5 !text-xs" @click="copyMapLink(provider)">
            نسخ رابط الموقع
          </button>
          <button type="button" class="btn btn-secondary !py-1.5 !px-2.5 !text-xs" @click="shareLocation(provider)">
            مشاركة
          </button>
          <button type="button" class="btn btn-primary !py-1.5 !px-2.5 !text-xs" @click="openNavigation(provider)">
            تنقل
          </button>
        </div>
      </article>
      <div v-if="!filteredProviders.length" class="rounded-2xl border border-dashed border-gray-300 dark:border-slate-700 p-6 text-center text-sm text-gray-500 dark:text-slate-400 lg:col-span-2">
        لا توجد مواقع تغطية مطابقة للفلترة الحالية.
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { MapPinIcon } from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import { ensureGoogleMapsLoaded, getGoogleMapsApiKey } from '@/composables/useGoogleMaps'
import { useToast } from '@/composables/useToast'

type ServiceLite = { id: number; name: string; group: string }
type ProviderRow = {
  id: number
  name: string
  city: string
  address: string
  phone: string
  latitude: number
  longitude: number
  services: ServiceLite[]
}

const toast = useToast()
const mapEl = ref<HTMLElement | null>(null)
const mapLoading = ref(true)
const mapError = ref('')
const searchText = ref('')
const selectedServiceId = ref('')
const providers = ref<ProviderRow[]>([])
const allowedServices = ref<ServiceLite[]>([])

let mapInstance: any = null
let infoWindow: any = null
const markers: any[] = []

function extractList(payload: any): any[] {
  if (Array.isArray(payload?.data?.data)) return payload.data.data
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload)) return payload
  return []
}

function toNum(v: unknown): number | null {
  const n = Number(v)
  return Number.isFinite(n) ? n : null
}

function normalizeServiceName(s: any): string {
  return String(s?.name_ar || s?.name || s?.label || '').trim()
}

function normalizeServiceGroup(s: any): string {
  return String(
    s?.category_name_ar
    || s?.category_name
    || s?.service_category_ar
    || s?.service_category
    || s?.group_name_ar
    || s?.group_name
    || s?.service_type
    || 'خدمات أخرى',
  ).trim()
}

function getBranchServiceIds(row: any): number[] {
  const ids = new Set<number>()
  const add = (val: unknown) => {
    const n = toNum(val)
    if (n != null && n > 0) ids.add(n)
  }
  ;(Array.isArray(row?.service_ids) ? row.service_ids : []).forEach(add)
  ;(Array.isArray(row?.services) ? row.services : []).forEach((s: any) => add(s?.id))
  ;(Array.isArray(row?.offered_services) ? row.offered_services : []).forEach((s: any) => add(s?.id ?? s))
  return [...ids]
}

const filteredProviders = computed(() => {
  const q = searchText.value.trim().toLowerCase()
  const selected = selectedServiceId.value ? Number(selectedServiceId.value) : null
  return providers.value.filter((p) => {
    if (selected != null && !p.services.some((s) => s.id === selected)) return false
    if (!q) return true
    const hay = `${p.name} ${p.city} ${p.address}`.toLowerCase()
    return hay.includes(q)
  })
})

const filteredServiceCount = computed(() => {
  const ids = new Set<number>()
  for (const provider of filteredProviders.value) {
    for (const service of provider.services) ids.add(service.id)
  }
  return ids.size
})

function orderedServices(services: ServiceLite[]): ServiceLite[] {
  return [...services].sort((a, b) => a.name.localeCompare(b.name, 'ar'))
}

function groupedServices(services: ServiceLite[]): Array<{ name: string; items: ServiceLite[] }> {
  const groups = new Map<string, ServiceLite[]>()
  for (const service of orderedServices(services)) {
    const key = service.group || 'خدمات أخرى'
    const items = groups.get(key) ?? []
    items.push(service)
    groups.set(key, items)
  }
  return [...groups.entries()]
    .sort((a, b) => a[0].localeCompare(b[0], 'ar'))
    .map(([name, items]) => ({ name, items }))
}

function mapLink(p: ProviderRow): string {
  return `https://www.google.com/maps?q=${encodeURIComponent(`${p.latitude},${p.longitude}`)}`
}

function formatCoord(v: number): string {
  return v.toFixed(6)
}

async function copyText(value: string, label: string): Promise<void> {
  try {
    await navigator.clipboard.writeText(value)
    toast.success('تم النسخ', label)
  } catch {
    toast.warning('تعذر النسخ', value)
  }
}

function copyCoordinates(p: ProviderRow): void {
  void copyText(`${p.latitude},${p.longitude}`, 'تم نسخ الإحداثيات.')
}

function copyMapLink(p: ProviderRow): void {
  void copyText(mapLink(p), 'تم نسخ رابط الموقع.')
}

function shareLocation(p: ProviderRow): void {
  const url = mapLink(p)
  if (navigator.share) {
    navigator.share({ title: p.name, text: `موقع ${p.name}`, url }).catch(() => {})
    return
  }
  void copyText(url, 'تم نسخ الرابط للمشاركة.')
}

function openNavigation(p: ProviderRow): void {
  const url = `https://www.google.com/maps/dir/?api=1&destination=${p.latitude},${p.longitude}`
  window.open(url, '_blank', 'noopener,noreferrer')
}

function focusProvider(p: ProviderRow): void {
  if (!mapInstance) return
  mapInstance.panTo({ lat: p.latitude, lng: p.longitude })
  mapInstance.setZoom(Math.max(mapInstance.getZoom?.() ?? 12, 14))
  const marker = markers.find((m: any) => m.__providerId === p.id)
  if (marker && infoWindow) {
    infoWindow.setContent(`
      <div dir="rtl" style="padding:8px;max-width:240px">
        <strong>${p.name}</strong>
        <p style="font-size:12px;margin:6px 0 0">${p.city || ''} ${p.address || ''}</p>
      </div>
    `)
    infoWindow.open({ map: mapInstance, anchor: marker })
  }
}

function clearMarkers(): void {
  markers.forEach((m) => m.setMap(null))
  markers.length = 0
}

async function renderMapMarkers(): Promise<void> {
  if (!mapInstance || !(window as any).google?.maps) return
  const maps = (window as any).google.maps
  clearMarkers()
  const bounds = new maps.LatLngBounds()
  let hasAny = false
  for (const p of filteredProviders.value) {
    const marker = new maps.Marker({
      map: mapInstance,
      position: { lat: p.latitude, lng: p.longitude },
      title: p.name,
    })
    ;(marker as any).__providerId = p.id
    marker.addListener('click', () => focusProvider(p))
    markers.push(marker)
    bounds.extend({ lat: p.latitude, lng: p.longitude })
    hasAny = true
  }
  if (hasAny) mapInstance.fitBounds(bounds)
}

async function buildMap(): Promise<void> {
  mapLoading.value = true
  mapError.value = ''
  try {
    const g = await ensureGoogleMapsLoaded()
    if (!g?.maps) {
      mapError.value = getGoogleMapsApiKey() ? 'تعذر تحميل الخرائط.' : 'مفتاح Google Maps غير مضاف.'
      return
    }
    if (!mapEl.value) return
    mapInstance = new g.maps.Map(mapEl.value, {
      center: { lat: 24.7136, lng: 46.6753 },
      zoom: 6,
      mapTypeControl: true,
      streetViewControl: false,
    })
    infoWindow = new g.maps.InfoWindow()
    await renderMapMarkers()
  } finally {
    mapLoading.value = false
  }
}

async function loadCoverageData(): Promise<void> {
  const [branchesRes, catalogRes] = await Promise.allSettled([
    apiClient.get('/branches', { params: { for_map: 1, per_page: 300 } }),
    apiClient.get('/fleet-portal/service-catalog'),
  ])

  const catalogRows = catalogRes.status === 'fulfilled' ? extractList(catalogRes.value.data) : []
  const servicesMap = new Map<number, ServiceLite>()
  for (const row of catalogRows) {
    const id = toNum(row?.id)
    const name = normalizeServiceName(row)
    const group = normalizeServiceGroup(row)
    if (id != null && id > 0 && name) servicesMap.set(id, { id, name, group })
  }
  allowedServices.value = [...servicesMap.values()]

  const branchRows = branchesRes.status === 'fulfilled' ? extractList(branchesRes.value.data) : []
  providers.value = branchRows
    .map((row: any): ProviderRow | null => {
      const lat = toNum(row?.latitude)
      const lng = toNum(row?.longitude)
      if (lat == null || lng == null) return null
      const serviceIds = getBranchServiceIds(row)
      const linkedServices = serviceIds
        .map((id) => servicesMap.get(id))
        .filter((s): s is ServiceLite => !!s)
      return {
        id: Number(row?.id ?? 0),
        name: String(row?.name_ar || row?.name || 'مزود الخدمة'),
        city: String(row?.city || ''),
        address: String(row?.address || ''),
        phone: String(row?.phone || ''),
        latitude: lat,
        longitude: lng,
        services: linkedServices,
      }
    })
    .filter((p): p is ProviderRow => p != null && p.id > 0)

  // Fallback: إن لم تتوفر خدمة مرتبطة بفرع، نظهر جميع الخدمات المفعلة للعميل لهذا المزود.
  if (allowedServices.value.length) {
    providers.value = providers.value.map((p) => (p.services.length ? p : { ...p, services: allowedServices.value }))
  }
}

watch([filteredProviders, selectedServiceId], () => {
  void renderMapMarkers()
})

onMounted(async () => {
  try {
    await loadCoverageData()
    await buildMap()
  } catch {
    mapError.value = 'تعذر تحميل بيانات مواقع التغطية.'
  }
})

onUnmounted(() => {
  clearMarkers()
  mapInstance = null
  infoWindow = null
})
</script>
