<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex" dir="rtl">
      <!-- Backdrop -->
      <div class="flex-1 bg-black/40" @click="$emit('close')"></div>

      <!-- Drawer -->
      <div class="w-full max-w-2xl bg-white dark:bg-gray-900 flex flex-col h-full shadow-2xl overflow-hidden">
        <!-- Header -->
        <div class="flex items-start justify-between p-5 border-b border-gray-200 dark:border-gray-700">
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-1">
              <span class="font-mono text-sm text-blue-600 dark:text-blue-400 font-bold">{{ ticket.ticket_number }}</span>
              <PriorityBadge :priority="ticket.priority" />
              <StatusBadge :status="ticket.status" />
            </div>
            <h2 class="font-bold text-gray-900 dark:text-white text-base">{{ ticket.subject }}</h2>
            <p class="text-xs text-gray-500 mt-0.5">
              {{ categoryLabel(ticket.category) }} · {{ ticket.channel }} · {{ formatDate(ticket.created_at) }}
            </p>
          </div>
          <button class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 mr-4" @click="$emit('close')">
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>

        <!-- SLA Bar -->
        <div v-if="detail?.sla_due_at" class="px-5 py-3 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between text-xs mb-1">
            <span class="text-gray-600 dark:text-gray-400">SLA</span>
            <SlaIndicator :ticket="detail" />
          </div>
        </div>

        <!-- Tabs -->
        <div class="flex border-b border-gray-200 dark:border-gray-700 px-5">
          <button v-for="t in tabs" :key="t.key" :class="tab === t.key ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-gray-500'"
                  class="px-4 py-3 text-sm font-medium transition-all"
                  @click="tab = t.key"
          >
            {{ t.label }}
          </button>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto p-5">
          <!-- Details Tab -->
          <div v-if="tab === 'details'">
            <div class="prose dark:prose-invert prose-sm max-w-none mb-6">
              <p class="text-gray-700 dark:text-gray-300">{{ ticket.description }}</p>
            </div>

            <!-- AI Insights -->
            <div v-if="ticket.ai_sentiment_score !== null" class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-xl p-4 mb-4">
              <h4 class="text-sm font-semibold text-blue-700 dark:text-blue-300 mb-3">🤖 تحليل الذكاء الاصطناعي</h4>
              <div class="grid grid-cols-3 gap-3 text-center">
                <div class="bg-white dark:bg-gray-800 rounded-lg p-2">
                  <div class="text-lg">{{ sentimentEmoji }}</div>
                  <div class="text-xs text-gray-600 dark:text-gray-400">المشاعر</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg p-2">
                  <div class="text-sm font-bold text-gray-800 dark:text-gray-200">{{ categoryLabel(ticket.ai_category_suggestion) }}</div>
                  <div class="text-xs text-gray-600 dark:text-gray-400">الفئة المقترحة</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg p-2">
                  <div class="text-sm font-bold text-gray-800 dark:text-gray-200">{{ priorityLabel(ticket.ai_priority_suggestion) }}</div>
                  <div class="text-xs text-gray-600 dark:text-gray-400">الأولوية المقترحة</div>
                </div>
              </div>
            </div>

            <!-- Suggested KB Articles -->
            <div v-if="suggestedArticles.length" class="bg-amber-50 dark:bg-amber-900/20 rounded-xl p-4 mb-4">
              <h4 class="text-sm font-semibold text-amber-700 dark:text-amber-300 mb-2">💡 مقالات ذات صلة</h4>
              <ul class="space-y-1">
                <li v-for="a in suggestedArticles" :key="a.id" class="text-sm text-amber-600 dark:text-amber-400">
                  📄 {{ a.title }}
                </li>
              </ul>
            </div>

            <!-- Change Status -->
            <div class="border border-gray-200 dark:border-gray-700 rounded-xl p-4">
              <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">تغيير الحالة</h4>
              <div class="flex flex-wrap gap-2">
                <button v-for="s in statusOptions" :key="s.value"
                        :disabled="detail?.status === s.value"
                        :class="detail?.status === s.value ? 'opacity-50 cursor-not-allowed' : 'hover:shadow-sm'"
                        class="px-3 py-1.5 text-xs rounded-lg border font-medium transition-all"
                        :style="{ borderColor: s.color, color: s.color }"
                        @click="changeStatus(s.value)"
                >
                  {{ s.label }}
                </button>
              </div>
            </div>
          </div>

          <!-- Replies Tab -->
          <div v-if="tab === 'replies'">
            <div class="space-y-4 mb-6">
              <div v-for="r in detail?.replies" :key="r.id"
                   :class="r.is_internal ? 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800' : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700'"
                   class="rounded-xl border p-4"
              >
                <div class="flex items-center justify-between mb-2">
                  <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full bg-blue-100 dark:bg-blue-900/50 flex items-center justify-center text-xs font-bold text-blue-700 dark:text-blue-300">
                      {{ (r.author_name || r.user?.name || 'S').charAt(0) }}
                    </div>
                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ r.author_name || r.user?.name }}</span>
                    <span v-if="r.is_internal" class="text-xs bg-yellow-200 dark:bg-yellow-800 text-yellow-800 dark:text-yellow-200 px-1.5 py-0.5 rounded">داخلي</span>
                    <span v-if="r.event_type !== 'reply'" class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 px-1.5 py-0.5 rounded">{{ eventTypeLabel(r.event_type) }}</span>
                  </div>
                  <span class="text-xs text-gray-400">{{ formatDate(r.created_at) }}</span>
                </div>
                <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ r.body }}</p>
              </div>
            </div>

            <!-- Reply Box -->
            <div class="border border-gray-200 dark:border-gray-700 rounded-xl p-4">
              <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">إضافة رد</h4>
              <textarea v-model="replyBody" rows="3" placeholder="اكتب ردك هنا..."
                        class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:text-white resize-none outline-none focus:ring-2 focus:ring-blue-500"
              ></textarea>
              <div class="flex items-center justify-between mt-3">
                <label class="flex items-center gap-2 text-xs text-gray-500 cursor-pointer">
                  <input v-model="replyInternal" type="checkbox" class="rounded" />
                  ملاحظة داخلية (غير مرئية للعميل)
                </label>
                <button :disabled="!replyBody || sendingReply" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded-lg text-sm font-medium transition-all flex items-center gap-2"
                        @click="sendReply"
                >
                  <ArrowPathIcon v-if="sendingReply" class="w-4 h-4 animate-spin" />
                  إرسال
                </button>
              </div>
            </div>
          </div>

          <!-- Satisfaction Tab (واجهة المستأجر فقط — لا يُعرض من مشغّل المنصة) -->
          <div v-if="tab === 'satisfaction' && showSatisfactionTab" class="text-center py-8">
            <StarIcon class="w-16 h-16 text-amber-400 mx-auto mb-4" />
            <h3 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-2">تقييم جودة الدعم</h3>
            <p class="text-gray-500 mb-6 text-sm">كيف تقيّم تجربتك مع فريق الدعم؟</p>
            <div class="flex justify-center gap-3 mb-6">
              <button v-for="s in [1,2,3,4,5]" :key="s"
                      :class="satScore >= s ? 'text-amber-400 scale-110' : 'text-gray-300'"
                      class="text-4xl transition-all hover:scale-110"
                      @click="satScore = s"
              >
                ★
              </button>
            </div>
            <textarea v-model="satComment" rows="3" placeholder="تعليق إضافي..."
                      class="w-full max-w-md mx-auto block border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:text-white outline-none focus:ring-2 focus:ring-amber-400 resize-none mb-4"
            ></textarea>
            <button :disabled="!satScore" class="px-6 py-2 bg-amber-500 hover:bg-amber-600 disabled:opacity-50 text-white rounded-lg font-medium transition-all"
                    @click="submitRating"
            >
              إرسال التقييم
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import axios from 'axios'
import { XMarkIcon, ArrowPathIcon, StarIcon } from '@heroicons/vue/24/outline'
import PriorityBadge from './PriorityBadge.vue'
import StatusBadge from './StatusBadge.vue'
import SlaIndicator from './SlaIndicator.vue'

