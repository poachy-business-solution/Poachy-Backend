SHELL := /bin/bash

DOCKER_COMPOSE ?= docker compose
APP_SERVICE ?= laravel.test
DBTOOLS_NETWORK ?= dbtools

.PHONY: setup ensure-dbtools env up composer-install key migrate seed-central seed-demo shell artisan test pint down logs

setup: ensure-dbtools env up composer-install key migrate seed-central seed-demo
	@printf "\nPoachy local setup complete.\n"
	@printf "Central API: http://poachy.test/api\n"
	@printf "Demo tenant: https://demo.poachy.test\n\n"

ensure-dbtools:
	@if ! docker network inspect "$(DBTOOLS_NETWORK)" >/dev/null 2>&1; then \
		echo "Creating Docker network: $(DBTOOLS_NETWORK)"; \
		docker network create "$(DBTOOLS_NETWORK)" >/dev/null; \
	else \
		echo "Docker network already exists: $(DBTOOLS_NETWORK)"; \
	fi

env:
	@if [ ! -f .env ]; then \
		cp .env.example .env; \
		echo "Created .env from .env.example"; \
	else \
		echo ".env already exists"; \
	fi

up:
	$(DOCKER_COMPOSE) up -d --build

composer-install:
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) composer install

key:
	@if ! grep -q '^APP_KEY=base64:' .env; then \
		$(DOCKER_COMPOSE) exec $(APP_SERVICE) php artisan key:generate; \
	else \
		echo "APP_KEY already set"; \
	fi

migrate:
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) php artisan migrate --force

seed-central:
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) php artisan db:seed --force

seed-demo:
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) php artisan tenant:seed-demo

shell:
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) bash

artisan:
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) php artisan $(cmd)

test:
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) php artisan test

pint:
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) vendor/bin/pint --dirty

logs:
	$(DOCKER_COMPOSE) logs -f $(APP_SERVICE)

down:
	$(DOCKER_COMPOSE) down
