# Ticketumfrage-Tool

Eine Symfony-Anwendung zum automatisierten Versand von Zufriedenheitsanfragen per E-Mail an Benutzer nach Ticketabschluss.

## Funktionen

- Upload von CSV-Dateien mit Ticket-Informationen
- Automatischer E-Mail-Versand an die zugehörigen Benutzer
- Verwaltung von Benutzer-zu-E-Mail-Zuordnungen
- Anpassbares E-Mail-Template mit Platzhaltern
- Test- und Live-Modus für den E-Mail-Versand
- Protokollierung aller Versandaktionen
- Passwortgeschützte Benutzeroberfläche
- Import und Export von Benutzer-Daten als CSV

## Systemanforderungen

- PHP 8.3 oder höher
- MySQL 8.0 oder höher (oder MariaDB 10.11+)
- Composer
- Symfony 7.2
- Symfony CLI (optional, für die lokale Entwicklung)
- Docker und Docker Compose (optional, für die Docker-Installation)

## Installation

Sie haben zwei Möglichkeiten, das Ticketumfrage-Tool zu installieren: direkt auf Ihrem System oder mit Docker.

### A. Installation mit Docker (empfohlen)

#### Voraussetzungen
- Docker Engine (Version 20.10.0 oder höher)
- Docker Compose (Version 2.0.0 oder höher)
- Git

#### 1. Projekt klonen

```bash
git clone https://github.com/ihrsvcname/phpticketmailer.git
cd phpticketmailer
```

#### 2. Umgebungsvariablen konfigurieren

Kopieren Sie `.env` nach `.env.local` und passen Sie die Einstellungen an:

```bash
cp .env .env.local
```

Für die Docker-Umgebung sollten Sie folgende Einstellungen verwenden:

```
DATABASE_URL="mysql://ticketuser:ticketpassword@database:3306/ticket_mailer_db?serverVersion=mariadb-10.11.2&charset=utf8mb4"
MAILER_DSN=smtp://mailhog:1025
```

#### 3. Docker-Container starten

**Für Intel/AMD-Architekturen (Standard):**
```bash
docker compose up -d
```

**Für ARM-Architekturen (z.B. Apple Silicon oder Raspberry Pi):**
```bash
DOCKER_PLATFORM=linux/arm64v8 docker compose up -d
```

Oder für ältere Raspberry Pi Modelle:
```bash
DOCKER_PLATFORM=linux/arm/v7 docker compose up -d
```

#### 4. Abhängigkeiten installieren und Datenbank einrichten

```bash
# Composer-Abhängigkeiten installieren
docker exec -it ticketumfrage_php composer install

# Datenbank-Migration durchführen
docker exec -it ticketumfrage_php php bin/console doctrine:migrations:migrate --no-interaction
```

Hinweis: Falls die Container-Namen anders sind, überprüfen Sie diese mit `docker ps`.

#### 5. Zugriff auf die Anwendung

- Ticketumfrage-Tool: http://localhost:8090
- MySQL/MariaDB-Datenbank: localhost:3306 (Zugangsdaten: ticketuser/ticketpassword)
- MailHog (für E-Mail-Tests): http://localhost:8025

### B. Manuelle Installation

#### Voraussetzungen
- PHP 8.3 oder höher
- MySQL 8.0 oder höher (oder MariaDB 10.11+)
- Composer
- Git
- Symfony CLI (empfohlen)

#### 1. Projekt klonen

```bash
git clone https://github.com/ihrsvcname/phpticketmailer.git
cd phpticketmailer
```

#### 2. Abhängigkeiten installieren

```bash
composer install
```

#### 3. Konfiguration

Kopieren Sie `.env` nach `.env.local` und passen Sie die Einstellungen an:

```bash
cp .env .env.local
```

Bearbeiten Sie die `.env.local`-Datei und geben Sie Ihre Datenbank- und E-Mail-Konfiguration an, zum Beispiel:

```
DATABASE_URL="mysql://benutzername:passwort@127.0.0.1:3306/phpticketmailer?serverVersion=8.0.32&charset=utf8mb4"
# Oder für MariaDB:
# DATABASE_URL="mysql://benutzername:passwort@127.0.0.1:3306/phpticketmailer?serverVersion=mariadb-10.11.2&charset=utf8mb4"
MAILER_DSN=smtp://benutzername:passwort@ihrmailserver:25
```

