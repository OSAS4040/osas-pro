<template>
  <div class="flex flex-col lg:flex-row gap-4 min-h-[calc(100vh-8rem)]" dir="rtl">
    <aside class="w-full lg:w-80 flex-shrink-0 space-y-3 order-2 lg:order-1">
      <div class="flex items-center justify-between gap-2 flex-wrap">
        <h1 class="text-lg font-bold text-gray-900 dark:text-slate-100 flex items-center gap-2">
          <MapPinIcon class="w-6 h-6 text-primary-600" />
          خريطة الفروع
        </h1>
        <RouterLink
          v-if="auth.isManager"
          to="/branches"
          class="text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline"
        >
          إدارة الفروع
        </RouterLink>
        <span v-else class="text-[11px] text-gray-400 dark:text-slate-500">عرض فقط — التعديل للمدير</span>
      </div>
      <p class="text-xs text-gray-600 dark:text-slate-400">
        العلامات مربوطة ببيانات الفروع. من النافذة المنبثقة يمكنك الانتقال لتعديل الإحداثيات.
      </p>

      <div
        v-if="!apiKeyPresent"
        class="rounded-xl border border-amber-200 dark:border-amber-900/50 bg-amber-50 dark:bg-amber-950/30 p-3 text-xs text-amber-900 dark:text-amber-100 space-y-2"
      >
        <p class="font-semibold">لم يُضبط مفتاح Google Maps</p>
        <p>أضف المتغير <code class="px-1 rounded bg-white/50 dark:bg-black/20">VITE_GOOGLE_MAPS_API_KEY</code> في ملف بيئة الواجهة (انظر <code class="px-1">env.example</code>) ثم أعد تشغيل Vite.</p>
      </div>

      <div class="rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 divide-y divide-gray-100 dark:divide-slate-700 max-h-[40vh] lg:max-h-[calc(100vh-14rem)] overflow-y-auto">
        <button
          v-for="b in branches"
          :key="b.id"
          type="button"
          class="w-full text-right px-3 py-2.5 hover:bg-primary-50/60 dark:hover:bg-primary-950/20 transition-colors flex flex-col gap-0.5"
          :class="selectedId === b.id ? 'bg-primary-50 dark:bg-primary-950/30 ring-1 ring-primary-200 dark:ring-primary-800' : ''"
          @click="focusBranch(b)"
        >
          <span class="text-sm font-medium text-gray-900 dark:text-slate-100">{{ b.name_ar || b.name }}</span>
          <span v-if="b.city" class="text-[11px] text-gray-500">{{ b.city }}</span>
          <span v-if="!hasCoords(b)" class="text-[11px] text-amber-600 dark:text-amber-400">بدون إحداثيات — عرّفها من «إدارة الفروع»</span>
        </button>
        <p v-if="!branches.length && !listLoading" class="p-4 text-sm text-gray-500 text-center">لا فروع لعرضها</p>
      </div>
    </aside>

    <div class="flex-1 min-h-[320px] lg:min-h-[calc(100vh-10rem)] order-1 lg:order-2 rounded-2xl overflow-hidden border border-gray-200 dark:border-slate-700 shadow-md bg-gray-100 dark:bg-slate-900 relative">
      <div v-if="mapLoadError" class="absolute inset-0 flex items-center justify-center p-6 text-center text-sm text-red-600 z-10 bg-white/90 dark:bg-slate-900/90">
        {{ mapLoadError }}
      </div>
      <div v-if="listLoading" class="absolute inset-0 flex items-center justify-center z-10 bg-white/70 dark:bg-slate-900/70">
        <div class="animate-spin rounded-full h-10 w-10 border-2 border-primary-500 border-t-transparent" />
      </div>
      <div ref="mapEl" class="w-full h-full min-h-[320px]" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { MapPinIcon } from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'
import { ensureGoogleMapsLoaded, getGoogleMapsApiKey } from '@/composables/useGoogleMaps'
import type { BranchRow } from './branchTypes'

const router = useRouter()
const auth = useAuthStore()
const mapEl = ref<HTMLElement | null>(null)
const branches = ref<BranchRow[]>([])
const listLoading = ref(true)
const mapLoadError = ref('')
const selectedId = ref<number | null>(null)

const apiKeyPresent = computed(() => !!getGoogleMapsApiKey())

let mapInstance: any = null
const markers: any[] = []
let infoWindow: any = null

