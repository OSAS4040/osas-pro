<template>
  <div class="max-w-4xl space-y-5 pb-8" dir="rtl">
    <div class="flex items-center gap-3">
      <RouterLink to="/invoices" class="text-gray-400 hover:text-gray-600 text-sm">← الفواتير</RouterLink>
      <h2 class="text-lg font-semibold text-gray-900">فاتورة جديدة</h2>
    </div>

    <form ref="invoiceFormRef" class="space-y-5" @submit.prevent="submit">
      <!-- العميل والمعلومات -->
      <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
        <h3 class="text-sm font-semibold text-gray-700">بيانات الفاتورة</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div class="sm:col-span-2 flex flex-col gap-1">
            <label class="block text-xs text-gray-500">العميل <span class="text-red-500">*</span></label>
            <div class="flex gap-2 items-stretch">
              <select
                v-model="form.customer_id"
                required
                class="flex-1 min-w-0 px-3 py-2 border border-gray-300 rounded-lg text-sm"
              >
                <option value="">اختر عميلاً</option>
                <option v-for="c in customers" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
              </select>
              <button
                type="button"
                class="shrink-0 whitespace-nowrap px-3 py-2 text-xs font-semibold rounded-lg border border-primary-300 bg-primary-50 text-primary-800 hover:bg-primary-100 dark:border-primary-700 dark:bg-primary-950/40 dark:text-primary-200"
                @click="openQuickCustomer"
              >
                + عميل سريع
              </button>
            </div>
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">تاريخ الإصدار</label>
            <SmartDatePicker :model-value="form.issued_at" mode="single" @change="onIssuedDateChange" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">تاريخ الاستحقاق</label>
            <SmartDatePicker :model-value="form.due_at" mode="single" @change="onDueDateChange" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">العملة</label>
            <select v-model="form.currency" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
              <option value="SAR">ريال سعودي (SAR)</option>
              <option value="USD">دولار أمريكي (USD)</option>
              <option value="AED">درهم إماراتي (AED)</option>
            </select>
          </div>
          <div class="sm:col-span-3 flex flex-col gap-1">
            <label class="block text-xs text-gray-500">المركبة <span class="text-gray-400 font-normal">(اختياري)</span></label>
            <div class="flex gap-2 items-stretch flex-wrap">
              <select
                v-model="form.vehicle_id"
                class="flex-1 min-w-[12rem] px-3 py-2 border border-gray-300 rounded-lg text-sm disabled:bg-gray-100 disabled:text-gray-400"
                :disabled="!form.customer_id"
              >
                <option value="">بدون مركبة</option>
                <option v-for="v in vehicles" :key="v.id" :value="String(v.id)">{{ vehicleLabel(v) }}</option>
              </select>
              <button
                type="button"
                class="shrink-0 whitespace-nowrap px-3 py-2 text-xs font-semibold rounded-lg border border-teal-300 bg-teal-50 text-teal-900 hover:bg-teal-100 disabled:opacity-45 disabled:cursor-not-allowed"
                :disabled="!form.customer_id"
                @click="openQuickVehicle"
              >
                + مركبة سريعة
              </button>
            </div>
            <p v-if="!form.customer_id" class="text-[11px] text-gray-400">
              اختر العميل أولاً لعرض مركباته أو إضافة مركبة جديدة مرتبطة به.
            </p>
          </div>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">ملاحظات</label>
          <textarea v-model="form.notes" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="ملاحظات للعميل..."></textarea>
        </div>
      </div>

      <!-- البنود -->
      <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
        <div class="flex items-center justify-between gap-2 flex-wrap">
          <h3 class="text-sm font-semibold text-gray-700">البنود والخدمات</h3>
          <div class="flex items-center gap-2 flex-wrap">
            <input
              ref="ocrFileInput"
              type="file"
              accept="image/jpeg,image/png,image/webp,image/jpg"
              class="sr-only"
              @change="onOcrFileChange"
            />
            <button
              v-if="auth.hasPermission('invoices.create')"
              type="button"
              :disabled="ocrLoading"
              class="text-sm text-teal-700 hover:underline flex items-center gap-1 disabled:opacity-50"
              title="رفع صورة فاتورة (OCR) — راجع الأرقام قبل الحفظ"
              @click="openOcrPicker"
            >
              {{ ocrLoading ? 'جارٍ الاستخراج…' : '📷 استخراج من صورة' }}
            </button>
            <button type="button" class="text-sm text-primary-600 hover:underline flex items-center gap-1" @click="addItem">
              <span class="text-base leading-none">+</span> إضافة بند
            </button>
          </div>
        </div>
        <p v-if="ocrHint" class="text-[11px] text-teal-800 bg-teal-50 rounded-lg px-2 py-1.5">{{ ocrHint }}</p>

        <div class="rounded-lg border border-gray-100 bg-gray-50/80 px-3 py-2.5 mb-3 flex flex-col sm:flex-row sm:items-end gap-2">
          <div class="flex-1 min-w-0">
            <label class="block text-[11px] font-medium text-gray-500 mb-1">بحث في الكتالوج (منتجات وخدمات)</label>
            <input
              v-model="productSearch"
              type="search"
              class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-white"
              placeholder="ابحث بالاسم أو رمز SKU…"
              autocomplete="off"
            />
          </div>
          <p v-if="catalogLoadError" class="text-xs text-amber-700 sm:shrink-0">{{ catalogLoadError }}</p>
          <p v-else class="text-[11px] text-gray-500 sm:shrink-0 tabular-nums">{{ filteredProducts.length }} / {{ catalogProducts.length }} صنفاً</p>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-xs text-gray-500 bg-gray-50">
              <tr>
                <th class="px-3 py-2 text-right min-w-[220px]">المنتج / الخدمة</th>
                <th class="px-3 py-2 text-right w-20">الكمية</th>
                <th class="px-3 py-2 text-right w-28">سعر الوحدة</th>
                <th class="px-3 py-2 text-right w-20">الضريبة %</th>
                <th class="px-3 py-2 text-right w-28">الإجمالي</th>
                <th class="px-3 py-2 w-8"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, i) in form.items" :key="`${i}-${item.catalog_pick}-${item.product_id ?? 'x'}`" class="border-t border-gray-100">
                <td class="px-3 py-2 align-top">
                  <select
                    v-model="item.catalog_pick"
                    class="w-full px-2 py-1.5 mb-1.5 border border-gray-300 rounded-lg text-sm bg-white"
                    @change="applyCatalogPick(i)"
                  >
                    <option value="">— اختر من القائمة أو أدخل يدوياً —</option>
                    <option value="__manual__">✏️ بند حر (بدون ربط كتالوج)</option>
                    <option
                      v-for="p in filteredProducts"
                      :key="p.id"
                      :value="String(p.id)"
                    >
                      {{ formatProductOption(p) }}
                    </option>
                  </select>
                  <input
                    v-model="item.name"
                    required
                    placeholder="يُملأ تلقائياً عند الاختيار، أو اكتب وصف البند"
                    class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm"
                  />
                  <div class="mt-1 flex flex-wrap items-center gap-1.5">
                    <span
                      v-if="item.product_id"
                      class="inline-flex items-center text-[10px] font-medium px-1.5 py-0.5 rounded-md bg-teal-50 text-teal-800 border border-teal-100"
                    >
                      مرتبط بالكتالوج #{{ item.product_id }}
                    </span>
                    <button
                      type="button"
                      title="إضافة منتج أو خدمة جديدة إلى الكتالوج وربطها بهذا البند"
                      class="whitespace-nowrap rounded-lg border border-primary-200 bg-primary-50 px-2 py-1 text-[11px] font-semibold text-primary-900 transition-colors hover:bg-primary-100 dark:border-primary-800/50 dark:bg-primary-950/30 dark:text-primary-100 dark:hover:bg-primary-900/35"
                      @click="openQuickProduct(i)"
                    >
                      + جديد في الكتالوج
                    </button>
                  </div>
                </td>
                <td class="px-3 py-2 align-top">
                  <input v-model.number="item.quantity" type="number" min="0.001" step="0.001" required class="w-20 px-2 py-1.5 border border-gray-300 rounded text-sm text-center" />
                </td>
                <td class="px-3 py-2 align-top">
                  <input v-model.number="item.unit_price" type="number" min="0" step="0.01" required class="w-28 px-2 py-1.5 border border-gray-300 rounded text-sm text-center" />
                </td>
                <td class="px-3 py-2 align-top">
                  <input v-model.number="item.tax_rate" type="number" min="0" max="100" class="w-20 px-2 py-1.5 border border-gray-300 rounded text-sm text-center" />
                </td>
                <td class="px-3 py-2 font-medium text-right align-top">
                  {{ lineTotal(item).toFixed(2) }}
                </td>
                <td class="px-3 py-2 align-top">
                  <button type="button" class="text-red-400 hover:text-red-600 text-lg" @click="removeItem(i)">×</button>
                </td>
              </tr>
            </tbody>
            <tfoot v-if="form.items.length">
              <tr class="border-t-2 border-gray-200 bg-gray-50">
                <td colspan="4" class="px-3 py-2 text-right font-semibold text-gray-700">الإجمالي</td>
                <td class="px-3 py-2 text-right font-bold text-gray-900">{{ grandTotal.toFixed(2) }} {{ form.currency }}</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <!-- الدفع -->
      <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
        <h3 class="text-sm font-semibold text-gray-700">معلومات الدفع</h3>
        <p class="text-[11px] text-gray-500 leading-relaxed">
          يُعبَّأ المبلغ المدفوع تلقائياً بإجمالي الفاتورة لطرق الدفع الفورية؛ للائتمان يبقى 0 حتى تسجّل دفعات لاحقاً من صفحة الفاتورة.
        </p>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="block text-xs text-gray-500 mb-1">طريقة الدفع</label>
            <select
              v-model="form.payment.method"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
            >
              <option value="cash">نقداً</option>
              <option value="card">بطاقة</option>
              <option value="wallet">محفظة</option>
              <option value="bank_transfer">تحويل بنكي</option>
              <option value="credit">ائتمان (آجل)</option>
            </select>
          </div>
          <div>
            <div class="flex items-center justify-between gap-2 mb-1">
              <label class="block text-xs text-gray-500">المبلغ المدفوع</label>
              <button
                v-if="paymentAmountTouched"
                type="button"
                class="text-[10px] font-semibold text-primary-600 hover:underline shrink-0"
                @click="resetPaymentAutoSync"
              >
                إعادة التعبئة التلقائية
              </button>
            </div>
            <input
              v-model.number="form.payment.amount"
              type="number"
              min="0"
              step="0.01"
              :max="grandTotal > 0 ? roundMoney2(grandTotal) : undefined"
              :placeholder="paymentPlaceholder"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm tabular-nums"
              @input="onPaidFieldInput"
            />
            <p v-if="form.payment.method === 'credit'" class="text-[10px] text-gray-500 mt-1">
              لا يُرسل مبلغ عند الإنشاء؛ الفاتورة تبقى مستحقة بالكامل حتى تسجيل دفعات لاحقاً.
            </p>
          </div>
          <div class="flex items-end pb-1">
            <span class="text-sm text-gray-600">
              المتبقي:
              <strong :class="remainingStrongClass">{{ remainingAmount.toFixed(2) }} {{ form.currency }}</strong>
            </span>
          </div>
        </div>
      </div>

      <div v-if="error" class="text-red-600 text-sm bg-red-50 rounded-lg p-3 whitespace-pre-line">{{ error }}</div>

      <div class="flex justify-end gap-3">
        <RouterLink to="/invoices" class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">إلغاء</RouterLink>
        <button type="submit" :disabled="saving || !form.items.length" class="px-6 py-2 text-sm bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50">
          {{ saving ? 'جارٍ الحفظ...' : 'إنشاء الفاتورة' }}
        </button>
      </div>
    </form>

    <!-- عميل سريع -->
    <Teleport to="body">
      <div
        v-if="quickCustomerOpen"
        class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/45"
        role="dialog"
        aria-modal="true"
        @click.self="quickCustomerOpen = false"
      >
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-600 shadow-xl max-w-md w-full p-5 space-y-4" @click.stop>
          <h4 class="text-sm font-bold text-gray-900 dark:text-slate-100">إضافة عميل سريعة</h4>
          <div class="grid grid-cols-1 gap-3 text-sm">
            <div>
              <label class="block text-xs text-gray-500 mb-1">نوع العميل</label>
              <select v-model="quickCustomer.type" class="w-full px-3 py-2 border rounded-lg border-gray-300">
                <option value="b2c">فرد (B2C)</option>
                <option value="b2b">شركة (B2B)</option>
              </select>
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">الاسم <span class="text-red-500">*</span></label>
              <input v-model="quickCustomer.name" class="w-full px-3 py-2 border rounded-lg border-gray-300" placeholder="اسم العميل" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">الجوال</label>
              <input v-model="quickCustomer.phone" class="w-full px-3 py-2 border rounded-lg border-gray-300" placeholder="05xxxxxxxx" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">البريد</label>
              <input v-model="quickCustomer.email" type="email" class="w-full px-3 py-2 border rounded-lg border-gray-300" placeholder="اختياري" />
            </div>
          </div>
          <p v-if="quickModalError" class="text-xs text-red-600">{{ quickModalError }}</p>
          <div class="flex justify-end gap-2 pt-1">
            <button type="button" class="px-3 py-2 text-sm border rounded-lg border-gray-300" @click="quickCustomerOpen = false">إلغاء</button>
            <button
              type="button"
              class="px-4 py-2 text-sm rounded-lg bg-primary-600 text-white disabled:opacity-50"
              :disabled="quickCustomerSaving || !quickCustomer.name.trim()"
              @click="submitQuickCustomer"
            >
              {{ quickCustomerSaving ? 'جارٍ الحفظ...' : 'حفظ واختيار' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- مركبة سريعة -->
    <Teleport to="body">
      <div
        v-if="quickVehicleOpen"
        class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/45"
        role="dialog"
        aria-modal="true"
        @click.self="quickVehicleOpen = false"
      >
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-600 shadow-xl max-w-md w-full p-5 space-y-4" @click.stop>
          <h4 class="text-sm font-bold text-gray-900 dark:text-slate-100">إضافة مركبة سريعة</h4>
          <p class="text-xs text-gray-500">تُربط المركبة بالعميل المختار حالياً.</p>
          <div class="grid grid-cols-1 gap-3 text-sm">
            <div>
              <label class="block text-xs text-gray-500 mb-1">رقم اللوحة <span class="text-red-500">*</span></label>
              <input v-model="quickVehicle.plate_number" class="w-full px-3 py-2 border rounded-lg border-gray-300" placeholder="مثال: أ ب ج 1234" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">الماركة <span class="text-red-500">*</span></label>
              <input v-model="quickVehicle.make" class="w-full px-3 py-2 border rounded-lg border-gray-300" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">الموديل <span class="text-red-500">*</span></label>
              <input v-model="quickVehicle.model" class="w-full px-3 py-2 border rounded-lg border-gray-300" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">السنة</label>
              <input v-model.number="quickVehicle.year" type="number" min="1900" max="2100" class="w-full px-3 py-2 border rounded-lg border-gray-300" placeholder="اختياري" />
            </div>
          </div>
          <p v-if="quickModalError" class="text-xs text-red-600">{{ quickModalError }}</p>
          <div class="flex justify-end gap-2 pt-1">
            <button type="button" class="px-3 py-2 text-sm border rounded-lg border-gray-300" @click="quickVehicleOpen = false">إلغاء</button>
            <button
              type="button"
              class="px-4 py-2 text-sm rounded-lg bg-teal-600 text-white disabled:opacity-50"
              :disabled="quickVehicleSaving || !canSubmitQuickVehicle"
              @click="submitQuickVehicle"
            >
              {{ quickVehicleSaving ? 'جارٍ الحفظ...' : 'حفظ واختيار' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- منتج سريع -->
    <Teleport to="body">
      <div
        v-if="quickProductOpen"
        class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/45"
        role="dialog"
        aria-modal="true"
        @click.self="quickProductOpen = false"
      >
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-600 shadow-xl max-w-md w-full p-5 space-y-4" @click.stop>
          <h4 class="text-sm font-bold text-gray-900 dark:text-slate-100">إضافة سريعة للكتالوج</h4>
          <p class="text-xs text-gray-500">يُنشأ صنفاً في المنتجات/الخدمات ويُربَط بهذا البند مباشرة (يتطلب صلاحية إنشاء منتجات).</p>
          <div class="grid grid-cols-1 gap-3 text-sm">
            <div>
              <label class="block text-xs text-gray-500 mb-1">نوع الصنف</label>
              <select v-model="quickProduct.product_type" class="w-full px-3 py-2 border rounded-lg border-gray-300 bg-white">
                <option value="service">خدمة (افتراضي للفواتير)</option>
                <option value="physical">منتج مادي</option>
                <option value="consumable">مستهلكات</option>
                <option value="labor">أجور / عمالة</option>
              </select>
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">الاسم <span class="text-red-500">*</span></label>
              <input v-model="quickProduct.name" class="w-full px-3 py-2 border rounded-lg border-gray-300" placeholder="مثال: تغيير زيت" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">سعر البيع <span class="text-red-500">*</span></label>
              <input v-model.number="quickProduct.sale_price" type="number" min="0" step="0.01" class="w-full px-3 py-2 border rounded-lg border-gray-300" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">الضريبة %</label>
              <input v-model.number="quickProduct.tax_rate" type="number" min="0" max="100" class="w-full px-3 py-2 border rounded-lg border-gray-300" />
            </div>
            <label
              v-if="quickProduct.product_type === 'physical' || quickProduct.product_type === 'consumable'"
              class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer"
            >
              <input v-model="quickProduct.track_inventory" type="checkbox" class="rounded border-gray-300" />
              تتبع المخزون (للمنتجات المادية عادةً مفعّل)
            </label>
          </div>
          <p v-if="quickModalError" class="text-xs text-red-600">{{ quickModalError }}</p>
          <div class="flex justify-end gap-2 pt-1">
            <button type="button" class="px-3 py-2 text-sm border rounded-lg border-gray-300" @click="quickProductOpen = false">إلغاء</button>
            <button
              type="button"
              class="rounded-lg bg-primary-600 px-4 py-2 text-sm text-white transition-colors hover:bg-primary-700 disabled:opacity-50"
              :disabled="quickProductSaving || !quickProduct.name.trim()"
              @click="submitQuickProduct"
            >
              {{ quickProductSaving ? 'جارٍ الحفظ...' : 'حفظ وربط' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { LEDGER_POST_FAILED } from '@/constants/apiErrorCodes'
import apiClient, { withIdempotency } from '@/lib/apiClient'
import SmartDatePicker from '@/components/ui/SmartDatePicker.vue'
import { localizeBackendMessage } from '@/utils/runtimeLocale'
import { summarizeAxiosError } from '@/utils/apiErrorSummary'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import {
  getDefaultPaidAmountByPaymentMethod,
  parsePaidInput,
  remainingFromTotalAndPaid,
  roundMoney2,
  validatePaidForSubmit,
} from '@/utils/invoiceCreatePayment'

type IdRecord = { id: number; name?: string; plate_number?: string; make?: string; model?: string; year?: number | null }
type ProductRow = {
  id: number
  name: string
  name_ar?: string | null
  sale_price: number | string
  tax_rate?: number | string | null
  sku?: string | null
  product_type?: string | null
}
type LineItem = {
  name: string
  quantity: number
  unit_price: number
  tax_rate: number
  product_id: number | null
  /** '', '__manual__', or product id string — bound to the catalog `<select>` */
  catalog_pick: string
}

const router = useRouter()
const auth = useAuthStore()
const toast = useToast()
const customers = ref<IdRecord[]>([])
const catalogProducts = ref<ProductRow[]>([])
const productSearch = ref('')
const catalogLoadError = ref('')
const ocrFileInput = ref<HTMLInputElement | null>(null)
const invoiceFormRef = ref<HTMLFormElement | null>(null)
const ocrLoading = ref(false)
const ocrHint = ref('')

type OcrLineItem = {
  description?: string
  qty?: number | null
  unit_price?: number | null
  matched_product_id?: number | null
}

function openOcrPicker() {
  ocrHint.value = ''
  ocrFileInput.value?.click()
}

function fileToBase64Payload(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const r = new FileReader()
    r.onload = () => {
      const s = String(r.result ?? '')
      const comma = s.indexOf(',')
      resolve(comma >= 0 ? s.slice(comma + 1) : s)
    }
    r.onerror = () => reject(r.error ?? new Error('read failed'))
    r.readAsDataURL(file)
  })
}

async function onOcrFileChange(ev: Event) {
  const input = ev.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file || !auth.hasPermission('invoices.create')) return
  ocrLoading.value = true
  ocrHint.value = ''
  try {
    const b64 = await fileToBase64Payload(file)
    const { data } = await apiClient.post<{ results?: Array<{ success?: boolean; error?: string; line_items?: OcrLineItem[] }> }>(
      '/invoices/ocr-extract',
      { images: [b64], match_products: true },
      { skipGlobalErrorToast: true },
    )
    const results = Array.isArray(data?.results) ? data.results : []
    const first = results.find((r) => r?.success && Array.isArray(r.line_items) && r.line_items.length)
    if (!first?.line_items?.length) {
      const err = results.find((r) => r?.error)?.error
      toast.error(err ? String(err) : 'لم يُستخرج أي بند من الصورة.')
      return
    }
    const mapped: LineItem[] = first.line_items.map((li) => {
      const pid =
        li.matched_product_id != null && Number.isFinite(Number(li.matched_product_id))
          ? Number(li.matched_product_id)
          : null
      return {
        name: (li.description && String(li.description).trim()) || 'بند',
        quantity: li.qty != null && Number.isFinite(Number(li.qty)) ? Math.max(0.001, Number(li.qty)) : 1,
        unit_price: li.unit_price != null && Number.isFinite(Number(li.unit_price)) ? Number(li.unit_price) : 0,
        tax_rate: 15,
        product_id: pid,
        catalog_pick: pid ? String(pid) : '__manual__',
      }
    })
    form.value.items.push(...mapped)
    ocrHint.value = `أُضيف ${mapped.length} بنداً من OCR — راجع الكميات والأسعار قبل الإصدار.`
    toast.success('تم استخراج البنود')
  } catch (e: unknown) {
    toast.error(summarizeAxiosError(e))
  } finally {
    ocrLoading.value = false
  }
}
const vehicles = ref<IdRecord[]>([])
const saving = ref(false)
const error = ref('')

const quickCustomerOpen = ref(false)
const quickVehicleOpen = ref(false)
const quickProductOpen = ref(false)
const quickModalError = ref('')
const quickCustomerSaving = ref(false)
const quickVehicleSaving = ref(false)
const quickProductSaving = ref(false)
const quickProductRowIndex = ref<number | null>(null)

const quickCustomer = ref({
  type: 'b2c' as 'b2c' | 'b2b',
  name: '',
  phone: '',
  email: '',
})

const quickVehicle = ref({
  plate_number: '',
  make: '',
  model: '',
  year: null as number | null,
})

const quickProduct = ref({
  name: '',
  sale_price: 0,
  tax_rate: 15,
  track_inventory: false,
  product_type: 'service' as 'service' | 'physical' | 'consumable' | 'labor',
})

const today = new Date().toISOString().split('T')[0]
const dueDate = new Date(Date.now() + 30 * 86400000).toISOString().split('T')[0]

const form = ref({
  customer_id: '',
  vehicle_id: '',
  issued_at: today,
  due_at: dueDate,
  currency: 'SAR',
  notes: '',
  items: [{ name: '', quantity: 1, unit_price: 0, tax_rate: 15, product_id: null, catalog_pick: '' }] as LineItem[],
  payment: { method: 'cash', amount: 0 },
})

function extractPaginatedList(res: { data?: { data?: { data?: unknown[] } | unknown[] } }): unknown[] {
  const root = res.data?.data
  if (Array.isArray(root)) return root
  if (root && typeof root === 'object' && 'data' in root && Array.isArray((root as { data: unknown[] }).data)) {
    return (root as { data: unknown[] }).data
  }
  return []
}

function vehicleLabel(v: IdRecord): string {
  const plate = v.plate_number ?? ''
  const mm = [v.make, v.model].filter(Boolean).join(' ')
  return mm ? `${plate} — ${mm}` : plate || `#${v.id}`
}

function normalizeProduct(raw: Record<string, unknown>): ProductRow {
  return {
    id: Number(raw.id),
    name: String(raw.name ?? ''),
    name_ar: raw.name_ar != null ? String(raw.name_ar) : null,
    sale_price: raw.sale_price as number | string,
    tax_rate: raw.tax_rate as number | string | null | undefined,
    sku: raw.sku != null ? String(raw.sku) : null,
    product_type: raw.product_type != null ? String(raw.product_type) : null,
  }
}

function productTypeShort(t?: string | null): string {
  switch (t) {
    case 'service':
      return 'خدمة'
    case 'physical':
      return 'منتج'
    case 'consumable':
      return 'مستهلك'
    case 'labor':
      return 'عمالة'
    default:
      return ''
  }
}

function formatProductOption(p: ProductRow): string {
  const label = (p.name_ar && String(p.name_ar).trim()) || p.name || `صنف #${p.id}`
  const tag = productTypeShort(p.product_type)
  const sku = p.sku ? ` · ${p.sku}` : ''
  return `${tag ? `[${tag}] ` : ''}${label}${sku}`
}

const filteredProducts = computed(() => {
  const list = catalogProducts.value
  const q = productSearch.value.trim().toLowerCase()
  if (!q) return list
  return list.filter((p) => {
    const blob = `${p.name || ''} ${p.name_ar || ''} ${p.sku || ''}`.toLowerCase()
    return blob.includes(q)
  })
})

function applyCatalogPick(rowIndex: number) {
  const item = form.value.items[rowIndex]
  if (!item) return
  const v = item.catalog_pick
  if (v === '' || v === null) {
    item.product_id = null
    return
  }
  if (v === '__manual__') {
    item.product_id = null
    return
  }
  const id = Number(v)
  if (!Number.isFinite(id)) return
  const p = catalogProducts.value.find((x) => x.id === id)
  if (!p) return
  item.product_id = p.id
  item.name = (p.name_ar && String(p.name_ar).trim()) || p.name || ''
  item.unit_price = Number(p.sale_price) || 0
  item.tax_rate = p.tax_rate != null && p.tax_rate !== '' ? Number(p.tax_rate) : 15
}

async function loadCatalogProducts() {
  catalogLoadError.value = ''
  try {
    const { data } = await apiClient.get('/products', {
      params: { per_page: 500, is_active: true },
      skipGlobalErrorToast: true,
    })
    const rawList = extractPaginatedList({ data }) as Record<string, unknown>[]
    catalogProducts.value = rawList.map((r) => normalizeProduct(r))
  } catch {
    catalogProducts.value = []
    catalogLoadError.value = 'تعذر تحميل الكتالوج. يمكنك اختيار «بند حر» أو «جديد في الكتالوج».'
  }
}

const canSubmitQuickVehicle = computed(() => {
  return (
    Boolean(quickVehicle.value.plate_number.trim())
    && Boolean(quickVehicle.value.make.trim())
    && Boolean(quickVehicle.value.model.trim())
  )
})

function openQuickCustomer() {
  quickModalError.value = ''
  quickCustomer.value = { type: 'b2c', name: '', phone: '', email: '' }
  quickCustomerOpen.value = true
}

function openQuickVehicle() {
  if (!form.value.customer_id) return
  quickModalError.value = ''
  quickVehicle.value = { plate_number: '', make: '', model: '', year: null }
  quickVehicleOpen.value = true
}

function openQuickProduct(rowIndex: number) {
  quickModalError.value = ''
  quickProductRowIndex.value = rowIndex
  const row = form.value.items[rowIndex]
  quickProduct.value = {
    name: row?.name?.trim() || '',
    sale_price: Number(row?.unit_price) || 0,
    tax_rate: Number(row?.tax_rate) ?? 15,
    track_inventory: false,
    product_type: 'service',
  }
  quickProductOpen.value = true
}

async function loadCustomers() {
  try {
    const { data } = await apiClient.get('/customers', { params: { per_page: 500 }, skipGlobalErrorToast: true })
    const list = extractPaginatedList({ data }) as IdRecord[]
    customers.value = list
  } catch {
    customers.value = []
  }
}

async function loadVehiclesForCustomer(customerId: string) {
  const id = Number(customerId)
  if (!id) {
    vehicles.value = []
    return
  }
  try {
    const { data } = await apiClient.get('/vehicles', { params: { customer_id: id, per_page: 200 }, skipGlobalErrorToast: true })
    vehicles.value = extractPaginatedList({ data }) as IdRecord[]
  } catch {
    vehicles.value = []
  }
}

watch(
  () => form.value.customer_id,
  (cid) => {
    form.value.vehicle_id = ''
    void loadVehiclesForCustomer(cid)
  },
)

function addItem() {
  form.value.items.push({
    name: '',
    quantity: 1,
    unit_price: 0,
    tax_rate: 15,
    product_id: null,
    catalog_pick: '',
  })
}

function removeItem(i: number) {
  form.value.items.splice(i, 1)
}

function lineTotal(item: LineItem): number {
  const base = item.quantity * item.unit_price
  return base + base * ((item.tax_rate ?? 0) / 100)
}

const grandTotal = computed(() => form.value.items.reduce((s, i) => s + lineTotal(i), 0))

/** لم يُعدِل المستخدم حقل المدفوع يدوياً — يُسمح بإعادة التعبئة التلقائية */
const paymentAmountTouched = ref(false)

const paidAmountNumeric = computed(() => {
  const v = parsePaidInput(form.value.payment.amount)
  return Number.isFinite(v) ? roundMoney2(v) : 0
})

const remainingAmount = computed(() =>
  remainingFromTotalAndPaid(grandTotal.value, paidAmountNumeric.value),
)

const remainingStrongClass = computed(() => {
  const r = remainingAmount.value
  if (r <= 0.005) return 'text-emerald-700'
  return 'text-amber-700'
})

const paymentPlaceholder = computed(() => roundMoney2(grandTotal.value).toFixed(2))

function onPaidFieldInput() {
  paymentAmountTouched.value = true
}

function resetPaymentAutoSync() {
  paymentAmountTouched.value = false
  form.value.payment.amount = getDefaultPaidAmountByPaymentMethod(
    form.value.payment.method,
    grandTotal.value,
  )
}

watch(
  () => [roundMoney2(grandTotal.value), form.value.payment.method] as const,
  () => {
    if (paymentAmountTouched.value) return
    form.value.payment.amount = getDefaultPaidAmountByPaymentMethod(
      form.value.payment.method,
      grandTotal.value,
    )
  },
  { flush: 'post' },
)

watch(
  () => form.value.payment.amount,
  (v) => {
    if (typeof v === 'number' && Number.isNaN(v)) {
      form.value.payment.amount = 0
    }
  },
)

function onIssuedDateChange(val: { from: string; to: string }) {
  form.value.issued_at = val.from || val.to
}

function onDueDateChange(val: { from: string; to: string }) {
  form.value.due_at = val.from || val.to
}

async function submitQuickCustomer() {
  quickModalError.value = ''
  quickCustomerSaving.value = true
  try {
    const { data } = await apiClient.post(
      '/customers',
      {
        type: quickCustomer.value.type,
        name: quickCustomer.value.name.trim(),
        phone: quickCustomer.value.phone.trim() || undefined,
        email: quickCustomer.value.email.trim() || undefined,
      },
      { skipGlobalErrorToast: true },
    )
    const c = data.data as IdRecord
    customers.value.push(c)
    customers.value.sort((a, b) => String(a.name ?? '').localeCompare(String(b.name ?? ''), 'ar'))
    form.value.customer_id = String(c.id)
    quickCustomerOpen.value = false
  } catch (e: unknown) {
    quickModalError.value = summarizeAxiosError(e)
  } finally {
    quickCustomerSaving.value = false
  }
}

async function submitQuickVehicle() {
  if (!form.value.customer_id) return
  quickModalError.value = ''
  quickVehicleSaving.value = true
  try {
    const body: Record<string, unknown> = {
      customer_id: Number(form.value.customer_id),
      plate_number: quickVehicle.value.plate_number.trim(),
      make: quickVehicle.value.make.trim(),
      model: quickVehicle.value.model.trim(),
    }
    if (quickVehicle.value.year != null && quickVehicle.value.year > 0) {
      body.year = quickVehicle.value.year
    }
    const { data } = await apiClient.post('/vehicles', body, { skipGlobalErrorToast: true })
    const v = data.data as IdRecord
    vehicles.value = [v, ...vehicles.value.filter((x) => x.id !== v.id)]
    form.value.vehicle_id = String(v.id)
    quickVehicleOpen.value = false
  } catch (e: unknown) {
    quickModalError.value = summarizeAxiosError(e)
  } finally {
    quickVehicleSaving.value = false
  }
}

async function submitQuickProduct() {
  const idx = quickProductRowIndex.value
  if (idx === null || idx < 0) return
  quickModalError.value = ''
  quickProductSaving.value = true
  try {
    const pt = quickProduct.value.product_type
    const track =
      (pt === 'physical' || pt === 'consumable') ? quickProduct.value.track_inventory : false
    const { data } = await apiClient.post(
      '/products',
      {
        name: quickProduct.value.name.trim(),
        sale_price: quickProduct.value.sale_price,
        tax_rate: quickProduct.value.tax_rate,
        product_type: pt,
        track_inventory: track,
      },
      { skipGlobalErrorToast: true },
    )
    const raw = data.data as Record<string, unknown>
    const p = normalizeProduct(raw)
    const row = form.value.items[idx]
    if (row) {
      row.product_id = p.id
      row.catalog_pick = String(p.id)
      row.name = (p.name_ar && String(p.name_ar).trim()) || p.name || ''
      row.unit_price = Number(p.sale_price) || 0
      row.tax_rate = p.tax_rate != null && p.tax_rate !== '' ? Number(p.tax_rate) : 15
    }
    const rest = catalogProducts.value.filter((x) => x.id !== p.id)
    catalogProducts.value = [...rest, p].sort((a, b) =>
      String(a.name || '').localeCompare(String(b.name || ''), 'ar'),
    )
    quickProductOpen.value = false
    toast.success('تمت الإضافة', 'صنف جديد في الكتالوج ومربوط بالبند.')
  } catch (e: unknown) {
    quickModalError.value = summarizeAxiosError(e)
  } finally {
    quickProductSaving.value = false
  }
}

async function submit() {
  if (saving.value) return
  error.value = ''
  const formEl = invoiceFormRef.value
  if (formEl && !formEl.checkValidity()) {
    formEl.reportValidity()
    return
  }
  if (!form.value.items.length) {
    error.value = 'أضف بنداً واحداً على الأقل قبل حفظ الفاتورة.'
    return
  }
  if (form.value.items.some((i) => !String(i.name || '').trim())) {
    error.value = 'يرجى إدخال وصف لكل بند (عمود اسم البند).'
    return
  }
  saving.value = true
  try {
    const total = roundMoney2(grandTotal.value)
    const paidRaw = parsePaidInput(form.value.payment.amount)
    const payVal = validatePaidForSubmit({
      method: form.value.payment.method,
      paid: paidRaw,
      invoiceTotal: total,
    })
    if (!payVal.ok) {
      error.value = payVal.messageAr
      saving.value = false
      return
    }
    const paidFinal = roundMoney2(Number.isFinite(paidRaw) ? paidRaw : 0)

    const payload: Record<string, unknown> = {
      customer_id: Number(form.value.customer_id),
      issued_at: form.value.issued_at,
      due_at: form.value.due_at,
      currency: form.value.currency,
      notes: form.value.notes || undefined,
      items: form.value.items.map((i) => ({
        name: i.name,
        quantity: i.quantity,
        unit_price: i.unit_price,
        tax_rate: i.tax_rate,
        ...(i.product_id ? { product_id: i.product_id } : {}),
      })),
    }

    if (form.value.payment.method !== 'credit' && paidFinal > 0.005) {
      payload.payment = {
        method: form.value.payment.method,
        amount: paidFinal,
      }
    }
    if (form.value.vehicle_id) {
      payload.vehicle_id = Number(form.value.vehicle_id)
    }
    const { data } = await apiClient.post('/invoices', payload, { ...withIdempotency(), skipGlobalErrorToast: true })
    router.push(`/invoices/${data.data.id}`)
  } catch (e: unknown) {
    const errObj = e as {
      response?: { status?: number; data?: { message?: string; code?: string; trace_id?: string } }
    }
    const payload = errObj?.response?.data
    const tid = String(payload?.trace_id ?? '').trim()
    if (errObj?.response?.status === 503 && payload?.code === LEDGER_POST_FAILED) {
      const base =
        localizeBackendMessage(payload?.message) || 'لم تُنشأ الفاتورة — أعد المحاولة بنفس البيانات.'
      error.value = tid ? `${base}\nرمز التتبع: ${tid}` : base
    } else {
      error.value = summarizeAxiosError(e)
    }
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  void Promise.all([loadCustomers(), loadCatalogProducts()])
})
</script>
