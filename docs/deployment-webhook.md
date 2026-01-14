# Deployment Webhook

Diese Doku beschreibt, wie der GitHub -> Server Webhook funktioniert und wie er aus GitHub Actions aufgerufen wird.

## Übersicht
- Endpoint: `POST /webhook/github-deploy`
- Signatur: `X-Hub-Signature-256: sha256=<hex-hmac>` (HMAC SHA-256 über den JSON-Body mit dem Secret)
- Event: erwartet `X-GitHub-Event: push`
- Branch: Standard `refs/heads/main` (konfigurierbar via `GITHUB_DEPLOY_BRANCH` env)
- Aktion: startet standardmäßig das Kommando `make deploy` (konfigurierbar via `DEPLOY_COMMAND` env, Fallback: `./deploy.sh`)

## Server-Konfiguration
1. Setze in deiner Produktions-Umgebung die Umgebungsvariable `GITHUB_WEBHOOK_SECRET` (geheime Zeichenkette).
2. (Optional) Setze `GITHUB_DEPLOY_BRANCH` wenn du eine andere Branch verwenden willst (Standard: `refs/heads/main`).
3. Setze optional `DEPLOY_COMMAND` auf dem Server (z. B. `make deploy`). Falls `DEPLOY_COMMAND` nicht gesetzt ist, versucht das System `make deploy` als Default, und als letzter Fallback kann `./deploy.sh` verwendet werden (diese Datei muss dann ausführbar sein: `chmod +x deploy.sh`).

## Sicherheit
- Die Signatur wird mittels `hash_hmac('sha256', $body, $secret)` geprüft und über `hash_equals()` verglichen.
- Nutze ein starkes Secret und lege es in den Server-Umgebungsvariablen ab (nicht im Repo).

## Beispiel GitHub Actions-Aufruf
Die Datei `.github/workflows/trigger-deploy.yml` im Repo enthält ein Beispiel, das bei jedem Push in `main` den Webhook aufruft.

Wichtig: In den Repository-Secrets muss `DEPLOY_WEBHOOK_URL` (z. B. `https://example.com/webhook/github-deploy`) und `DEPLOY_WEBHOOK_SECRET` gesetzt sein. Setze `DEPLOY_WEBHOOK_SECRET` auf denselben Wert wie die Server-Umgebungsvariable `GITHUB_WEBHOOK_SECRET`, damit die Signaturprüfung erfolgreich ist.

Beispiel, was die Action macht:
- Erzeugt ein kleines Payload JSON: `{ "ref": "refs/heads/main" }`
- Berechnet die Signatur mit `openssl -sha256 -hmac "$WEBHOOK_SECRET"`
- Sendet `POST` mit `X-Hub-Signature-256` und `X-GitHub-Event: push`

## Hinweise
- In produktiven Setups solltest du das Deploy-Script robust gestalten (Locking, Logging, Fehlerbehandlung).
- Falls du keine direkte Ausführung auf dem Webserver möchtest, kannst du stattdessen einen Job-Queue-Eintrag oder ein System-Signal setzen, das einen Deploy-Worker auslöst.
