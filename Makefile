.PHONY: up down build fresh logs logs-all shell migrate seed key tinker queue-restart swagger test test-filter test-coverage ps install

up:
	docker compose up -d

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

key:
	docker compose exec app php artisan key:generate

tinker:
	docker compose exec app php artisan tinker

queue-restart:
	docker compose restart queue_high queue_default queue_low

swagger:
	docker compose exec app php artisan l5-swagger:generate

test:
	docker compose exec app php artisan test --parallel

test-filter:
	docker compose exec app php artisan test --filter=$(filter)

test-coverage:
	docker compose exec app php artisan test --coverage

ps:
	docker compose ps

install:
	docker compose exec app composer install
	docker compose exec frontend npm install
