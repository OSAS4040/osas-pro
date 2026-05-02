<template>
  <div class="mx-auto max-w-6xl space-y-6 pb-12" dir="rtl">
    <nav class="flex flex-wrap gap-1 text-[11px] text-slate-500 dark:text-slate-400" aria-label="breadcrumb">
      <RouterLink to="/platform/overview" class="font-semibold text-primary-700 hover:underline dark:text-primary-400">
        إدارة المنصة
      </RouterLink>
      <span>/</span>
      <span class="font-semibold text-slate-700 dark:text-slate-200">العقود</span>
    </nav>

    <div>
      <h1 class="text-2xl font-bold text-slate-900 dark:text-white">العقود على مستوى المنصّة</h1>
      <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
        اختر <strong>شركة المستأجر (مزوّد الخدمة)</strong> ثم يمكن تصفية <strong>الخدمات</strong> و<strong>العقود</strong> المرتبطة.
        إدارة بنود العقد التفصيلية تتم من بوابة ذلك المستأجر؛ من هنا تعرض المنصّة ملخصاً وتوجيهات سريعة.
      </p>
    </div>

    <div class="flex flex-wrap gap-2">
      <RouterLink
        :to="{ name: 'platform-companies' }"
        class="inline-flex rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700"
      >
        المشتركون (شركات)
      </RouterLink>
      <RouterLink
        :to="{ name: 'platform-pricing-customer-prices' }"
        class="inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold dark:border-slate-600"
      >
        نسخ أسعار العملاء
      </RouterLink>
    </div>

    <!-- فلاتر رئيسية -->
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
      <h2 class="mb-3 text-sm font-bold text-slate-800 dark:text-slate-100">استهداف شركة وخدمة</h2>
      <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <label class="flex flex-col gap-1">
          <span class="text-xs font-semibold text-slate-600 dark:text-slate-400">بحث شركات</span>
          <input
            v-model="companySearch"
            type="search"
            class="field w-full text-sm"
            placeholder="اسم الشركة…"
            @input="scheduleCompanyReload"
          />
        </label>
        <label class="flex flex-col gap-1 md:col-span-2">
          <span class="text-xs font-semibold text-slate-600 dark:text-slate-400">المستأجر / مزوّد الخدمة</span>
          <select v-model.number="selectedCompanyId" class="field w-full text-sm" @change="onCompanyChange">
            <option :value="0">— اختر شركة —</option>
            <option v-for="c in companies" :key="c.id" :value="c.id">
              {{ c.name }} · #{{ c.id }}
            </option>
          </select>
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-xs font-semibold text-slate-600 dark:text-slate-400">بحث في الخدمات</span>
          <input
            v-model="serviceSearch"
            type="search"
            class="field w-full text-sm"
            placeholder="اسم أو كود…"
            :disabled="!selectedCompanyId"
            @input="scheduleServiceReload"
          />
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-xs font-semibold text-slate-600 dark:text-slate-400">تصفية العقود بالخدمة</span>
          <select
            v-model.number="filterServiceId"
            class="field w-full text-sm"
            :disabled="!selectedCompanyId"
            @change="loadContracts"
          >
            <option :value="0">كل العقود</option>
            <option v-for="s in services" :key="s.id" :value="s.id">
              {{ s.code || '—' }} — {{ s.name }}
            </option>
          </select>
        </label>
      </div>

      <p v-if="selectedCompanyId && companyDetailLink" class="mt-3 text-xs">
        <RouterLink
          :to="{ name: 'platform-company-detail', params: { id: selectedCompanyId } }"
          class="font-semibold text-primary-600 hover:underline dark:text-primary-400"
        >
          فتح بطاقة الشركة في المنصّة
        </RouterLink>
      </p>
    </section>

    <div v-if="selectedCompanyId" class="grid gap-6 lg:grid-cols-2">
      <!-- خدمات الكتالوج -->
      <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
          <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">خدمات الكتالوج</h3>
          <span class="text-[11px] text-slate-500">{{ services.length }} صف</span>
        </div>
        <div v-if="servicesLoading" class="py-8 text-center text-sm text-slate-500">جاري التحميل…</div>
        <div v-else-if="!services.length" class="rounded-lg bg-slate-50 py-6 text-center text-sm text-slate-500 dark:bg-slate-800/60">
          لا توجد خدمات مطابقة.
        </div>
        <div v-else class="max-h-72 overflow-auto rounded-lg border border-slate-100 dark:border-slate-700">
          <table class="w-full text-xs">
            <thead class="sticky top-0 bg-slate-100 dark:bg-slate-800">
              <tr>
                <th class="px-2 py-2 text-right">الكود</th>
                <th class="px-2 py-2 text-right">الاسم</th>
                <th class="px-2 py-2 text-end">السعر الأساسي</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
              <tr v-for="s in services" :key="s.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                <td class="px-2 py-1.5 font-mono text-[11px]" dir="ltr">{{ s.code || '—' }}</td>
                <td class="px-2 py-1.5">{{ s.name }}</td>
                <td class="px-2 py-1.5 text-end tabular-nums">{{ s.base_price }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- عقود المستأجر -->
      <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
        <div class="mb-3 flex flex-wrap items-end gap-3">
          <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">عقود المستأجر</h3>
          <label class="flex flex-col gap-1">
            <span class="text-[10px] font-semibold text-slate-500">حالة العقد</span>
            <select v-model="contractStatus" class="field text-xs" @change="loadContracts">
              <option value="">كل الحالات</option>
              <option value="draft">مسودة</option>
              <option value="active">نشط</option>
              <option value="expired">منتهي</option>
            </select>
          </label>
          <label class="flex min-w-[10rem] flex-col gap-1">
            <span class="text-[10px] font-semibold text-slate-500">بحث عنوان / طرف</span>
            <input
              v-model="contractSearch"
              type="search"
              class="field text-xs"
              placeholder="عنوان العقد…"
              @input="scheduleContractReload"
            />
          </label>
        </div>
        <div v-if="contractsLoading" class="py-8 text-center text-sm text-slate-500">جاري التحميل…</div>
        <div v-else-if="!contracts.length" class="rounded-lg bg-slate-50 py-6 text-center text-sm text-slate-500 dark:bg-slate-800/60">
          لا توجد عقود مطابقة.
        </div>
        <div v-else class="max-h-72 overflow-auto rounded-lg border border-slate-100 dark:border-slate-700">
          <table class="w-full text-xs">
            <thead class="sticky top-0 bg-slate-100 dark:bg-slate-800">
              <tr>
                <th class="px-2 py-2 text-right">العنوان</th>
                <th class="px-2 py-2 text-right">الحالة</th>
                <th class="px-2 py-2 text-center">بنود</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
              <tr v-for="row in contracts" :key="row.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                <td class="px-2 py-1.5">
                  <span class="font-medium">{{ row.title }}</span>
                  <span class="mt-0.5 block text-[10px] text-slate-500">{{ row.party_name }}</span>
                </td>
                <td class="px-2 py-1.5">{{ row.status }}</td>
                <td class="px-2 py-1.5 text-center tabular-nums">{{ row.service_items_count ?? 0 }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <p v-if="errorMessage" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-100">
      {{ errorMessage }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import apiClient from '@/lib/apiClient'

interface CompanyRow {
  id: number
  name: string
}

interface ServiceRow {
  id: number
  name: string
  code: string | null
  base_price: string | number
}

interface ContractRow {
  id: number
  title: string
  party_name: string
  status: string
  service_items_count?: number
}

const companies = ref<CompanyRow[]>([])
const companySearch = ref('')
const selectedCompanyId = ref(0)
const services = ref<ServiceRow[]>([])
const serviceSearch = ref('')
const filterServiceId = ref(0)
const contracts = ref<ContractRow[]>([])
const contractStatus = ref('')
const contractSearch = ref('')

const servicesLoading = ref(false)
const contractsLoading = ref(false)
const errorMessage = ref('')

let companyTimer: ReturnType<typeof setTimeout> | null = null
let serviceTimer: ReturnType<typeof setTimeout> | null = null
let contractTimer: ReturnType<typeof setTimeout> | null = null

const companyDetailLink = computed(() => selectedCompanyId.value > 0)

async function loadCompanies(): Promise<void> {
  errorMessage.value = ''
  try {
    const { data } = await apiClient.get<{ data: CompanyRow[] }>('/platform/companies', {
      params: {
        per_page: 100,
        search: companySearch.value.trim() || undefined,
      },
    })
    companies.value = data?.data ?? []
    if (selectedCompanyId.value > 0 && !companies.value.some((c) => c.id === selectedCompanyId.value)) {
      selectedCompanyId.value = 0
    }
  } catch (e: unknown) {
    errorMessage.value = 'تعذّر تحميل قائمة الشركات.'
    companies.value = []
  }
}

function scheduleCompanyReload(): void {
  if (companyTimer) clearTimeout(companyTimer)
  companyTimer = setTimeout(() => {
    loadCompanies()
  }, 320)
}

async function loadServices(): Promise<void> {
  if (!selectedCompanyId.value) {
    services.value = []
    return
  }
  servicesLoading.value = true
  errorMessage.value = ''
  try {
    const { data } = await apiClient.get<{ data: ServiceRow[] }>(
      `/platform/companies/${selectedCompanyId.value}/services-bridge`,
      {
        params: {
          per_page: 200,
          search: serviceSearch.value.trim() || undefined,
          active_only: true,
        },
      },
    )
    services.value = data?.data ?? []
  } catch {
    errorMessage.value = 'تعذّر تحميل خدمات المستأجر.'
    services.value = []
  } finally {
    servicesLoading.value = false
  }
}

function scheduleServiceReload(): void {
  if (!selectedCompanyId.value) return
  if (serviceTimer) clearTimeout(serviceTimer)
  serviceTimer = setTimeout(() => {
    loadServices()
  }, 320)
}

async function loadContracts(): Promise<void> {
  if (!selectedCompanyId.value) {
    contracts.value = []
    return
  }
  contractsLoading.value = true
  errorMessage.value = ''
  try {
    const { data } = await apiClient.get<{ data: ContractRow[] }>(
      `/platform/companies/${selectedCompanyId.value}/contracts-bridge`,
      {
        params: {
          per_page: 80,
          status: contractStatus.value || undefined,
          search: contractSearch.value.trim() || undefined,
          service_id: filterServiceId.value > 0 ? filterServiceId.value : undefined,
        },
      },
    )
    contracts.value = data?.data ?? []
  } catch {
    errorMessage.value = 'تعذّر تحميل عقود المستأجر.'
    contracts.value = []
  } finally {
    contractsLoading.value = false
  }
}

function scheduleContractReload(): void {
  if (!selectedCompanyId.value) return
  if (contractTimer) clearTimeout(contractTimer)
  contractTimer = setTimeout(() => {
    loadContracts()
  }, 360)
}

function onCompanyChange(): void {
  filterServiceId.value = 0
  serviceSearch.value = ''
  contractSearch.value = ''
  contractStatus.value = ''
  loadServices()
  loadContracts()
}

watch(selectedCompanyId, (id) => {
  if (!id) {
    services.value = []
    contracts.value = []
  }
})

onMounted(() => {
  loadCompanies()
})
</script>
