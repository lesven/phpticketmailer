# Makefile für Docker-basierte Ausführung von Projekt-Tasks
# Annahmen:
# - Docker Compose Dateien: docker-compose.yml und optional compose.override.yaml
# - Default PHP-Service-Name: php (kann via PHP_SERVICE überschrieben werden)
# - Default DB-Service-Name: mysql (kann via DB_SERVICE überschrieben werden)

DC ?= docker-compose -f docker-compose.yml -f compose.override.yaml
PHP_SERVICE ?= php
DB_SERVICE ?= mysql

# Composer options
COMPOSER_FLAGS ?= --no-interaction --prefer-dist --optimize-autoloader

.PHONY: help build up down restart logs ps shell composer-install composer-update composer-dump-env \
        console cache-clear cache-warmup migrate migrate-fresh db-create db-drop test

help:
	@echo "Makefile commands (verwende: make <target>)"
	@echo
	@echo "  build                 - Docker images bauen"
	@echo "  up                    - Container starten (detached)"
	@echo "  down                  - Container stoppen"
	@echo "  restart               - restart services"
	@echo "  logs                  - Logs (follow)"
	@echo "  ps                    - Zeige laufende Services"
	@echo "  shell                 - Interaktives shell im PHP-Container"
	@echo
	@echo "  composer-install      - composer install inside php container"
	@echo "  composer-update       - composer update inside php container"
	@echo "  composer-dump-env     - symfony:dump-env inside php container"
	@echo
	@echo "  console <cmd>         - run bin/console <cmd> (z.B. make console\"cache:clear\")"
	@echo "  cache-clear           - php bin/console cache:clear"
	@echo "  cache-warmup          - php bin/console cache:warmup"
	@echo
	@echo "  migrate               - doctrine migrations:migrate"
	@echo "  migrate-fresh         - drop, create, migrate (DB reset)"
	@echo "  db-create             - create database"
	@echo "  db-drop               - drop database"
	@echo
	@echo "  test                  - run phpunit tests (vendor/bin/phpunit)"
	@echo
	@echo "Variablen (falls nötig): DC, PHP_SERVICE, DB_SERVICE, COMPOSER_FLAGS"


## Docker lifecycle
build:
	$(DC) build --pull

up:
	$(DC) up -d --remove-orphans

down:
	$(DC) down

restart: down up

logs:
	$(DC) logs -f

ps:
	$(DC) ps

shell:
	@echo "Opening shell in $(PHP_SERVICE) container"
	$(DC) exec $(PHP_SERVICE) sh


## Composer
composer-install:
	@echo "Running: composer install inside $(PHP_SERVICE)"
	$(DC) run --rm $(PHP_SERVICE) composer install $(COMPOSER_FLAGS)

composer-update:
	@echo "Running: composer update inside $(PHP_SERVICE)"
	$(DC) run --rm $(PHP_SERVICE) composer update $(COMPOSER_FLAGS)

composer-dump-env:
	@echo "Dumping env vars (symfony:dump-env)"
	$(DC) run --rm $(PHP_SERVICE) php bin/console dotenv:dump-env prod


## Symfony console helpers
console:
	@# Usage: make console ARGS="cache:clear --no-warmup"
	if [ -z "$(ARGS)" ]; then \
		echo "Bitte: make console ARGS=\"your:command --flags\""; exit 1; \
	fi
	$(DC) run --rm $(PHP_SERVICE) php bin/console $(ARGS)

cache-clear:
	$(DC) run --rm $(PHP_SERVICE) php bin/console cache:clear --no-warmup

cache-warmup:
	$(DC) run --rm $(PHP_SERVICE) php bin/console cache:warmup


## Doctrine / DB
migrate:
	$(DC) run --rm $(PHP_SERVICE) php bin/console doctrine:migrations:migrate --no-interaction

migrate-fresh:
	@echo "Dropping and recreating database, then migrating"
	$(DC) run --rm $(PHP_SERVICE) php bin/console doctrine:database:drop --force --if-exists || true
	$(DC) run --rm $(PHP_SERVICE) php bin/console doctrine:database:create --if-not-exists
	$(DC) run --rm $(PHP_SERVICE) php bin/console doctrine:migrations:migrate --no-interaction

db-create:
	$(DC) run --rm $(PHP_SERVICE) php bin/console doctrine:database:create --if-not-exists

db-drop:
	$(DC) run --rm $(PHP_SERVICE) php bin/console doctrine:database:drop --force --if-exists


## Tests
test:
	@echo "Running phpunit inside $(PHP_SERVICE)"
	$(DC) run --rm $(PHP_SERVICE) vendor/bin/phpunit --colors=always


# Shortcut to run arbitrary command in php container
run-php:
	@# Usage: make run-php CMD="composer install"
	if [ -z "$(CMD)" ]; then \
		echo "Bitte: make run-php CMD=\"your command\""; exit 1; \
	fi
	$(DC) run --rm $(PHP_SERVICE) sh -lc "$(CMD)"
