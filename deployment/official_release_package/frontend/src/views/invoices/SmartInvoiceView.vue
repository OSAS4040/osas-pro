<template>
  <div dir="rtl" class="min-h-screen bg-gray-50 dark:bg-slate-950 p-4 sm:p-6">
    <!-- Loading -->
    <div v-if="loading" class="no-print flex justify-center items-center h-60">
      <div class="w-10 h-10 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <div v-else-if="invoice" class="max-w-3xl mx-auto space-y-4">
      <!-- Header Actions -->
      <div class="no-print flex items-center justify-between">
        <button class="flex items-center gap-1.5 text-gray-500 hover:text-gray-700 dark:text-slate-400 text-sm" @click="$router.back()">
          <ChevronRightIcon class="w-4 h-4" />
          رجوع
        </button>
        <div class="flex flex-col items-end gap-1">
          <div class="flex gap-2">
            <button class="btn btn-outline text-xs py-1.5 px-3" @click="printInvoice">
              <PrinterIcon class="w-3.5 h-3.5 mr-1" /> طباعة
            </button>
            <button class="btn bg-green-500 hover:bg-green-600 text-white text-xs py-1.5 px-3 flex items-center gap-1" @click="shareWhatsApp">
              <span class="text-sm">📱</span> واتساب
            </button>
            <button
              type="button"
              class="btn btn-primary text-xs py-1.5 px-3 inline-flex items-center justify-center disabled:opacity-60"
              :disabled="pdfExporting"
              @click="downloadPDF"
            >
              <span
                v-if="pdfExporting"
                class="inline-block w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin mr-1"
                aria-hidden="true"
              />
              <ArrowDownTrayIcon v-else class="w-3.5 h-3.5 mr-1" />
              {{ pdfExporting ? 'جاري التصدير…' : 'PDF' }}
            </button>
          </div>
          <p class="text-[10px] text-gray-500 dark:text-slate-400 max-w-[16rem] text-right leading-snug">
            إذا تعذّر التصدير إلى PDF، استخدم «طباعة» ثم «حفظ كـ PDF» من المتصفح (قد يفشل التقاط الصور أو المحتوى الكبير).
          </p>
        </div>
      </div>

      <!-- Main Invoice Card -->
      <div id="invoice-print-area" class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden">
        <!-- Company Header -->
        <div class="bg-gradient-to-r from-blue-700 to-blue-900 px-6 py-5 text-white">
          <div class="flex justify-between items-start">
            <div>
              <h1 class="text-xl font-bold">{{ invoice.company?.name ?? 'فاتورة ضريبية' }}</h1>
              <p class="text-blue-200 text-sm mt-0.5">{{ invoice.company?.vat_number ? 'رقم ضريبي: ' + invoice.company.vat_number : '' }}</p>
            </div>
            <div class="text-left">
              <p class="text-2xl font-mono font-bold">#{{ invoice.invoice_number }}</p>
              <p class="text-blue-200 text-xs mt-0.5">{{ formatDate(invoice.issued_at) }}</p>
            </div>
          </div>
        </div>

        <!-- Status Bar -->
        <div class="flex items-center justify-between px-6 py-2.5 bg-gray-50 dark:bg-slate-900 border-b border-gray-100 dark:border-slate-700">
          <span :class="statusClass(invoice.status)" class="px-3 py-1 rounded-full text-xs font-bold">
            {{ statusLabel(invoice.status) }}
          </span>
          <span class="text-sm text-gray-500 dark:text-slate-400">{{ formatDate(invoice.issued_at) }}</span>
        </div>

        <!-- Customer + Vehicle Info -->
        <div class="grid grid-cols-2 gap-4 px-6 py-4 border-b border-gray-100 dark:border-slate-700">
          <div>
            <p class="text-xs text-gray-400 mb-1">العميل</p>
            <p class="font-semibold text-gray-900 dark:text-white text-sm">{{ invoice.customer?.name ?? '—' }}</p>
            <p class="text-xs text-gray-500 dark:text-slate-400">{{ invoice.customer?.phone ?? '' }}</p>
          </div>
          <div v-if="invoice.vehicle">
            <p class="text-xs text-gray-400 mb-1">المركبة</p>
            <p class="font-semibold text-gray-900 dark:text-white text-sm font-mono">{{ invoice.vehicle.plate_number }}</p>
            <p class="text-xs text-gray-500 dark:text-slate-400">{{ invoice.vehicle.make }} {{ invoice.vehicle.model }}</p>
          </div>
        </div>

        <!-- Items Table -->
        <div class="px-6 py-4">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-gray-400 dark:text-slate-500 text-xs border-b border-gray-100 dark:border-slate-700">
                <th class="text-right pb-2 font-medium">البند</th>
                <th class="text-center pb-2 font-medium w-16">الكمية</th>
                <th class="text-center pb-2 font-medium w-24">السعر</th>
                <th class="text-center pb-2 font-medium w-20">الضريبة</th>
                <th class="text-left pb-2 font-medium w-24">الإجمالي</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-slate-700">
              <tr v-for="item in invoice.items" :key="item.id" class="py-2">
                <td class="py-2.5 text-gray-800 dark:text-slate-200">{{ item.name }}</td>
                <td class="text-center text-gray-600 dark:text-slate-400">{{ item.quantity }}</td>
                <td class="text-center text-gray-600 dark:text-slate-400">{{ fmt(item.unit_price) }}</td>
                <td class="text-center text-gray-500 dark:text-slate-500 text-xs">%{{ item.vat_rate ?? 15 }}</td>
                <td class="text-left font-medium text-gray-800 dark:text-white">{{ fmt(item.total) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Totals -->
        <div class="px-6 pb-4 border-t border-gray-100 dark:border-slate-700">
          <div class="max-w-xs mr-auto space-y-1.5 mt-3">
            <div class="flex justify-between text-sm text-gray-600 dark:text-slate-400">
              <span>المجموع قبل الضريبة</span>
              <span class="font-mono">{{ fmt(invoice.subtotal) }}</span>
            </div>
            <div v-if="invoice.discount_amount > 0" class="flex justify-between text-sm text-green-600">
              <span>الخصم</span>
              <span class="font-mono">- {{ fmt(invoice.discount_amount) }}</span>
            </div>
            <div class="flex justify-between text-sm text-gray-600 dark:text-slate-400">
              <span>ضريبة القيمة المضافة (15%)</span>
              <span class="font-mono">{{ fmt(invoice.tax_amount) }}</span>
            </div>
            <div class="flex justify-between font-bold text-lg text-gray-900 dark:text-white border-t border-gray-200 dark:border-slate-600 pt-2 mt-2">
              <span>الإجمالي النهائي</span>
              <span class="font-mono text-blue-700 dark:text-blue-400">{{ fmt(invoice.total) }} ر.س</span>
            </div>
          </div>
        </div>

        <!-- Visual Price Breakdown (Pie Chart) -->
        <div class="px-6 py-4 bg-blue-50 dark:bg-slate-900 border-t border-blue-100 dark:border-slate-700">
          <p class="text-xs font-bold text-gray-600 dark:text-slate-300 mb-3">أين ذهبت أموالك؟</p>
          <div class="flex items-center gap-4">
            <svg viewBox="0 0 36 36" class="w-16 h-16 flex-shrink-0">
              <path :d="pieSlice(0, partsPercent)" fill="#3b82f6" />
              <path :d="pieSlice(partsPercent, laborPercent)" fill="#10b981" />
              <path :d="pieSlice(partsPercent + laborPercent, vatPercent)" fill="#f59e0b" />
              <circle cx="18" cy="18" r="8" fill="white" class="dark:fill-slate-900" />
            </svg>
            <div class="space-y-1.5 text-xs">
              <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-blue-500 flex-shrink-0"></span><span class="text-gray-700 dark:text-slate-300">قطع الغيار: <b>{{ partsPercent }}%</b></span></div>
              <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500 flex-shrink-0"></span><span class="text-gray-700 dark:text-slate-300">العمالة: <b>{{ laborPercent }}%</b></span></div>
              <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-amber-500 flex-shrink-0"></span><span class="text-gray-700 dark:text-slate-300">الضريبة: <b>{{ vatPercent }}%</b></span></div>
            </div>
          </div>
        </div>

        <!-- Warranty Tracker -->
        <div v-if="warrantyItems.length" class="px-6 py-4 border-t border-gray-100 dark:border-slate-700">
          <p class="text-xs font-bold text-gray-600 dark:text-slate-300 mb-3 flex items-center gap-1.5">
            <ShieldCheckIcon class="w-4 h-4 text-emerald-500" /> ضمان القطع المستبدلة
          </p>
          <div class="space-y-2">
            <div v-for="w in warrantyItems" :key="w.id" class="flex items-center justify-between bg-gray-50 dark:bg-slate-900 rounded-xl px-3 py-2">
              <div>
                <p class="text-xs font-medium text-gray-800 dark:text-white">{{ w.part_name }}</p>
                <p class="text-xs text-gray-400">ينتهي {{ formatDate(w.warranty_end) }}</p>
              </div>
              <span :class="warrantyBadge(w.status)" class="text-xs px-2 py-0.5 rounded-full font-medium">
                {{ w.days_remaining }} يوم
              </span>
            </div>
          </div>
        </div>

        <!-- Dual QR Section -->
        <div class="grid grid-cols-2 gap-4 px-6 py-4 bg-gray-50 dark:bg-slate-900 border-t border-gray-100 dark:border-slate-700">
          <div class="text-center">
            <div ref="zatcaQrRef" class="bg-white p-2 rounded-xl inline-block shadow-sm"></div>
            <p class="text-xs text-gray-500 dark:text-slate-400 mt-1.5">QR ضريبي (ZATCA)</p>
          </div>
          <div class="text-center">
            <div class="bg-white p-2 rounded-xl inline-block shadow-sm">
              <div ref="experienceQrRef"></div>
            </div>
            <p class="text-xs text-gray-500 dark:text-slate-400 mt-1.5">تجربتك + صور العمل</p>
          </div>
        </div>

        <!-- Next Service CTA -->
        <div v-if="nextService" class="mx-6 mb-4 mt-2 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-2xl p-4 text-white">
          <div class="flex items-center justify-between">
            <div>
              <p class="font-bold text-sm">موعدك القادم 📅</p>
              <p class="text-blue-100 text-xs mt-0.5">{{ formatDate(nextService.next_service_date) }}</p>
              <p v-if="nextService.discount_code" class="mt-2 text-xs">
                كود الخصم: <span class="font-mono bg-white/20 px-2 py-0.5 rounded-lg">{{ nextService.discount_code }}</span>
                <span class="text-blue-200 mr-1">({{ nextService.discount_value }}{{ nextService.discount_type === 'percentage' ? '%' : ' ر.س' }})</span>
              </p>
            </div>
            <CalendarDaysIcon class="w-10 h-10 text-white/30 flex-shrink-0" />
          </div>
        </div>
      </div>

      <!-- NPS Rating Card -->
      <div v-if="!npsSubmitted" class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 p-5 text-center shadow-sm">
        <p class="font-bold text-gray-900 dark:text-white mb-1">كيف كانت تجربتك؟</p>
        <p class="text-xs text-gray-400 mb-4">تقييمك يساعدنا على التطوير</p>
        <div class="flex justify-center gap-3">
          <button v-for="n in 5" :key="n" :class="npsHover >= n || npsScore >= n ? 'text-yellow-400 scale-110' : 'text-gray-200 dark:text-slate-600'"
                  class="text-3xl transition-transform"
                  @click="submitNPS(n)" @mouseenter="npsHover = n" @mouseleave="npsHover = 0"
          >
            ★
          </button>
        </div>
        <p class="text-xs text-gray-400 mt-2">{{ npsScore ? npsLabels[npsScore - 1] : '' }}</p>
      </div>
      <div v-else class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-2xl p-4 text-center">
        <p class="text-green-700 dark:text-green-400 font-medium text-sm">✅ شكراً على تقييمك!</p>
      </div>

      <!-- Green Invoice Toggle -->
      <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-2xl p-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="text-2xl">🌿</span>
            <div>
              <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-300">فاتورة خضراء</p>
              <p class="text-xs text-emerald-600 dark:text-emerald-400">استلم عبر واتساب واكسب 10 نقاط ولاء</p>
            </div>
          </div>
          <button class="text-xs bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-xl transition-colors"
                  @click="shareWhatsApp"
          >
            إرسال
          </button>
        </div>
      </div>

      <Teleport to="body">
        <section
          id="smart-invoice-print-template"
          class="invoice-print-only print-container invoice-formal-print"
          dir="rtl"
        >
          <div class="formal-sheet" :style="{ '--inv-accent': printSettings.primaryColor || '#5b21b6' }">
            <header class="formal-issuer-bar">
              <div class="formal-issuer-grid">
                <div class="formal-issuer-en" dir="ltr">
                  <div class="formal-doc-type">Tax Invoice</div>
                  <p class="formal-co-name">{{ printCompanyNameEn }}</p>
                  <div class="formal-issuer-lines">
                    <p v-if="invoice?.company?.address">{{ invoice.company.address }}</p>
                    <p v-if="invoice?.company?.vat_number || invoice?.company?.tax_number">
                      VAT: {{ invoice?.company?.tax_number || invoice?.company?.vat_number }}
                    </p>
                    <p v-if="invoice?.company?.cr_number">CR: {{ invoice.company.cr_number }}</p>
                    <p v-if="invoice?.company?.phone">{{ invoice.company.phone }}</p>
                  </div>
                </div>
                <div class="formal-issuer-logo-mid">
                  <div v-if="printSettings.show_logo" class="formal-logo-on-brand">
                    <img v-if="invoice?.company?.logo_url" :src="invoice.company.logo_url" alt="" />
                    <div v-else class="formal-logo-fallback" aria-hidden="true">
                      {{ printLogoMonogram }}
                    </div>
                  </div>
                </div>
                <div class="formal-issuer-ar" dir="rtl">
                  <div class="formal-doc-type">فاتورة ضريبية</div>
                  <p class="formal-co-name">{{ printCompanyNameAr }}</p>
                  <div class="formal-issuer-lines">
                    <p v-if="invoice?.company?.address">{{ invoice.company.address }}</p>
                    <p v-if="invoice?.company?.vat_number || invoice?.company?.tax_number">
                      الرقم الضريبي: {{ invoice?.company?.tax_number || invoice?.company?.vat_number }}
                    </p>
                    <p v-if="invoice?.company?.cr_number">السجل التجاري: {{ invoice.company.cr_number }}</p>
                    <p v-if="invoice?.company?.phone">{{ invoice.company.phone }}</p>
                  </div>
                </div>
              </div>
            </header>

            <p class="formal-invoice-no">{{ invoice?.invoice_number || '—' }}</p>
            <p v-if="printSettings.headerNote" class="formal-header-note">{{ printSettings.headerNote }}</p>

            <div class="formal-section">
              <div class="formal-section-head">
                <span dir="ltr">Invoice details</span>
                <span>تفاصيل الفاتورة</span>
              </div>
              <table class="formal-kv">
                <tbody>
                  <tr>
                    <th>رقم الفاتورة <span class="k-en">Invoice #</span></th>
                    <td class="font-mono">{{ invoice?.invoice_number || '—' }}</td>
                  </tr>
                  <tr>
                    <th>تاريخ الإصدار <span class="k-en">Issue date</span></th>
                    <td>{{ formatInvoiceDateOnly(invoice?.issued_at || '') }}</td>
                  </tr>
                  <tr>
                    <th>وقت الإصدار <span class="k-en">Issue time</span></th>
                    <td dir="ltr" style="text-align: right">{{ formatInvoiceTimeOnly(invoice?.issued_at || '') }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="formal-section">
              <div class="formal-section-head">
                <span dir="ltr">Customer</span>
                <span>معلومات العميل</span>
              </div>
              <table class="formal-kv">
                <tbody>
                  <tr>
                    <th>اسم العميل <span class="k-en">Customer name</span></th>
                    <td>{{ invoice?.customer?.name || '—' }}</td>
                  </tr>
                  <tr>
                    <th>الهاتف <span class="k-en">Phone</span></th>
                    <td>{{ invoice?.customer?.phone || '—' }}</td>
                  </tr>
                  <tr v-if="invoice?.vehicle?.plate_number">
                    <th>رقم اللوحة <span class="k-en">Vehicle plate</span></th>
                    <td class="font-mono">{{ invoice.vehicle.plate_number }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="formal-lines-wrap">
              <table class="formal-lines">
                <thead>
                  <tr>
                    <th style="width: 3%">#</th>
                    <th style="width: 40%">وصف الخدمة / Description</th>
                    <th style="width: 9%">كمية<br />Qty</th>
                    <th style="width: 11%">سعر<br />Price</th>
                    <th style="width: 13%">ضريبة<br />VAT</th>
                    <th style="width: 13%">إجمالي<br />Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, idx) in (invoice?.items || [])" :key="item.id || idx">
                    <td class="col-num">{{ idx + 1 }}</td>
                    <td class="col-desc">{{ item.name }}</td>
                    <td class="col-num">{{ Number(item.quantity || 0).toFixed(0) }}</td>
                    <td class="col-num">{{ Number(item.unit_price || 0).toFixed(2) }}</td>
                    <td class="col-num">{{ Number(item.tax_amount || 0).toFixed(2) }}</td>
                    <td class="col-num">{{ Number(item.total ?? item.line_total ?? 0).toFixed(2) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="formal-totals-wrap">
              <div class="formal-qr">
                <img v-if="zatcaQRUrl" :src="zatcaQRUrl" alt="QR" />
              </div>
              <table class="formal-totals">
                <tr>
                  <th>المجموع الفرعي <span dir="ltr">Subtotal</span></th>
                  <td>{{ Number(invoice?.subtotal || 0).toFixed(2) }} ر.س</td>
                </tr>
                <tr v-if="Number(invoice?.discount_amount) > 0">
                  <th>الخصم <span dir="ltr">Discount</span></th>
                  <td>-{{ Number(invoice?.discount_amount || 0).toFixed(2) }} ر.س</td>
                </tr>
                <tr>
                  <th>ضريبة القيمة المضافة <span dir="ltr">VAT</span></th>
                  <td>{{ Number(invoice?.tax_amount || 0).toFixed(2) }} ر.س</td>
                </tr>
                <tr class="grand">
                  <th>الإجمالي شامل الضريبة <span dir="ltr">Total inc. VAT</span></th>
                  <td>{{ Number(invoice?.total || 0).toFixed(2) }} ر.س</td>
                </tr>
              </table>
            </div>

            <p class="formal-thanks">شكراً لكم · Thank you</p>
            <p class="formal-brand-line">صُدرت عبر نظام <strong>أسس برو</strong></p>
            <div class="formal-footer-row">
              <span>{{ printCompanyNameAr }}</span>
              <span class="font-mono" dir="ltr">{{ invoice?.invoice_number || '' }}</span>
            </div>
            <p v-if="printSettings.footerNote" class="formal-footer-note">{{ printSettings.footerNote }}</p>
          </div>
        </section>
      </Teleport>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import {
  ChevronRightIcon, PrinterIcon, ArrowDownTrayIcon,
  ShieldCheckIcon, CalendarDaysIcon
} from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import { useToast } from '@/composables/useToast'
import { PDF_EXPORT_FAIL_AR } from '@/constants/pdfExportMessages'
import { printDocument, ensurePrintFontsReady } from '@/composables/useAppPrint'
import { getZatcaQRUrl } from '@/utils/zatca'
import { addInvoiceCanvasToSinglePagePdf } from '@/utils/invoicePdfExport'
import {
  invoicePrintCompanyDisplayName,
  invoicePrintLogoMonogram,
} from '@/utils/invoicePrintDisplay'

const toast = useToast()
const route   = useRoute()
const loading = ref(true)
const pdfExporting = ref(false)
const invoice     = ref<any>(null)
const warrantyItems = ref<any[]>([])
const nextService   = ref<any>(null)
const zatcaQrRef     = ref<HTMLElement | null>(null)
const experienceQrRef= ref<HTMLElement | null>(null)
const npsScore    = ref(0)
const npsHover    = ref(0)
const npsSubmitted= ref(false)
const npsLabels   = ['سيء جداً', 'سيء', 'مقبول', 'جيد', 'ممتاز']
const printSettings = ref({
  show_logo: true,
  primaryColor: '#1e3a8a',
  headerNote: '',
  footerNote: '',
})

const printCompany = computed(() => invoice.value?.company ?? null)
const printCompanyNameEn = computed(() => invoicePrintCompanyDisplayName(printCompany.value, 'en'))
const printCompanyNameAr = computed(() => invoicePrintCompanyDisplayName(printCompany.value, 'ar'))
const printLogoMonogram = computed(() => invoicePrintLogoMonogram(printCompany.value))

const fmt = (v: number) => Number(v || 0).toLocaleString('ar-SA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const formatDate = (d: string) => d ? new Date(d).toLocaleDateString('ar-SA') : '—'

function formatInvoiceDateOnly(iso: string): string {
  if (!iso) return '—'
  return iso.slice(0, 10)
}

function formatInvoiceTimeOnly(iso: string): string {
  if (!iso) return '—'
  try {
    return new Date(iso).toLocaleTimeString('en-GB', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: false,
    })
  } catch {
    return '—'
  }
}

const zatcaQRUrl = computed(() => {
  const inv = invoice.value
  if (!inv?.company) return ''
  return getZatcaQRUrl({
    sellerName: invoicePrintCompanyDisplayName(inv.company, 'ar') || '—',
    vatNumber: String(inv.company?.tax_number ?? inv.company?.vat_number ?? '000000000000000'),
    invoiceDate: inv.created_at ?? inv.issued_at ?? new Date().toISOString(),
    totalWithVat: parseFloat(String(inv.total ?? '0')),
    vatAmount: parseFloat(String(inv.tax_amount ?? '0')),
  })
})

const statusLabel = (s: string) => ({'paid':'مدفوعة','partial':'مدفوعة جزئياً','unpaid':'غير مدفوعة','cancelled':'ملغاة','draft':'مسودة'}[s] ?? s)
const statusClass = (s: string) => ({'paid':'bg-green-100 text-green-700','partial':'bg-amber-100 text-amber-700','unpaid':'bg-red-100 text-red-700','cancelled':'bg-gray-100 text-gray-500','draft':'bg-blue-100 text-blue-700'}[s] ?? 'bg-gray-100 text-gray-500')
const warrantyBadge = (s: string) => ({'active':'bg-green-100 text-green-700','warning':'bg-amber-100 text-amber-700','expiring_soon':'bg-orange-100 text-orange-700','expired':'bg-red-100 text-red-600'}[s] ?? 'bg-gray-100')

const partsPercent = computed(() => {
  if (!invoice.value) return 33
  const subtotal = invoice.value.subtotal || 1
  const parts = (invoice.value.items || []).filter((i: any) => i.item_type !== 'service').reduce((s: number, i: any) => s + Number(i.subtotal), 0)
  return Math.round((parts / subtotal) * 100) || 40
})
const laborPercent = computed(() => Math.max(5, 100 - partsPercent.value - vatPercent.value))
const vatPercent   = computed(() => {
  if (!invoice.value) return 15
  return Math.round(((invoice.value.tax_amount || 0) / (invoice.value.total || 1)) * 100)
})

function pieSlice(startPct: number, pct: number) {
  const cx = 18, cy = 18, r = 16
  const startAngle = (startPct / 100) * 2 * Math.PI - Math.PI / 2
  const endAngle   = ((startPct + pct) / 100) * 2 * Math.PI - Math.PI / 2
  const x1 = cx + r * Math.cos(startAngle), y1 = cy + r * Math.sin(startAngle)
  const x2 = cx + r * Math.cos(endAngle),   y2 = cy + r * Math.sin(endAngle)
  const large = pct > 50 ? 1 : 0
  return `M${cx},${cy} L${x1},${y1} A${r},${r} 0 ${large},1 ${x2},${y2} Z`
}

async function generateQRs() {
  await nextTick()
  try {
    const QRCode = (await import('qrcode')).default
    const inv = invoice.value
    // ZATCA QR data (simplified TLV)
    const zatcaData = btoa(unescape(encodeURIComponent(
      `${inv.company?.name ?? ''}\n${inv.company?.vat_number ?? ''}\n${inv.issued_at}\n${inv.total}\n${inv.tax_amount}`
    )))
    if (zatcaQrRef.value) {
      const canvas = document.createElement('canvas')
      await QRCode.toCanvas(canvas, zatcaData, { width: 100, margin: 1 })
      zatcaQrRef.value.innerHTML = ''
      zatcaQrRef.value.appendChild(canvas)
    }
    const expUrl = `${window.location.origin}/invoice-review/${inv.uuid}`
    if (experienceQrRef.value) {
      const canvas2 = document.createElement('canvas')
      await QRCode.toCanvas(canvas2, expUrl, { width: 100, margin: 1 })
      experienceQrRef.value.innerHTML = ''
      experienceQrRef.value.appendChild(canvas2)
    }
  } catch (e) { /* qrcode not installed - silently skip */ }
}

async function submitNPS(score: number) {
  npsScore.value = score
  try {
    await apiClient.post('/nps', { score, invoice_id: invoice.value?.id, channel: 'invoice' })
    npsSubmitted.value = true
  } catch { npsSubmitted.value = true }
}

function shareWhatsApp() {
  const msg = `فاتورة رقم ${invoice.value?.invoice_number} - الإجمالي: ${fmt(invoice.value?.total)} ر.س\nرابط العرض: ${window.location.href}`
  const phone = invoice.value?.customer?.phone?.replace(/\D/g,'') ?? ''
  window.open(`https://wa.me/${phone}?text=${encodeURIComponent(msg)}`, '_blank')
}

async function printInvoice() {
  const root = document.getElementById('smart-invoice-print-template')
  if (!root) {
    toast.error('تعذّر الطباعة', 'لم يُعثر على قالب الفاتورة.')
    return
  }
  await printDocument({ root })
}

async function downloadPDF() {
  if (pdfExporting.value) return
  pdfExporting.value = true
  let captureNode: HTMLElement | null = null
  try {
    const target = document.getElementById('smart-invoice-print-template')
    if (!target) throw new Error('print template not found')

    const [{ default: html2canvas }, { jsPDF }] = await Promise.all([
      import('html2canvas'),
      import('jspdf'),
    ])

    captureNode = target.cloneNode(true) as HTMLElement
    captureNode.classList.remove('invoice-print-only')
    captureNode.style.display = 'block'
    captureNode.style.position = 'fixed'
    captureNode.style.left = '-10000px'
    captureNode.style.top = '0'
    captureNode.style.zIndex = '-1'
    document.body.appendChild(captureNode)

    await ensurePrintFontsReady()

    const canvas = await html2canvas(captureNode, {
      scale: 2,
      useCORS: true,
      allowTaint: false,
      backgroundColor: '#ffffff',
      imageTimeout: 20000,
      logging: false,
    })
    if (captureNode.parentNode) captureNode.parentNode.removeChild(captureNode)
    captureNode = null

    const imgData = canvas.toDataURL('image/png')
    if (!imgData || imgData.length < 100) {
      throw new Error('empty canvas')
    }
    const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
    addInvoiceCanvasToSinglePagePdf(pdf, imgData, canvas)
    pdf.save(`invoice-${invoice.value?.invoice_number || 'invoice'}.pdf`)
    toast.success('تم التصدير', 'تم تنزيل ملف PDF.')
  } catch (e: unknown) {
    console.warn('[SmartInvoice PDF]', e)
    toast.error('تصدير PDF', PDF_EXPORT_FAIL_AR)
  } finally {
    if (captureNode && captureNode.parentNode) {
      captureNode.parentNode.removeChild(captureNode)
    }
    pdfExporting.value = false
  }
}

async function load() {
  loading.value = true
  try {
    const id = route.params.id
    const { data } = await apiClient.get(`/invoices/${id}`)
    invoice.value = data.data ?? data
    try {
      const companyId = invoice.value?.company_id ?? invoice.value?.company?.id
      if (companyId) {
        const sRes = await apiClient.get(`/companies/${companyId}/settings`)
        const opts = sRes.data?.data?.invoice_options || {}
        printSettings.value.show_logo = opts.show_logo ?? true
        printSettings.value.primaryColor = typeof opts.print_primary_color === 'string' ? opts.print_primary_color : '#1e3a8a'
        printSettings.value.headerNote = typeof opts.print_header_note === 'string' ? opts.print_header_note : ''
        printSettings.value.footerNote = sRes.data?.data?.invoice_footer_note || ''
      }
    } catch {}
    // Fetch warranty items
    try {
      const wr = await apiClient.get(`/warranty-items?invoice_id=${id}`)
      warrantyItems.value = wr.data.data?.data ?? []
    } catch {}
    // Fetch next service
    try {
      if (invoice.value?.customer?.id) {
        const sr = await apiClient.get(`/service-reminders?customer_id=${invoice.value.customer.id}`)
        const items = sr.data.data?.data ?? []
        nextService.value = items.find((x: any) => !x.notified) ?? null
      }
    } catch {}
    await generateQRs()
  } catch { /* error handled */ } finally { loading.value = false }
}

onMounted(load)
</script>

<style scoped>
.invoice-print-only {
  display: none;
}
</style>
