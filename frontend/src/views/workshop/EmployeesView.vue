<template>
  <div class="app-shell-page pb-10" dir="rtl">
    <!-- رأس الصفحة -->
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
      <div>
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight">إدارة الموظفين</h2>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-1 max-w-2xl leading-relaxed">
          ملفات موظفين كاملة مع تكاملات <strong class="font-semibold text-gray-700 dark:text-slate-300">قوى</strong>،
          <strong class="font-semibold">التأمينات الاجتماعية (GOSI)</strong>، و<strong class="font-semibold">التعاقد الإلكتروني</strong>.
          المزامنة الفعلية تتطلب اعتماد API من الجهة المختصة — يمكنك حفظ المراجع يدوياً والتنبيهات الذكية تساعدك على إكمال البيانات.
        </p>
      </div>
      <div class="flex items-center gap-2 flex-wrap shrink-0">
        <ExcelImport
          endpoint="/api/v1/governance/employees/import"
          template-url="/templates/employees_template.csv"
          label="استيراد Excel"
          title="استيراد موظفين من Excel"
          @imported="refreshAll"
        />
        <button
          type="button"
          class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary-600 text-white rounded-xl hover:bg-primary-700 transition-colors text-sm font-semibold shadow-sm"
          @click="openCreate"
        >
          <PlusIcon class="w-4 h-4" />
          موظف جديد
        </button>
      </div>
    </div>

    <!-- KPI -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
      <div
        v-for="s in kpiCards"
        :key="s.key"
        class="rounded-2xl border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800/70 p-4 shadow-sm hover:shadow-md transition-shadow"
      >
        <p class="text-[11px] font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wide">{{ s.label }}</p>
        <p class="text-2xl font-bold tabular-nums mt-1" :class="s.color">{{ s.value }}</p>
        <p v-if="s.hint" class="text-[10px] text-gray-400 dark:text-slate-500 mt-1 leading-snug">{{ s.hint }}</p>
      </div>
    </div>

    <!-- تنبيهات ذكية -->
    <div
      v-if="smartAlerts.length"
      class="rounded-2xl border border-amber-200 dark:border-amber-900/50 bg-amber-50/90 dark:bg-amber-950/30 px-4 py-3 space-y-2"
    >
      <p class="text-sm font-semibold text-amber-900 dark:text-amber-200 flex items-center gap-2">
        <BellAlertIcon class="w-5 h-5 shrink-0" />
        تنبيهات ومتابعة
      </p>
      <ul class="text-xs text-amber-900/90 dark:text-amber-100/90 space-y-1.5 list-disc list-inside leading-relaxed">
        <li v-for="(a, i) in smartAlerts" :key="i">{{ a }}</li>
      </ul>
    </div>

    <!-- تبويبات عرض -->
    <div class="flex flex-wrap gap-2 border-b border-gray-200 dark:border-slate-600 pb-1">
      <button
        v-for="t in viewTabs"
        :key="t.key"
        type="button"
        class="px-4 py-2 rounded-t-lg text-sm font-medium transition-colors"
        :class="
          viewTab === t.key
            ? 'bg-primary-600 text-white'
            : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800'
        "
        @click="viewTab = t.key"
      >
        {{ t.label }}
      </button>
    </div>

    <!-- فلاتر -->
    <div
      v-if="viewTab === 'list'"
      class="bg-white dark:bg-slate-800/60 rounded-2xl border border-gray-200 dark:border-slate-600 p-4 flex flex-col md:flex-row gap-3 md:items-center"
    >
      <input
        v-model="search"
        type="search"
        placeholder="بحث بالاسم، الرقم الوظيفي، الهوية، التخصص..."
        class="flex-1 min-w-[200px] border border-gray-300 dark:border-slate-600 dark:bg-slate-900 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
      />
      <select
        v-model="filterStatus"
        class="border border-gray-300 dark:border-slate-600 dark:bg-slate-900 rounded-xl px-4 py-2.5 text-sm focus:outline-none"
      >
        <option value="">كل الحالات</option>
        <option value="active">نشط</option>
        <option value="inactive">غير نشط</option>
        <option value="suspended">موقوف / إيقاف</option>
      </select>
      <select v-model="filterHr" class="border border-gray-300 dark:border-slate-600 dark:bg-slate-900 rounded-xl px-4 py-2.5 text-sm">
        <option value="">كل الموظفين</option>
        <option value="needs">يحتاج إكمال تكامل / هوية</option>
        <option value="ok">تكامل نسبي مكتمل</option>
      </select>
    </div>

    <!-- تحميل -->
    <div v-if="loading" class="flex justify-center py-16">
      <div class="w-10 h-10 border-4 border-primary-500 border-t-transparent rounded-full animate-spin" />
    </div>

    <!-- جدول -->
    <div
      v-else-if="viewTab === 'list'"
      class="table-shell dark:bg-slate-800/60"
    >
      <div class="overflow-x-auto">
        <table class="data-table min-w-[900px]">
          <thead>
            <tr>
              <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-slate-200">الموظف</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-slate-200">الهوية / الوظيفة</th>
              <th v-if="branches.length" class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-slate-200">الفرع</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-slate-200">الراتب</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-slate-200">التكاملات</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-slate-200">الحالة</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-slate-200">إجراءات</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="emp in filtered" :key="emp.id" class="hover:bg-gray-50/80 dark:hover:bg-slate-900/40 transition-colors">
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <div
                    class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-100 to-primary-200 dark:from-primary-900/50 dark:to-primary-800/40 flex items-center justify-center text-primary-800 dark:text-primary-200 font-bold text-sm"
                  >
                    {{ (emp.name || '?').charAt(0) }}
                  </div>
                  <div class="min-w-0">
                    <p class="font-semibold text-gray-900 dark:text-white truncate">{{ emp.name }}</p>
                    <p class="text-xs text-gray-400 font-mono truncate">{{ emp.employee_number || '—' }}</p>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3">
                <p class="text-gray-800 dark:text-slate-200 font-mono text-xs">{{ emp.national_id || '—' }}</p>
                <p class="text-gray-600 dark:text-slate-400 text-xs mt-0.5">{{ emp.position || '—' }}</p>
                <p v-if="skillPreview(emp)" class="text-[10px] text-slate-500 dark:text-slate-500 mt-1 line-clamp-2">{{ skillPreview(emp) }}</p>
              </td>
              <td v-if="branches.length" class="px-4 py-3 text-xs text-gray-600 dark:text-slate-400">
                {{ branchLabel(emp.branch_id) }}
              </td>
              <td class="px-4 py-3 text-gray-800 dark:text-slate-200 tabular-nums">{{ formatNum(emp.base_salary) }} ر.س</td>
              <td class="px-4 py-3">
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="b in integrationBadges(emp)"
                    :key="b.key"
                    class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-medium border"
                    :class="b.ok ? 'bg-emerald-50 text-emerald-800 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-gray-100 text-gray-600 border-gray-200 dark:bg-slate-700 dark:text-slate-300'"
                  >
                    {{ b.label }}
                  </span>
                </div>
              </td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium"
                  :class="statusClass(emp.status)"
                >
                  {{ statusLabel(emp.status) }}
                </span>
              </td>
              <td class="px-4 py-3">
                <button
                  type="button"
                  class="text-primary-600 dark:text-primary-400 hover:underline text-xs font-semibold"
                  @click="openEdit(emp)"
                >
                  تعديل الملف
                </button>
              </td>
            </tr>
            <tr v-if="!filtered.length">
              <td :colspan="branches.length ? 7 : 6" class="table-empty">
                <p class="table-empty-title">لا يوجد موظفون مطابقون للفلتر</p>
                <p class="table-empty-sub">جرّب تغيير البحث أو الفلاتر</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- لوحة التكاملات — ربط فعلي بسوق الإضافات + تقرير عند التثبيت -->
    <div v-else-if="viewTab === 'integrations'" class="grid md:grid-cols-3 gap-4">
      <div
        v-for="card in integrationCards"
        :key="card.title"
        class="rounded-2xl border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800/60 p-5 space-y-3"
      >
        <div class="flex items-center gap-2">
          <component :is="card.icon" class="w-8 h-8 text-primary-600 dark:text-primary-400" />
          <h3 class="font-bold text-gray-900 dark:text-white">{{ card.title }}</h3>
        </div>
        <p class="text-xs text-gray-600 dark:text-slate-400 leading-relaxed">{{ card.body }}</p>
        <div class="flex flex-col gap-2">
          <RouterLink
            :to="{ path: '/plugins', query: { tag: card.filterTag } }"
            class="w-full text-center py-2.5 rounded-xl text-xs font-semibold border border-primary-300 dark:border-primary-700 bg-primary-50 dark:bg-primary-950/40 text-primary-800 dark:text-primary-200 hover:bg-primary-100 dark:hover:bg-primary-900/50 transition-colors"
          >
            تثبيت / إعداد من سوق الإضافات
          </RouterLink>
          <button
            v-if="card.pluginKey"
            type="button"
            class="w-full py-2 rounded-xl text-xs font-medium border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/80 transition-colors"
            :disabled="integrationRun === card.pluginKey"
            @click="runIntegrationReport(card.pluginKey!)"
          >
            {{ integrationRun === card.pluginKey ? 'جاري التقرير...' : 'تقرير سريع (بيانات محلية)' }}
          </button>
        </div>
        <p v-if="integrationMessage && integrationLastKey === card.pluginKey" class="text-[11px] text-slate-500 dark:text-slate-400 leading-snug border-t border-slate-100 dark:border-slate-700 pt-2 whitespace-pre-wrap">
          {{ integrationMessage }}
        </p>
      </div>
    </div>

    <!-- نافذة الموظف -->
    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 bg-black/50 dark:bg-black/70 z-50 flex items-center justify-center p-4 overflow-y-auto"
        dir="rtl"
        @click.self="closeModal"
      >
        <div
          class="bg-white dark:bg-slate-900 rounded-2xl w-full max-w-3xl shadow-2xl border border-gray-200 dark:border-slate-600 my-8 max-h-[min(92vh,900px)] flex flex-col"
        >
          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-slate-600 shrink-0">
            <div>
              <h3 class="font-bold text-lg text-gray-900 dark:text-white">{{ editId ? 'تعديل ملف موظف' : 'موظف جديد' }}</h3>
              <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">البيانات تُحفظ في خادمك — التكامل الخارجي اختياري</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-700 dark:hover:text-white p-1" @click="closeModal">
              <XMarkIcon class="w-6 h-6" />
            </button>
          </div>

          <form class="flex-1 overflow-y-auto px-6 py-5 space-y-8" @submit.prevent="save">
            <!-- بيانات أساسية -->
            <section class="space-y-3">
              <h4 class="text-sm font-bold text-primary-700 dark:text-primary-300 border-b border-primary-100 dark:border-primary-900/50 pb-2">
                البيانات الأساسية
              </h4>
              <div class="grid sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                  <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">الاسم الكامل *</label>
                  <input
                    v-model="form.name"
                    required
                    class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-800 rounded-xl px-3 py-2 text-sm"
                  />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">رقم الهوية / الإقامة</label>
                  <input
                    v-model="form.national_id"
                    class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-800 rounded-xl px-3 py-2 text-sm font-mono"
                    placeholder="10 أرقام للمواطن"
                  />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">البريد الإلكتروني</label>
                  <input v-model="form.email" type="email" class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-800 rounded-xl px-3 py-2 text-sm" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">رقم الجوال</label>
                  <input v-model="form.phone" class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-800 rounded-xl px-3 py-2 text-sm" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">الحالة</label>
                  <select v-model="form.status" class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-800 rounded-xl px-3 py-2 text-sm">
                    <option value="active">نشط</option>
                    <option value="inactive">غير نشط</option>
                    <option value="suspended">موقوف</option>
                  </select>
                </div>
              </div>
            </section>

            <!-- الوظيفة والراتب -->
            <section class="space-y-3">
              <h4 class="text-sm font-bold text-primary-700 dark:text-primary-300 border-b border-primary-100 dark:border-primary-900/50 pb-2">
                الوظيفة والراتب
              </h4>
              <div class="grid sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">المسمى / التخصص</label>
                  <input v-model="form.position" class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-800 rounded-xl px-3 py-2 text-sm" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">القسم</label>
                  <input v-model="form.department" class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-800 rounded-xl px-3 py-2 text-sm" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">تاريخ التعيين</label>
                  <SmartDatePicker :model-value="form.hire_date" mode="single" @change="onHireDateChange" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">الراتب الأساسي (ر.س)</label>
                  <input v-model.number="form.base_salary" type="number" min="0" step="0.01" class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-800 rounded-xl px-3 py-2 text-sm" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">تاريخ إنهاء الخدمة</label>
                  <SmartDatePicker :model-value="form.termination_date" mode="single" @change="onTerminationDateChange" />
                </div>
                <div v-if="branches.length" class="sm:col-span-2">
                  <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">الفرع</label>
                  <select v-model.number="form.branch_id" class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-800 rounded-xl px-3 py-2 text-sm">
                    <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name_ar || b.name }}</option>
                  </select>
                </div>
                <div class="sm:col-span-2">
                  <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">المهارات (مفصولة بفاصلة)</label>
                  <input
                    v-model="form.skillsText"
                    class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-800 rounded-xl px-3 py-2 text-sm"
                    placeholder="مثال: تغيير زيت، ضبط زوايا، كهرباء"
                  />
                </div>
              </div>
            </section>

            <!-- قوى -->
            <section class="space-y-3">
              <h4 class="text-sm font-bold text-primary-700 dark:text-primary-300 border-b border-primary-100 dark:border-primary-900/50 pb-2 flex items-center gap-2">
                منصة قوى (Qiwa)
                <span class="text-[10px] font-normal text-gray-400">مرجع يدوي حتى ربط API</span>
              </h4>
              <div class="grid sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">مرجع الموظف في قوى</label>
                  <input v-model="form.hr_integrations.qiwa.employee_ref" class="w-full border rounded-xl px-3 py-2 text-sm font-mono dark:bg-slate-800 dark:border-slate-600" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">رقم المنشأة</label>
                  <input v-model="form.hr_integrations.qiwa.establishment_id" class="w-full border rounded-xl px-3 py-2 text-sm dark:bg-slate-800 dark:border-slate-600" />
                </div>
                <div class="sm:col-span-2 flex items-center gap-2">
                  <input :id="'qiwa-linked'" v-model="form.hr_integrations.qiwa.linked" type="checkbox" class="rounded border-gray-300" />
                  <label :for="'qiwa-linked'" class="text-sm text-gray-700 dark:text-slate-300">تم التحقق يدوياً من بيانات قوى</label>
                </div>
              </div>
            </section>

            <!-- GOSI -->
            <section class="space-y-3">
              <h4 class="text-sm font-bold text-primary-700 dark:text-primary-300 border-b border-primary-100 dark:border-primary-900/50 pb-2">
                التأمينات الاجتماعية (GOSI)
              </h4>
              <div class="grid sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">رقم الاشتراك</label>
                  <input v-model="form.hr_integrations.gosi.subscription_number" class="w-full border rounded-xl px-3 py-2 text-sm font-mono dark:bg-slate-800 dark:border-slate-600" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">الأجر المُبلَّغ (ر.س)</label>
                  <input v-model.number="form.hr_integrations.gosi.wage_reported" type="number" min="0" step="0.01" class="w-full border rounded-xl px-3 py-2 text-sm dark:bg-slate-800 dark:border-slate-600" />
                </div>
              </div>
            </section>

            <!-- تعاقد إلكتروني -->
            <section class="space-y-3">
              <h4 class="text-sm font-bold text-primary-700 dark:text-primary-300 border-b border-primary-100 dark:border-primary-900/50 pb-2">
                التعاقد الإلكتروني
              </h4>
              <div class="grid sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">معرّف العقد</label>
                  <input v-model="form.hr_integrations.e_contract.contract_id" class="w-full border rounded-xl px-3 py-2 text-sm font-mono dark:bg-slate-800 dark:border-slate-600" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">حالة العقد</label>
                  <select v-model="form.hr_integrations.e_contract.status" class="w-full border rounded-xl px-3 py-2 text-sm dark:bg-slate-800 dark:border-slate-600">
                    <option value="">—</option>
                    <option value="draft">مسودة</option>
                    <option value="pending_signature">بانتظار التوقيع</option>
                    <option value="signed">موقّع</option>
                    <option value="expired">منتهي</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">تاريخ التوقيع</label>
                  <SmartDatePicker :model-value="form.hr_integrations.e_contract.signed_at || ''" mode="single" @change="onSignedAtDateChange" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">مزوّد الخدمة</label>
                  <input v-model="form.hr_integrations.e_contract.provider" placeholder="مثال: نفاذ / طرف ثالث" class="w-full border rounded-xl px-3 py-2 text-sm dark:bg-slate-800 dark:border-slate-600" />
                </div>
              </div>
            </section>

            <!-- ملاحظات -->
            <section>
              <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">ملاحظات داخلية</label>
              <textarea
                v-model="form.internal_notes"
                rows="3"
                class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-800 rounded-xl px-3 py-2 text-sm"
                placeholder="ملاحظات لا تُرسل للجهات الخارجية"
              />
            </section>

            <div v-if="modalError" class="text-red-600 dark:text-red-400 text-sm bg-red-50 dark:bg-red-950/40 rounded-xl p-3 border border-red-100 dark:border-red-900/50">
              {{ modalError }}
            </div>

            <div class="flex gap-3 justify-end pt-2 border-t border-gray-100 dark:border-slate-700 sticky bottom-0 bg-white dark:bg-slate-900 pb-1">
              <button type="button" class="px-5 py-2.5 border border-gray-300 dark:border-slate-600 rounded-xl text-sm font-medium text-gray-700 dark:text-slate-200 hover:bg-gray-50 dark:hover:bg-slate-800" @click="closeModal">إلغاء</button>
              <button
                type="submit"
                :disabled="saving"
                class="px-6 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-semibold hover:bg-primary-700 disabled:opacity-50 shadow-sm"
              >
                {{ saving ? 'جاري الحفظ...' : 'حفظ الملف' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, reactive } from 'vue'
import {
  PlusIcon,
  XMarkIcon,
  BellAlertIcon,
  BuildingOffice2Icon,
  ShieldCheckIcon,
  DocumentTextIcon,
} from '@heroicons/vue/24/outline'
import ExcelImport from '@/components/ExcelImport.vue'
import apiClient from '@/lib/apiClient'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import SmartDatePicker from '@/components/ui/SmartDatePicker.vue'

interface HrInt {
  qiwa: { linked?: boolean; establishment_id?: string; employee_ref?: string }
  gosi: { subscription_number?: string; wage_reported?: number | null }
  e_contract: { contract_id?: string; status?: string; signed_at?: string; provider?: string }
}

function emptyHr(): HrInt {
  return {
    qiwa: { linked: false, establishment_id: '', employee_ref: '' },
    gosi: { subscription_number: '', wage_reported: null },
    e_contract: { contract_id: '', status: '', signed_at: '', provider: '' },
  }
}

const auth = useAuthStore()
const employees = ref<any[]>([])
const branches = ref<{ id: number; name: string; name_ar?: string }[]>([])
const integrationRun = ref<string | null>(null)
const integrationMessage = ref('')
const integrationLastKey = ref<string | null>(null)
const stats = ref({
  total: 0,
  active: 0,
  inactive: 0,
  suspended: 0,
  missing_national_id: 0,
  needs_hr_sync: 0,
})
const loading = ref(true)
const search = ref('')
const filterStatus = ref('')
const filterHr = ref('')
const viewTab = ref<'list' | 'integrations'>('list')
const showModal = ref(false)
const editId = ref<number | null>(null)
const saving = ref(false)
const modalError = ref('')

const form = reactive({
  name: '',
  phone: '',
  email: '',
  national_id: '',
  position: '',
  department: '',
  hire_date: '',
  termination_date: '',
  branch_id: 0 as number,
  base_salary: 0,
  skillsText: '',
  status: 'active' as 'active' | 'inactive' | 'suspended',
  internal_notes: '',
  hr_integrations: emptyHr(),
})

const viewTabs = [
  { key: 'list' as const, label: 'قائمة الموظفين' },
  { key: 'integrations' as const, label: 'دليل التكاملات' },
]

const integrationCards: {
  title: string
  body: string
  icon: typeof BuildingOffice2Icon
  filterTag: string
  pluginKey: string
}[] = [
  {
    title: 'قوى Qiwa',
    body: 'ربط العمالة والعقود مع وزارة الموارد البشرية. بعد اعتماد التكامل الرسمي، يمكن مزامنة بيانات الموظف والمنشأة تلقائياً.',
    icon: BuildingOffice2Icon,
    filterTag: 'qiwa',
    pluginKey: 'int_qiwa_workforce',
  },
  {
    title: 'التأمينات GOSI',
    body: 'مطابقة أجور الاشتراك والاشتراكات مع نظام GOSI. يُنصح بحفظ رقم الاشتراك والأجر المُبلَّغ يدوياً حتى اكتمال الربط.',
    icon: ShieldCheckIcon,
    filterTag: 'gosi',
    pluginKey: 'int_gosi_payroll',
  },
  {
    title: 'التعاقد الإلكتروني',
    body: 'تتبع حالة العقد الإلكتروني والتوقيع عبر مزوّدي الخدمة المعتمدين (مثل نفاذ). أدخل المعرّفات المرجعية من بوابة المزوّد.',
    icon: DocumentTextIcon,
    filterTag: 'hr',
    pluginKey: 'int_e_contract',
  },
]

const kpiCards = computed(() => [
  { key: 't', label: 'إجمالي الموظفين', value: stats.value.total, color: 'text-primary-600 dark:text-primary-400', hint: '' },
  { key: 'a', label: 'نشط', value: stats.value.active, color: 'text-emerald-600 dark:text-emerald-400', hint: '' },
  { key: 's', label: 'موقوف / غير نشط', value: stats.value.suspended + stats.value.inactive, color: 'text-amber-600 dark:text-amber-400', hint: 'يشمل غير النشط والموقوف' },
  {
    key: 'h',
    label: 'يحتاج متابعة HR',
    value: stats.value.needs_hr_sync,
    color: 'text-rose-600 dark:text-rose-400',
    hint: 'هوية أو تكامل ناقص',
  },
])

const smartAlerts = computed(() => {
  const a: string[] = []
  if (stats.value.missing_national_id > 0) {
    a.push(`يوجد ${stats.value.missing_national_id} موظف بدون رقم هوية/إقامة — مطلوب لأغلب التكاملات الرسمية.`)
  }
  if (stats.value.needs_hr_sync > 0) {
    a.push(`${stats.value.needs_hr_sync} ملفاً يحتاج إكمال بيانات قوى / GOSI / التعاقد الإلكتروني أو الهوية.`)
  }
  if (stats.value.total === 0) {
    a.push('ابدأ بإضافة موظف أو استيراد Excel من القسم أعلاه.')
  }
  return a
})

function needsHrAttention(emp: any): boolean {
  const h = emp.hr_integrations || {}
  const gosiOk = !!(h.gosi?.subscription_number ?? '').toString().trim()
  const qiwaOk = !!(h.qiwa?.employee_ref ?? '').toString().trim()
  const eOk = !!(h.e_contract?.contract_id ?? '').toString().trim()
  const idOk = !!(emp.national_id ?? '').toString().trim()
  return !idOk || !gosiOk || !qiwaOk || !eOk
}

const filtered = computed(() =>
  employees.value.filter((e) => {
    const q = search.value.trim().toLowerCase()
    const matchQ =
      !q ||
      (e.name && String(e.name).toLowerCase().includes(q)) ||
      (e.position && String(e.position).toLowerCase().includes(q)) ||
      (e.employee_number && String(e.employee_number).toLowerCase().includes(q)) ||
      (e.national_id && String(e.national_id).includes(q))
    const matchS =
      !filterStatus.value ||
      (filterStatus.value === 'active' ? e.status === 'active' : filterStatus.value === 'inactive' ? e.status === 'inactive' : e.status === 'suspended')
    const matchHr =
      !filterHr.value ||
      (filterHr.value === 'needs' ? needsHrAttention(e) : filterHr.value === 'ok' ? !needsHrAttention(e) : true)
    return matchQ && matchS && matchHr
  }),
)

function integrationBadges(emp: any) {
  const h = emp.hr_integrations || {}
  return [
    { key: 'q', label: 'قوى', ok: !!(h.qiwa?.employee_ref || h.qiwa?.linked) },
    { key: 'g', label: 'تأمينات', ok: !!h.gosi?.subscription_number },
    { key: 'e', label: 'عقد', ok: !!h.e_contract?.contract_id },
  ]
}

function statusLabel(s: string) {
  const m: Record<string, string> = { active: 'نشط', inactive: 'غير نشط', suspended: 'موقوف' }
  return m[s] ?? s
}

function statusClass(s: string) {
  if (s === 'active') return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300'
  if (s === 'suspended') return 'bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-300'
  return 'bg-gray-100 text-gray-700 dark:bg-slate-700 dark:text-slate-300'
}

function formatNum(n: number) {
  return Number(n || 0).toLocaleString('ar-SA')
}

function branchLabel(branchId: number | null | undefined): string {
  if (branchId == null) return '—'
  const b = branches.value.find((x) => x.id === branchId)
  return b?.name_ar || b?.name || `#${branchId}`
}

function skillPreview(emp: any): string {
  const s = emp?.skills
  if (!Array.isArray(s) || !s.length) return ''
  return s.slice(0, 4).join(' · ') + (s.length > 4 ? '…' : '')
}

function mergeHr(raw: any): HrInt {
  const e = emptyHr()
  if (!raw || typeof raw !== 'object') return e
  Object.assign(e.qiwa, raw.qiwa || {})
  Object.assign(e.gosi, raw.gosi || {})
  Object.assign(e.e_contract, raw.e_contract || {})
  return e
}

async function loadBranches() {
  try {
    const res = await apiClient.get('/branches', { params: { per_page: 100 } })
    const p = res.data?.data
    branches.value = Array.isArray(p?.data) ? p.data : []
  } catch {
    branches.value = []
  }
}

async function runIntegrationReport(pluginKey: string) {
  integrationRun.value = pluginKey
  integrationMessage.value = ''
  integrationLastKey.value = pluginKey
  try {
    const { data } = await apiClient.post(`/plugins/${pluginKey}/execute`, {})
    const d = data?.data ?? {}
    const lines: string[] = []
    if (typeof d.message === 'string') lines.push(d.message)
    if (d.employees_total != null) lines.push(`إجمالي الموظفين: ${d.employees_total}`)
    if (d.with_qiwa_ref != null) lines.push(`بمرجع قوى: ${d.with_qiwa_ref} — بدون: ${d.missing_qiwa_ref}`)
    if (d.with_subscription_no != null) lines.push(`برقم اشتراك GOSI: ${d.with_subscription_no}`)
    if (d.with_contract_id != null) lines.push(`بمرجع عقد: ${d.with_contract_id}`)
    integrationMessage.value = lines.length ? lines.join('\n') : JSON.stringify(d, null, 2)
  } catch (e: any) {
    const msg = e?.response?.data?.message
    integrationMessage.value =
      typeof msg === 'string' ? msg : 'ثبّت الإضافة من «سوق الإضافات» ثم نفّذ التقرير مرة أخرى.'
  } finally {
    integrationRun.value = null
  }
}

async function loadStats() {
  try {
    const res = await apiClient.get('/workshop/employees/stats')
    const d = res.data?.data ?? res.data
    if (d) stats.value = { ...stats.value, ...d }
  } catch {
    /* ignore */
  }
}

async function load() {
  loading.value = true
  try {
    await loadBranches()
    const res = await apiClient.get('/workshop/employees', { params: { per_page: 100 } })
    const payload = res.data
    employees.value = Array.isArray(payload?.data) ? payload.data : []
    await loadStats()
  } finally {
    loading.value = false
  }
}

async function refreshAll() {
  await load()
}

function openCreate() {
  editId.value = null
  modalError.value = ''
  const defBr = auth.user?.branch_id ?? branches.value[0]?.id ?? 0
  Object.assign(form, {
    name: '',
    phone: '',
    email: '',
    national_id: '',
    position: '',
    department: '',
    hire_date: '',
    termination_date: '',
    branch_id: defBr,
    base_salary: 0,
    skillsText: '',
    status: 'active',
    internal_notes: '',
    hr_integrations: emptyHr(),
  })
  showModal.value = true
}

function openEdit(emp: any) {
  editId.value = emp.id
  modalError.value = ''
  const skillArr = emp.skills
  const skillsText = Array.isArray(skillArr) ? skillArr.filter(Boolean).join(', ') : ''
  Object.assign(form, {
    name: emp.name ?? '',
    phone: emp.phone ?? '',
    email: emp.email ?? '',
    national_id: emp.national_id ?? '',
    position: emp.position ?? '',
    department: emp.department ?? '',
    hire_date: emp.hire_date ? String(emp.hire_date).slice(0, 10) : '',
    termination_date: emp.termination_date ? String(emp.termination_date).slice(0, 10) : '',
    branch_id: Number(emp.branch_id) || auth.user?.branch_id || branches.value[0]?.id || 0,
    base_salary: Number(emp.base_salary) || 0,
    skillsText,
    status: (emp.status as 'active' | 'inactive' | 'suspended') || 'active',
    internal_notes: emp.internal_notes ?? '',
    hr_integrations: mergeHr(emp.hr_integrations),
  })
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editId.value = null
  modalError.value = ''
}

function onHireDateChange(val: { from: string; to: string }) {
  form.hire_date = val.from || val.to
}

function onTerminationDateChange(val: { from: string; to: string }) {
  form.termination_date = val.from || val.to
}

function onSignedAtDateChange(val: { from: string; to: string }) {
  form.hr_integrations.e_contract.signed_at = val.from || val.to
}

function payloadFromForm() {
  const hr = JSON.parse(JSON.stringify(form.hr_integrations))
  const skills = form.skillsText
    .split(/[,،]/)
    .map((s) => s.trim())
    .filter(Boolean)
  const body: Record<string, unknown> = {
    name: form.name,
    phone: form.phone || null,
    email: form.email || null,
    national_id: form.national_id || null,
    position: form.position || null,
    department: form.department || null,
    hire_date: form.hire_date || null,
    termination_date: form.termination_date || null,
    base_salary: form.base_salary,
    status: form.status,
    internal_notes: form.internal_notes || null,
    hr_integrations: hr,
    skills,
  }
  if (branches.value.length && form.branch_id) {
    body.branch_id = form.branch_id
  }
  return body
}

async function save() {
  saving.value = true
  modalError.value = ''
  try {
    const body = payloadFromForm()
    if (editId.value) {
      await apiClient.put(`/workshop/employees/${editId.value}`, body)
    } else {
      await apiClient.post('/workshop/employees', body)
    }
    await load()
    closeModal()
  } catch (e: any) {
    modalError.value = e?.response?.data?.message ?? e?.message ?? 'حدث خطأ'
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>
