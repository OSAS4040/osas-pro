<template>
  <div class="min-h-screen bg-gradient-to-b from-slate-950 via-slate-900 to-slate-950 text-slate-100">
    <div class="mx-auto max-w-lg px-4 py-10 sm:py-14">
      <div class="mb-8 text-center">
        <p class="text-[10px] font-semibold uppercase tracking-[0.35em] text-teal-400/90">OSAS Pro</p>
        <h1 class="mt-2 text-2xl font-bold text-white">أسس برو</h1>
        <p class="mt-1 text-sm text-slate-400">هوية رقمية للمركبة</p>
      </div>

      <div
        v-if="tokenInvalid"
        class="rounded-2xl border border-amber-500/30 bg-amber-950/40 px-5 py-8 text-center text-sm text-amber-100"
        role="alert"
      >
        رابط التحقق غير صالح. تأكد من مسح الرمز كاملاً أو اطلب بطاقة جديدة من مركز الخدمة.
      </div>

      <div v-else-if="loading" class="rounded-2xl border border-slate-700/80 bg-slate-900/60 px-5 py-12 text-center text-sm text-slate-400" aria-busy="true">
        جاري التحقق من الهوية الرقمية…
      </div>

      <div
        v-else-if="loadError"
        class="rounded-2xl border border-red-500/30 bg-red-950/30 px-5 py-8 text-center text-sm text-red-100"
        role="alert"
      >
        {{ loadError }}
      </div>

      <div v-else-if="payload" class="space-y-6">
        <div class="rounded-2xl border border-white/10 bg-white/[0.04] p-5 shadow-xl shadow-black/40 backdrop-blur-sm">
          <p class="text-xs font-medium text-slate-400">مركز الخدمة</p>
          <p class="mt-1 text-lg font-semibold text-white">{{ payload.company_name }}</p>
          <div class="mt-4 grid gap-2 text-sm text-slate-300">
            <p v-if="payload.vehicle?.make || payload.vehicle?.model">
              <span class="text-slate-500">المركبة:</span>
              {{ [payload.vehicle?.make, payload.vehicle?.model].filter(Boolean).join(' ') }}
              <span v-if="payload.vehicle?.year" class="text-slate-500"> · {{ payload.vehicle.year }}</span>
            </p>
            <p v-if="payload.vehicle?.plate_masked">
              <span class="text-slate-500">لوحة (مختصرة):</span>
              {{ payload.vehicle.plate_masked }}
            </p>
            <p v-if="payload.public_code" class="font-mono text-xs text-teal-300/95">
              {{ payload.public_code }}
            </p>
          </div>
        </div>

        <p class="text-center text-xs leading-relaxed text-slate-400">
          {{ payload.product?.tagline }}
        </p>

        <div class="flex flex-col gap-3 sm:flex-row sm:justify-center">
          <RouterLink
            v-if="!auth.isAuthenticated"
            :to="{ name: 'login', query: { redirect: route.fullPath } }"
            class="inline-flex min-h-[44px] items-center justify-center rounded-xl bg-teal-600 px-5 text-sm font-semibold text-white shadow-lg shadow-teal-900/40 transition hover:bg-teal-500"
          >
            تسجيل الدخول لفتح الملف الكامل
          </RouterLink>
          <button
            v-else
            type="button"
            class="inline-flex min-h-[44px] items-center justify-center rounded-xl bg-teal-600 px-5 text-sm font-semibold text-white shadow-lg shadow-teal-900/40 transition hover:bg-teal-500 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="resolveBusy"
            @click="tryResolve"
          >
            {{ resolveBusy ? 'جاري الفتح…' : 'فتح ملف المركبة' }}
          </button>
        </div>

        <p v-if="auth.isAuthenticated && auth.isCustomer" class="text-center text-xs text-amber-200/90">
          حساب العميل لا يفتح ملفات المركبات التشغيلية. استخدم حساب مركز الخدمة أو الأسطول.
        </p>

        <p class="text-center text-[11px] text-slate-500">
          {{ payload.login_hint }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const toast = useToast()

type PublicPayload = {
  product?: { tagline?: string }
  public_code?: string
  company_name?: string
  vehicle?: { make?: string; model?: string; year?: number; plate_masked?: string | null }
  login_hint?: string
}

const loading = ref(true)
const loadError = ref<string | null>(null)
const payload = ref<PublicPayload | null>(null)
const resolveBusy = ref(false)

const rawToken = computed(() => String(route.params.token ?? ''))

const tokenInvalid = computed(() => {
  const t = rawToken.value
  return t !== '' && !/^[a-f0-9]{64}$/i.test(t)
})

async function fetchPublic() {
  if (tokenInvalid.value) {
    loading.value = false
    return
  }
  loading.value = true
  loadError.value = null
  payload.value = null
  try {
    const res = await apiClient.get(`/public/vehicle-identity/${rawToken.value}`, {
      skipGlobalErrorToast: true,
    })
    payload.value = res.data?.data ?? null
    if (!payload.value) {
      loadError.value = 'تعذّر عرض هذه الهوية.'
    }
  } catch {
    loadError.value = 'لم نعثر على هوية نشطة لهذا الرابط. قد يكون الرمز منتهياً أو ملغى.'
  } finally {
    loading.value = false
  }
}

async function tryResolve() {
  if (tokenInvalid.value || !rawToken.value) return
  if (auth.isCustomer) {
    toast.warning('تنبيه', 'هذا الحساب لا يملك صلاحية فتح ملف المركبة.')
    return
  }
  resolveBusy.value = true
  try {
    const res = await apiClient.post(
      '/vehicle-identity/resolve',
      { token: rawToken.value },
      { skipGlobalErrorToast: true },
    )
    const id = res.data?.data?.vehicle_id
    if (typeof id !== 'number') {
      toast.error('تعذّر الفتح', 'لم نتمكن من ربط الرمز بالمركبة.')
      return
    }
    if (auth.isFleet) {
      await router.replace({ path: '/fleet-portal/vehicles', query: { highlight: String(id) } })
      return
    }
    await router.replace(`/vehicles/${id}/card`)
  } catch {
    toast.error('لا يمكن الفتح', 'تحقق من الصلاحيات أو أنك على حساب نفس مركز الخدمة.')
  } finally {
    resolveBusy.value = false
  }
}

onMounted(() => {
  void fetchPublic()
})

watch(
  () => auth.isAuthenticated,
  (ok) => {
    if (ok && !auth.isCustomer && payload.value && !tokenInvalid.value) {
      void tryResolve()
    }
  },
)
</script>
