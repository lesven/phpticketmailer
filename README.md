# Ticketumfrage-Tool

Symfony-Anwendung zum automatisierten Versand von Zufriedenheitsanfragen per E-Mail nach Ticketabschluss.

## Funktionen

- CSV-Upload mit Ticket-Informationen
- Automatischer E-Mail-Versand 
- Benutzer-E-Mail-Verwaltung
- Anpassbares E-Mail-Template
- Test- und Live-Modus
- Versandprotokollierung
- Passwortschutz
- Systemüberwachung mit Zabbix-Integration

## Voraussetzungen

- PHP 8.3+
- MySQL 8.0+ oder MariaDB 10.11+
- Docker & Docker Compose

## Installation

### Docker (empfohlen)

```bash
git clone https://github.com/lesven/phpticketmailer.git
cd phpticketmailer
cp .env .env.local
```

Umgebung konfigurieren (`.env.local`):
```env
APP_SECRET=2cf7a8f6b1a3668d88ae797af6388f1a
DATABASE_URL="mysql://ticketuser:ticketpassword@database:3306/ticket_mailer_db?serverVersion=mariadb-10.11.2&charset=utf8mb4"
```

Container starten:
```bash
# Standard (Intel/AMD)
docker compose up -d

# ARM (Raspberry Pi)
DOCKER_PLATFORM=linux/arm64v8 docker compose up -d
```

Setup abschließen:
```bash
docker exec -it ticketumfrage_php composer install
docker exec -it ticketumfrage_php php bin/console doctrine:migrations:migrate --no-interaction
```

**Zugriff:** http://localhost:8090

### Manuelle Installation

```bash
git clone https://github.com/ihrsvcname/phpticketmailer.git
cd phpticketmailer
composer install
cp .env .env.local
```

Konfiguration anpassen (`.env.local`):
```env
APP_SECRET=HIER_EINEN_SICHEREN_WERT_EINTRAGEN
DATABASE_URL="mysql://user:password@127.0.0.1:3306/phpticketmailer?serverVersion=8.0.32&charset=utf8mb4"
```

Datenbank einrichten:
```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
symfony server:start
```

## Verwendung

**Standard-Login:** Passwort "geheim" (sofort ändern!)

### Ersteinrichtung
1. **SMTP konfigurieren**: Gehen Sie zu "SMTP-Konfiguration" und geben Sie Ihre E-Mail-Server-Daten ein
2. **Benutzer anlegen**: Unter "Benutzer" Benutzernamen und E-Mail-Adressen hinzufügen
3. **E-Mail-Template anpassen** (optional): Unter "E-Mail-Template"

### CSV-Format
```csv
ticketId,username,ticketName
123456,mustermann,Problem mit Anmeldung
123457,schmidt,Fehler bei Dateiupload
```

### Workflow
1. CSV-Datei unter "CSV-Upload" hochladen
2. E-Mails werden automatisch versendet
3. Ergebnisse im Dashboard prüfen

### E-Mail-Platzhalter
- `{{ticketId}}`: Ticket-ID
- `{{ticketName}}`: Ticket-Titel  
- `{{username}}`: Benutzername
- `{{ticketLink}}`: Link zum Ticket

### Zusätzliche Services (Docker)
- **MailHog** (E-Mail-Test): http://localhost:8025
- **Datenbank**: localhost:3306 (ticketuser/ticketpassword)

## Automatisches Deployment

Das Projekt unterstützt automatisches Deployment über GitHub Actions und Webhooks. Bei jeder Änderung im `develop` Branch wird automatisch ein Deployment auf dem Test-Server ausgelöst.

### Quick Start

1. **GitHub Secrets konfigurieren**:
   - `DEPLOY_WEBHOOK_URL`: https://ihr-server.example.com/deploy-webhook
   - `DEPLOY_WEBHOOK_SECRET`: Generiert mit `openssl rand -hex 32`

2. **Server vorbereiten**:
   - Webhook-Receiver auf HTTPS-Server installieren
   - `WEBHOOK_SECRET` in `.env` konfigurieren
   - Web-Server (nginx/Apache) konfigurieren

3. **Deployment auslösen**:
   ```bash
   git push origin develop
   ```

**Detaillierte Anleitung:** Siehe [DEPLOYMENT_WEBHOOK.md](DEPLOYMENT_WEBHOOK.md)

## Systemüberwachung

Die Anwendung stellt eine Überwachungsschnittstelle bereit, die in Zabbix oder andere Monitoring-Systeme integriert werden kann.

### Monitoring-Endpunkte

- **/monitoring/health**: Gesamtstatus des Systems
- **/monitoring/database**: Datenbankstatus
- **/monitoring/webserver**: Webserverstatus
- **/monitoring/containers**: Docker-Container-Status

Alle Endpunkte liefern JSON-Daten zurück.

### Zabbix-Integration

Details zur Zabbix-Integration finden Sie in der [Zabbix-Dokumentation](ZABBIX_MONITORING.md).

## Support

sven.heising@gmail.com