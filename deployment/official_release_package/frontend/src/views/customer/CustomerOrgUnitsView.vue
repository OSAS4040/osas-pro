<template>
  <section class="space-y-4">
    <header class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2 class="text-lg font-bold text-gray-900 dark:text-slate-100">هيكل القطاعات والأقسام</h2>
        <p class="text-xs text-gray-500 dark:text-slate-400">إدارة كاملة لهيكل القطاعات: إضافة، تعديل، وحذف.</p>
      </div>
      <div class="flex items-center gap-2">
        <span class="rounded-lg bg-violet-50 px-2.5 py-1 text-[11px] font-semibold text-violet-700 dark:bg-violet-900/40 dark:text-violet-200">
          إجمالي الوحدات: {{ flatRows.length }}
        </span>
        <button type="button" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-600 text-white hover:bg-emerald-700" @click="startCreate()">
          إضافة وحدة
        </button>
        <button type="button" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-primary-600 text-white hover:bg-primary-700" :disabled="loading" @click="load">
          تحديث
        </button>
      </div>
    </header>

    <section class="rounded-2xl border border-gray-100 bg-white p-3 dark:border-slate-700 dark:bg-slate-800">
      <div class="grid gap-2 md:grid-cols-3">
        <div>
          <label class="mb-1 block text-[10px] text-gray-500">بحث بالاسم</label>
          <input v-model.trim="searchQuery" type="search" placeholder="ابحث عن قطاع أو قسم" class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs dark:border-slate-600 dark:bg-slate-900">
        </div>
        <div>
          <label class="mb-1 block text-[10px] text-gray-500">نوع الوحدة</label>
          <select v-model="typeFilter" class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs dark:border-slate-600 dark:bg-slate-900">
            <option value="">الكل</option>
            <option v-for="t in availableTypes" :key="t" :value="t">{{ typeLabel(t) }}</option>
          </select>
        </div>
        <div class="flex items-end">
          <span class="inline-flex rounded-lg bg-violet-50 px-2.5 py-1 text-[11px] font-semibold text-violet-700 dark:bg-violet-900/40 dark:text-violet-200">
            النتائج: {{ filteredRows.length }}
          </span>
        </div>
      </div>
    </section>

    <div v-if="isEditing" class="rounded-2xl border border-indigo-200 bg-indigo-50/70 p-4 dark:border-indigo-700 dark:bg-indigo-950/20">
      <div class="grid gap-3 md:grid-cols-3">
        <div>
          <label class="mb-1 block text-[10px] text-gray-500">اسم الوحدة</label>
          <input v-model.trim="form.name" type="text" class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs dark:border-slate-600 dark:bg-slate-900">
        </div>
        <div>
          <label class="mb-1 block text-[10px] text-gray-500">الاسم العربي (اختياري)</label>
          <input v-model.trim="form.name_ar" type="text" class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs dark:border-slate-600 dark:bg-slate-900">
        </div>
        <div>
          <label class="mb-1 block text-[10px] text-gray-500">النوع</label>
          <select v-model="form.type" class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs dark:border-slate-600 dark:bg-slate-900">
            <option value="sector">قطاع</option>
            <option value="department">إدارة</option>
            <option value="division">وحدة / شعبة</option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-[10px] text-gray-500">الوحدة الأب</label>
          <select v-model.number="form.parent_id" class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs dark:border-slate-600 dark:bg-slate-900">
            <option :value="0">بدون (جذر)</option>
            <option v-for="opt in parentOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-[10px] text-gray-500">الكود</label>
          <input v-model.trim="form.code" type="text" class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs dark:border-slate-600 dark:bg-slate-900">
        </div>
        <div>
          <label class="mb-1 block text-[10px] text-gray-500">الترتيب</label>
          <input v-model.number="form.sort_order" type="number" min="0" class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs dark:border-slate-600 dark:bg-slate-900">
        </div>
      </div>
      <div class="mt-3 flex items-center gap-2">
        <button type="button" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-primary-600 text-white hover:bg-primary-700 disabled:opacity-60" :disabled="saving" @click="saveForm">
          {{ saving ? 'جارٍ الحفظ...' : (editingId ? 'حفظ التعديل' : 'إنشاء الوحدة') }}
        </button>
        <button type="button" class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-gray-300 text-gray-700" :disabled="saving" @click="cancelEdit">
          إلغاء
        </button>
      </div>
    </div>

    <div v-if="forbidden" class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
      هذا الحساب غير مصرح له بالوصول لهيكل القطاعات.
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 p-4">
      <div v-if="loading" class="py-10 text-center text-gray-400 text-sm">جارٍ التحميل...</div>
      <div v-else-if="errorMessage" class="py-10 text-center text-red-500 text-sm">{{ errorMessage }}</div>
      <div v-else class="space-y-2">
        <div class="flex items-center justify-between text-xs text-gray-500">
          <span>عرض {{ pageStart }} - {{ pageEnd }} من {{ filteredRows.length }}</span>
          <div class="flex items-center gap-2">
            <button type="button" class="px-2 py-1 rounded border border-gray-200 disabled:opacity-50" :disabled="currentPage <= 1" @click="currentPage -= 1">السابق</button>
            <span>صفحة {{ currentPage }} / {{ totalPages }}</span>
            <button type="button" class="px-2 py-1 rounded border border-gray-200 disabled:opacity-50" :disabled="currentPage >= totalPages" @click="currentPage += 1">التالي</button>
          </div>
        </div>
        <ul class="space-y-1.5 text-sm max-h-[34rem] overflow-auto">
          <li v-for="node in paginatedRows" :key="node.id" class="flex items-center gap-2 px-2.5 py-2 rounded-lg border border-gray-100 hover:bg-gray-50 dark:border-slate-700 dark:hover:bg-slate-700/60" :style="{ marginInlineStart: `${node.depth * 14}px` }">
            <span class="h-1.5 w-1.5 rounded-full bg-violet-400" />
            <span class="text-[11px] rounded-md bg-gray-100 px-1.5 py-0.5 text-gray-500 dark:bg-slate-700 dark:text-slate-300">{{ typeLabel(node.type, node.depth) }}</span>
            <span class="text-gray-800 dark:text-slate-100 flex-1">{{ node.name }}</span>
            <button type="button" class="inline-flex items-center gap-1 px-2 py-1 text-[11px] rounded border border-emerald-200 text-emerald-700" @click="startCreate(node.id, suggestedChildType(node.type))">
              <PlusCircleIcon class="h-3.5 w-3.5" />
              إضافة تابع
            </button>
            <button type="button" class="inline-flex items-center gap-1 px-2 py-1 text-[11px] rounded border border-indigo-200 text-indigo-700" @click="startEdit(node)">
              <PencilSquareIcon class="h-3.5 w-3.5" />
              تعديل
            </button>
            <button type="button" class="inline-flex items-center gap-1 px-2 py-1 text-[11px] rounded border border-rose-200 text-rose-700" @click="removeNode(node)">
              <TrashIcon class="h-3.5 w-3.5" />
              حذف
            </button>
          </li>
          <li v-if="!filteredRows.length" class="py-8 text-center text-gray-400">لا توجد نتائج مطابقة للفلتر الحالي.</li>
        </ul>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { PencilSquareIcon, PlusCircleIcon, TrashIcon } from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import { useToast } from '@/composables/useToast'

