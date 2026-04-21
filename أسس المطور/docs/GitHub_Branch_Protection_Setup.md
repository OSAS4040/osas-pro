# إعداد حماية الفرع `main` على GitHub

هذا المستند **إجراء تنفيذي** لمسؤولي المستودع — إعدادات الحماية نفسها **لا تُخزَّن في git**؛ تُطبَّق من واجهة GitHub (أو Rulesets على مستوى المنظمة). المستودع يوفّر أداة **قراءة فقط** للتحقق بعد الضبط: `make github-branch-protection-status` (تتطلب [GitHub CLI](https://cli.github.com/) و`gh auth login`).

## المتطلبات

- صلاحية **Admin** على المستودع (أو سياسة المنظمة تسمح بحماية الفروع).
- أن يكون workflow [**Policy env on PR**](../.github/workflows/policy-env-on-pr.yml) قد **عُرِضَ على الأقل مرة** على PR نحو `main` حتى يظهر اسم الفحص في قائمة **Required status checks** عادة بصيغة **`Policy env on PR / policy-env-example`**.
- لتجربة الـ workflow دون PR: **Actions** → **Policy env on PR** → **Run workflow** (`workflow_dispatch` — لا يغني عن تشغيل الفحص على PR لدمج آمن).
- (اختياري) تشغيل [**Staging gate**](../.github/workflows/staging-gate.yml) عبر PR يطابق المسارات أو `workflow_dispatch` لإضافة **`Staging gate / staging-gate`** إن رغبتم بجعله required مع فهم قيود المسارات.

## مساران في واجهة GitHub

### أ) حماية كلاسيكية (Branches)

1. المستودع → **Settings** → **Branches** → **Add branch protection rule** (أو تعديل القاعدة الحالية لـ `main`).
2. **Branch name pattern:** `main`

### ب) قواعد المستودع — Rulesets (موصى به في منظمات حديثة)

1. المستودع → **Settings** → **Rules** → **Rulesets** → **New ruleset** (أو تعديل قاعدة موجودة).
2. **Target branches:** قيد `main` أو الفرع الافتراضي (`Include default branch` إن كان `main`).
3. أضف قاعدة النوع **Require status checks to pass** واختر نفس أسماء الفحوص أدناه بعد أول تشغيل ناجح.

السلوك الأمني المطلوب متطابق: PR إلزامي، فحوص تنجح، منع force push، وتضييق الاستثناءات (bypass).

## الخطوات (مختصرة)

1. اتبع **(أ)** أو **(ب)** أعلاه حسب ما تستخدمه المنظمة.
2. تفعيل:
   - **Require a pull request before merging** (مع عدد الموافقات حسب سياسة الفريق).
   - **Require status checks to pass before merging**
   - **Require branches to be up to date before merging** (مُستحسن).
3. في **Status checks that are required** (أو قاعدة Ruleset المعادلة)، أضف — حسب ما يظهر بعد أول تشغيل ناجح للـ workflow:

   | أولوية | اسم الفحص في واجهة GitHub (مثال شائع) | ملاحظة |
   |--------|----------------------------------------|--------|
   | **إلزامي (مُنصح)** | **`Policy env on PR / policy-env-example`** | يعمل على **كل** PR نحو `main` (workflow [policy-env-on-pr.yml](../.github/workflows/policy-env-on-pr.yml)) — خفيف وآمن كشرط دائم |
   | اختياري | `Staging gate / staging-gate` | يظهر فقط عندما يُشغَّل workflow [staging-gate.yml](../.github/workflows/staging-gate.yml) (مسارات محددة). **لا تجعله required** إن كان يتخطى بعض الـ PRs فيُعطّل الدمج؛ أو وسّع المسارات في الـ workflow، أو اعتمد على التشغيل المحلي + القالب |
   | اختياري | `Staging gate / frontend-test-ci` | نفس الـ workflow [staging-gate.yml](../.github/workflows/staging-gate.yml) — Vitest + Playwright على `ubuntu-latest`؛ يُنصح بجعله required فقط إن كان يُشغَّل على كل PRs ذات صلة (أو بعد توسيع `paths`) |

   قد تختلف الصيغة قليلاً حسب واجهة GitHub؛ اختر المدخلات التي تطابق jobs الـ workflow عندكم.

4. تفعيل **Do not allow bypassing the above settings** (أو تضييق **Bypass list** في Rulesets) حتى لا يُتجاوَز الشرط إلا لأدوار محدودة مع موافقة إدارية.
5. **Block force pushes** إلى `main`.
6. **Restrict who can push to matching branches** — اترك الدمج عبر PR فقط (لا push مباشر لفريق التطوير العادي إن أمكن).
7. (مُستحسن) بعد الحفظ: من جذر المستودع نفّذ `gh auth login` ثم **`make github-branch-protection-status`** — يجب ألا يخرج برمز خطأ وأن يُظهر تضمين فحص السياسة.

## ملاحظات

- إذا **لم يُشغَّل** workflow على PR (خارج `paths` في الملف)، **لا تظهر** checks — يبقى الالتزام بتشغيل **`make policy-env-example`** و **`make staging-gate`** محلياً وتوثيق ذلك في الـ PR (انظر القالب).
- workflow **Deploy with gate** على `push` إلى `main` منفصل؛ حماية `main` تمنع الدمج بدون checks، والـ deploy يعمل بعد الدمج الناجح.

## التحقق

- افتح PR تجريبياً: يجب أن يظهر انتظار **checks** وعدم إمكانية الدمج حتى تنجح.
- حاول push مباشراً إلى `main` (يجب أن يُرفض إن كانت القواعد مفعّلة).
- `make github-branch-protection-status` — يقرأ إعداد الحماية عبر API (قراءة فقط؛ لا يغيّر شيئاً على GitHub).

## بعد الانتهاء

انتقل إلى **المرحلة 2** وما يليها في [`Execution_Order_Asas_Pro.md`](./Execution_Order_Asas_Pro.md).
