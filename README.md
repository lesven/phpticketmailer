# Ticketumfrage-Tool

Eine Symfony-Anwendung zum automatisierten Versand von Zufriedenheitsanfragen per E-Mail an Benutzer nach Ticketabschluss.

## Funktionen

- Upload von CSV-Dateien mit Ticket-Informationen
- Automatischer E-Mail-Versand an die zugehörigen Benutzer
- Verwaltung von Benutzer-zu-E-Mail-Zuordnungen
- Anpassbares E-Mail-Template mit Platzhaltern
- Test- und Live-Modus für den E-Mail-Versand
- Protokollierung aller Versandaktionen

## Systemanforderungen

- PHP 7.4 oder höher
- MySQL 5.7 oder höher (oder MariaDB)
- Composer
- Symfony CLI (optional, für die lokale Entwicklung)
- Docker und Docker Compose (optional, für die Docker-Installation)

## Installation

Sie haben zwei Möglichkeiten, das Ticketumfrage-Tool zu installieren: direkt auf Ihrem System oder mit Docker.

### A. Installation mit Docker (empfohlen)

#### 1. Projekt klonen

```bash
git clone https://your-repository/ticketumfrage-tool.git
cd ticketumfrage-tool
```

#### 2. Docker-Container starten

```bash
docker-compose up -d
```

Dies startet automatisch:
- PHP-FPM Container (PHP 8.1)
- Nginx Webserver
- MySQL-Datenbank
- phpMyAdmin für die Datenbankverwaltung
- Mailhog für SMTP-Testing und E-Mail-Debugging

#### 3. Abhängigkeiten installieren und Datenbank einrichten

```bash
# Composer-Abhängigkeiten installieren
docker exec -it ticketumfrage_php bash -c "composer install"

# Datenbank-Migration durchführen
docker exec -it ticketumfrage_php bash -c "php bin/console doctrine:migrations:migrate --no-interaction"
```

#### 4. Konfiguration

Kopieren Sie `.env.local.example` nach `.env.local` und passen Sie die Einstellungen an:

```bash
cp .env.local.example .env.local
```

Für die Docker-Umgebung sollten Sie folgende Einstellungen verwenden:

```
DATABASE_URL="mysql://ticketuser:ticketpassword@database:3306/ticket_mailer_db?serverVersion=8.0&charset=utf8mb4"
MAILER_DSN=smtp://mailhog:1025
```

#### 5. Zugriff auf die Anwendung

- Ticketumfrage-Tool: http://localhost:8080
- phpMyAdmin: http://localhost:8081 (Benutzername: ticketuser, Passwort: ticketpassword)
- Mailhog (E-Mail-Test): http://localhost:8025

### B. Manuelle Installation

#### 1. Projekt klonen

```bash
git clone https://your-repository/ticketumfrage-tool.git
cd ticketumfrage-tool
```

#### 2. Abhängigkeiten installieren

```bash
composer install
```

#### 3. Konfiguration

Kopieren Sie `.env.local.example` nach `.env.local` und passen Sie die Einstellungen an:

```bash
cp .env.local.example .env.local
```

Bearbeiten Sie die `.env.local`-Datei und geben Sie Ihre Datenbank- und E-Mail-Konfiguration an.

#### 4. Datenbank erstellen und Tabellen anlegen

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

#### 5. Webserver starten

```bash
# Mit dem Symfony CLI
symfony server:start

# Oder mit dem eingebauten PHP-Webserver
php -S localhost:8000 -t public/
```

## Verwendung

### CSV-Dateiformat

Die hochzuladende CSV-Datei muss folgende Spalten enthalten:

- `ticketId`: Die ID des Tickets
- `username`: Der Benutzername des Ticketbearbeiters/Kunden
- `ticketName`: (optional) Der Name oder Titel des Tickets

Beispiel:
```
ticketId,username,ticketName
123456,mustermann,Problem mit Anmeldung
123457,schmidt,Fehler bei Dateiupload
```

### Workflow

1. **Benutzer anlegen**: Fügen Sie unter "Benutzer" Benutzernamen und E-Mail-Adressen hinzu.
2. **E-Mail-Template anpassen**: Optional können Sie unter "E-Mail-Template" die Vorlage für die E-Mails anpassen.
3. **CSV-Datei hochladen**: Laden Sie unter "CSV-Upload" eine CSV-Datei mit Ticket-Informationen hoch.
4. **E-Mails versenden**: Nach dem Upload werden E-Mails automatisch versendet oder Sie werden zur Eingabe fehlender E-Mail-Adressen aufgefordert.
5. **Ergebnisse prüfen**: Auf dem Dashboard und der Ergebnisseite können Sie den Status aller Versandaktionen einsehen.

## Platzhalter im E-Mail-Template

Im E-Mail-Template können folgende Platzhalter verwendet werden:

- `{{ticketId}}`: Die ID des Tickets
- `{{ticketName}}`: Der Name/Titel des Tickets
- `{{username}}`: Der Benutzername des Empfängers
- `{{ticketLink}}`: Ein Link zum Ticket im Ticketsystem

## Testmodus

Im Testmodus werden alle E-Mails an die in der Konfiguration angegebene Test-E-Mail-Adresse gesendet, anstatt an die tatsächlichen Empfänger. Dies ist nützlich, um den Versandprozess zu testen, ohne echte E-Mails zu versenden.

## Support und Kontakt

Bei Fragen oder Problemen wenden Sie sich bitte an das Support-Team unter support@example.com.