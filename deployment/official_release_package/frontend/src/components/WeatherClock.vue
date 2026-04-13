<template>
  <div class="flex items-center gap-4 flex-wrap">
    <!-- Digital Clock -->
    <div class="flex items-center gap-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl px-4 py-2 shadow-sm">
      <ClockIcon class="w-4 h-4 text-primary-500 flex-shrink-0" />
      <div>
        <p class="text-lg font-bold text-gray-900 dark:text-slate-100 font-mono tabular-nums leading-none">{{ time }}</p>
        <p class="text-xs text-gray-400 dark:text-slate-500">{{ date }}</p>
      </div>
    </div>

    <!-- Weather -->
    <div v-if="weather" class="flex items-center gap-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl px-4 py-2 shadow-sm">
      <span class="text-2xl leading-none">{{ weather.icon }}</span>
      <div>
        <p class="text-lg font-bold text-gray-900 dark:text-slate-100 leading-none">{{ weather.temp }}°</p>
        <p class="text-xs text-gray-400 dark:text-slate-500">{{ weather.desc }}</p>
      </div>
    </div>
    <div v-else-if="loadingWeather" class="w-24 h-12 bg-gray-100 dark:bg-slate-700 rounded-xl animate-pulse" />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { ClockIcon } from '@heroicons/vue/24/outline'
import { useLocale } from '@/composables/useLocale'

const { lang } = useLocale()
const time   = ref('')
const date   = ref('')
const weather = ref<{ temp: number; icon: string; desc: string } | null>(null)
const loadingWeather = ref(true)

const WMO_ICONS: Record<number, string> = {
  0: '☀️', 1: '🌤️', 2: '⛅', 3: '☁️',
  45: '🌫️', 48: '🌫️',
  51: '🌦️', 53: '🌦️', 55: '🌧️',
  61: '🌧️', 63: '🌧️', 65: '🌧️',
  71: '❄️', 73: '❄️', 75: '❄️',
  80: '🌦️', 81: '🌦️', 82: '⛈️',
  95: '⛈️', 96: '⛈️', 99: '⛈️',
}
const WMO_DESC_AR: Record<number, string> = {
  0: 'صافٍ', 1: 'غائم جزئياً', 2: 'غائم نسبياً', 3: 'غائم',
  45: 'ضباب', 48: 'ضباب',
  51: 'رذاذ خفيف', 53: 'رذاذ', 55: 'رذاذ كثيف',
  61: 'مطر خفيف', 63: 'مطر', 65: 'مطر غزير',
  71: 'ثلج خفيف', 73: 'ثلج', 75: 'ثلج كثيف',
  80: 'زخات مطر', 81: 'زخات', 82: 'زخات عنيفة',
  95: 'عاصفة رعدية', 96: 'عاصفة مع برد', 99: 'عاصفة شديدة',
}
const WMO_DESC_EN: Record<number, string> = {
  0: 'Clear sky', 1: 'Partly cloudy', 2: 'Overcast', 3: 'Cloudy',
  45: 'Foggy', 51: 'Drizzle', 61: 'Rainy', 71: 'Snowy',
  80: 'Showers', 95: 'Thunderstorm',
}

function formatTime() {
  const now = new Date()
  time.value = now.toLocaleTimeString(lang.value === 'ar' ? 'ar-SA' : 'en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true })
  date.value = now.toLocaleDateString(lang.value === 'ar' ? 'ar-SA-u-ca-gregory' : 'en-US', { weekday: 'short', month: 'short', day: 'numeric' })
}

async function fetchWeather() {
  loadingWeather.value = true
  try {
    // Detect city from IP (or default to Riyadh)
    let lat = 24.68, lng = 46.72
    try {
      const geo = await fetch('https://ipapi.co/json/').then(r => r.json())
      if (geo.latitude) { lat = geo.latitude; lng = geo.longitude }
    } catch { /* use Riyadh */ }

    const r = await fetch(
      `https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lng}&current=temperature_2m,weather_code&timezone=auto`
    )
    const data = await r.json()
    const code = data.current.weather_code as number
    const descMap = lang.value === 'ar' ? WMO_DESC_AR : WMO_DESC_EN
    weather.value = {
      temp: Math.round(data.current.temperature_2m),
      icon: WMO_ICONS[code] ?? '🌡️',
      desc: descMap[code] ?? descMap[Math.floor(code/10)*10] ?? 'N/A',
    }
  } catch { weather.value = null }
  finally { loadingWeather.value = false }
}

let timer: ReturnType<typeof setInterval>
onMounted(() => {
  formatTime()
  timer = setInterval(formatTime, 1000)
  fetchWeather()
})
onUnmounted(() => clearInterval(timer))
</script>
