# Makefile für phpticketmailer
# Enthält gängige Targets zum Bauen, Starten und Verwalten der Docker-Umgebung

# Docker Compose command detection (Windows-friendly)
# Versucht zuerst "docker-compose", dann "docker compose". Auf Windows kann "docker compose"
# nur als zwei-token-Aufruf funktionieren, daher bauen wir die Argumente in DC_ARGS.
COMPOSE_FILE := docker-compose.yml
COMPOSE_DEV_FILE := docker-compose.dev.yml

# Vereinfachte Verwendung: verwende standardmäßig `docker compose -f docker-compose.yml`.
# Das vermeidet shell-Aufrufe beim Parsen des Makefiles, die auf Windows Fehlermeldungen
# wie "Das System kann den angegebenen Pfad nicht finden." auslösen können.
DC_BASE := docker
DC_ARGS := compose -f $(COMPOSE_FILE)
DC_DEV_ARGS := compose -f $(COMPOSE_FILE) -f $(COMPOSE_DEV_FILE)

# Use bash for advanced shell features (pipefail etc.)
SHELL := /bin/bash

# Service-Namen wie in docker-compose.yml
PHP_SERVICE := php
WEB_SERVICE := webserver
DB_SERVICE := database
MAILHOG_SERVICE := mailhog

# Database defaults (matching docker-compose.yml)
DB_USER := ticketuser
DB_PASSWORD := ticketpassword
DB_NAME := ticket_mailer_db
# Default dump file on host (can be overridden: `make db-dump DUMP_FILE=./backups/my.sql`)
DUMP_FILE ?= ./db-dump.sql

.PHONY: help build build-dev up up-d down down-remove restart ps logs logs-php exec-php console deploy deploy-dev composer-install composer-update cache-clear cache-warmup migrate migrate-status test coverage fresh recreate-db test-e2e test-e2e-headed test-e2e-install test-e2e-with-fixtures

help:
	@echo "Makefile - gängige Targets für Docker und Symfony/Scripts"
	@echo "  make build                  -> Docker-Images bauen (no-cache, pull) - PRODUCTION (ohne Xdebug)"
	@echo "  make build-dev              -> Docker-Images bauen (no-cache, pull) - DEVELOPMENT (mit Xdebug)"
	@echo "  make up                     -> Docker-Compose up (foreground)"
	@echo "  make up-d                   -> Docker-Compose up -d (detached)"
	@echo "  make down                   -> Stoppt alle Compose-Container (sicher, löscht keine Volumes)"
	@echo "  make down-remove            -> Docker-Compose down (entfernt volumes & orphans)"
	@echo "  make restart                -> Restart aller Services"
	@echo "  make ps                     -> Anzeigen laufender Compose-Services"
	@echo "  make logs                   -> Logs aller Services (folgen)"
	@echo "  make logs-php               -> Logs des PHP-Service"
	@echo "  make exec-php               -> Interaktiv in den PHP-Container (bash)"
	@echo "  make console                -> Symfony Console ausführen im PHP-Container (nutze ARGS='...')"
	@echo "  make deploy                 -> Vollständiger Deploy-Flow (Production, ohne phpMyAdmin)"
	@echo "  make deploy-dev             -> Deploy-Flow für Development (mit phpMyAdmin auf Port 8087)"
	@echo "  make composer-install       -> composer install im PHP-Container"
	@echo "  make composer-update        -> composer update im PHP-Container"
	@echo "  make cache-clear            -> Symfony Cache leeren (dev & prod)"
	@echo "  make cache-warmup           -> Symfony Cache vorerwärmen"
	@echo "  make migrate                -> Doctrine-Migrations ausführen"
	@echo "  make migrate-status         -> Migration-Status anzeigen"
	@echo "  make test                   -> PHPUnit-Tests im PHP-Container ausführen"
	@echo "  make fresh                  -> full rebuild + composer install + migrate"
	@echo "  make recreate-db            -> DB-Volume entfernen und DB neu starten"
	@echo ""
	@echo "E2E-Tests (TestCafe - erfordert Node.js und laufende App auf Port 8090):"
	@echo "  make test-e2e-install       -> Node.js-Abhängigkeiten (TestCafe) installieren"
	@echo "  make test-e2e               -> E2E-Tests headless ausführen (Chrome)"
	@echo "  make test-e2e-headed        -> E2E-Tests mit sichtbarem Browser ausführen"
	@echo "  make test-e2e-with-fixtures -> Fixtures laden und E2E-Tests ausführen"

## Build Docker images (no-cache, pull latest base images) - PRODUCTION (ohne Xdebug)
build:
	@echo "==> Building docker images for PRODUCTION (without Xdebug)"
	@$(DC_BASE) $(DC_ARGS) build --pull --no-cache --build-arg ENABLE_XDEBUG=false

## Build Docker images (no-cache, pull latest base images) - DEVELOPMENT (mit Xdebug)
build-dev:
	@echo "==> Building docker images for DEVELOPMENT (with Xdebug)"
	@$(DC_BASE) $(DC_ARGS) build --pull --no-cache --build-arg ENABLE_XDEBUG=true

