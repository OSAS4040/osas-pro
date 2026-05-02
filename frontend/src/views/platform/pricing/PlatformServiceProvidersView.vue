<template>
  <div class="mx-auto max-w-[1200px] space-y-6 pb-12" dir="rtl">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
          {{ route.name === 'platform-providers-new' ? 'إضافة مزود خدمة' : 'مزودو الخدمة (منصة)' }}
        </h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
          سجل المزودين لتكاليف الخدمات لأغراض التسعير التجاري — لا تُعرض للعميل النهائي من واجهات المستأجر.
        </p>
      </div>
      <RouterLink :to="{ name: 'platform-overview' }" class="text-sm font-semibold text-primary-700 hover:underline dark:text-primary-400">← الملخص</RouterLink>
    </div>

    <div v-if="!auth.hasPermission('platform.providers.manage')" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-100">
      لا تملك صلاحية إدارة مزودي المنصة.
    </div>

    <div
      v-else
      id="platform-provider-create"
      class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900/40"
    >
      <h2 class="mb-3 text-sm font-bold text-slate-800 dark:text-white">تسجيل مزود</h2>
      <div class="grid gap-3 md:grid-cols-2">
        <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">
          الاسم
          <input v-model="form.name" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white" />
        </label>
        <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">
          جهة الاتصال
          <input v-model="form.contact_name" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white" />
        </label>
        <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">
          الهاتف
          <input v-model="form.phone" type="text" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white" dir="ltr" />
        </label>
        <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">
          البريد
          <input v-model="form.email" type="email" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white" dir="ltr" />
        </label>
      </div>
      <button
        type="button"
        class="mt-3 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50"
        :disabled="saving"
        @click="onSave"
      >
        {{ saving ? 'جارٍ الحفظ…' : 'حفظ المزود' }}
      </button>
    </div>

    <div v-if="auth.hasPermission('platform.providers.manage')" class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900/40">
      <div v-if="loading" class="p-10 text-center text-slate-400">جارٍ التحميل…</div>
      <table v-else class="w-full text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-600 dark:bg-slate-800/80">
          <tr>
            <th class="px-4 py-3 text-right font-semibold">المعرّف</th>
            <th class="px-4 py-3 text-right font-semibold">الاسم</th>
            <th class="px-4 py-3 text-right font-semibold">الحالة</th>
            <th class="px-4 py-3" />
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
          <tr v-for="p in rows" :key="p.id">
            <td class="px-4 py-3 font-mono text-xs" dir="ltr">{{ p.id }}</td>
            <td class="px-4 py-3 font-medium">{{ p.name }}</td>
            <td class="px-4 py-3">{{ p.is_active ? 'نشط' : 'معطّل' }}</td>
            <td class="px-4 py-3">
              <RouterLink
                :to="{ name: 'platform-provider-costs', query: { providerId: String(p.id) } }"
                class="text-primary-700 hover:underline dark:text-primary-400"
              >
                التكاليف
              </RouterLink>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import {
  createPlatformProvider,
  fetchPlatformProviders,
  providersApiErrorMessage,
  type PlatformServiceProviderRow,
} from '@/composables/platform-admin/usePlatformServiceProvidersApi'
import { useToast } from '@/composables/useToast'

const auth = useAuthStore()
const route = useRoute()
const toast = useToast()

const rows = ref<PlatformServiceProviderRow[]>([])
const loading = ref(false)
const saving = ref(false)
const form = ref({ name: '', contact_name: '', phone: '', email: '' })

async function load(): Promise<void> {
  if (!auth.hasPermission('platform.providers.manage')) return
  loading.value = true
  try {
    const { rows: data } = await fetchPlatformProviders({ per_page: 100 })
    rows.value = data
  } catch (e) {
    toast.error('مزودو المنصة', providersApiErrorMessage(e))
  } finally {
    loading.value = false
  }
}

async function onSave(): Promise<void> {
  if (!form.value.name.trim()) {
    toast.warning('الاسم مطلوب', '')
    return
  }
  saving.value = true
  try {
    await createPlatformProvider({
      name: form.value.name.trim(),
      contact_name: form.value.contact_name || undefined,
      phone: form.value.phone || undefined,
      email: form.value.email || undefined,
    })
    toast.success('تم', 'أُضيف المزود')
    form.value = { name: '', contact_name: '', phone: '', email: '' }
    await load()
  } catch (e) {
    toast.error('فشل الحفظ', providersApiErrorMessage(e))
  } finally {
    saving.value = false
  }
}

function scrollToCreate(): void {
  if (route.name === 'platform-providers-new') {
    document.getElementById('platform-provider-create')?.scrollIntoView({ behavior: 'smooth' })
  }
}

onMounted(() => {
  void load()
  scrollToCreate()
})

watch(
  () => route.name,
  () => scrollToCreate(),
)
</script>