const props = withDefaults(
  defineProps<{
    ticket: any
    /** جذر مسار التذاكر، مثال: `/api/v1/support/tickets` أو `/api/v1/platform/support/tickets` */
    ticketsApiBase?: string
  }>(),
  { ticketsApiBase: '/api/v1/support/tickets' },
)
const emit = defineEmits(['close', 'updated'])

const tab = ref('details')
const detail = ref<any>(null)
const suggestedArticles = ref<any[]>([])

const replyBody = ref('')
const replyInternal = ref(false)
const sendingReply = ref(false)
const satScore = ref(0)
const satComment = ref('')

const ticketsApiRoot = computed(() => String(props.ticketsApiBase || '/api/v1/support/tickets').replace(/\/$/, ''))
const showSatisfactionTab = computed(() => !ticketsApiRoot.value.includes('/platform/support'))

const tabs = computed(() => {
  const base = [
    { key: 'details', label: 'التفاصيل' },
    { key: 'replies', label: 'الردود والسجل' },
  ] as { key: string; label: string }[]
  if (showSatisfactionTab.value) {
    base.push({ key: 'satisfaction', label: 'التقييم ⭐' })
  }
  return base
})

const statusOptions = [
  { value: 'open',             label: 'مفتوحة',          color: '#3B82F6' },
  { value: 'in_progress',      label: 'قيد المعالجة',    color: '#6366F1' },
  { value: 'pending_customer', label: 'انتظار العميل',   color: '#F59E0B' },
  { value: 'resolved',         label: 'محلولة',          color: '#10B981' },
  { value: 'closed',           label: 'مغلقة',           color: '#6B7280' },
]

