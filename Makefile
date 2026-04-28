# ترتيب التنفيذ الرسمي (مرقم): docs/Execution_Order_Asas_Pro.md
.PHONY: up down build fresh logs logs-all shell migrate seed seed-simulate key tinker queue-restart swagger dompdf-arabic-check test test-filter test-coverage test-frontend-full fe-phases fe-phases-with-e2e test-project-gate staging-gate staging-gate-ps ocr-verify preflight-pilot-readonly production-readiness-gate policy-env-example github-branch-protection-status execution-order-local execution-order-local-ps install-git-hooks verify release-gate monitoring-gate integrity-verify load-test load-test-preflight load-test-matrix load-test-release-gate load-test-rc-secure-gate load-test-capacity-discovery load-test-capacity-one ps install ngrok-up ngrok-down ngrok-url up-ngrok dev-bootstrap

up:
	docker compose up -d
	@$(MAKE) dev-bootstrap

# بعد التشغيل: migrations دائماً؛ seed الديمو فقط عند APP_ENV=local (آمن لـ staging).
# Windows بدون make: pwsh -File scripts/dev-bootstrap.ps1 | bash scripts/dev-bootstrap.sh
dev-bootstrap:
	docker compose exec -T app php artisan migrate --force
	docker compose exec -T app php artisan dev:demo-seed

# تشغيل الخدمات الأساسية + نفق ngrok (يتطلب `.env` مع NGROK_AUTHTOKEN؛ NGROK_DOMAIN اختياري للدومين الثابت)
up-ngrok:
	docker compose up -d postgres redis app queue_default frontend nginx
	docker compose --profile ngrok up -d ngrok

# نفق ngrok فقط (بعد تشغيل frontend مسبقاً)
ngrok-up:
	docker compose --profile ngrok up -d ngrok

ngrok-down:
	docker compose --profile ngrok stop ngrok || true

# اطبع الرابط العام وسطر VITE_DEV_PUBLIC_HOST (واجهة المفتش على localhost:4040)
ngrok-url:
	node scripts/show-ngrok-url.mjs

down:
	docker compose down

build:
	docker compose build --no-cache

fresh: down
	docker compose up -d --build
	docker compose exec app php artisan migrate:fresh --seed

logs:
	docker compose logs -f app

logs-all:
	docker compose logs -f

shell:
	docker compose exec app sh

migrate:
	docker compose exec app php artisan migrate

seed:
	docker compose exec app php artisan db:seed

# Demo users only (bypasses container resolution issues with db:seed --class in some Docker setups)
seed-demo:
	docker compose exec app php artisan workshop:seed-demo

# Full DB seed then load simulation (avoids accidental: php artisan db:seeddocker ...)
seed-simulate:
	docker compose exec app php artisan db:seed
	docker compose exec app php artisan simulation:stress --customers=20 --batches=1

key:
	docker compose exec app php artisan key:generate

tinker:
	docker compose exec app php artisan tinker

queue-restart:
	docker compose restart queue_high queue_default queue_pos queue_low

swagger:
	docker compose exec app php artisan l5-swagger:generate

# جاهزية خط PDF العربي (Dompdf + Noto + storage/fonts) — بعد النشر أو عند تشخيص مشاكل العرض
dompdf-arabic-check:
	docker compose exec -T app php artisan dompdf:check-arabic-font

# PHPUnit مباشرة + مسح كاش الإعدادات (يتفادى تعارض ‎DB_HOST‎ مع ‎php artisan test‎ في بعض الحاويات)
# ملاحظة: ‎--parallel‎ يتطلب brianium/paratest — غير مثبت حالياً؛ استخدم ‎test-parallel‎ بعد إضافته إن لزم.
test:
	docker compose exec -T app sh -lc "cd /var/www && php artisan config:clear && ./vendor/bin/phpunit"

# واجهة كاملة على المضيف: ESLint + vue-tsc + Vitest + Playwright (هبوط/دخول/عامة). يتطلب: Chromium (`cd frontend && npm run test:e2e:install`).
test-frontend-full:
	cd frontend && npm run test:ci

# نفس `frontend-phase-gates` في CI: Vitest حسب المراحل 0–6 (يفترض `npm install` أو `npm ci` في frontend مسبقاً)
# Windows بدون make: pwsh -File scripts/fe-phases.ps1
fe-phases:
	cd frontend && npm run test:phases:fe

# Vitest 0–6 ثم Playwright المرحلة 7 (يحتاج `npm run test:e2e:install` في الواجهة)
# Windows: pwsh -File scripts/fe-phases-with-e2e.ps1
fe-phases-with-e2e:
	cd frontend && npm run test:phases:fe:with-e2e

