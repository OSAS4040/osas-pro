<template>
  <div class="space-y-3">
    <p class="text-xs text-gray-600 leading-relaxed bg-slate-50 border border-slate-100 rounded-lg px-3 py-2">
      اختر <strong>خدمة من الكتالوج</strong> ليُحسب السعر والضريبة على الخادم من العقد أو سياسة التسعير — الحقلان غير قابلين للتعديل.
      أو <strong>«بند يدوي»</strong> لإدخال الاسم والسعر يدوياً.
    </p>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="text-xs text-gray-500 bg-gray-50">
          <tr>
            <th class="px-2 py-2 text-right">النوع</th>
            <th class="px-2 py-2 text-right min-w-[11rem]">خدمة الكتالوج</th>
            <th class="px-2 py-2 text-right">الاسم / الوصف</th>
            <th class="px-2 py-2 text-right">الكمية</th>
            <th class="px-2 py-2 text-right">سعر الوحدة</th>
            <th class="px-2 py-2 text-right">الضريبة %</th>
            <th class="px-2 py-2 text-right">الإجمالي</th>
            <th v-if="!readonly" class="px-2 py-2"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(item, i) in items" :key="i" class="border-t border-gray-100">
            <td class="px-2 py-2">
              <select
                v-model="item.item_type"
                class="border border-gray-300 rounded px-2 py-1 text-xs"
                :disabled="readonly"
              >
                <option value="part">قطعة</option>
                <option value="labor">عمالة</option>
                <option value="service">خدمة</option>
                <option value="other">أخرى</option>
              </select>
            </td>
            <td class="px-2 py-2 align-top">
              <select
                class="w-full border border-gray-300 rounded px-2 py-1 text-xs max-w-[14rem]"
                :disabled="readonly"
                :value="item.service_id == null ? '' : String(item.service_id)"
                @change="onSelectService(i, ($event.target as HTMLSelectElement).value)"
              >
                <option value="">— بند يدوي —</option>
                <option v-for="s in services" :key="s.id" :value="String(s.id)">{{ s.name_ar || s.name }}</option>
              </select>
              <p v-if="item.pricing_loading" class="text-[10px] text-gray-500 mt-1">جارٍ جلب السعر المعتمد…</p>
              <p v-else-if="item.pricing_error" class="text-[10px] text-red-600 mt-1 leading-snug">{{ item.pricing_error }}</p>
              <p v-else-if="item.service_id != null && item.pricing_ok" class="text-[10px] text-emerald-800 mt-1 leading-snug">
                {{ item.pricing_source_label_ar }}
              </p>
            </td>
            <td class="px-2 py-2">
              <input
                v-model="item.name"
                type="text"
                :required="item.service_id == null"
                :readonly="item.service_id != null || readonly"
                :class="item.service_id != null || readonly ? 'bg-gray-100 text-gray-700' : ''"
                placeholder="اسم البند"
                class="w-full border border-gray-300 rounded px-2 py-1 text-sm"
              />
            </td>
            <td class="px-2 py-2 w-20">
              <input
                v-model="item.quantity"
                type="number"
                min="0.001"
                step="0.001"
                class="w-full border border-gray-300 rounded px-2 py-1 text-sm text-center"
                :disabled="readonly"
                @change="onQuantityChange(i)"
              />
            </td>
            <td class="px-2 py-2 w-28">
              <input
                v-model="item.unit_price"
                type="number"
                min="0"
                step="0.01"
                :readonly="item.service_id != null || readonly"
                :class="item.service_id != null || readonly ? 'bg-gray-100 text-gray-800' : ''"
                class="w-full border border-gray-300 rounded px-2 py-1 text-sm text-center"
              />
            </td>
            <td class="px-2 py-2 w-20">
              <input
                v-model="item.tax_rate"
                type="number"
                min="0"
                max="100"
                step="0.01"
                :readonly="item.service_id != null || readonly"
                :class="item.service_id != null || readonly ? 'bg-gray-100 text-gray-800' : ''"
                class="w-full border border-gray-300 rounded px-2 py-1 text-sm text-center"
              />
            </td>
            <td class="px-2 py-2 text-center font-medium text-gray-700 w-28">{{ lineTotalDisplay(item) }} ر.س</td>
            <td v-if="!readonly" class="px-2 py-2">
              <button type="button" class="text-red-400 hover:text-red-600 text-lg" @click="removeAt(i)">✕</button>
            </td>
          </tr>
        </tbody>
        <tfoot v-if="items.length">
          <tr class="border-t-2 border-gray-200 bg-gray-50">
            <td :colspan="readonly ? 6 : 6" class="px-2 py-2 text-right font-semibold text-gray-700">الإجمالي</td>
            <td class="px-2 py-2 text-center font-bold text-gray-900 text-base">{{ totalAmountDisplay(items) }} ر.س</td>
            <td v-if="!readonly"></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <div v-if="!readonly" class="flex justify-end">
      <button type="button" class="text-sm text-primary-600 hover:underline flex items-center gap-1" @click="addLine">
        <span class="text-lg leading-none">+</span> إضافة بند
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import {
  type CatalogLineItem,
  emptyCatalogLine,
  lineTotalDisplay,
  totalAmountDisplay,
  loadCatalogLinePreview,
} from '@/composables/useWorkOrderCatalogLines'

const items = defineModel<CatalogLineItem[]>({ required: true })

const props = withDefaults(
  defineProps<{
    customerId: string
    vehicleId: string
    services: Array<{ id: number; name?: string; name_ar?: string }>
    readonly?: boolean
  }>(),
  { readonly: false, services: () => [] },
)

function removeAt(i: number) {
  items.value.splice(i, 1)
}

function addLine() {
  items.value.push(emptyCatalogLine())
}

async function refreshLine(i: number) {
  const row = items.value[i]
  if (!row || row.service_id == null) return
  await loadCatalogLinePreview(row, props.customerId, props.vehicleId, props.services)
}

function onSelectService(index: number, raw: string) {
  const row = items.value[index]
  if (!row) return
  row.service_id = raw === '' ? null : Number(raw)
  row.pricing_error = ''
  row.pricing_source_label_ar = ''
  if (row.service_id == null) {
    row.pricing_ok = false
    row.pricing_loading = false
    return
  }
  void refreshLine(index)
}

function onQuantityChange(index: number) {
  const row = items.value[index]
  if (row?.service_id != null) {
    void refreshLine(index)
  }
}

defineExpose({
  refreshAllCatalogPricing: async () => {
    for (let i = 0; i < items.value.length; i++) {
      if (items.value[i].service_id != null) {
        await refreshLine(i)
      }
    }
  },
})
</script>
