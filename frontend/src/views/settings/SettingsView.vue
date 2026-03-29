<template>
  <div class="max-w-3xl space-y-6" dir="rtl">

    <div>
      <h2 class="text-xl font-bold text-gray-900 dark:text-slate-100">إعدادات الشركة</h2>
      <p class="text-sm text-gray-400 mt-0.5">المعلومات والهوية البصرية — تظهر في الفواتير والوثائق الرسمية</p>
    </div>

    <!-- ══ Tab Nav ══ -->
    <div class="flex gap-1 bg-gray-100 dark:bg-slate-800 p-1 rounded-xl overflow-x-auto">
      <button v-for="t in tabs" :key="t.id" @click="activeTab = t.id"
        class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors whitespace-nowrap"
        :class="activeTab === t.id
          ? 'bg-white dark:bg-slate-700 text-gray-900 dark:text-slate-100 shadow-sm'
          : 'text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200'">
        <component :is="t.icon" class="w-4 h-4" />
        {{ t.label }}
      </button>
    </div>

    <!-- ══════════════════════════════════════
         TAB 1 — الهوية البصرية
    ══════════════════════════════════════ -->
    <template v-if="activeTab === 'identity'">

      <!-- Logo -->
      <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-slate-200 mb-4 flex items-center gap-2">
          <PhotoIcon class="w-4 h-4 text-primary-600" /> شعار الشركة
        </h3>
        <div class="flex items-center gap-6">
          <div class="w-24 h-24 rounded-xl border-2 border-dashed border-gray-300 dark:border-slate-600 flex items-center justify-center overflow-hidden bg-gray-50 dark:bg-slate-700 flex-shrink-0">
            <img v-if="logoPreview || form.logo_url" :src="logoPreview || form.logo_url" class="w-full h-full object-contain p-1" />
            <BuildingOfficeIcon v-else class="w-10 h-10 text-gray-300 dark:text-slate-500" />
          </div>
          <div class="space-y-2">
            <label class="cursor-pointer inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg text-sm hover:bg-primary-700 transition">
              <ArrowUpTrayIcon class="w-4 h-4" /> رفع الشعار
              <input type="file" accept="image/*" class="hidden" @change="onLogoChange" />
            </label>
            <p class="text-xs text-gray-400">PNG, JPG, WebP, SVG · أقصى 2MB</p>
            <button v-if="logoFile" @click="uploadLogo" :disabled="uploadingLogo"
              class="px-3 py-1.5 bg-green-600 text-white rounded-lg text-xs disabled:opacity-50">
              {{ uploadingLogo ? 'جارٍ الرفع...' : 'حفظ الشعار' }}
            </button>
          </div>
        </div>
      </div>

      <!-- ── Signature & Stamp ── -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <!-- Signature -->
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
          <h3 class="text-sm font-semibold text-gray-700 dark:text-slate-200 mb-1 flex items-center gap-2">
            <PencilSquareIcon class="w-4 h-4 text-indigo-600" /> التوقيع الرسمي
          </h3>
          <p class="text-xs text-gray-400 mb-4">يظهر في أسفل الفاتورة — PNG بخلفية شفافة مُفضَّل</p>

          <!-- Preview -->
          <div class="h-28 rounded-xl border-2 border-dashed flex items-center justify-center mb-3 overflow-hidden relative"
            :class="signaturePreview || form.signature_url ? 'border-indigo-300 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-200 dark:border-slate-600 bg-gray-50 dark:bg-slate-700'">
            <img v-if="signaturePreview || form.signature_url"
              :src="signaturePreview || form.signature_url"
              class="max-h-full max-w-full object-contain p-2" />
            <div v-else class="text-center">
              <PencilSquareIcon class="w-8 h-8 text-gray-200 dark:text-slate-500 mx-auto mb-1" />
              <p class="text-xs text-gray-300 dark:text-slate-500">لا يوجد توقيع</p>
            </div>
            <!-- Delete btn -->
            <button v-if="form.signature_url && !signatureFile" @click="deleteSignature"
              class="absolute top-2 left-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600 transition">
              <XMarkIcon class="w-3.5 h-3.5" />
            </button>
          </div>

          <div class="flex gap-2">
            <label class="flex-1 cursor-pointer flex items-center justify-center gap-2 px-3 py-2 border border-indigo-300 dark:border-indigo-700 text-indigo-600 dark:text-indigo-400 rounded-lg text-sm hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition">
              <ArrowUpTrayIcon class="w-4 h-4" /> رفع
              <input type="file" accept="image/*" class="hidden" @change="e => onImageChange(e,'signature')" />
            </label>
            <button v-if="signatureFile" @click="uploadSignature" :disabled="uploadingSignature"
              class="flex-1 px-3 py-2 bg-indigo-600 text-white rounded-lg text-sm disabled:opacity-50 hover:bg-indigo-700 transition">
              {{ uploadingSignature ? '...' : 'حفظ' }}
            </button>
          </div>
        </div>

        <!-- Stamp -->
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
          <h3 class="text-sm font-semibold text-gray-700 dark:text-slate-200 mb-1 flex items-center gap-2">
            <CheckBadgeIcon class="w-4 h-4 text-green-600" /> ختم الشركة
          </h3>
          <p class="text-xs text-gray-400 mb-4">يظهر فوق التوقيع — PNG بخلفية شفافة مُفضَّل</p>

          <div class="h-28 rounded-xl border-2 border-dashed flex items-center justify-center mb-3 overflow-hidden relative"
            :class="stampPreview || form.stamp_url ? 'border-green-300 bg-green-50 dark:bg-green-900/20' : 'border-gray-200 dark:border-slate-600 bg-gray-50 dark:bg-slate-700'">
            <img v-if="stampPreview || form.stamp_url"
              :src="stampPreview || form.stamp_url"
              class="max-h-full max-w-full object-contain p-2" />
            <div v-else class="text-center">
              <CheckBadgeIcon class="w-8 h-8 text-gray-200 dark:text-slate-500 mx-auto mb-1" />
              <p class="text-xs text-gray-300 dark:text-slate-500">لا يوجد ختم</p>
            </div>
            <button v-if="form.stamp_url && !stampFile" @click="deleteStamp"
              class="absolute top-2 left-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600 transition">
              <XMarkIcon class="w-3.5 h-3.5" />
            </button>
          </div>

          <div class="flex gap-2">
            <label class="flex-1 cursor-pointer flex items-center justify-center gap-2 px-3 py-2 border border-green-300 dark:border-green-700 text-green-600 dark:text-green-400 rounded-lg text-sm hover:bg-green-50 dark:hover:bg-green-900/20 transition">
              <ArrowUpTrayIcon class="w-4 h-4" /> رفع
              <input type="file" accept="image/*" class="hidden" @change="e => onImageChange(e,'stamp')" />
            </label>
            <button v-if="stampFile" @click="uploadStamp" :disabled="uploadingStamp"
              class="flex-1 px-3 py-2 bg-green-600 text-white rounded-lg text-sm disabled:opacity-50 hover:bg-green-700 transition">
              {{ uploadingStamp ? '...' : 'حفظ' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Invoice Preview -->
      <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-slate-200 mb-4 flex items-center gap-2">
          <DocumentTextIcon class="w-4 h-4 text-gray-400" /> معاينة الفاتورة
        </h3>
        <div class="border border-gray-200 dark:border-slate-600 rounded-xl overflow-hidden bg-white dark:bg-slate-900 text-sm shadow-sm">
          <!-- Invoice Header -->
          <div class="flex items-start justify-between p-5 border-b border-gray-100 dark:border-slate-700">
            <div class="flex items-center gap-4">
              <div class="w-16 h-16 rounded-lg border border-gray-200 dark:border-slate-600 bg-gray-50 dark:bg-slate-800 flex items-center justify-center overflow-hidden">
                <img v-if="logoPreview || form.logo_url" :src="logoPreview || form.logo_url" class="w-full h-full object-contain p-1" />
                <BuildingOfficeIcon v-else class="w-8 h-8 text-gray-300" />
              </div>
              <div>
                <p class="font-bold text-gray-900 dark:text-slate-100">{{ form.name_ar || form.name || 'اسم الشركة' }}</p>
                <p class="text-xs text-gray-500 dark:text-slate-400">{{ form.name }}</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ form.address || 'العنوان' }}</p>
              </div>
            </div>
            <div class="text-left text-xs text-gray-500 dark:text-slate-400 space-y-0.5">
              <p v-if="form.tax_number">الرقم الضريبي: <span class="font-mono font-medium text-gray-700 dark:text-slate-200">{{ form.tax_number }}</span></p>
              <p v-if="form.cr_number">السجل التجاري: <span class="font-mono font-medium text-gray-700 dark:text-slate-200">{{ form.cr_number }}</span></p>
              <p v-if="form.phone">{{ form.phone }}</p>
              <p v-if="form.email">{{ form.email }}</p>
            </div>
          </div>
          <!-- Fake invoice body -->
          <div class="px-5 pt-4 pb-2">
            <div class="flex justify-between text-xs text-gray-400 mb-3">
              <span>فاتورة ضريبية رقم: <span class="font-mono text-gray-700 dark:text-slate-200">INV-2025-0001</span></span>
              <span>التاريخ: {{ new Date().toLocaleDateString('ar-SA-u-ca-gregory') }}</span>
            </div>
            <div class="h-20 bg-gray-50 dark:bg-slate-700 rounded-lg flex items-center justify-center text-xs text-gray-300 dark:text-slate-500 mb-4">بنود الفاتورة...</div>
          </div>
          <!-- Signature/Stamp Footer -->
          <div class="flex items-end justify-between px-5 py-4 border-t border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-800/50">
            <div class="text-center">
              <div v-if="stampPreview || form.stamp_url" class="h-16 flex items-center justify-center mb-1">
                <img :src="stampPreview || form.stamp_url" class="max-h-full max-w-[80px] object-contain opacity-80" />
              </div>
              <div v-if="signaturePreview || form.signature_url" class="h-12 flex items-center justify-center border-b border-gray-300 dark:border-slate-500 mb-1">
                <img :src="signaturePreview || form.signature_url" class="max-h-full max-w-[120px] object-contain" />
              </div>
              <p class="text-xs text-gray-400">{{ form.name_ar || 'المفوَّض بالتوقيع' }}</p>
            </div>
            <div class="text-left text-xs text-gray-400">
              <p>شكراً لثقتكم</p>
              <p class="font-mono text-[10px] mt-0.5">{{ form.website || '' }}</p>
            </div>
          </div>
        </div>
      </div>

    </template>

    <!-- ══════════════════════════════════════
         TAB 2 — المعلومات الأساسية
    ══════════════════════════════════════ -->
    <template v-if="activeTab === 'info'">
      <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6 space-y-5">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-slate-200">المعلومات الأساسية</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div><label class="label">اسم الشركة (عربي)</label>
            <input v-model="form.name_ar" class="field" placeholder="شركة النخبة لصيانة السيارات" /></div>
          <div><label class="label">اسم الشركة (إنجليزي)</label>
            <input v-model="form.name" class="field" placeholder="Elite Auto Services Co." /></div>
          <div><label class="label">الرقم الضريبي (VAT)</label>
            <input v-model="form.tax_number" class="field font-mono" placeholder="3000000000" /></div>
          <div><label class="label">السجل التجاري (CR)</label>
            <input v-model="form.cr_number" class="field font-mono" placeholder="1010000000" /></div>
          <div><label class="label">البريد الإلكتروني</label>
            <input v-model="form.email" type="email" class="field" /></div>
          <div><label class="label">رقم الهاتف</label>
            <input v-model="form.phone" class="field font-mono" placeholder="+966512345678" /></div>
          <div><label class="label">الموقع الإلكتروني</label>
            <input v-model="form.website" class="field font-mono" placeholder="https://www.myworkshop.sa" /></div>
          <div><label class="label">المدينة</label>
            <input v-model="form.city" class="field" placeholder="الرياض" /></div>
          <div><label class="label">المنطقة الزمنية</label>
            <select v-model="form.timezone" class="field bg-white dark:bg-slate-700">
              <option value="Asia/Riyadh">Asia/Riyadh (توقيت الرياض)</option>
              <option value="Asia/Dubai">Asia/Dubai</option>
              <option value="UTC">UTC</option>
            </select>
          </div>
          <div><label class="label">اسم البنك</label>
            <input v-model="form.bank_name" class="field" placeholder="البنك الأهلي السعودي" /></div>
          <div class="md:col-span-2"><label class="label">رقم الآيبان (IBAN)</label>
            <input v-model="form.iban" class="field font-mono" placeholder="SA0000000000000000000000" /></div>
          <div class="md:col-span-2"><label class="label">العنوان التفصيلي</label>
            <textarea v-model="form.address" rows="2" class="field resize-none" placeholder="حي السليمانية، طريق الملك فهد، الرياض 12345"></textarea>
          </div>
        </div>
        <div class="flex items-center gap-3 pt-2 border-t border-gray-100 dark:border-slate-700">
          <button @click="save" :disabled="saving"
            class="px-5 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 transition disabled:opacity-50">
            {{ saving ? 'جارٍ الحفظ...' : 'حفظ التغييرات' }}
          </button>
          <Transition name="fade">
            <span v-if="savedMsg" class="text-sm text-green-600 flex items-center gap-1">
              <CheckCircleIcon class="w-4 h-4" /> تم الحفظ
            </span>
          </Transition>
        </div>
      </div>
    </template>

    <!-- ══════════════════════════════════════
         TAB 3 — إعدادات الفاتورة
    ══════════════════════════════════════ -->
    <template v-if="activeTab === 'invoice'">
      <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6 space-y-5">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-slate-200">خيارات الفاتورة</h3>
        <div class="space-y-3">
          <label v-for="opt in invoiceOptions" :key="opt.key"
            class="flex items-center justify-between p-3.5 rounded-xl border border-gray-100 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700/50 cursor-pointer transition-colors">
            <div>
              <p class="text-sm font-medium text-gray-800 dark:text-slate-200">{{ opt.label }}</p>
              <p class="text-xs text-gray-400 mt-0.5">{{ opt.desc }}</p>
            </div>
            <button @click="opt.enabled = !opt.enabled"
              class="relative w-11 h-6 rounded-full transition-colors flex-shrink-0"
              :class="opt.enabled ? 'bg-primary-600' : 'bg-gray-200 dark:bg-slate-600'">
              <span class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform"
                :class="opt.enabled ? 'translate-x-5' : 'translate-x-0.5'" />
            </button>
          </label>
        </div>
        <div>
          <label class="label">ملاحظة تظهر في أسفل كل فاتورة</label>
          <textarea v-model="invoiceFooterNote" rows="2"
            class="field resize-none"
            placeholder="شكراً لتعاملكم معنا — الدفع خلال 30 يوم من تاريخ الفاتورة"></textarea>
        </div>
        <button @click="saveInvoiceSettings" :disabled="savingInvoice"
          class="px-5 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 transition disabled:opacity-50">
          {{ savingInvoice ? 'جارٍ الحفظ...' : 'حفظ' }}
        </button>
      </div>
    </template>

    <!-- Theme Tab -->
    <template v-if="activeTab === 'theme'">
      <div class="space-y-6">
        <div>
          <h3 class="font-semibold text-gray-800 dark:text-white mb-1">تخصيص لون النظام</h3>
          <p class="text-sm text-gray-500 dark:text-slate-400">اختر لوناً يتناسب مع هويتك البصرية</p>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
          <button v-for="preset in THEME_PRESETS" :key="preset.name"
            @click="setTheme(preset.primary)"
            class="flex items-center gap-3 p-3 rounded-xl border-2 transition-all"
            :class="currentTheme === preset.primary
              ? 'border-gray-900 dark:border-white shadow-md'
              : 'border-gray-200 dark:border-slate-700 hover:border-gray-400'"
          >
            <div class="w-8 h-8 rounded-full flex-shrink-0 shadow" :style="{ background: preset.primary }"></div>
            <div class="text-right">
              <div class="text-sm font-medium text-gray-800 dark:text-white">{{ preset.label }}</div>
              <div class="text-xs text-gray-400 font-mono">{{ preset.primary }}</div>
            </div>
            <span v-if="currentTheme === preset.primary" class="mr-auto text-sm">✓</span>
          </button>
        </div>
        <div>
          <h4 class="text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">لون مخصص</h4>
          <div class="flex items-center gap-3">
            <input type="color" :value="currentTheme" @input="e => setTheme((e.target as HTMLInputElement).value)"
              class="w-12 h-12 rounded-xl cursor-pointer border-2 border-gray-200 dark:border-slate-600" />
            <div>
              <p class="text-sm text-gray-700 dark:text-slate-300">{{ currentTheme }}</p>
              <p class="text-xs text-gray-400 mt-0.5">سيُطبَّق فوراً ويُحفَظ للمرة القادمة</p>
            </div>
          </div>
        </div>
        <div class="p-4 rounded-xl border-2 bg-gray-50 dark:bg-slate-700/40"
          :style="{ borderColor: currentTheme, backgroundColor: currentTheme + '10' }">
          <p class="text-sm font-medium" :style="{ color: currentTheme }">معاينة اللون الحالي</p>
          <div class="flex gap-2 mt-2">
            <button class="px-4 py-2 text-white text-sm rounded-lg" :style="{ backgroundColor: currentTheme }">زر رئيسي</button>
            <button class="px-4 py-2 text-sm rounded-lg border-2" :style="{ color: currentTheme, borderColor: currentTheme }">زر ثانوي</button>
          </div>
        </div>
      </div>
    </template>

  </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted } from 'vue'