type OrgNode = {
  id: number
  parent_id?: number | null
  name: string
  name_ar?: string | null
  type: string
  code?: string | null
  sort_order?: number
  children?: OrgNode[]
}

type FlatOrgNode = OrgNode & { depth: number; label: string }

const toast = useToast()
const loading = ref(true)
const saving = ref(false)
const forbidden = ref(false)
const errorMessage = ref('')
const tree = ref<OrgNode[]>([])
const searchQuery = ref('')
const typeFilter = ref('')
const pageSize = 20
const currentPage = ref(1)
const editingId = ref<number | null>(null)
const formVisible = ref(false)

const form = reactive({
  name: '',
  name_ar: '',
  type: 'sector',
  parent_id: 0,
  code: '',
  sort_order: 0,
})

const isEditing = computed(() => formVisible.value)

function flatten(nodes: OrgNode[], depth = 0): FlatOrgNode[] {
  const out: FlatOrgNode[] = []
  for (const node of nodes) {
    out.push({ ...node, depth, label: `${'— '.repeat(depth)}${node.name}` })
    if (Array.isArray(node.children) && node.children.length) out.push(...flatten(node.children, depth + 1))
  }
  return out
}

const flatRows = computed(() => flatten(tree.value))
const filteredRows = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  return flatRows.value.filter((row) => {
    if (typeFilter.value && row.type !== typeFilter.value) return false
    if (q && !String(row.name || '').toLowerCase().includes(q)) return false
    return true
  })
})
const totalPages = computed(() => Math.max(1, Math.ceil(filteredRows.value.length / pageSize)))
const pageStart = computed(() => (filteredRows.value.length ? (currentPage.value - 1) * pageSize + 1 : 0))
const pageEnd = computed(() => Math.min(currentPage.value * pageSize, filteredRows.value.length))
const paginatedRows = computed(() => filteredRows.value.slice((currentPage.value - 1) * pageSize, currentPage.value * pageSize))
const availableTypes = computed(() => Array.from(new Set(flatRows.value.map((r) => String(r.type || '')).filter(Boolean))))
const blockedParentIds = computed(() => {
  if (!editingId.value) return new Set<number>()
  const blocked = new Set<number>([editingId.value])
  const visit = (nodes: OrgNode[]): boolean => {
    for (const node of nodes) {
      if (node.id === editingId.value) {
        collectChildren(node, blocked)
        return true
      }
      if (Array.isArray(node.children) && visit(node.children)) return true
    }
    return false
  }
  visit(tree.value)
  return blocked
})
const parentOptions = computed(() => flatRows.value
  .filter((x) => !blockedParentIds.value.has(x.id))
  .map((x) => ({ id: x.id, label: x.label })))