#### 4. Datenbank erstellen und Tabellen anlegen

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

#### 5. Webserver starten

```bash
# Mit dem Symfony CLI (empfohlen)
symfony server:start

# Oder mit dem eingebauten PHP-Webserver
php -S localhost:8000 -t public/
```

Die Anwendung ist dann unter http://localhost:8000 verfügbar.

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

1. **Login**: Bei der ersten Nutzung ist das Standard-Passwort "geheim". Es wird empfohlen, dieses sofort zu ändern.
2. **Benutzer anlegen**: Fügen Sie unter "Benutzer" Benutzernamen und E-Mail-Adressen hinzu oder importieren Sie eine CSV-Datei mit Benutzern.
3. **E-Mail-Template anpassen**: Optional können Sie unter "E-Mail-Template" die Vorlage für die E-Mails anpassen.
4. **SMTP-Konfiguration prüfen**: Stellen Sie sicher, dass die SMTP-Einstellungen korrekt sind.
5. **CSV-Datei hochladen**: Laden Sie unter "CSV-Upload" eine CSV-Datei mit Ticket-Informationen hoch.
6. **E-Mails versenden**: Nach dem Upload werden E-Mails automatisch versendet oder Sie werden zur Eingabe fehlender E-Mail-Adressen aufgefordert.
7. **Ergebnisse prüfen**: Auf dem Dashboard und der Ergebnisseite können Sie den Status aller Versandaktionen einsehen.

## Multi-Architektur-Unterstützung

Das Ticketumfrage-Tool ist für die Ausführung auf verschiedenen Hardware-Architekturen konfiguriert:

### Unterstützte Architekturen

- **x86/x64 (Intel/AMD)**: Standard-Konfiguration
- **ARM64**: Für neuere Raspberry Pi Modelle (3/4/5) und andere ARM64-basierte Systeme
- **ARMv7**: Für ältere Raspberry Pi Modelle und kompatible Geräte

### Plattform-Konfiguration

Die Docker-Compose-Konfiguration verwendet eine Umgebungsvariable `DOCKER_PLATFORM`, um die Zielplattform festzulegen:

```bash
# Standard (Intel/AMD)
docker compose up -d

# ARM64 (z.B. Raspberry Pi 4)
DOCKER_PLATFORM=linux/arm64v8 docker compose up -d

# ARMv7 (z.B. ältere Raspberry Pi Modelle)
DOCKER_PLATFORM=linux/arm/v7 docker compose up -d
```

### Hinweise zur Performance

- Die Performance kann je nach Hardware-Architektur variieren
- Auf ARM-basierten Systemen mit begrenztem RAM (wie Raspberry Pi) empfehlen wir eine Erhöhung des Swap-Speichers
- Für produktiven Einsatz auf einem Raspberry Pi empfehlen wir mindestens ein Modell mit 4GB RAM

## Platzhalter im E-Mail-Template

Im E-Mail-Template können folgende Platzhalter verwendet werden:

- `{{ticketId}}`: Die ID des Tickets
- `{{ticketName}}`: Der Name/Titel des Tickets
- `{{username}}`: Der Benutzername des Empfängers
- `{{ticketLink}}`: Ein Link zum Ticket im Ticketsystem

## Testmodus

Im Testmodus werden alle E-Mails an die in der Konfiguration angegebene Test-E-Mail-Adresse gesendet, anstatt an die tatsächlichen Empfänger. Dies ist nützlich, um den Versandprozess zu testen, ohne echte E-Mails zu versenden.

## Sicherheit

- Die Anwendung ist passwortgeschützt. Das Standard-Passwort lautet "geheim".
- Ändern Sie das Passwort nach der ersten Anmeldung über den Menüpunkt "Passwort ändern".
- Das Passwort wird verschlüsselt in der Datenbank gespeichert.
- Es gibt kein Benutzermanagement mit unterschiedlichen Berechtigungen - jeder mit dem Passwort hat vollen Zugriff.

## Support und Kontakt

Bei Fragen oder Problemen wenden Sie sich bitte an das Support-Team unter sven.heising@gmail.com.