async function load() {
  const res = await axios.get(`${ticketsApiRoot.value}/${props.ticket.id}`)
  detail.value = res.data.data
  suggestedArticles.value = res.data.suggested_articles ?? []
}

async function changeStatus(status: string) {
  await axios.patch(`${ticketsApiRoot.value}/${props.ticket.id}/status`, { status })
  await load()
}

async function sendReply() {
  if (!replyBody.value) return
  sendingReply.value = true
  try {
    await axios.post(`${ticketsApiRoot.value}/${props.ticket.id}/replies`, {
      body: replyBody.value, is_internal: replyInternal.value,
    })
    replyBody.value = ''
    await load()
  } finally { sendingReply.value = false }
}

async function submitRating() {
  if (!showSatisfactionTab.value) return
  await axios.post(`${ticketsApiRoot.value}/${props.ticket.id}/rate`, {
    score: satScore.value, comment: satComment.value,
  })
  emit('updated')
}

const sentimentEmoji = computed(() => {
  const s = detail.value?.ai_sentiment_score ?? 0
  return s > 0.3 ? '😊' : s < -0.3 ? '😤' : '😐'
})

const catMap: Record<string, string> = { financial: 'مالية', technical: 'تقنية', vehicle: 'مركبات', general: 'عامة', operational: 'تشغيلية', billing: 'فوترة', complaint: 'شكاوى' }
const priMap: Record<string, string> = { critical: 'حرجة', high: 'عالية', medium: 'متوسطة', low: 'منخفضة' }
const categoryLabel = (c: string) => catMap[c] ?? c
const priorityLabel = (p: string) => priMap[p] ?? p
const eventTypeLabel = (e: string) => ({ status_change: 'تغيير حالة', assignment: 'تعيين', sla_breach: 'خرق SLA', satisfaction: 'تقييم', created: 'إنشاء' }[e] ?? e)
const formatDate = (d: string) => new Date(d).toLocaleDateString('ar-SA', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })

onMounted(load)

watch(
  () => props.ticket.id,
  () => {
    tab.value = 'details'
    void load()
  },
)

watch(showSatisfactionTab, (show) => {
  if (!show && tab.value === 'satisfaction') {
    tab.value = 'details'
  }
})
</script>
