ENV_FILE := .env.docker

.PHONY: up down build logs sh migrate up-prod sync logs-prod

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build

logs:
	docker compose logs -f

sh:
	docker compose exec backend sh

migrate:
	docker compose exec backend php artisan migrate --force

up-prod:
	docker compose -f docker-compose.prod.yml --env-file $(ENV_FILE) up -d

sync:
	git pull
	docker compose -f docker-compose.prod.yml --env-file $(ENV_FILE) build
	docker compose -f docker-compose.prod.yml --env-file $(ENV_FILE) up -d

logs-prod:
	docker compose -f docker-compose.prod.yml --env-file $(ENV_FILE) logs -f

migrate-prod:
	docker compose -f docker-compose.prod.yml --env-file $(ENV_FILE) exec backend php artisan migrate --force
