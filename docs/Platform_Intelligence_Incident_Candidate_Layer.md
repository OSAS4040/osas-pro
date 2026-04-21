# Platform Intelligence — Incident Candidate Layer (مرحلة مرشّحات الحوادث)

## 1) هدف المرحلة

بناء **طبقة مرشّحات تشغيلية** رسمية فوق **محرك الإشارات (Priority 7)**، بحيث تُحوَّل الإشارات المعتمدة (`PlatformSignalContract`) إلى **عقد مرشّح** (`PlatformIncidentCandidateContract`) جاهز لاحقًا لمرحلة **Incident Center MVP**، دون إنشاء حادث مُدار، دون دورة حياة، ودون قرارات أو أوامر تنفيذ.

## 2) حدود المرحلة (غير قابلة للتجاوز)

| مسموح | ممنوع |
|--------|--------|
| قراءة إشارات عبر `PlatformSignalEngine` ثم اشتقاق مرشّحات | قراءة overview خام من الواجهة أو إعادة اكتشاف إشارات خارج المحرك |
| مخرج `PlatformIncidentCandidateContract` فقط | `PlatformIncident` فعلي، Decision Log، Incident Center، أي mutations تحت intelligence |
| توصيات إرشادية نصية فقط | acknowledge / assign / escalate / resolve / close / snooze / mute أو أي واجهة دورة حياة |
| تتبع تحليلي خفيف (`candidate_*`) | فيض تتبع أو audit تشغيلي نهائي |
| GET ` /api/v1/platform/intelligence/incident-candidates` | POST / PATCH / DELETE لمسارات `platform/intelligence/*` |

لا يُمس: Ledger / Wallets / Posting / Reconciliation أو أي منطق مالي.

## 3) المدخلات الرسمية

- **الوحيد:** قائمة `PlatformSignalContract` الناتجة من `PlatformSignalEngine::build()` في نفس طلب الـ API (لا استعلامات موازية لإعادة بناء الإشارات داخل المحرك).

## 4) المخرجات الرسمية

- **الوحيد:** قائمة `PlatformIncidentCandidateContract` مُسلسلة عبر `PlatformIncidentCandidateContractSerializer`.
- **Meta:** `candidate_rules_version`, `candidate_rules_release_date`, `candidate_rules_changelog`, `candidate_order`.

## 5) Eligibility Rules (أهلية الإشارة)

تُقيَّم كل إشارة قبل التجميع:

1. `confidence >= 0.38` (أي إشارة أضعف تُستبعد).
2. يجب وجود واحد على الأقل من: `affected_companies` غير فارغ، أو `affected_entities` غير فارغ، أو `correlation_keys` غير فارغة بعد التقليم.
3. إذا `severity === info`:  
   - يجب وجود `correlation_keys` فعلية.  
   - `confidence >= 0.52`.  
   - يجب وجود شركات متأثرة **أو** كيانات متأثرة (لا يُقبل info بدون مرساة تشغيلية).

**الملف:** `CandidateEligibilityEvaluator.php`

## 6) Grouping Rules (تجميع الإشارات)

Union-Find على الإشارات المؤهّلة؛ يُنشأ حد بين `i` و`j` إذا تحقق **أحد** الشروط:

1. **تقاطع `correlation_keys`** بعد تطبيع القيم (trim، تجاهل الفارغ).
2. **نفس `affected_scope` ونفس `signal_type` وتقاطع شركات ≥ 1**.
3. **نفس `source` (نطاق المصدر) وتقاطع شركات ≥ 2**.

لا يُستخدم ترتيب الإدخال العشوائي؛ تُفرَز المجموعات حسب أصغر `signal_key` معنويًا.

**الملف:** `CandidateGroupingService.php`

## 7) Candidate Identity Policy

- **`incident_key`:** `icand_` + `sha256` لحملة ثابتة: salt الإصدار + مفاتيح الإشارات المرتبة + جميع مفاتيح الارتباط (فريدة مرتبة) + مرساة النطاق (أول `affected_scope` معجميًا) + مرساة نوع الإشارة + مرساة المصدر. **لا** يعتمد على وقت التنفيذ اللحظي فقط.
- **`dedupe_fingerprint`:** `sha256` على مفاتيح الإشارات + النطاق + المصدر السائد + شدة المرشّح + عدد الشركات الفريدة — لقمع التكرار الشكلي بعد التجميع.
- **`incident_type`:**  
  - `candidate.shared_correlation` إن وُجد تقاطع مفاتيح ارتباط مشترك بين **كل** أعضاء المجموعة.  
  - وإلا `candidate.multi_signal_overlap` إذا >1 إشارة.  
  - وإلا `candidate.single_signal`.