## Start in foreground
up-foreground:
	@echo "==> docker compose up (foreground)"
	@$(DC_BASE) $(DC_ARGS) up

## Start detached
up:
	@echo "==> docker compose up -d"
	@$(DC_BASE) $(DC_ARGS) up -d

## Stop containers only (safer)
down:
	@echo "==> docker compose stop (containers only, volumes preserved)"
	@$(DC_BASE) $(DC_ARGS) stop

## Full down: stop and remove containers, networks, volumes and orphans (legacy behavior)
down-remove:
	@echo "==> docker compose down (remove volumes & orphans)"
	@sh -c '\
	printf "ACHTUNG: Dadurch werden Container, Netzwerke und Docker-Volumes gelöscht.\n"; \
	printf "Willst du fortfahren? Tippe y und Enter zum Bestätigen: "; \
	read -r ans; \
	if [ "$$ans" = "y" ] || [ "$$ans" = "Y" ]; then \
		echo "-> Ausführen: $(DC_BASE) $(DC_ARGS) down --volumes --remove-orphans"; \
		$(DC_BASE) $(DC_ARGS) down --volumes --remove-orphans; \
	else \
		echo "Abgebrochen."; \
	fi'

restart: down up

ps:
	@$(DC_BASE) $(DC_ARGS) ps

logs:
	@$(DC_BASE) $(DC_ARGS) logs -f --tail=200

logs-php:
	@$(DC_BASE) $(DC_ARGS) logs -f --tail=200 $(PHP_SERVICE)

## Interaktives Shell in PHP-Container
exec-php:
	@echo "==> Exec into $(PHP_SERVICE)"
	@$(DC_BASE) $(DC_ARGS) exec $(PHP_SERVICE) bash

## Symfony Console passthrough: make console ARGS="cache:clear --env=prod"
console:
	@echo "==> Running php bin/console in $(PHP_SERVICE): $(ARGS)"
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) php bin/console $(ARGS)

## Load application fixtures
fixtures:
	@echo "==> Loading data fixtures (no --force)"
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) php bin/console app:load-data-fixtures

fixtures-force:
	@echo "==> Loading data fixtures (--force)"
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) php bin/console app:load-data-fixtures --force

## Composer helpers (führen Composer im PHP-Container aus)
composer-install:
	@echo "==> composer install im $(PHP_SERVICE)"
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) composer install --no-interaction --prefer-dist --optimize-autoloader

composer-update:
	@echo "==> composer update im $(PHP_SERVICE)"
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) composer update --no-interaction

## Update only phpunit in composer.lock (sauberer als full update)
composer-update-phpunit:
	@echo "==> composer update phpunit/phpunit im $(PHP_SERVICE)"
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) composer update phpunit/phpunit --with-dependencies --no-interaction || \
		echo "composer update phpunit failed"

## Symfony cache
cache-clear:
	@echo "==> symfony cache:clear (dev & prod)"
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) php bin/console cache:clear --no-warmup
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) php bin/console cache:clear --env=prod --no-warmup

cache-warmup:
	@echo "==> symfony cache:warmup (prod)"
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) php bin/console cache:warmup --env=prod

## Doctrine Migrations

migrate-status:
	@echo "==> doctrine:migrations:status"
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) php bin/console doctrine:migrations:status


migrate:
	@echo "==> doctrine:migrations:migrate (no interaction)"
	@$(MAKE) wait-db
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) php bin/console doctrine:migrations:migrate --no-interaction

.PHONY: wait-db
wait-db:
	@echo "==> Waiting for database to become reachable from $(PHP_SERVICE)"
	@i=1; while [ $$i -le 30 ]; do \
		if $(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) php -r 'new PDO("mysql:host=database;port=3306", "ticketuser", "ticketpassword");' 2>/dev/null; then \
			echo "Database is ready!"; \
			exit 0; \
		fi; \
		echo "Waiting for database... attempt $$i/30"; \
		sleep 2; \
		i=$$((i + 1)); \
	done; \
	echo "ERROR: Database did not become ready in time"; \
	exit 1

## Tests (PHPUnit)

test:
	@echo "==> Running PHPUnit tests inside $(PHP_SERVICE) (APP_ENV=test)"
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) bash -lc "if [ -f vendor/bin/phpunit ]; then APP_ENV=test vendor/bin/phpunit --colors=always; else echo 'phpunit not found, run composer install first'; exit 1; fi"

## Generate code coverage (HTML + text summary)
coverage:
	@echo "==> Running PHPUnit coverage inside $(PHP_SERVICE)"
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) /bin/sh -lc "echo '=== php -v ===' && php -v && echo '=== php -m (xdebug?) ===' && php -m | grep -i xdebug || true && if [ -f vendor/bin/phpunit ]; then mkdir -p var/coverage && XDEBUG_MODE=coverage XDEBUG_CONFIG='start_with_request=1' vendor/bin/phpunit --colors=always --coverage-html var/coverage --coverage-text; else echo 'phpunit not found, run composer install first'; exit 1; fi"