# بوابة مشروع: واجهة كاملة ثم PHPUnit داخل Docker
test-project-gate: test-frontend-full
	docker compose exec -T app sh -lc "cd /var/www && php artisan config:clear && ./vendor/bin/phpunit"

# فحص سريع بعد نشر staging — Vitest + PHPUnit مراحل 0–7 + `php artisan ocr:verify --fail` (Tesseract)
# يتطلب: docker compose up -d — نفس المنطق: bash scripts/staging-gate.sh (لـ CI/Linux وGitHub Actions)
staging-gate:
	bash scripts/staging-gate.sh

# Windows (PowerShell): نفس محتوى staging-gate.sh دون Git Bash
staging-gate-ps:
	pwsh -NoProfile -ExecutionPolicy Bypass -File scripts/staging-gate.ps1

# تحقق سريع من Tesseract (eng+ara) داخل حاوية app — يتطلب docker compose up -d
ocr-verify:
	docker compose exec -T app sh -lc "cd /var/www && php artisan ocr:verify --fail"

# فحص قراءة فقط قبل Pilot (سياسة env + health + اختياري OCR) — Linux/macOS: bash scripts/preflight-pilot-readonly.sh
preflight-pilot-readonly:
	bash scripts/preflight-pilot-readonly.sh

# بوابة جاهزية إنتاج آلية كاملة (تنظيف، بناء، اختبارات، k6 enterprise، integrity، artifact no-dev).
# على Windows: pwsh -File scripts/osas-pro-production-readiness-gate.ps1
production-readiness-gate:
	bash scripts/osas-pro-production-readiness-gate.sh

# تحقق سياسة قوالب env (جذر + backend + frontend + load-testing + حزم النشر) — لا يحتاج Docker (Node فقط)
policy-env-example:
	node scripts/check-policy-env-example.mjs

# المرحلة 1 — قراءة فقط من GitHub API: هل main محمي وفيه فحص Policy env (يتطلب gh auth)
github-branch-protection-status:
	node scripts/gh-branch-protection-status.mjs

# المراحل 0→5 (محلي): سياسة env ثم تذكير ترتيبي لـ GitHub / Staging / الإنتاج (انظر scripts/execution-order-local-hint.mjs)
execution-order-local: policy-env-example
	node scripts/execution-order-local-hint.mjs

# Windows بدون make: نفس المنطق (powershell 5.1+؛ على macOS/Linux يُفضّل pwsh إن وُجد)
ifeq ($(OS),Windows_NT)
execution-order-local-ps:
	powershell -NoProfile -ExecutionPolicy Bypass -File scripts/execution-order-local.ps1
else
execution-order-local-ps:
	pwsh -NoProfile -ExecutionPolicy Bypass -File scripts/execution-order-local.ps1
endif

# المرحلة 5 — خطاف git اختياري: فحص policy عند تعديل backend/.env*.example أو frontend/env*.example (يضبط core.hooksPath=.githooks)
install-git-hooks:
	git config core.hooksPath .githooks

# بوابة ما قبل الإنتاج: مسار عميل كامل + سلامة بيانات + idempotency خفيف (انظر tests/Feature/PreProduction)
# واجهة المستخدم: تحقق يدوي (زمن تحميل <1.5s، لا شاشات بيضاء) — لا يغطيه PHPUnit.
test-preprod:
	docker compose exec -T app php artisan test --group=pre-production

# فحص سريع: اتصال DB + أعمدة IAM المنصة + جدول platform_audit_logs
integrity-sanity:
	docker compose exec -T app php artisan integrity:sanity

# فحص سلامة البيانات (فاتورة↔قيود، مخزون، محفظة، تكرار) — يخرج 1 عند وجود مخالفات
integrity-verify:
	docker compose exec -T app php artisan integrity:verify

# Mandatory pre-delivery / pre-merge gate (requires stack: `make up` or equivalent).
# يطابق خطوة CI: ‎npm ci‎ ثم lint وبناء الإنتاج — ثم PHPUnit كاملاً.
verify:
	docker compose exec -T frontend sh -lc "cd /app && npm ci && npm run lint:check && npm run build"
	docker compose exec -T app sh -lc "cd /var/www && php artisan config:clear && ./vendor/bin/phpunit"

# بوابة إصدار واحدة متكاملة: جودة الواجهة + كل اختبارات PHPUnit + سلامة بيانات التشغيل + مراقبة الخدمات.
# pre-production مضمّن ضمن `php artisan test` أعلاه؛ لا حاجة لتكرار test-preprod هنا.
# يتوقف عند أول فشل. monitoring-gate يحتاج bash (Git Bash على Windows) و curl يصل إلى CHECK_BASE_URL.
release-gate: verify integrity-verify monitoring-gate