import {
  BuildingOfficeIcon, ArrowUpTrayIcon, CheckCircleIcon, DocumentTextIcon,
  PhotoIcon, PencilSquareIcon, CheckBadgeIcon, XMarkIcon, Cog6ToothIcon,
} from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useTheme, THEME_PRESETS } from '@/composables/useTheme'

const { currentTheme, setTheme } = useTheme()

const auth  = useAuthStore()
const toast = useToast()

const activeTab = ref('identity')
const tabs = [
  { id: 'identity', label: 'الهوية البصرية', icon: PhotoIcon },
  { id: 'info',     label: 'معلومات الشركة', icon: BuildingOfficeIcon },
  { id: 'invoice',  label: 'إعدادات الفاتورة', icon: DocumentTextIcon },
  { id: 'theme',    label: 'تخصيص الألوان', icon: Cog6ToothIcon },
]

// ── Form ──────────────────────────────────────────────────────────────
const form = reactive({
  name: '', name_ar: '', tax_number: '', cr_number: '',
  email: '', phone: '', address: '', city: '',
  timezone: 'Asia/Riyadh',
  logo_url: '', signature_url: '', stamp_url: '',
  website: '', iban: '', bank_name: '',
})

const saving      = ref(false)
const savedMsg    = ref(false)

// ── Logo ──────────────────────────────────────────────────────────────
const logoFile      = ref<File | null>(null)
const logoPreview   = ref('')
const uploadingLogo = ref(false)

