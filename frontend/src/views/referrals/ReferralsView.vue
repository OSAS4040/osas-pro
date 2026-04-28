<template>
  <div class="space-y-6" dir="rtl">
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <GiftIcon class="w-7 h-7 text-pink-500" />
          الإحالات والولاء
        </h1>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">نظام الإحالات ونقاط الولاء للعملاء</p>
      </div>
      <div class="flex gap-2">
        <button :class="activeTab==='referrals' ? 'bg-pink-500 text-white' : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-slate-300 border border-gray-200 dark:border-slate-600'" class="px-4 py-2 rounded-xl text-sm font-semibold transition-colors" @click="activeTab = 'referrals'">الإحالات</button>
        <button :class="activeTab==='loyalty' ? 'bg-primary-500 text-white' : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-slate-300 border border-gray-200 dark:border-slate-600'" class="px-4 py-2 rounded-xl text-sm font-semibold transition-colors" @click="activeTab = 'loyalty'">النقاط</button>
        <button :class="activeTab==='policy' ? 'bg-blue-500 text-white' : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-slate-300 border border-gray-200 dark:border-slate-600'" class="px-4 py-2 rounded-xl text-sm font-semibold transition-colors" @click="activeTab = 'policy'">السياسات</button>
      </div>
    </div>

    <!-- Referrals Tab -->
    <div v-if="activeTab === 'referrals'" class="space-y-4">
      <!-- Stats -->
      <div class="grid grid-cols-3 gap-4">
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 p-5 text-center shadow-sm">
          <p class="text-3xl font-bold text-pink-500">{{ refStats.total ?? 0 }}</p>
          <p class="text-sm text-gray-500 mt-1">إجمالي الإحالات</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 p-5 text-center shadow-sm">
          <p class="text-3xl font-bold text-green-500">{{ refStats.completed ?? 0 }}</p>
          <p class="text-sm text-gray-500 mt-1">مكتملة</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 p-5 text-center shadow-sm">
          <p class="text-3xl font-bold text-orange-500">{{ fmt(refStats.total_rewards ?? 0) }}</p>
          <p class="text-sm text-gray-500 mt-1">مكافآت ممنوحة</p>
        </div>
      </div>

      <!-- Generate link -->
      <div class="bg-gradient-to-l from-pink-50 to-primary-50 dark:from-pink-900/20 dark:to-primary-900/20 rounded-2xl border border-pink-100 dark:border-pink-800/40 p-5">
        <h3 class="font-bold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
          <LinkIcon class="w-5 h-5 text-pink-500" />
          رابط الإحالة الخاص بك
        </h3>
        <div v-if="myCode" class="flex gap-2">
          <input :value="referralUrl" readonly class="flex-1 px-3 py-2.5 bg-white dark:bg-slate-700 border border-pink-200 dark:border-pink-700 rounded-xl text-sm text-gray-700 dark:text-white" />
          <button class="px-4 py-2 bg-pink-500 text-white rounded-xl text-sm font-semibold hover:bg-pink-600 transition-colors flex items-center gap-2" @click="copyLink">
            <ClipboardDocumentIcon class="w-4 h-4" /> نسخ
          </button>
          <button class="px-4 py-2 bg-green-500 text-white rounded-xl text-sm font-semibold hover:bg-green-600 transition-colors" @click="shareWhatsApp">
            واتساب
          </button>
        </div>
        <button v-else :disabled="generating" class="px-4 py-2 bg-pink-500 text-white rounded-xl text-sm font-semibold hover:bg-pink-600 disabled:opacity-50 transition-colors" @click="generateCode">
          {{ generating ? 'جارٍ الإنشاء...' : 'إنشاء رابط إحالة' }}
        </button>
        <p v-if="myCode" class="text-xs text-gray-500 dark:text-slate-400 mt-2">
          الكود: <strong class="text-pink-600 dark:text-pink-400">{{ myCode }}</strong>
          — مكافأة المُحيل: <strong>{{ fmt(policy.referrer_reward) }}</strong>
          — مكافأة المُحال: <strong>{{ fmt(policy.referred_reward) }}</strong>
        </p>
      </div>

      <!-- Referrals List -->
      <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
          <h3 class="font-semibold text-gray-800 dark:text-white">قائمة الإحالات</h3>
        </div>
        <div v-if="loadingRefs" class="flex justify-center py-12"><div class="w-7 h-7 border-4 border-pink-200 border-t-pink-500 rounded-full animate-spin"></div></div>
        <table v-else-if="referrals.length" class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-slate-700/50 text-xs text-gray-500 dark:text-slate-400">
            <tr>
              <th class="px-5 py-3 text-right font-semibold">المُحيل</th>
              <th class="px-4 py-3 text-right font-semibold">الكود</th>
              <th class="px-4 py-3 text-right font-semibold">الحالة</th>
              <th class="px-4 py-3 text-right font-semibold">المكافأة</th>
              <th class="px-4 py-3 text-right font-semibold">التاريخ</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
            <tr v-for="r in referrals" :key="r.id" class="hover:bg-gray-50 dark:hover:bg-slate-700/30">
              <td class="px-5 py-3">{{ r.referrer?.name ?? '—' }}</td>
              <td class="px-4 py-3"><code class="bg-gray-100 dark:bg-slate-700 px-2 py-0.5 rounded text-xs">{{ r.code }}</code></td>
              <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-semibold" :class="statusClass(r.status)">{{ statusLabel(r.status) }}</span></td>
              <td class="px-4 py-3 font-semibold text-pink-600 dark:text-pink-400">{{ fmt(r.reward_amount) }}</td>
              <td class="px-4 py-3 text-gray-400 text-xs">{{ fmtDate(r.created_at) }}</td>
            </tr>
          </tbody>
        </table>
        <div v-else class="py-12 text-center text-gray-400 text-sm">لا توجد إحالات حتى الآن</div>
      </div>
    </div>

    <!-- Loyalty Tab -->
    <div v-else-if="activeTab === 'loyalty'" class="space-y-4">
      <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
          <h3 class="font-semibold text-gray-800 dark:text-white">متصدرو النقاط</h3>
          <TrophyIcon class="w-5 h-5 text-yellow-500" />
        </div>
        <div v-if="loadingLb" class="flex justify-center py-12"><div class="w-7 h-7 border-4 border-primary-200 border-t-primary-500 rounded-full animate-spin"></div></div>
        <div v-else class="divide-y divide-gray-100 dark:divide-slate-700">
          <div v-for="(lp, i) in leaderboard" :key="lp.id" class="flex items-center gap-4 px-5 py-3 hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors">
            <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0"
                 :class="i===0?'bg-yellow-400 text-white':i===1?'bg-gray-300 text-gray-700':i===2?'bg-orange-300 text-white':'bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-slate-300'"
            >
              {{ i + 1 }}
            </div>
            <div class="flex-1">
              <p class="font-medium text-gray-900 dark:text-white">{{ lp.customer?.name }}</p>
              <p class="text-xs text-gray-400">{{ lp.customer?.phone }}</p>
            </div>
            <div class="text-right">
              <p class="font-bold text-primary-600 dark:text-primary-400">{{ lp.points?.toLocaleString('ar-SA') }} نقطة</p>
              <p class="text-xs text-gray-400">مستخدم: {{ lp.points_used?.toLocaleString('ar-SA') }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Policy Tab -->
    <div v-else-if="activeTab === 'policy'" class="space-y-4">
      <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm p-6">
        <h3 class="font-bold text-gray-900 dark:text-white mb-5 flex items-center gap-2">
          <Cog6ToothIcon class="w-5 h-5 text-blue-500" />
          إعدادات الإحالات والمكافآت
        </h3>
        <div class="space-y-5">
          <div class="flex items-center gap-4">
            <label class="flex items-center gap-3 cursor-pointer">
              <div class="relative">
                <input v-model="policyForm.enabled" type="checkbox" class="sr-only" />
                <div class="w-11 h-6 rounded-full transition-colors" :class="policyForm.enabled ? 'bg-green-500' : 'bg-gray-300'"></div>
                <div class="absolute top-0.5 right-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform" :class="policyForm.enabled ? 'translate-x-0' : 'translate-x-5'"></div>
              </div>
              <span class="font-medium text-gray-800 dark:text-white">تفعيل نظام الإحالات</span>
            </label>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">نوع المكافأة</label>
              <select v-model="policyForm.reward_type" class="w-full px-3 py-2.5 border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl text-sm focus:ring-2 focus:ring-blue-400">
                <option value="wallet">رصيد في المحفظة</option>
                <option value="points">نقاط ولاء</option>
                <option value="discount">خصم مباشر</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">نقطة لكل ريال</label>
              <input v-model.number="policyForm.points_per_sar" type="number" min="0" class="w-full px-3 py-2.5 border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl text-sm focus:ring-2 focus:ring-blue-400" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">مكافأة المُحيل (ريال)</label>
              <input v-model.number="policyForm.referrer_reward" type="number" min="0" step="0.01" class="w-full px-3 py-2.5 border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl text-sm focus:ring-2 focus:ring-blue-400" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">مكافأة المُحال (ريال)</label>
              <input v-model.number="policyForm.referred_reward" type="number" min="0" step="0.01" class="w-full px-3 py-2.5 border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl text-sm focus:ring-2 focus:ring-blue-400" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">الحد الأدنى للشراء</label>
              <input v-model.number="policyForm.min_purchase_to_earn" type="number" min="0" step="0.01" class="w-full px-3 py-2.5 border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl text-sm focus:ring-2 focus:ring-blue-400" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">انتهاء النقاط (يوم)</label>
              <input v-model.number="policyForm.points_expiry_days" type="number" min="0" placeholder="0 = بلا انتهاء" class="w-full px-3 py-2.5 border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl text-sm focus:ring-2 focus:ring-blue-400" />
            </div>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1.5">الشروط والأحكام</label>
            <textarea v-model="policyForm.terms" rows="3" class="w-full px-3 py-2.5 border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-xl text-sm focus:ring-2 focus:ring-blue-400" placeholder="تُطبق الشروط والأحكام..." />
          </div>
          <button :disabled="savingPolicy" class="px-6 py-2.5 bg-blue-500 text-white rounded-xl text-sm font-semibold hover:bg-blue-600 disabled:opacity-50 transition-colors" @click="savePolicy">
            {{ savingPolicy ? 'جارٍ الحفظ...' : 'حفظ الإعدادات' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { GiftIcon, LinkIcon, ClipboardDocumentIcon, TrophyIcon, Cog6ToothIcon } from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import { useToast } from '@/composables/useToast'

const toast = useToast()
const activeTab = ref('referrals')
const loadingRefs = ref(false)
const loadingLb = ref(false)
const generating = ref(false)
const savingPolicy = ref(false)
const referrals = ref<any[]>([])
const leaderboard = ref<any[]>([])
const myCode = ref('')
const policy = ref<any>({})
const refStats = ref({ total: 0, completed: 0, total_rewards: 0 })

const policyForm = reactive({
  enabled: true, reward_type: 'wallet',
  referrer_reward: 50, referred_reward: 25,
  points_per_sar: 1, min_purchase_to_earn: 0,
  points_expiry_days: null as number | null, terms: '',
})

const referralUrl = computed(() => `${window.location.origin}/register?ref=${myCode.value}`)
const fmt = (n: any) => new Intl.NumberFormat('ar-SA', { style: 'currency', currency: 'SAR' }).format(+n || 0)
const fmtDate = (d: string) => new Date(d).toLocaleDateString('ar-SA', { day: 'numeric', month: 'short', year: 'numeric' })
function statusLabel(s: string) { return { pending: 'انتظار', completed: 'مكتملة', rewarded: 'مكافأ', expired: 'منتهية' }[s] ?? s }
function statusClass(s: string) {
  return { pending: 'bg-yellow-100 text-yellow-700', completed: 'bg-green-100 text-green-700', rewarded: 'bg-blue-100 text-blue-700', expired: 'bg-gray-100 text-gray-500' }[s] ?? 'bg-gray-100 text-gray-500'
}

async function loadReferrals() {
  loadingRefs.value = true
  try {
    const r = await apiClient.get('/governance/referrals')
    referrals.value = r.data?.data ?? []
    const total = referrals.value.length
    const completed = referrals.value.filter(r => r.status === 'completed' || r.status === 'rewarded').length
    const total_rewards = referrals.value.reduce((s, r) => s + (+r.reward_amount || 0), 0)
    refStats.value = { total, completed, total_rewards }
  } catch { referrals.value = [] }
  finally { loadingRefs.value = false }
}

async function loadLeaderboard() {
  loadingLb.value = true
  try { const r = await apiClient.get('/governance/loyalty/leaderboard'); leaderboard.value = r.data ?? [] }
  catch { leaderboard.value = [] }
  finally { loadingLb.value = false }
}

async function loadPolicy() {
  try {
    const r = await apiClient.get('/governance/referrals/policy')
    policy.value = r.data
    Object.assign(policyForm, r.data)
  } catch { /* silent */ }
}

async function generateCode() {
  generating.value = true
  try {
    const r = await apiClient.post('/governance/referrals/generate', { channel: 'link' })
    myCode.value = r.data.code
    toast.success('تم إنشاء رابط الإحالة')
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'خطأ') }
  finally { generating.value = false }
}

async function savePolicy() {
  savingPolicy.value = true
  try {
    await apiClient.put('/governance/referrals/policy', policyForm)
    toast.success('تم حفظ إعدادات الإحالات')
    await loadPolicy()
  } catch { toast.error('فشل الحفظ') }
  finally { savingPolicy.value = false }
}

function copyLink() { navigator.clipboard.writeText(referralUrl.value); toast.success('تم نسخ الرابط') }
function shareWhatsApp() { window.open(`https://wa.me/?text=${encodeURIComponent('انضم باستخدام رابط الإحالة: ' + referralUrl.value)}`, '_blank') }

watch(activeTab, (t) => { if (t === 'loyalty') loadLeaderboard() })

onMounted(() => { loadReferrals(); loadPolicy() })
</script>