monitoring-gate:
	FAIL_ON_FAILED_JOBS=1 CHECK_BASE_URL=$${CHECK_BASE_URL:-http://127.0.0.1} bash ./check.sh

# k6 — خط أساس ثابت عبر K6_PROFILE: smoke | normal | peak | verification | stress | spike | soak
# مثال: make load-test K6_PROFILE=peak  أو  make load-test K6_PROFILE=verification
K6_PROFILE ?= smoke
load-test:
	docker run --rm \
		-v "$(CURDIR)/load-testing:/work" \
		-w /work/k6 \
		--add-host=host.docker.internal:host-gateway \
		-e K6_BASE_URL=http://host.docker.internal/api \
		-e K6_PROFILE=$(K6_PROFILE) \
		-e K6_EMAIL_A=simulation.owner@demo.local \
		-e K6_PASSWORD_A=SimulationDemo123! \
		-e K6_EMAIL_B=owner@demo.sa \
		-e K6_PASSWORD_B=password \
		grafana/k6:latest run suite.js

# Smoke: health + dual login only (يُستخدم للتحقق من البذور والشبكة قبل الضغط الكامل)
load-test-preflight:
	docker run --rm \
		-v "$(CURDIR)/load-testing:/work" \
		-w /work/k6 \
		--add-host=host.docker.internal:host-gateway \
		-e K6_BASE_URL=http://host.docker.internal/api \
		-e K6_EMAIL_A=simulation.owner@demo.local \
		-e K6_PASSWORD_A=SimulationDemo123! \
		-e K6_EMAIL_B=owner@demo.sa \
		-e K6_PASSWORD_B=password \
		grafana/k6:latest run preflight.js

# مصفوفة تشغيل عملية (توقف مبكر عند أول فشل)
load-test-matrix:
	$(MAKE) load-test-preflight
	$(MAKE) load-test K6_PROFILE=smoke
	$(MAKE) load-test K6_PROFILE=normal
	$(MAKE) load-test K6_PROFILE=peak

# بوابة إصدار موسعة (قد تستغرق وقتاً)
load-test-release-gate:
	$(MAKE) load-test-matrix
	$(MAKE) load-test K6_PROFILE=stress
	$(MAKE) load-test K6_PROFILE=soak

# Secure RC gate (official): strict baseline + peak with infra telemetry
load-test-rc-secure-gate:
	powershell -ExecutionPolicy Bypass -File scripts/verify-operational-baseline.ps1
	powershell -ExecutionPolicy Bypass -File scripts/run-peak-rc-secure.ps1

# سلم سعة POS (3→5→7 req/s افتراضياً): تقارير في load-testing/reports/capacity-discovery-*
# Windows: PowerShell. Linux/macOS: bash. يتطلب Docker + docker compose up + بذور المحاكاة.
load-test-capacity-discovery:
ifeq ($(OS),Windows_NT)
	powershell -ExecutionPolicy Bypass -File scripts/run-capacity-discovery.ps1
else
	bash scripts/run-capacity-discovery.sh
endif

# تشغيل نقطة واحدة: make load-test-capacity-one K6_CAPACITY_POS_RATE=5
K6_CAPACITY_POS_RATE ?= 3
K6_CAPACITY_POS_STEADY_MIN ?= 5
load-test-capacity-one:
	docker run --rm \
		-v "$(CURDIR)/load-testing:/work" \
		-w /work/k6 \
		--add-host=host.docker.internal:host-gateway \
		-e K6_BASE_URL=http://host.docker.internal/api \
		-e K6_PROFILE=capacity_pos \
		-e K6_CAPACITY_POS_RATE=$(K6_CAPACITY_POS_RATE) \
		-e K6_CAPACITY_POS_STEADY_MIN=$(K6_CAPACITY_POS_STEADY_MIN) \
		-e K6_POS_DISTRIBUTION=single \
		-e K6_EMAIL_A=simulation.owner@demo.local \
		-e K6_PASSWORD_A=SimulationDemo123! \
		-e K6_EMAIL_B=owner@demo.sa \
		-e K6_PASSWORD_B=password \
		grafana/k6:latest run suite.js

test-filter:
	docker compose exec app php artisan test --filter=$(filter)

test-coverage:
	docker compose exec app php artisan test --coverage

ps:
	docker compose ps

install:
	docker compose exec app composer install
	docker compose exec frontend npm install