## Recreate DB: stop, remove volumes and bring up database only

recreate-db:
	@echo "==> Recreate DB volume and start database"
	@$(DC_BASE) $(DC_ARGS) down --volumes --remove-orphans
	@$(DC_BASE) $(DC_ARGS) up -d $(DB_SERVICE)


## Database dump to host file (default: ./db-dump.sql)
## Usage: make db-dump OR make db-dump DUMP_FILE=./backups/my.sql
db-dump:
	@echo "==> Dumping database $(DB_NAME) to $(DUMP_FILE)"
	@set -o pipefail; \
	if $(DC_BASE) $(DC_ARGS) exec -T $(DB_SERVICE) mariadb-dump -u$(DB_USER) -p"$(DB_PASSWORD)" --databases $(DB_NAME) --single-transaction --quick --skip-lock-tables > $(DUMP_FILE) 2>/dev/null; then \
		echo "Dump created using $(DB_SERVICE) container"; \
	else \
		echo "mariadb-dump not found via exec, using temporary client container"; \
		$(DC_BASE) $(DC_ARGS) run --rm --no-deps $(DB_SERVICE) sh -c "mariadb-dump -h database -P 3306 -u$(DB_USER) -p'$(DB_PASSWORD)' --databases $(DB_NAME) --single-transaction --quick --skip-lock-tables" > $(DUMP_FILE); \
	fi


## Restore database from host file
## Usage: make db-restore DUMP_FILE=./backups/my.sql
db-restore:
	@echo "==> Restoring database $(DB_NAME) from $(DUMP_FILE)"
	@$(MAKE) wait-db
	@set -o pipefail; \
	if cat $(DUMP_FILE) | $(DC_BASE) $(DC_ARGS) exec -T $(DB_SERVICE) mariadb -u$(DB_USER) -p"$(DB_PASSWORD)" $(DB_NAME) 2>/dev/null; then \
		echo "Restore completed using $(DB_SERVICE) container"; \
	else \
		echo "mariadb client not found via exec, using temporary client container"; \
		cat $(DUMP_FILE) | $(DC_BASE) $(DC_ARGS) run --rm --no-deps $(DB_SERVICE) sh -c "mariadb -h database -P 3306 -u$(DB_USER) -p'$(DB_PASSWORD)' $(DB_NAME)"; \
	fi

deploy:
	git pull
	make down
	make build
	make up
	make composer-install
	make migrate
	make cache-clear
	make cache-warmup
	make up

deploy-dev:
	@echo "==> Dev-Deploy (mit phpMyAdmin)"
	git pull
	$(MAKE) down
	$(DC_BASE) $(DC_ARGS) build --pull --no-cache --build-arg ENABLE_XDEBUG=true
	$(DC_BASE) $(DC_DEV_ARGS) up -d
	$(MAKE) composer-install
	$(MAKE) migrate
	$(MAKE) cache-clear
	$(MAKE) up

## E2E-Tests mit TestCafe
## Voraussetzungen: Node.js installiert, App läuft auf Port 8090 (make up)

## Node.js-Abhängigkeiten für E2E-Tests installieren
test-e2e-install:
	@echo "==> Installiere Node.js-Abhängigkeiten für E2E-Tests (TestCafe)"
	@command -v npm >/dev/null 2>&1 || { echo "FEHLER: npm nicht gefunden. Bitte Node.js installieren."; exit 1; }
	@npm install
	@echo "==> TestCafe erfolgreich installiert"

## E2E-Tests headless ausführen (Chrome ohne Benutzeroberfläche)
## Die App muss laufen: make up && make migrate && make fixtures-force
test-e2e:
	@echo "==> Starte TestCafe E2E-Tests (headless Chromium) – App muss auf Port 8090 laufen"
	@command -v npx >/dev/null 2>&1 || { echo "FEHLER: npx nicht gefunden. Bitte Node.js installieren."; exit 1; }
	@[ -d node_modules/testcafe ] || { echo "==> Installiere TestCafe..."; npm install; }
	@APP_URL=$${APP_URL:-http://localhost:8090} npx testcafe 'chromium:headless' e2e/tests/ --reporter spec

## E2E-Tests mit sichtbarem Browser ausführen (für Debugging)
test-e2e-headed:
	@echo "==> Starte TestCafe E2E-Tests (Chromium mit Benutzeroberfläche)"
	@command -v npx >/dev/null 2>&1 || { echo "FEHLER: npx nicht gefunden. Bitte Node.js installieren."; exit 1; }
	@[ -d node_modules/testcafe ] || { echo "==> Installiere TestCafe..."; npm install; }
	@APP_URL=$${APP_URL:-http://localhost:8090} npx testcafe chromium e2e/tests/ --reporter spec

## Fixtures laden und danach E2E-Tests ausführen (All-in-one für CI)
test-e2e-with-fixtures:
	@echo "==> Lade Fixtures und führe E2E-Tests aus"
	@$(MAKE) fixtures-force
	@$(MAKE) test-e2e
