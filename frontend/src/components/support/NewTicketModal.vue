<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" dir="rtl" @click.self="$emit('close')">
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg">
        <div class="flex items-center justify-between p-5 border-b border-gray-200 dark:border-gray-700">
          <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
            <PlusCircleIcon class="w-5 h-5 text-blue-500" />
            تذكرة دعم جديدة
          </h2>
          <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="submit" class="p-5 space-y-4">
          <!-- AI Analysis Banner -->
          <div v-if="aiSuggestion" class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-3 text-sm">
            <p class="text-blue-700 dark:text-blue-300 font-medium mb-1">🤖 اقتراح الذكاء الاصطناعي</p>
            <div class="flex gap-3 text-blue-600 dark:text-blue-400 text-xs">
              <span>الفئة: <strong>{{ categoryLabel(aiSuggestion.category) }}</strong></span>
              <span>الأولوية: <strong>{{ priorityLabel(aiSuggestion.priority) }}</strong></span>
              <span>المشاعر: <strong>{{ sentimentLabel(aiSuggestion.sentiment) }}</strong></span>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">الموضوع *</label>
            <input v-model="form.subject" @blur="analyzeText" required placeholder="وصف مختصر للمشكلة..."
              class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none" />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">التفاصيل *</label>
            <textarea v-model="form.description" @blur="analyzeText" required rows="4" placeholder="اشرح المشكلة بالتفصيل..."
              class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none resize-none"></textarea>
          </div>

          <!-- Suggested KB Articles -->
          <div v-if="suggestedArticles.length" class="border border-amber-200 dark:border-amber-800 rounded-lg p-3 bg-amber-50 dark:bg-amber-900/20">
            <p class="text-xs font-semibold text-amber-700 dark:text-amber-400 mb-2">💡 قد تجد إجابتك في قاعدة المعرفة:</p>
            <ul class="space-y-1">
              <li v-for="a in suggestedArticles" :key="a.id">
                <a href="#" class="text-xs text-amber-600 dark:text-amber-400 hover:underline">{{ a.title }}</a>
              </li>
            </ul>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">الفئة</label>
              <select v-model="form.category" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:text-white">
                <option value="general">عامة</option>
                <option value="financial">مالية</option>
                <option value="technical">تقنية</option>
                <option value="vehicle">مركبات</option>
                <option value="operational">تشغيلية</option>
                <option value="billing">فوترة</option>
                <option value="complaint">شكاوى</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">الأولوية</label>
              <select v-model="form.priority" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:text-white">
                <option value="low">منخفضة</option>
                <option value="medium">متوسطة</option>
                <option value="high">عالية</option>
                <option value="critical">حرجة</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">القناة</label>
              <select v-model="form.channel" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:text-white">
                <option value="portal">البوابة</option>
                <option value="email">البريد</option>
                <option value="whatsapp">واتساب</option>
                <option value="phone">هاتف</option>
                <option value="walk_in">حضوري</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">العميل (اختياري)</label>
              <input v-model="form.customer_search" placeholder="اسم العميل..."
                class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:text-white" />
            </div>
          </div>

          <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="$emit('close')"
              class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-all">
              إلغاء
            </button>
            <button type="submit" :disabled="submitting"
              class="px-5 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded-lg text-sm font-medium transition-all flex items-center gap-2">
              <ArrowPathIcon v-if="submitting" class="w-4 h-4 animate-spin" />
              <span>{{ submitting ? 'جارٍ الإنشاء...' : 'إنشاء التذكرة' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, watch, watchEffect } from 'vue'
import axios from 'axios'
import { PlusCircleIcon, XMarkIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'

const emit = defineEmits(['close', 'created'])

const form = ref({
  subject: '', description: '', category: 'general', priority: 'medium',
  channel: 'portal', customer_search: '',
})

const submitting       = ref(false)
const aiSuggestion     = ref<any>(null)
const suggestedArticles= ref<any[]>([])

const analyzeText = async () => {
  if (form.value.subject.length < 5) return
  try {
    const res = await axios.get('/api/v1/support/kb/search', { params: { q: form.value.subject } })
    suggestedArticles.value = res.data.data?.slice(0, 3) ?? []
  } catch {}
}

// Client-side AI hint based on keywords
watchEffect(() => { const text = form.value.subject + form.value.description
  if (text.length < 10) { aiSuggestion.value = null; return }
  const t = text.toLowerCase()
  let category = 'general', priority = 'medium', sentiment = 0
  if (/فاتورة|دفع|رصيد|مالي/.test(t)) category = 'financial'
  else if (/مركبة|سيارة|لوحة/.test(t)) category = 'vehicle'
  else if (/خطأ|لا يعمل|مشكلة/.test(t)) category = 'technical'
  if (/عاجل|حرج|urgent|critical/.test(t)) priority = 'critical'
  else if (/مهم|important/.test(t)) priority = 'high'
  if (/غاضب|سيئ|خطأ/.test(t)) sentiment = -0.5
  else if (/شكراً|ممتاز/.test(t)) sentiment = 0.8
  aiSuggestion.value = { category, priority, sentiment }
  form.value.category = category
  form.value.priority = priority
})

async function submit() {
  submitting.value = true
  try {
    const res = await axios.post('/api/v1/support/tickets', {
      subject: form.value.subject,
      description: form.value.description,
      category: form.value.category,
      priority: form.value.priority,
      channel: form.value.channel,
    })
    emit('created', res.data.data)
  } finally {
    submitting.value = false
  }
}

const catMap: Record<string, string> = { financial: 'مالية', technical: 'تقنية', vehicle: 'مركبات', general: 'عامة', operational: 'تشغيلية', billing: 'فوترة', complaint: 'شكاوى' }
const priMap: Record<string, string> = { critical: 'حرجة', high: 'عالية', medium: 'متوسطة', low: 'منخفضة' }
const categoryLabel = (c: string) => catMap[c] ?? c
const priorityLabel = (p: string) => priMap[p] ?? p
const sentimentLabel = (s: number) => s > 0.3 ? '😊 إيجابي' : s < -0.3 ? '😤 سلبي' : '😐 محايد'
</script>