// ── Signature ─────────────────────────────────────────────────────────
const signatureFile      = ref<File | null>(null)
const signaturePreview   = ref('')
const uploadingSignature = ref(false)

// ── Stamp ─────────────────────────────────────────────────────────────
const stampFile      = ref<File | null>(null)
const stampPreview   = ref('')
const uploadingStamp = ref(false)

// ── Invoice Options ───────────────────────────────────────────────────
const invoiceFooterNote = ref('')
const savingInvoice = ref(false)
const invoiceOptions = reactive([
  { key: 'show_signature',    label: 'إظهار التوقيع في الفاتورة',    desc: 'توقيع المفوَّض في أسفل الفاتورة',      enabled: true  },
  { key: 'show_stamp',        label: 'إظهار الختم في الفاتورة',      desc: 'ختم الشركة فوق التوقيع',               enabled: true  },
  { key: 'show_logo',         label: 'إظهار الشعار في الفاتورة',     desc: 'شعار الشركة في رأس الفاتورة',          enabled: true  },
  { key: 'show_bank_details', label: 'إظهار بيانات الحساب البنكي',   desc: 'الآيبان واسم البنك في أسفل الفاتورة',  enabled: false },
  { key: 'show_qr',           label: 'رمز QR في الفاتورة',           desc: 'يتيح للعميل التحقق وعرض الفاتورة',    enabled: true  },
  { key: 'talking_invoice',   label: 'الفاتورة الناطقة',             desc: 'رابط للصور والفيديو والملاحظة الصوتية', enabled: false },
])

