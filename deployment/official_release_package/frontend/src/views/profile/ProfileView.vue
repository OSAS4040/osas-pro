<template>
  <div class="max-w-2xl space-y-6" dir="rtl">
    <div>
      <h2 class="text-xl font-bold text-gray-900">الملف الشخصي</h2>
      <p class="text-sm text-gray-400 mt-0.5">إدارة بياناتك الشخصية وكلمة المرور</p>
    </div>

    <!-- Avatar Section -->
    <div class="bg-white rounded-xl border border-gray-200 p-6">
      <div class="flex items-center gap-5">
        <div class="relative">
          <div class="w-20 h-20 rounded-full overflow-hidden bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center text-white text-2xl font-bold shadow-md">
            <img v-if="avatarPreview" :src="avatarPreview" class="w-full h-full object-cover" />
            <span v-else>{{ auth.user?.name?.charAt(0)?.toUpperCase() }}</span>
          </div>
          <label class="absolute -bottom-1 -left-1 w-7 h-7 bg-white rounded-full border-2 border-gray-200 flex items-center justify-center cursor-pointer hover:bg-gray-50 shadow-sm">
            <CameraIcon class="w-3.5 h-3.5 text-gray-500" />
            <input type="file" class="hidden" accept="image/*" @change="onAvatarChange" />
          </label>
        </div>
        <div>
          <p class="font-bold text-gray-900 text-lg">{{ auth.user?.name }}</p>
          <p class="text-sm text-gray-500">{{ auth.user?.email }}</p>
          <span class="inline-block mt-1 text-xs px-2 py-0.5 bg-primary-100 text-primary-700 rounded-full font-medium">{{ roleLabel }}</span>
        </div>
      </div>
    </div>

    <!-- Personal Info -->
    <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
      <h3 class="text-sm font-semibold text-gray-700">المعلومات الشخصية</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
          <label class="block text-xs text-gray-500 mb-1">الاسم الكامل</label>
          <input v-model="form.name" class="field" placeholder="اسمك الكامل" />
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">البريد الإلكتروني</label>
          <input v-model="form.email" type="email" class="field" />
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">رقم الجوال</label>
          <input v-model="form.phone" class="field font-mono" placeholder="+966512345678" />
        </div>
      </div>
      <div class="flex items-center gap-3 pt-1">
        <button :disabled="savingProfile" class="px-5 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50 transition-colors" @click="saveProfile">
          {{ savingProfile ? 'جارٍ الحفظ...' : 'حفظ المعلومات' }}
        </button>
        <Transition name="fade"><span v-if="profileSaved" class="text-sm text-green-600 flex items-center gap-1"><CheckCircleIcon class="w-4 h-4" />تم الحفظ</span></Transition>
      </div>
    </div>

    <!-- Change Password -->
    <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
      <h3 class="text-sm font-semibold text-gray-700">تغيير كلمة المرور</h3>
      <div class="space-y-3">
        <div>
          <label class="block text-xs text-gray-500 mb-1">كلمة المرور الحالية</label>
          <div class="relative">
            <input v-model="pwd.current" :type="showPwd.current ? 'text' : 'password'" class="field pr-10" />
            <button class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" @click="showPwd.current = !showPwd.current">
              <EyeIcon v-if="!showPwd.current" class="w-4 h-4" />
              <EyeSlashIcon v-else class="w-4 h-4" />
            </button>
          </div>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">كلمة المرور الجديدة</label>
          <div class="relative">
            <input v-model="pwd.new" :type="showPwd.new ? 'text' : 'password'" class="field pr-10" />
            <button class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" @click="showPwd.new = !showPwd.new">
              <EyeIcon v-if="!showPwd.new" class="w-4 h-4" />
              <EyeSlashIcon v-else class="w-4 h-4" />
            </button>
          </div>
          <!-- Strength indicator -->
          <div v-if="pwd.new" class="mt-2 space-y-1">
            <div class="flex gap-1">
              <div v-for="i in 4" :key="i" class="h-1 flex-1 rounded-full transition-colors"
                   :class="i <= pwdStrength ? strengthColor : 'bg-gray-200'"
              />
            </div>
            <p class="text-xs" :class="strengthColor.replace('bg-','text-')">{{ strengthLabel }}</p>
          </div>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">تأكيد كلمة المرور الجديدة</label>
          <input v-model="pwd.confirm" type="password" class="field" />
          <p v-if="pwd.confirm && pwd.new !== pwd.confirm" class="text-xs text-red-500 mt-1">كلمتا المرور غير متطابقتين</p>
        </div>
      </div>
      <div v-if="pwdError" class="text-sm text-red-600 bg-red-50 rounded-lg p-3">{{ pwdError }}</div>
      <div class="flex items-center gap-3 pt-1">
        <button :disabled="savingPwd || pwd.new !== pwd.confirm || !pwd.current || !pwd.new" class="px-5 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900 disabled:opacity-50 transition-colors"
                @click="changePassword"
        >
          {{ savingPwd ? 'جارٍ التغيير...' : 'تغيير كلمة المرور' }}
        </button>
        <Transition name="fade"><span v-if="pwdSaved" class="text-sm text-green-600 flex items-center gap-1"><CheckCircleIcon class="w-4 h-4" />تم التغيير</span></Transition>
      </div>
    </div>

    <!-- Notification Preferences -->
    <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
      <h3 class="text-sm font-semibold text-gray-700">تفضيلات الإشعارات</h3>
      <div class="space-y-3">
        <label v-for="n in notifications" :key="n.key" class="flex items-center justify-between gap-3 py-2 border-b border-gray-50 last:border-0">
          <div>
            <p class="text-sm text-gray-800">{{ n.label }}</p>
            <p class="text-xs text-gray-400">{{ n.desc }}</p>
          </div>
          <button class="relative w-10 h-5 rounded-full transition-colors" :class="n.enabled ? 'bg-primary-600' : 'bg-gray-200'" @click="n.enabled = !n.enabled">
            <span class="absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform" :class="n.enabled ? 'translate-x-5' : 'translate-x-0.5'" />
          </button>
        </label>
      </div>
    </div>

    <!-- UX Preferences -->
    <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
      <h3 class="text-sm font-semibold text-gray-700">تفضيلات إضافية</h3>
      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs text-gray-500 mb-1">لغة الواجهة المفضلة</label>
          <select v-model="prefs.lang" class="field">
            <option value="ar">العربية</option>
            <option value="en">English</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">الوضع الافتراضي بعد الدخول</label>
          <select v-model="prefs.defaultLanding" class="field">
            <option value="/">الرئيسية</option>
            <option value="/pos">نقطة البيع</option>
            <option value="/reports">التقارير</option>
            <option value="/workshop/tasks">المهام</option>
          </select>
        </div>
      </div>
      <label class="flex items-center justify-between py-2 border-b border-gray-50">
        <span class="text-sm text-gray-700">إظهار المساعد الذكي</span>
        <input v-model="prefs.aiAssistant" type="checkbox" class="rounded" />
      </label>
      <label class="flex items-center justify-between py-2">
        <span class="text-sm text-gray-700">تنبيهات صوتية للعمليات الهامة</span>
        <input v-model="prefs.soundAlerts" type="checkbox" class="rounded" />
      </label>
      <div class="pt-1">
        <button class="px-5 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700" @click="savePrefs">حفظ التفضيلات</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { CameraIcon, EyeIcon, EyeSlashIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'

const auth  = useAuthStore()
const toast = useToast()

const avatarPreview = ref('')
const savingProfile = ref(false)
const profileSaved  = ref(false)
const savingPwd     = ref(false)
const pwdSaved      = ref(false)
const pwdError      = ref('')

const form = reactive({ name: auth.user?.name ?? '', email: auth.user?.email ?? '', phone: '' })
const pwd  = reactive({ current: '', new: '', confirm: '' })
const showPwd = reactive({ current: false, new: false })
const prefs = reactive({
  lang: 'ar',
  defaultLanding: '/',
  aiAssistant: true,
  soundAlerts: false,
})

const roleLabels: Record<string, string> = {
  owner: 'المالك', manager: 'مدير', cashier: 'كاشير',
  technician: 'فني', fleet_contact: 'منسق أسطول',
  fleet_manager: 'مدير أسطول', customer: 'عميل',
}
const roleLabel = computed(() => roleLabels[auth.user?.role as string] ?? auth.user?.role ?? '')

const pwdStrength = computed(() => {
  const p = pwd.new
  let score = 0
  if (p.length >= 8) score++
  if (/[A-Z]/.test(p)) score++
  if (/[0-9]/.test(p)) score++
  if (/[^A-Za-z0-9]/.test(p)) score++
  return score
})

const strengthColor = computed(() => ['bg-red-400','bg-red-400','bg-amber-400','bg-blue-500','bg-green-500'][pwdStrength.value])
const strengthLabel = computed(() => ['','ضعيفة','مقبولة','جيدة','قوية جداً'][pwdStrength.value])

function onAvatarChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (file) avatarPreview.value = URL.createObjectURL(file)
}