**الملف:** `CandidateIdentityBuilder.php`

## 8) Severity Rollup Rules

1. ابدأ بأعلى شدة بين أعضاء المجموعة.
2. إذا كانت الرتبة < critical وعدد الإشارات ≥ 6: ارفع درجة واحدة كحد أقصى.
3. إذا كانت الرتبة < critical وعدد الشركات الفريدة ≥ 12: ارفع درجة واحدة كحد أقصى.
4. إذا كانت الشدة القصوى `info` وعدد الإشارات ≥ 3 ومتوسط الثقة ≥ 0.55: ارفع إلى `low` (منع بقاء نمط متكرر دون وزن تشغيلي).

**الملف:** `CandidateSeverityRollup.php` — النص العربي للتفسير في `rationaleAr()`.

## 9) Confidence Rollup Rules

- متوسط ثقة الإشارات + علاوة دعم `min(0.12, 0.03*(n-1))`.
- خصم 0.05 عند تشتت ثقة عالٍ (`variance > 0.04`).
- علاوة +0.02 عند وجود ≥2 مفتاح ارتباط مشترك بين جميع الأعضاء.
- خصم 0.03 عند تعدد ≥4 نطاقات `affected_scope` داخل المجموعة.
- تقريب إلى 4 خانات عشرية، حد أعلى **0.94**.

**الملف:** `CandidateConfidenceRollup.php`

## 10) Noise Suppression Rules

1. **قمع مرشّح info مفرد** بدون أي `affected_companies` (إشارات معلوماتية بلا مرساة مستأجر في بُعد الشركة).
2. **إزالة تكرار `dedupe_fingerprint`** مع الإبقاء على أول مرشّح مرتّب معجميًا بعد الفرز.

**الملف:** `CandidateSuppressionService.php`

## 11) Explainability Contract

لكل مرشّح:

- **`grouping_reason`:** فقرة عربية صريحة (تقاطع مفاتيح الارتباط أو تقاطع نطاق/نوع/شركات).
- **`why_summary`:** يتضمن: لماذا مرشّح واحد، تفسير الشدة، تفسير الثقة، ولماذا لم تُترك الإشارات منفصلة عندما يكون التجميع >1.
- **`summary`:** قائمة نقطية بالإشارات المساهمة مع نوعها وشدتها وثقتها.

**الملف:** `IncidentCandidateExplainabilityComposer.php`

## 12) Recommended Actions Policy

قائمة نصية **إرشادية فقط** (مراجعة، مراقبة لاحقة، جاهزية لتحويل لاحق عند استمرار النمط، مراجعة الشركات المتأثرة).  
لا صياغة تلمّح بتنفيذ أو إغلاق أو تصعيد فعلي من النظام.

**الملف:** `IncidentCandidateRecommendedActionsPolicy.php`

## 13) العلاقة مع Incident Center القادمة

- `incident_key` و`dedupe_fingerprint` مصممان لتكون مراسي تسجيل حادث لاحق.
- `incident_type` و`source_signals` يوفّران أثرًا قابلًا للتدقيق عند ربط مرحلة الـ MVP.
- لا يُفترض وجود `owner` أو `status` قبل تفعيل طبقة الحوادث.

## 14) معايير الإغلاق

1. محرك رسمي `PlatformIncidentCandidateEngine::buildFromSignals`.
2. مدخل وحيد من عقد الإشارة المعتمد.
3. مخرج وحيد من عقد المرشّح المعتمد.
4. قواعد أهلية/تجميع/rollup/قمع موثّقة ومغطاة باختبارات وحدة.
5. GET محمي بـ `platform.intelligence.candidates.read` + صلاحية قدرة `view_incident_candidates`.
6. تتبع `candidate_derived` … `candidate_explained` دون فيض.
7. واجهة قراءة محدودة في SPA مع نفس البوابة.

## 15) بنود منع الانتقال إلى Incident Center MVP

لا تُفتح مرحلة Incident Center قبل:

- استقرار `incident_key` تحت إعادة ترتيب الإشارات (مختبر).
- نضوج القمع دون فقدان مرشّحات ذات شركات متأثرة.
- جودة تفسيرية (`grouping_reason` / `why_summary`) مقبولة تشغيليًا.
- بقاء المنظومة خالية من mutations جديدة تحت `platform/intelligence` (يُرصد في `PlatformIntelligenceGuardrailTest`).

---

**API:** `GET /api/v1/platform/intelligence/incident-candidates`  
**صلاحية IAM:** `platform.intelligence.candidates.read` — **قدرة:** `PlatformIntelligenceCapability::ViewIncidentCandidates`  
**إصدار القواعد:** `PlatformIncidentCandidateRulesVersion::VERSION`
