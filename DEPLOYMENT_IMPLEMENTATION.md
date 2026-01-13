# Implementierung: Automatisches Deployment via GitHub Actions und Webhook

## Zusammenfassung

Diese Implementierung ermöglicht automatisches Deployment der Anwendung auf dem Test-Server, sobald Änderungen in den `develop` Branch gepusht werden.

## Was wurde implementiert?

### 1. GitHub Actions Workflow (`.github/workflows/deploy-develop.yml`)

Ein Workflow, der:
- Bei jedem Push auf den `develop` Branch ausgelöst wird
- Eine JSON-Payload mit Commit-Informationen erstellt
- Eine HMAC-SHA256 Signatur zur Authentifizierung generiert
- Einen HTTPS POST-Request an den Webhook-Endpoint sendet
- Den HTTP-Status überprüft und Fehler meldet

**Benötigte GitHub Secrets:**
- `DEPLOY_WEBHOOK_URL`: Die HTTPS-URL des Webhook-Receivers auf dem Server
- `DEPLOY_WEBHOOK_SECRET`: Ein sicheres Secret (min. 32 Zeichen) zur Signaturvalidierung

### 2. Webhook-Receiver (`webhook-receiver.php`)

Ein PHP-Script, das:
- HTTPS POST-Requests empfängt
- Die HMAC-SHA256 Signatur validiert (Schutz vor unauthorisierten Requests)
- Die Payload parst und validiert
- `make deploy` im Projekt-Verzeichnis ausführt
- Alle Aktionen in eine Log-Datei schreibt
- JSON-Responses mit Status-Informationen zurückgibt

**Sicherheitsfeatures:**
- HMAC-SHA256 Signaturvalidierung mit timing-attack-sicherer Vergleichsfunktion
- Nur POST-Requests werden akzeptiert
- Secret muss auf Server und in GitHub identisch sein
- Ausführliches Logging aller Aktivitäten
- Fehlerbehandlung mit aussagekräftigen Fehlermeldungen

### 3. Dokumentation

#### DEPLOYMENT_WEBHOOK.md (Vollständige Dokumentation)
- Detaillierte Übersicht über das Deployment-System
- Schritt-für-Schritt Installationsanleitung
- Web-Server Konfiguration (nginx und Apache)
- Berechtigungen und Sicherheit
- Troubleshooting-Guide
- Erweiterte Konfigurationsoptionen
- Wartung und Monitoring

#### DEPLOYMENT_QUICKSTART.md (Schnellstart-Anleitung)
- Kompakte Checkliste für schnelle Einrichtung
- Alle essentiellen Befehle auf einen Blick
- Häufige Probleme und Lösungen
- Ideal für erfahrene Administratoren

### 4. Test-Script (`test-webhook.sh`)

Ein Bash-Script, das:
- Webhook-Requests manuell simuliert
- Korrekte Signatur-Generierung testet
- HTTP-Responses anzeigt und validiert
- Hilfreiche Debugging-Informationen liefert
- Kann mit Command-Line Argumenten oder Umgebungsvariablen verwendet werden

### 5. Konfigurationsbeispiele

- `.env.local.example` erweitert um `WEBHOOK_SECRET` Parameter
- `README.md` erweitert um Deployment-Sektion mit Verweis auf Dokumentation

## Workflow-Ablauf

```
Developer                GitHub                 Test-Server
    |                      |                         |
    |--- git push develop --->|                      |
    |                      |                         |
    |                 [Workflow startet]             |
    |                      |                         |
    |                      |--- POST /deploy-webhook --->|
    |                      |    (signiert)           |
    |                      |                         |
    |                      |                    [Signatur prüfen]
    |                      |                         |
    |                      |                    [make deploy ausführen]
    |                      |                         |
    |                      |<--- 200 OK -------------|
    |                      |                         |
    |<--- Success-Notification ---|                  |
```

## Sicherheitsmerkmale