function hasCoords(b: BranchRow): boolean {
  return b.latitude != null && b.longitude != null
}

function escapeHtml(s: string): string {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;')
}

function focusBranch(b: BranchRow) {
  selectedId.value = b.id
  if (!mapInstance || !hasCoords(b)) return
  const g = (window as any).google
  if (!g?.maps) return
  const pos = { lat: Number(b.latitude), lng: Number(b.longitude) }
  mapInstance.panTo(pos)
  mapInstance.setZoom(Math.max(mapInstance.getZoom?.() ?? 10, 14))
  const marker = markers.find((m) => (m as any).__branchId === b.id)
  if (marker && infoWindow) {
    openInfo(marker, b)
  }
}

function openInfo(marker: any, b: BranchRow) {
  const g = (window as any).google
  if (!g?.maps || !infoWindow || !mapInstance) return
  const title = escapeHtml(b.name_ar || b.name)
  const addr = escapeHtml([b.address, b.city].filter(Boolean).join(' — ') || '')
  const editBlock = auth.isManager
    ? `<button type="button" id="gmap-branch-edit-${b.id}" style="margin-top:10px;padding:8px 12px;border-radius:10px;border:0;background:#0d9488;color:#fff;cursor:pointer;font-size:12px;font-weight:600;width:100%">
        فتح التعديل في النظام
      </button>`
    : `<p style="font-size:11px;margin-top:8px;color:#888;text-align:center">التعديل من صفحة الفروع (مدير/مالك)</p>`
  infoWindow.setContent(`
    <div dir="rtl" style="padding:8px;max-width:240px;font-family:'Tajawal','Inter',ui-sans-serif,system-ui,sans-serif">
      <strong style="font-size:14px">${title}</strong>
      ${addr ? `<p style="font-size:12px;margin:6px 0 0;color:#555">${addr}</p>` : ''}
      ${editBlock}
    </div>
  `)
  infoWindow.open({ map: mapInstance, anchor: marker })
  if (auth.isManager) {
    g.maps.event.addListenerOnce(infoWindow, 'domready', () => {
      document.getElementById(`gmap-branch-edit-${b.id}`)?.addEventListener('click', () => {
        router.push({ name: 'branches', query: { edit: String(b.id) } })
        infoWindow.close()
      })
    })
  }
}

async function loadList() {
  listLoading.value = true
  try {
    const { data } = await apiClient.get('/branches', { params: { for_map: 1 } })
    const rows = data.data
    branches.value = Array.isArray(rows) ? rows : []
  } catch {
    branches.value = []
    mapLoadError.value = 'تعذّر جلب قائمة الفروع.'
  } finally {
    listLoading.value = false
  }
}

async function buildMap() {
  mapLoadError.value = ''
  if (!mapEl.value) return

  const g = await ensureGoogleMapsLoaded()
  if (!g?.maps) {
    if (apiKeyPresent.value) {
      mapLoadError.value = 'تعذّر تحميل خرائط Google.'
    }
    return
  }

  const maps = g.maps
  const center = { lat: 24.7136, lng: 46.6753 }
  mapInstance = new maps.Map(mapEl.value, {
    center,
    zoom: 6,
    mapTypeControl: true,
    streetViewControl: false,
    mapId: undefined,
  })

  infoWindow = new maps.InfoWindow()

  const bounds = new maps.LatLngBounds()
  let hasPoint = false

  for (const b of branches.value) {
    if (!hasCoords(b)) continue
    const pos = { lat: Number(b.latitude), lng: Number(b.longitude) }
    bounds.extend(pos)
    hasPoint = true
    const marker = new maps.Marker({
      position: pos,
      map: mapInstance,
      title: b.name_ar || b.name,
    })
    ;(marker as any).__branchId = b.id
    marker.addListener('click', () => {
      selectedId.value = b.id
      openInfo(marker, b)
    })
    markers.push(marker)
  }

  if (hasPoint) {
    mapInstance.fitBounds(bounds)
    maps.event.addListenerOnce(mapInstance, 'bounds_changed', () => {
      const z = mapInstance.getZoom()
      if (z > 15) mapInstance.setZoom(15)
    })
  }
}

onMounted(async () => {
  await loadList()
  await buildMap()
})

onUnmounted(() => {
  markers.forEach((m) => m.setMap(null))
  markers.length = 0
  mapInstance = null
  infoWindow = null
})
</script>