function savePrefs() {
  localStorage.setItem('user_prefs_v1', JSON.stringify(prefs))
  toast.success('تم حفظ التفضيلات')
}

const notifications = reactive([
  { key: 'invoice_created', label: 'فاتورة جديدة',      desc: 'عند إنشاء فاتورة جديدة', enabled: true },
  { key: 'wo_assigned',     label: 'أمر عمل مُعيَّن',   desc: 'عند تعيينك لأمر عمل',    enabled: true },
  { key: 'booking_req',     label: 'طلب حجز',           desc: 'عند ورود طلب حجز جديد',   enabled: true },
  { key: 'low_stock',       label: 'مخزون منخفض',       desc: 'عند نقص مخزون المنتجات', enabled: false },
])

async function saveProfile() {
  savingProfile.value = true
  try {
    await apiClient.put(`/users/${auth.user?.id}`, { name: form.name, email: form.email, phone: form.phone })
    if (auth.user) auth.user.name = form.name
    toast.success('تم حفظ المعلومات الشخصية')
    profileSaved.value = true
    setTimeout(() => { profileSaved.value = false }, 3000)
  } catch (e: any) {
    toast.error('فشل الحفظ', e?.response?.data?.message)
  } finally { savingProfile.value = false }
}

async function changePassword() {
  if (pwd.new !== pwd.confirm) return
  savingPwd.value = true
  pwdError.value = ''
  try {
    await apiClient.post('/auth/change-password', { current_password: pwd.current, new_password: pwd.new, new_password_confirmation: pwd.confirm })
    toast.success('تم تغيير كلمة المرور بنجاح')
    pwdSaved.value = true
    pwd.current = pwd.new = pwd.confirm = ''
    setTimeout(() => { pwdSaved.value = false }, 3000)
  } catch (e: any) {
    pwdError.value = e?.response?.data?.message ?? 'فشل تغيير كلمة المرور'
  } finally { savingPwd.value = false }
}

onMounted(async () => {
  try {
    const r = await apiClient.get('/auth/me')
    const u = r.data?.data
    if (u) { form.name = u.name; form.email = u.email; form.phone = u.phone ?? '' }
  } catch { /* silent */ }
  try {
    const raw = localStorage.getItem('user_prefs_v1')
    if (raw) Object.assign(prefs, JSON.parse(raw))
  } catch {
    // ignore
  }
})
</script>

<style scoped>
.field { @apply w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent; }
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