// ── Load ──────────────────────────────────────────────────────────────
onMounted(async () => {
  if (!auth.user?.company_id) return
  try {
    const { data } = await apiClient.get(`/companies/${auth.user.company_id}`)
    Object.assign(form, data.data)
  } catch { /* */ }

  try {
    const { data } = await apiClient.get(`/companies/${auth.user.company_id}/settings`)
    const s = data.data
    if (s?.invoice_options) {
      invoiceOptions.forEach(opt => {
        if (s.invoice_options[opt.key] !== undefined) opt.enabled = s.invoice_options[opt.key]
      })
    }
    if (s?.invoice_footer_note) invoiceFooterNote.value = s.invoice_footer_note
  } catch { /* */ }
})

// ── Handlers ──────────────────────────────────────────────────────────
function onLogoChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  logoFile.value   = file
  logoPreview.value = URL.createObjectURL(file)
}

function onImageChange(e: Event, type: 'signature' | 'stamp') {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  if (type === 'signature') {
    signatureFile.value    = file
    signaturePreview.value = URL.createObjectURL(file)
  } else {
    stampFile.value    = file
    stampPreview.value = URL.createObjectURL(file)
  }
}

async function uploadLogo() {
  if (!logoFile.value || !auth.user?.company_id) return
  uploadingLogo.value = true
  try {
    const fd = new FormData()
    fd.append('logo', logoFile.value)
    const { data } = await apiClient.post(`/companies/${auth.user.company_id}/logo`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    form.logo_url  = data.data.logo_url
    logoFile.value = null
    toast.success('تم رفع الشعار بنجاح')
  } catch { toast.error('فشل رفع الشعار') }
  finally { uploadingLogo.value = false }
}

async function uploadSignature() {
  if (!signatureFile.value || !auth.user?.company_id) return
  uploadingSignature.value = true
  try {
    const fd = new FormData()
    fd.append('signature', signatureFile.value)
    const { data } = await apiClient.post(`/companies/${auth.user.company_id}/signature`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    form.signature_url    = data.data.signature_url
    signatureFile.value   = null
    signaturePreview.value = ''
    toast.success('تم رفع التوقيع بنجاح')
  } catch { toast.error('فشل رفع التوقيع') }
  finally { uploadingSignature.value = false }
}

async function deleteSignature() {
  if (!auth.user?.company_id) return
  try {
    await apiClient.delete(`/companies/${auth.user.company_id}/signature`)
    form.signature_url = ''
    toast.success('تم حذف التوقيع')
  } catch { toast.error('فشل الحذف') }
}

async function uploadStamp() {
  if (!stampFile.value || !auth.user?.company_id) return
  uploadingStamp.value = true
  try {
    const fd = new FormData()
    fd.append('stamp', stampFile.value)
    const { data } = await apiClient.post(`/companies/${auth.user.company_id}/stamp`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    form.stamp_url    = data.data.stamp_url
    stampFile.value   = null
    stampPreview.value = ''
    toast.success('تم رفع الختم بنجاح')
  } catch { toast.error('فشل رفع الختم') }
  finally { uploadingStamp.value = false }
}

async function deleteStamp() {
  if (!auth.user?.company_id) return
  try {
    await apiClient.delete(`/companies/${auth.user.company_id}/stamp`)
    form.stamp_url = ''
    toast.success('تم حذف الختم')
  } catch { toast.error('فشل الحذف') }
}

async function save() {
  if (!auth.user?.company_id) return
  saving.value = true
  try {
    await apiClient.put(`/companies/${auth.user.company_id}`, {
      name: form.name, name_ar: form.name_ar, tax_number: form.tax_number,
      cr_number: form.cr_number, email: form.email, phone: form.phone,
      address: form.address, city: form.city, timezone: form.timezone,
      website: form.website, iban: form.iban, bank_name: form.bank_name,
    })
    savedMsg.value = true
    setTimeout(() => { savedMsg.value = false }, 3000)
    toast.success('تم حفظ المعلومات بنجاح')
  } catch { toast.error('فشل الحفظ') }
  finally { saving.value = false }
}

async function saveInvoiceSettings() {
  if (!auth.user?.company_id) return
  savingInvoice.value = true
  try {
    const optMap = invoiceOptions.reduce((a, o) => ({ ...a, [o.key]: o.enabled }), {} as Record<string, boolean>)
    await apiClient.patch(`/companies/${auth.user.company_id}/settings`, {
      invoice_options: optMap,
      invoice_footer_note: invoiceFooterNote.value,
    })
    toast.success('تم حفظ إعدادات الفاتورة')
  } catch { toast.error('فشل الحفظ') }
  finally { savingInvoice.value = false }
}
</script>

<style scoped>
.field  { @apply w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent dark:bg-slate-700 dark:text-slate-100; }
.label  { @apply block text-xs text-gray-500 dark:text-slate-400 mb-1; }
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to       { opacity: 0; }
</style>