watch([searchQuery, typeFilter], () => { currentPage.value = 1 })
watch(totalPages, (next) => { if (currentPage.value > next) currentPage.value = next })

function typeLabel(value: string, depth = 0): string {
  if (value === 'department') return 'إدارة'
  if (value === 'division') return depth >= 3 ? 'شعبة' : 'وحدة'
  return 'قطاع'
}

function clearForm(): void {
  editingId.value = null
  formVisible.value = false
  form.name = ''
  form.name_ar = ''
  form.type = 'sector'
  form.parent_id = 0
  form.code = ''
  form.sort_order = 0
}

function collectChildren(node: OrgNode, bucket: Set<number>): void {
  if (!Array.isArray(node.children)) return
  for (const child of node.children) {
    bucket.add(child.id)
    collectChildren(child, bucket)
  }
}

function suggestedChildType(parentType: string): string {
  if (parentType === 'sector') return 'department'
  return 'division'
}

function startCreate(parentId = 0, type = 'sector'): void {
  clearForm()
  formVisible.value = true
  form.parent_id = parentId
  form.type = type
}

function startEdit(node: FlatOrgNode): void {
  formVisible.value = true
  editingId.value = Number(node.id)
  form.name = node.name || ''
  form.name_ar = node.name_ar || ''
  form.type = node.type || 'sector'
  form.parent_id = Number(node.parent_id || 0)
  form.code = node.code || ''
  form.sort_order = Number(node.sort_order || 0)
}

function cancelEdit(): void {
  clearForm()
}

async function saveForm(): Promise<void> {
  if (!form.name.trim()) {
    toast.warning('بيانات ناقصة', 'اسم الوحدة مطلوب.')
    return
  }
  saving.value = true
  try {
    const payload: Record<string, unknown> = {
      name: form.name.trim(),
      name_ar: form.name_ar.trim() || null,
      type: form.type,
      parent_id: form.parent_id > 0 ? form.parent_id : null,
      code: form.code.trim() || null,
      sort_order: Number(form.sort_order || 0),
      is_active: true,
    }
    if (editingId.value) {
      await apiClient.put(`/customer-portal/org-units/${editingId.value}`, payload)
      toast.success('تم التحديث', 'تم تعديل الوحدة بنجاح.')
    } else {
      await apiClient.post('/customer-portal/org-units', payload)
      toast.success('تم الإنشاء', 'تم إنشاء وحدة جديدة بنجاح.')
    }
    clearForm()
    await load()
  } catch (e: any) {
    toast.warning('تعذر الحفظ', e?.response?.data?.message || 'تعذر تنفيذ العملية.')
  } finally {
    saving.value = false
  }
}

async function removeNode(node: FlatOrgNode): Promise<void> {
  if (!window.confirm(`حذف الوحدة: ${node.name}؟`)) return
  try {
    await apiClient.delete(`/customer-portal/org-units/${node.id}`)
    toast.success('تم الحذف', 'تم حذف الوحدة بنجاح.')
    await load()
  } catch (e: any) {
    toast.warning('تعذر الحذف', e?.response?.data?.message || 'لا يمكن حذف هذه الوحدة حالياً.')
  }
}

async function load(): Promise<void> {
  loading.value = true
  forbidden.value = false
  errorMessage.value = ''
  try {
    const { data } = await apiClient.get('/customer-portal/org-units/tree', { params: { active_only: false } })
    tree.value = Array.isArray(data?.data) ? data.data : []
  } catch (e: any) {
    if (e?.response?.status === 403) forbidden.value = true
    else errorMessage.value = e?.response?.data?.message || 'تعذّر تحميل هيكل القطاعات حالياً.'
    tree.value = []
  } finally {
    currentPage.value = 1
    loading.value = false
  }
}

onMounted(load)
</script>
