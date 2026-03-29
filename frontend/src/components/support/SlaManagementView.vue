<template>
  <div dir="rtl">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
        <ClockIcon class="w-6 h-6 text-purple-500" />
        سياسات مستوى الخدمة (SLA)
      </h2>
      <button @click="showNew = true" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-medium">
        + سياسة جديدة
      </button>
    </div>

    <!-- SLA Matrix -->
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div v-for="p in policies" :key="p.id" :class="priorityCardClass(p.priority)"
        class="rounded-xl p-4 border">
        <div class="flex items-center justify-between mb-3">
          <span class="font-bold text-sm">{{ priorityLabel(p.priority) }}</span>
          <span :class="p.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
            class="text-xs px-2 py-0.5 rounded-full">{{ p.is_active ? 'فعال' : 'معطّل' }}</span>
        </div>
        <div class="space-y-2 text-xs">
          <div class="flex items-center gap-2">
            <div class="w-2 h-2 rounded-full bg-blue-400"></div>
            <span class="text-gray-600 dark:text-gray-400">أول رد:</span>
            <span class="font-semibold">{{ p.first_response_hours }}ساعة</span>
          </div>
          <div class="flex items-center gap-2">
            <div class="w-2 h-2 rounded-full bg-green-400"></div>
            <span class="text-gray-600 dark:text-gray-400">حل:</span>
            <span class="font-semibold">{{ p.resolution_hours }}ساعة</span>
          </div>
          <div class="flex items-center gap-2">
            <div class="w-2 h-2 rounded-full bg-red-400"></div>
            <span class="text-gray-600 dark:text-gray-400">تصعيد بعد:</span>
            <span class="font-semibold">{{ p.escalation_after_hours }}ساعة</span>
          </div>
        </div>
        <div class="mt-3 flex gap-2">
          <button @click="editPolicy(p)" class="text-xs text-gray-500 hover:text-gray-700 underline">تعديل</button>
          <button @click="toggleActive(p)" class="text-xs" :class="p.is_active ? 'text-red-500 hover:text-red-700' : 'text-green-500 hover:text-green-700'">
            {{ p.is_active ? 'تعطيل' : 'تفعيل' }}
          </button>
        </div>
      </div>
    </div>

    <!-- SLA Explanation -->
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-5">
      <h3 class="font-semibold text-blue-700 dark:text-blue-300 mb-3">📋 كيف تعمل سياسات SLA؟</h3>
      <div class="grid sm:grid-cols-3 gap-4 text-sm text-blue-600 dark:text-blue-400">
        <div>
          <div class="font-semibold mb-1">أول رد</div>
          <p class="text-xs opacity-80">الوقت الأقصى للرد الأول على التذكرة. تجاوزه يُحدّث حقل "first_response_breached".</p>
        </div>
        <div>
          <div class="font-semibold mb-1">وقت الحل</div>
          <p class="text-xs opacity-80">الوقت الإجمالي لإغلاق التذكرة. تجاوزه يُصعّد التذكرة تلقائيًا.</p>
        </div>
        <div>
          <div class="font-semibold mb-1">التصعيد التلقائي</div>
          <p class="text-xs opacity-80">عند تجاوز وقت التصعيد، يُرسل تنبيه للأدوار المحددة ويتغير حالة التذكرة.</p>
        </div>
      </div>
    </div>

    <!-- New/Edit Policy Modal -->
    <Teleport to="body">
      <div v-if="showNew || editing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" dir="rtl">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md">
          <div class="flex items-center justify-between p-5 border-b">
            <h3 class="font-bold text-gray-900 dark:text-white">{{ editing ? 'تعديل' : 'سياسة جديدة' }}</h3>
            <button @click="closeModal"><XMarkIcon class="w-5 h-5 text-gray-400" /></button>
          </div>
          <form @submit.prevent="savePolicy" class="p-5 space-y-4">
            <div>
              <label class="text-sm font-medium text-gray-700 dark:text-gray-300 block mb-1">الاسم</label>
              <input v-model="form.name" required class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:text-white outline-none" />
            </div>
            <div>
              <label class="text-sm font-medium text-gray-700 dark:text-gray-300 block mb-1">الأولوية</label>
              <select v-model="form.priority" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:text-white">
                <option value="critical">حرجة</option>
                <option value="high">عالية</option>
                <option value="medium">متوسطة</option>
                <option value="low">منخفضة</option>
              </select>
            </div>
            <div class="grid grid-cols-3 gap-3">
              <div>
                <label class="text-xs text-gray-600 dark:text-gray-400 block mb-1">أول رد (ساعة)</label>
                <input v-model.number="form.first_response_hours" type="number" min="1" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:text-white outline-none" />
              </div>
              <div>
                <label class="text-xs text-gray-600 dark:text-gray-400 block mb-1">وقت الحل (ساعة)</label>
                <input v-model.number="form.resolution_hours" type="number" min="1" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:text-white outline-none" />
              </div>
              <div>
                <label class="text-xs text-gray-600 dark:text-gray-400 block mb-1">تصعيد بعد (ساعة)</label>
                <input v-model.number="form.escalation_after_hours" type="number" min="1" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:text-white outline-none" />
              </div>
            </div>
            <div class="flex justify-end gap-2">
              <button type="button" @click="closeModal" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">إلغاء</button>
              <button type="submit" class="px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-medium">حفظ</button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { ClockIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const policies = ref<any[]>([])
const showNew  = ref(false)
const editing  = ref<any>(null)
const form     = ref({ name: '', priority: 'medium', first_response_hours: 4, resolution_hours: 24, escalation_after_hours: 8 })

async function fetchPolicies() {
  const res = await axios.get('/api/v1/support/sla-policies')
  policies.value = res.data.data ?? []
}

async function savePolicy() {
  if (editing.value) {
    await axios.put(`/api/v1/support/sla-policies/${editing.value.id}`, form.value)
  } else {
    await axios.post('/api/v1/support/sla-policies', form.value)
  }
  closeModal()
  fetchPolicies()
}

async function toggleActive(p: any) {
  await axios.put(`/api/v1/support/sla-policies/${p.id}`, { ...p, is_active: !p.is_active })
  fetchPolicies()
}

function editPolicy(p: any) {
  editing.value = p
  form.value = { name: p.name, priority: p.priority, first_response_hours: p.first_response_hours, resolution_hours: p.resolution_hours, escalation_after_hours: p.escalation_after_hours }
}

function closeModal() { showNew.value = false; editing.value = null; form.value = { name: '', priority: 'medium', first_response_hours: 4, resolution_hours: 24, escalation_after_hours: 8 } }

const priorityColors: Record<string, string> = { critical: 'border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20', high: 'border-orange-200 dark:border-orange-800 bg-orange-50 dark:bg-orange-900/20', medium: 'border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-900/20', low: 'border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20' }
const priorityCardClass = (p: string) => priorityColors[p] ?? ''
const priorityLabel = (p: string) => ({ critical: '🔴 حرجة', high: '🟠 عالية', medium: '🟡 متوسطة', low: '🟢 منخفضة' }[p] ?? p)

onMounted(fetchPolicies)
</script>
