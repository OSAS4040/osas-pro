<template>
  <div class="max-w-5xl mx-auto space-y-6 p-4" :dir="locale.langInfo.value.dir">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <RouterLink to="/settings" class="text-xs text-primary-600 hover:underline">{{ locale.t('teamUsers.backSettings') }}</RouterLink>
        <h1 class="text-xl font-bold text-gray-900 dark:text-slate-100 mt-1">{{ locale.t('teamUsers.title') }}</h1>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-1 max-w-2xl">
          <span>{{ locale.t('teamUsers.subtitleStart') }}</span>
          <RouterLink to="/settings/org-units" class="text-primary-600 hover:underline">{{ locale.t('teamUsers.linkOrgStructure') }}</RouterLink>
          <span>{{ locale.t('teamUsers.subtitleEnd') }}</span>
        </p>
      </div>
      <button
        type="button"
        class="px-4 py-2 rounded-xl bg-primary-600 text-white text-sm font-medium hover:bg-primary-700"
        @click="openCreate"
      >
        {{ locale.t('teamUsers.newUser') }}
      </button>
    </header>

    <div v-if="loadError" class="text-sm text-red-600">{{ loadError }}</div>

    <div
      v-if="!loadError"
      class="flex flex-wrap gap-3 items-end"
    >
      <input
        v-model="searchInput"
        type="search"
        autocomplete="off"
        enterkeyhint="search"
        :placeholder="locale.t('teamUsers.searchPlaceholder')"
        class="flex-1 min-w-[12rem] border border-gray-200 dark:border-slate-600 rounded-xl px-3 py-2 text-sm bg-white dark:bg-slate-900 dark:text-slate-100"
      />
      <div class="min-w-[9rem]">
        <label class="block text-[10px] uppercase tracking-wide text-gray-500 mb-1">{{ locale.t('teamUsers.filterBranch') }}</label>
        <select
          v-model="filterBranchId"
          class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-2 py-2 text-sm dark:bg-slate-900"
        >
          <option value="">{{ locale.t('teamUsers.allBranches') }}</option>
          <option v-for="b in branches" :key="b.id" :value="String(b.id)">{{ b.name_ar || b.name }}</option>
        </select>
      </div>
      <div class="min-w-[9rem]">
        <label class="block text-[10px] uppercase tracking-wide text-gray-500 mb-1">{{ locale.t('teamUsers.filterRole') }}</label>
        <select v-model="filterRole" class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-2 py-2 text-sm dark:bg-slate-900">
          <option value="">{{ locale.t('teamUsers.allRoles') }}</option>
          <option v-for="r in filterRoleOptions" :key="r.value" :value="r.value">{{ r.label }}</option>
        </select>
      </div>
      <div class="min-w-[9rem]">
        <label class="block text-[10px] uppercase tracking-wide text-gray-500 mb-1">{{ locale.t('teamUsers.filterStatus') }}</label>
        <select v-model="filterStatus" class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-2 py-2 text-sm dark:bg-slate-900">
          <option value="">{{ locale.t('teamUsers.statusAll') }}</option>
          <option value="active">{{ locale.t('teamUsers.statusActive') }}</option>
          <option value="inactive">{{ locale.t('teamUsers.statusInactive') }}</option>
        </select>
      </div>
    </div>

    <div v-if="!loadError" class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-slate-900/80 text-xs text-gray-500 uppercase">
            <tr>
              <th class="px-4 py-3 text-start">{{ locale.t('teamUsers.colName') }}</th>
              <th class="px-4 py-3 text-start">{{ locale.t('teamUsers.colEmail') }}</th>
              <th class="px-4 py-3 text-start">{{ locale.t('teamUsers.colRole') }}</th>
              <th class="px-4 py-3 text-start">{{ locale.t('teamUsers.colBranch') }}</th>
              <th v-if="biz.isEnabled('org_structure')" class="px-4 py-3 text-start">{{ locale.t('teamUsers.colOrgUnit') }}</th>
              <th class="px-4 py-3 text-start">{{ locale.t('teamUsers.colActive') }}</th>
              <th class="px-4 py-3 w-32 text-start">{{ locale.t('teamUsers.colActions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
            <tr v-for="u in rows" :key="u.id" class="hover:bg-gray-50/80 dark:hover:bg-slate-900/40">
              <td class="px-4 py-3 font-medium">{{ u.name }}</td>
              <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-slate-400">{{ u.email }}</td>
              <td class="px-4 py-3 text-xs">{{ roleLabel(u.role) }}</td>
              <td class="px-4 py-3 text-xs text-gray-600">{{ u.branch?.name ?? '—' }}</td>
              <td v-if="biz.isEnabled('org_structure')" class="px-4 py-3 text-xs text-gray-600">
                {{ u.org_unit?.name ?? '—' }}
              </td>
              <td class="px-4 py-3">
                <span
                  class="px-2 py-0.5 rounded-full text-xs"
                  :class="u.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'"
                >
                  {{ u.is_active ? locale.t('teamUsers.active') : locale.t('teamUsers.inactive') }}
                </span>
              </td>
              <td class="px-4 py-3 whitespace-nowrap text-end">
                <button type="button" class="text-primary-600 text-xs hover:underline ms-2" @click="openEdit(u)">{{ locale.t('teamUsers.edit') }}</button>
                <button
                  v-if="auth.isOwner && u.id !== auth.user?.id"
                  type="button"
                  class="text-red-600 text-xs hover:underline"
                  @click="confirmDelete(u)"
                >
                  {{ locale.t('teamUsers.delete') }}
                </button>
              </td>
            </tr>
            <tr v-if="!loading && !rows.length">
              <td :colspan="tableColCount" class="px-4 py-10 text-center text-gray-400">{{ locale.t('teamUsers.empty') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="lastPage > 1" class="flex items-center justify-center gap-2 px-4 py-3 border-t border-gray-100 dark:border-slate-700 text-xs">
        <button
          type="button"
          class="px-3 py-1 rounded-lg border disabled:opacity-40"
          :disabled="page <= 1"
          @click="page--; load()"
        >
          {{ locale.t('teamUsers.prev') }}
        </button>
        <span class="text-gray-500">{{ page }} / {{ lastPage }}</span>
        <button
          type="button"
          class="px-3 py-1 rounded-lg border disabled:opacity-40"
          :disabled="page >= lastPage"
          @click="page++; load()"
        >
          {{ locale.t('teamUsers.next') }}
        </button>
      </div>
    </div>

    <div v-if="showForm" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" @click.self="closeForm">
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xl p-6 w-full max-w-md max-h-[90vh] overflow-y-auto space-y-3">
        <h3 class="text-base font-semibold">{{ editId ? locale.t('teamUsers.modalEdit') : locale.t('teamUsers.modalCreate') }}</h3>
        <div>
          <label class="block text-xs text-gray-500 mb-1">{{ locale.t('teamUsers.fieldName') }}</label>
          <input v-model="form.name" type="text" class="w-full border rounded-lg px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600" />
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">{{ locale.t('teamUsers.fieldEmail') }}</label>
          <input
            v-model="form.email"
            type="email"
            class="w-full border rounded-lg px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600"
            :disabled="!!editId"
          />
        </div>
        <div v-if="!editId">
          <label class="block text-xs text-gray-500 mb-1">{{ locale.t('teamUsers.fieldPassword') }}</label>
          <input v-model="form.password" type="password" class="w-full border rounded-lg px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600" />
        </div>
        <div v-else>
          <label class="block text-xs text-gray-500 mb-1">{{ locale.t('teamUsers.fieldPasswordOptional') }}</label>
          <input v-model="form.password" type="password" class="w-full border rounded-lg px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600" />
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">{{ locale.t('teamUsers.fieldRole') }}</label>
          <select v-model="form.role" class="w-full border rounded-lg px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600">
            <option v-for="r in roleOptions" :key="r.value" :value="r.value">{{ r.label }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">{{ locale.t('teamUsers.fieldBranch') }}</label>
          <select v-model="form.branch_id" class="w-full border rounded-lg px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600">
            <option value="">{{ locale.t('teamUsers.noBranch') }}</option>
            <option v-for="b in branches" :key="b.id" :value="String(b.id)">{{ b.name_ar || b.name }}</option>
          </select>
        </div>
        <div v-if="biz.isEnabled('org_structure')">
          <label class="block text-xs text-gray-500 mb-1">{{ locale.t('teamUsers.fieldOrgUnit') }}</label>
          <select v-model="form.org_unit_id" class="w-full border rounded-lg px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600">
            <option value="">{{ locale.t('teamUsers.noOrgUnit') }}</option>
            <option v-for="o in orgFlat" :key="o.id" :value="String(o.id)">{{ o.label }}</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <input id="uActive" v-model="form.is_active" type="checkbox" class="rounded" />
          <label for="uActive" class="text-sm text-gray-700 dark:text-slate-300">{{ locale.t('teamUsers.accountActive') }}</label>
        </div>
        <div class="space-y-2 rounded-xl border border-gray-200 p-3 dark:border-slate-600">
          <p class="text-xs font-semibold text-gray-700 dark:text-slate-200">صلاحيات إظهار أقسام القائمة لهذا المستخدم</p>
          <p class="text-[11px] text-gray-500 dark:text-slate-400">الإخفاء هنا يكون ضمن حدود سياسة المنصة والشركة.</p>
          <div class="grid gap-2 sm:grid-cols-2">
            <label v-for="k in visibleSectionKeys" :key="`sec-${k}`" class="flex items-center gap-2 text-xs">
              <input v-model="formNav.sections[k]" type="checkbox" class="rounded" />
              <span>{{ sectionLabels[k] }}</span>
            </label>
          </div>
          <div class="grid gap-2 sm:grid-cols-2">
            <label v-for="k in visibleGroupKeys" :key="`grp-${k}`" class="flex items-center gap-2 text-xs">
              <input v-model="formNav.groups[k]" type="checkbox" class="rounded" />
              <span>{{ groupLabels[k] }}</span>
            </label>
          </div>
        </div>
        <p v-if="formError" class="text-red-600 text-sm">{{ formError }}</p>
        <div class="flex gap-2 justify-end pt-2">
          <button type="button" class="px-4 py-2 text-sm border rounded-lg" @click="closeForm">{{ locale.t('teamUsers.cancel') }}</button>
          <button type="button" class="px-4 py-2 text-sm bg-primary-600 text-white rounded-lg" :disabled="saving" @click="save">
            {{ saving ? locale.t('teamUsers.saving') : locale.t('teamUsers.save') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { RouterLink } from 'vue-router'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'
import { useBusinessProfileStore } from '@/stores/businessProfile'
import { useLocale } from '@/composables/useLocale'
import { useToast } from '@/composables/useToast'
import { appConfirm } from '@/services/appConfirmDialog'
import {
  DEFAULT_NAV_VISIBILITY,
  NAV_GROUP_LABELS,
  NAV_SECTION_LABELS,
  type NavVisibilityPolicy,
} from '@/config/navigationVisibility'

const auth = useAuthStore()
const biz = useBusinessProfileStore()
const locale = useLocale()
const toast = useToast()

const rows = ref<any[]>([])
const branches = ref<any[]>([])
const orgFlat = ref<{ id: number; label: string }[]>([])
const loading = ref(true)
const loadError = ref('')
const page = ref(1)
const lastPage = ref(1)

const searchInput = ref('')
const filterBranchId = ref('')
const filterRole = ref('')
const filterStatus = ref('')

const showForm = ref(false)
const editId = ref<number | null>(null)
const saving = ref(false)
const formError = ref('')

let searchDebounce: ReturnType<typeof setTimeout> | null = null

const tableColCount = computed(() => (biz.isEnabled('org_structure') ? 7 : 6))

const allRoleDefs: { value: string; labelKey: string }[] = [
  { value: 'owner', labelKey: 'teamUsers.rolesOwner' },
  { value: 'manager', labelKey: 'teamUsers.rolesManager' },
  { value: 'staff', labelKey: 'teamUsers.rolesStaff' },
  { value: 'cashier', labelKey: 'teamUsers.rolesCashier' },
  { value: 'accountant', labelKey: 'teamUsers.rolesAccountant' },
  { value: 'technician', labelKey: 'teamUsers.rolesTechnician' },
  { value: 'viewer', labelKey: 'teamUsers.rolesViewer' },
]

const roleOptions = computed(() => {
  void locale.lang.value
  const defs = auth.isOwner ? allRoleDefs : allRoleDefs.filter((r) => r.value !== 'owner')
  return defs.map((r) => ({ value: r.value, label: locale.t(r.labelKey) }))
})

const filterRoleOptions = computed(() => {
  void locale.lang.value
  return allRoleDefs.map((r) => ({ value: r.value, label: locale.t(r.labelKey) }))
})

const form = reactive({
  name: '',
  email: '',
  password: '',
  role: 'staff',
  branch_id: '' as string,
  org_unit_id: '' as string,
  is_active: true,
})
const sectionLabels = NAV_SECTION_LABELS
const groupLabels = NAV_GROUP_LABELS
const sectionKeys = Object.keys(NAV_SECTION_LABELS)
const groupKeys = Object.keys(NAV_GROUP_LABELS)
const visibleSectionKeys = ref<string[]>([...sectionKeys])
const visibleGroupKeys = ref<string[]>([...groupKeys])
const formNav = reactive<NavVisibilityPolicy>(JSON.parse(JSON.stringify(DEFAULT_NAV_VISIBILITY)))

function roleLabel(role: string | { value?: string }): string {
  void locale.lang.value
  const v = typeof role === 'object' && role?.value ? role.value : String(role ?? '')
  const def = allRoleDefs.find((r) => r.value === v)
  return def ? locale.t(def.labelKey) : v
}

function orgTypeLabel(type: string): string {
  void locale.lang.value
  if (type === 'department') return locale.t('orgUnits.typeDept')
  if (type === 'division') return locale.t('orgUnits.typeDiv')
  return locale.t('orgUnits.typeSector')
}

function flattenOrg(nodes: any[], depth: number): { id: number; label: string }[] {
  const out: { id: number; label: string }[] = []
  for (const n of nodes || []) {
    const pad = ' '.repeat(depth)
    out.push({ id: n.id, label: `${pad}${n.name} (${orgTypeLabel(n.type)})` })
    if (n.children?.length) out.push(...flattenOrg(n.children, depth + 1))
  }
  return out
}

async function loadOrgUnits() {
  if (!biz.isEnabled('org_structure')) {
    orgFlat.value = []
    return
  }
  try {
    const { data } = await apiClient.get('/org-units/tree', { params: { active_only: true } })
    orgFlat.value = flattenOrg(data.data ?? [], 0)
  } catch {
    orgFlat.value = []
  }
}

async function loadBranches() {
  try {
    const { data } = await apiClient.get('/branches', { params: { per_page: 100 } })
    const p = data.data
    branches.value = Array.isArray(p?.data) ? p.data : Array.isArray(p) ? p : []
  } catch {
    branches.value = []
  }
}

function tInterpolate(key: string, vars: Record<string, string>): string {
  let s = locale.t(key)
  for (const [k, v] of Object.entries(vars)) {
    s = s.split(`{${k}}`).join(v)
  }
  return s
}

async function load() {
  loadError.value = ''
  loading.value = true
  try {
    const params: Record<string, string | number | boolean> = {
      page: page.value,
      per_page: 25,
    }
    const q = searchInput.value.trim()
    if (q) params.search = q
    if (filterBranchId.value) params.branch_id = Number(filterBranchId.value)
    if (filterRole.value) params.role = filterRole.value
    if (filterStatus.value === 'active') params.is_active = true
    if (filterStatus.value === 'inactive') params.is_active = false

    const { data } = await apiClient.get('/users', { params })
    const p = data.data
    if (p?.data) {
      rows.value = p.data
      lastPage.value = Math.max(1, Number(p.last_page ?? 1))
    } else {
      rows.value = []
      lastPage.value = 1
    }
  } catch (e: any) {
    loadError.value = e?.response?.data?.message ?? locale.t('teamUsers.loadError')
    rows.value = []
  } finally {
    loading.value = false
  }
}

async function loadCompanyNavigationPolicy() {
  if (!auth.user?.company_id) return
  try {
    const { data } = await apiClient.get(`/companies/${auth.user.company_id}/navigation-visibility`)
    const platform = data?.data?.platform_policy
    const company = data?.data?.company_policy
    visibleSectionKeys.value = sectionKeys.filter((key) => (platform?.sections?.[key] ?? true) !== false)
    visibleGroupKeys.value = groupKeys.filter((key) => (platform?.groups?.[key] ?? true) !== false)
    if (company?.sections && company?.groups) {
      for (const key of sectionKeys) formNav.sections[key] = company.sections[key] !== false
      for (const key of groupKeys) formNav.groups[key] = company.groups[key] !== false
      return
    }
  } catch {
    // ignore: fallback to defaults
  }
  visibleSectionKeys.value = [...sectionKeys]
  visibleGroupKeys.value = [...groupKeys]
  for (const key of sectionKeys) formNav.sections[key] = true
  for (const key of groupKeys) formNav.groups[key] = true
}

watch(searchInput, () => {
  if (searchDebounce) clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => {
    page.value = 1
    load()
  }, 350)
})

watch([filterBranchId, filterRole, filterStatus], () => {
  page.value = 1
  load()
})

watch(
  () => locale.lang.value,
  () => {
    if (biz.isEnabled('org_structure')) loadOrgUnits()
  },
)

function emptyForm() {
  form.name = ''
  form.email = ''
  form.password = ''
  form.role = auth.isOwner ? 'staff' : 'staff'
  form.branch_id = auth.user?.branch_id != null ? String(auth.user.branch_id) : ''
  form.org_unit_id = ''
  form.is_active = true
  for (const key of sectionKeys) formNav.sections[key] = true
  for (const key of groupKeys) formNav.groups[key] = true
}

async function openCreate() {
  await biz.load().catch(() => {})
  formError.value = ''
  editId.value = null
  emptyForm()
  await loadOrgUnits()
  await loadCompanyNavigationPolicy()
  showForm.value = true
}

async function openEdit(u: any) {
  await biz.load().catch(() => {})
  formError.value = ''
  editId.value = u.id
  form.name = u.name ?? ''
  form.email = u.email ?? ''
  form.password = ''
  const rv = typeof u.role === 'object' && u.role?.value ? u.role.value : String(u.role ?? 'staff')
  form.role = rv
  form.branch_id = u.branch_id != null ? String(u.branch_id) : ''
  form.org_unit_id = u.org_unit_id != null ? String(u.org_unit_id) : ''
  form.is_active = Boolean(u.is_active)
  await loadOrgUnits()
  await loadCompanyNavigationPolicy()
  const effective = u?.navigation_visibility_effective
  const override = u?.navigation_visibility_override
  const source = override?.sections ? override : effective
  if (source?.sections && source?.groups) {
    for (const key of sectionKeys) formNav.sections[key] = source.sections[key] !== false
    for (const key of groupKeys) formNav.groups[key] = source.groups[key] !== false
  }
  showForm.value = true
}

function closeForm() {
  showForm.value = false
}

async function save() {
  formError.value = ''
  saving.value = true
  try {
    const branchId = form.branch_id === '' ? null : Number(form.branch_id)
    const orgId =
      biz.isEnabled('org_structure') && form.org_unit_id !== '' ? Number(form.org_unit_id) : null

    if (!editId.value) {
      if (!form.password || form.password.length < 8) {
        formError.value = locale.t('teamUsers.passwordMin')
        saving.value = false
        return
      }
      await apiClient.post('/users', {
        name: form.name.trim(),
        email: form.email.trim(),
        password: form.password,
        role: form.role,
        branch_id: branchId,
        org_unit_id: orgId,
        is_active: form.is_active,
        nav_visibility: formNav,
      })
    } else {
      const body: Record<string, unknown> = {
        name: form.name.trim(),
        role: form.role,
        branch_id: branchId,
        is_active: form.is_active,
      }
      if (biz.isEnabled('org_structure')) {
        body.org_unit_id = orgId
      }
      if (form.password) body.password = form.password
      body.nav_visibility = formNav
      await apiClient.put(`/users/${editId.value}`, body)
    }
    closeForm()
    await load()
  } catch (e: any) {
    const msg = e?.response?.data?.message
    const errs = e?.response?.data?.errors
    if (errs && typeof errs === 'object') {
      formError.value = Object.values(errs).flat().join(' — ')
    } else {
      formError.value = msg ?? locale.t('teamUsers.saveError')
    }
  } finally {
    saving.value = false
  }
}

async function confirmDelete(u: any) {
  const ok = await appConfirm({
    title: locale.t('common.confirmDelete'),
    message: tInterpolate('teamUsers.deleteConfirm', { name: u.name ?? '' }),
    variant: 'danger',
    confirmLabel: locale.t('teamUsers.delete'),
    cancelLabel: locale.t('teamUsers.cancel'),
  })
  if (!ok) return
  try {
    await apiClient.delete(`/users/${u.id}`)
    await load()
  } catch (e: any) {
    toast.error(locale.t('teamUsers.deleteError'), e?.response?.data?.message ?? '')
  }
}

watch(
  () => biz.isEnabled('org_structure'),
  () => {
    loadOrgUnits()
  },
)

onMounted(async () => {
  await biz.load().catch(() => {})
  await loadBranches()
  await loadOrgUnits()
  await load()
})
</script>
