<template>
  <div class="max-w-4xl mx-auto space-y-6 p-4" :dir="locale.langInfo.value.dir">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <RouterLink to="/settings?tab=profile" class="text-xs text-primary-600 hover:underline">{{ locale.t('orgUnits.backProfile') }}</RouterLink>
        <h1 class="text-xl font-bold text-gray-900 dark:text-slate-100 mt-1">{{ locale.t('orgUnits.title') }}</h1>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">
          {{ locale.t('orgUnits.intro') }}
        </p>
      </div>
      <button
        type="button"
        class="px-4 py-2 text-sm rounded-lg bg-primary-600 text-white hover:bg-primary-700 disabled:opacity-50"
        :disabled="loading"
        @click="loadTree"
      >
        {{ locale.t('orgUnits.refresh') }}
      </button>
    </div>

    <div v-if="forbidden" class="rounded-xl border border-amber-200 bg-amber-50 dark:bg-amber-950/30 dark:border-amber-800 p-4 text-sm text-amber-900 dark:text-amber-100">
      {{ locale.t('orgUnits.forbiddenHint') }}
      <RouterLink class="font-semibold underline ms-1" to="/settings?tab=profile">{{ locale.t('orgUnits.forbiddenLink') }}</RouterLink>.
    </div>

    <div v-else class="grid gap-6 lg:grid-cols-2">
      <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
        <h2 class="text-sm font-semibold text-gray-800 dark:text-slate-200 mb-3">{{ locale.t('orgUnits.treeTitle') }}</h2>
        <div v-if="loadError" class="text-sm text-red-600">{{ loadError }}</div>
        <ul v-else class="space-y-1 text-sm max-h-[32rem] overflow-auto">
          <li
            v-for="row in flatRows"
            :key="row.id"
            class="flex items-center justify-between gap-2 py-1.5 px-2 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700/60"
            :style="{ marginInlineStart: row.depth * 12 + 'px' }"
          >
            <span class="text-gray-800 dark:text-slate-100">
              <span class="text-xs text-gray-400 me-1">{{ typeLabel(row.type) }}</span>
              {{ row.name }}
            </span>
            <div class="flex items-center gap-2 shrink-0">
              <button type="button" class="text-xs text-emerald-700 hover:underline" @click="startCreateUnder(row)">
                + {{ locale.t('common.add') }}
              </button>
              <button type="button" class="text-xs text-indigo-700 hover:underline" @click="startEdit(row)">
                {{ locale.t('common.edit') }}
              </button>
              <button type="button" class="text-xs text-red-600 hover:underline" @click="confirmRemove(row)">
                {{ locale.t('orgUnits.delete') }}
              </button>
            </div>
          </li>
        </ul>
        <p v-if="!loading && !flatRows.length && !loadError" class="text-gray-400 text-sm">{{ locale.t('orgUnits.noneYet') }}</p>
      </div>

      <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4 space-y-3">
        <h2 class="text-sm font-semibold text-gray-800 dark:text-slate-200">
          {{ editId ? locale.t('common.edit') : locale.t('orgUnits.addTitle') }}
        </h2>
        <div class="space-y-2">
          <label class="block text-xs text-gray-500">{{ locale.t('orgUnits.parentLabel') }}</label>
          <select v-model="form.parent_id" class="w-full border rounded-lg px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600">
            <option :value="''">{{ locale.t('orgUnits.rootOption') }}</option>
            <option v-for="o in flatOptions" :key="o.id" :value="String(o.id)">{{ o.label }}</option>
          </select>
        </div>
        <div class="space-y-2">
          <label class="block text-xs text-gray-500">{{ locale.t('orgUnits.typeLabel') }}</label>
          <select v-model="form.type" class="w-full border rounded-lg px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600">
            <option value="sector">{{ locale.t('orgUnits.typeSector') }}</option>
            <option value="department">{{ locale.t('orgUnits.typeDept') }}</option>
            <option value="division">{{ locale.t('orgUnits.typeDiv') }}</option>
          </select>
        </div>
        <div class="space-y-2">
          <label class="block text-xs text-gray-500">{{ locale.t('orgUnits.nameLabel') }}</label>
          <input v-model="form.name" type="text" class="w-full border rounded-lg px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600" />
        </div>
        <p v-if="saveError" class="text-sm text-red-600">{{ saveError }}</p>
        <button
          type="button"
          class="w-full py-2 rounded-lg bg-primary-600 text-white text-sm hover:bg-primary-700 disabled:opacity-50"
          :disabled="saving || !form.name.trim()"
          @click="submitSave"
        >
          {{ saving ? locale.t('orgUnits.saving') : locale.t('orgUnits.save') }}
        </button>
        <button
          v-if="editId"
          type="button"
          class="w-full py-2 rounded-lg border border-gray-300 text-gray-700 text-sm hover:bg-gray-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
          :disabled="saving"
          @click="resetForm"
        >
          {{ locale.t('common.cancel') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import apiClient from '@/lib/apiClient'
import { useBusinessProfileStore } from '@/stores/businessProfile'
import { useLocale } from '@/composables/useLocale'
import { useToast } from '@/composables/useToast'
import { appConfirm } from '@/services/appConfirmDialog'

interface OrgNode {
  id: number
  parent_id?: number | null
  name: string
  type: string
  children?: OrgNode[]
}

interface FlatRow {
  id: number
  name: string
  type: string
  depth: number
}

const biz = useBusinessProfileStore()
const locale = useLocale()
const toast = useToast()
const tree = ref<OrgNode[]>([])
const loading = ref(false)
const saving = ref(false)
const loadError = ref('')
const saveError = ref('')
const forbidden = ref(false)

const form = reactive({
  parent_id: '' as string,
  type: 'sector',
  name: '',
})
const editId = ref<number | null>(null)

function flattenNodes(nodes: OrgNode[], depth: number): FlatRow[] {
  const out: FlatRow[] = []
  for (const n of nodes) {
    out.push({ id: n.id, name: n.name, type: n.type, depth })
    if (n.children?.length) out.push(...flattenNodes(n.children, depth + 1))
  }
  return out
}

const flatRows = computed(() => flattenNodes(tree.value, 0))

function typeLabel(t: string): string {
  void locale.lang.value
  if (t === 'department') return locale.t('orgUnits.typeDept')
  if (t === 'division') return locale.t('orgUnits.typeDiv')
  return locale.t('orgUnits.typeSector')
}

const flatOptions = computed(() => {
  void locale.lang.value
  const blocked = new Set<number>()
  if (editId.value !== null) {
    blocked.add(editId.value)
    const src = tree.value
    const visit = (nodes: OrgNode[]): boolean => {
      for (const n of nodes) {
        if (n.id === editId.value) {
          collectChildren(n, blocked)
          return true
        }
        if (n.children?.length && visit(n.children)) return true
      }
      return false
    }
    visit(src)
  }
  return flatRows.value.map((r) => ({
    id: r.id,
    label: `${' '.repeat(r.depth)}${r.name} (${typeLabel(r.type)})`,
  })).filter((x) => !blocked.has(x.id))
})

function tInterpolate(key: string, vars: Record<string, string>): string {
  let s = locale.t(key)
  for (const [k, v] of Object.entries(vars)) {
    s = s.split(`{${k}}`).join(v)
  }
  return s
}

async function loadTree() {
  forbidden.value = false
  loadError.value = ''
  loading.value = true
  try {
    await biz.load()
    if (!biz.isEnabled('org_structure')) {
      forbidden.value = true
      return
    }
    const { data } = await apiClient.get('/org-units/tree', { params: { active_only: 'false' } })
    tree.value = data.data ?? []
  } catch (e: any) {
    if (e?.response?.status === 403) {
      forbidden.value = true
    } else {
      loadError.value = e?.response?.data?.message ?? locale.t('orgUnits.loadError')
    }
  } finally {
    loading.value = false
  }
}

async function submitCreate() {
  saveError.value = ''
  saving.value = true
  try {
    const body: Record<string, unknown> = {
      type: form.type,
      name: form.name.trim(),
    }
    if (form.parent_id !== '') body.parent_id = Number(form.parent_id)
    await apiClient.post('/org-units', body)
    form.name = ''
    await loadTree()
  } catch (e: any) {
    saveError.value = e?.response?.data?.message ?? locale.t('orgUnits.saveError')
  } finally {
    saving.value = false
  }
}

function collectChildren(node: OrgNode, bucket: Set<number>) {
  if (!node.children?.length) return
  for (const child of node.children) {
    bucket.add(child.id)
    collectChildren(child, bucket)
  }
}

function startCreateUnder(row: FlatRow) {
  resetForm()
  form.parent_id = String(row.id)
  form.type = row.type === 'sector' ? 'department' : 'division'
}

function startEdit(row: FlatRow) {
  editId.value = row.id
  form.name = row.name
  form.type = row.type === 'department' || row.type === 'division' ? row.type : 'sector'
  const source = findNodeById(tree.value, row.id)
  form.parent_id = source?.parent_id != null ? String(source.parent_id) : ''
}

function findNodeById(nodes: OrgNode[], id: number): OrgNode | null {
  for (const node of nodes) {
    if (node.id === id) return node
    if (node.children?.length) {
      const found = findNodeById(node.children, id)
      if (found) return found
    }
  }
  return null
}

function resetForm() {
  editId.value = null
  form.parent_id = ''
  form.type = 'sector'
  form.name = ''
}

async function submitSave() {
  if (editId.value) {
    saveError.value = ''
    saving.value = true
    try {
      const body: Record<string, unknown> = {
        type: form.type,
        name: form.name.trim(),
        parent_id: form.parent_id === '' ? null : Number(form.parent_id),
      }
      await apiClient.put(`/org-units/${editId.value}`, body)
      resetForm()
      await loadTree()
    } catch (e: any) {
      saveError.value = e?.response?.data?.message ?? locale.t('orgUnits.saveError')
    } finally {
      saving.value = false
    }
    return
  }
  await submitCreate()
}

async function confirmRemove(row: FlatRow) {
  const ok = await appConfirm({
    title: locale.t('common.confirmDelete'),
    message: tInterpolate('orgUnits.deleteConfirm', { name: row.name }),
    variant: 'danger',
    confirmLabel: locale.t('orgUnits.delete'),
    cancelLabel: locale.t('common.cancel'),
  })
  if (!ok) return
  try {
    await apiClient.delete(`/org-units/${row.id}`)
    await loadTree()
  } catch (e: any) {
    toast.error(locale.t('orgUnits.deleteError'), e?.response?.data?.message ?? '')
  }
}

onMounted(() => {
  loadTree()
})
</script>
