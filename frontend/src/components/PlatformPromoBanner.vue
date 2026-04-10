<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { MegaphoneIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'

const STORAGE_KEY = 'platform_promo_banner_dismiss'

export type BannerVariant = 'info' | 'success' | 'warning' | 'promo'

type BannerPayload =
  | { enabled: false; dismiss_token: string }
  | {
      enabled: true
      title: string | null
      message: string
      link_url: string | null
      link_text: string | null
      variant: BannerVariant
      dismissible: boolean
      dismiss_token: string
    }

const payload = ref<BannerPayload | null>(null)
/** يُحدَّث عند الإخفاء لإعادة تقييم computed دون الاعتماد على تفاعلية localStorage */
const dismissBump = ref(0)

const activeBanner = computed(() => {
  const p = payload.value
  if (!p || !p.enabled) return null
  return p
})

const visible = computed(() => {
  dismissBump.value
  const p = activeBanner.value
  if (!p) return false
  if (!p.dismissible) return true
  try {
    const dismissed = localStorage.getItem(STORAGE_KEY)
    return dismissed !== p.dismiss_token
  } catch {
    return true
  }
})

const shellClass = computed(() => {
  const v = activeBanner.value?.variant ?? 'promo'
  const map: Record<BannerVariant, string> = {
    promo:
      'border-primary-400/35 bg-gradient-to-l from-primary-600 via-primary-700 to-teal-700 text-white shadow-md shadow-primary-900/20',
    info: 'border-sky-400/40 bg-gradient-to-l from-sky-600 to-indigo-700 text-white shadow-md shadow-sky-900/15',
    success:
      'border-emerald-400/35 bg-gradient-to-l from-emerald-600 to-teal-700 text-white shadow-md shadow-emerald-900/15',
    warning:
      'border-amber-400/50 bg-gradient-to-l from-amber-600 to-orange-700 text-white shadow-md shadow-amber-900/20',
  }
  return map[v] ?? map.promo
})

/** مسار داخلي لـ Vue Router: يدعم `/landing` وكذلك الرابط الكامل لنفس الأصل (مثلاً http://localhost:5173/landing) */
function internalRouteFromLink(linkUrl: string | null | undefined): string | null {
  const u = linkUrl?.trim()
  if (!u) return null
  if (u.startsWith('/') && !u.startsWith('//')) return u
  if (typeof window === 'undefined') return null
  try {
    const parsed = new URL(u, window.location.origin)
    if (parsed.origin === window.location.origin) {
      return `${parsed.pathname}${parsed.search}${parsed.hash}` || '/'
    }
  } catch {
    /* تجاهل */
  }
  return null
}

const internalRoute = computed(() => internalRouteFromLink(activeBanner.value?.link_url))

function dismiss(): void {
  const p = activeBanner.value
  if (!p?.dismissible) return
  try {
    localStorage.setItem(STORAGE_KEY, p.dismiss_token)
  } catch {
    /* وضع خاص للمتصفح */
  }
  dismissBump.value += 1
}

async function load(): Promise<void> {
  try {
    const { data } = await apiClient.get('/public/platform-announcement-banner')
    const d = data?.data
    if (d && typeof d === 'object') {
      payload.value = d as BannerPayload
    }
  } catch {
    payload.value = null
  }
}

onMounted(() => {
  void load()
})

defineExpose({ reload: load })
</script>

<template>
  <div
    v-if="visible && activeBanner"
    class="relative border-b px-4 py-2.5 sm:px-6"
    :class="shellClass"
    role="region"
    aria-label="إعلان من المنصة"
  >
    <div class="mx-auto flex max-w-[1600px] items-start gap-3 sm:items-center">
      <MegaphoneIcon class="h-5 w-5 shrink-0 opacity-90 mt-0.5 sm:mt-0" aria-hidden="true" />
      <div class="min-w-0 flex-1 text-sm leading-relaxed">
        <p v-if="activeBanner.title" class="font-bold leading-snug">{{ activeBanner.title }}</p>
        <p :class="activeBanner.title ? 'mt-0.5 opacity-95' : ''">{{ activeBanner.message }}</p>
        <div v-if="activeBanner.link_url" class="mt-2">
          <RouterLink
            v-if="internalRoute"
            :to="internalRoute"
            class="inline-flex items-center rounded-lg bg-white/15 px-3 py-1.5 text-xs font-bold backdrop-blur-sm transition hover:bg-white/25 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/80"
          >
            {{ activeBanner.link_text || 'تفاصيل' }}
          </RouterLink>
          <a
            v-else
            :href="activeBanner.link_url"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center rounded-lg bg-white/15 px-3 py-1.5 text-xs font-bold backdrop-blur-sm transition hover:bg-white/25 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/80"
          >
            {{ activeBanner.link_text || 'تفاصيل' }}
          </a>
        </div>
      </div>
      <button
        v-if="activeBanner.dismissible"
        type="button"
        class="shrink-0 rounded-lg p-1.5 text-white/90 transition hover:bg-white/15 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70"
        aria-label="إخفاء الإعلان"
        @click="dismiss"
      >
        <XMarkIcon class="h-5 w-5" />
      </button>
    </div>
  </div>
</template>