1. **HTTPS-Only**: Alle Kommunikation erfolgt verschlüsselt
2. **HMAC-SHA256 Signatur**: Jeder Request wird mit einem gemeinsamen Secret signiert
3. **Timing-Attack Schutz**: `hash_equals()` verhindert Timing-Angriffe
4. **Logging**: Alle Aktivitäten werden protokolliert
5. **Input-Validierung**: JSON-Payload wird validiert
6. **Error Handling**: Sichere Fehlerbehandlung ohne Offenlegung sensibler Daten

## Vorteile

✅ **Automatisierung**: Kein manuelles Deployment mehr nötig
✅ **Schnelligkeit**: Deployment startet sofort nach Push
✅ **Sicherheit**: Mehrere Sicherheitsebenen (HTTPS, Signatur, Validation)
✅ **Nachvollziehbarkeit**: Vollständiges Logging aller Deployments
✅ **Flexibilität**: Einfach auf andere Branches oder Server erweiterbar
✅ **Dokumentiert**: Umfassende Dokumentation für Setup und Troubleshooting
✅ **Testbar**: Test-Script für einfache Verifizierung

## Erweiterungsmöglichkeiten

Die Implementierung kann leicht erweitert werden für:

1. **Multi-Environment Deployment**: Zusätzliche Workflows für `staging`, `production`
2. **Benachrichtigungen**: E-Mail oder Slack-Benachrichtigungen bei Deployment
3. **Rollback-Funktionalität**: Automatisches Rollback bei Fehlern
4. **IP-Whitelist**: Nur GitHub IPs akzeptieren
5. **Deployment-History**: Datenbank-Tracking aller Deployments
6. **Health Checks**: Post-Deployment Health Checks

## Technische Details

### Verwendete Technologien
- **GitHub Actions**: CI/CD Pipeline
- **Bash**: Workflow-Scripting und Test-Script
- **PHP 7.4+**: Webhook-Receiver
- **OpenSSL**: HMAC-SHA256 Signatur-Generierung
- **curl**: HTTP-Requests
- **Make**: Deployment-Automatisierung

### Dateistruktur
```
phpticketmailer/
├── .github/
│   └── workflows/
│       └── deploy-develop.yml          # GitHub Actions Workflow
├── webhook-receiver.php                # Webhook-Empfänger (für Server)
├── test-webhook.sh                     # Test-Script
├── DEPLOYMENT_WEBHOOK.md               # Vollständige Dokumentation
├── DEPLOYMENT_QUICKSTART.md            # Schnellstart-Anleitung
├── .env.local.example                  # Erweitert um WEBHOOK_SECRET
└── README.md                           # Erweitert um Deployment-Sektion
```

## Nächste Schritte

Für die Inbetriebnahme:

1. **Webhook-Secret generieren**: `openssl rand -hex 32`
2. **GitHub Secrets konfigurieren**: `DEPLOY_WEBHOOK_URL` und `DEPLOY_WEBHOOK_SECRET`
3. **Server vorbereiten**: Webhook-Receiver installieren und konfigurieren
4. **Web-Server einrichten**: nginx oder Apache für HTTPS-Endpoint
5. **Berechtigungen setzen**: www-data zur docker-Gruppe hinzufügen
6. **Testen**: Mit `test-webhook.sh` oder durch Push auf develop

Siehe [DEPLOYMENT_QUICKSTART.md](DEPLOYMENT_QUICKSTART.md) für eine schrittweise Anleitung.

## Support und Troubleshooting

Bei Problemen siehe:
- [DEPLOYMENT_WEBHOOK.md](DEPLOYMENT_WEBHOOK.md) - Abschnitt "Troubleshooting"
- Webhook-Logs: `/var/www/webhook/webhook-deploy.log`
- GitHub Actions Logs: Repository → Actions → Workflow Run
- Web-Server Logs: nginx/apache error logs

## Lizenz

Diese Implementierung ist Teil des phpticketmailer Projekts und unterliegt der gleichen Lizenz.
