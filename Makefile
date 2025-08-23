# Makefile für phpticketmailer
# Enthält gängige Targets zum Bauen, Starten und Verwalten der Docker-Umgebung

# Docker Compose command detection (Windows-friendly)
# Versucht zuerst "docker-compose", dann "docker compose". Auf Windows kann "docker compose"
# nur als zwei-token-Aufruf funktionieren, daher bauen wir die Argumente in DC_ARGS.
COMPOSE_FILE := docker-compose.yml

# Vereinfachte Verwendung: verwende standardmäßig `docker compose -f docker-compose.yml`.
# Das vermeidet shell-Aufrufe beim Parsen des Makefiles, die auf Windows Fehlermeldungen
# wie "Das System kann den angegebenen Pfad nicht finden." auslösen können.
DC_BASE := docker
DC_ARGS := compose -f $(COMPOSE_FILE)

# Service-Namen wie in docker-compose.yml
PHP_SERVICE := php
WEB_SERVICE := webserver
DB_SERVICE := database
MAILHOG_SERVICE := mailhog

.PHONY: help build up up-d down down-remove restart ps logs logs-php exec-php console deploy composer-install composer-update cache-clear cache-warmup migrate migrate-status test coverage fresh recreate-db

help:
	@echo "Makefile - gängige Targets für Docker und Symfony/Scripts"
	@echo "  make build           -> Docker-Images bauen (no-cache, pull)"
	@echo "  make up              -> Docker-Compose up (foreground)"
	@echo "  make up-d            -> Docker-Compose up -d (detached)"
	@echo "  make down            -> Stoppt alle Compose-Container (sicher, löscht keine Volumes)"
	@echo "  make down-remove     -> Docker-Compose down (entfernt volumes & orphans)"
	@echo "  make restart         -> Restart aller Services"
	@echo "  make ps              -> Anzeigen laufender Compose-Services"
	@echo "  make logs            -> Logs aller Services (folgen)"
	@echo "  make logs-php        -> Logs des PHP-Service"
	@echo "  make exec-php        -> Interaktiv in den PHP-Container (bash)"
	@echo "  make console         -> Symfony Console ausführen im PHP-Container (nutze ARGS='...')"
	@echo "  make deploy          -> Vollständiger Deploy-Flow (GIT_PULL=1, SKIP_MIGRATIONS=1, SKIP_COMPOSER=1 möglich)"
	@echo "  make composer-install-> composer install im PHP-Container"
	@echo "  make composer-update -> composer update im PHP-Container"
	@echo "  make cache-clear     -> Symfony Cache leeren (dev & prod)"
	@echo "  make cache-warmup    -> Symfony Cache vorerwärmen"
	@echo "  make migrate         -> Doctrine-Migrations ausführen"
	@echo "  make migrate-status  -> Migration-Status anzeigen"
	@echo "  make test            -> PHPUnit-Tests im PHP-Container ausführen"
	@echo "  make fresh           -> full rebuild + composer install + migrate"
	@echo "  make recreate-db     -> DB-Volume entfernen und DB neu starten"

## Build Docker images (no-cache, pull latest base images)
build:
	@echo "==> Building docker images"
	@$(DC_BASE) $(DC_ARGS) build --pull --no-cache

## Start in foreground
up-foreground:
	@echo "==> docker compose up (foreground)"
	@$(DC_BASE) $(DC_ARGS) up --build

## Start detached
up:
	@echo "==> docker compose up -d"
	@$(DC_BASE) $(DC_ARGS) up -d --build

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
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) php bin/console doctrine:migrations:migrate --no-interaction

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

## Full fresh flow: rebuild, start, composer install, migrate
fresh: build up-d composer-install migrate cache-warmup

## Deploy flow: bring new code, ensure containers, run composer, migrations, cache, assets
# Variables:
#   GIT_PULL=1           -> run 'git pull' on the host before deploying
#   SKIP_COMPOSER=1      -> skip composer install
#   SKIP_MIGRATIONS=1    -> skip doctrine migrations
#   SKIP_ASSETS=1        -> skip assets:install
deploy:
	@bash -lc '
	set -e
	echo "==> Deploy flow started"
	if [ "$(GIT_PULL)" = "1" ]; then
	  echo "-> Running git pull on host"
	  git pull || { echo "git pull failed"; exit 1; }
	fi
	echo "-> Pulling images and starting services"
	$(DC_BASE) $(DC_ARGS) pull || true
	$(DC_BASE) $(DC_ARGS) up -d --build
	if [ "$(SKIP_COMPOSER)" != "1" ]; then
	  echo "-> composer install in $(PHP_SERVICE)"
	  $(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) composer install --no-interaction --prefer-dist --optimize-autoloader || { echo "composer install failed"; exit 1; }
	else
	  echo "-> Skipping composer install"
	fi
	if [ "$(SKIP_MIGRATIONS)" != "1" ]; then
	  echo "-> Running doctrine migrations"
	  $(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) php bin/console doctrine:migrations:migrate --no-interaction || { echo "migrations failed"; exit 1; }
	else
	  echo "-> Skipping migrations"
	fi
	echo "-> Clearing and warming cache (prod)"
	$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) php bin/console cache:clear --no-warmup || true
	$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) php bin/console cache:warmup --env=prod || true
	if [ "$(SKIP_ASSETS)" != "1" ]; then
	  echo "-> Installing assets (if symfony/asset available)"
	  $(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) php bin/console assets:install --symlink --relative || true
	else
	  echo "-> Skipping assets"
	fi
	echo "==> Deploy flow finished"
	'
