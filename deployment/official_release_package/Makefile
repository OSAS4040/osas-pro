# ترتيب التنفيذ الرسمي (مرقم): docs/Execution_Order_Asas_Pro.md
.PHONY: up down build fresh logs logs-all shell migrate seed seed-simulate key tinker queue-restart swagger dompdf-arabic-check test test-filter test-coverage test-frontend-full test-project-gate staging-gate policy-env-example github-branch-protection-status verify release-gate monitoring-gate integrity-verify load-test load-test-preflight load-test-matrix load-test-release-gate load-test-rc-secure-gate ps install ngrok-up ngrok-down ngrok-url up-ngrok dev-bootstrap

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
	docker compose restart queue_high queue_default queue_low

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

# بوابة مشروع: واجهة كاملة ثم PHPUnit داخل Docker
test-project-gate: test-frontend-full
	docker compose exec -T app sh -lc "cd /var/www && php artisan config:clear && ./vendor/bin/phpunit"

# فحص سريع بعد نشر staging (قرار: staging أولاً + متغيرات محافظة) — Vitest + PHPUnit مسار المنصة/SaaS
# نفس المنطق: scripts/staging-gate.sh (لـ CI/Linux)
staging-gate:
	docker compose exec -T frontend sh -lc "cd /app && npm ci && npm test"
	docker compose exec -T app sh -lc "cd /var/www && ./vendor/bin/phpunit tests/Unit/Support/SaasPlatformAccessTest.php tests/Feature/Saas/"

# تحقق سياسة أمثلة الإعداد — لا يحتاج Docker (Node فقط)
policy-env-example:
	node scripts/check-policy-env-example.mjs

# المرحلة 1 — قراءة فقط من GitHub API: هل main محمي وفيه فحص Policy env (يتطلب gh auth)
github-branch-protection-status:
	node scripts/gh-branch-protection-status.mjs

# بوابة ما قبل الإنتاج: مسار عميل كامل + سلامة بيانات + idempotency خفيف (انظر tests/Feature/PreProduction)
# واجهة المستخدم: تحقق يدوي (زمن تحميل <1.5s، لا شاشات بيضاء) — لا يغطيه PHPUnit.
test-preprod:
	docker compose exec -T app php artisan test --group=pre-production

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

# k6 — خط أساس ثابت عبر K6_PROFILE: smoke | normal | peak | stress | spike | soak
# مثال: make load-test K6_PROFILE=peak
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

test-filter:
	docker compose exec app php artisan test --filter=$(filter)

test-coverage:
	docker compose exec app php artisan test --coverage

ps:
	docker compose ps

install:
	docker compose exec app composer install
	docker compose exec frontend npm install
