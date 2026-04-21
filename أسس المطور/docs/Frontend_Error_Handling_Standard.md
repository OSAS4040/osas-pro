# Frontend error handling & loading — standard

**آخر تحديث:** 2026-04-05  
**النطاق:** واجهة Vue (`frontend/`) — بدون تغيير عقود الـ API.

## 1) طبقة `apiClient` (Axios)

- **Interceptor للاستجابة:** يعرض Toast موحّدًا لحالات HTTP الشائعة بعد **تعريب** حمولة الخطأ (`localizeApiErrorPayload`).
- **401 (غير مسار تسجيل الدخول):** مسح التوكن وإعادة التوجيه إلى `/login` (جلسة منتهية).
- **403:** Toast تحذير — عدم الصلاحية.
- **404:** Toast تحذير — المورد غير موجود / غير متاح.
- **409:** Toast تحذير — تعارض (تحديث متزامن).
- **422:** Toast تحذير — تحقق من الحقول (أول رسالة + تسمية الحقل عند الإمكان).
- **402 / 423:** اشتراك / وضع قراءة فقط.
- **429:** كثرة الطلبات.
- **503:** خدمة غير متاحة؛ حالة خاصة لفشل ترحيل دفتر الأستاذ مع نص واضح.
- **≥500:** Toast خطأ — رسالة آمنة للمستخدم (لا تُعرض رسائل تقنية مثل SQL/Stack).
- **بدون `response`:** شبكة أو **انتهاء مهلة** (`ECONNABORTED` / timeout) — نص منفصل عن «تعذّر الاتصال».

## 2) تجنب ازدواجية Toast

- خيار الطلب: **`skipGlobalErrorToast: true`** (على كائن إعدادات Axios الثالث).
- يُستخدم عندما تعرض الشاشة الخطأ **inline** (بانر/نموذج) أو Toast **واحد** محلي، مع `summarizeAxiosError()` من `@/utils/apiErrorSummary`.

أمثلة في المشروع: تسجيل الدخول (`auth/login`)، إتمام بيع POS، إنشاء فاتورة، دفع/استرداد فاتورة، انتقال حالة أمر عمل، إلخ.

## 3) `summarizeAxiosError(err)`

- يُرجع نصًا واحدًا مناسبًا للواجهة: شبكة، مهلة، 403/404/409/422، 5xx مع تصفية الرسائل التقنية.
- يُفضّل استخدامه في `catch` بعد الطلبات ذات `skipGlobalErrorToast`.

## 4) قواعد التحميل وتعطيل الأزرار

- **إجراءات إرسال (POST/PATCH حرجة):** `loading`/`saving`/`processing` + `:disabled` على الزر + في الدالة `if (flag) return` لمنع النقر المزدوج.
- **نجاح:** إعادة تعيين النموذم أو التوجيه؛ **فشل:** الإبقاء على البيانات مع رسالة واضحة.

## 5) ممنوع في واجهة المستخدم النهائية

- عرض stack traces أو SQL أو أسماء ملفات خادم.
- `catch` فارغ للعمليات التي تؤثر على إتمام إجراء المستخدم (ما عدا أماكن مقصودة مثل قراءة `localStorage`).

## 6) مراجع كود

| الملف | الدور |
|--------|--------|
| `frontend/src/lib/apiClient.ts` | Interceptors + Toasts |
| `frontend/src/utils/apiErrorSummary.ts` | `summarizeAxiosError`, تصفية الرسائل التقنية |
| `frontend/src/utils/runtimeLocale.ts` | `localizeBackendMessage`, `uiByLang` |
| `frontend/src/types/axios-augment.d.ts` | `skipGlobalErrorToast` على `AxiosRequestConfig` |
| `frontend/src/composables/useToast.ts` | عرض الرسائل |
