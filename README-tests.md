README — Tests (Docker)
=========================

Kurz: hier stehen die wichtigsten Befehle, um PHPUnit/Composer/Coverage manuell innerhalb deines Docker-Setups auszuführen.
Alle Befehle sind für zsh formuliert und setzen voraus, dass du im Projekt-Root arbeitest.

Wichtige Pfade
- PHPUnit-Konfig: `phpunit.xml.dist`
- Coverage HTML: `build/coverage-html/index.html`
- Clover (CI): `build/logs/clover.xml` (falls vorhanden)
- Einfacher HTML-Generator (falls PHPUnit nicht direkt HTML schreibt): `tools/clover-to-html.php`

Vorbereitung
1) PHP-Image bauen (falls du Dockerfile geändert hast):

```bash
# rebuild php image (nutze, wenn du z.B. Xdebug in Dockerfile hinzugefügt hast)
docker compose build php
```

2) PHP- & DB-Services starten (falls nötig):

```bash
# startet php + database im Hintergrund
docker compose up -d php database
```

3) Composer-Dependencies installieren (im Container, damit Berechtigungen auf dem Volume passen):

```bash
# als root (falls Probleme mit Schreiben auf dem Volume auftreten)
docker compose run --rm --no-deps -u 0 php composer install --no-interaction --prefer-dist --optimize-autoloader

# oder (wenn Container bereits läuft)
docker compose exec php composer install --no-interaction --prefer-dist --optimize-autoloader
```

Tests ausführen
----------------

1) Alle Tests ausführen (schnell, ohne Coverage):

```bash
# benutzt vendor/bin/phpunit im laufenden php-container
docker exec -it ticketumfrage_php vendor/bin/phpunit -c phpunit.xml.dist --testdox
```

2) Einzelne Test-Datei ausführen:

```bash
# Beispiel: nur CsvProcessorTest
docker exec -it ticketumfrage_php vendor/bin/phpunit -c phpunit.xml.dist tests/Service/CsvProcessorTest.php --testdox
```

3) Tests als Host-User ausführen (vermeidet Root-Besitz von generierten Dateien):

```bash
docker compose run --rm --no-deps -u $(id -u):$(id -g) php vendor/bin/phpunit -c phpunit.xml.dist --testdox
```

Coverage erzeugen (lokal)
-------------------------

Hinweis: Für Coverage brauchst du einen Coverage-Treiber (Xdebug oder PCOV) im PHP-Image. Dieses Repo hat Xdebug per Dockerfile installiert.

1) Coverage (Text-Ausgabe):

```bash
# Xdebug per env aktivieren und Coverage-Text ausgeben
docker exec -it ticketumfrage_php bash -lc 'XDEBUG_MODE=coverage vendor/bin/phpunit -c phpunit.xml.dist --coverage-text --colors=never'
```

2) HTML-Report + Clover (CI) per Composer-Script (falls konfiguriert):

```bash
# via composer script (projekt hat ein 'coverage' script in composer.json)
docker compose run --rm --no-deps -u 0 php composer coverage
```

3) Manuell HTML + Clover erzeugen (falls du es lieber per CLI möchtest):

```bash
# erzeugt HTML in build/coverage-html und Clover in build/logs/clover.xml
docker compose run --rm --no-deps -u 0 php bash -lc 'XDEBUG_MODE=coverage vendor/bin/phpunit -c phpunit.coverage.final.xml || true'

# wenn das config-file keine clover schreibt, kannst du explizit die clover datei forcieren
docker compose run --rm --no-deps -u 0 php bash -lc 'XDEBUG_MODE=coverage vendor/bin/phpunit -c phpunit.coverage.final.xml --coverage-clover build/logs/clover.xml || true'
```

docker exec -it ticketumfrage_php php tools/clover-to-html.php
4) Falls PHPUnit keine HTML-Dateien schreibt (Config-Probleme):

- Prüfe die `phpunit.coverage.final.xml`-Konfiguration oder erzeuge die Reports per CLI-Flags (siehe oben).

Wenn nötig kopiere die erzeugte `coverage.xml` manuell nach `build/logs/clover.xml` (CI):

```bash
# copy coverage.xml to CI path
cp coverage.xml build/logs/clover.xml
```

Erzeugte Dateien und Orte
- coverage.xml (im Projekt-Root) — temporär erstellt, kann nach `build/logs/clover.xml` kopiert werden
- build/coverage-html/index.html — HTML-Report
- build/logs/clover.xml — CI-geeignete Clover-XML (wird ggf. per cp aus coverage.xml erzeugt)

Praktische Tipps / Troubleshooting
---------------------------------
- Wenn PHPUnit meldet "No code coverage driver available":
  - prüfe, ob Xdebug geladen ist (in Container: `php -m | grep xdebug`)
  - rebuild PHP-Image, wenn du Xdebug in Dockerfile ergänzt hast: `docker compose build php`

- Permission-Probleme (vendor/ oder build/):
  - Führe `composer install` als root im Container aus (siehe oben) oder passe die Rechte auf dem Host an.

- Wenn PHPUnit bei Coverage sagt "No filter is configured":
  - Nutze die mitgelieferte `phpunit.coverage.final.xml` oder rufe phpunit mit CLI-Flags `--coverage-html`/`--coverage-clover` auf.

- CI: In GitHub Actions kannst du `docker compose run --rm php composer coverage` oder `docker compose run --rm php vendor/bin/phpunit -c phpunit.coverage.final.xml` verwenden und die `build/coverage-html` bzw. `build/logs/clover.xml` als Artifacts hochladen.

Zusammenfassung (schnell)
------------------------
- Tests (keine Coverage):
  - `docker exec -it ticketumfrage_php vendor/bin/phpunit -c phpunit.xml.dist --testdox`
- Coverage (HTML):
  - `docker compose run --rm --no-deps -u 0 php composer coverage`

Wenn du möchtest, passe ich die README noch an (z. B. mehr Troubleshooting, Hinweise für CI), oder ich lege eine kurze GitHub Actions Workflow-Datei an, die exakt diese Befehle in CI ausführt.
